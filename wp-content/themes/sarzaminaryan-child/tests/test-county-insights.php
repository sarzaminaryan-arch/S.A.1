<?php
/**
 * Standalone test: CU-1 «از تجربهٔ مسافرها» data layer (2.22.49).
 *
 * The county page keeps its fixed article text; this layer adds the living,
 * traveller-sourced facts on top of it (docs/live-site/core-update-2026-plan.md):
 * access quick-facts, four-season notes, structured local tips, extra places
 * people actually go, the «مردم از نگاه مسافرها» character note and the
 * field-update note. Everything is stored in canonical county-profile keys,
 * so the admin meta box, the save handler and the empty-field report pick
 * the new fields up automatically.
 *
 * Locked rules guarded here:
 *  1. The twelve CU-1 keys are registered in the canonical field registry.
 *  2. Tip lines parse `دسته | عنوان | متن` (Latin or Persian category),
 *     malformed lines are dropped, unknown categories fall back safely.
 *  3. Place lines parse `نام | نوع | مسیر | نکته | پیوند`; name is required.
 *  4. has_insights is true only when real insight data exists — a county
 *     with only classic identity data renders no traveller block at all.
 *  5. The template carries the first-person sourcing note (sources named,
 *     no fake certainty, promise to re-update) and the block anchor/TOC id.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-county-insights.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

$GLOBALS['t'] = array( 'meta' => array(), 'posts' => array() );

function sa_ci_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}
function add_action() { return true; }
function add_filter() { return true; }
function get_post_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['t']['meta'][ $id ][ $key ] ) ? $GLOBALS['t']['meta'][ $id ][ $key ] : '';
}
function get_post( $id ) {
	$id = is_object( $id ) ? $id->ID : (int) $id;
	return isset( $GLOBALS['t']['posts'][ $id ] ) ? $GLOBALS['t']['posts'][ $id ] : null;
}
function get_post_type( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_type : null;
}
function get_the_title( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_title : '';
}
function get_permalink( $id ) {
	$post = get_post( is_object( $id ) ? $id->ID : $id );
	return $post ? 'https://example.test/' . $post->post_type . '/' . $post->post_name . '/' : '';
}
function get_page_by_path( $path, $output = null, $type = 'page' ) {
	unset( $output, $type );
	foreach ( $GLOBALS['t']['posts'] as $post ) {
		if ( $post->post_name === $path ) {
			return $post;
		}
	}
	return null;
}
function sanitize_title( $title ) { return trim( (string) $title ); }
function sa_county_name( $post_or_id, $include_prefix = false ) {
	$name = get_the_title( $post_or_id );
	return $include_prefix && '' !== $name ? 'شهرستان ' . $name : $name;
}

$theme_dir = dirname( __DIR__ );
require_once $theme_dir . '/inc/county-profile.php';

/* ---- 1. Registry -------------------------------------------------------- */

$fields = sa_county_profile_fields();
foreach ( array( 'road_quality', 'mobile_internet', 'access_difficulty', 'parking_status', 'season_spring', 'season_summer', 'season_autumn', 'season_winter', 'local_tips', 'extra_places', 'people_character', 'field_update_note', 'plate_code', 'phone_code' ) as $key ) {
	sa_ci_assert( isset( $fields[ $key ] ), "insights: canonical key {$key} is registered" );
}

/* ---- 2. Tips parser ------------------------------------------------------ */

$tips = sa_county_profile_parse_tips( "warning | پمپ‌بنزین | در مسیر برگشت صف طولانی است.\nهشدار | زنجیر چرخ | گردنه برف‌گیر است.\nفرهنگ | سوغات | عسل را از روستا بخرید.\nکاربردی | ساعت بازدید | صبح زود خلوت‌تر است.\nsomething | نکته | دستهٔ ناشناخته امن می‌ماند.\nخراب | فقط دو بخش" );
sa_ci_assert( 5 === count( $tips ), 'tips: malformed line dropped, five valid tips parsed' );
sa_ci_assert( 'warning' === $tips[0]['category'] && 'warning' === $tips[1]['category'], 'tips: Latin and Persian warning both map to warning' );
sa_ci_assert( 'culture' === $tips[2]['category'], 'tips: Persian فرهنگ maps to culture' );
sa_ci_assert( 'logistics' === $tips[3]['category'] && 'logistics' === $tips[4]['category'], 'tips: کاربردی and unknown categories fall back to logistics' );
sa_ci_assert( 'هشدار' === $tips[0]['cat_label'], 'tips: Persian category label rendered' );
sa_ci_assert( array() === sa_county_profile_parse_tips( '' ), 'tips: empty input renders nothing' );

