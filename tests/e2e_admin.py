"""End-to-end test of the com_lcookies backend against a running Joomla site.

Usage: python3 tests/e2e_admin.py <site-url> <database-name>
  e.g. python3 tests/e2e_admin.py http://127.0.0.1:8106 j6      (MySQL/MariaDB)
       python3 tests/e2e_admin.py http://127.0.0.1:8160 j6pg    (PostgreSQL: name ends in "pg")

Environment (defaults match the local test setup described in docs/fases/fase-1.md):
  LC_ENV     folder with php, mariadb/ and my.sock   (default /tmp/claude-1000/lc)
  LC_ADMIN   administrator username / password      (default admin / Admin123456789!)
The test resets the LCookies tables and expects table prefix jos_.
"""

import http.cookiejar, json, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request
from pathlib import Path

BASE = sys.argv[1]
DB = sys.argv[2]
L = os.environ.get("LC_ENV", "/tmp/claude-1000/lc")
ROOT = Path(__file__).resolve().parent.parent
ADMIN_USER, ADMIN_PASS = os.environ.get("LC_ADMIN", "admin:Admin123456789!").split(":", 1)
jar = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
fails = []


def sql(q):
    if DB.endswith("pg"):
        q = re.sub(r"GROUP_CONCAT\((\w+) ORDER BY (\w+)\)", r"string_agg(\1, ',' ORDER BY \2)", q)
        r = subprocess.run([f"{L}/php", str(ROOT / "tests" / "pgq.php"), DB], input=q, capture_output=True, text=True)
        if r.stderr.strip():
            print("SQLERR", r.stderr.strip()[:300])
        return r.stdout.strip()
    return subprocess.run([f"{L}/mariadb/bin/mariadb", "--no-defaults", "-S", f"{L}/my.sock", "-uroot", "-N", DB, "-e", q],
                          capture_output=True, text=True).stdout.strip()


def req(path, data=None, opener=None, raw=False):
    url = BASE + "/administrator/index.php" + path
    body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    try:
        r = (opener or op).open(url, body)
        if raw:
            return r
        with r:
            return r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        if raw:
            return e
        return e.read().decode("utf-8", "replace")


def login(opener, user, password):
    html = req("", opener=opener)
    return req("", {"username": user, "passwd": password, "option": "com_login", "task": "login",
                    "return": "aW5kZXgucGhw", token(html): "1"}, opener=opener)


