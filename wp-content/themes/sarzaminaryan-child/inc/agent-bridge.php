<?php
/**
 * پل ایجنت (v2.22.72) — خواندن و نوشتن مستقیم ایجنت در وردپرس، با قواعد مالک.
 *
 * قواعد مالک (۱۴۰۵-۰۸):
 *   ۱) ایجنت سایت را مستقیم می‌خواند: همهٔ نوع‌های محتوا، حتی آن‌هایی که
 *      show_in_rest=false دارند (استان، شهرستان، دیدنی، …)، به‌اضافهٔ پیش‌نویس‌ها و متا.
 *   ۲) مقالهٔ تازه فقط «پیش‌نویس» ساخته می‌شود. این ماژول هیچ راهی برای انتشار ندارد.
 *   ۳) پیش از هر تغییر در محتوای موجود، ظاهر یا ساختار، یک پشتیبان کامل از وضع
 *      قبلی ذخیره می‌شود و بدون پشتیبانِ موفق هیچ نوشتنی انجام نمی‌شود.
 *   ۴) متن مقالهٔ منتشرشده مستقیم عوض نمی‌شود: یک «پیش‌نویس بازنویسی» کنارش
 *      ساخته می‌شود. فقط با پرچم صریح owner_approved_live_edit (به دستور مالک) متنِ
 *      منتشرشده بازنویسی می‌شود — آن هم بعد از پشتیبان.
 *   ۵) هیچ مسیر حذفی وجود ندارد. وضعیت، نامک و تاریخ انتشار را ایجنت عوض نمی‌کند.
 *
 * احراز هویت: Application Password وردپرس، فقط برای کاربرانی که edit_others_posts
 * دارند (ویرایشگر و مدیر). برای میزبان‌هایی که سرآیند Authorization را حذف می‌کنند،
 * سرآیند جایگزین X-SA-Authorization هم پذیرفته می‌شود.
 *
 * فضای نام: /wp-json/sa-agent/v1
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SA_AGENT_NS' ) ) {
	define( 'SA_AGENT_NS', 'sa-agent/v1' );
}
if ( ! defined( 'SA_AGENT_BACKUP_TYPE' ) ) {
	define( 'SA_AGENT_BACKUP_TYPE', 'sa_agent_backup' );
}

/* -------------------------------------------------------------------------
 * ۱) Application Password — فقط برای ویرایشگر و مدیر.
 * ---------------------------------------------------------------------- */

/**
 * Application Passwords در کل سایت در دسترس است…
 *
 * @return bool
 */
function sa_agent_app_passwords_available() {
	return true;
}
add_filter( 'wp_is_application_passwords_available', 'sa_agent_app_passwords_available', 20 );

/**
 * …ولی فقط برای کاربری که می‌تواند نوشته‌های دیگران را ویرایش کند.
 *
 * @param bool    $available وضعیت فعلی.
 * @param WP_User $user      کاربر.
 * @return bool
 */
function sa_agent_app_passwords_for_user( $available, $user ) {
	if ( ! $available || ! is_object( $user ) ) {
		return false;
	}
	return (bool) user_can( $user, 'edit_others_posts' );
}
add_filter( 'wp_is_application_passwords_available_for_user', 'sa_agent_app_passwords_for_user', 20, 2 );

/**
 * سرآیند Authorization را از جاهای جایگزین برمی‌دارد (CGI/LiteSpeed گاهی حذفش می‌کنند)
 * و PHP_AUTH_USER / PHP_AUTH_PW را پر می‌کند تا هستهٔ وردپرس Application Password را
 * بسنجد. فقط برای درخواست‌های REST و فقط اگر خودِ PHP آن‌ها را پر نکرده باشد.
 *
 * @param array|null $server آرایهٔ $_SERVER (برای تست).
 * @return array آرایهٔ به‌روزشده.
 */
