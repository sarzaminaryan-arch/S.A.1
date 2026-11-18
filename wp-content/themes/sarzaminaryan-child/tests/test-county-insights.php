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
/* v2.22.94 — the people's own words travel in a fifth column, and a tip without
   one must not invent an empty quote block. */
$GLOBALS['t']['meta'][10]['local_tips'] = "warning | ترافیک برگشت | عصر جمعه مسیر شمال سنگین است.\n"
	. 'culture | چای خانهٔ بازار | صبح‌ها شلوغ است. | «صبح‌ها جای سوزن انداختن نیست» (google_maps، 2026-11-16)';
$rich2 = sa_county_profile_data( 10 );
sa_ci_assert( '' === (string) $rich2['local_tips'][0]['evidence'], 'insights: a tip without a quote carries no evidence text' );
sa_ci_assert( false !== strpos( (string) $rich2['local_tips'][1]['evidence'], 'google_maps' ), 'insights: the quote keeps its platform and date' );
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
sa_ci_assert( 6 === count( $tabriz['local_tips'] ) && 8 === count( $tabriz['extra_places'] ), 'bundled: Tabriz tips and places parsed from the seed (tram-under-construction place dropped in 2.22.71)' );
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
sa_ci_assert( 6 === count( $ahvaz['local_tips'] ) && 9 === count( $ahvaz['extra_places'] ), 'bundled: Ahvaz tips and places parsed from the seed (9 places since 2.22.86)' );
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
	70 => array( 'qods', 'قدس', 5, 8, 'ایران ۲۱' ),
	71 => array( 'kashan', 'کاشان', 6, 9, 'ایران ۴۳' ),
	72 => array( 'malard', 'ملارد', 5, 8, 'ایران ۲۱' ),
);
foreach ( $batch65 as $batch_id => $batch_row ) {
	list( $batch_slug, $batch_name, $batch_tips, $batch_places, $batch_plate ) = $batch_row;
	$GLOBALS['t']['posts'][ $batch_id ] = (object) array( 'ID' => $batch_id, 'post_type' => 'city', 'post_name' => $batch_slug, 'post_title' => $batch_name, 'post_status' => 'publish' );
	$GLOBALS['t']['meta'][ $batch_id ]   = array();
	$batch_data = sa_county_profile_data( $batch_id );
	sa_ci_assert( ! empty( $batch_data['has_insights'] ), "bundled: {$batch_slug} traveller block switches on from the shipped seed (2.22.65)" );
	sa_ci_assert( $batch_tips === count( $batch_data['local_tips'] ) && $batch_places === count( $batch_data['extra_places'] ), "bundled: {$batch_slug} tips and places parsed from the seed (2.22.65)" );
	sa_ci_assert( $batch_plate === $batch_data['plate_code'], "bundled: {$batch_slug} plate reaches tile 12 without meta (2.22.65)" );
	// ملارد: آتشکدهٔ تخت رستم در دهستان جوقینِ شهرستان شهریار است و نباید در رکورد ملارد باشد؛ شهریار هم ایستگاه مترو ندارد (2.22.66).
	if ( 'malard' === $batch_slug ) {
		$malard_text = json_encode( $batch_data, JSON_UNESCAPED_UNICODE );
		$malard_names = array();
		foreach ( $batch_data['extra_places'] as $batch_place ) {
			$malard_names[] = $batch_place['name'];
		}
		sa_ci_assert( false === strpos( implode( ' ', $malard_names ), 'تخت رستم' ), 'bundled: malard carries no Takht-e Rostam place (Shahriar county, 2.22.66)' );
		sa_ci_assert( in_array( 'تپه‌های ارسطو و بالکین و تخت کیکاووس', $malard_names, true ), 'bundled: malard keeps its own Takht-e Kaykavus instead (2.22.66)' );
		sa_ci_assert( false === strpos( (string) $malard_text, 'ایستگاه‌ها در شهریار' ), 'bundled: malard no longer claims metro stations in Shahriar (2.22.66)' );
	}
}

/* ---- 4j. Bundled seed: شهریار (2.22.67) ---------------------------------- */