/* ---- 3. Places parser ---------------------------------------------------- */

$places = sa_county_profile_parse_places( "آبشار نمونه | طبیعت | جادهٔ اصلی، کیلومتر ۱۲ | صبح زود بروید | https://example.test/abshar\nدریاچهٔ نمونه | تفریحی | مسیر فرعی آسفالت\n | بدون نام نمی‌آید" );
sa_ci_assert( 2 === count( $places ), 'places: nameless line dropped' );
sa_ci_assert( 'آبشار نمونه' === $places[0]['name'] && 'https://example.test/abshar' === $places[0]['url'], 'places: full line parses with link' );
sa_ci_assert( 'دریاچهٔ نمونه' === $places[1]['name'] && '' === $places[1]['url'], 'places: link is optional' );

/* ---- 4. Aggregation: has_insights --------------------------------------- */

$GLOBALS['t']['posts'][10] = (object) array( 'ID' => 10, 'post_type' => 'city', 'post_name' => 'demo-city', 'post_title' => 'تهران', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][10]   = array( 'center_fa' => 'تهران' );
$plain = sa_county_profile_data( 10 );
sa_ci_assert( empty( $plain['has_insights'] ), 'insights: classic-only county has no traveller block' );

$GLOBALS['t']['meta'][10]['road_quality']   = 'بزرگراه آسفالت تا خود شهر';
$GLOBALS['t']['meta'][10]['season_spring']  = 'اردیبهشت بهترین هواست.';
$GLOBALS['t']['meta'][10]['local_tips']     = 'warning | ترافیک برگشت | عصر جمعه مسیر شمال سنگین است.';
$GLOBALS['t']['meta'][10]['extra_places']   = 'بام نمونه | تفریحی | انتهای خیابان نمونه | غروب شلوغ است | ';
$GLOBALS['t']['meta'][10]['people_character'] = 'از تجربهٔ مسافرها، مردمش گرم و مهمان‌دوست‌اند.';
$GLOBALS['t']['meta'][10]['plate_code'] = 'ایران ۱۱، ۲۲، ۳۳';
$rich = sa_county_profile_data( 10 );
sa_ci_assert( ! empty( $rich['has_insights'] ), 'insights: block switches on with real data' );
sa_ci_assert( 'بزرگراه آسفالت تا خود شهر' === $rich['access_facts']['وضعیت جاده'], 'insights: access fact labelled in Persian' );
sa_ci_assert( isset( $rich['seasons']['بهار'] ) && ! isset( $rich['seasons']['زمستان'] ), 'insights: only filled seasons listed' );
sa_ci_assert( 1 === count( $rich['local_tips'] ) && 1 === count( $rich['extra_places'] ), 'insights: parsed structures reach the data layer' );
sa_ci_assert( 'ایران ۱۱، ۲۲، ۳۳' === $rich['plate_code'], 'insights: license plate reaches the data layer (tile 12)' );

/* ---- 4b. Bundled seed: Tehran experience + plate/phone fallbacks (2.22.55) - */