function sa_agent_fill_basic_auth( $server = null ) {
	$use_global = ( null === $server );
	$s          = $use_global ? $_SERVER : $server; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! empty( $s['PHP_AUTH_USER'] ) ) {
		return $s;
	}
	$uri     = isset( $s['REQUEST_URI'] ) ? (string) $s['REQUEST_URI'] : '';
	$is_rest = ( false !== strpos( $uri, '/wp-json/' ) ) || ( false !== strpos( $uri, 'rest_route=' ) );
	if ( ! $is_rest ) {
		return $s;
	}
	$header = '';
	foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'HTTP_X_SA_AUTHORIZATION', 'REDIRECT_HTTP_X_SA_AUTHORIZATION' ) as $key ) {
		if ( ! empty( $s[ $key ] ) ) {
			$header = trim( (string) $s[ $key ] );
			break;
		}
	}
	if ( 0 !== stripos( $header, 'basic ' ) ) {
		return $s;
	}
	$decoded = base64_decode( trim( substr( $header, 6 ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	if ( false === $decoded || false === strpos( $decoded, ':' ) ) {
		return $s;
	}
	list( $user, $pass ) = explode( ':', $decoded, 2 );
	$s['PHP_AUTH_USER']  = $user;
	$s['PHP_AUTH_PW']    = $pass;
	if ( $use_global ) {
		$_SERVER['PHP_AUTH_USER'] = $user;
		$_SERVER['PHP_AUTH_PW']   = $pass;
	}
	return $s;
}
add_filter(
	'determine_current_user',
	function ( $user_id ) {
		sa_agent_fill_basic_auth();
		return $user_id;
	},
	5
);

/* -------------------------------------------------------------------------
 * ۲) نوع نوشتهٔ خصوصی پشتیبان‌ها (فقط مدیر می‌بیند؛ عمومی نیست و در نقشهٔ سایت نمی‌آید).
 * ---------------------------------------------------------------------- */

/**
 * ثبت نوع نوشتهٔ پشتیبان.
 */
function sa_agent_register_backup_type() {
	register_post_type(
		SA_AGENT_BACKUP_TYPE,
		array(
			'labels'              => array(
				'name'          => 'پشتیبان‌های ایجنت',
				'singular_name' => 'پشتیبان ایجنت',
				'menu_name'     => 'پشتیبان‌های ایجنت',
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => 'tools.php',
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
			'capabilities'        => array(
				'create_posts'       => 'do_not_allow',
				'edit_post'          => 'manage_options',
				'read_post'          => 'manage_options',
				'delete_post'        => 'manage_options',
				'edit_posts'         => 'manage_options',
				'edit_others_posts'  => 'manage_options',
				'publish_posts'      => 'manage_options',
				'read_private_posts' => 'manage_options',
				'delete_posts'       => 'manage_options',
			),
			'map_meta_cap'        => false,
		)
	);
}
add_action( 'init', 'sa_agent_register_backup_type' );

/* -------------------------------------------------------------------------
 * ۳) قواعد پایه.
 * ---------------------------------------------------------------------- */

/**
 * نوع‌های محتوایی که ایجنت می‌خواند و می‌نویسد.
 *
 * @return string[]
 */
function sa_agent_post_types() {
	$types = array( 'post', 'page' );
	if ( function_exists( 'sa_entity_types' ) ) {
		$types = array_merge( $types, sa_entity_types() );
	}
	return array_values( array_unique( $types ) );
}

/**
 * کلید متای مجاز برای نوشتن: محافظت‌شده (با «_») نیست، جز تصویر شاخص.
 *
 * @param string $key کلید.
 * @return bool
 */
function sa_agent_meta_key_writable( $key ) {
	$key = (string) $key;
	if ( '' === $key || strlen( $key ) > 191 ) {
		return false;
	}
	if ( '_thumbnail_id' === $key ) {
		return true;
	}
	return '_' !== $key[0];
}

/**
 * واژه‌های ممنوع مالک در متن (AGENTS.md بندهای ۸ و ۱۱).
 *
 * @return string[]
 */
function sa_agent_banned_phrases() {
	return array( 'راستش', 'هوش مصنوعی', 'بازبینی انسانی', 'ChatGPT', 'Claude', 'Gemini' );
}

/**
 * اولین واژهٔ ممنوعی که در متن‌ها پیدا شود؛ رشتهٔ خالی یعنی متن تمیز است.
 *
 * @param array $texts متن‌ها.
 * @return string
 */
function sa_agent_find_banned( array $texts ) {
	foreach ( $texts as $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			continue;
		}
		foreach ( sa_agent_banned_phrases() as $phrase ) {
			if ( false !== mb_stripos( $text, $phrase ) ) {
				return $phrase;
			}
		}
	}
	return '';
}

/**
 * خطای استاندارد.
 *
 * @param string $code    کد.
 * @param string $message پیام.
 * @param int    $status  وضعیت HTTP.
 * @return WP_Error
 */
function sa_agent_error( $code, $message, $status ) {
	return new WP_Error( $code, $message, array( 'status' => (int) $status ) );
}

/* -------------------------------------------------------------------------
 * ۴) عکس کامل یک نوشته (همان قالبی که پشتیبان هم ذخیره می‌کند).
 * ---------------------------------------------------------------------- */

/**
 * عکس کامل نوشته: فیلدها، همهٔ متاهای غیرمحافظت‌شده، تصویر شاخص و برچسب‌ها.
 *
 * @param int $post_id شناسه.
 * @return array|null
 */
function sa_agent_snapshot_post( $post_id ) {
	$post = get_post( (int) $post_id );
	if ( ! $post || ! in_array( $post->post_type, sa_agent_post_types(), true ) ) {
		return null;
	}
	$meta = array();
	foreach ( (array) get_post_meta( $post->ID ) as $key => $values ) {
		if ( ! sa_agent_meta_key_writable( $key ) && '_sa_agent_draft_of' !== $key ) {
			continue;
		}
		$values       = array_map( 'maybe_unserialize', (array) $values );
		$meta[ $key ] = ( 1 === count( $values ) ) ? $values[0] : $values;
	}
	$terms = array();
	foreach ( (array) get_object_taxonomies( $post->post_type ) as $tax ) {
		$list = wp_get_object_terms( $post->ID, $tax, array( 'fields' => 'slugs' ) );
		if ( is_array( $list ) && $list ) {
			$terms[ $tax ] = array_values( $list );
		}
	}
	return array(
		'id'             => (int) $post->ID,
		'type'           => $post->post_type,
		'status'         => $post->post_status,
		'slug'           => $post->post_name,
		'title'          => $post->post_title,
		'content'        => $post->post_content,
		'excerpt'        => $post->post_excerpt,
		'parent'         => (int) $post->post_parent,
		'menu_order'     => (int) $post->menu_order,
		'author'         => (int) $post->post_author,
		'date_gmt'       => $post->post_date_gmt,
		'modified_gmt'   => $post->post_modified_gmt,
		'featured_media' => (int) get_post_thumbnail_id( $post->ID ),
		'link'           => get_permalink( $post->ID ),
		'meta'           => $meta,
		'terms'          => $terms,
	);
}

/* -------------------------------------------------------------------------
 * ۵) پشتیبان.
 * ---------------------------------------------------------------------- */

/**
 * یک پشتیبان می‌سازد. JSON در متای _sa_backup_json می‌نشیند (متا از فیلتر kses نمی‌گذرد).
 *
 * @param string $kind      post | theme_mods.
 * @param int    $target_id شناسهٔ نوشتهٔ هدف (برای theme_mods صفر).
 * @param array  $data      داده.
 * @param string $reason    دلیل (برای فهرست پیشخوان).
 * @return int|WP_Error شناسهٔ پشتیبان.
 */
function sa_agent_create_backup( $kind, $target_id, array $data, $reason ) {
	$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $json ) || '' === $json ) {
		return sa_agent_error( 'sa_agent_backup_encode', 'پشتیبان ساخته نشد (رمزگذاری JSON).', 500 );
	}
	$label = ( 'post' === $kind ) ? sprintf( '%s #%d — %s', isset( $data['type'] ) ? $data['type'] : 'post', (int) $target_id, isset( $data['title'] ) ? $data['title'] : '' ) : 'theme_mods';
	$id    = wp_insert_post(
		array(
			'post_type'   => SA_AGENT_BACKUP_TYPE,
			'post_status' => 'private',
			'post_title'  => sprintf( 'پشتیبان پیش از «%s»: %s — %s', $reason, $label, gmdate( 'Y-m-d H:i:s' ) ),
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		return sa_agent_error( 'sa_agent_backup_failed', 'پشتیبان ساخته نشد؛ هیچ تغییری اعمال نشد.', 500 );
	}
	update_post_meta( $id, '_sa_backup_kind', $kind );
	update_post_meta( $id, '_sa_backup_target', (int) $target_id );
	update_post_meta( $id, '_sa_backup_reason', $reason );
	update_post_meta( $id, '_sa_backup_json', wp_slash( $json ) );
	$stored = get_post_meta( $id, '_sa_backup_json', true );
	if ( ! is_string( $stored ) || '' === $stored ) {
		return sa_agent_error( 'sa_agent_backup_verify', 'پشتیبان ذخیره نشد؛ هیچ تغییری اعمال نشد.', 500 );
	}
	return (int) $id;
}

/**
 * دادهٔ یک پشتیبان.
 *
 * @param int $backup_id شناسه.
 * @return array|null
 */
function sa_agent_read_backup( $backup_id ) {
	$post = get_post( (int) $backup_id );
	if ( ! $post || SA_AGENT_BACKUP_TYPE !== $post->post_type ) {
		return null;
	}
	$data = json_decode( (string) get_post_meta( $post->ID, '_sa_backup_json', true ), true );
	return array(
		'id'      => (int) $post->ID,
		'title'   => $post->post_title,
		'date'    => $post->post_date_gmt,
		'kind'    => (string) get_post_meta( $post->ID, '_sa_backup_kind', true ),
		'target'  => (int) get_post_meta( $post->ID, '_sa_backup_target', true ),
		'reason'  => (string) get_post_meta( $post->ID, '_sa_backup_reason', true ),
		'data'    => is_array( $data ) ? $data : null,
	);
}

/* -------------------------------------------------------------------------
 * ۶) نوشتن.
 * ---------------------------------------------------------------------- */

/**
 * برچسب‌ها را فقط با نامکِ از پیش موجود می‌پذیرد (ایجنت برچسب تازه نمی‌سازد).
 *
 * @param string $type  نوع نوشته.
 * @param array  $terms tax => [slug].
 * @return array|WP_Error tax => [term_id].
 */
function sa_agent_resolve_terms( $type, $terms ) {
	$out     = array();
	$allowed = (array) get_object_taxonomies( $type );
	foreach ( (array) $terms as $tax => $slugs ) {
		if ( ! in_array( $tax, $allowed, true ) ) {
			return sa_agent_error( 'sa_agent_bad_taxonomy', 'طبقه‌بندی «' . $tax . '» برای این نوع نوشته مجاز نیست.', 400 );
		}
		$ids = array();
		foreach ( (array) $slugs as $slug ) {
			$term = get_term_by( 'slug', sanitize_title( (string) $slug ), $tax );
			if ( ! $term ) {
				return sa_agent_error( 'sa_agent_unknown_term', 'برچسب «' . $slug . '» در «' . $tax . '» وجود ندارد؛ ایجنت برچسب تازه نمی‌سازد.', 400 );
			}
			$ids[] = (int) $term->term_id;
		}
		$out[ $tax ] = $ids;
	}
	return $out;
}

/**
 * متاها را بررسی می‌کند.
 *
 * @param mixed $meta key => value.
 * @return array|WP_Error
 */
function sa_agent_clean_meta( $meta ) {
	$out = array();
	foreach ( (array) $meta as $key => $value ) {
		if ( ! sa_agent_meta_key_writable( $key ) ) {
			return sa_agent_error( 'sa_agent_protected_meta', 'کلید متای «' . $key . '» محافظت‌شده است.', 400 );
		}
		if ( is_object( $value ) ) {
			$value = json_decode( wp_json_encode( $value ), true );
		}
		$out[ (string) $key ] = $value;
	}
	return $out;
}

/**
 * پیش‌نویس تازه می‌سازد. وضعیت همیشه draft است، هر چه فرستاده شود.
 *
 * @param array $args type, title, content, excerpt, slug, parent, meta, terms, draft_of.
 * @return array|WP_Error
 */
function sa_agent_create_draft( array $args ) {
	$type = isset( $args['type'] ) ? (string) $args['type'] : 'post';
	if ( ! in_array( $type, sa_agent_post_types(), true ) ) {
		return sa_agent_error( 'sa_agent_bad_type', 'نوع نوشتهٔ «' . $type . '» مجاز نیست.', 400 );
	}
	$title   = isset( $args['title'] ) ? (string) $args['title'] : '';
	$content = isset( $args['content'] ) ? (string) $args['content'] : '';
	$excerpt = isset( $args['excerpt'] ) ? (string) $args['excerpt'] : '';
	if ( '' === trim( $title ) ) {
		return sa_agent_error( 'sa_agent_no_title', 'عنوان لازم است.', 400 );
	}
	$banned = sa_agent_find_banned( array( $title, $content, $excerpt ) );
	if ( '' !== $banned ) {
		return sa_agent_error( 'sa_agent_banned_phrase', 'متن واژهٔ ممنوع «' . $banned . '» دارد.', 400 );
	}
	$meta = sa_agent_clean_meta( isset( $args['meta'] ) ? $args['meta'] : array() );
	if ( is_wp_error( $meta ) ) {
		return $meta;
	}
	$terms = sa_agent_resolve_terms( $type, isset( $args['terms'] ) ? $args['terms'] : array() );
	if ( is_wp_error( $terms ) ) {
		return $terms;
	}
	$postarr = array(
		'post_type'    => $type,
		'post_status'  => 'draft',
		'post_title'   => $title,
		'post_content' => $content,
		'post_excerpt' => $excerpt,
	);
	if ( ! empty( $args['slug'] ) ) {
		$postarr['post_name'] = sanitize_title( (string) $args['slug'] );
	}
	if ( ! empty( $args['parent'] ) ) {
		$postarr['post_parent'] = (int) $args['parent'];
	}
	$id = wp_insert_post( wp_slash( $postarr ), true );
	if ( is_wp_error( $id ) || ! $id ) {
		return sa_agent_error( 'sa_agent_insert_failed', 'پیش‌نویس ساخته نشد.', 500 );
	}
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, wp_slash( $value ) );
	}
	foreach ( $terms as $tax => $ids ) {
		wp_set_object_terms( $id, $ids, $tax );
	}
	update_post_meta( $id, '_sa_agent_created', gmdate( 'c' ) );
	if ( ! empty( $args['draft_of'] ) ) {
		update_post_meta( $id, '_sa_agent_draft_of', (int) $args['draft_of'] );
	}
	return array(
		'id'        => (int) $id,
		'status'    => 'draft',
		'edit_link' => admin_url( 'post.php?post=' . (int) $id . '&action=edit' ),
		'preview'   => add_query_arg( array( 'p' => (int) $id, 'preview' => 'true' ), home_url( '/' ) ),
	);
}

