<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_REST {
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function has_permission() {
		return is_user_logged_in() && wp_verify_nonce( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? $_SERVER['HTTP_X_WP_NONCE'] : '', 'wp_rest' );
	}

	public static function has_submission_permission() {
		$nonce_ok = wp_verify_nonce( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '', 'wp_rest' );
		if ( ! $nonce_ok ) {
			return false;
		}
		if ( class_exists( 'CC_Admin' ) && CC_Admin::upload_requires_login() ) {
			return is_user_logged_in();
		}
		return true;
	}

	public static function voter_key( $request ) {
		if ( is_user_logged_in() ) {
			return 'u' . get_current_user_id();
		}
		$tok = preg_replace( '/[^a-f0-9]/i', '', (string) $request->get_param( 'voter' ) );
		if ( strlen( $tok ) < 16 ) {
			$tok = hash( 'sha256', ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' ) . ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' ) . wp_salt( 'auth' ) );
		}
		return 'c' . substr( hash( 'sha256', strtolower( $tok ) . wp_salt( 'nonce' ) ), 0, 40 );
	}

	public static function routes() {
		register_rest_route( 'cc/v1', '/ping', array( 'methods' => 'GET', 'callback' => function () { global $wpdb; $t = CC_DB::table(); return array( 'ok' => true, 'version' => CC_VERSION, 'tables' => ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ), 'php' => PHP_VERSION ); }, 'permission_callback' => '__return_true' ) );
		register_rest_route( 'cc/v1', '/cities/(?P<id>\d+)/rating', array( 'methods' => 'GET', 'callback' => function ( $r ) { return self::get_rating( $r ); }, 'permission_callback' => '__return_true' ) );
		register_rest_route( 'cc/v1', '/cities/(?P<id>\d+)/rating', array( 'methods' => 'POST', 'callback' => function ( $r ) { return self::post_rating( $r ); }, 'permission_callback' => '__return_true' ) );
		register_rest_route( 'cc/v1', '/submissions', array( 'methods' => 'POST', 'callback' => function ( $r ) { return self::submission( $r ); }, 'permission_callback' => function () { return self::has_submission_permission(); } ) );
		register_rest_route( 'cc/v1', '/submissions/(?P<id>\d+)/resubmit', array( 'methods' => 'POST', 'callback' => function ( $r ) { return self::resubmit( $r ); }, 'permission_callback' => function () { return self::has_permission(); } ) );
		register_rest_route( 'cc/v1', '/leaderboard', array( 'methods' => 'GET', 'callback' => function ( $r ) { return CC_Gamification::leaderboard( absint( $r->get_param( 'city_id' ) ) ); }, 'permission_callback' => '__return_true' ) );
		register_rest_route( 'cc/v1', '/my-submissions', array( 'methods' => 'GET', 'callback' => function ( $r ) { return self::my_submissions( $r ); }, 'permission_callback' => function () { return self::has_permission(); } ) );
	}


	public static function fa_num( $value ) {
		return function_exists( 'sa_fa_digits' ) ? sa_fa_digits( (string) $value ) : number_format_i18n( $value );
	}

	public static function upload_max_files() {
		return class_exists( 'CC_Admin' ) ? CC_Admin::upload_max_files() : 3;
	}

	public static function upload_max_bytes() {
		return class_exists( 'CC_Admin' ) ? CC_Admin::upload_max_bytes() : 2 * MB_IN_BYTES;
	}

	public static function upload_max_mb() {
		return class_exists( 'CC_Admin' ) ? CC_Admin::upload_max_mb() : 2;
	}

	public static function upload_daily_limit() {
		return class_exists( 'CC_Admin' ) ? CC_Admin::upload_daily_limit() : 3;
	}

	public static function normalise_uploaded_images() {
		if ( empty( $_FILES['image'] ) ) {
			return array();
		}
		$raw = $_FILES['image'];
		if ( isset( $raw['name'] ) && is_array( $raw['name'] ) ) {
			$out = array();
			foreach ( $raw['name'] as $i => $name ) {
				$out[] = array(
					'name'     => $name,
					'type'     => isset( $raw['type'][ $i ] ) ? $raw['type'][ $i ] : '',
					'tmp_name' => isset( $raw['tmp_name'][ $i ] ) ? $raw['tmp_name'][ $i ] : '',
					'error'    => isset( $raw['error'][ $i ] ) ? (int) $raw['error'][ $i ] : UPLOAD_ERR_NO_FILE,
					'size'     => isset( $raw['size'][ $i ] ) ? (int) $raw['size'][ $i ] : 0,
				);
			}
			return array_values( array_filter( $out, function ( $file ) { return UPLOAD_ERR_NO_FILE !== (int) $file['error']; } ) );
		}
		return UPLOAD_ERR_NO_FILE === (int) ( $raw['error'] ?? UPLOAD_ERR_NO_FILE ) ? array() : array( $raw );
	}

	public static function get_rating( $r ) {
		try {
			$id = absint( $r['id'] );
			if ( 'city' !== get_post_type( $id ) ) {
				return new WP_Error( 'invalid_city', 'شهر معتبر نیست', array( 'status' => 404 ) );
			}
			return CC_Rating::get( $id, self::voter_key( $r ) );
		} catch ( Throwable $e ) {
			return new WP_Error( 'cc_error', 'خطای داخلی سامانهٔ امتیازدهی', array( 'status' => 500 ) );
		}
	}

	public static function post_rating( $r ) {
		try {
			$id = absint( $r['id'] );
			if ( 'city' !== get_post_type( $id ) ) {
				return new WP_Error( 'invalid_city', 'شهر معتبر نیست', array( 'status' => 404 ) );
			}
			$stars = filter_var( $r->get_param( 'stars' ), FILTER_VALIDATE_INT );
			if ( false === $stars || $stars < 1 || $stars > 7 ) {
				return new WP_Error( 'invalid_stars', 'امتیاز باید بین ۱ تا ۷ باشد', array( 'status' => 400 ) );
			}
			$key = self::voter_key( $r );
			if ( ! is_user_logged_in() ) {
				$ipk = 'cc_rate_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' );
				$n   = (int) get_transient( $ipk );
				if ( $n >= 40 ) {
					return new WP_Error( 'rate_limited', 'تعداد درخواست‌های شما بیش از حد مجاز است؛ فردا دوباره تلاش کنید', array( 'status' => 429 ) );
				}
				set_transient( $ipk, $n + 1, DAY_IN_SECONDS );
			}
			global $wpdb;
			$t       = CC_DB::table();
			$now     = current_time( 'mysql' );
			$row     = $wpdb->get_row( $wpdb->prepare( "SELECT id,stars,updated_at FROM $t WHERE voter_key=%s AND city_id=%d", $key, $id ) );
			$changed = false;
			if ( $row ) {
				$ts = strtotime( $row->updated_at );
				if ( time() - $ts < DAY_IN_SECONDS ) {
					return new WP_Error( 'vote_locked', 'رأی شما پیش‌تر ثبت شده است؛ تنها پس از گذشت یک روز می‌توانید آن را تغییر دهید.', array( 'status' => 409, 'changeable_at' => $ts + DAY_IN_SECONDS ) );
				}
				$wpdb->update( $t, array( 'stars' => $stars, 'updated_at' => $now ), array( 'id' => $row->id ), array( '%d', '%s' ), array( '%d' ) );
				$changed = true;
			} else {
				$uid = is_user_logged_in() ? get_current_user_id() : 0;
				$wpdb->insert( $t, array( 'city_id' => $id, 'user_id' => $uid, 'voter_key' => $key, 'stars' => $stars, 'created_at' => $now, 'updated_at' => $now ), array( '%d', '%d', '%s', '%d', '%s', '%s' ) );
			}
			CC_DB::recalc( $id );
			$data            = CC_Rating::get( $id, $key );
			$data['success'] = true;
			$data['message'] = $changed ? 'رأی شما به‌روزرسانی شد؛ سپاس!' : 'رأی شما ثبت شد؛ سپاس!';
			return $data;
		} catch ( Throwable $e ) {
			return new WP_Error( 'cc_error', 'خطای داخلی سامانهٔ امتیازدهی', array( 'status' => 500 ) );
		}
	}

	public static function submission( $r ) {
		try {
			$city        = absint( $r->get_param( 'city_id' ) );
			$type        = sanitize_key( $r->get_param( 'type' ) );
			$text        = sanitize_textarea_field( (string) $r->get_param( 'text' ) );
			$place       = sanitize_text_field( (string) $r->get_param( 'place_name' ) );
			$contributor = sanitize_text_field( (string) $r->get_param( 'contributor_name' ) );
			$province    = function_exists( 'sa_gallery_city_province_id' ) ? sa_gallery_city_province_id( $city ) : (int) get_post_meta( $city, 'sa_province_id', true );

			if ( 'city' !== get_post_type( $city ) ) {
				return new WP_Error( 'invalid_city', 'شهر معتبر نیست', array( 'status' => 404 ) );
			}
			if ( $r->get_param( 'website' ) ) {
				return new WP_Error( 'spam', 'ارسال نامعتبر است', array( 'status' => 400 ) );
			}
			if ( '' === $place ) {
				return new WP_Error( 'place_required', 'نام مکان برای ارسال تصویر الزامی است.', array( 'status' => 400 ) );
			}
			if ( mb_strlen( $text ) > 700 ) {
				return new WP_Error( 'invalid_text', 'توضیح اختیاری باید حداکثر ۷۰۰ نویسه باشد.', array( 'status' => 400 ) );
			}
			if ( (string) $r->get_param( 'rights_confirm' ) !== '1' ) {
				return new WP_Error( 'rights_required', 'برای ارسال تصویر باید مالکیت یا اجازهٔ انتشار را تأیید کنید.', array( 'status' => 400 ) );
			}

			$files = self::normalise_uploaded_images();
			if ( ! $files ) {
				return new WP_Error( 'image_required', 'برای آلبوم نمای برتر، انتخاب تصویر الزامی است.', array( 'status' => 400 ) );
			}
			$max_files = self::upload_max_files();
			if ( count( $files ) > $max_files ) {
				return new WP_Error( 'too_many_images', 'در هر ارسال حداکثر ' . self::fa_num( $max_files ) . ' تصویر مجاز است.', array( 'status' => 400 ) );
			}
			$max_bytes = self::upload_max_bytes();
			$max_mb    = self::upload_max_mb();
			foreach ( $files as $file ) {
				if ( ! empty( $file['size'] ) && (int) $file['size'] > $max_bytes ) {
					return new WP_Error( 'image_too_large', 'حجم هر تصویر باید حداکثر ' . self::fa_num( $max_mb ) . ' مگابایت باشد.', array( 'status' => 400 ) );
				}
			}

			$key   = 'cc_submissions_' . self::voter_key( $r );
			$n     = (int) get_transient( $key );
			$daily = self::upload_daily_limit();
			if ( $n + count( $files ) > $daily ) {
				return new WP_Error( 'daily_limit', 'سقف روزانه ارسال تصویر شما تکمیل شده است. سقف فعلی: ' . self::fa_num( $daily ) . ' تصویر در روز.', array( 'status' => 429 ) );
			}

			$sender_note = trim( $text );
			$caption     = sprintf( 'تصویر ارسالی برای %1$s در شهرستان %2$s، استان %3$s.', $place, get_the_title( $city ), $province ? get_the_title( $province ) : '' );
			$created     = array();

			foreach ( $files as $index => $file ) {
				$image = CC_Media::handle( $file, $city, $caption, $place, $contributor );
				if ( is_wp_error( $image ) ) {
					return $image;
				}
				$p = wp_insert_post(
					array(
						'post_type'    => 'cc_submission',
						'post_status'  => 'pending',
						'post_title'   => $place . ' — ' . get_the_title( $city ) . ( count( $files ) > 1 ? ' #' . ( $index + 1 ) : '' ),
						'post_content' => $caption,
						'post_author'  => get_current_user_id(),
						'meta_input'   => array(
							'cc_city_id'          => $city,
							'cc_province_id'      => $province,
							'cc_type'             => $type ? $type : 'photo',
							'cc_place_name'       => $place,
							'cc_contributor_name' => $contributor,
							'cc_sender_note'      => $sender_note,
							'cc_image_id'         => is_int( $image ) ? $image : 0,
							'cc_rights_confirmed' => current_time( 'mysql' ),
							'cc_ip_hash'          => hash( 'sha256', ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' ) . wp_salt( 'auth' ) ),
						),
					),
					true
				);
				if ( is_wp_error( $p ) ) {
					return $p;
				}
				if ( is_int( $image ) && $image ) {
					wp_update_post( array( 'ID' => $image, 'post_parent' => $p ) );
				}
				$created[] = absint( $p );
			}

			set_transient( $key, $n + count( $created ), DAY_IN_SECONDS );
			$count = count( $created );
			return array(
				'success' => true,
				'count'   => $count,
				'message' => self::fa_num( $count ) . ' تصویر و نام مکان برای بررسی ارسال شد.',
			);
		} catch ( Throwable $e ) {
			return new WP_Error( 'cc_error', 'خطای داخلی', array( 'status' => 500 ) );
		}
	}

	public static function resubmit( $r ) {
		try {
			$id = absint( $r['id'] );
			$p  = get_post( $id );
			if ( ! $p || 'cc_submission' !== $p->post_type || (int) $p->post_author !== get_current_user_id() ) {
				return new WP_Error( 'forbidden', 'این مشارکت متعلق به شما نیست', array( 'status' => 403 ) );
			}
			if ( 'needs_edit' !== $p->post_status ) {
				return new WP_Error( 'invalid_status', 'این مشارکت نیاز به اصلاح ندارد', array( 'status' => 400 ) );
			}
			$text = sanitize_textarea_field( $r->get_param( 'text' ) );
			if ( mb_strlen( $text ) > 700 ) {
				return new WP_Error( 'invalid_text', 'متن باید حداکثر ۷۰۰ نویسه باشد', array( 'status' => 400 ) );
			}
			$ok = wp_update_post( array( 'ID' => $id, 'post_content' => $text, 'post_status' => 'pending' ), true );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
			return array( 'success' => true, 'message' => 'نسخه اصلاح‌شده برای بررسی ارسال شد.' );
		} catch ( Throwable $e ) {
			return new WP_Error( 'cc_error', 'خطای داخلی', array( 'status' => 500 ) );
		}
	}

	public static function my_submissions() {
		$q   = new WP_Query( array( 'post_type' => 'cc_submission', 'author' => get_current_user_id(), 'post_status' => array( 'pending', 'publish', 'rejected', 'needs_edit' ), 'posts_per_page' => 20 ) );
		$out = array();
		foreach ( $q->posts as $p ) {
			$out[] = array(
				'id'     => $p->ID,
				'city'   => get_the_title( get_post_meta( $p->ID, 'cc_city_id', true ) ),
				'place'  => get_post_meta( $p->ID, 'cc_place_name', true ),
				'type'   => get_post_meta( $p->ID, 'cc_type', true ),
				'status' => $p->post_status,
				'note'   => get_post_meta( $p->ID, 'cc_review_note', true ),
				'date'   => get_post_time( 'c', true, $p ),
			);
		}
		return $out;
	}
}
