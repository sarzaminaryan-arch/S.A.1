<?php
/**
 * لایهٔ شبیه‌سازِ وردپرس برای اجرای تست‌های قالب در php-wasm.
 * این فایل فقط در محیطِ تست (tools/sa-tests) استفاده می‌شود و در قالب منتشر نمی‌شود.
 *
 * @package sa-tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/ws/' );
}
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['sa_options']      = array();
$GLOBALS['sa_meta']         = array();
$GLOBALS['sa_posts']        = array();
$GLOBALS['sa_next_id']      = 1;
$GLOBALS['sa_transients']   = array();
$GLOBALS['sa_events']       = array();
$GLOBALS['sa_http_queue']   = array();
$GLOBALS['sa_http_log']     = array();
$GLOBALS['sa_filters']      = array();
$GLOBALS['sa_menu_pages']   = array();
$GLOBALS['sa_rest_routes']  = array();
$GLOBALS['sa_caps']         = array( 'manage_options' => true );
$GLOBALS['sa_died']         = null;
$GLOBALS['sa_redirects']    = array();
$GLOBALS['sa_meta_boxes']   = array();
$GLOBALS['sa_settings_reg'] = array();
$GLOBALS['sa_taxonomies']   = array();
$GLOBALS['sa_terms_reg']    = array();
$GLOBALS['sa_next_term_id'] = 1;

/* ---------------------------------------------------------------- کلاس‌ها */

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code    = (string) $code;
		$this->message = (string) $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

class WP_Post {
	public $ID;
	public $post_title;
	public $post_type     = 'post';
	public $post_status   = 'publish';
	public $post_content  = '';
	public $post_excerpt  = '';
	public $post_name     = '';
	public $post_date     = '2026-01-01 10:00:00';
	public $post_date_gmt = '2026-01-01 10:00:00';
	public $menu_order    = 0;
	public function __construct( $args = array() ) {
		foreach ( (array) $args as $key => $value ) {
			$this->$key = $value;
		}
	}
}

class WP_REST_Server {
	const READABLE   = 'GET';
	const CREATABLE  = 'POST';
	const EDITABLE   = 'POST, PUT, PATCH';
	const DELETABLE  = 'DELETE';
	const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
}

class WP_REST_Request {
	private $params = array();
	private $body   = '';
	private $json   = null;
	public function __construct( $params = array(), $body = '' ) {
		$this->params = (array) $params;
		$this->body   = (string) $body;
		$this->json   = json_decode( $this->body, true );
	}
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
	public function get_params() { return $this->params; }
	public function get_json_params() { return is_array( $this->json ) ? $this->json : array(); }
	public function get_body() { return $this->body; }
}

class WP_REST_Response {
	public $data;
	public $status;
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = (int) $status;
	}
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
}

/* ------------------------------------------------------------ داده‌های تست */

/**
 * ساخت یک نوشتهٔ آزمایشی.
 *
 * @param array<string,mixed> $args آرگومان‌ها.
 * @return int شناسه.
 */
function sa_add_post( $args ) {
	$id  = (int) $GLOBALS['sa_next_id']++;
	$defaults = array(
		'ID'           => $id,
		'post_title'   => 'عنوان ' . $id,
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_content' => '',
		'post_excerpt' => '',
		'post_name'    => 'post-' . $id,
		'meta'         => array(),
	);
	$args  = array_merge( $defaults, (array) $args );
	$meta  = (array) $args['meta'];
	unset( $args['meta'] );

	$post = new WP_Post( $args );
	$GLOBALS['sa_posts'][ $id ] = $post;
	foreach ( $meta as $key => $value ) {
		$GLOBALS['sa_meta'][ $id ][ $key ] = array( $value );
	}
	return $id;
}

/**
 * تنظیمِ شناسهٔ کاربر فعلی و توانمندی‌ها.
 *
 * @param array<string,bool> $caps توانمندی‌ها.
 */
function sa_set_caps( $caps ) {
	$GLOBALS['sa_caps'] = (array) $caps;
}