/**
 * نوشتهٔ موجود را به‌روز می‌کند: اول پشتیبان، بعد تغییر.
 *
 * @param int   $post_id شناسه.
 * @param array $args    expected_modified_gmt, fields{title,content,excerpt}, meta{}, terms{},
 *                       owner_approved_live_edit, reason.
 * @return array|WP_Error
 */
function sa_agent_update_post( $post_id, array $args ) {
	$before = sa_agent_snapshot_post( $post_id );
	if ( null === $before ) {
		return sa_agent_error( 'sa_agent_not_found', 'نوشته پیدا نشد یا نوعش مجاز نیست.', 404 );
	}
	$expected = isset( $args['expected_modified_gmt'] ) ? (string) $args['expected_modified_gmt'] : '';
	if ( '' === $expected ) {
		return sa_agent_error( 'sa_agent_need_expected', 'expected_modified_gmt لازم است (اول نوشته را بخوان).', 400 );
	}
	if ( $expected !== $before['modified_gmt'] ) {
		return sa_agent_error( 'sa_agent_stale', 'نوشته از زمان خواندن عوض شده؛ دوباره بخوان و بعد بنویس.', 409 );
	}
	$fields = array();
	foreach ( array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt' ) as $in => $col ) {
		if ( isset( $args['fields'][ $in ] ) ) {
			$fields[ $col ] = (string) $args['fields'][ $in ];
		}
	}
	$banned = empty( $args['__restore'] ) ? sa_agent_find_banned( array_values( $fields ) ) : '';
	if ( '' !== $banned ) {
		return sa_agent_error( 'sa_agent_banned_phrase', 'متن واژهٔ ممنوع «' . $banned . '» دارد.', 400 );
	}
	$live = in_array( $before['status'], array( 'publish', 'future', 'private' ), true );
	if ( $fields && $live && empty( $args['owner_approved_live_edit'] ) ) {
		return sa_agent_error( 'sa_agent_live_content', 'متن نوشتهٔ منتشرشده مستقیم عوض نمی‌شود؛ از draft-copy استفاده کن یا دستور صریح مالک (owner_approved_live_edit) را بگیر.', 409 );
	}
	$meta = sa_agent_clean_meta( isset( $args['meta'] ) ? $args['meta'] : array() );
	if ( is_wp_error( $meta ) ) {
		return $meta;
	}
	$terms = sa_agent_resolve_terms( $before['type'], isset( $args['terms'] ) ? $args['terms'] : array() );
	if ( is_wp_error( $terms ) ) {
		return $terms;
	}
	if ( ! $fields && ! $meta && ! $terms ) {
		return sa_agent_error( 'sa_agent_nothing', 'تغییری فرستاده نشده است.', 400 );
	}
	$reason    = isset( $args['reason'] ) ? sanitize_text_field( (string) $args['reason'] ) : 'به‌روزرسانی';
	$backup_id = sa_agent_create_backup( 'post', $before['id'], $before, $reason );
	if ( is_wp_error( $backup_id ) ) {
		return $backup_id;
	}
	if ( $fields ) {
		$fields['ID'] = $before['id'];
		$res          = wp_update_post( wp_slash( $fields ), true );
		if ( is_wp_error( $res ) || ! $res ) {
			return sa_agent_error( 'sa_agent_update_failed', 'به‌روزرسانی انجام نشد؛ پشتیبان #' . $backup_id . ' سالم است.', 500 );
		}
	}
	foreach ( $meta as $key => $value ) {
		if ( null === $value ) {
			delete_post_meta( $before['id'], $key );
		} else {
			update_post_meta( $before['id'], $key, wp_slash( $value ) );
		}
	}
	foreach ( $terms as $tax => $ids ) {
		wp_set_object_terms( $before['id'], $ids, $tax );
	}
	$after = sa_agent_snapshot_post( $before['id'] );
	return array(
		'id'           => $before['id'],
		'backup_id'    => (int) $backup_id,
		'status'       => $after ? $after['status'] : $before['status'],
		'modified_gmt' => $after ? $after['modified_gmt'] : '',
		'changed'      => array(
			'fields' => array_keys( array_diff_key( $fields, array( 'ID' => 0 ) ) ),
			'meta'   => array_keys( $meta ),
			'terms'  => array_keys( $terms ),
		),
	);
}

