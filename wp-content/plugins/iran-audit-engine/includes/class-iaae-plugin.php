<?php
/**
 * Engine lifecycle and non-persistent capability mapping.
 *
 * @package IranAuditEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IAAE_Plugin {
	private static $initialized = false;

	public static function init() {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'user_has_cap', array( __CLASS__, 'map_capabilities' ), 10, 4 );
		if ( (string) get_option( 'iaa_schema_version', '' ) !== IAAE_Database::SCHEMA_VERSION || ! IAAE_Database::tables_exist() ) {
			IAAE_Database::install();
		}
		IAAE_REST::init();
		IAAE_Jobs::init();
		IAAE_Admin::init();
	}

	/**
	 * Provide contract capabilities from existing WordPress caps without persisting role changes.
	 *
	 * @param array  $allcaps User capabilities.
	 * @param string[] $caps Requested caps.
	 * @param array  $args Capability arguments.
	 * @param WP_User $user User.
	 * @return array
	 */
	public static function map_capabilities( $allcaps, $caps, $args, $user ) {
		if ( ! is_array( $allcaps ) ) {
			return $allcaps;
		}
		if ( ! empty( $allcaps['edit_others_posts'] ) ) {
			$allcaps['iaa_view'] = true;
			$allcaps['iaa_run'] = true;
		}
		if ( ! empty( $allcaps['manage_options'] ) ) {
			$allcaps['iaa_manage'] = true;
			$allcaps['iaa_view'] = true;
			$allcaps['iaa_run'] = true;
		}
		return $allcaps;
	}
}
