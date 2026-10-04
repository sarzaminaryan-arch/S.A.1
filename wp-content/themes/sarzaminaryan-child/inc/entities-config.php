<?php
/**
 * GENERATED FILE — do not edit by hand.
 * Source: data-model/schema/data-model.yaml (model v1.1) via build_child_config.py
 * Maps MASTER_DATA_MODEL Levels 1–5 onto WordPress: CPTs, meta keys, relations, taxonomies, SEO fields.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entity definitions keyed by CPT slug.
 */
function sa_entities_config() {
	static $config = null;
	if ( null !== $config ) {
		return $config;
	}
	$config = array(
		'province' => array(
			'cpt' => 'province',
			'status' => 'active',
			'label' => 'Province',
			'singular' => 'استان',
			'plural' => 'استان‌ها',
			'icon' => 'dashicons-location-alt',
			'menu_pos' => 1,
			'url_base' => 'province',
			'primary_key' => 'province_slug',
			'primary_taxonomy' => 'province_tax',
			'summary_field' => null,
			'description_field' => 'province_description',
			'fields' => array(
				array(
					'name' => 'province_center_city',
					'label' => 'مرکز استان',
					'type' => 'reference',
					'key' => 'sa_province_center_city_id',
					'target' => 'city',
				),
				array(
					'name' => 'province_population',
					'label' => 'جمعیت',
					'type' => 'integer',
					'key' => 'sa_province_population',
				),
				array(
					'name' => 'province_area',
					'label' => 'مساحت (کیلومتر مربع)',
					'type' => 'number',
					'key' => 'sa_province_area',
					'unit' => 'km2',
				),
				array(
					'name' => 'province_latitude',
					'label' => 'عرض جغرافیایی',
					'type' => 'float',
					'key' => 'sa_province_latitude',
				),
				array(
					'name' => 'province_longitude',
					'label' => 'طول جغرافیایی',
					'type' => 'float',
					'key' => 'sa_province_longitude',
				),
				array(
					'name' => 'province_climate',
					'label' => 'اقلیم',
					'type' => 'text',
					'key' => 'sa_province_climate',
				),
			),
			'relations' => array(
				array(
					'type' => 'has_many',
					'target' => 'city',
					'status' => 'active',
				),
				array(
					'type' => 'has_many',
					'target' => 'attraction',
					'status' => 'active',
				),
				array(
					'type' => 'has_many',
					'target' => 'local_food',
					'status' => 'active',
				),
				array(
					'type' => 'has_many',
					'target' => 'souvenir',
					'status' => 'active',
				),
				array(
					'type' => 'has_many',
					'target' => 'travel_route',
					'status' => 'active',
				),
				array(
					'type' => 'has_many',
					'target' => 'accommodation',
					'status' => 'reserved',
				),
			),
			'enums' => array(
				array(
					'field' => 'province_best_travel_season',
					'taxonomy' => 'travel_season',
				),
			),
			'taxonomies' => array(
				'province_tax',
				'travel_season',
			),
		),
		'city' => array(
			'cpt' => 'city',
			'status' => 'active',
			'label' => 'City',
			'singular' => 'شهر',
			'plural' => 'شهرها',
			'icon' => 'dashicons-building',
			'menu_pos' => 2,
			'url_base' => 'city',
			'primary_key' => 'city_slug',
			'primary_taxonomy' => 'province_tax',
			'summary_field' => null,
			'description_field' => 'city_description',
			'fields' => array(
				array(
					'name' => 'city_population',
					'label' => 'جمعیت',
					'type' => 'integer',
					'key' => 'sa_city_population',
				),
				array(
					'name' => 'city_elevation',
					'label' => 'ارتفاع از سطح دریا (متر)',
					'type' => 'integer',
					'key' => 'sa_city_elevation',
					'unit' => 'm',
				),
				array(
					'name' => 'city_latitude',
					'label' => 'عرض جغرافیایی',
					'type' => 'float',
					'key' => 'sa_city_latitude',
				),
				array(
					'name' => 'city_longitude',
					'label' => 'طول جغرافیایی',
					'type' => 'float',
					'key' => 'sa_city_longitude',
				),
				array(
					'name' => 'access_air',
					'label' => 'دسترسی هوایی',
					'type' => 'textarea',
					'key' => 'sa_access_air',
				),
				array(
					'name' => 'access_rail',
					'label' => 'دسترسی ریلی',
					'type' => 'textarea',
					'key' => 'sa_access_rail',
				),
				array(
					'name' => 'access_road',
					'label' => 'دسترسی جاده‌ای',
					'type' => 'textarea',
					'key' => 'sa_access_road',
				),
				array(
					'name' => 'google_map_url',
					'label' => 'لینک گوگل‌مپ',
					'type' => 'url',
					'key' => 'sa_google_map_url',
				),
			),
			'relations' => array(
				array(
					'type' => 'belongs_to',
					'target' => 'province',
					'status' => 'active',
					'key' => 'sa_province_id',
				),
				array(
					'type' => 'has_many',
					'target' => 'attraction',
					'status' => 'active',
				),
				array(
					'type' => 'has_many',
					'target' => 'local_food',
					'status' => 'active',
				),
				array(
					'type' => 'has_many',
					'target' => 'souvenir',
					'status' => 'active',
				),
				array(
					'type' => 'belongs_to_many',
					'target' => 'travel_route',
					'status' => 'active',
					'key' => 'sa_travel_route_ids',
				),
				array(
					'type' => 'has_many',
					'target' => 'accommodation',
					'status' => 'reserved',
				),
			),
			'enums' => array(
				array(
					'field' => 'best_travel_season',
					'taxonomy' => 'travel_season',
				),
			),
			'taxonomies' => array(
				'province_tax',
				'travel_season',
			),
		),
		'attraction' => array(
			'cpt' => 'attraction',
			'status' => 'active',
			'label' => 'Attraction',
			'singular' => 'نمای برتر',
			'plural' => 'نمای برتر',
			'icon' => 'dashicons-camera-alt',
			'menu_pos' => 3,
			'url_base' => 'attraction',
			'primary_key' => 'attraction_slug',
			'primary_taxonomy' => 'attraction_type',
			'summary_field' => 'attraction_summary',
			'description_field' => null,
			'fields' => array(
				array(
					'name' => 'latitude',
					'label' => 'عرض جغرافیایی',
					'type' => 'float',
					'key' => 'sa_latitude',
				),
				array(
					'name' => 'longitude',
					'label' => 'طول جغرافیایی',
					'type' => 'float',
					'key' => 'sa_longitude',
				),
				array(
					'name' => 'address',
					'label' => 'نشانی',
					'type' => 'text',
					'key' => 'sa_address',
				),
				array(
					'name' => 'opening_hours',
					'label' => 'ساعات بازدید',
					'type' => 'text',
					'key' => 'sa_opening_hours',
				),
				array(
					'name' => 'ticket_price',
					'label' => 'قیمت بلیت',
					'type' => 'text',
					'key' => 'sa_ticket_price',
				),
				array(
					'name' => 'visit_duration',
					'label' => 'مدت بازدید پیشنهادی',
					'type' => 'text',
					'key' => 'sa_visit_duration',
				),
				array(
					'name' => 'official_website',
					'label' => 'وب‌سایت رسمی',
					'type' => 'url',
					'key' => 'sa_official_website',
				),
				array(
					'name' => 'last_verified_date',
					'label' => 'تاریخ آخرین راستی‌آزمایی (ساعات/قیمت/دسترسی)',
					'type' => 'date',
					'key' => 'sa_last_verified_date',
				),
			),
			'relations' => array(
				array(
					'type' => 'belongs_to',
					'target' => 'province',
					'status' => 'active',
					'key' => 'sa_province_id',
				),
				array(
					'type' => 'belongs_to',
					'target' => 'city',
					'status' => 'active',
					'key' => 'sa_city_id',
				),
				array(
					'type' => 'related_many',
					'target' => 'attraction',
					'status' => 'active',
					'key' => 'sa_related_attraction_ids',
				),
				array(
					'type' => 'near_many',
					'target' => 'accommodation',
					'status' => 'reserved',
					'key' => 'sa_near_accommodation_ids',
				),
			),
			'enums' => array(
				array(
					'field' => 'attraction_type',
					'taxonomy' => 'attraction_type',
				),
				array(
					'field' => 'best_visit_season',
					'taxonomy' => 'travel_season',
				),
			),
			'taxonomies' => array(
				'attraction_type',
				'travel_season',
			),
		),
		'travel_route' => array(
			'cpt' => 'travel_route',
			'status' => 'active',
			'label' => 'TravelRoute',
			'singular' => 'مسیر سفر',
			'plural' => 'مسیرهای سفر',
			'icon' => 'dashicons-randomize',
			'menu_pos' => 4,
			'url_base' => 'route',
			'primary_key' => 'route_slug',
			'primary_taxonomy' => 'travel_duration',
			'summary_field' => 'route_summary',
			'description_field' => null,
			'fields' => array(
				array(
					'name' => 'route_distance',
					'label' => 'مسافت مسیر (کیلومتر)',
					'type' => 'number',
					'key' => 'sa_route_distance',
					'unit' => 'km',
				),
			),
			'relations' => array(
				array(
					'type' => 'belongs_to_many',
					'target' => 'city',
					'status' => 'active',
					'key' => 'sa_city_ids',
				),
				array(
					'type' => 'belongs_to_many',
					'target' => 'attraction',
					'status' => 'active',
					'key' => 'sa_attraction_ids',
				),
				array(
					'type' => 'has_many',
					'target' => 'accommodation',
					'status' => 'reserved',
				),
			),
			'enums' => array(
				array(
					'field' => 'route_duration',
					'taxonomy' => 'travel_duration',
				),
				array(
					'field' => 'best_season',
					'taxonomy' => 'travel_season',
				),
				array(
					'field' => 'estimated_budget',
					'taxonomy' => 'travel_budget',
				),
			),
			'taxonomies' => array(
				'travel_budget',
				'travel_duration',
				'travel_season',
			),
		),
		'local_food' => array(
			'cpt' => 'local_food',
			'status' => 'active',
			'label' => 'LocalFood',
			'singular' => 'غذای محلی',
			'plural' => 'غذاهای محلی',
			'icon' => 'dashicons-food',
			'menu_pos' => 5,
			'url_base' => 'food',
			'primary_key' => 'food_slug',
			'primary_taxonomy' => 'province_tax',
			'summary_field' => 'food_summary',
			'description_field' => null,
			'fields' => array(
				array(
					'name' => 'main_ingredients',
					'label' => 'مواد اصلی (هر مورد در یک خط)',
					'type' => 'list',
					'key' => 'sa_main_ingredients',
				),
				array(
					'name' => 'serving_method',
					'label' => 'نحوه سرو',
					'type' => 'textarea',
					'key' => 'sa_serving_method',
				),
			),
			'relations' => array(
				array(
					'type' => 'belongs_to',
					'target' => 'city',
					'status' => 'active',
					'key' => 'sa_city_id',
				),
				array(
					'type' => 'belongs_to',
					'target' => 'province',
					'status' => 'active',
					'key' => 'sa_province_id',
				),
			),
			'enums' => array(),
			'taxonomies' => array(
				'province_tax',
			),
		),
		'souvenir' => array(
			'cpt' => 'souvenir',
			'status' => 'active',
			'label' => 'Souvenir',
			'singular' => 'سوغات',
			'plural' => 'سوغات',
			'icon' => 'dashicons-cart',
			'menu_pos' => 6,
			'url_base' => 'souvenir',
			'primary_key' => 'souvenir_slug',
			'primary_taxonomy' => 'province_tax',
			'summary_field' => 'souvenir_summary',
			'description_field' => null,
			'fields' => array(
				array(
					'name' => 'purchase_location',
					'label' => 'محل خرید',
					'type' => 'textarea',
					'key' => 'sa_purchase_location',
				),
			),
			'relations' => array(
				array(
					'type' => 'belongs_to',
					'target' => 'city',
					'status' => 'active',
					'key' => 'sa_city_id',
				),
				array(
					'type' => 'belongs_to',
					'target' => 'province',
					'status' => 'active',
					'key' => 'sa_province_id',
				),
			),
			'enums' => array(),
			'taxonomies' => array(
				'province_tax',
			),
		),
		'accommodation' => array(
			'cpt' => 'accommodation',
			'status' => 'reserved',
			'label' => 'Accommodation',
			'singular' => 'اقامتگاه',
			'plural' => 'اقامتگاه‌ها',
			'icon' => 'dashicons-admin-multisite',
			'menu_pos' => 7,
			'url_base' => 'accommodation',
			'primary_key' => 'accommodation_slug',
			'primary_taxonomy' => 'accommodation_type',
			'summary_field' => 'accommodation_summary',
			'description_field' => null,
			'fields' => array(
				array(
					'name' => 'star_rating',
					'label' => 'ستاره (۱ تا ۵)',
					'type' => 'integer',
					'key' => 'sa_star_rating',
					'min' => 1,
					'max' => 5,
				),
				array(
					'name' => 'price_per_night_min',
					'label' => 'حداقل قیمت هر شب',
					'type' => 'number',
					'key' => 'sa_price_per_night_min',
				),
				array(
					'name' => 'price_per_night_max',
					'label' => 'حداکثر قیمت هر شب',
					'type' => 'number',
					'key' => 'sa_price_per_night_max',
				),
				array(
					'name' => 'address',
					'label' => 'نشانی',
					'type' => 'text',
					'key' => 'sa_address',
				),
				array(
					'name' => 'latitude',
					'label' => 'عرض جغرافیایی',
					'type' => 'float',
					'key' => 'sa_latitude',
				),
				array(
					'name' => 'longitude',
					'label' => 'طول جغرافیایی',
					'type' => 'float',
					'key' => 'sa_longitude',
				),
				array(
					'name' => 'phone',
					'label' => 'تلفن',
					'type' => 'text',
					'key' => 'sa_phone',
				),
				array(
					'name' => 'website_url',
					'label' => 'وب‌سایت',
					'type' => 'url',
					'key' => 'sa_website_url',
				),
				array(
					'name' => 'booking_url',
					'label' => 'لینک رزرو',
					'type' => 'url',
					'key' => 'sa_booking_url',
				),
				array(
					'name' => 'affiliate_provider',
					'label' => 'ارائه‌دهنده همکاری',
					'type' => 'text',
					'key' => 'sa_affiliate_provider',
				),
				array(
					'name' => 'is_sponsored',
					'label' => 'محتوای اسپانسری',
					'type' => 'boolean',
					'key' => 'sa_is_sponsored',
				),
				array(
					'name' => 'amenities',
					'label' => 'امکانات (هر مورد در یک خط)',
					'type' => 'list',
					'key' => 'sa_amenities',
				),
				array(
					'name' => 'check_in_time',
					'label' => 'ساعت ورود',
					'type' => 'time',
					'key' => 'sa_check_in_time',
				),
				array(
					'name' => 'check_out_time',
					'label' => 'ساعت خروج',
					'type' => 'time',
					'key' => 'sa_check_out_time',
				),
				array(
					'name' => 'capacity',
					'label' => 'ظرفیت',
					'type' => 'integer',
					'key' => 'sa_capacity',
				),
				array(
					'name' => 'google_map_url',
					'label' => 'لینک گوگل‌مپ',
					'type' => 'url',
					'key' => 'sa_google_map_url',
				),
			),
			'relations' => array(
				array(
					'type' => 'belongs_to',
					'target' => 'city',
					'status' => 'reserved',
					'key' => 'sa_city_id',
					'required' => true,
				),
				array(
					'type' => 'belongs_to',
					'target' => 'province',
					'status' => 'reserved',
					'key' => 'sa_province_id',
				),
				array(
					'type' => 'near_many',
					'target' => 'attraction',
					'status' => 'reserved',
					'key' => 'sa_near_attraction_ids',
				),
				array(
					'type' => 'belongs_to_many',
					'target' => 'travel_route',
					'status' => 'reserved',
					'key' => 'sa_travel_route_ids',
				),
				array(
					'type' => 'related_many',
					'target' => 'accommodation',
					'status' => 'reserved',
					'key' => 'sa_related_accommodation_ids',
				),
			),
			'enums' => array(
				array(
					'field' => 'accommodation_type',
					'taxonomy' => 'accommodation_type',
				),
				array(
					'field' => 'price_range',
					'taxonomy' => 'travel_budget',
				),
			),
			'taxonomies' => array(
				'accommodation_type',
				'travel_budget',
			),
		),
	);
	return $config;
}