/**
 * پیش‌نویس بازنویسی برای نوشتهٔ منتشرشده (اصل دست‌نخورده می‌ماند).
 *
 * @param int   $post_id شناسهٔ اصل.
 * @param array $args    fields{title,content,excerpt}, meta{}.
 * @return array|WP_Error
 */
function sa_agent_draft_copy( $post_id, array $args ) {
	$src = sa_agent_snapshot_post( $post_id );
	if ( null === $src ) {
		return sa_agent_error( 'sa_agent_not_found', 'نوشته پیدا نشد یا نوعش مجاز نیست.', 404 );
	}
	$f    = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array();
	$meta = array();
	foreach ( $src['meta'] as $key => $value ) {
		if ( sa_agent_meta_key_writable( $key ) && '_thumbnail_id' !== $key ) {
			$meta[ $key ] = $value;
		}
	}
	if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
		$meta = array_merge( $meta, $args['meta'] );
	}
	return sa_agent_create_draft(
		array(
			'type'     => $src['type'],
			'title'    => isset( $f['title'] ) ? $f['title'] : $src['title'],
			'content'  => isset( $f['content'] ) ? $f['content'] : $src['content'],
			'excerpt'  => isset( $f['excerpt'] ) ? $f['excerpt'] : $src['excerpt'],
			'parent'   => $src['parent'],
			'meta'     => $meta,
			'terms'    => $src['terms'],
			'draft_of' => $src['id'],
		)
	);
}

