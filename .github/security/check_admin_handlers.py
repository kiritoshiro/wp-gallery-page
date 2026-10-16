"""Fail if an admin-post or AJAX handler skips its capability or nonce check.

Every `admin_post_*` / `wp_ajax_*` action registered in includes/*.php must
call current_user_can() and check_admin_referer()/check_ajax_referer() in its
own body. The settings form posts to options.php (Settings API), so today
there are no handlers; the check guards ones added later.
"""
import glob
import re
import sys

HOOK = re.compile(
    r"add_action\(\s*'((?:admin_post|wp_ajax)_[A-Za-z0-9_]+)',\s*array\(\s*(?:\$this|__CLASS__),\s*'([A-Za-z0-9_]+)'"
)
ANY_HOOK = re.compile(r"""['"](?:admin_post|wp_ajax)_""")

failed = False
checked = 0
for path in sorted(glob.glob("includes/*.php")):
    src = open(path, encoding="utf-8").read()
    hooks = HOOK.findall(src)
    if len(ANY_HOOK.findall(src)) != len(hooks):
        failed = True
        print(f"::error file={path}::admin-post/AJAX hook registered in a form this check cannot read; use add_action( 'hook', array( __CLASS__, 'method' ) )")
    for hook, method in hooks:
        checked += 1
        match = re.search(r"function " + re.escape(method) + r"\s*\([^)]*\)\s*\{(.*?)\n\t\}", src, re.S)
        body = match.group(1) if match else ""
        has_cap = "current_user_can(" in body
        has_nonce = re.search(r"check_(?:admin|ajax)_referer\(", body) is not None
        if not (has_cap and has_nonce):
            failed = True
            print(f"::error file={path}::{hook} -> {method}() is missing "
                  + " and ".join(x for x, ok in (("a capability check", has_cap), ("a nonce check", has_nonce)) if not ok))
print(f"Checked {checked} admin handlers.")
sys.exit(1 if failed else 0)
