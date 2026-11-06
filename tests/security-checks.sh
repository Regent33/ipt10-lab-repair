#!/usr/bin/env bash
# Black-box security checks against a running local instance with a freshly imported schema.sql.
# Usage: ALEX_PW=... SAM_PW=... bash tests/security-checks.sh [base_url]
# Passwords come from the environment so they are never written into this file or its output.
set -u
BASE=${1:-http://127.0.0.1:8000}
: "${ALEX_PW:?set ALEX_PW}" "${SAM_PW:?set SAM_PW}"
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
pass=0; failn=0
check() { if eval "$2"; then echo "PASS  $1"; pass=$((pass+1)); else echo "FAIL  $1"; failn=$((failn+1)); fi; }
token() { curl -s -b "$1" -c "$1" "$BASE/" | grep -o 'name="csrf-token" content="[^"]*"' | cut -d'"' -f4; }
sid() { grep PHPSESSID "$1" | awk '{print $7}'; }
login() { # jar email password
  local t; t=$(token "$1")
  curl -s -b "$1" -c "$1" -o /dev/null -d "action=login&csrf_token=$t" --data-urlencode "email=$2" --data-urlencode "password=$3" "$BASE/"
}
api() { curl -s -o "$T/body" -w '%{http_code}' "$@"; }

# --- Login and session
token "$T/a" >/dev/null; before=$(sid "$T/a")
login "$T/a" alex@example.test "$ALEX_PW"
check "T01 valid login shows Alex's account" "curl -s -b $T/a $BASE/ | grep -q 'User ID: 1 '"
check "T02 session ID changes on login (no fixation)" "[ -n \"$before\" ] && [ \"$before\" != \"\$(sid $T/a)\" ]"
check "T03 cookie is HttpOnly" "grep -q '^#HttpOnly_' $T/a"
login "$T/x" alex@example.test wrong-password
curl -s -b "$T/x" "$BASE/" > "$T/xpage"
check "T04 invalid login is rejected with generic message" "grep -q 'Invalid email or password' $T/xpage && ! grep -q 'User ID:' $T/xpage"
login "$T/s" "alex@example.test' -- " wrong
check "T05 SQL injection login payload is rejected" "! curl -s -b $T/s $BASE/ | grep -q 'User ID:'"

# --- Authorization between two users
login "$T/b" sam@example.test "$SAM_PW"
check "T06 Alex's list contains no Sam notes" "! curl -s -b $T/a $BASE/ | grep -q 'Sam:'"
check "T07 Alex cannot open Sam's note 3 for editing" "[ \$(curl -s -b $T/a -o /dev/null -w '%{http_code}' '$BASE/?edit=3') = 404 ]"
TA=$(token "$T/a")
check "T08 Alex cannot update Sam's note 3" "[ \$(curl -s -b $T/a -o /dev/null -w '%{http_code}' -d 'action=update&id=3&title=hacked&body=hacked&csrf_token=$TA' $BASE/) = 404 ]"
check "T09 Alex cannot delete Sam's note 3" "[ \$(curl -s -b $T/a -o /dev/null -w '%{http_code}' -d 'action=delete&id=3&csrf_token=$TA' $BASE/) = 404 ]"
curl -s -b "$T/a" -o /dev/null "$BASE/?delete=4"
check "T10 Sam's notes 3 and 4 still exist and are unchanged" "curl -s -b $T/b $BASE/api.php | grep -q 'Sam: study plan' && curl -s -b $T/b $BASE/api.php | grep -q 'Sam: meeting' && ! curl -s -b $T/b $BASE/api.php | grep -q hacked"

# --- CSRF and unsafe methods
check "T11 POST without CSRF token is rejected (403)" "[ \$(curl -s -b $T/a -o /dev/null -w '%{http_code}' -d 'action=save&title=x&body=x' $BASE/) = 403 ]"
check "T12 POST with cross-site Origin is rejected (403)" "[ \$(curl -s -b $T/a -o /dev/null -w '%{http_code}' -H 'Origin: https://evil.example' -d 'action=save&title=x&body=x&csrf_token=$TA' $BASE/) = 403 ]"
curl -s -b "$T/a" -o /dev/null "$BASE/?logout=1"
check "T13 GET ?logout=1 no longer changes state" "curl -s -b $T/a $BASE/ | grep -q 'User ID: 1 '"

# --- Client-controlled owner / role, XSS
curl -s -b "$T/a" -o /dev/null -d "action=save&user_id=2&csrf_token=$TA" --data-urlencode 'title=<script>alert(1)</script>' --data-urlencode 'body=<img src=x onerror=alert(2)>' "$BASE/"
check "T14 spoofed user_id=2 ignored; note saved under Alex" "curl -s -b $T/a $BASE/api.php | grep -q '\"user_id\":1,\"title\":\"<script>' && ! curl -s -b $T/b $BASE/api.php | grep -q script"
check "T15 HTML-like note text is escaped in the page" "curl -s -b $T/a $BASE/ | grep -q '&lt;script&gt;alert(1)&lt;/script&gt;' && ! curl -s -b $T/a $BASE/ | grep -q '<script>alert'"
TN=$(token "$T/n"); E="eve$RANDOM@example.test"
curl -s -b "$T/n" -c "$T/n" -o /dev/null -d "action=register&role=admin&csrf_token=$TN&password=$SAM_PW" --data-urlencode "email=$E" "$BASE/"
login "$T/n" "$E" "$SAM_PW"
check "T16 registering with role=admin still creates a student" "curl -s -b $T/n $BASE/ | grep -q 'Role: student'"

# --- API
check "T17 API with no session returns 401" "[ \$(api $BASE/api.php?user_id=1) = 401 ]"
check "T18 API GET for another user's ID returns 403" "[ \$(api -b $T/a '$BASE/api.php?user_id=2') = 403 ] && ! grep -q Sam $T/body"
check "T19 API GET own notes returns 200" "[ \$(api -b $T/a '$BASE/api.php?user_id=1') = 200 ] && grep -q 'Alex:' $T/body"
check "T20 API POST without CSRF header returns 403" "[ \$(api -b $T/a -H 'Content-Type: application/json' -d '{\"id\":1,\"title\":\"t\",\"body\":\"b\"}' $BASE/api.php) = 403 ]"
H=(-b "$T/a" -H "X-CSRF-Token: $TA" -H 'Content-Type: application/json')
check "T21 API malformed JSON returns 400 JSON error, no PHP warning" "[ \$(api \"\${H[@]}\" -d '{bad' $BASE/api.php) = 400 ] && grep -q '\"error\"' $T/body && ! grep -qi 'warning\|<b>' $T/body"
check "T22 API JSON array / missing fields return 400" "[ \$(api \"\${H[@]}\" -d '[1,2]' $BASE/api.php) = 400 ] && [ \$(api \"\${H[@]}\" -d '{\"id\":1}' $BASE/api.php) = 400 ] && [ \$(api \"\${H[@]}\" -d '{\"id\":\"1 OR 1=1\",\"title\":\"t\",\"body\":\"b\"}' $BASE/api.php) = 400 ]"
check "T23 API wrong Content-Type returns 415" "[ \$(api -b $T/a -H 'X-CSRF-Token: $TA' -d 'id=1' $BASE/api.php) = 415 ]"
check "T24 API update of Sam's note 3 returns 404" "[ \$(api \"\${H[@]}\" -d '{\"id\":3,\"title\":\"hacked\",\"body\":\"hacked\"}' $BASE/api.php) = 404 ]"
check "T25 API update of own note 1 returns 200" "[ \$(api \"\${H[@]}\" -d '{\"id\":1,\"title\":\"Alex: course reminder\",\"body\":\"Updated via API.\"}' $BASE/api.php) = 200 ]"
check "T26 API untrusted Origin: 403 and no CORS allow headers" "[ \$(api -b $T/a -H 'Origin: https://evil.example' $BASE/api.php) = 403 ] && ! curl -s -D - -o /dev/null -b $T/a -H 'Origin: https://evil.example' $BASE/api.php | grep -qi access-control-allow"
check "T27 API OPTIONS/PUT return 405" "[ \$(api -b $T/a -X PUT $BASE/api.php) = 405 ]"
check "T28 direct request to templates/home.php returns 404" "[ \$(api $BASE/templates/home.php) = 404 ]"

# --- Logout
cp "$T/a" "$T/old"
TA=$(token "$T/a")
curl -s -b "$T/a" -c "$T/a" -o /dev/null -d "action=logout&csrf_token=$TA" "$BASE/"
check "T29 after logout, page shows sign-in form" "curl -s -b $T/a $BASE/ | grep -q 'Register or sign in'"
check "T30 after logout, old session cookie gets 401 from API" "[ \$(api -b $T/old $BASE/api.php) = 401 ]"
check "T31 Sam's session is unaffected by Alex's logout" "[ \$(api -b $T/b $BASE/api.php) = 200 ]"

echo "---"; echo "$pass passed, $failn failed"; [ "$failn" -eq 0 ]