/**
 * بازگردانی از پشتیبان (پیش از آن از وضع فعلی هم پشتیبان گرفته می‌شود).
 *
 * @param int $backup_id شناسهٔ پشتیبان.
 * @return array|WP_Error
 */
function sa_agent_restore( $backup_id ) {
	$b = sa_agent_read_backup( $backup_id );
	if ( null === $b || ! is_array( $b['data'] ) ) {
		return sa_agent_error( 'sa_agent_no_backup', 'پشتیبان پیدا نشد.', 404 );
	}
	if ( 'theme_mods' === $b['kind'] ) {
		$current = sa_agent_theme_mods_snapshot();
		$pre     = sa_agent_create_backup( 'theme_mods', 0, $current, 'بازگردانی پشتیبان #' . $b['id'] );
		if ( is_wp_error( $pre ) ) {
			return $pre;
		}
		foreach ( (array) $b['data']['mods'] as $key => $value ) {
			set_theme_mod( $key, $value );
		}
		return array( 'restored' => $b['id'], 'kind' => 'theme_mods', 'backup_id' => (int) $pre );
	}
	$now = sa_agent_snapshot_post( $b['target'] );
	if ( null === $now ) {
		return sa_agent_error( 'sa_agent_not_found', 'نوشتهٔ هدف دیگر وجود ندارد.', 404 );
	}
	$meta = array();
	foreach ( (array) $b['data']['meta'] as $key => $value ) {
		if ( sa_agent_meta_key_writable( $key ) ) {
			$meta[ $key ] = $value;
		}
	}
	foreach ( array_keys( $now['meta'] ) as $key ) {
		if ( sa_agent_meta_key_writable( $key ) && ! array_key_exists( $key, $meta ) ) {
			$meta[ $key ] = null; // کلیدی که بعد از پشتیبان اضافه شده بود برداشته می‌شود.
		}
	}
	return sa_agent_update_post(
		$now['id'],
		array(
			'expected_modified_gmt'    => $now['modified_gmt'],
			'fields'                   => array(
				'title'   => $b['data']['title'],
				'content' => $b['data']['content'],
				'excerpt' => $b['data']['excerpt'],
			),
			'meta'                     => $meta,
			'owner_approved_live_edit' => true, // بازگردانی یعنی برگشت به نسخه‌ای که خودِ سایت داشته.
			'reason'                   => 'بازگردانی پشتیبان #' . $b['id'],
			'__restore'                => true, // فقط داخلی؛ از REST حذف می‌شود.
		)
	);
}