/**
 * بازنشانیِ کاملِ وضعیتِ تست.
 */
function sa_reset_test_state() {
	// توجه: قلاب‌های ثبت‌شده (add_action) عمداً پاک نمی‌شوند،
	// چون ماژول‌ها هنگام بارگذاری ثبت می‌کنند و باید در همهٔ بخش‌های تست زنده بمانند.
	$GLOBALS['sa_options']      = array();
	$GLOBALS['sa_meta']         = array();
	$GLOBALS['sa_posts']        = array();
	$GLOBALS['sa_next_id']      = 1;
	$GLOBALS['sa_transients']   = array();
	$GLOBALS['sa_events']       = array();
	$GLOBALS['sa_http_queue']   = array();
	$GLOBALS['sa_http_log']     = array();
	$GLOBALS['sa_menu_pages']   = array();
	$GLOBALS['sa_rest_routes']  = array();
	$GLOBALS['sa_caps']         = array( 'manage_options' => true );
	$GLOBALS['sa_died']         = null;
	$GLOBALS['sa_redirects']    = array();
	$GLOBALS['sa_meta_boxes']   = array();
	$GLOBALS['sa_settings_reg'] = array();
	$GLOBALS['sa_taxonomies']   = array();
	$GLOBALS['sa_terms_reg']    = array();
	$GLOBALS['sa_next_term_id'] = 1;
}

/**
 * افزودن یک پاسخٔ شبیه‌سازی‌شده به صفِ درخواست‌های HTTP.
 *
 * @param mixed  $body    بدنه (رشته یا آرایه؛ آرایه json می‌شود).
 * @param int    $code    کد پاسخ.
 * @param string $error   در صورت نیاز، پیام خطا (به‌جای پاسخ، WP_Error برمی‌گردد).
 */
function sa_queue_response( $body = '', $code = 200, $error = '' ) {
	$GLOBALS['sa_http_queue'][] = array(
		'body'  => is_array( $body ) ? json_encode( $body ) : (string) $body,
		'code'  => (int) $code,
		'error' => (string) $error,
	);
}

/**
 * آخرین درخواستِ ارسال‌شده.
 *
 * @return array{url:string,args:array<string,mixed>}|null
 */
function sa_last_request() {
	$log = $GLOBALS['sa_http_log'];
	return empty( $log ) ? null : $log[ count( $log ) - 1 ];
}

/**
 * همهٔ درخواست‌های ارسال‌شده.
 *
 * @return array<int,array{url:string,args:array<string,mixed>}>
 */
function sa_requests() {
	return $GLOBALS['sa_http_log'];
}

/**
 * رویدادهای زمان‌بندی‌شده.
 *
 * @param string $hook نام قلاب (اختیاری).
 * @return array<int,array{hook:string,time:int,args:array<int,mixed>}>
 */
function sa_events( $hook = '' ) {
	$events = $GLOBALS['sa_events'];
	if ( '' === $hook ) {
		return $events;
	}
	$found = array();
	foreach ( $events as $event ) {
		if ( $event['hook'] === $hook ) {
			$found[] = $event;
		}
	}
	return $found;
}

/**
 * اجرای دستیِ یک رویدادِ زمان‌بندی‌شده.
 *
 * @param string $hook نام قلاب.
 * @return bool
 */
function sa_run_event( $hook ) {
	$found = false;
	foreach ( $GLOBALS['sa_events'] as $index => $event ) {
		if ( $event['hook'] !== $hook ) {
			continue;
		}
		$found = true;
		unset( $GLOBALS['sa_events'][ $index ] );
		if ( isset( $GLOBALS['sa_filters'][ $hook ] ) ) {
			foreach ( $GLOBALS['sa_filters'][ $hook ] as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					call_user_func_array( $callback['callback'], $event['args'] );
				}
			}
		}
	}
	$GLOBALS['sa_events'] = array_values( $GLOBALS['sa_events'] );
	return $found;
}

