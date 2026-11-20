<?php
/**
 * «ثبت تجربه» — a real, moderated submission page (2026 design).
 *
 * Owner report (۲۹ آبان ۱۴۰۵): the call to action led to a 404. This module serves
 * a real page at /city-contrib/ (plus /contribute/ as an alias), accepts a text
 * story and one photo, stores the submission as a **pending** post of the private
 * `sa_experience` type — visible and reviewable in WP admin — and never publishes
 * anything by itself.
 *
 * Rules kept from the repo: no alert() in the UI, no secret in the browser, all
 * input sanitised, uploads limited by type and size, and a light spam guard
 * (honeypot + per-IP rate limit) that needs no third-party service.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SA_EXPERIENCE_POST_TYPE = 'sa_experience';

/**
 * Submission storage type (private: never in sitemaps, feeds or search).
 */
function sa_experience_register_post_type() {
	register_post_type(
		SA_EXPERIENCE_POST_TYPE,
		array(
			'labels'        => array(
				'name'          => 'تجربه‌های ارسالی',
				'singular_name' => 'تجربهٔ ارسالی',
				'menu_name'     => 'تجربه‌ها (ارسالی)',
				'all_items'     => 'همهٔ تجربه‌های ارسالی',
				'edit_item'     => 'بررسی تجربه',
				'view_item'     => 'نمایش تجربه',
				'search_items'  => 'جست‌وجوی تجربه‌ها',
				'not_found'     => 'تجربه‌ای ثبت نشده است.',
			),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 26,
			'menu_icon'     => 'dashicons-format-status',
			'supports'      => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'capability_type' => 'post',
			'map_meta_cap'  => true,
			'has_archive'   => false,
			'rewrite'       => false,
			'query_var'     => false,
			'exclude_from_search' => true,
			'show_in_rest'  => false,
		)
	);
}
add_action( 'init', 'sa_experience_register_post_type', 6 );

/**
 * Public URL of the submission page.
 *
 * @return string
 */
function sa_experience_form_url() {
	$page = get_page_by_path( 'city-contrib' );
	if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
		return (string) get_permalink( $page );
	}

	return home_url( '/city-contrib/' );
}

/**
 * Does the owner already have a real page for the form?
 *
 * @return bool
 */
function sa_experience_has_page() {
	$page = get_page_by_path( 'city-contrib' );

	return $page instanceof WP_Post && 'publish' === $page->post_status;
}

/**
 * Virtual route so the page works even when no WP page with that slug exists.
 *
 * Registered only while the owner has no real page: as soon as one exists the page
 * itself renders the form, and the route must not shadow it.
 *
 * @return void
 */
function sa_experience_register_route() {
	if ( sa_experience_has_page() ) {
		return;
	}
	add_rewrite_rule( '^city-contrib/?$', 'index.php?sa_experience_form=1', 'top' );
	add_rewrite_rule( '^contribute/?$', 'index.php?sa_experience_form=1', 'top' );
}
add_action( 'init', 'sa_experience_register_route', 7 );

/**
 * Register the query var.
 *
 * @param array<int,string> $vars Existing vars.
 * @return array<int,string>
 */
function sa_experience_query_vars( $vars ) {
	$vars[] = 'sa_experience_form';
	return $vars;
}
add_filter( 'query_vars', 'sa_experience_query_vars' );

/**
 * Flush the rewrite rules once after a deploy (the route is new).
 *
 * @return void
 */