/* -------------------------------------------------------------------------
 * ۷) ظاهر: تنظیمات سفارشی‌ساز قالب (theme_mods).
 * ---------------------------------------------------------------------- */

/**
 * عکس تنظیمات سفارشی‌ساز.
 *
 * @return array
 */
function sa_agent_theme_mods_snapshot() {
	$mods = get_theme_mods();
	$mods = is_array( $mods ) ? $mods : array();
	ksort( $mods );
	return array(
		'stylesheet' => get_stylesheet(),
		'version'    => defined( 'SA_CHILD_VERSION' ) ? SA_CHILD_VERSION : '',
		'hash'       => md5( (string) wp_json_encode( $mods ) ),
		'mods'       => $mods,
	);
}

/**
 * تغییر تنظیمات سفارشی‌ساز: فقط کلیدهای sa_* یا کلیدهای از پیش موجود، فقط مقدار ساده؛
 * اول پشتیبان کامل.
 *
 * @param array $args expected_hash, set{key:value}, reason.
 * @return array|WP_Error
 */
function sa_agent_set_theme_mods( array $args ) {
	$now = sa_agent_theme_mods_snapshot();
	if ( empty( $args['expected_hash'] ) || $args['expected_hash'] !== $now['hash'] ) {
		return sa_agent_error( 'sa_agent_stale', 'تنظیمات از زمان خواندن عوض شده یا expected_hash نیامده؛ دوباره بخوان.', 409 );
	}
	$set = isset( $args['set'] ) && is_array( $args['set'] ) ? $args['set'] : array();
	if ( ! $set ) {
		return sa_agent_error( 'sa_agent_nothing', 'تغییری فرستاده نشده است.', 400 );
	}
	foreach ( $set as $key => $value ) {
		$key = (string) $key;
		if ( 0 !== strpos( $key, 'sa_' ) && ! array_key_exists( $key, $now['mods'] ) ) {
			return sa_agent_error( 'sa_agent_bad_mod', 'کلید «' . $key . '» مجاز نیست (فقط sa_* یا کلید موجود).', 400 );
		}
		if ( ! is_scalar( $value ) && null !== $value ) {
			return sa_agent_error( 'sa_agent_bad_mod_value', 'مقدار «' . $key . '» باید ساده باشد.', 400 );
		}
	}
	$reason    = isset( $args['reason'] ) ? sanitize_text_field( (string) $args['reason'] ) : 'تغییر ظاهر';
	$backup_id = sa_agent_create_backup( 'theme_mods', 0, $now, $reason );
	if ( is_wp_error( $backup_id ) ) {
		return $backup_id;
	}
	foreach ( $set as $key => $value ) {
		set_theme_mod( (string) $key, $value );
	}
	return array( 'backup_id' => (int) $backup_id, 'changed' => array_keys( $set ), 'hash' => sa_agent_theme_mods_snapshot()['hash'] );
}