/* --------------------------------------------------------------- گزینه‌ها */

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['sa_options'] ) ? $GLOBALS['sa_options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['sa_options'][ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['sa_options'][ $key ] );
	return true;
}

/* ------------------------------------------------------------------- متا */

function get_post_meta( $post_id, $key = '', $single = false ) {
	$all = isset( $GLOBALS['sa_meta'][ (int) $post_id ] ) ? $GLOBALS['sa_meta'][ (int) $post_id ] : array();
	if ( '' === $key ) {
		return $all;
	}
	if ( ! isset( $all[ $key ] ) ) {
		return $single ? '' : array();
	}
	return $single ? $all[ $key ][0] : $all[ $key ];
}
function update_post_meta( $post_id, $key, $value, $prev = '' ) {
	$GLOBALS['sa_meta'][ (int) $post_id ][ (string) $key ] = array( $value );
	return true;
}
function add_post_meta( $post_id, $key, $value, $unique = false ) {
	$GLOBALS['sa_meta'][ (int) $post_id ][ (string) $key ][] = $value;
	return true;
}
function delete_post_meta( $post_id, $key, $value = '' ) {
	unset( $GLOBALS['sa_meta'][ (int) $post_id ][ (string) $key ] );
	return true;
}
function update_term_meta( $term_id, $key, $value ) { return true; }

/* ---------------------------------------------------------------- نوشته‌ها */

function get_post( $post = null ) {
	$id = is_object( $post ) ? (int) $post->ID : (int) $post;
	if ( ! $id ) {
		return null;
	}
	return isset( $GLOBALS['sa_posts'][ $id ] ) ? $GLOBALS['sa_posts'][ $id ] : null;
}
function get_post_type( $post = null ) {
	$post = is_object( $post ) ? $post : get_post( $post );
	return $post ? $post->post_type : false;
}
function get_post_status( $post = null ) {
	$post = is_object( $post ) ? $post : get_post( $post );
	return $post ? $post->post_status : false;
}
function get_post_field( $field, $post = null, $context = 'display' ) {
	$post = is_object( $post ) ? $post : get_post( $post );
	return $post && isset( $post->$field ) ? $post->$field : '';
}
function get_the_title( $post = null ) {
	$post = is_object( $post ) ? $post : get_post( $post );
	return $post ? (string) $post->post_title : '';
}
function get_permalink( $post = null ) {
	$post = is_object( $post ) ? $post : get_post( $post );
	if ( ! $post ) {
		return '';
	}
	return 'https://sarzaminaryan.test/' . $post->post_type . '/' . $post->post_name . '/';
}
function get_the_date( $format = '', $post = null ) {
	return '1404/07/14';
}
function get_the_post_thumbnail_url( $post = null, $size = 'full' ) {
	$post = is_object( $post ) ? $post : get_post( $post );
	if ( ! $post ) {
		return false;
	}
	$thumb = get_post_meta( $post->ID, '_thumb', true );
	return '' === $thumb ? false : $thumb;
}
function post_type_exists( $type ) {
	return in_array( $type, array( 'post', 'page', 'province', 'city', 'attraction', 'travel_route', 'local_food', 'souvenir' ), true );
}
function get_object_taxonomies( $type ) {
	return array( 'province_tax' );
}

/**
 * شبیه‌سازِ WP_Query::get_posts.
 *
 * @param array<string,mixed> $args آرگومان‌ها.
 * @return array<int,WP_Post|int>
 */
