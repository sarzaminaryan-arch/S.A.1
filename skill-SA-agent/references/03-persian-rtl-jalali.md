# 03 — Persian WordPress: RTL, fonts, Persian digits, Jalali dates

Sources: `wpvar/wordpress-fa` fork (bundled `wp-shamsi` 4.1 — hook strategy, skip-format list,
digit conversion, `gregorianToJalali` algorithm), `localization-toolkit` (claude-code-guide),
quantum-pedia RTL review.

## A. Install base — decision

- **Do NOT** install the `wordpress-fa` fork: it is WordPress **5.8** (2021, unsupported,
  insecure) with a bundled plugin. Its only value is the *technique*, extracted below.
- **Do** install the current official Persian build (`fa.wordpress.org`) or English WP + set
  `Site Language = فارسی`. Language packs give `dir="rtl"`, Persian admin, and `is_rtl()`.
- Everything Persian-specific lives in the child theme (`inc/jalali.php`) — no plugin.

## B. RTL rules (theme CSS)

- `language_attributes()` outputs `dir="rtl"`; never hard-code `dir`.
- Prefer **logical properties**: `margin-inline-start`, `padding-inline`, `inset-inline-end`,
  `text-align: start`, `border-inline-start`. Then `rtl.css` becomes tiny.
- **Bug pattern:** `html[dir=rtl] .nav ul { flex-direction: row-reverse }` double-flips (flex rows
  already follow direction). Remove such rules (found in quantum-pedia `.brand`, `.main-navigation ul`).
- Mixed content: wrap Latin/number runs with `<bdi>` or `unicode-bidi: isolate` when needed
  (phone numbers, URLs, coordinates).
- Numbers/dates: use `font-variant-numeric: tabular-nums` in tables.
- Fonts: bundle **Vazirmatn** (OFL) `woff2` Regular+Bold locally; `font-display: swap`;
  `<link rel="preload" as="font" type="font/woff2" crossorigin>` for the regular weight; fallback
  stack `Vazirmatn, "Segoe UI", Tahoma, Arial, sans-serif`. No Google Fonts (privacy + Iran access).
- Line-height ≥ 1.8 for Persian body text; headings 1.3; letter-spacing 0.

## C. Persian digits — where and where not

Convert **only display text**: dates, counters, prices, populations inside HTML text nodes.
Never convert: `datetime=""` attributes, `<time>` machine values, JSON-LD, URLs, form `value`
attributes that are parsed as numbers, CSS, `id`/`class`, coordinates used by maps, `hreflang`.

```php
function sa_fa_digits( $s ) {
    return strtr( (string) $s, [ '0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹' ] );
}
function sa_en_digits( $s ) { // normalise user input (Persian + Arabic-Indic digits) before validation
    return strtr( (string) $s, [ '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                                 '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9' ] );
}
```

Apply `sa_fa_digits()` in template tags you control; optionally on `the_content`/`the_title`
behind a Customizer switch, skipping `<code>`, `<pre>`, `<time>`, `<script>` and attributes.

## D. Jalali dates — hook strategy (from wp-shamsi, minimal & safe)

```php
add_filter( 'wp_date',   'sa_jalali_wp_date', 10, 4 );   // WP ≥ 5.3 (date_i18n calls wp_date)
function sa_jalali_wp_date( $date, $format, $timestamp, $timezone ) {
    static $skip = [ 'Y-m-d\TH:i:sP', 'D, d M Y H:i:s O', 'l, d-M-Y H:i:s T', 'Y-m-d\TH:i:sO',
                     'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d', 'c', 'r', 'U' ];
    if ( is_admin() && ! sa_option( 'jalali_admin' ) ) return $date;           // admin stays Gregorian unless enabled
    if ( in_array( $format, $skip, true ) || preg_match( '/\\\\T|P$|O$|U$/', $format ) ) return $date; // machine formats untouched
    return sa_jalali_format( $format, $timestamp, $timezone );                // your formatter
}
```

Rules:
1. **Skip machine formats** (ISO 8601 / RFC 2822 / W3C / `U`): feeds, sitemaps, `datetime`
   attributes and JSON-LD must stay Gregorian — Google requires ISO 8601 in `datePublished`.
2. Do **not** hook `wp_checkdate` to `__return_true` or rewrite `post_date` on save (wp-shamsi
   does this to accept Jalali input in admin; it corrupts date math). Keep DB dates Gregorian.
3. `human_time_diff` → translate units via `gettext`/language pack, not by rewriting numbers.
4. Month names: فروردین اردیبهشت خرداد تیر مرداد شهریور مهر آبان آذر دی بهمن اسفند;
   weekdays via `$wp_locale->get_weekday()` already Persian with fa_IR pack.
5. Provide `sa_jalali_date( $format, $timestamp )` for templates and a readable default
   `'j F Y'` → `۴ مهر ۱۴۰۵`.

Algorithm (integer, no extension needed; classic Pournader/Toossi form used by wp-shamsi):

```php
function sa_g2j( $gy, $gm, $gd ) {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = ( $gm > 2 ) ? $gy + 1 : $gy;
    $days = 355666 + 365 * $gy + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
    $jy = -1595 + 33 * intdiv( $days, 12053 ); $days %= 12053;
    $jy += 4 * intdiv( $days, 1461 );          $days %= 1461;
    if ( $days > 365 ) { $jy += intdiv( $days - 1, 365 ); $days = ( $days - 1 ) % 365; }
    if ( $days < 186 ) { $jm = 1 + intdiv( $days, 31 ); $jd = 1 + $days % 31; }
    else               { $jm = 7 + intdiv( $days - 186, 30 ); $jd = 1 + ( $days - 186 ) % 30; }
    return [ $jy, $jm, $jd ];
}
```
(`intdiv` = PHP 7+. Verified against 2026-09-26 → 1405-07-04.)

## E. Localization hygiene (from localization-toolkit)

- Source strings may be Persian in the child theme (single-language site) but still wrapped in
  `esc_html__()`/`esc_html_e()` with text domain — keeps escaping + future translation.
- Parent theme keeps English source strings + `languages/fa_IR.po/.mo`.
- Never expose raw error messages; Persian, friendly, no stack traces.
- Brand/technical terms stay Latin where conventional (WordPress, SEO, JSON-LD).
- Validate: no `lang="en"` leftovers, `<html dir="rtl" lang="fa-IR">`, forms accept Persian digits
  (normalise with `sa_en_digits()` before `absint/floatval`).