$GLOBALS['t']['posts'][20] = (object) array( 'ID' => 20, 'post_type' => 'city', 'post_name' => 'tehran', 'post_title' => 'تهران', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][20]   = array();
$seed = sa_county_profile_data( 20 );
sa_ci_assert( ! empty( $seed['has_insights'] ), 'bundled: Tehran traveller block switches on from the shipped seed' );
sa_ci_assert( 4 === count( $seed['seasons'] ), 'bundled: four seasons reach the data layer' );
sa_ci_assert( 6 === count( $seed['local_tips'] ) && 10 === count( $seed['extra_places'] ), 'bundled: tips and places parsed from the seed' );
sa_ci_assert( 'ایران ۱۱، ۲۲، ۳۳، ۴۴، ۵۵، ۶۶، ۷۷، ۸۸، ۹۹' === $seed['plate_code'], 'bundled: Tehran plate reaches tile 12 without meta' );
$GLOBALS['t']['meta'][20]['plate_code'] = 'ایران ۱۱';
sa_ci_assert( 'ایران ۱۱' === sa_county_profile_data( 20 )['plate_code'], 'bundled: post meta wins over the shipped seed' );
$GLOBALS['t']['meta'][20]['phone_code'] = '۰۲۱';
sa_ci_assert( '۰۲۱' === sa_county_profile_data( 20 )['phone_code'], 'phone: canonical phone_code reaches the data layer (tile 13)' );

$plates = require $theme_dir . '/data/county-plates.php';
sa_ci_assert( count( $plates ) >= 340, 'plates: county plate table covers 340+ counties' );
sa_ci_assert( isset( $plates['tehran-city'] ) && isset( $plates['dorud'] ), 'plates: keyed by registry slug (tehran-city, dorud)' );
$provinces_data = require $theme_dir . '/data/provinces.php';
$phones_ok = 0;
foreach ( $provinces_data as $row ) {
	if ( ! empty( $row['phone'] ) ) {
		$phones_ok++;
	}
}
sa_ci_assert( 31 === $phones_ok, 'phone: all 31 provinces carry a fixed-line area code' );

/* ---- 4c. Bundled seed: Mashhad experience (2.22.57) --------------------- */

$GLOBALS['t']['posts'][30] = (object) array( 'ID' => 30, 'post_type' => 'city', 'post_name' => 'mashhad', 'post_title' => 'مشهد', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][30]   = array();
$mashhad = sa_county_profile_data( 30 );
sa_ci_assert( ! empty( $mashhad['has_insights'] ), 'bundled: Mashhad traveller block switches on from the shipped seed' );
sa_ci_assert( 4 === count( $mashhad['seasons'] ), 'bundled: Mashhad four seasons reach the data layer' );
sa_ci_assert( 8 === count( $mashhad['local_tips'] ) && 12 === count( $mashhad['extra_places'] ), 'bundled: Mashhad tips and places parsed from the seed' );
sa_ci_assert( 'ایران ۱۲، ۳۶، ۴۲، ۷۴' === $mashhad['plate_code'], 'bundled: Mashhad plate reaches tile 12 without meta' );
sa_ci_assert( false !== strpos( (string) $mashhad['people_character'], 'زائرنواز' ), 'bundled: Mashhad people note is the positive traveller view' );

/* ---- 4d. Bundled seeds: Isfahan, Karaj, Shiraz (2.22.58) ----------------- */

$GLOBALS['t']['posts'][40] = (object) array( 'ID' => 40, 'post_type' => 'city', 'post_name' => 'isfahan', 'post_title' => 'اصفهان', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][40]   = array();
$isfahan = sa_county_profile_data( 40 );
sa_ci_assert( ! empty( $isfahan['has_insights'] ), 'bundled: Isfahan traveller block switches on from the shipped seed' );
sa_ci_assert( 8 === count( $isfahan['local_tips'] ) && 11 === count( $isfahan['extra_places'] ), 'bundled: Isfahan tips and places parsed from the seed' );
sa_ci_assert( 'ایران ۱۳، ۵۳، ۶۷' === $isfahan['plate_code'], 'bundled: Isfahan plate reaches tile 12 without meta' );

$GLOBALS['t']['posts'][41] = (object) array( 'ID' => 41, 'post_type' => 'city', 'post_name' => 'karaj', 'post_title' => 'کرج', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][41]   = array();
$karaj = sa_county_profile_data( 41 );
sa_ci_assert( ! empty( $karaj['has_insights'] ), 'bundled: Karaj traveller block switches on from the shipped seed' );
sa_ci_assert( 6 === count( $karaj['local_tips'] ) && 8 === count( $karaj['extra_places'] ), 'bundled: Karaj tips and places parsed from the seed' );
sa_ci_assert( 'ایران ۶۸، ۲۱، ۳۸' === $karaj['plate_code'], 'bundled: Karaj plate reaches tile 12 without meta' );

