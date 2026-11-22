<?php
/**
 * Guard: «ثبت تجربه» and the comment block stay real, safe and reversible.
 *
 * Owner report (۲۹ آبان ۱۴۰۵): the call to action led to a 404, and comments were off
 * site-wide. This guard runs without WordPress (plain PHP, like the other theme
 * guards) and is executed by the release gate before packaging:
 *
 *   php wp-content/themes/sarzaminaryan-child/tests/test-experience-form.php
 *
 * Covered:
 *   1. the submission page is reachable: a real page branch in `page.php`, a virtual
 *      route as fallback, and a template wrapper that prints header + footer;
 *   2. the form posts to `admin-post.php` with a nonce, a honeypot and no `alert()`;
 *   3. the handler stores a **pending** submission and never publishes on its own, and
 *      the photo goes through `media_handle_upload()` with a size/type limit;
 *   4. submissions appear in wp-admin (post type registered, list columns, badge);
 *   5. comments are opened by a filter (reversible) and rendered by the child
 *      `comments.php` inside `.sa-comments`, called from the single templates;
 *   6. the design switch still bypasses everything.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // Standalone run for the release gate.
}

define( 'SA_GUARD_DIR', dirname( __DIR__ ) . '/' );

$failures = 0;

/**
 * Assert one condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Message.
 * @return void
 */
function sa_exp_assert( $condition, $message ) {
	global $failures;
	if ( $condition ) {
		return;
	}
	++$failures;
	fwrite( STDERR, "FAIL: {$message}\n" );
}

/**
 * Read a theme file.
 *
 * @param string $relative Path inside the child theme.
 * @return string
 */
function sa_exp_file( $relative ) {
	$path = SA_GUARD_DIR . $relative;
	return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
}

$form    = sa_exp_file( 'inc/experience-form.php' );
$page    = sa_exp_file( 'page.php' );
$wrap    = sa_exp_file( 'template-experience.php' );
$view    = sa_exp_file( 'template-parts/2026/experience-page.php' );
$modal   = sa_exp_file( 'template-parts/2026/experience-modal.php' );
$comments = sa_exp_file( 'comments.php' );
$comments_mod = sa_exp_file( 'inc/comments-2026.php' );
$functions = sa_exp_file( 'functions.php' );

/* 1 — the page can never 404 again ------------------------------------------- */
sa_exp_assert( '' !== $form, 'inc/experience-form.php ships' );
sa_exp_assert( false !== strpos( $form, "add_rewrite_rule( '^city-contrib/?$'" ), 'virtual route for /city-contrib/ exists' );
sa_exp_assert( false !== strpos( $form, 'template_include' ), 'the virtual route has its own template' );
sa_exp_assert( false !== strpos( $form, "\$query_vars['error'] = ''" ), 'the virtual route clears the 404 flag' );
sa_exp_assert( false !== strpos( $form, "add_action( 'template_redirect', 'sa_experience_unset_404', 1 )" ), 'the virtual route answers 200, never 404' );
sa_exp_assert( false !== strpos( $form, 'sa_experience_has_page()' ), 'a real page wins over the virtual route' );
sa_exp_assert( false !== strpos( $wrap, 'get_header()' ) && false !== strpos( $wrap, 'get_footer()' ), 'wrapper prints header and footer' );
sa_exp_assert( false !== strpos( $wrap, 'template-parts/2026/experience-page' ), 'wrapper includes the shared form view' );
sa_exp_assert( false !== strpos( $page, 'sa_experience_is_form_request' ), 'page.php renders the form for a real city-contrib page' );
sa_exp_assert( false !== strpos( $modal, 'sa_experience_form_url' ), 'the «ثبت تجربه» dialog links to the working page' );

/* 1b — owner report 2026-11-22: both «ثبت تجربه» buttons landed on آموزش سفر ------ */
$hub     = sa_exp_file( 'inc/guide-hub.php' );
$contact = sa_exp_file( 'inc/contact-form.php' );
$footer  = sa_exp_file( 'legacy/footer.php' );
sa_exp_assert( '' !== $hub && '' !== $contact && '' !== $footer, 'guide hub, contact form and footer ship' );
sa_exp_assert(
	false !== strpos( $hub, "get_query_var( 'sa_experience_form' )" ),
	'the posts-page 301 lets the virtual submission route through (no detour to /travel-guide/)'
);
sa_exp_assert( false !== strpos( $form, 'function sa_experience_page()' ), 'one resolver owns the submission page' );
sa_exp_assert( false !== strpos( $form, "'submit-experience'" ), 'the resolver honours every accepted slug' );
sa_exp_assert( false !== strpos( $hub, 'apply_filters( \'sa_guide_redirect_posts_page\', true )' ), 'that redirect keeps its off-switch' );

/* 1c — owner report 2026-11-22: «یک خط کد نامرتب» instead of the address --------- */
sa_exp_assert(
	false === strpos( $contact, 'esc_html( antispambot(' ),
	'the contact lead never double-escapes the obfuscated address (text, not &#…; code)'
);
sa_exp_assert( false !== strpos( $contact, 'esc_html( $sa_lead_email )' ), 'the printed address is escaped exactly once (never entity-obfuscated)' );
sa_exp_assert( false !== strpos( $contact, 'sa_contact_decode_stray_address' ), 'an address stored as character references is repaired on render' );
sa_exp_assert(
	false === strpos( $footer, 'antispambot(' ),
	'the footer mailto link prints a real address, not escaped entities'
);

