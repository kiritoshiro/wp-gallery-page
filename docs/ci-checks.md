# CI and security checks

## Security gate

`.github/workflows/security-gate.yml` is the only trigger for the security workflows. It runs on pull requests and pushes to `main`, weekly and on demand, and the release workflow calls it on the tagged commit.

Its final job, **All security checks passed**, fails if any check failed, was cancelled or was skipped. A green gate means every scanner ran and found nothing it blocks on; it does not prove the code is secure.

- **Baseline** (`cybersecurity.yml`):
  - Semgrep CE (`p/php`, `p/javascript`);
  - TruffleHog secret scan without verification;
  - zizmor (workflow security);
  - Trivy (dependencies and configuration, HIGH/CRITICAL);
  - WordPress security sniffs (PHPCS with WPCS 3.4.1, isolated in `.github/security`, never shipped);
  - actionlint.
- **CodeQL** (`codeql.yml`): JavaScript, on the public repository.
- **WordPress** (`wordpress-checks.yml`):
  - WordPress Plugin Check: general, security, performance and accessibility. The `plugin_repo` category is left out, because the plugin is self-hosted.
  - WordPress Playground smoke test on PHP 7.4 and 8.4. It creates pages (one draft), posts and settings, then checks the gallery output: the video panel comes before the latest posts, the chosen featured page, the grid order, no draft, the custom image, the "No image" box, `show_yt="0"` and the Lithuanian translation. It then loads the gallery page and every wp-admin menu page. A PHP error, warning, notice or deprecation from this plugin fails it.

The settings form posts to `options.php` (Settings API: nonce and `manage_options` checked by WordPress), so the plugin has no `admin_post_*`/AJAX handlers of its own.

## CI

`ci.yml` runs PHP lint, `tests/run.php` and the JavaScript syntax check on PHP 7.4 and 8.4, and builds a trial release ZIP. The ZIP build needs Node 24 and `npm ci --ignore-scripts`: it minifies the packaged scripts and styles with SWC and lightningcss, and fails if that fails.

`tests/run.php` covers settings sanitizing (limits, ordered IDs, images only for listed pages and only http(s), playlist from a pasted link, headings), reading 2.x settings, the Lithuanian strings and the updater (foreign host, prerelease, wrong asset, tampered download).

## Links

`links.yml` checks Markdown links with lychee on pull requests that change them, monthly and on demand. It is not part of the security gate.

## Running locally

- `php tests/run.php`
- `PYTHONUTF8=1 python .github/playground/smoke.py --php 8.4` (needs Node 24 and Python 3; in Git Bash also `MSYS_NO_PATHCONV=1` if you call the Playground CLI yourself)
- `composer install --working-dir=.github/security --no-plugins --no-scripts`, then `.github/security/vendor/bin/phpcs --standard=.github/security/phpcs.xml .`
- `npm ci --ignore-scripts && python scripts/build-release.py v3.0.0`