function get_posts( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'fields'         => '',
			'meta_query'     => array(),
			'tax_query'      => array(),
			'post__in'       => array(),
			's'              => '',
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	if ( ! empty( $args['tax_query'] ) ) {
		return array(); // ترم‌ها در این شبیه‌ساز پشتیبانی نمی‌شوند.
	}

	$types   = (array) $args['post_type'];
	$status  = (array) $args['post_status'];
	$results = array();

	foreach ( $GLOBALS['sa_posts'] as $id => $post ) {
		if ( ! in_array( $post->post_type, $types, true ) ) {
			continue;
		}
		if ( ! in_array( $post->post_status, $status, true ) ) {
			continue;
		}
		if ( ! empty( $args['post__in'] ) && ! in_array( (int) $id, array_map( 'intval', (array) $args['post__in'] ), true ) ) {
			continue;
		}
		if ( '' !== (string) $args['s'] ) {
			$haystack = $post->post_title . ' ' . $post->post_content;
			if ( false === strpos( $haystack, (string) $args['s'] ) ) {
				continue;
			}
		}
		$ok = true;
		foreach ( (array) $args['meta_query'] as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
				continue;
			}
			$value = get_post_meta( $id, $clause['key'], true );
			if ( is_array( $value ) ) {
				if ( ! in_array( (int) $clause['value'], array_map( 'intval', $value ), true ) ) {
					$ok = false;
				}
			} elseif ( (string) $value !== (string) $clause['value'] ) {
				$ok = false;
			}
		}
		if ( ! $ok ) {
			continue;
		}
		$results[] = $post;
	}

	if ( 'title' === $args['orderby'] ) {
		usort(
			$results,
			function ( $a, $b ) {
				return strcmp( $a->post_title, $b->post_title );
			}
		);
		if ( 'DESC' === strtoupper( (string) $args['order'] ) ) {
			$results = array_reverse( $results );
		}
	} elseif ( 'post__in' === $args['orderby'] && ! empty( $args['post__in'] ) ) {
		$order   = array_map( 'intval', (array) $args['post__in'] );
		$results = array_values(
			array_filter(
				array_map(
					function ( $id ) {
						return isset( $GLOBALS['sa_posts'][ $id ] ) ? $GLOBALS['sa_posts'][ $id ] : null;
					},
					$order
				)
			)
		);
	}

	$limit = (int) $args['posts_per_page'];
	if ( $limit > 0 ) {
		$results = array_slice( $results, 0, $limit );
	}

	if ( 'ids' === $args['fields'] ) {
		return array_map(
			function ( $post ) {
				return (int) $post->ID;
			},
			$results
		);
	}

	return $results;
}

function wp_insert_post( $args, $error = false ) {
	return sa_add_post( $args );
}
function wp_update_post( $args ) {
	$post = get_post( isset( $args['ID'] ) ? $args['ID'] : 0 );
	if ( ! $post ) {
		return 0;
	}
	foreach ( (array) $args as $key => $value ) {
		if ( 'ID' === $key ) {
			continue;
		}
		$post->$key = $value;
	}
	return (int) $post->ID;
}

/* ------------------------------------------------- پیوست‌ها و تصویرها */

function wp_get_attachment_url( $attachment_id ) {
	return 'https://sarzaminaryan.test/wp-content/uploads/gallery/file-' . (int) $attachment_id . '.webp';
}
function wp_get_attachment_image_src( $attachment_id, $size = 'thumbnail', $icon = false ) {
	$sizes = array(
		'full'            => array( 1600, 1200 ),
		'sa-gallery-large' => array( 1200, 900 ),
		'sa-gallery-card' => array( 600, 450 ),
	);
	$dim = isset( $sizes[ $size ] ) ? $sizes[ $size ] : array( 300, 200 );
	return array( wp_get_attachment_url( $attachment_id ), $dim[0], $dim[1], false );
}
function has_post_thumbnail( $post = null ) {
	$post = is_object( $post ) ? $post : get_post( $post );
	return $post ? (bool) get_post_meta( $post->ID, '_thumb_id', true ) : false;
}
function get_post_thumbnail_id( $post = null ) {
	$post = is_object( $post ) ? $post : get_post( $post );
	return $post ? (int) get_post_meta( $post->ID, '_thumb_id', true ) : 0;
}

/* --------------------------------------------------------------- ترم‌ها */