$GLOBALS['t']['posts'][73] = (object) array( 'ID' => 73, 'post_type' => 'city', 'post_name' => 'shahriar', 'post_title' => 'شهریار', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][73]   = array();
$shahriar_data = sa_county_profile_data( 73 );
sa_ci_assert( ! empty( $shahriar_data['has_insights'] ), 'bundled: shahriar traveller block switches on from the shipped seed (2.22.67)' );
sa_ci_assert( 5 === count( $shahriar_data['local_tips'] ) && 9 === count( $shahriar_data['extra_places'] ), 'bundled: shahriar tips and places parsed from the seed (2.22.67)' );
sa_ci_assert( 'ایران ۲۱' === $shahriar_data['plate_code'], 'bundled: shahriar plate reaches tile 12 without meta (2.22.67)' );
$shahriar_names = array();
foreach ( $shahriar_data['extra_places'] as $shahriar_place ) {
	$shahriar_names[] = $shahriar_place['name'];
}
// تخت رستم جای درستش، شهرستان شهریار (دهستان جوقین) است؛ همان که در 2.22.66 از ملارد برداشته شد.
sa_ci_assert( in_array( 'آتشکدهٔ تخت رستم', $shahriar_names, true ), 'bundled: shahriar carries its own Takht-e Rostam fire temple (2.22.67)' );

/* ---- 4j. Bundled seed: دزفول (2.22.68) ----------------------------------- */

$GLOBALS['t']['posts'][74] = (object) array( 'ID' => 74, 'post_type' => 'city', 'post_name' => 'dezful', 'post_title' => 'دزفول', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][74]   = array();
$dezful_data = sa_county_profile_data( 74 );
sa_ci_assert( ! empty( $dezful_data['has_insights'] ), 'bundled: dezful traveller block switches on from the shipped seed (2.22.68)' );
sa_ci_assert( 7 === count( $dezful_data['local_tips'] ) && 10 === count( $dezful_data['extra_places'] ), 'bundled: dezful tips and places parsed from the seed (2.22.68)' );
sa_ci_assert( 'ایران ۲۴' === $dezful_data['plate_code'], 'bundled: dezful plate reaches tile 12 without meta (2.22.68)' );
$dezful_names = array();
foreach ( $dezful_data['extra_places'] as $dezful_place ) {
	$dezful_names[] = $dezful_place['name'];
}
sa_ci_assert( in_array( 'پل قدیم (ساسانی) دزفول', $dezful_names, true ), 'bundled: dezful keeps its Sasanian bridge place (2.22.68)' );
// شوش شهرستان جداست و نباید به‌عنوان جای دزفول بیاید.
sa_ci_assert( false === strpos( implode( ' ', $dezful_names ), 'شوش' ), 'bundled: dezful lists no Shush-county place (2.22.68)' );

/* ---- 4j. Bundled seeds: نیشابور، بابل، خمینی‌شهر و سبزوار (2.22.69) ------- */

$GLOBALS['t']['posts'][75] = (object) array( 'ID' => 75, 'post_type' => 'city', 'post_name' => 'neyshabur', 'post_title' => 'نیشابور', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][75]   = array();
$neyshabur_data = sa_county_profile_data( 75 );
sa_ci_assert( ! empty( $neyshabur_data['has_insights'] ), 'bundled: neyshabur traveller block switches on from the shipped seed (2.22.69)' );
sa_ci_assert( 6 === count( $neyshabur_data['local_tips'] ) && 9 === count( $neyshabur_data['extra_places'] ), 'bundled: neyshabur tips and places parsed from the seed (2.22.69)' );
sa_ci_assert( 'ایران ۳۲، ۳۶، ۷۴' === $neyshabur_data['plate_code'], 'bundled: neyshabur plate reaches tile 12 without meta (2.22.69)' );
$neyshabur_names = array();
foreach ( $neyshabur_data['extra_places'] as $neyshabur_place ) {
	$neyshabur_names[] = $neyshabur_place['name'];
}
sa_ci_assert( in_array( 'آرامگاه خیام', $neyshabur_names, true ), 'bundled: neyshabur keeps its Khayyam tomb place (2.22.69)' );
// قدمگاه حالا مرکز شهرستان زبرخان است و نباید جای نیشابور بیاید.
sa_ci_assert( false === strpos( implode( ' ', $neyshabur_names ), 'قدمگاه' ), 'bundled: neyshabur lists no Qadamgah (now Zebarkhan county) (2.22.69)' );

