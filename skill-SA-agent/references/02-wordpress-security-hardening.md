# 02 — WordPress security hardening & review

Sources: `WordPress/agent-skills` (wp-plugin-development/security), `claude-code-guide`
(wordpress-penetration-testing → inverted into defenses; secure-code-review OWASP SOP),
`awesome-claude-code-subagents/security-auditor`, `wordpress-master`.

## A. What attackers enumerate → what we lock down (from the pentest skill, inverted)

| Attacker step | Our defense (child `inc/security.php` unless noted) |
|---|---|
| Version via `readme.html`, `?ver=`, `<meta name="generator">` | `remove_action('wp_head','wp_generator')`; strip `ver` on front assets is optional; delete `readme.html`/`license.txt` on server (cPanel) |
| `xmlrpc.php` brute force / pingback DDoS | `add_filter('xmlrpc_enabled','__return_false')`; remove `X-Pingback` header; remove `rel="pingback"`; `pings_open` false |
| User enumeration `?author=1`, `/wp-json/wp/v2/users` | redirect `author` query for non-logged-in; `rest_endpoints` filter removing `/wp/v2/users` for anonymous; author archives 404 if no posts |
| Login brute force `wp-login.php` | generic login error message (`login_errors`); strong passwords; hosting-level rate limit (cPanel/ModSecurity); 2FA later |
| Theme/plugin enumeration | keep everything updated; delete unused themes/plugins; no `directory listing` (`Options -Indexes` in `.htaccess`) |
| File upload / editors | `define('DISALLOW_FILE_EDIT', true)` in `wp-config.php`; uploads dir `.htaccess` deny PHP execution |
| `wp-config.php` exposure | permissions 640/600; move above webroot if hosting allows; unique salts |
| RSD / WLW manifests, shortlinks, REST link header noise | remove `rsd_link`, `wlwmanifest_link`, `wp_shortlink_wp_head` |
| Security headers | `send_headers`: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` minimal; HSTS at server level once HTTPS is stable |

## B. Code review SOP (OWASP Top 10 mapped to WordPress)

Principle: prefer false positives over missed issues; every item appears in the report (pass/fail);
severity RED/YELLOW/GREEN; every finding ships with fix code and `file:line`.

| OWASP | WordPress check |
|---|---|
| A01 Access control | every write path has `current_user_can()`; meta/settings saves check `edit_post`/`manage_options`; AJAX `nopriv` never writes |
| A02 Crypto | no secrets in theme files; salts unique; passwords never logged |
| A03 Injection | `$wpdb->prepare()`; no string-built SQL; `esc_*` at output; `wp_kses_post` for HTML input; shortcodes escape attributes |
| A04 Insecure design | publish gate cannot be bypassed via REST/quick edit; rate-limit forms (honeypot + nonce) |
| A05 Misconfiguration | `WP_DEBUG` off in prod; `DISALLOW_FILE_EDIT`; no directory listing; file perms |
| A06 Vulnerable components | WP core/parent theme updated; no abandoned bundled libs |
| A07 Auth failures | generic login errors; strong password policy; app passwords only when needed |
| A08 Integrity | no `unserialize()` on user data; `wp_json_encode/json_decode(true)` |
| A09 Logging | `WP_DEBUG_LOG` in staging; failed logins visible in hosting logs |
| A10 SSRF | any URL fetch uses `wp_safe_remote_get()` with timeout, only to allow-listed hosts |

## C. Theme-code rules (non-negotiable)

```php
// Read explicit keys, unslash, sanitize by type
$val = isset( $_POST['sa_meta']['city_population'] )
    ? absint( wp_unslash( $_POST['sa_meta']['city_population'] ) ) : 0;

// Nonce + capability + autosave guard in save_post
if ( ! isset( $_POST['sa_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sa_meta_nonce'] ), 'sa_meta_save' ) ) return;
if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return;

// Output
echo esc_html( $text ); echo esc_attr( $attr ); echo esc_url( $url ); echo wp_kses_post( $html );
echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
```

Sanitizers by type: text → `sanitize_text_field`; textarea → `sanitize_textarea_field`; HTML →
`wp_kses_post`; int → `absint`/`intval`; float → `floatval` (locale-safe: replace Persian digits
first); URL → `esc_url_raw`; email → `sanitize_email`; slug/key → `sanitize_key`/`sanitize_title`;
IDs list → `array_map('absint', (array) $v)`; bool → `rest_sanitize_boolean`.

## D. Server-side checklist for the owner (cPanel)

1. HTTPS forced (AutoSSL) + `FORCE_SSL_ADMIN`.
2. `wp-config.php`: unique salts, `DISALLOW_FILE_EDIT`, `WP_DEBUG false`, `WP_POST_REVISIONS 10`, `AUTOMATIC_UPDATER_DISABLED false` (minor auto-updates on).
3. Delete `readme.html`, `license.txt`, `wp-config-sample.php`, unused themes (keep one default for fallback) and plugins.
4. `.htaccess`: `Options -Indexes`; deny PHP in `wp-content/uploads`.
5. Daily backups (cPanel/JetBackup) + off-site copy.
6. Admin username not `admin`; strong password; separate editor accounts for writers.