function get_the_terms( $post, $taxonomy ) { return array(); }
function register_taxonomy( $taxonomy, $object_type, $args = array() ) {
	$GLOBALS['sa_taxonomies'][ (string) $taxonomy ] = array(
		'object_type' => (array) $object_type,
		'args'        => (array) $args,
	);
	return true;
}
function taxonomy_exists( $taxonomy ) {
	return isset( $GLOBALS['sa_taxonomies'][ (string) $taxonomy ] );
}
function term_exists( $term, $taxonomy = '', $parent = null ) {
	$tax = (string) $taxonomy;
	if ( ! isset( $GLOBALS['sa_terms_reg'][ $tax ] ) ) {
		return null;
	}
	return isset( $GLOBALS['sa_terms_reg'][ $tax ][ (string) $term ] ) ? $GLOBALS['sa_terms_reg'][ $tax ][ (string) $term ] : null;
}
function wp_insert_term( $term, $taxonomy, $args = array() ) {
	$id  = (int) $GLOBALS['sa_next_term_id']++;
	$key = isset( $args['slug'] ) && '' !== $args['slug'] ? (string) $args['slug'] : sanitize_title( (string) $term );
	$GLOBALS['sa_terms_reg'][ (string) $taxonomy ][ $key ] = array(
		'term_id' => $id,
		'term_taxonomy_id' => $id,
	);
	return array( 'term_id' => $id, 'term_taxonomy_id' => $id );
}
function sanitize_title( $title, $fallback = '', $context = 'save' ) {
	$out = strtolower( (string) $title );
	$out = preg_replace( '/[^a-z0-9\-]+/', '-', $out );
	return trim( $out, '-' );
}
function wp_get_post_terms( $post, $taxonomy, $args = array() ) { return array(); }
function get_term_by( $field, $value, $taxonomy = '' ) { return false; }
function wp_set_post_terms( $post_id, $terms, $taxonomy, $append = false ) { return array(); }

/* ------------------------------------------------------------ ترنزینت‌ها */

function get_transient( $key ) {
	if ( ! isset( $GLOBALS['sa_transients'][ $key ] ) ) {
		return false;
	}
	$item = $GLOBALS['sa_transients'][ $key ];
	if ( $item['expiration'] > 0 && $item['expiration'] < time() ) {
		return false;
	}
	return $item['value'];
}
function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['sa_transients'][ $key ] = array(
		'value'      => $value,
		'expiration' => (int) $expiration,
	);
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['sa_transients'][ $key ] );
	return true;
}

/* ----------------------------------------------------------------- HTTP */

function sa_http_dispatch( $url, $args ) {
	$GLOBALS['sa_http_log'][] = array(
		'url'  => (string) $url,
		'args' => (array) $args,
	);
	if ( empty( $GLOBALS['sa_http_queue'] ) ) {
		return new WP_Error( 'http_request_failed', 'هیچ پاسخِ شبیه‌سازی‌شده‌ای در صف نیست.' );
	}
	$queued = array_shift( $GLOBALS['sa_http_queue'] );
	if ( '' !== $queued['error'] ) {
		return new WP_Error( 'http_request_failed', $queued['error'] );
	}
	return array(
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => $queued['body'],
		'response' => array(
			'code'    => $queued['code'],
			'message' => 200 === $queued['code'] ? 'OK' : 'Error',
		),
		'cookies'  => array(),
	);
}
function wp_remote_post( $url, $args = array() ) {
	return sa_http_dispatch( $url, $args );
}
function wp_remote_get( $url, $args = array() ) {
	return sa_http_dispatch( $url, $args );
}
function wp_remote_retrieve_body( $response ) {
	return is_array( $response ) && isset( $response['body'] ) ? $response['body'] : '';
}
function wp_remote_retrieve_response_code( $response ) {
	return is_array( $response ) && isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

/* ---------------------------------------------------------- زمان‌بندی */

function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
	$GLOBALS['sa_events'][] = array(
		'hook' => (string) $hook,
		'time' => (int) $timestamp,
		'args' => (array) $args,
	);
	return true;
}
function wp_next_scheduled( $hook, $args = array() ) {
	foreach ( $GLOBALS['sa_events'] as $event ) {
		if ( $event['hook'] === $hook ) {
			return $event['time'];
		}
	}
	return false;
}
function wp_clear_scheduled_hook( $hook, $args = array() ) {
	$removed = 0;
	foreach ( $GLOBALS['sa_events'] as $index => $event ) {
		if ( $event['hook'] === $hook ) {
			unset( $GLOBALS['sa_events'][ $index ] );
			$removed++;
		}
	}
	$GLOBALS['sa_events'] = array_values( $GLOBALS['sa_events'] );
	return $removed;
}