$GLOBALS['t']['posts'][76] = (object) array( 'ID' => 76, 'post_type' => 'city', 'post_name' => 'babol', 'post_title' => 'بابل', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][76]   = array();
$babol_data = sa_county_profile_data( 76 );
sa_ci_assert( ! empty( $babol_data['has_insights'] ), 'bundled: babol traveller block switches on from the shipped seed (2.22.69)' );
sa_ci_assert( 6 === count( $babol_data['local_tips'] ) && 10 === count( $babol_data['extra_places'] ), 'bundled: babol tips and places parsed from the seed (2.22.69)' );
sa_ci_assert( 'ایران ۷۲، ۸۲، ۹۲' === $babol_data['plate_code'], 'bundled: babol plate reaches tile 12 without meta (2.22.69)' );
$babol_names = array();
foreach ( $babol_data['extra_places'] as $babol_place ) {
	$babol_names[] = $babol_place['name'];
}
sa_ci_assert( in_array( 'تکیهٔ مقریکلا', $babol_names, true ), 'bundled: babol keeps its Moqrikola takyeh place (2.22.69)' );
// بابلسر و فریدونکنار شهرستان جدا هستند.
sa_ci_assert( false === strpos( implode( ' ', $babol_names ), 'بابلسر' ), 'bundled: babol lists no Babolsar-county place (2.22.69)' );

$GLOBALS['t']['posts'][77] = (object) array( 'ID' => 77, 'post_type' => 'city', 'post_name' => 'khomeyni-shahr', 'post_title' => 'خمینی‌شهر', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][77]   = array();
$khomeyni_shahr_data = sa_county_profile_data( 77 );
sa_ci_assert( ! empty( $khomeyni_shahr_data['has_insights'] ), 'bundled: khomeyni-shahr traveller block switches on from the shipped seed (2.22.69)' );
sa_ci_assert( 5 === count( $khomeyni_shahr_data['local_tips'] ) && 8 === count( $khomeyni_shahr_data['extra_places'] ), 'bundled: khomeyni-shahr tips and places parsed from the seed (2.22.69)' );
sa_ci_assert( 'ایران ۲۳، ۴۳، ۵۳' === $khomeyni_shahr_data['plate_code'], 'bundled: khomeyni-shahr plate reaches tile 12 without meta (2.22.69)' );
$khomeyni_shahr_names = array();
foreach ( $khomeyni_shahr_data['extra_places'] as $khomeyni_shahr_place ) {
	$khomeyni_shahr_names[] = $khomeyni_shahr_place['name'];
}
sa_ci_assert( in_array( 'مسجد جامع خوزان', $khomeyni_shahr_names, true ), 'bundled: khomeyni-shahr keeps its Khuzan mosque place (2.22.69)' );
// آتشگاه نشانی و ورودی‌اش از اصفهان است و جای خمینی‌شهر نیست.
sa_ci_assert( false === strpos( implode( ' ', $khomeyni_shahr_names ), 'آتشگاه' ), 'bundled: khomeyni-shahr lists no Atashgah place (entrance and address are Isfahan) (2.22.69)' );

$GLOBALS['t']['posts'][78] = (object) array( 'ID' => 78, 'post_type' => 'city', 'post_name' => 'sabzevar', 'post_title' => 'سبزوار', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][78]   = array();
$sabzevar_data = sa_county_profile_data( 78 );
sa_ci_assert( ! empty( $sabzevar_data['has_insights'] ), 'bundled: sabzevar traveller block switches on from the shipped seed (2.22.69)' );
sa_ci_assert( 5 === count( $sabzevar_data['local_tips'] ) && 9 === count( $sabzevar_data['extra_places'] ), 'bundled: sabzevar tips and places parsed from the seed (2.22.69)' );
sa_ci_assert( 'ایران ۳۲، ۳۶، ۴۲، ۷۴' === $sabzevar_data['plate_code'], 'bundled: sabzevar plate reaches tile 12 without meta (2.22.69)' );
$sabzevar_names = array();
foreach ( $sabzevar_data['extra_places'] as $sabzevar_place ) {
	$sabzevar_names[] = $sabzevar_place['name'];
}
sa_ci_assert( in_array( 'منارهٔ خسروگرد', $sabzevar_names, true ), 'bundled: sabzevar keeps its Khosrogerd minaret place (2.22.69)' );
// بیزه حالا جزو شهرستان داورزن است.
sa_ci_assert( false === strpos( implode( ' ', $sabzevar_names ), 'بیزه' ), 'bundled: sabzevar lists no Bizeh place (now Davarzan county) (2.22.69)' );

