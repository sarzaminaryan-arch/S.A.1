<?php
/**
 * Read-only, schema-checked rule and support-data loader.
 *
 * @package IranAuditEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IAAE_Rules {
	private static $cache = null;
	private static $error = '';

	/**
	 * Load the bundled, owner-approved rule file once per request.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$file = IAAE_PATH . 'data/rules.json';
		if ( ! is_readable( $file ) ) {
			self::$error = 'فایل قوانین در بستهٔ موتور پیدا نشد.';
			self::$cache = array();
			return self::$cache;
		}
		$raw  = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) || JSON_ERROR_NONE !== json_last_error() ) {
			self::$error = 'ساختار JSON قوانین معتبر نیست.';
			self::$cache = array();
			return self::$cache;
		}
		$problems = self::validate( $data );
		if ( $problems ) {
			self::$error = implode( '؛ ', $problems );
			self::$cache = array();
			return self::$cache;
		}
		self::$cache = $data;
		self::$error = '';
		return self::$cache;
	}

	/**
	 * Validate rule file shape and identifiers without mutating it.
	 *
	 * @param array $data Decoded rules.
	 * @return string[]
	 */
	public static function validate( $data ) {
		$problems = array();
		if ( empty( $data['schema_version'] ) || 1 !== (int) $data['schema_version'] ) {
			$problems[] = 'نسخهٔ ساختار قوانین پشتیبانی نمی‌شود';
		}
		if ( empty( $data['rules_version'] ) || ! preg_match( '/^\\d{4}\\.\\d{2}\\.\\d{2}-\\d+$/', (string) $data['rules_version'] ) ) {
			$problems[] = 'نسخهٔ قوانین معتبر نیست';
		}
		if ( empty( $data['profiles'] ) || ! is_array( $data['profiles'] ) || empty( $data['category_weights'] ) || ! is_array( $data['category_weights'] ) ) {
			$problems[] = 'پروفایل‌ها یا وزن دسته‌ها موجود نیست';
			return array_values( array_unique( $problems ) );
		}
		$weight_sum = 0.0;
		foreach ( $data['category_weights'] as $category => $weight ) {
			if ( ! is_string( $category ) || ! preg_match( '/^[a-z][a-z0-9_]*$/', $category ) || ! is_numeric( $weight ) || (float) $weight <= 0 ) {
				$problems[] = 'وزن دستهٔ ' . (string) $category . ' معتبر نیست';
				continue;
			}
			$weight_sum += (float) $weight;
		}
		if ( abs( 100.0 - $weight_sum ) > 0.00001 ) {
			$problems[] = 'مجموع وزن دسته‌ها باید ۱۰۰ باشد';
		}
		if ( empty( $data['rules'] ) || ! is_array( $data['rules'] ) ) {
			$problems[] = 'فهرست قوانین خالی یا نامعتبر است';
			return array_values( array_unique( $problems ) );
		}
		$ids = array();
		$severities = array( 'critical', 'major', 'minor', 'info' );
		$profiles = array_keys( $data['profiles'] );
		foreach ( $data['rules'] as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['id'] ) || empty( $rule['category'] ) || empty( $rule['evaluator'] ) ) {
				$problems[] = 'یک قانون فیلدهای الزامی را ندارد';
				continue;
			}
			$id = (string) $rule['id'];
			if ( ! preg_match( '/^[A-Z0-9]+(?:-[A-Z0-9]+)*-\\d{2}$/', $id ) ) {
				$problems[] = 'شناسهٔ قانون معتبر نیست: ' . $id;
			}
			if ( isset( $ids[ $id ] ) ) {
				$problems[] = 'شناسهٔ تکراری قانون: ' . $id;
			}
			$ids[ $id ] = true;
			if ( ! isset( $data['category_weights'][ $rule['category'] ] ) ) {
				$problems[] = 'دستهٔ قانون ' . $id . ' وزن تعریف‌شده ندارد';
			}
			if ( ! isset( $rule['enabled'] ) || ! is_bool( $rule['enabled'] ) || ! isset( $rule['severity'] ) || ! in_array( $rule['severity'], $severities, true ) || ! isset( $rule['weight'] ) || ! is_numeric( $rule['weight'] ) || (float) $rule['weight'] <= 0 || empty( $rule['applies_to'] ) || ! is_array( $rule['applies_to'] ) ) {
				$problems[] = 'ساختار قانون ' . $id . ' ناقص یا نامعتبر است';
				continue;
			}
			foreach ( $rule['applies_to'] as $profile ) {
				if ( '*' !== $profile && ! in_array( $profile, $profiles, true ) ) {
					$problems[] = 'پروفایل قانون ' . $id . ' تعریف نشده است: ' . (string) $profile;
				}
			}
		}
		return array_values( array_unique( $problems ) );
	}

	/**
	 * Rule file health for status/dashboard.
	 *
	 * @return array
	 */
	public static function health() {
		$data = self::all();
		return array(
			'valid'        => ! empty( $data ),
			'rules_version' => isset( $data['rules_version'] ) ? (string) $data['rules_version'] : '',
			'rule_count'   => isset( $data['rules'] ) && is_array( $data['rules'] ) ? count( $data['rules'] ) : 0,
			'error'        => self::$error,
		);
	}

	/**
	 * Return all enabled rules or one rule by ID.
	 *
	 * @param bool $enabled_only Only enabled rules.
	 * @return array
	 */
	public static function list_rules( $enabled_only = false ) {
		$data = self::all();
		$rules = isset( $data['rules'] ) ? (array) $data['rules'] : array();
		if ( $enabled_only ) {
			$rules = array_values(
				array_filter(
					$rules,
					static function ( $rule ) {
						return ! empty( $rule['enabled'] );
					}
				)
			);
		}
		return $rules;
	}

	/**
	 * Find a rule by its exact configured identifier.
	 *
	 * @param string $rule_id Rule ID.
	 * @return array|null
	 */
	public static function find( $rule_id ) {
		foreach ( self::list_rules() as $rule ) {
			if ( isset( $rule['id'] ) && (string) $rule['id'] === (string) $rule_id ) {
				return $rule;
			}
		}
		return null;
	}

	/**
	 * Read a known JSON support file. Missing files remain explicit gaps.
	 *
	 * @param string $name Basename from the allowlist.
	 * @return array|WP_Error
	 */
	public static function support_data( $name ) {
		$allowed = array( 'spelling-lexicon.json', 'cliches-fa.json', 'iran-divisions.csv' );
		if ( ! in_array( $name, $allowed, true ) ) {
			return new WP_Error( 'iaa_invalid_param', 'نام فایل داده مجاز نیست.', array( 'status' => 400 ) );
		}
		$file = IAAE_PATH . 'data/' . $name;
		if ( ! is_readable( $file ) ) {
			return new WP_Error( 'iaa_data_missing', 'دادهٔ لازم موجود نیست: ' . $name, array( 'status' => 404 ) );
		}
		if ( '.csv' === substr( $name, -4 ) ) {
			return array( 'path' => $file, 'format' => 'csv' );
		}
		$raw  = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		return is_array( $data ) && JSON_ERROR_NONE === json_last_error()
			? $data
			: new WP_Error( 'iaa_rules_invalid', 'فایل دادهٔ کمکی معتبر نیست: ' . $name, array( 'status' => 500 ) );
	}
}
