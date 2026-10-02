"""End-to-end test of the LCookies Web Services API (plg_webservices_lcookies + com_lcookies/api).

Usage: python3 tests/e2e_api.py <site-url> <joomla root>
  e.g. python3 tests/e2e_api.py http://127.0.0.1:8106 /tmp/claude-1000/lc/j6.0.0

Uses tests/fixture.php (LC_PHP, default /tmp/claude-1000/lc/php) to create API tokens, a Manager user
and to change permissions; it creates and deletes its own records ("lcapi-" aliases). Test sites only.
"""

import json, os, subprocess, sys, urllib.error, urllib.request, uuid
from pathlib import Path

BASE, ROOT = sys.argv[1], sys.argv[2]
API = BASE + "/api/index.php/v1/lcookies"
PHP = os.environ.get("LC_PHP", "/tmp/claude-1000/lc/php")
FIXTURE = str(Path(__file__).resolve().parent / "fixture.php")
fails = []


def fixture(*args):
    return subprocess.run([PHP, FIXTURE, ROOT, *args], capture_output=True, text=True, check=True).stdout.strip()


def call(method, path, body=None, token=None, base=API):
    headers = {"Accept": "application/vnd.api+json"}
    if token:
        headers["Authorization"] = "Bearer " + token
    data = None
    if body is not None:
        data = json.dumps(body).encode()
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(base + path, data, headers, method=method)
    try:
        with urllib.request.urlopen(req) as r:
            raw = r.read().decode()
            return r.status, json.loads(raw) if raw else None
    except urllib.error.HTTPError as e:
        raw = e.read().decode()
        try:
            return e.code, json.loads(raw)
        except ValueError:
            return e.code, raw


def check(name, cond, extra=""):
    print(("PASS " if cond else "FAIL ") + name + ("" if cond else "  -> " + str(extra)[:400]))
    if not cond:
        fails.append(name)


def attrs(doc):
    return doc["data"]["attributes"] if isinstance(doc, dict) and isinstance(doc.get("data"), dict) else {}


def items(doc):
    return [d["attributes"] for d in doc.get("data", [])] if isinstance(doc, dict) else []


def error(doc):
    return json.dumps(doc.get("errors") if isinstance(doc, dict) else doc)


def site_consent(uuid, cats, action):
    body = json.dumps({"consent": {"id": uuid, "v": 1, "cats": cats, "ts": 0}, "action": action, "url": BASE + "/"}).encode()
    req = urllib.request.Request(BASE + "/index.php?option=com_lcookies&task=consent.save&format=json", body, {"Content-Type": "application/json"})
    with urllib.request.urlopen(req) as r:
        return r.status


ADMIN = fixture("token", "admin")
created = {"categories": [], "services": [], "cookies": []}