// هیچ رکوردی در دادهٔ همراه قالب نباید اصطلاحات فرایند تولید داشته باشد (قاعدهٔ مالک، AGENTS.md بند ۸).
$seed_src = (string) file_get_contents( $theme_dir . '/data/county-experiences.php' );
sa_ci_assert( false === strpos( $seed_src, 'بازبینی انسانی' ), 'bundled: seed carries no production-process wording (2.22.65)' );
sa_ci_assert( false === strpos( $seed_src, 'هوش مصنوعی' ), 'bundled: seed carries no AI wording (2.22.65)' );

/* ---- 4l. ده شهرستان قالب 2.22.79 (بازسازی دستهٔ ۵ + سه رکورد نگه‌داشته) ---- */

$sa79_rows = array(
	array( 80, 'baharestan', 'بهارستان', 6, 7, 'ایران ۷۸، ۲۱، ۳۸' ),
	array( 81, 'amol', 'آمل', 6, 11, 'ایران ۷۲، ۸۲، ۹۲' ),
	array( 82, 'pakdasht', 'پاکدشت', 7, 6, 'ایران ۷۸، ۲۱' ),
	array( 83, 'dargaz', 'درگز', 7, 10, 'ایران ۴۲' ),
	array( 84, 'sheshtamad', 'ششتمد', 6, 9, '' ),
	array( 85, 'davarzan', 'داورزن', 6, 10, '' ),
	array( 86, 'jovein', 'جوین', 6, 10, '' ),
	array( 87, 'khaf', 'خواف', 8, 9, 'ایران ۴۲' ),
	array( 88, 'kuhsorkh', 'کوهسرخ', 8, 14, 'ایران ۳۲' ),
	array( 89, 'bagh-e-malek', 'باغ‌ملک', 8, 14, 'ایران ۳۴' ),
);
foreach ( $sa79_rows as $sa79 ) {
	list( $sa79_id, $sa79_slug, $sa79_title, $sa79_tips, $sa79_places, $sa79_plate ) = $sa79;
	$GLOBALS['t']['posts'][ $sa79_id ] = (object) array( 'ID' => $sa79_id, 'post_type' => 'city', 'post_name' => $sa79_slug, 'post_title' => $sa79_title, 'post_status' => 'publish' );
	$GLOBALS['t']['meta'][ $sa79_id ]  = array();
	$sa79_data = sa_county_profile_data( $sa79_id );
	sa_ci_assert( ! empty( $sa79_data['has_insights'] ), "bundled: {$sa79_slug} traveller block switches on from the shipped seed (2.22.79)" );
	sa_ci_assert( 4 === count( $sa79_data['seasons'] ), "bundled: {$sa79_slug} four seasons reach the data layer (2.22.79)" );
	sa_ci_assert( $sa79_tips === count( $sa79_data['local_tips'] ) && $sa79_places === count( $sa79_data['extra_places'] ), "bundled: {$sa79_slug} tips and places parsed from the seed (2.22.79)" );
	sa_ci_assert( $sa79_plate === $sa79_data['plate_code'], "bundled: {$sa79_slug} plate reaches tile 12 (2.22.79)" );
	sa_ci_assert( '' !== (string) $sa79_data['people_character'], "bundled: {$sa79_slug} carries a positive people note (2.22.79)" );
}
$sa79_seed = (string) file_get_contents( $theme_dir . '/data/county-experiences.php' );
// جمعیت ۱۰۵٬۳۸۴ باغ‌ملک مال پیش از جدایی صیدون (۲۰۲۳) بود.
sa_ci_assert( false === strpos( $sa79_seed, '۱۰۵٬۳۸۴' ) && false !== strpos( $sa79_seed, '۸۳٬۱۲۹' ), 'bundled: bagh-e-malek uses the post-Seydun population (2.22.79)' );
sa_ci_assert( false === strpos( $sa79_seed, 'اسرائیل' ) && false === strpos( $sa79_seed, 'اسراییل' ), 'seed: banned country name absent (2.22.79)' );
sa_ci_assert( false === strpos( $sa79_seed, 'http' ), 'seed: places carry no outbound links; sources live in the article (2.22.79)' );
// 2.22.80 — زوزن روستای مسکونی است (۲٬۶۷۷ نفر، ۱۳۹۵)؛ «شهر ویران‌شدهٔ زوزن» محوطه‌ای جدا و ثبت‌شده است.
sa_ci_assert( false === strpos( $sa79_seed, 'متروکهٔ زوزن' ) && false === strpos( $sa79_seed, 'روستایی متروکه در دشت' ), 'seed: زوزن is no longer described as an abandoned village (2.22.80)' );

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