/* -------------------------------------------------------------------------
 * ۸) مسیرهای REST. هیچ مسیر DELETE وجود ندارد.
 * ---------------------------------------------------------------------- */

/**
 * دسترسی خواندن.
 *
 * @return bool
 */
function sa_agent_can_read() {
	return is_user_logged_in() && current_user_can( 'edit_posts' );
}

/**
 * دسترسی نوشتن.
 *
 * @return bool
 */
function sa_agent_can_write() {
	return is_user_logged_in() && current_user_can( 'edit_others_posts' );
}

/**
 * دسترسی ظاهر.
 *
 * @return bool
 */
function sa_agent_can_theme() {
	return is_user_logged_in() && current_user_can( 'edit_theme_options' );
}

/**
 * پاسخ REST.
 *
 * @param mixed $data داده یا WP_Error.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_respond( $data ) {
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	return rest_ensure_response( $data );
}

/**
 * GET /ping
 *
 * @return WP_REST_Response
 */
function sa_agent_rest_ping() {
	$user = wp_get_current_user();
	return rest_ensure_response(
		array(
			'ok'         => true,
			'theme'      => defined( 'SA_CHILD_VERSION' ) ? SA_CHILD_VERSION : '',
			'user'       => $user ? $user->user_login : '',
			'can_write'  => sa_agent_can_write(),
			'can_theme'  => sa_agent_can_theme(),
			'post_types' => sa_agent_post_types(),
			'time_gmt'   => gmdate( 'c' ),
		)
	);
}

/**
 * GET /posts
 *
 * @param WP_REST_Request $r درخواست.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_list( $r ) {
	$type = (string) $r->get_param( 'type' );
	$type = '' === $type ? 'post' : $type;
	if ( ! in_array( $type, sa_agent_post_types(), true ) ) {
		return sa_agent_error( 'sa_agent_bad_type', 'نوع نوشته مجاز نیست.', 400 );
	}
	$status = (string) $r->get_param( 'status' );
	$args   = array(
		'post_type'      => $type,
		'post_status'    => '' === $status ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : array_map( 'sanitize_key', explode( ',', $status ) ),
		'posts_per_page' => max( 1, min( 100, (int) ( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 50 ) ) ),
		'paged'          => max( 1, (int) $r->get_param( 'page' ) ),
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'no_found_rows'  => false,
	);
	if ( $r->get_param( 'search' ) ) {
		$args['s'] = sanitize_text_field( (string) $r->get_param( 'search' ) );
	}
	if ( $r->get_param( 'slug' ) ) {
		$args['name'] = sanitize_title( (string) $r->get_param( 'slug' ) );
	}
	if ( $r->get_param( 'modified_after' ) ) {
		$args['date_query'] = array(
			array(
				'column' => 'post_modified_gmt',
				'after'  => sanitize_text_field( (string) $r->get_param( 'modified_after' ) ),
			),
		);
	}
	$q     = new WP_Query( $args );
	$items = array();
	foreach ( $q->posts as $p ) {
		$items[] = array(
			'id'           => (int) $p->ID,
			'type'         => $p->post_type,
			'status'       => $p->post_status,
			'slug'         => $p->post_name,
			'title'        => $p->post_title,
			'modified_gmt' => $p->post_modified_gmt,
			'link'         => get_permalink( $p->ID ),
			'words'        => count( preg_split( '/\s+/u', trim( wp_strip_all_tags( $p->post_content ) ), -1, PREG_SPLIT_NO_EMPTY ) ),
		);
	}
	return rest_ensure_response(
		array(
			'total' => (int) $q->found_posts,
			'pages' => (int) $q->max_num_pages,
			'items' => $items,
		)
	);
}

/**
 * ثبت مسیرها.
 */
