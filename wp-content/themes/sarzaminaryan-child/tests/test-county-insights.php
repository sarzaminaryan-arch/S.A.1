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
foreach ( array( 'road_quality', 'mobile_internet', 'access_difficulty', 'parking_status', 'season_spring', 'season_summer', 'season_autumn', 'season_winter', 'local_tips', 'extra_places', 'people_character', 'field_update_note' ) as $key ) {
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

$GLOBALS['t']['posts'][10] = (object) array( 'ID' => 10, 'post_type' => 'city', 'post_name' => 'tehran', 'post_title' => 'تهران', 'post_status' => 'publish' );
$GLOBALS['t']['meta'][10]   = array( 'center_fa' => 'تهران' );
$plain = sa_county_profile_data( 10 );
sa_ci_assert( empty( $plain['has_insights'] ), 'insights: classic-only county has no traveller block' );

$GLOBALS['t']['meta'][10]['road_quality']   = 'بزرگراه آسفالت تا خود شهر';
$GLOBALS['t']['meta'][10]['season_spring']  = 'اردیبهشت بهترین هواست.';
$GLOBALS['t']['meta'][10]['local_tips']     = 'warning | ترافیک برگشت | عصر جمعه مسیر شمال سنگین است.';
$GLOBALS['t']['meta'][10]['extra_places']   = 'بام نمونه | تفریحی | انتهای خیابان نمونه | غروب شلوغ است | ';
$GLOBALS['t']['meta'][10]['people_character'] = 'از تجربهٔ مسافرها، مردمش گرم و مهمان‌دوست‌اند.';
$rich = sa_county_profile_data( 10 );
sa_ci_assert( ! empty( $rich['has_insights'] ), 'insights: block switches on with real data' );
sa_ci_assert( 'بزرگراه آسفالت تا خود شهر' === $rich['access_facts']['وضعیت جاده'], 'insights: access fact labelled in Persian' );
sa_ci_assert( isset( $rich['seasons']['بهار'] ) && ! isset( $rich['seasons']['زمستان'] ), 'insights: only filled seasons listed' );
sa_ci_assert( 1 === count( $rich['local_tips'] ) && 1 === count( $rich['extra_places'] ), 'insights: parsed structures reach the data layer' );

/* ---- 5. Template: human sourcing note ------------------------------------ */

$tpl = (string) file_get_contents( $theme_dir . '/template-parts/entity/county-profile.php' );
sa_ci_assert( false !== strpos( $tpl, 'id="county-field-notes"' ), 'template: block anchor exists for the TOC' );
sa_ci_assert( false !== strpos( $tpl, "sa_toc_register( 'از تجربهٔ مسافرها', 'county-field-notes', 6 )" ), 'template: TOC entry registered only with the block' );
sa_ci_assert( false !== strpos( $tpl, 'گوگل‌مپ، نشان، بلد' ), 'template: sourcing note names the review sources' );
sa_ci_assert( false !== strpos( $tpl, 'ممکن است بعضی جزئیات' ), 'template: note avoids fake certainty (owner voice)' );
sa_ci_assert( false !== strpos( $tpl, 'دوباره به‌روز می‌کنیم' ), 'template: note promises re-updates' );
sa_ci_assert( false !== strpos( $tpl, 'sa-field-tip--' ), 'template: tip category colour classes rendered' );

$css = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
$pos = strpos( $css, 'v2.22.49 — بلوک «از تجربهٔ مسافرها»' );
sa_ci_assert( false !== $pos, 'css: v2.22.49 field-notes block exists' );
$block = substr( $css, (int) $pos, 6000 );
foreach ( array( '.sa-field-tip--warning', '.sa-field-tip--culture', '.sa-field-tip--logistics', '.sa-field-season--best' ) as $cls ) {
	sa_ci_assert( false !== strpos( $block, $cls ), "css: {$cls} styled" );
}

echo "PASS: county insights layer (12 canonical keys, tips/places parsers, has_insights gate, human sourcing note, category colours)\n";