/* ---- 4k. فقط جاهای باز و جمله‌های تأییدشده؛ بی «راستش» (2.22.71) --------- */

$seed_src = (string) file_get_contents( $theme_dir . '/data/county-experiences.php' );
foreach ( array( 'در حال ساخت', 'در حال احداث', 'در حال تکمیل', 'در حال نهایی', 'در حال اجرا', 'به‌زودی', 'کلنگ', 'آمادهٔ افتتاح', 'هنوز کامل نیست' ) as $sa_future ) {
	sa_ci_assert( false === strpos( $seed_src, $sa_future ), "seed: no not-yet-open place or project ({$sa_future}) in the traveller data (2.22.71)" );
}
sa_ci_assert( false === strpos( $seed_src, 'راستش' ), 'seed: the filler word «راستش» is not repeated in the traveller data (2.22.71)' );
sa_ci_assert( false === strpos( $seed_src, 'غار دستکند قهرمان' ) && false === strpos( $seed_src, 'سند ۱۸ هکتار' ), 'seed: unverified/future Ladar sentences removed from khomeyni-shahr (2.22.71)' );
$GLOBALS['t']['posts'][79] = (object) array( 'ID' => 79, 'post_type' => 'city', 'post_name' => 'kashan', 'post_title' => 'کاشان', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][79]   = array();
$kashan71 = sa_county_profile_data( 79 );
$kashan71_names = array();
foreach ( $kashan71['extra_places'] as $kashan71_place ) {
	$kashan71_names[] = $kashan71_place['name'];
}
sa_ci_assert( in_array( 'بوستان فیروزی', $kashan71_names, true ), 'bundled: kashan lists the open Firuzi park without the unopened pavilion (2.22.71)' );

/* v2.22.145 — بلوک «تجربهٔ مسافرها» به سکشن مستقل خودش منتقل شد؛ تست همان
 * ادعاهای قبلی را روی فایل جدید بررسی می‌کند (نسخهٔ ۲.۲۲.۸۶ دست‌نخورده). */
$tpl = (string) file_get_contents( $theme_dir . '/template-parts/county/section-field-notes.php' );
sa_ci_assert( false !== strpos( $tpl, 'id="county-field-notes"' ), 'template: block anchor exists for the TOC' );
sa_ci_assert( false !== strpos( $tpl, "sa_toc_register( 'تجربهٔ مسافرها', 'county-field-notes', 6 )" ), 'template: TOC entry registered with the clean block title (2.22.86)' );
/* 2.22.86 (دستور مالک): بلوک تجربهٔ مسافرها عنوان تمیز دارد؛ توضیح زیر عنوان
 * و سطر «آخرین به‌روزرسانی این بخش» حذف شدند و زیر عنوان مستقیم کادرهای
 * اطلاعات، فصل‌ها، جاهای دیدنی شهرستان و نکته‌ها می‌آیند. */