$GLOBALS['t']['posts'][42] = (object) array( 'ID' => 42, 'post_type' => 'city', 'post_name' => 'shiraz', 'post_title' => 'شیراز', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][42]   = array();
$shiraz = sa_county_profile_data( 42 );
sa_ci_assert( ! empty( $shiraz['has_insights'] ), 'bundled: Shiraz traveller block switches on from the shipped seed' );
sa_ci_assert( 7 === count( $shiraz['local_tips'] ) && 10 === count( $shiraz['extra_places'] ), 'bundled: Shiraz tips and places parsed from the seed' );
sa_ci_assert( 'ایران ۶۳، ۹۳' === $shiraz['plate_code'], 'bundled: Shiraz plate reaches tile 12 without meta' );

/* ---- 4e. Bundled seeds: Tabriz, Qom + insight freshness (2.22.59) -------- */

$GLOBALS['t']['posts'][50] = (object) array( 'ID' => 50, 'post_type' => 'city', 'post_name' => 'tabriz', 'post_title' => 'تبریز', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][50]   = array();
$tabriz = sa_county_profile_data( 50 );
sa_ci_assert( ! empty( $tabriz['has_insights'] ), 'bundled: Tabriz traveller block switches on from the shipped seed' );
sa_ci_assert( 6 === count( $tabriz['local_tips'] ) && 9 === count( $tabriz['extra_places'] ), 'bundled: Tabriz tips and places parsed from the seed' );
sa_ci_assert( 'ایران ۱۵، ۳۵' === $tabriz['plate_code'], 'bundled: Tabriz plate reaches tile 12 without meta' );
sa_ci_assert( '2026-11-12' === $tabriz['insight_updated_at'], 'bundled: insight freshness date is exposed for schema dateModified' );

$GLOBALS['t']['posts'][51] = (object) array( 'ID' => 51, 'post_type' => 'city', 'post_name' => 'qom', 'post_title' => 'قم', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][51]   = array();
$qom = sa_county_profile_data( 51 );
sa_ci_assert( ! empty( $qom['has_insights'] ), 'bundled: Qom traveller block switches on from the shipped seed' );
sa_ci_assert( 7 === count( $qom['local_tips'] ) && 9 === count( $qom['extra_places'] ), 'bundled: Qom tips and places parsed from the seed' );
sa_ci_assert( 'ایران ۱۶' === $qom['plate_code'], 'bundled: Qom plate reaches tile 12 without meta' );

$schema_src = (string) file_get_contents( $theme_dir . '/inc/schema.php' );
sa_ci_assert( false !== strpos( $schema_src, 'insight_updated_at' ), 'schema: city dateModified follows insight freshness (2.22.59)' );

$css59 = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
$pos59 = strpos( $css59, 'v2.22.59' );
sa_ci_assert( false !== $pos59, 'css: v2.22.59 badge block exists' );
$block59 = substr( $css59, (int) $pos59, 1600 );
sa_ci_assert( false !== strpos( $block59, '.sa-field-fact__label' ), 'css: access-fact titles use the badge geometry (2.22.59)' );
sa_ci_assert( false !== strpos( $block59, 'rgba(244, 63, 94' ), 'css: access-fact badge halo is vivid red-pink (2.22.59)' );
sa_ci_assert( false !== strpos( $block59, 'color: #111827' ), 'css: access-fact badge text stays black (2.22.59)' );

/* ---- 4f. Bundled seed: Ahvaz + tight fact badges (2.22.60) --------------- */

$GLOBALS['t']['posts'][52] = (object) array( 'ID' => 52, 'post_type' => 'city', 'post_name' => 'ahvaz', 'post_title' => 'اهواز', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][52]   = array();
$ahvaz = sa_county_profile_data( 52 );
sa_ci_assert( ! empty( $ahvaz['has_insights'] ), 'bundled: Ahvaz traveller block switches on from the shipped seed' );
sa_ci_assert( 6 === count( $ahvaz['local_tips'] ) && 8 === count( $ahvaz['extra_places'] ), 'bundled: Ahvaz tips and places parsed from the seed' );
sa_ci_assert( 'ایران ۱۴' === $ahvaz['plate_code'], 'bundled: Ahvaz plate reaches tile 12 without meta' );