/* 2 — a form the owner can trust --------------------------------------------- */
sa_exp_assert( false !== strpos( $view, 'admin-post.php' ), 'form posts to admin-post.php' );
sa_exp_assert( false !== strpos( $view, "wp_nonce_field( 'sa_experience_submit', 'sa_experience_nonce' )" ), 'form carries a nonce' );
sa_exp_assert( false !== strpos( $view, 'enctype="multipart/form-data"' ), 'form accepts a file upload' );
sa_exp_assert( false !== strpos( $view, 'sa_exp_website' ), 'honeypot field exists' );
sa_exp_assert( false === strpos( $view, 'alert(' ) && false === strpos( $modal, 'alert(' ), 'no alert() in the submission UI' );
sa_exp_assert( false !== strpos( $view, 'name="sa_exp_story"' ) && false !== strpos( $view, 'name="sa_exp_consent"' ), 'story and consent fields exist' );

/* 3 — the handler is moderated, never auto-publishing ------------------------ */
sa_exp_assert( false !== strpos( $form, "wp_verify_nonce(" ), 'handler verifies the nonce' );
sa_exp_assert( false !== strpos( $form, "'post_status'  => 'pending'" ), 'submissions are stored as pending' );
sa_exp_assert( false === strpos( $form, "'post_status'  => 'publish'" ), 'the handler never publishes by itself' );
sa_exp_assert( false !== strpos( $form, 'sanitize_textarea_field' ), 'the story is sanitised' );
sa_exp_assert( false !== strpos( $form, 'media_handle_upload(' ), 'the photo goes through media_handle_upload()' );
sa_exp_assert( false !== strpos( $form, 'MB_IN_BYTES' ), 'upload size is limited' );
sa_exp_assert( false !== strpos( $form, "in_array( \$type, array( 'image/jpeg', 'image/png', 'image/webp' ), true )" ), 'upload mime types are allow-listed' );
sa_exp_assert( false !== strpos( $form, 'admin_post_nopriv_sa_experience_submit' ), 'logged-out visitors can submit' );
sa_exp_assert( false !== strpos( $form, 'set_transient' ), 'a per-IP rate limit exists' );

/* 4 — submissions land in wp-admin ------------------------------------------- */
sa_exp_assert( false !== strpos( $form, "register_post_type(" ), 'the submission type is registered' );
sa_exp_assert( false !== strpos( $form, "'show_ui'       => true" ), 'the submission type has an admin screen' );
sa_exp_assert( false !== strpos( $form, "'public'        => false" ), 'submissions are not public' );
sa_exp_assert( false !== strpos( $form, "'_posts_columns'" ), 'admin list columns are declared' );
sa_exp_assert( false !== strpos( $form, 'awaiting-mod' ), 'pending submissions get a menu badge' );

/* 5 — comments: on, compact, template-wired ---------------------------------- */
sa_exp_assert( '' !== $comments_mod, 'inc/comments-2026.php ships' );
sa_exp_assert( false !== strpos( $comments_mod, "add_filter( 'comments_open'" ), 'comments are opened through a filter (reversible)' );
sa_exp_assert( false !== strpos( $comments_mod, "'sa_comments_off'" ), 'a per-post opt-out exists' );
sa_exp_assert( false !== strpos( $comments_mod, "add_filter( 'sa_comments_enabled'" ) || false !== strpos( $comments_mod, "apply_filters( 'sa_comments_enabled'" ), 'a global switch exists' );
sa_exp_assert( false !== strpos( $comments_mod, 'comments_template(' ), 'the render helper calls comments_template()' );
sa_exp_assert( false !== strpos( $comments_mod, "add_filter( 'pre_comment_approved'" ), 'visitor comments are held for review' );
sa_exp_assert( '' !== $comments, 'child comments.php ships' );
sa_exp_assert( false !== strpos( $comments, 'class="sa-comments"' ), 'comments.php prints the .sa-comments block' );
sa_exp_assert( false !== strpos( $comments, 'number_format_i18n' ), 'comments.php shows a Persian count' );
sa_exp_assert( false !== strpos( $comments, 'comment_form(' ), 'comments.php prints the form' );

foreach ( array( 'single-attraction.php', 'single-city.php', 'single-province.php', 'template-parts/content-single-post.php' ) as $single ) {
	$body = sa_exp_file( $single );
	sa_exp_assert( false !== strpos( $body, 'sa_comments_2026_render(' ), "{$single} prints the comment block" );
}

$css = sa_exp_file( 'assets/css/sa-2026.css' );
foreach ( array( '.sa-comments', '.sa-exp-form', '.sa-notice', '.sa-side-card', '.sa-facts-list' ) as $component ) {
	sa_exp_assert( false !== strpos( $css, $component ), "compiled stylesheet ships {$component}" );
}

/* 6 — reversible -------------------------------------------------------------- */
foreach ( array( 'inc/content-2026.php', 'inc/experience-form.php', 'inc/comments-2026.php' ) as $module ) {
	sa_exp_assert( false !== strpos( $functions, $module ), "{$module} is loaded by functions.php" );
}

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} failure(s) in the «ثبت تجربه» / comments guard.\n" );
	exit( 1 );
}

echo "OK: «ثبت تجربه» page, moderation flow and comments guard passed.\n";
exit( 0 );