sa_ci_assert( false !== strpos( $tpl, '<h2>تجربهٔ مسافرها</h2>' ), 'template: clean traveller-block title (2.22.86)' );
sa_ci_assert( false === strpos( $tpl, 'گوگل‌مپ، نشان، بلد' ), 'template: long sourcing note removed from the page (2.22.86)' );
sa_ci_assert( false === strpos( $tpl, 'ممکن است بعضی جزئیات' ), 'template: uncertainty note removed (2.22.86)' );
sa_ci_assert( false === strpos( $tpl, 'دوباره به‌روز می‌کنیم' ), 'template: re-update promise removed (2.22.86)' );
sa_ci_assert( false === strpos( $tpl, 'آخرین به‌روزرسانی این بخش' ), 'template: update-note line removed (2.22.86)' );
sa_ci_assert( false !== strpos( $tpl, 'sa-field-season__bar' ) && false !== strpos( $tpl, 'sa-field-season__en' ), 'template: season cards carry a bar with Persian right + English left (2.22.86)' );
sa_ci_assert( false !== strpos( $tpl, "'بهار' => 'Spring'" ) && false !== strpos( $tpl, "'زمستان' => 'Winter'" ), 'template: English season names mapped (2.22.86)' );
sa_ci_assert( false !== strpos( $tpl, 'جاهای دیدنی <?php echo esc_html( $sa_county_full ); ?>' ), 'template: places subsection retitled «جاهای دیدنی [شهرستان]» (2.22.86)' );
sa_ci_assert( false === strpos( $tpl, 'جاهایی که مردم زیاد می‌روند' ), 'template: old places heading gone (2.22.86)' );
sa_ci_assert( false === strpos( $tpl, 'کجا برویم؟ (مقصدهای منتخب شهرستان)' ), 'template: destinations POI box removed from under the identity card (2.22.86)' );
sa_ci_assert( false === strpos( $tpl, 'DESTINATIONS' ), 'template: no destinations POI box code left (2.22.86)' );
sa_ci_assert( false !== strpos( $tpl, "sa_county_siblings_html" ), 'template: sibling cities stashed for the end of the article (2.22.86)' );
sa_ci_assert( false !== strpos( $tpl, 'sa-field-tip--' ), 'template: tip category colour classes rendered' );
sa_ci_assert( false !== strpos( $tpl, "'12 // LICENSE_PLATE'" ), 'template: license-plate tile registered in the identity card' );
sa_ci_assert( false !== strpos( $tpl, "'13 // AREA_CODE'" ), 'template: phone area-code tile registered in the identity card' );
sa_ci_assert( false !== strpos( $tpl, "'بهار' => 'spring'" ) && false !== strpos( $tpl, 'sa-field-season--' ), 'template: each season card carries its own colour class' );
sa_ci_assert( false === strpos( $tpl, 'county-attractions' ), 'template: attractions table under the identity card is removed (2.22.51)' );
// 2.22.83 — نشان «فصل پیشنهادی» به خودِ فصل بسته است، نه به تطابق تصادفی رشته؛
// در عبارت بازه‌ای («آذر تا فروردین») هیچ فصل تنها نشان نمی‌گیرد.
sa_ci_assert( false !== strpos( $tpl, 'sa_is_range' ), 'template: best-season badge guards against range wording (2.22.83)' );
sa_ci_assert( false !== strpos( $tpl, '$sa_is_best   = ( ! $sa_is_range' ), 'template: badge requires the season to be named in the phrase and no range (2.22.83)' );

$css58 = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
$pos58 = strpos( $css58, 'v2.22.58' );
sa_ci_assert( false !== $pos58, 'css: v2.22.58 label-style block exists' );
$block58 = substr( $css58, (int) $pos58, 2000 );
sa_ci_assert( false !== strpos( $block58, '.sa-field-fact__label' ), 'css: access-fact titles styled as labels (2.22.58)' );
sa_ci_assert( false !== strpos( $block58, 'rgba(214, 69, 69' ), 'css: access-fact labels are soft translucent red (2.22.58)' );
sa_ci_assert( false !== strpos( $block58, '.sa-field-place__kind' ), 'css: place-kind labels styled (2.22.58)' );
sa_ci_assert( false !== strpos( $block58, 'rgba(16, 54, 120' ), 'css: place-kind labels are soft translucent dark blue (2.22.58)' );


/* ---- 4m. کامل بودن و پاکی هر ۵۰ رکورد قالب (2.22.82) -------------------- */