$css60 = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
$pos60 = strpos( $css60, 'v2.22.60' );
sa_ci_assert( false !== $pos60, 'css: v2.22.60 badge-fit block exists' );
$block60 = substr( $css60, (int) $pos60, 1400 );
sa_ci_assert( false !== strpos( $block60, 'justify-self: start' ), 'css: access-fact badge hugs its word, never the card width (2.22.60)' );
sa_ci_assert( false !== strpos( $block60, 'display: inline-block' ), 'css: access-fact badge is inline like the place-kind label (2.22.60)' );

/* ---- 4h. Bundled seeds: ten-city batch import (2.22.62) ------------------ */

$batch62 = array(
	53 => array( 'kermanshah', 'کرمانشاه', 7, 9, 'ایران ۱۹' ),
	54 => array( 'urmia', 'ارومیه', 7, 8, 'ایران ۱۷' ),
	55 => array( 'rasht', 'رشت', 7, 8, 'ایران ۴۶' ),
	56 => array( 'zahedan', 'زاهدان', 7, 8, 'ایران ۸۵' ),
	57 => array( 'hamedan', 'همدان', 7, 9, 'ایران ۱۸' ),
	58 => array( 'kerman', 'کرمان', 7, 9, 'ایران ۴۵' ),
	59 => array( 'yazd', 'یزد', 7, 9, 'ایران ۵۴' ),
	60 => array( 'ardabil-city', 'اردبیل', 7, 8, 'ایران ۹۱، ۷۶' ),
	61 => array( 'bandar-e-abbas', 'بندرعباس', 7, 9, 'ایران ۸۴' ),
	62 => array( 'arak', 'اراک', 6, 8, 'ایران ۴۷' ),
);
foreach ( $batch62 as $batch_id => $batch_row ) {
	list( $batch_slug, $batch_name, $batch_tips, $batch_places, $batch_plate ) = $batch_row;
	$GLOBALS['t']['posts'][ $batch_id ] = (object) array( 'ID' => $batch_id, 'post_type' => 'city', 'post_name' => $batch_slug, 'post_title' => $batch_name, 'post_status' => 'publish' );
	$GLOBALS['t']['meta'][ $batch_id ]   = array();
	$batch_data = sa_county_profile_data( $batch_id );
	sa_ci_assert( ! empty( $batch_data['has_insights'] ), "bundled: {$batch_slug} traveller block switches on from the shipped seed (2.22.62)" );
	sa_ci_assert( $batch_tips === count( $batch_data['local_tips'] ) && $batch_places === count( $batch_data['extra_places'] ), "bundled: {$batch_slug} tips and places parsed from the seed (2.22.62)" );
	sa_ci_assert( $batch_plate === $batch_data['plate_code'], "bundled: {$batch_slug} plate reaches tile 12 without meta (2.22.62)" );
}

/* ---- 4i. Bundled seeds: seven-city batch import up to ساری (2.22.63) ----- */