/* ------------------------------------------------------- قلاب‌ها (هوک‌ها) */

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['sa_filters'][ $hook ][ (int) $priority ][] = array( 'callback' => $callback );
	return true;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return add_filter( $hook, $callback, $priority, $args );
}
function has_filter( $hook, $callback = false ) {
	return isset( $GLOBALS['sa_filters'][ $hook ] );
}
function apply_filters( $hook, $value ) {
	$all_args = func_get_args();
	if ( ! isset( $GLOBALS['sa_filters'][ $hook ] ) ) {
		return $value;
	}
	$priorities = $GLOBALS['sa_filters'][ $hook ];
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$args    = array_slice( $all_args, 1 );
			$args[0] = $value;
			$value   = call_user_func_array( $callback['callback'], $args );
		}
	}
	return $value;
}
function do_action( $hook ) {
	$all_args = array_slice( func_get_args(), 1 );
	if ( ! isset( $GLOBALS['sa_filters'][ $hook ] ) ) {
		return;
	}
	$priorities = $GLOBALS['sa_filters'][ $hook ];
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			call_user_func_array( $callback['callback'], $all_args );
		}
	}
}

/* ------------------------------------------------------------- REST */

function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
	$GLOBALS['sa_rest_routes'][] = array(
		'namespace' => (string) $namespace,
		'route'     => (string) $route,
		'args'      => (array) $args,
	);
	return true;
}
function rest_url( $path = '', $scheme = 'rest' ) {
	return 'https://sarzaminaryan.test/wp-json/' . ltrim( (string) $path, '/' );
}

/* ----------------------------------------------------------- پیشخوان */

function add_submenu_page( $parent, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
	$GLOBALS['sa_menu_pages'][] = array(
		'parent'   => $parent,
		'title'    => $page_title,
		'slug'     => $menu_slug,
		'callback' => $callback,
		'cap'      => $capability,
	);
	return $menu_slug;
}
function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $args = null ) {
	$GLOBALS['sa_meta_boxes'][] = array(
		'id'       => $id,
		'title'    => $title,
		'callback' => $callback,
		'screen'   => $screen,
	);
}
function register_setting( $group, $name, $args = array() ) {
	$GLOBALS['sa_settings_reg'][ $name ] = $args;
}
function current_user_can( $cap ) {
	return ! empty( $GLOBALS['sa_caps'][ $cap ] );
}
function wp_die( $message = '', $title = '', $args = array() ) {
	$GLOBALS['sa_died'] = (string) $message;
	throw new Exception( 'wp_die: ' . (string) $message );
}
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	return true;
}
function wp_verify_nonce( $nonce, $action = -1 ) {
	return 1;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	$html = '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce" />';
	if ( $display ) {
		echo $html;
	}
	return $html;
}
function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) {
	return $url . '&' . $name . '=nonce';
}
function settings_fields( $group ) {
	echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '" />';
}
function submit_button( $text = 'ذخیره', $type = 'primary', $name = 'submit', $wrap = true, $attrs = '' ) {
	echo '<button type="submit" class="button button-' . esc_attr( $type ) . '">' . esc_html( $text ) . '</button>';
}
function admin_url( $path = '', $scheme = 'admin' ) {
	return 'https://sarzaminaryan.test/wp-admin/' . ltrim( (string) $path, '/' );
}
function home_url( $path = '', $scheme = null ) {
	return 'https://sarzaminaryan.test/' . ltrim( (string) $path, '/' );
}
function site_url( $path = '', $scheme = null ) {
	return home_url( $path );
}
function get_current_screen() {
	return null;
}
function get_edit_post_link( $post = 0, $context = 'display' ) {
	$post = is_object( $post ) ? $post : get_post( $post );
	return $post ? admin_url( 'post.php?post=' . (int) $post->ID ) : '';
}
function wp_safe_redirect( $url, $status = 302 ) {
	$GLOBALS['sa_redirects'][] = $url;
	return true;
}
function checked( $checked, $current = true, $display = true ) {
	$html = ( (string) $checked === (string) $current ) ? ' checked=\'checked\'' : '';
	if ( $display ) {
		echo $html;
	}
	return $html;
}
function selected( $selected, $current = true, $display = true ) {
	$html = ( (string) $selected === (string) $current ) ? ' selected=\'selected\'' : '';
	if ( $display ) {
		echo $html;
	}
	return $html;
}