def site_consent(uuid, cats, action, path="/"):
    body = json.dumps({"consent": {"id": uuid, "v": 1, "cats": cats, "ts": 0}, "action": action, "url": BASE + path}).encode()
    request = urllib.request.Request(BASE + "/index.php?option=com_lcookies&task=consent.save&format=json", body,
                                     {"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(request) as r:
            return r.status
    except urllib.error.HTTPError as e:
        return e.code


def token(html):
    m = re.search(r'name="([a-f0-9]{32})" value="1"', html)
    return m.group(1)


def check(name, cond, extra=""):
    print(("PASS " if cond else "FAIL ") + name + ("" if cond else "  -> " + str(extra)))
    if not cond:
        fails.append(name)


def clean(html, name):
    html = re.sub(r'value="COM_LCOOKIES_[A-Z_]+"', '', html)
    html = re.sub(r'>COM_LCOOKIES_[A-Z_]+</textarea>', '', html)
    html = html.replace('(e.g. COM_LCOOKIES_CAT_STATISTICS)', '')
    bad = re.findall(r"(Fatal error|Warning:|Notice:|Deprecated:|Uncaught|Stack trace|\?\?COM_LCOOKIES|COM_LCOOKIES_[A-Z_]+)", html)
    check(name + " no PHP errors/untranslated keys", not bad, str(sorted(set(bad))[:8]))


def messages(html):
    return re.findall(r'<joomla-alert[^>]*type="([a-z]+)"[^>]*>.*?<div class="alert-message">(.*?)</div>', html, re.S) or \
        re.findall(r'"(error|warning|message|success)":\["(.*?)"\]', html)


# Reset data
install = open(ROOT / "src/com_lcookies/admin/sql" / ("install." + ("postgresql" if DB.endswith("pg") else "mysql") + ".utf8.sql")).read().replace("#__", "jos_")
sql("DROP TABLE IF EXISTS jos_lcookies_consents, jos_lcookies_cookies, jos_lcookies_services, jos_lcookies_categories; " + install)
check("reset", sql("SELECT COUNT(*) FROM jos_lcookies_categories") == "5")

# Login
html = login(op, ADMIN_USER, ADMIN_PASS)
check("login", "com_login" not in html or "logout" in html.lower(), "")

# Lists
for view, expect in [("categories", "Strictly necessary"), ("services", "This website"), ("cookies", "joomla_user_state")]:
    html = req(f"?option=com_lcookies&view={view}")
    check(f"list {view} renders", expect in html, html[:300])
    clean(html, f"list {view}")
html = req("?option=com_lcookies&view=cookies")
check("duration rendered", "180 days" in html and "Session" in html)
check("regex badge", "Regular expression" in html)

# Options page
html = req("?option=com_config&view=component&component=com_lcookies")
check("options renders", "Policy Version" in html and "Consent Mode" in html)
clean(html, "options")

# Edit core category: alias/required/state locked
html = req("?option=com_lcookies&task=category.edit&id=1")
check("edit core category renders", 'id="category-form"' in html)
clean(html, "edit category")
check("core alias readonly", re.search(r'name="jform\[alias\]"[^>]*readonly', html) is not None)
tok = token(html)
html = req("?option=com_lcookies&view=category&layout=edit&id=1", {
    "jform[title]": "COM_LCOOKIES_CAT_NECESSARY", "jform[alias]": "hacked", "jform[required]": "0", "jform[state]": "0",
    "jform[description]": "COM_LCOOKIES_CAT_NECESSARY_DESC", "jform[gcm_types][]": ["security_storage"], "jform[id]": "1",
    "task": "category.save", tok: "1"})
row = sql("SELECT alias, required, state, core FROM jos_lcookies_categories WHERE id=1")
check("core category stays locked after tampered save", row == "necessary\t1\t1\t1", row)

# Clear all GCM checkboxes on statistics
html = req("?option=com_lcookies&task=category.edit&id=3")
tok = token(html)
req("?option=com_lcookies&view=category&layout=edit&id=3", {
    "jform[title]": "COM_LCOOKIES_CAT_STATISTICS", "jform[alias]": "statistics", "jform[required]": "0", "jform[state]": "1",
    "jform[description]": "x", "jform[id]": "3", "jform[core]": "1", "task": "category.save", tok: "1"})
row = sql("SELECT gcm_types, core FROM jos_lcookies_categories WHERE id=3")
check("unchecking all GCM types saves []; core not injectable", row == "[]\t0", row)

# Save as copy of statistics -> unique alias, translated title, unpublished
html = req("?option=com_lcookies&task=category.edit&id=3")
tok = token(html)
req("?option=com_lcookies&view=category&layout=edit&id=3", {
    "jform[title]": "COM_LCOOKIES_CAT_STATISTICS", "jform[alias]": "statistics", "jform[required]": "0", "jform[state]": "1",
    "jform[description]": "x", "jform[gcm_types][]": ["analytics_storage"], "jform[id]": "3", "task": "category.save2copy", tok: "1"})
row = sql("SELECT alias, title, state, core FROM jos_lcookies_categories ORDER BY id DESC LIMIT 1")
check("save2copy unique alias + translated title", row == "statistics-2\tStatistics (2)\t0\t0", row)
copy_id = sql("SELECT id FROM jos_lcookies_categories WHERE alias='statistics-2'")
req("?option=com_lcookies&task=category.cancel", {"task": "category.cancel", tok: "1"})

# New service in copied category with an invalid regex pattern -> error, then valid
html = req("?option=com_lcookies&task=service.add")
clean(html, "new service form")
check("service category field lists translated titles", "Strictly necessary" in html and "Statistics (2)" in html)
tok = token(html)
html = req("?option=com_lcookies&view=service&layout=edit", {
    "jform[title]": "Google Analytics", "jform[alias]": "", "jform[category_id]": copy_id, "jform[provider]": "Google",
    "jform[privacy_url]": "https://policies.google.com/privacy", "jform[block_patterns]": "googletagmanager.com\n/gtag\\(/\n/[invalid/",
    "jform[state]": "1", "jform[id]": "0", "task": "service.save", tok: "1"})
check("invalid block regex rejected", "not a valid regular expression" in html, str(messages(html))[:300])
html = req("?option=com_lcookies&task=service.add")
tok = token(html)
html = req("?option=com_lcookies&view=service&layout=edit", {
    "jform[title]": "Google Analytics", "jform[alias]": "", "jform[category_id]": copy_id, "jform[provider]": "Google",
    "jform[privacy_url]": "https://policies.google.com/privacy", "jform[block_patterns]": "  googletagmanager.com  \n\n/gtag\\(/\n",
    "jform[head_code]": "<script>gtag('config','G-X');</script>",
    "jform[state]": "1", "jform[id]": "0", "task": "service.save", tok: "1"})
row = sql("SELECT id, alias, block_patterns, head_code, ordering FROM jos_lcookies_services WHERE title='Google Analytics'")
check("service saved, alias generated, patterns normalised, raw code kept",
      row.startswith("") and "\tgoogle-analytics\tgoogletagmanager.com\\n/gtag\\\\(/\t<script>gtag('config','G-X');</script>\t1" in row, row)
svc_id = row.split("\t")[0] if row else "0"

# Cookies: invalid regex rejected, valid saved
for name, mt, ok in [("_ga[", "regex", False), ("^_ga_[A-Z0-9]+$", "regex", True), ("_ga", "exact", True)]:
    html = req("?option=com_lcookies&task=cookie.add")
    tok = token(html)
    html = req("?option=com_lcookies&view=cookie&layout=edit", {
        "jform[name]": name, "jform[match_type]": mt, "jform[service_id]": svc_id, "jform[type]": "cookie",
        "jform[duration_unit]": "year", "jform[duration_value]": "2", "jform[description]": "GA", "jform[source]": "scanner",
        "jform[state]": "1", "jform[id]": "0", "task": "cookie.save", tok: "1"})
    exists = sql(f"SELECT COUNT(*) FROM jos_lcookies_cookies WHERE name='{name}'") == "1"
    check(f"cookie {name!r} saved={ok}", exists == ok)
html = req("?option=com_lcookies&view=cookies")
check("2 years duration listed", "2 years" in html)
clean(html, "cookies after save")

# Filters via URL
html = req(f"?option=com_lcookies&view=cookies&filter[service_id]={svc_id}")
check("cookie filter by service", "_ga" in html and "joomla_user_state" not in html)
req("?option=com_lcookies&view=cookies&filter[service_id]=")
html = req(f"?option=com_lcookies&view=services&filter[category_id]={copy_id}")
check("service filter by category", "Google Analytics" in html and "This website" not in html)
req("?option=com_lcookies&view=services&filter[category_id]=")

# Core category cannot be unpublished / trashed
html = req("?option=com_lcookies&view=categories")
tok = token(html)
html = req("?option=com_lcookies&view=categories", {"cid[]": ["1"], "task": "categories.unpublish", tok: "1"})
check("core unpublish refused", sql("SELECT state FROM jos_lcookies_categories WHERE id=1") == "1" and "must stay published" in html)

# Category with services cannot be deleted (trash then delete)
req("?option=com_lcookies&view=categories", {"cid[]": [copy_id], "task": "categories.trash", token(req('?option=com_lcookies&view=categories')): "1"})
html = req("?option=com_lcookies&view=categories&filter[published]=-2")
html = req("?option=com_lcookies&view=categories", {"cid[]": [copy_id], "task": "categories.delete", token(html): "1"})
check("category with services not deleted", sql(f"SELECT COUNT(*) FROM jos_lcookies_categories WHERE id={copy_id}") == "1" and "still has 1 service" in html,
      str(messages(html)))
req("?option=com_lcookies&view=categories&filter[published]=")

# Deleting a service cascades to its cookies
html = req("?option=com_lcookies&view=services")
req("?option=com_lcookies&view=services", {"cid[]": [svc_id], "task": "services.trash", token(html): "1"})
html = req("?option=com_lcookies&view=services&filter[published]=-2")
html = req("?option=com_lcookies&view=services", {"cid[]": [svc_id], "task": "services.delete", token(html): "1"})
check("service deleted + cookies cascaded",
      sql(f"SELECT COUNT(*) FROM jos_lcookies_services WHERE id={svc_id}") == "0" and sql(f"SELECT COUNT(*) FROM jos_lcookies_cookies WHERE service_id={svc_id}") == "0")
req("?option=com_lcookies&view=services&filter[published]=")

# Now the copied category is empty -> delete works
html = req("?option=com_lcookies&view=categories&filter[published]=-2")
req("?option=com_lcookies&view=categories", {"cid[]": [copy_id], "task": "categories.delete", token(html): "1"})
check("empty trashed category deleted", sql(f"SELECT COUNT(*) FROM jos_lcookies_categories WHERE id={copy_id}") == "0")
req("?option=com_lcookies&view=categories&filter[published]=")

# Drag & drop ordering (ajax)
html = req("?option=com_lcookies&view=categories")
tok = token(html)
req(f"?option=com_lcookies&task=categories.saveOrderAjax&tmpl=component", {"cid[]": ["2", "1", "3", "4", "5"], "order[]": ["1", "2", "3", "4", "5"], tok: "1"})
row = sql("SELECT GROUP_CONCAT(alias ORDER BY ordering) FROM jos_lcookies_categories")
check("ajax reorder", row.startswith("preferences,necessary"), row)

# Direct access to edit layout without checkout is refused
html = req("?option=com_lcookies&view=service&layout=edit&id=1")
check("direct edit access refused", "You are not permitted to use that link" in html or "JLIB_APPLICATION_ERROR_UNHELD_ID" not in html and 'id="service-form"' not in html)

# Batch: move services to another category -----------------------------------------------------------
html = req("?option=com_lcookies&view=services")
check("batch dialog in the services list", 'id="joomla-dialog-batch"' in html and 'name="batch[service_category_id]"' in html)
before = sql("SELECT category_id, ordering FROM jos_lcookies_services WHERE id = 1")
html = req("?option=com_lcookies&view=services", {"task": "service.batch", "cid[]": ["1"], "batch[service_category_id]": "3", token(html): "1"})
row = sql("SELECT category_id FROM jos_lcookies_services WHERE id = 1")
check("batch moves the service", row == "3" and "Batch process completed" in html, (row, str(messages(html))[:200]))
html = req("?option=com_lcookies&view=services", {"task": "service.batch", "cid[]": ["1"], "batch[service_category_id]": "99999", token(html): "1"})
check("batch to an unknown category refused", sql("SELECT category_id FROM jos_lcookies_services WHERE id = 1") == "3")
html = req("?option=com_lcookies&view=services", {"task": "service.batch", "cid[]": ["1"], "batch[service_category_id]": "1", token(html): "1"})
check("batch moves it back", sql("SELECT category_id FROM jos_lcookies_services WHERE id = 1") == "1")
clean(html, "services after batch")

# Service library, export and import ---------------------------------------------------------------
def upload(path, fields, filename, content):
    boundary = "----lcookies" + str(abs(hash(content)))
    body = b""
    for k, v in fields.items():
        body += f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode()
    body += (f'--{boundary}\r\nContent-Disposition: form-data; name="import_file"; filename="{filename}"\r\n'
             f'Content-Type: application/json\r\n\r\n').encode() + content + f"\r\n--{boundary}--\r\n".encode()
    request = urllib.request.Request(BASE + "/administrator/index.php" + path, body, {"Content-Type": f"multipart/form-data; boundary={boundary}"})
    with op.open(request) as r:
        return r.read().decode("utf-8", "replace")


html = req("?option=com_lcookies&view=presets")
check("library renders", "Google Analytics 4" in html and "Microsoft Clarity" in html and 'value="google-analytics"' in html, html[:300])
clean(html, "library")
html = req("?option=com_lcookies&view=services")
check("services toolbar links to the library", "view=presets" in html)
html = req("?option=com_lcookies&view=presets")
html = req("?option=com_lcookies&view=presets", {"task": "transfer.preset", "preset": "google-analytics", token(html): "1"})
row = sql("SELECT s.block_patterns, c.alias, (SELECT COUNT(*) FROM jos_lcookies_cookies k WHERE k.service_id = s.id AND k.source = 'preset') "
          "FROM jos_lcookies_services s JOIN jos_lcookies_categories c ON c.id = s.category_id WHERE s.alias = 'google-analytics'")
check("preset added with patterns, category and cookies", row == "googletagmanager.com/gtag/js\\ngoogle-analytics.com\tstatistics\t2", row)
check("library shows it as added", "Services: 1 added" in html and 'value="google-analytics"' not in html, str(messages(html))[:300])
html = req("?option=com_lcookies&view=presets", {"task": "transfer.preset", "preset": "google-analytics", token(html): "1"})
check("adding it again skips it", "Services: 0 added, 0 updated, 1 skipped" in html and sql("SELECT COUNT(*) FROM jos_lcookies_services WHERE alias = 'google-analytics'") == "1")

tok = token(html)
exported = json.loads(req(f"?option=com_lcookies&task=transfer.export&{tok}=1"))
ga = [x for x in exported.get("services", []) if x["alias"] == "google-analytics"]
check("export: format, categories, services with cookies", exported.get("format") == "lcookies" and exported.get("version") == 1
      and any(c["alias"] == "necessary" for c in exported["categories"]) and ga and len(ga[0]["cookies"]) == 2, str(exported)[:300])

ga[0]["title"] = "GA renamed"
ga[0]["cookies"] = ga[0]["cookies"][:1]
data = {"format": "lcookies", "version": 1,
        "categories": [{"alias": "lcimport-cat", "title": "Imported category", "gcm_types": ["ad_storage", "bogus"]},
                       {"alias": "necessary", "title": "Hacked", "required": 0, "state": 0}],
        "services": [ga[0],
                     {"alias": "lcimport-svc", "category": "lcimport-cat", "title": "Imported service", "block_patterns": ["lcimport.js"],
                      "cookies": [{"name": "_lcimp", "duration_value": 1, "duration_unit": "year"}, {"name": "bad[", "match_type": "regex"}]},
                     {"alias": "lcimport-orphan", "category": "does-not-exist", "title": "Orphan service"}]}
payload = json.dumps(data).encode()
html = upload("?option=com_lcookies&view=presets", {"task": "transfer.import", token(html): "1"}, "lcookies.json", payload)
check("import adds new items, skips existing ones", "Categories: 1 added, 0 updated, 1 skipped. Services: 2 added, 0 updated, 1 skipped. Cookies added: 1." in html,
      str(messages(html))[:400])
check("import reports invalid cookies", "bad[" in html and "regular expression" in html.lower())
row = sql("SELECT c.gcm_types FROM jos_lcookies_categories c WHERE c.alias = 'lcimport-cat'")
check("imported category keeps only known Consent Mode types", row == '["ad_storage"]', row)
row = sql("SELECT c.alias FROM jos_lcookies_services s JOIN jos_lcookies_categories c ON c.id = s.category_id WHERE s.alias = 'lcimport-orphan'")
check("unknown category -> unclassified", row == "unclassified", row)
html = req("?option=com_lcookies&view=presets")
html = upload("?option=com_lcookies&view=presets", {"task": "transfer.import", "overwrite": "1", token(html): "1"}, "lcookies.json", payload)
row = sql("SELECT s.title, (SELECT COUNT(*) FROM jos_lcookies_cookies k WHERE k.service_id = s.id) FROM jos_lcookies_services s WHERE s.alias = 'google-analytics'")
check("overwrite updates the service and replaces its cookies", row == "GA renamed\t1", row)
row = sql("SELECT alias, title, required, state, gcm_types FROM jos_lcookies_categories WHERE id = 1")
check("core category stays locked on import", row.startswith("necessary\tHacked\t1\t1"), row)
check("update keeps fields missing from the file", row.endswith('["security_storage"]'), row)
html = req("?option=com_lcookies&view=presets")
html = upload("?option=com_lcookies&view=presets", {"task": "transfer.import", token(html): "1"}, "x.json", b'{"hello": 1}')
check("invalid file refused", "not an LCookies export" in html, str(messages(html))[:300])
html = req("?option=com_lcookies&view=presets")
clean(html, "library after import")

# Leave the default data for the other tests (e2e_api.py, e2e_front.mjs).
sql("DELETE FROM jos_lcookies_cookies WHERE service_id IN (SELECT id FROM jos_lcookies_services WHERE alias IN "
    "('google-analytics', 'lcimport-svc', 'lcimport-orphan'))")
sql("DELETE FROM jos_lcookies_services WHERE alias IN ('google-analytics', 'lcimport-svc', 'lcimport-orphan')")
sql("DELETE FROM jos_lcookies_categories WHERE alias = 'lcimport-cat'")
sql("UPDATE jos_lcookies_categories SET title = 'COM_LCOOKIES_CAT_NECESSARY', description = 'COM_LCOOKIES_CAT_NECESSARY_DESC' WHERE id = 1")

# Consent records ----------------------------------------------------------------------------------
U1, U2 = "11111111-2222-4333-8444-555555555555", "aaaaaaaa-bbbb-4ccc-9ddd-eeeeeeeeeeee"
codes = [site_consent(U1, ["necessary", "statistics"], "custom", "/index.php?email=x@y.z"),
         site_consent(U1, ["necessary"], "reject_all"),
         site_consent(U2, ["necessary", "preferences", "statistics", "marketing"], "accept_all")]
check("site endpoint records consents", codes == [200, 200, 200], str(codes))
old = "NOW() - INTERVAL '30 months'" if DB.endswith("pg") else "NOW() - INTERVAL 30 MONTH"
sql("INSERT INTO jos_lcookies_consents (consent_uuid, action, categories, policy_version, ip_hash, ua_hash, url, language, created) VALUES "
    f"('cccccccc-dddd-4eee-8fff-000000000000', 'custom', '[\"necessary\"]', 1, '', '', '=HYPERLINK(1)', 'en-GB', {old})")

html = req("?option=com_lcookies&view=consents&filter[search]=&list[fullordering]=a.id%20DESC")
check("consents list renders", U1 in html and U2 in html and "Accepted all" in html and "Rejected all" in html, html[:300])
clean(html, "consents list")
check("consents list shows guest + category titles", "Guest" in html and "Statistics" in html and "Marketing" in html)
check("query string not stored", "email=" not in html and "x@y.z" not in html)
check("submenu link to consents", "view=consents" in html)
html = req(f"?option=com_lcookies&view=consents&filter[search]=uuid:{U1}")
check("filter by consent id (history)", html.count(f"<code>{U1}</code>") == 2 and U2 not in html)
html = req("?option=com_lcookies&view=consents&filter[search]=&filter[category]=marketing")
check("filter by accepted category", U2 in html and f"<code>{U1}</code>" not in html)
html = req("?option=com_lcookies&view=consents&filter[category]=&filter[action]=reject_all")
check("filter by choice", html.count(f"<code>{U1}</code>") == 1 and U2 not in html)
html = req("?option=com_lcookies&view=consents&filter[action]=&filter[from]=2000-01-01&filter[to]=2001-01-01")
check("filter by date (no results)", "No Matching Results" in html or "No matching results" in html.lower() or U1 not in html)
html = req("?option=com_lcookies&view=consents&filter[from]=&filter[to]=")

# CSV export (token in the URL) honours the active filters
tok = token(html)
r = req(f"?option=com_lcookies&task=consents.export&{tok}=1", raw=True)
csv_text = r.read().decode("utf-8")
check("export is CSV download", r.headers.get("Content-Type", "").startswith("text/csv") and "attachment" in r.headers.get("Content-Disposition", ""),
      str(dict(r.headers))[:300])
lines = csv_text.splitlines()
check("CSV header + BOM", csv_text.startswith("\ufeffcreated_utc,consent_id,action,categories,policy_version,user_id,language,page_url,ip_hash,user_agent_hash"), str(lines[:1]))
check("CSV has all 4 records", len(lines) == 5, str(len(lines)))
check("CSV neutralises formulas", any(",'=HYPERLINK(1)," in l for l in lines), str([l for l in lines if "HYPERLINK" in l]))
check("CSV dates in UTC ISO", re.match(r"^\ufeff?\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ,", lines[1]) is not None, lines[1])
req(f"?option=com_lcookies&view=consents&filter[search]=uuid:{U2}")
csv_text = req(f"?option=com_lcookies&task=consents.export&{tok}=1")
check("CSV export follows the filters", len(csv_text.splitlines()) == 2 and U2 in csv_text, csv_text[:300])
req("?option=com_lcookies&view=consents&filter[search]=")
html = req("?option=com_lcookies&task=consents.export&bad=1")
check("export without token refused", "created_utc" not in html)

# Purge expired records
html = req("?option=com_lcookies&view=consents")
html = req("?option=com_lcookies&view=consents", {"task": "consents.purge", token(html): "1"})
check("purge deletes only expired", sql("SELECT COUNT(*) FROM jos_lcookies_consents") == "3" and "1 expired record deleted" in html, str(messages(html)))

# Permissions: a Manager allowed to manage the component still needs the consents permissions
php = f"{L}/php"
pw = subprocess.run([php, "-r", "echo password_hash('Manager123456789!', PASSWORD_BCRYPT);"], capture_output=True, text=True).stdout
sql("DELETE FROM jos_user_usergroup_map WHERE user_id = 9900; DELETE FROM jos_users WHERE id = 9900")
q = '"' if DB.endswith("pg") else "`"
cols = ", ".join(f"{q}{c}{q}" for c in ["id", "name", "username", "email", "password", "block", "sendEmail", "registerDate", "params", "requireReset"])
sql(f"INSERT INTO jos_users ({cols}) "
    f"VALUES (9900, 'LC Manager', 'lcmanager', 'lcmanager@example.com', '{pw}', 0, 0, NOW(), '{{}}', 0)")
sql("INSERT INTO jos_user_usergroup_map (user_id, group_id) VALUES (9900, 6)")
rules = sql("SELECT rules FROM jos_assets WHERE name = 'com_lcookies'")
sql("UPDATE jos_assets SET rules = '{\"core.manage\":{\"6\":1}}' WHERE name = 'com_lcookies'")
mjar = http.cookiejar.CookieJar()
mop = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(mjar))
login(mop, "lcmanager", "Manager123456789!")
html = req("?option=com_lcookies&view=services", opener=mop)
check("manager can manage the component", "This website" in html, html[:200])
html = req("?option=com_lcookies&view=consents", opener=mop)
check("manager without permission: consents refused", U2 not in html and ("not authorised" in html.lower() or "403" in html), html[:200])
sql("UPDATE jos_assets SET rules = '{\"core.manage\":{\"6\":1},\"lcookies.consents.view\":{\"6\":1}}' WHERE name = 'com_lcookies'")
html = req("?option=com_lcookies&view=consents", opener=mop)
check("view permission granted: list visible, no export button", U2 in html and "consents.export" not in html)
r = req(f"?option=com_lcookies&task=consents.export&{token(html)}=1", opener=mop, raw=True)
body = r.read().decode("utf-8", "replace")
check("export refused without export permission", "created_utc" not in body, body[:200])
sql(f"UPDATE jos_assets SET rules = '{rules}' WHERE name = 'com_lcookies'")
sql("DELETE FROM jos_user_usergroup_map WHERE user_id = 9900; DELETE FROM jos_users WHERE id = 9900; DELETE FROM jos_session WHERE userid = 9900")

print("\nFAILED:" if fails else "\nALL PASSED", fails)
sys.exit(1 if fails else 0)