$batch63 = array(
	63 => array( 'eslamshahr', 'اسلامشهر', 7, 8, 'ایران ۷۸، ۲۱، ۳۸' ),
	64 => array( 'zanjan-city', 'زنجان', 7, 8, 'ایران ۸۷' ),
	65 => array( 'sanandaj', 'سنندج', 7, 8, 'ایران ۵۱' ),
	66 => array( 'qazvin-city', 'قزوین', 7, 9, 'ایران ۷۹' ),
	67 => array( 'khorramabad', 'خرم‌آباد', 7, 9, 'ایران ۳۱' ),
	68 => array( 'gorgan', 'گرگان', 7, 9, 'ایران ۵۹' ),
	69 => array( 'sari', 'ساری', 7, 9, 'ایران ۶۲' ),
);
foreach ( $batch63 as $batch_id => $batch_row ) {
	list( $batch_slug, $batch_name, $batch_tips, $batch_places, $batch_plate ) = $batch_row;
	$GLOBALS['t']['posts'][ $batch_id ] = (object) array( 'ID' => $batch_id, 'post_type' => 'city', 'post_name' => $batch_slug, 'post_title' => $batch_name, 'post_status' => 'publish' );
	$GLOBALS['t']['meta'][ $batch_id ]   = array();
	$batch_data = sa_county_profile_data( $batch_id );
	sa_ci_assert( ! empty( $batch_data['has_insights'] ), "bundled: {$batch_slug} traveller block switches on from the shipped seed (2.22.63)" );
	sa_ci_assert( $batch_tips === count( $batch_data['local_tips'] ) && $batch_places === count( $batch_data['extra_places'] ), "bundled: {$batch_slug} tips and places parsed from the seed (2.22.63)" );
	sa_ci_assert( $batch_plate === $batch_data['plate_code'], "bundled: {$batch_slug} plate reaches tile 12 without meta (2.22.63)" );
	// خرم‌آباد: آبشار بیشه جزو شهرستان دورود است و نباید در رکورد این شهر باشد (تذکر مالک).
	if ( 'khorramabad' === $batch_slug ) {
		$batch_names = array();
		foreach ( $batch_data['extra_places'] as $batch_place ) {
			$batch_names[] = $batch_place['name'];
		}
		sa_ci_assert( false === strpos( implode( ' ', $batch_names ), 'بیشه' ), 'bundled: khorramabad carries no Bisheh place (Dorud county, owner note)' );
		sa_ci_assert( in_array( 'پارک جنگلی مخمل‌کوه', $batch_names, true ), 'bundled: khorramabad Mokhmal Kooh park is present instead of Bisheh (2.22.63)' );
	}
}

/* ---- 4j. Bundled seeds: قدس، کاشان و ملارد (2.22.65) --------------------- */

$batch65 = array(
	70 => array( 'qods', 'قدس', 6, 8, 'ایران ۲۱' ),
	71 => array( 'kashan', 'کاشان', 6, 9, 'ایران ۴۳' ),
	72 => array( 'malard', 'ملارد', 6, 8, 'ایران ۲۱' ),
);
foreach ( $batch65 as $batch_id => $batch_row ) {
	list( $batch_slug, $batch_name, $batch_tips, $batch_places, $batch_plate ) = $batch_row;
	$GLOBALS['t']['posts'][ $batch_id ] = (object) array( 'ID' => $batch_id, 'post_type' => 'city', 'post_name' => $batch_slug, 'post_title' => $batch_name, 'post_status' => 'publish' );
	$GLOBALS['t']['meta'][ $batch_id ]   = array();
	$batch_data = sa_county_profile_data( $batch_id );
	sa_ci_assert( ! empty( $batch_data['has_insights'] ), "bundled: {$batch_slug} traveller block switches on from the shipped seed (2.22.65)" );
	sa_ci_assert( $batch_tips === count( $batch_data['local_tips'] ) && $batch_places === count( $batch_data['extra_places'] ), "bundled: {$batch_slug} tips and places parsed from the seed (2.22.65)" );
	sa_ci_assert( $batch_plate === $batch_data['plate_code'], "bundled: {$batch_slug} plate reaches tile 12 without meta (2.22.65)" );
}

// هیچ رکوردی در دادهٔ همراه قالب نباید اصطلاحات فرایند تولید داشته باشد (قاعدهٔ مالک، AGENTS.md بند ۸).
$seed_src = (string) file_get_contents( $theme_dir . '/data/county-experiences.php' );
sa_ci_assert( false === strpos( $seed_src, 'بازبینی انسانی' ), 'bundled: seed carries no production-process wording (2.22.65)' );
sa_ci_assert( false === strpos( $seed_src, 'هوش مصنوعی' ), 'bundled: seed carries no AI wording (2.22.65)' );

/* ---- 4g. Fact badge fits its word vertically too (2.22.61) --------------- */

$css61 = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
$pos61 = strpos( $css61, 'v2.22.61' );
sa_ci_assert( false !== $pos61, 'css: v2.22.61 badge-fit block exists' );
$block61 = substr( $css61, (int) $pos61, 1600 );
sa_ci_assert( false !== strpos( $block61, '.sa-field-fact__label' ), 'css: access-fact badge restyled in the 2.22.61 block' );
sa_ci_assert( false !== strpos( $block61, 'align-self: start' ), 'css: access-fact badge never stretches tall in equal-height desktop rows (2.22.61)' );
sa_ci_assert( false !== strpos( $block61, 'align-content: start' ), 'css: access-fact card rows pack from the top (2.22.61)' );
sa_ci_assert( false !== strpos( $block61, 'width: fit-content' ), 'css: access-fact badge width fits its word on desktop too (2.22.61)' );

