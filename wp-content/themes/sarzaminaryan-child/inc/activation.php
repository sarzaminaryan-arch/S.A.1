<?php
/**
 * One-time setup on theme activation (idempotent): seed terms, create pages/menu, flush rewrites.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs when the child theme is activated.
 */
function sa_after_switch_theme() {
	update_option( 'sa_needs_setup', 1, false );
}
add_action( 'after_switch_theme', 'sa_after_switch_theme' );

/**
 * Do the setup on the next init (CPTs/taxonomies are registered by then).
 */
function sa_maybe_run_setup() {
	if ( ! get_option( 'sa_needs_setup' ) || ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	delete_option( 'sa_needs_setup' );

	sa_seed_terms();
	sa_create_default_pages();
	sa_create_default_menu();

	// Level 4 URLs need fresh rewrite rules.
	flush_rewrite_rules();

	// Persian permalink structure recommended: /%postname%/
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
		flush_rewrite_rules();
	}

	set_transient( 'sa_setup_done_notice', 1, 300 );
}
add_action( 'init', 'sa_maybe_run_setup', 99 );

/**
 * Also (re)seed when the model version changes after an update.
 */
function sa_maybe_upgrade() {
	if ( get_option( 'sa_child_version' ) !== SA_CHILD_VERSION ) {
		sa_seed_terms();
		update_option( 'sa_child_version', SA_CHILD_VERSION, false );
		update_option( 'sa_flush_rewrite', 1, false );
	}
	if ( get_option( 'sa_flush_rewrite' ) ) {
		delete_option( 'sa_flush_rewrite' );
		flush_rewrite_rules();
	}
}
add_action( 'init', 'sa_maybe_upgrade', 100 );

/**
 * Create About / Contact / Blog / Home pages once (drafts for editable text, published for structure).
 */
function sa_create_default_pages() {
	$pages = array(
		'home'    => array( 'title' => 'خانه', 'status' => 'publish', 'content' => '' ),
		'blog'    => array( 'title' => 'وبلاگ', 'status' => 'publish', 'content' => '' ),
		'about'   => array( 'title' => 'درباره ما', 'status' => 'draft', 'content' => "<!-- wp:paragraph -->\n<p>سرزمین آریان دانشنامه‌ی سفر ایران است. این متن را با معرفی واقعی تیم، هدف سایت و سیاست تحریریه جایگزین کنید (برای E-E-A-T مهم است).</p>\n<!-- /wp:paragraph -->" ),
		'contact' => array( 'title' => 'تماس با ما', 'status' => 'draft', 'content' => "<!-- wp:paragraph -->\n<p>ایمیل و راه‌های تماس را اینجا بنویسید.</p>\n<!-- /wp:paragraph -->" ),
		'policy'  => array( 'title' => 'سیاست تحریریه و منابع', 'status' => 'draft', 'content' => "<!-- wp:paragraph -->\n<p>توضیح دهید اطلاعات (ساعات، قیمت‌ها، مسافت‌ها) از چه منابعی گردآوری و هر چند وقت یک‌بار بازبینی می‌شود.</p>\n<!-- /wp:paragraph -->" ),
	);
	$ids = get_option( 'sa_default_pages', array() );
	foreach ( $pages as $key => $p ) {
		if ( ! empty( $ids[ $key ] ) && get_post( $ids[ $key ] ) ) {
			continue;
		}
		$existing = get_page_by_path( sanitize_title( $key ) );
		if ( $existing ) {
			$ids[ $key ] = $existing->ID;
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => $p['title'],
				'post_name'    => $key,
				'post_status'  => $p['status'],
				'post_content' => $p['content'],
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			$ids[ $key ] = $id;
		}
	}
	update_option( 'sa_default_pages', $ids, false );

	if ( ! empty( $ids['home'] ) && ! empty( $ids['blog'] ) && 'page' !== get_option( 'show_on_front' ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
		update_option( 'page_for_posts', $ids['blog'] );
	}
}

/**
 * Create and assign a primary + footer menu if none exists.
 */
function sa_create_default_menu() {
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	if ( ! empty( $locations['primary'] ) && wp_get_nav_menu_object( $locations['primary'] ) ) {
		return;
	}
	$menu_id = wp_create_nav_menu( 'منوی اصلی' );
	if ( is_wp_error( $menu_id ) ) {
		return;
	}
	$items = array( array( 'title' => 'خانه', 'url' => home_url( '/' ), 'type' => 'custom' ) );
	foreach ( sa_entity_types() as $type ) {
		$items[] = array( 'title' => sa_entity_label( $type, true ), 'type' => 'post_type_archive', 'object' => $type );
	}
	$pages = get_option( 'sa_default_pages', array() );
	if ( ! empty( $pages['blog'] ) ) {
		$items[] = array( 'title' => 'وبلاگ', 'type' => 'post_type', 'object' => 'page', 'object_id' => $pages['blog'] );
	}
	foreach ( $items as $i => $item ) {
		$args = array(
			'menu-item-title'    => $item['title'],
			'menu-item-status'   => 'publish',
			'menu-item-position' => $i + 1,
			'menu-item-type'     => $item['type'],
		);
		if ( 'custom' === $item['type'] ) {
			$args['menu-item-url'] = $item['url'];
		} else {
			$args['menu-item-object'] = $item['object'];
			if ( isset( $item['object_id'] ) ) {
				$args['menu-item-object-id'] = $item['object_id'];
			}
		}
		wp_update_nav_menu_item( $menu_id, 0, $args );
	}
	$locations['primary'] = $menu_id;

	// Footer menu: about / contact / policy (drafts still link once published).
	$footer_id = wp_create_nav_menu( 'منوی پابرگ' );
	if ( ! is_wp_error( $footer_id ) ) {
		$pos = 1;
		foreach ( array( 'about', 'contact', 'policy' ) as $key ) {
			if ( ! empty( $pages[ $key ] ) ) {
				wp_update_nav_menu_item(
					$footer_id,
					0,
					array(
						'menu-item-title'     => get_the_title( $pages[ $key ] ),
						'menu-item-status'    => 'publish',
						'menu-item-position'  => $pos++,
						'menu-item-type'      => 'post_type',
						'menu-item-object'    => 'page',
						'menu-item-object-id' => $pages[ $key ],
					)
				);
			}
		}
		$locations['footer'] = $footer_id;
	}
	set_theme_mod( 'nav_menu_locations', $locations );
}

/**
 * Post-activation notice.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! get_transient( 'sa_setup_done_notice' ) ) {
			return;
		}
		delete_transient( 'sa_setup_done_notice' );
		echo '<div class="notice notice-success is-dismissible"><p><strong>قالب سرزمین آریان فعال شد.</strong> ۳۱ استان و واژگان ثابت ساخته شدند، صفحه‌های خانه/وبلاگ/درباره/تماس ایجاد شدند و منوها تنظیم شدند. ادامه: <a href="' . esc_url( admin_url( 'admin.php?page=sarzaminaryan' ) ) . '">داشبورد محتوا</a> · <a href="' . esc_url( admin_url( 'customize.php?autofocus[panel]=sa_panel' ) ) . '">تنظیمات قالب</a> · <a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">زبان/منطقه زمانی (fa_IR, Asia/Tehran)</a></p></div>';
	}
);