try:
    # Authentication ------------------------------------------------------------------------------
    for resource in ("categories", "services", "cookies", "consents"):
        status, _ = call("GET", "/" + resource)
        check(f"{resource}: token required", status == 401, status)

    status, doc = call("GET", "/config")
    check("config is public", status == 200 and attrs(doc).get("schema") == 1, doc)
    a = attrs(doc)
    check("config = frontend contract", doc["data"]["type"] == "config" and doc["data"]["id"] == "en-GB"
          and a["cookie"]["name"] == "lcookies_consent" and a["categories"][0]["alias"] == "necessary"
          and a["texts"]["acceptAll"] and "COM_LCOOKIES_" not in json.dumps(a), a)
    status, doc = call("GET", "/config?language=xx-XX")
    check("config with an unknown language: 404", status == 404, status)

    # Read ----------------------------------------------------------------------------------------
    status, doc = call("GET", "/categories", token=ADMIN)
    cats = items(doc)
    check("list categories", status == 200 and [c["alias"] for c in cats][:1] and any(c["alias"] == "necessary" for c in cats), doc)
    check("gcm_types as a list", all(isinstance(c["gcm_types"], list) for c in cats), cats[:1])
    status, doc = call("GET", "/categories/1", token=ADMIN)
    check("get category 1", status == 200 and attrs(doc).get("alias") == "necessary" and attrs(doc).get("gcm_types") == ["security_storage"], doc)
    status, _ = call("GET", "/categories/99999", token=ADMIN)
    check("missing category: 404", status == 404, status)
    status, doc = call("GET", "/services?filter[category]=1", token=ADMIN)
    check("services filtered by category", status == 200 and items(doc) and all(s["category_id"] == 1 for s in items(doc)), doc)
    status, doc = call("GET", "/cookies?filter[service]=1&list[ordering]=a.name&list[direction]=desc", token=ADMIN)
    names = [c["name"] for c in items(doc)]
    check("cookies filtered by service, ordered", status == 200 and names and names == sorted(names, reverse=True), names)
    status, doc = call("GET", "/cookies?filter[search]=joomla_user", token=ADMIN)
    check("cookies search", status == 200 and [c["name"] for c in items(doc)] == ["joomla_user_state"], doc)
    status, doc = call("GET", "/cookies?filter[search]=id:1", token=ADMIN)
    check("cookie display name in the API", status == 200 and [c.get("display_name") for c in items(doc)] == ["COM_LCOOKIES_COOKIE_SESSION_NAME"], doc)
    status, doc = call("GET", "/cookies?page[limit]=2", token=ADMIN)
    check("pagination", status == 200 and len(items(doc)) == 2 and "next" in doc.get("links", {}), doc.get("links"))

    # Write: categories ---------------------------------------------------------------------------
    status, doc = call("POST", "/categories", {"title": "API category", "alias": "lcapi-cat", "description": "From the API",
                                               "gcm_types": ["analytics_storage"], "state": 1}, ADMIN)
    cat = attrs(doc)
    check("create category", status == 200 and cat.get("alias") == "lcapi-cat" and cat.get("gcm_types") == ["analytics_storage"], doc)
    if cat.get("id"):
        created["categories"].append(cat["id"])
    status, doc = call("POST", "/categories", {"title": "Duplicate", "alias": "lcapi-cat"}, ADMIN)
    check("duplicate alias refused, translated message", status >= 400 and "COM_LCOOKIES_" not in error(doc) and "alias" in error(doc).lower(), (status, doc))
    status, doc = call("POST", "/categories", {"title": "Bad", "alias": "lcapi-bad", "gcm_types": ["nope"]}, ADMIN)
    check("unknown Consent Mode type refused", status >= 400, (status, doc))
    if status == 200:
        created["categories"].append(attrs(doc)["id"])
    status, doc = call("PATCH", f"/categories/{cat.get('id')}", {"title": "API category renamed"}, ADMIN)
    check("PATCH keeps the other fields", status == 200 and attrs(doc).get("title") == "API category renamed"
          and attrs(doc).get("gcm_types") == ["analytics_storage"] and attrs(doc).get("checked_out") in (None, 0), doc)
    status, doc = call("PATCH", "/categories/1", {"alias": "hacked", "required": 0, "state": 0}, ADMIN)
    status, doc = call("GET", "/categories/1", token=ADMIN)
    check("core category stays locked", attrs(doc).get("alias") == "necessary" and attrs(doc).get("required") == 1 and attrs(doc).get("state") == 1, doc)

    # Write: services and cookies -----------------------------------------------------------------
    status, doc = call("POST", "/services", {"title": "API service", "category_id": cat.get("id"), "provider": "Example",
                                             "block_patterns": "/[invalid/", "state": 1}, ADMIN)
    check("invalid block pattern refused", status >= 400 and "regular expression" in error(doc), (status, doc))
    status, doc = call("POST", "/services", {"title": "API service", "category_id": cat.get("id"), "provider": "Example",
                                             "block_patterns": " lcapi-tracker.js \n\n", "state": 1}, ADMIN)
    svc = attrs(doc)
    check("create service (alias generated, patterns trimmed)", status == 200 and svc.get("alias") == "api-service"
          and svc.get("block_patterns") == "lcapi-tracker.js", doc)
    if svc.get("id"):
        created["services"].append(svc["id"])
    status, doc = call("POST", "/cookies", {"name": "_lcapi[", "match_type": "regex", "service_id": svc.get("id"), "type": "cookie",
                                            "duration_value": 1, "duration_unit": "year", "state": 1}, ADMIN)
    check("invalid cookie regex refused", status >= 400, (status, doc))
    status, doc = call("POST", "/cookies", {"name": "_lcapi_", "match_type": "prefix", "service_id": svc.get("id"), "type": "cookie",
                                            "duration_value": 1, "duration_unit": "year", "description": "API cookie", "state": 1}, ADMIN)
    cookie = attrs(doc)
    check("create cookie", status == 200 and cookie.get("name") == "_lcapi_" and cookie.get("service_id") == svc.get("id"), doc)
    if cookie.get("id"):
        created["cookies"].append(cookie["id"])

    status, doc = call("GET", "/config")
    api_cat = [c for c in attrs(doc).get("categories", []) if c["alias"] == "lcapi-cat"]
    check("API changes reach the contract (cache cleaned)", api_cat and api_cat[0]["services"][0]["patterns"] == ["lcapi-tracker.js"]
          and api_cat[0]["services"][0]["cookies"][0]["name"] == "_lcapi_", api_cat)

    # Delete rules --------------------------------------------------------------------------------
    status, doc = call("DELETE", f"/services/{svc.get('id')}", token=ADMIN)
    check("published service is not deleted (409, must be trashed)", status == 409 and "trashed" in error(doc), (status, doc))
    call("PATCH", f"/categories/{cat.get('id')}", {"state": -2}, ADMIN)
    status, doc = call("DELETE", f"/categories/{cat.get('id')}", token=ADMIN)
    check("trashed category with services is not deleted (409 + reason)", status == 409 and "still has 1 service" in error(doc), (status, doc))
    call("PATCH", f"/services/{svc.get('id')}", {"state": -2}, ADMIN)
    status, _ = call("DELETE", f"/services/{svc.get('id')}", token=ADMIN)
    check("trashed service deleted", status == 204, status)
    status, _ = call("GET", f"/cookies/{cookie.get('id')}", token=ADMIN)
    check("its cookies deleted too", status == 404, status)
    created["services"].clear()
    created["cookies"].clear()
    status, _ = call("DELETE", f"/categories/{cat.get('id')}", token=ADMIN)
    check("trashed empty category deleted", status == 204, status)
    if status == 204:
        created["categories"].clear()

    # Consent records -----------------------------------------------------------------------------
    U1 = str(uuid.uuid4())
    check("records from the site endpoint", site_consent(U1, ["necessary", "statistics"], "custom") == 200
          and site_consent(U1, ["necessary"], "reject_all") == 200)
    status, doc = call("GET", f"/consents?filter[search]={U1}", token=ADMIN)
    rows = items(doc)
    check("list consents of one id (newest first)", status == 200 and [r["action"] for r in rows] == ["reject_all", "custom"]
          and rows[1]["categories"] == ["necessary", "statistics"] and len(rows[0]["ip_hash"]) == 64, rows)
    status, doc = call("GET", "/consents?filter[action]=custom&filter[category]=statistics", token=ADMIN)
    check("consents filters", status == 200 and any(r["consent_uuid"] == U1 for r in items(doc)) and all(r["action"] == "custom" for r in items(doc)), doc)
    rid = rows[0]["id"] if rows else 0
    status, doc = call("GET", f"/consents/{rid}", token=ADMIN)
    check("get one consent record", status == 200 and attrs(doc).get("consent_uuid") == U1 and attrs(doc).get("categories") == ["necessary"], doc)
    for method, path in (("POST", "/consents"), ("PATCH", f"/consents/{rid}"), ("DELETE", f"/consents/{rid}")):
        status, _ = call(method, path, {"action": "accept_all"} if method != "DELETE" else None, ADMIN)
        check(f"consents are read only ({method})", status in (403, 404, 405), status)

    # Permissions ---------------------------------------------------------------------------------
    root_rules = fixture("asset", "root.1")
    com_rules = fixture("asset", "com_lcookies")
    token_params = fixture("plugin", "user", "token")
    manager = fixture("token", "lcapimanager", "6")
    try:
        # API tokens are accepted only for the groups set in the "User - Joomla API Token" plugin.
        params = json.loads(token_params or "{}") or {}
        params["allowedUserGroups"] = ["8", "6"]
        fixture("plugin", "user", "token", json.dumps(params))
        login = json.loads(root_rules)
        login.setdefault("core.login.api", {})["6"] = 1
        fixture("asset", "root.1", json.dumps(login))
        status, _ = call("GET", "/categories", token=manager)
        check("manager without core.manage: 403", status == 403, status)
        fixture("asset", "com_lcookies", json.dumps({"core.manage": {"6": 1}, "core.create": {"6": 0}}))
        status, _ = call("GET", "/categories", token=manager)
        check("manager with core.manage reads", status == 200, status)
        status, _ = call("GET", "/consents", token=manager)
        check("consents need lcookies.consents.view", status == 403, status)
        status, _ = call("POST", "/categories", {"title": "No", "alias": "lcapi-no"}, manager)
        check("create needs core.create", status == 403, status)
        fixture("asset", "com_lcookies", json.dumps({"core.manage": {"6": 1}, "lcookies.consents.view": {"6": 1}}))
        status, _ = call("GET", "/consents", token=manager)
        check("consents readable with the permission", status == 200, status)
        # Code that runs on the site: only Super Users and groups with "No Filtering" (Text Filters).
        fixture("asset", "com_lcookies", json.dumps({"core.manage": {"6": 1}, "core.edit": {"6": 1}}))
        call("PATCH", "/services/1", {"head_code": "<!-- lcapi admin code -->"}, ADMIN)
        status, _ = call("PATCH", "/services/1", {"head_code": "<script>window.lcPwned = 1;</script>", "provider": "lcapi-manager"}, manager)
        status2, doc = call("GET", "/services/1", token=ADMIN)
        attrs = doc.get("data", {}).get("attributes", {}) if isinstance(doc, dict) else {}
        check("manager without unfiltered HTML cannot change code (kept as stored)", status == 200
              and attrs.get("head_code") == "<!-- lcapi admin code -->" and attrs.get("provider") == "lcapi-manager",
              (status, attrs.get("head_code"), attrs.get("provider")))
        call("PATCH", "/services/1", {"provider": "", "head_code": ""}, ADMIN)
    finally:
        fixture("plugin", "user", "token", token_params)
        fixture("asset", "root.1", root_rules)
        fixture("asset", "com_lcookies", com_rules)
        fixture("deluser", "lcapimanager")
finally:
    for resource in ("cookies", "services", "categories"):
        for rid in created[resource]:
            call("PATCH", f"/{resource}/{rid}", {"state": -2}, ADMIN)
            call("DELETE", f"/{resource}/{rid}", token=ADMIN)

print("\nFAILED:" if fails else "\nALL PASSED", fails)
sys.exit(1 if fails else 0)