/**
 * Taxonomy definitions keyed by taxonomy slug.
 */
function sa_taxonomies_config() {
	return array(
		'province_tax' => array(
			'status' => 'active',
			'singular' => 'استان',
			'plural' => 'استان‌ها',
			'slug' => 'ostan',
			'hierarchical' => true,
			'applies_to' => array(
				'province',
				'city',
				'attraction',
				'travel_route',
				'local_food',
				'souvenir',
				'accommodation',
			),
			'terms' => array(),
			'source' => '31 provinces of Iran',
		),
		'attraction_type' => array(
			'status' => 'active',
			'singular' => 'نوع نمای برتر',
			'plural' => 'انواع نمای برتر',
			'slug' => 'attraction-type',
			'hierarchical' => true,
			'applies_to' => array(
				'attraction',
			),
			'terms' => array(
				array(
					'slug' => 'historical',
					'name' => 'تاریخی',
				),
				array(
					'slug' => 'cultural',
					'name' => 'فرهنگی',
				),
				array(
					'slug' => 'religious',
					'name' => 'مذهبی',
				),
				array(
					'slug' => 'nature',
					'name' => 'طبیعی',
				),
				array(
					'slug' => 'mountain',
					'name' => 'کوهستانی',
				),
				array(
					'slug' => 'forest',
					'name' => 'جنگلی',
				),
				array(
					'slug' => 'desert',
					'name' => 'کویری',
				),
				array(
					'slug' => 'beach',
					'name' => 'ساحلی',
				),
				array(
					'slug' => 'island',
					'name' => 'جزیره‌ای',
				),
				array(
					'slug' => 'village',
					'name' => 'روستایی',
				),
				array(
					'slug' => 'ecotourism',
					'name' => 'بوم‌گردی',
				),
				array(
					'slug' => 'adventure',
					'name' => 'ماجراجویی',
				),
			),
			'source' => null,
		),
		'travel_season' => array(
			'status' => 'active',
			'singular' => 'فصل سفر',
			'plural' => 'فصل‌های سفر',
			'slug' => 'season',
			'hierarchical' => false,
			'applies_to' => array(
				'province',
				'city',
				'attraction',
				'travel_route',
			),
			'terms' => array(
				array(
					'slug' => 'spring',
					'name' => 'بهار',
				),
				array(
					'slug' => 'summer',
					'name' => 'تابستان',
				),
				array(
					'slug' => 'autumn',
					'name' => 'پاییز',
				),
				array(
					'slug' => 'winter',
					'name' => 'زمستان',
				),
			),
			'source' => null,
		),
		'travel_budget' => array(
			'status' => 'active',
			'singular' => 'بودجه سفر',
			'plural' => 'بودجه‌های سفر',
			'slug' => 'budget',
			'hierarchical' => false,
			'applies_to' => array(
				'travel_route',
				'accommodation',
			),
			'terms' => array(
				array(
					'slug' => 'economic',
					'name' => 'اقتصادی',
				),
				array(
					'slug' => 'medium',
					'name' => 'متوسط',
				),
				array(
					'slug' => 'luxury',
					'name' => 'لوکس',
				),
			),
			'source' => null,
		),
		'travel_duration' => array(
			'status' => 'active',
			'singular' => 'مدت سفر',
			'plural' => 'مدت‌های سفر',
			'slug' => 'duration',
			'hierarchical' => false,
			'applies_to' => array(
				'travel_route',
			),
			'terms' => array(
				array(
					'slug' => 'one_day',
					'name' => 'یک‌روزه',
				),
				array(
					'slug' => 'weekend',
					'name' => 'آخر هفته',
				),
				array(
					'slug' => '3_to_5_days',
					'name' => '۳ تا ۵ روز',
				),
				array(
					'slug' => 'more_than_5_days',
					'name' => 'بیش از ۵ روز',
				),
			),
			'source' => null,
		),
		'accommodation_type' => array(
			'status' => 'reserved',
			'singular' => 'نوع اقامتگاه',
			'plural' => 'انواع اقامتگاه',
			'slug' => 'accommodation-type',
			'hierarchical' => true,
			'applies_to' => array(
				'accommodation',
			),
			'terms' => array(
				array(
					'slug' => 'hotel',
					'name' => 'هتل',
				),
				array(
					'slug' => 'eco_lodge',
					'name' => 'اقامتگاه بوم‌گردی',
				),
				array(
					'slug' => 'guest_house',
					'name' => 'مهمان‌پذیر',
				),
				array(
					'slug' => 'traditional_house',
					'name' => 'خانه سنتی',
				),
				array(
					'slug' => 'camping',
					'name' => 'کمپینگ',
				),
			),
			'source' => null,
		),
	);
}

