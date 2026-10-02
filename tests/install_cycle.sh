#!/usr/bin/env bash
# Install, upgrade and uninstall test of the LCookies package on a Joomla site.
#
# Usage:
#   tests/install_cycle.sh upgrade   <joomla root> <new zip> [old zip]   (re)install old (if given), add data, install new, check
#   tests/install_cycle.sh uninstall <joomla root>                       uninstall the package, check nothing is left
#
# Env: LC_PHP (PHP CLI binary, default php). The site's $live_site is blanked during the installer
# runs (Joomla 5.2 CLI installs fail with it) and restored afterwards.

set -u
ACTION=${1:-}; ROOT=${2:-}; NEW=${3:-}; OLD=${4:-}
PHP=${LC_PHP:-php}
HERE=$(cd "$(dirname "$0")" && pwd)
FAILS=0

if [[ -z "$ACTION" || ! -f "$ROOT/configuration.php" ]]; then
    echo "Usage: $0 upgrade <joomla root> <new zip> [old zip] | uninstall <joomla root>" >&2
    exit 2
fi

ROOT=$(cd "$ROOT" && pwd)
# The installer runs from the Joomla root: make the package paths absolute.
abspath() { [[ -z "$1" ]] && return; echo "$(cd "$(dirname "$1")" && pwd)/$(basename "$1")"; }
NEW=$(abspath "$NEW"); OLD=$(abspath "$OLD")
q() { echo "$1" | "$PHP" "$HERE/sql.php" "$ROOT"; }
check() { if [[ "$2" == "$3" ]]; then echo "PASS $1"; else echo "FAIL $1 -> expected [$3], got [$2]"; FAILS=$((FAILS + 1)); fi; }
joomla() {
    local site
    site=$("$PHP" "$HERE/fixture.php" "$ROOT" livesite '')
    (cd "$ROOT" && "$PHP" cli/joomla.php "$@" 2>&1 | grep -E "\[(OK|ERROR|WARNING)\]" | head -3)
    "$PHP" "$HERE/fixture.php" "$ROOT" livesite "$site" > /dev/null
}
# information_schema of this site's database only (a server may hold several sites).
[[ $("$PHP" "$HERE/sql.php" "$ROOT" --type) == pgsql ]] && SCHEMA="table_schema = current_schema()" || SCHEMA="table_schema = DATABASE()"
package_id() { q "SELECT extension_id FROM jos_extensions WHERE element = 'pkg_lcookies'"; }
extensions() { q "SELECT COUNT(*) FROM jos_extensions WHERE element IN ('pkg_lcookies', 'com_lcookies', 'mod_lcookies') OR (type = 'plugin' AND element = 'lcookies')"; }

