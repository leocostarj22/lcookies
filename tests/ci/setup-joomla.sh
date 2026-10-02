#!/usr/bin/env bash
# Downloads and installs a Joomla site for the tests (used by .github/workflows/ci.yml, also locally).
#
# Usage: tests/ci/setup-joomla.sh <joomla version> <folder> <site url>
#   e.g. tests/ci/setup-joomla.sh 6.0.0 /tmp/joomla http://127.0.0.1:8000
#
# Env (database, which must exist and be empty):
#   DB_TYPE (mysql or pgsql), DB_HOST (host:port), DB_USER, DB_PASS, DB_NAME
#   LC_PHP (PHP CLI binary, default php)
# The site gets the table prefix jos_ and the administrator admin / Admin123456789! (what the
# tests expect), and $live_site = <site url> (needed by Joomla 6 behind `php -S`).

set -euo pipefail
VERSION=$1; DIR=$2; URL=$3
PHP=${LC_PHP:-php}

mkdir -p "$DIR"
curl -sSfL "https://github.com/joomla/joomla-cms/releases/download/$VERSION/Joomla_$VERSION-Stable-Full_Package.tar.gz" | tar -xz -C "$DIR"

(cd "$DIR" && "$PHP" installation/joomla.php install -n \
    --site-name="LCookies tests" --admin-user="Admin" --admin-username=admin --admin-password='Admin123456789!' \
    --admin-email=admin@example.com --db-type="$DB_TYPE" --db-host="$DB_HOST" --db-user="$DB_USER" \
    --db-pass="$DB_PASS" --db-name="$DB_NAME" --db-prefix=jos_ --db-encryption=0)

# The installer removes the installation folder on success; make sure, then set the site address.
rm -rf "$DIR/installation"
sed -i "s|public \$live_site = '[^']*';|public \$live_site = '$URL';|" "$DIR/configuration.php"
echo "Joomla $VERSION installed in $DIR ($DB_TYPE)"