/* ------------------------------------------------------------- متفرقه */

function wp_parse_args( $args, $defaults = array() ) {
	if ( is_object( $args ) ) {
		$args = get_object_vars( $args );
	}
	return array_merge( (array) $defaults, (array) $args );
}
function wp_parse_url( $url, $component = -1 ) {
	return call_user_func( 'parse_url', $url, $component );
}
function wp_rand( $min = 0, $max = 0 ) {
	return rand( (int) $min, (int) $max );
}
function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$out   = '';
	for ( $i = 0; $i < (int) $length; $i++ ) {
		$out .= $chars[ rand( 0, strlen( $chars ) - 1 ) ];
	}
	return $out;
}
function wp_date( $format, $timestamp = null, $timezone = null ) {
	return gmdate( $format, null === $timestamp ? time() : (int) $timestamp );
}
function current_time( $type, $gmt = 0 ) {
	return gmdate( 'Y-m-d H:i:s' );
}
function date_i18n( $format, $timestamp = false, $gmt = false ) {
	return wp_date( $format, $timestamp );
}
function human_time_diff( $from, $to = 0 ) {
	return '۵ دقیقه پیش';
}
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = strip_tags( (string) $text );
	if ( $remove_breaks ) {
		$text = str_replace( array( "\r", "\n" ), ' ', $text );
	}
	return trim( $text );
}
function strip_shortcodes( $text ) {
	return preg_replace( '/\[[^\]]+\]/', '', (string) $text );
}
function wp_kses_post( $text ) {
	return (string) $text;
}
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function sanitize_textarea_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key ) );
}
function absint( $value ) {
	return abs( (int) $value );
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $url, $protocols = null, $context = 'display' ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}
function esc_url_raw( $url, $protocols = null ) {
	return (string) $url;
}
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( $text );
}
function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html( $text );
}
function __( $text, $domain = 'default' ) {
	return $text;
}
function _e( $text, $domain = 'default' ) {
	echo $text;
}
function _x( $text, $context, $domain = 'default' ) {
	return $text;
}
function wp_list_pluck( $list, $field, $index_key = null ) {
	$out = array();
	foreach ( (array) $list as $item ) {
		$item = (array) $item;
		if ( isset( $item[ $field ] ) ) {
			$out[] = $item[ $field ];
		}
	}
	return $out;
}
function trailingslashit( $string ) {
	return rtrim( (string) $string, '/\\' ) . '/';
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals );
}
function sa_entity_label( $type, $form = 'singular' ) {
	$labels = array(
		'province'   => array( 'singular' => 'استان', 'plural' => 'استان‌ها' ),
		'city'       => array( 'singular' => 'شهرستان', 'plural' => 'شهرستان‌ها' ),
		'attraction' => array( 'singular' => 'نمای برتر', 'plural' => 'نماهای برتر' ),
	);
	return isset( $labels[ $type ][ $form ] ) ? $labels[ $type ][ $form ] : $type;
}