// قاعدهٔ مالک: هر صفحهٔ شهرستان که بلوک تجربه دارد باید همان قالب کامل را
// داشته باشد؛ رکورد نیم‌کاره یا رکوردی که در صفحه بلوک را نیمه‌کاره می‌کند
// اجازهٔ انتشار ندارد. این بخش کل فایل داده را یک‌جا می‌سنجد.

$sa81_src = (string) file_get_contents( $theme_dir . '/data/county-experiences.php' );
$sa81_map = (array) require $theme_dir . '/data/county-experiences.php';
sa_ci_assert( 50 === count( $sa81_map ), 'bundled 2.22.82: 50 county records ship in the seed' );
foreach ( array( 'aghajari', 'andika', 'dezpart', 'ramshir', 'zebarkhan', 'abadan' ) as $sa81_new ) {
	sa_ci_assert( isset( $sa81_map[ $sa81_new ] ), "bundled 2.22.82: {$sa81_new} entered the seed" );
}
$sa81_keys = array( 'road_quality', 'mobile_internet', 'access_difficulty', 'parking_status',
	'season_spring', 'season_summer', 'season_autumn', 'season_winter',
	'local_tips', 'extra_places', 'people_character', 'field_update_note' );
foreach ( $sa81_map as $sa81_slug => $sa81_row ) {
	foreach ( $sa81_keys as $sa81_key ) {
		sa_ci_assert( ! empty( $sa81_row[ $sa81_key ] ), "bundled 2.22.82: {$sa81_slug} fills {$sa81_key}" );
	}
	$sa81_tips = array_filter( explode( "\n", (string) $sa81_row['local_tips'] ) );
	sa_ci_assert( count( $sa81_tips ) >= 4, "bundled 2.22.82: {$sa81_slug} carries at least four local tips" );
	$sa81_places = array_filter( explode( "\n", (string) $sa81_row['extra_places'] ) );
	sa_ci_assert( count( $sa81_places ) >= 4, "bundled 2.22.82: {$sa81_slug} lists at least four extra places" );
}
foreach ( array( 'باید', 'راستش', 'http', 'به‌زودی', 'در حال ساخت', 'کلنگ', 'اسرائیل', 'اسراییل', 'بازبینی انسانی', 'هوش مصنوعی', 'منبع معتبر', 'فاقد منبع', 'بدون منبع', 'تأیید نمی‌شود', 'تایید نمی‌شود', 'بررسی بیشتر' ) as $sa81_bad ) {
	sa_ci_assert( false === strpos( $sa81_src, $sa81_bad ), "bundled 2.22.82: seed has no «{$sa81_bad}»" );
}

$css56 = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
foreach ( array( '.sa-field-season--spring', '.sa-field-season--summer', '.sa-field-season--autumn', '.sa-field-season--winter' ) as $cls ) {
	sa_ci_assert( false !== strpos( $css56, $cls ), "css: {$cls} has its own soft season colour (2.22.56)" );
}
$css86 = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
$pos86 = strpos( $css86, 'v2.22.86' );
sa_ci_assert( false !== $pos86, 'css: v2.22.86 season-card + siblings block exists' );
$block86 = substr( $css86, (int) $pos86, 4000 );
foreach ( array( 'border-top: 4px solid', '.sa-field-season__bar', '.sa-field-season__en', '.sa-siblings' ) as $css86_needle ) {
	sa_ci_assert( false !== strpos( $block86, $css86_needle ), "css: {$css86_needle} styled in the 2.22.86 block" );
}

$css = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
$pos = strpos( $css, 'v2.22.49 — بلوک «از تجربهٔ مسافرها»' );
sa_ci_assert( false !== $pos, 'css: v2.22.49 field-notes block exists' );
$block = substr( $css, (int) $pos, 6000 );
foreach ( array( '.sa-field-tip--warning', '.sa-field-tip--culture', '.sa-field-tip--logistics', '.sa-field-season--best' ) as $cls ) {
	sa_ci_assert( false !== strpos( $block, $cls ), "css: {$cls} styled" );
}

echo "PASS: county insights layer (14 canonical keys incl. plate+phone, bundled 50-city seeds, registry plate/province phone fallbacks, tips/places parsers, has_insights gate, human sourcing note, category colours)\n";