function sa_agent_register_routes() {
	$id_arg = array(
		'id' => array(
			'validate_callback' => function ( $v ) {
				return is_numeric( $v ) && (int) $v > 0;
			},
		),
	);
	register_rest_route( SA_AGENT_NS, '/ping', array( 'methods' => 'GET', 'callback' => 'sa_agent_rest_ping', 'permission_callback' => 'sa_agent_can_read' ) );
	register_rest_route( SA_AGENT_NS, '/posts', array( 'methods' => 'GET', 'callback' => 'sa_agent_rest_list', 'permission_callback' => 'sa_agent_can_read' ) );
	register_rest_route(
		SA_AGENT_NS,
		'/post/(?P<id>\d+)',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => function ( $r ) {
					$snap = sa_agent_snapshot_post( (int) $r['id'] );
					return null === $snap ? sa_agent_error( 'sa_agent_not_found', 'نوشته پیدا نشد یا نوعش مجاز نیست.', 404 ) : rest_ensure_response( $snap );
				},
				'permission_callback' => 'sa_agent_can_read',
				'args'                => $id_arg,
			),
			array(
				'methods'             => 'POST',
				'callback'            => function ( $r ) {
					$body = (array) $r->get_json_params();
					unset( $body['__restore'] );
					return sa_agent_respond( sa_agent_update_post( (int) $r['id'], $body ) );
				},
				'permission_callback' => 'sa_agent_can_write',
				'args'                => $id_arg,
			),
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/post/(?P<id>\d+)/draft-copy',
		array(
			'methods'             => 'POST',
			'callback'            => function ( $r ) {
				return sa_agent_respond( sa_agent_draft_copy( (int) $r['id'], (array) $r->get_json_params() ) );
			},
			'permission_callback' => 'sa_agent_can_write',
			'args'                => $id_arg,
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/draft',
		array(
			'methods'             => 'POST',
			'callback'            => function ( $r ) {
				return sa_agent_respond( sa_agent_create_draft( (array) $r->get_json_params() ) );
			},
			'permission_callback' => 'sa_agent_can_write',
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/backups',
		array(
			'methods'             => 'GET',
			'callback'            => function ( $r ) {
				$args = array(
					'post_type'      => SA_AGENT_BACKUP_TYPE,
					'post_status'    => 'private',
					'posts_per_page' => max( 1, min( 100, (int) ( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 30 ) ) ),
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
				);
				if ( $r->get_param( 'target' ) ) {
					$args['meta_query'] = array( array( 'key' => '_sa_backup_target', 'value' => (int) $r->get_param( 'target' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				}
				$q   = new WP_Query( $args );
				$out = array();
				foreach ( $q->posts as $bid ) {
					$b = sa_agent_read_backup( $bid );
					if ( $b ) {
						unset( $b['data'] );
						$out[] = $b;
					}
				}
				return rest_ensure_response( $out );
			},
			'permission_callback' => 'sa_agent_can_write',
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/backup/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => function ( $r ) {
				$b = sa_agent_read_backup( (int) $r['id'] );
				return null === $b ? sa_agent_error( 'sa_agent_no_backup', 'پشتیبان پیدا نشد.', 404 ) : rest_ensure_response( $b );
			},
			'permission_callback' => 'sa_agent_can_write',
			'args'                => $id_arg,
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/restore/(?P<id>\d+)',
		array(
			'methods'             => 'POST',
			'callback'            => function ( $r ) {
				return sa_agent_respond( sa_agent_restore( (int) $r['id'] ) );
			},
			'permission_callback' => 'sa_agent_can_write',
			'args'                => $id_arg,
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/theme-mods',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					return rest_ensure_response( sa_agent_theme_mods_snapshot() );
				},
				'permission_callback' => 'sa_agent_can_theme',
			),
			array(
				'methods'             => 'POST',
				'callback'            => function ( $r ) {
					return sa_agent_respond( sa_agent_set_theme_mods( (array) $r->get_json_params() ) );
				},
				'permission_callback' => 'sa_agent_can_theme',
			),
		)
	);
}
add_action( 'rest_api_init', 'sa_agent_register_routes' );