case "$ACTION" in
upgrade)
    VERSION=$("$PHP" -r '$z = new ZipArchive(); $z->open($argv[1]); preg_match("#<version>([^<]+)</version>#", (string) $z->getFromName("pkg_lcookies.xml"), $m); echo $m[1] ?? "";' "$NEW")

    if [[ -n "$OLD" ]]; then
        ID=$(package_id); [[ -n "$ID" ]] && joomla extension:remove "$ID" -n
        echo "-- install $(basename "$OLD")"; joomla extension:install --path="$OLD"
        q "INSERT INTO jos_lcookies_consents (consent_uuid, action, categories, policy_version, ip_hash, ua_hash, url, language, created) VALUES ('eeeeeeee-0000-4000-8000-000000000001', 'accept_all', '[\"necessary\"]', 1, '', '', '', 'en-GB', NOW())"
        q "UPDATE jos_lcookies_services SET title = 'Kept by the upgrade' WHERE alias = 'website'"
    fi

    echo "-- install $(basename "$NEW") ($VERSION)"; joomla extension:install --path="$NEW"

    check "7 extensions + package installed" "$(extensions)" "8"
    check "all at version $VERSION" "$(q "SELECT COUNT(*) FROM jos_extensions WHERE (element IN ('pkg_lcookies', 'com_lcookies', 'mod_lcookies') OR (type = 'plugin' AND element = 'lcookies')) AND manifest_cache LIKE '%\"version\":\"$VERSION\"%'")" "8"
    check "all plugins enabled" "$(q "SELECT COUNT(*) FROM jos_extensions WHERE type = 'plugin' AND element = 'lcookies' AND enabled = 1")" "5"
    check "database schema up to date" "$(q "SELECT s.version_id FROM jos_schemas s JOIN jos_extensions e ON e.extension_id = s.extension_id WHERE e.element = 'com_lcookies'")" \
        "$(ls "$HERE/../src/com_lcookies/admin/sql/updates/mysql" | sed 's/\.sql$//' | sort -V | tail -1)"
    check "tables" "$(q "SELECT COUNT(*) FROM information_schema.tables WHERE $SCHEMA AND table_name IN ('jos_lcookies_categories', 'jos_lcookies_services', 'jos_lcookies_cookies', 'jos_lcookies_consents', 'jos_lcookies_scans')")" "5"
    check "mail template of the scheduled scan" "$(q "SELECT COUNT(*) FROM jos_mail_templates WHERE template_id = 'plg_task_lcookies.scan'")" "1"
    check "backend submenu with the scanner" "$(q "SELECT COUNT(*) FROM jos_menu WHERE client_id = 1 AND link LIKE '%option=com_lcookies&view=scanner%'")" "1"

    if [[ -n "$OLD" ]]; then
        check "consent records kept" "$(q "SELECT COUNT(*) FROM jos_lcookies_consents WHERE consent_uuid = 'eeeeeeee-0000-4000-8000-000000000001'")" "1"
        check "edited data kept" "$(q "SELECT title FROM jos_lcookies_services WHERE alias = 'website'")" "Kept by the upgrade"
        q "DELETE FROM jos_lcookies_consents WHERE consent_uuid = 'eeeeeeee-0000-4000-8000-000000000001'"
        q "UPDATE jos_lcookies_services SET title = 'COM_LCOOKIES_SVC_WEBSITE' WHERE alias = 'website'"
    fi
    ;;
uninstall)
    q "INSERT INTO jos_scheduler_tasks (asset_id, title, type, execution_rules, cron_rules, state, last_exit_code, times_executed, times_failed, locked, priority, ordering, params, note, created, created_by, next_execution) VALUES (0, 'LCookies purge', 'lcookies.purge', '{}', '{}', 1, 0, 0, 0, NULL, 0, 0, '{}', '', NOW(), 0, NOW())"
    ID=$(package_id)
    echo "-- uninstall package $ID"; joomla extension:remove "$ID" -n

    check "extensions removed" "$(extensions)" "0"
    check "tables dropped" "$(q "SELECT COUNT(*) FROM information_schema.tables WHERE $SCHEMA AND table_name LIKE 'jos_lcookies%'")" "0"
    check "scheduled tasks removed" "$(q "SELECT COUNT(*) FROM jos_scheduler_tasks WHERE type LIKE 'lcookies.%'")" "0"
    check "mail templates removed" "$(q "SELECT COUNT(*) FROM jos_mail_templates WHERE extension = 'plg_task_lcookies'")" "0"
    check "backend menu removed" "$(q "SELECT COUNT(*) FROM jos_menu WHERE client_id = 1 AND link LIKE '%option=com_lcookies%'")" "0"
    check "media removed" "$(ls -d "$ROOT"/media/com_lcookies "$ROOT"/media/plg_system_lcookies "$ROOT"/media/mod_lcookies 2>/dev/null | wc -l | tr -d ' ')" "0"
    ;;
*)
    echo "Unknown action $ACTION" >&2; exit 2;;
esac

if [[ $FAILS -gt 0 ]]; then echo "FAILED: $FAILS"; exit 1; fi
echo "ALL PASSED"