/**
 * Level 5 — SEO fields required on every entity (meta keys are prefixed with sa_).
 */
function sa_seo_required_fields() {
	return array(
		'seo_title',
		'seo_description',
		'focus_keyword',
		'og_title',
		'og_description',
		'faq_schema',
		'breadcrumb_schema',
		'canonical_url',
	);
}

/**
 * Level 7 — publish blockers.
 */
function sa_publish_blockers() {
	return array(
		'missing_relation',
		'missing_seo_fields',
		'missing_faq',
		'missing_featured_image',
		'missing_primary_taxonomy',
		'missing_coordinates',
		'missing_sources',
		'missing_internal_links',
	);
}

/**
 * Level 7 (v1.1) — per-entity minimums enforced by the publish gate: faq, sources, internal_links, coordinates.
 */
function sa_content_minimums( $type = '' ) {
	$all = array(
		'province' => array(
			'faq' => 10,
			'sources' => 5,
			'internal_links' => 20,
			'coordinates' => true,
		),
		'city' => array(
			'faq' => 10,
			'sources' => 5,
			'internal_links' => 10,
			'coordinates' => true,
		),
		'attraction' => array(
			'faq' => 10,
			'sources' => 5,
			'internal_links' => 10,
			'coordinates' => true,
		),
		'travel_route' => array(
			'faq' => 3,
			'sources' => 2,
			'internal_links' => 5,
			'coordinates' => false,
		),
		'local_food' => array(
			'faq' => 3,
			'sources' => 2,
			'internal_links' => 3,
			'coordinates' => false,
		),
		'souvenir' => array(
			'faq' => 3,
			'sources' => 2,
			'internal_links' => 3,
			'coordinates' => false,
		),
		'accommodation' => array(
			'faq' => 3,
			'sources' => 2,
			'internal_links' => 3,
			'coordinates' => true,
		),
	);
	if ( '' === $type ) {
		return $all;
	}
	return isset( $all[ $type ] ) ? $all[ $type ] : array( 'faq' => 1, 'sources' => 0, 'internal_links' => 0, 'coordinates' => false );
}

/**
 * Level 7 (v1.1) — uncertainty markers allowed in published text (rendered as a badge, counted, never removed).
 */
function sa_uncertainty_markers() {
	return array(
		'[نیازمند بررسی]',
		'[منبع لازم]',
	);
}

/**
 * Level 7 (v1.1) — days after which attraction.last_verified_date is considered stale.
 */
function sa_stale_after_days() {
	return 365;
}