/* ---- 5. Template: human sourcing note ------------------------------------ */

$tpl = (string) file_get_contents( $theme_dir . '/template-parts/entity/county-profile.php' );
sa_ci_assert( false !== strpos( $tpl, 'id="county-field-notes"' ), 'template: block anchor exists for the TOC' );
sa_ci_assert( false !== strpos( $tpl, "sa_toc_register( 'از تجربهٔ مسافرها', 'county-field-notes', 6 )" ), 'template: TOC entry registered only with the block' );
sa_ci_assert( false !== strpos( $tpl, 'گوگل‌مپ، نشان، بلد' ), 'template: sourcing note names the review sources' );
sa_ci_assert( false !== strpos( $tpl, 'ممکن است بعضی جزئیات' ), 'template: note avoids fake certainty (owner voice)' );
sa_ci_assert( false !== strpos( $tpl, 'دوباره به‌روز می‌کنیم' ), 'template: note promises re-updates' );
sa_ci_assert( false !== strpos( $tpl, 'تیم سرزمین آریان' ), 'template: note credits the Sarzamin Aryan research team (2.22.58)' );
sa_ci_assert( false !== strpos( $tpl, 'sa-field-tip--' ), 'template: tip category colour classes rendered' );
sa_ci_assert( false !== strpos( $tpl, "'12 // LICENSE_PLATE'" ), 'template: license-plate tile registered in the identity card' );
sa_ci_assert( false !== strpos( $tpl, "'13 // AREA_CODE'" ), 'template: phone area-code tile registered in the identity card' );
sa_ci_assert( false !== strpos( $tpl, "'بهار' => 'spring'" ) && false !== strpos( $tpl, 'sa-field-season--' ), 'template: each season card carries its own colour class' );
sa_ci_assert( false === strpos( $tpl, 'county-attractions' ), 'template: attractions table under the identity card is removed (2.22.51)' );

$css58 = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
$pos58 = strpos( $css58, 'v2.22.58' );
sa_ci_assert( false !== $pos58, 'css: v2.22.58 label-style block exists' );
$block58 = substr( $css58, (int) $pos58, 2000 );
sa_ci_assert( false !== strpos( $block58, '.sa-field-fact__label' ), 'css: access-fact titles styled as labels (2.22.58)' );
sa_ci_assert( false !== strpos( $block58, 'rgba(214, 69, 69' ), 'css: access-fact labels are soft translucent red (2.22.58)' );
sa_ci_assert( false !== strpos( $block58, '.sa-field-place__kind' ), 'css: place-kind labels styled (2.22.58)' );
sa_ci_assert( false !== strpos( $block58, 'rgba(16, 54, 120' ), 'css: place-kind labels are soft translucent dark blue (2.22.58)' );

$css56 = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
foreach ( array( '.sa-field-season--spring', '.sa-field-season--summer', '.sa-field-season--autumn', '.sa-field-season--winter' ) as $cls ) {
	sa_ci_assert( false !== strpos( $css56, $cls ), "css: {$cls} has its own soft season colour (2.22.56)" );
}

$css = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
$pos = strpos( $css, 'v2.22.49 — بلوک «از تجربهٔ مسافرها»' );
sa_ci_assert( false !== $pos, 'css: v2.22.49 field-notes block exists' );
$block = substr( $css, (int) $pos, 6000 );
foreach ( array( '.sa-field-tip--warning', '.sa-field-tip--culture', '.sa-field-tip--logistics', '.sa-field-season--best' ) as $cls ) {
	sa_ci_assert( false !== strpos( $block, $cls ), "css: {$cls} styled" );
}

echo "PASS: county insights layer (14 canonical keys incl. plate+phone, bundled eight-city seeds, registry plate/province phone fallbacks, tips/places parsers, has_insights gate, human sourcing note, category colours)\n";