function sa_experience_maybe_flush() {
	$flag = 'sa_experience_route_version';
	if ( get_option( $flag ) === SA_CHILD_VERSION ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( $flag, SA_CHILD_VERSION, false );
}
add_action( 'init', 'sa_experience_maybe_flush', 20 );

/**
 * Render the submission page for the virtual route.
 *
 * @param string $template Current template.
 * @return string
 */
function sa_experience_route_template( $template ) {
	if ( ! get_query_var( 'sa_experience_form' ) ) {
		return $template;
	}

	$own = SA_CHILD_DIR . 'template-experience.php';

	return is_readable( $own ) ? $own : $template;
}
add_filter( 'template_include', 'sa_experience_route_template', 20 );

/**
 * The virtual route must not 404.
 *
 * @param array<string,mixed> $query_vars Query vars.
 * @return array<string,mixed>
 */
function sa_experience_force_200( $query_vars ) {
	if ( ! empty( $query_vars['sa_experience_form'] ) ) {
		$query_vars['error'] = '';
	}
	return $query_vars;
}
add_filter( 'request', 'sa_experience_force_200' );

/**
 * Drop the 404 that core sets for a query with no posts.
 *
 * `WP::handle_404()` marks every empty query as a 404 before `template_redirect`, and
 * our virtual page has no post object. Clearing it here keeps the response 200 (the
 * whole point of this module) while the template still renders.
 *
 * @return void
 */
function sa_experience_unset_404() {
	if ( ! get_query_var( 'sa_experience_form' ) ) {
		return;
	}

	global $wp_query;
	if ( $wp_query instanceof WP_Query ) {
		$wp_query->is_404 = false; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	}
	status_header( 200 );
	nocache_headers();
}
add_action( 'template_redirect', 'sa_experience_unset_404', 1 );

/**
 * Is the current request the submission page (real page or virtual route)?
 *
 * Used by `page.php` so a real `city-contrib` page renders the form, and by the
 * template wrapper for the virtual route.
 *
 * @return bool
 */
function sa_experience_is_form_request() {
	if ( get_query_var( 'sa_experience_form' ) ) {
		return true;
	}
	if ( ! is_page() ) {
		return false;
	}

	$page = get_queried_object();
	if ( ! $page instanceof WP_Post ) {
		return false;
	}

	return in_array( $page->post_name, array( 'city-contrib', 'contribute', 'submit-experience' ), true )
		|| 'template-experience.php' === (string) get_page_template_slug( $page );
}

/**
 * Title for the virtual route (no post object exists there).
 *
 * @param string $title Document title.
 * @return string
 */
function sa_experience_document_title( $title ) {
	if ( get_query_var( 'sa_experience_form' ) ) {
		return 'ثبت تجربه | ' . get_bloginfo( 'name' );
	}

	return $title;
}
add_filter( 'pre_get_document_title', 'sa_experience_document_title', 20 );

/**
 * Was the last submission accepted?
 *
 * @return string '' | 'sent' | error code.
 */
function sa_experience_flash() {
	return isset( $_GET['sa_exp'] ) ? sanitize_key( wp_unslash( $_GET['sa_exp'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

/**
 * Store one submission.
 *
 * @return void
 */
function sa_experience_handle_submit() {
	$back = sa_experience_form_url();

	if ( ! isset( $_POST['sa_experience_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sa_experience_nonce'] ) ), 'sa_experience_submit' ) ) {
		wp_safe_redirect( add_query_arg( 'sa_exp', 'expired', $back ) );
		exit;
	}

	// Honeypot: real people never fill this hidden field.
	if ( ! empty( $_POST['sa_exp_website'] ) ) {
		wp_safe_redirect( add_query_arg( 'sa_exp', 'sent', $back ) );
		exit;
	}

	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	$key   = 'sa_exp_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= 3 ) {
		wp_safe_redirect( add_query_arg( 'sa_exp', 'limit', $back ) );
		exit;
	}

	$name    = isset( $_POST['sa_exp_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_exp_name'] ) ) : '';
	$email   = isset( $_POST['sa_exp_email'] ) ? sanitize_email( wp_unslash( $_POST['sa_exp_email'] ) ) : '';
	$place   = isset( $_POST['sa_exp_place'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_exp_place'] ) ) : '';
	$story   = isset( $_POST['sa_exp_story'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sa_exp_story'] ) ) : '';
	$consent = ! empty( $_POST['sa_exp_consent'] );

	if ( mb_strlen( $story ) < 40 ) {
		wp_safe_redirect( add_query_arg( 'sa_exp', 'short', $back ) );
		exit;
	}
	if ( mb_strlen( $story ) > 6000 ) {
		$story = mb_substr( $story, 0, 6000 );
	}
	if ( '' === $name ) {
		$name = 'مسافر ناشناس';
	}
	if ( ! $consent ) {
		wp_safe_redirect( add_query_arg( 'sa_exp', 'consent', $back ) );
		exit;
	}

	$title = $place ? ( 'تجربهٔ ' . $name . ' — ' . $place ) : ( 'تجربهٔ ' . $name );

	$post_id = wp_insert_post(
		array(
			'post_type'    => SA_EXPERIENCE_POST_TYPE,
			'post_status'  => 'pending',
			'post_title'   => $title,
			'post_content' => $story,
			'post_author'  => 0,
			'meta_input'   => array(
				'sa_exp_name'    => $name,
				'sa_exp_email'   => $email,
				'sa_exp_place'   => $place,
				'sa_exp_ip'      => $ip,
				'sa_exp_consent' => $consent ? '1' : '0',
				'sa_exp_agent'   => substr( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '', 0, 180 ),
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		wp_safe_redirect( add_query_arg( 'sa_exp', 'failed', $back ) );
		exit;
	}

	$photo_error = '';
	if ( ! empty( $_FILES['sa_exp_photo']['name'] ) ) {
		$photo_error = sa_experience_store_photo( (int) $post_id );
	}

	set_transient( $key, $count + 1, HOUR_IN_SECONDS );

	$args = array( 'sa_exp' => $photo_error ? 'photo' : 'sent' );
	wp_safe_redirect( add_query_arg( $args, $back ) );
	exit;
}
add_action( 'admin_post_sa_experience_submit', 'sa_experience_handle_submit' );
add_action( 'admin_post_nopriv_sa_experience_submit', 'sa_experience_handle_submit' );

/**
 * Attach one uploaded photo to the submission (guarded by type and size).
 *
 * @param int $post_id Submission post.
 * @return string '' on success, error code otherwise.
 */
function sa_experience_store_photo( $post_id ) {
	if ( empty( $_FILES['sa_exp_photo']['tmp_name'] ) || ! is_uploaded_file( $_FILES['sa_exp_photo']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return '';
	}

	$size = isset( $_FILES['sa_exp_photo']['size'] ) ? (int) $_FILES['sa_exp_photo']['size'] : 0;
	if ( $size > 6 * MB_IN_BYTES ) {
		return 'photo';
	}

	$type = isset( $_FILES['sa_exp_photo']['type'] ) ? sanitize_mime_type( wp_unslash( $_FILES['sa_exp_photo']['type'] ) ) : '';
	if ( ! in_array( $type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
		return 'photo';
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$attachment_id = media_handle_upload(
		'sa_exp_photo',
		$post_id,
		array(
			'post_title'   => get_the_title( $post_id ),
			'post_content' => 'عکس ارسالی برای تجربهٔ «' . get_the_title( $post_id ) . '»',
		),
		array( 'test_form' => false )
	);

	if ( is_wp_error( $attachment_id ) ) {
		return 'photo';
	}

	update_post_meta( $post_id, 'sa_exp_photo_id', (int) $attachment_id );

	return '';
}

/**
 * Admin list columns so the team can triage submissions quickly.
 *
 * @param array<string,string> $columns Columns.
 * @return array<string,string>
 */
function sa_experience_columns( $columns ) {
	return array(
		'cb'       => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
		'title'    => 'تجربه',
		'sa_name'  => 'فرستنده',
		'sa_place' => 'مکان',
		'sa_email' => 'ایمیل',
		'sa_photo' => 'عکس',
		'date'     => 'تاریخ',
	);
}
add_filter( 'manage_' . SA_EXPERIENCE_POST_TYPE . '_posts_columns', 'sa_experience_columns' );

/**
 * Fill the custom columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function sa_experience_column_value( $column, $post_id ) {
	$map = array(
		'sa_name'  => 'sa_exp_name',
		'sa_place' => 'sa_exp_place',
		'sa_email' => 'sa_exp_email',
	);
	if ( isset( $map[ $column ] ) ) {
		echo esc_html( (string) get_post_meta( $post_id, $map[ $column ], true ) );
		return;
	}
	if ( 'sa_photo' === $column ) {
		$thumb = (int) get_post_meta( $post_id, 'sa_exp_photo_id', true );
		echo $thumb ? '<span class="dashicons dashicons-yes-alt" style="color:#1a7f37"></span>' : '—';
	}
}
add_action( 'manage_' . SA_EXPERIENCE_POST_TYPE . '_posts_custom_column', 'sa_experience_column_value', 10, 2 );

/**
 * Unread badge next to the menu entry.
 *
 * @return void
 */
function sa_experience_pending_badge() {
	global $menu;
	$count = (int) wp_count_posts( SA_EXPERIENCE_POST_TYPE )->pending;
	if ( ! $count ) {
		return;
	}
	foreach ( (array) $menu as $index => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=' . SA_EXPERIENCE_POST_TYPE === $item[2] ) {
			$menu[ $index ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . esc_html( number_format_i18n( $count ) ) . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			break;
		}
	}
}
add_action( 'admin_menu', 'sa_experience_pending_badge', 99 );
