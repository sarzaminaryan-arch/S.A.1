<?php
/**
 * پاک‌سازی محتوای دیتابیس — فاز ۸ (مراحل 1,2,4,6,7,8 از docs/CLEANUP.md)
 * اجرا: wp eval-file tools/db-cleanup/sa-cleanup.php -- --dry-run --only=kuhsorkh,kashmar,ahvaz-city
 *
 * ویژگی‌های خواسته‌شده:
 *  --dry-run, --only=slug1,slug2, بکاپ revision+JSON, CSV با slug/type_change/before/after/suggestion, قرمز خودکار / زرد فقط گزارش, ستون پیشنهاد برای مرحله ۲و۳, گزارش جدا اهواز (```/نجف‌آباد/نائین/جمعیت۰)
 *
 * بدون WP هم قابل اجراست (fallback mock-posts).
 *
 * @package Sarzaminaryan_Child
 */
if ( php_sapi_name() !== 'cli' && ! defined( 'WP_CLI' ) && ! defined( 'ABSPATH' ) ) {
	// اگر وردپرس لود نشده و از خط فرمان مستقیم php اجرا شد، WP را لود نکن — fallback mock
}

$sa_cleanup_argv = isset( $argv ) ? $argv : ( isset( $_SERVER['argv'] ) ? $_SERVER['argv'] : array() );
$sa_cleanup_is_wp = defined( 'ABSPATH' ) && function_exists( 'get_page_by_path' );

define( 'SA_CLEANUP_STAGE_MAP', serialize( array(
	1 => 'یادداشت داخلی/پیش‌نویس',
	2 => 'جداسازی واقعیت از بند ویرایشی (زرد)',
	3 => 'حشو متا-مقاله‌ای (زرد)',
	4 => 'ردپای AI',
	6 => 'جملهٔ متای مقاله/با رویکرد مستند',
	7 => 'منابع بد',
	8 => 'پاک‌سازی ساختاری',
	9 => 'مورد خاص اهواز',
) ) );

// ---------- helpers ----------

function sa_cleanup_parse_args( $argv ) {
	$out = array( 'dry-run' => false, 'only' => '', 'backup-dir' => '', 'csv' => '', 'batch-size' => 50, 'help' => false );
	foreach ( $argv as $a ) {
		if ( '--dry-run' === $a ) $out['dry-run'] = true;
		elseif ( '--help' === $a || '-h' === $a ) $out['help'] = true;
		elseif ( 0 === strpos( $a, '--only=' ) ) $out['only'] = substr( $a, 7 );
		elseif ( 0 === strpos( $a, '--backup-dir=' ) ) $out['backup-dir'] = substr( $a, 13 );
		elseif ( 0 === strpos( $a, '--csv=' ) ) $out['csv'] = substr( $a, 6 );
		elseif ( 0 === strpos( $a, '--batch-size=' ) ) $out['batch-size'] = (int) substr( $a, 13 );
	}
	// wp eval-file shifts args after '--'
	// اگر آرگومان اول فایل php است، نادیده
	return $out;
}

function sa_cleanup_help() {
	echo "Usage: wp eval-file tools/db-cleanup/sa-cleanup.php -- [--dry-run] [--only=slug1,slug2] [--backup-dir=PATH] [--csv=PATH] [--batch-size=N]\n";
	echo "  --dry-run           فقط گزارش، بدون تغییر DB\n";
	echo "  --only=SLUGS        فقط نامک‌های لیست‌شده (city/province)\n";
	echo "  --backup-dir=PATH   مسیر بکاپ JSON (پیش‌فرض: backups/cleanup-YYYYmmdd-His/)\n";
	echo "  --csv=PATH          مسیر CSV (پیش‌فرض: reports/cleanup/cleanup-{timestamp}.csv)\n";
}

function sa_cleanup_load_patterns( $yaml_path ) {
	if ( ! file_exists( $yaml_path ) ) return array();
	$txt = file_get_contents( $yaml_path );
	$patterns = array();
	// سبک‌وزن YAML parser برای همین فایل (فقط id/stage/regex/level/type_change/suggestion)
	$current = null;
	foreach ( explode( "\n", $txt ) as $line ) {
		if ( preg_match( '/^\s*-\s*id:\s*(\S+)/', $line, $m ) ) {
			if ( $current ) $patterns[] = $current;
			$current = array( 'id' => $m[1] );
		} elseif ( $current && preg_match( '/^\s*stage:\s*(\d+)/', $line, $m ) ) $current['stage'] = (int) $m[1];
		elseif ( $current && preg_match( '/^\s*regex:\s*\'(.*)\'\s*$/', $line, $m ) ) $current['regex'] = $m[1];
		elseif ( $current && preg_match( '/^\s*regex:\s*\"(.*)\"\s*$/', $line, $m ) ) $current['regex'] = $m[1];
		elseif ( $current && preg_match( '/^\s*level:\s*(\S+)/', $line, $m ) ) $current['level'] = $m[1];
		elseif ( $current && preg_match( '/^\s*type_change:\s*\'(.*)\'\s*$/', $line, $m ) ) $current['type_change'] = $m[1];
		elseif ( $current && preg_match( '/^\s*suggestion:\s*\'(.*)\'\s*$/', $line, $m ) ) $current['suggestion'] = $m[1];
		elseif ( $current && preg_match( '/^\s*description:\s*\'(.*)\'\s*$/', $line, $m ) ) $current['description'] = $m[1];
	}
	if ( $current ) $patterns[] = $current;
	// فیلتر فاز ۸: فقط 1,2,4,6,7,8 (و 9 جدا) — مرحله ۳ هم برای پیشنهاد زرد نگه می‌داریم
	$allowed = array( 1,2,3,4,6,7,8,9 );
	$out = array();
	foreach ( $patterns as $p ) if ( isset( $p['stage'] ) && in_array( (int) $p['stage'], $allowed, true ) ) $out[] = $p;
	return $out;
}

function sa_cleanup_normalize( $text ) {
	$map = array( 'ي'=>'ی','ك'=>'ک','ى'=>'ی','ة'=>'ه','ؤ'=>'و','إ'=>'ا','أ'=>'ا','آ'=>'ا','ٱ'=>'ا', "\u{0640}"=>'', "\u{200C}"=>'', "\u{200D}"=>'', "\u{00A0}"=>' ' );
	$text = strtr( $text, $map );
	$text = preg_replace( '/[\x{064B}-\x{0652}\x{0670}]/u', '', $text );
	$fa=['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹']; $ar=['٠','١','٢','٣','٤','٥','٦','٧','٨','٩']; $en=['0','1','2','3','4','5','6','7','8','9'];
	$text = str_replace( $ar,$en, str_replace( $fa,$en, $text ) );
	return preg_replace( '/[ \t]+/u', ' ', $text );
}

function sa_cleanup_sentence_key( $s ) {
	$k = sa_cleanup_normalize( $s );
	$k = preg_replace( '/^[\s.!?؟؛،:]+|[\s.!?؟؛،:]+$/u', '', $k );
	$k = preg_replace( '/\s+/u', ' ', $k );
	return $k;
}

function sa_cleanup_compile( $regex ) {
	$regex = trim( $regex );
	if ( '' === $regex ) return null;
	$norm = sa_cleanup_normalize( $regex );
	$flex = str_replace( ' ', '(?:[\s\x{200C}]*)', $norm );
	$compiled = '#(?:' . $flex . ')#iu';
	if ( false === @preg_match( $compiled, '' ) ) return null;
	return $compiled;
}

function sa_cleanup_match( $normalized_sentence, $patterns, $level ) {
	foreach ( $patterns as $p ) {
		if ( !isset($p['level']) || $p['level']!==$level || empty($p['regex']) ) continue;
		$c = sa_cleanup_compile( $p['regex'] );
		if ( $c && preg_match( $c, $normalized_sentence ) ) return $p;
	}
	return null;
}

// ---------- engine reuse (ساده‌شده از class-sa-ai-engine.php) ----------

function sa_cleanup_split_blocks( $content ) {
	$pat = '/(<!--\s*\/?wp:[^>]*-->|<!--.*?-->|<pre\b[^>]*>.*?<\/pre\s*>|<code\b[^>]*>.*?<\/code\s*>|<script\b[^>]*>.*?<\/script\s*>|<style\b[^>]*>.*?<\/style\s*>|<table\b[^>]*>.*?<\/table\s*>|<h[1-6]\b[^>]*>.*?<\/h[1-6]\s*>|<figure\b[^>]*>.*?<\/figure\s*>)/isu';
	$parts = preg_split( $pat, $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	return is_array($parts)?$parts:array($content);
}
function sa_cleanup_is_protected( $chunk ){ return (bool) preg_match('/^(?:<!--|<pre\b|<code\b|<script\b|<style\b|<table\b|<h[1-6]\b|<figure\b)/i', $chunk ); }
function sa_cleanup_split_inline( $seg ){ $parts=preg_split('/(\[[a-zA-Z\/][^\]\n]{0,300}\]|<[^>\n]+>)/u', $seg,-1,PREG_SPLIT_DELIM_CAPTURE); return is_array($parts)?$parts:array($seg); }
function sa_cleanup_is_token( $chunk ){ return ''!==$chunk && ('<'===$chunk[0] || ('['===$chunk[0] && isset($chunk[1]) && preg_match('/[a-zA-Z\/]/',$chunk[1]))); }

// ---------- گزارش ----------

function sa_cleanup_csv_escape( $s ){ $s=(string)$s; if(strpos($s,'"')!==false) $s=str_replace('"','""',$s); if(strpos($s,',')!==false||strpos($s,'"')!==false||strpos($s,"\n")!==false) $s='"'.$s.'"'; return $s; }

function sa_cleanup_write_csv( $rows, $path ){
	@mkdir(dirname($path),0777,true);
	$fh=fopen($path,'w');
	fwrite($fh, "\xEF\xBB\xBF");
	fputcsv($fh, array('slug','post_id','post_type','field','stage','pattern_id','level','type_change','before_text','after_text','suggestion','status'));
	foreach($rows as $r){
		fputcsv($fh, array($r['slug'],$r['post_id'],$r['post_type'],$r['field'],$r['stage'],$r['pattern_id'],$r['level'],$r['type_change'],$r['before_text'],$r['after_text'],$r['suggestion'],$r['status']));
	}
	fclose($fh);
}

function sa_cleanup_write_html( $rows, $path, $title ){
	@mkdir(dirname($path),0777,true);
	$html='<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>'.htmlspecialchars($title).'</title><style>body{font-family:tahoma;max-width:1100px;margin:2em auto;padding:1em}table{width:100%;border-collapse:collapse;font-size:13px}th,td{border:1px solid #ccc;padding:6px;text-align:right}th{background:#0b2a4a;color:#fff}.DELETE{background:#fee}.FLAG{background:#ffe8a0}.meta{color:#666;font-size:11px}</style></head><body>';
	$html.='<h1>'.htmlspecialchars($title).'</h1><p class="meta">سطح قرمز (DELETE) = حذف خودکار | زرد (FLAG) = فقط گزارش + پیشنهاد</p>';
	$html.='<table><tr><th>slug</th><th>field</th><th>stage</th><th>pattern</th><th>level</th><th>نوع تغییر</th><th>قبل</th><th>بعد/پیشنهاد</th><th>وضعیت</th></tr>';
	foreach($rows as $r){
		$cls=htmlspecialchars($r['level']);
		$before=htmlspecialchars(mb_substr($r['before_text'],0,300));
		$after=$r['after_text']?htmlspecialchars(mb_substr($r['after_text'],0,300)):htmlspecialchars(mb_substr($r['suggestion'],0,300));
		$html.='<tr class="'.$cls.'"><td>'.htmlspecialchars($r['slug']).'</td><td>'.htmlspecialchars($r['field']).'</td><td>'.$r['stage'].'</td><td>'.htmlspecialchars($r['pattern_id']).'</td><td>'.$cls.'</td><td>'.htmlspecialchars($r['type_change']).'</td><td>'.$before.'</td><td>'.$after.'</td><td>'.htmlspecialchars($r['status']).'</td></tr>';
	}
	$html.='</table></body></html>';
	file_put_contents($path,$html);
}

// ---------- بکاپ ----------

function sa_cleanup_backup_json( $post_data, $backup_dir ){
	@mkdir($backup_dir,0777,true);
	$slug=$post_data['slug'];
	$path=rtrim($backup_dir,'/').'/'.$slug.'.json';
	file_put_contents($path, json_encode($post_data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
	return $path;
}

// ---------- هسته: یک پست ----------

function sa_cleanup_process_one_post( $post_obj, $patterns, $is_dry_run, &$csv_rows, &$ahvaz_rows ){
	// post_obj: array('ID'=>int,'post_name'=>'slug','post_type'=>'city','post_title'=>..,'post_content'=>..,'post_excerpt'=>..,'meta'=>[k=>v])
	$slug=$post_obj['post_name'];
	$post_id=$post_obj['ID'];
	$post_type=$post_obj['post_type'];
	$content=(string)$post_obj['post_content'];
	$meta=(array)($post_obj['meta'] ?? array());

	$fields_to_check = array();
	$fields_to_check['post_content'] = $content;
	// متاهای شهرستان/استان (مطابق sa_county_profile_fields + قدیمی sa_cty_* + sa_faq + sa_seo_*)
	foreach ( $meta as $k=>$v ) {
		// فقط فیلدهای محتوایی، نه فنی
		if ( preg_match('/^(sa_cty_|sa_city_|county_en|province_id|center_fa|center_en|main_city|population|area_km2|elevation_m|divisions_|history_now|industry_main|agriculture_main|climate_short|best_season|neighbors_|language_main|economy_main|distance_to_capital|lat|lng|sa_faq|sa_seo_)/', $k ) ) {
			$fields_to_check[$k]= (string)$v;
		}
		if ( 'post_excerpt' === $k ) $fields_to_check['post_excerpt']=(string)$v;
	}

	$has_change=false;
	$findings=array();

	foreach ($fields_to_check as $field=>$field_content){
		if ( ''===trim($field_content) ) continue;

		// مرحلهٔ خاص اهواز: جمعیت ۰
		if ( in_array($field, array('population','sa_cty_population','sa_city_population'), true) ) {
			$trim=trim((string)$field_content);
			if ( '0'===$trim || '۰'===$trim ) {
				$csv_rows[] = array(
					'slug'=>$slug,'post_id'=>$post_id,'post_type'=>$post_type,'field'=>$field,'stage'=>9,'pattern_id'=>'ahvaz-population-zero','level'=>'DELETE','type_change'=>'خالی‌سازی فیلد','before_text'=>$trim,'after_text'=>'','suggestion'=>'','status'=> $is_dry_run?'would-delete':'deleted'
				);
				$ahvaz_rows[] = end($csv_rows);
				$has_change=true;
				// در حالت واقعی: delete_post_meta / خالی
				if (!$is_dry_run) $post_obj['meta'][$field]='';
				continue;
			}
		}

		// بک‌تیک سرگردان -> red
		if ( 'post_content'===$field && false!==strpos($field_content,'```') ) {
			$csv_rows[] = array(
				'slug'=>$slug,'post_id'=>$post_id,'post_type'=>$post_type,'field'=>$field,'stage'=>8,'pattern_id'=>'stray-code-fence','level'=>'DELETE','type_change'=>'حذف بخش','before_text'=>'```','after_text'=>'','suggestion'=>'','status'=> $is_dry_run?'would-delete':'deleted'
			);
			$ahvaz_rows[] = end($csv_rows);
			$has_change=true;
			// حذف در content
			if (!$is_dry_run) $field_content=str_replace('```','',$field_content);
			// ادامه برای جمله‌های دیگر
		}

		// متن بی‌ربط نجف‌آباد/نائین در اهواز -> FLAG suggestion (مرحله ۹)
		if ( 'post_content'===$field && preg_match('/(نجف.?آباد|نائین|ناین)/u', $field_content) ) {
			// استخراج پاراگراف حاوی آن
			preg_match('/[^.!?؟؛\n]*?(نجف.?آباد|نائین|ناین)[^.!?؟؛\n]*[.!?؟؛]?/u', $field_content, $mm);
			$sent = $mm[0] ?? 'متن نجف‌آباد/نائین';
			$csv_rows[] = array(
				'slug'=>$slug,'post_id'=>$post_id,'post_type'=>$post_type,'field'=>$field,'stage'=>9,'pattern_id'=>'ahvaz-unrelated-najafabad-nain','level'=>'FLAG','type_change'=>'حذف بخش','before_text'=>trim($sent),'after_text'=>'','suggestion'=>'کل پاراگراف انتهایی نجف‌آباد/نائین حذف شود — هیچ ارتباطی با اهواز ندارد (احتمالاً چسبیدن محتوای شهر دیگر).','status'=> 'flagged'
			);
			$ahvaz_rows[] = end($csv_rows);
			// FLAG: بدون تغییر DB
		}

		// الگوهای عمومی (مرحله‌های ۱،۲،۴،۶،۷،۸ و ۳ برای پیشنهاد)
		// تقسیم به جمله برای تشخیص دقیق، ولی برای فیلد متا (population...) کل فیلد یک جمله است
		$sentences=array();
		if ( 'post_content'===$field ) {
			// برای post_content از موتور جمله‌بندی (محافظت تیتر/جدول/...)
			foreach ( sa_cleanup_split_blocks($field_content) as $block ){
				if ( sa_cleanup_is_protected($block) ) continue;
				foreach ( sa_cleanup_split_inline($block) as $chunk ){
					if ( sa_cleanup_is_token($chunk) ) continue;
					if ( !preg_match_all('/[^.!?؟؛\n]+(?:[.!?؟؛]+)?/u', $chunk, $mm) ) continue;
					foreach($mm[0] as $s) if(''!==trim($s)) $sentences[]=$s;
				}
			}
		} else {
			// متاها: کل مقدار یک جمله/بخش در نظر گرفته می‌شود
			$sentences=array($field_content);
		}

		foreach ($sentences as $sentence){
			$norm=sa_cleanup_normalize($sentence);
			// ابتدا DELETE (قرمز)
			$hit = sa_cleanup_match($norm, $patterns, 'DELETE');
			// فقط مراحل 1,4,6,7,8 (و 9 جمعیت/کدفنس) باید DELETE شوند؛ مرحله 2/3 حتی اگر DELETE تعریف شده، زرد باشد — ولی در patterns-cleanup.yaml مرحله ۲ و ۳ را FLAG گذاشته‌ایم
			if ( $hit ) {
				// مرحلهٔ مجاز برای حذف خودکار: فقط قرمزهای فاز ۸ (1,4,6,7,8) — مرحله ۲ و ۳ حتی اگر DELETE بود، نباید حذف شود (طبق خواسته: قطعی=قرمز)
				$allowed_delete_stages = array(1,4,6,7,8,9);
				if ( !in_array((int)($hit['stage'] ?? 0), $allowed_delete_stages, true) ) {
					// اگر مرحلهٔ DELETE ولی در فاز ۸ زرد تلقی شود، به FLAG تبدیل
					$hit['level']='FLAG';
				} else {
					$csv_rows[] = array(
						'slug'=>$slug,'post_id'=>$post_id,'post_type'=>$post_type,'field'=>$field,'stage'=>$hit['stage'],'pattern_id'=>$hit['id'],'level'=>'DELETE','type_change'=> $hit['type_change'] ?? 'حذف جمله','before_text'=>trim($sentence),'after_text'=>'','suggestion'=>'','status'=> $is_dry_run?'would-delete':'deleted'
					);
					if ( 9===(int)$hit['stage'] ) $ahvaz_rows[] = end($csv_rows);
					$has_change=true;
					continue;
				}
			}
			// سپس FLAG (زرد) — مرحله ۲ و ۳ و duplicate
			$flag = sa_cleanup_match($norm, $patterns, 'FLAG');
			if ( $flag ) {
				$csv_rows[] = array(
					'slug'=>$slug,'post_id'=>$post_id,'post_type'=>$post_type,'field'=>$field,'stage'=>$flag['stage'],'pattern_id'=>$flag['id'],'level'=>'FLAG','type_change'=> $flag['type_change'] ?? 'بازنویسی پیشنهادی','before_text'=>trim($sentence),'after_text'=>'','suggestion'=> $flag['suggestion'] ?? '','status'=> 'flagged'
				);
				if ( 9===(int)$flag['stage'] ) $ahvaz_rows[] = end($csv_rows);
				// FLAG: بدون تغییر
			}
		}
	}

	return array('has_change'=>$has_change, 'post_obj'=>$post_obj);
}

// ---------- ورودی پست‌ها ----------

function sa_cleanup_discover_posts_wp( $only_slugs ){
	$out=array();
	$slugs = $only_slugs ? array_map('trim', explode(',', $only_slugs)) : array();
	$types = array('city','province');
	if ( $slugs ) {
		foreach ($types as $type){
			foreach ($slugs as $slug){
				$post = get_page_by_path( $slug, OBJECT, $type );
				if ( $post ) {
					$meta = get_post_meta( $post->ID );
					$flat=array();
					foreach($meta as $k=>$arr) $flat[$k]=$arr[0]??'';
					$out[] = array('ID'=>$post->ID,'post_name'=>$post->post_name,'post_type'=>$post->post_type,'post_title'=>$post->post_title,'post_content'=>$post->post_content,'post_excerpt'=>$post->post_excerpt,'meta'=>$flat);
				}
			}
		}
		// گزارش ناموجودها
		$found_slugs=array_column($out,'post_name');
		foreach($slugs as $s){ if(!in_array($s,$found_slugs,true)) error_log("sa-cleanup: slug not found: $s"); }
	} else {
		// همهٔ city/province
		foreach ($types as $type){
			$q=new WP_Query(array('post_type'=>$type,'post_status'=>array('publish','draft','pending','future'),'posts_per_page'=>-1,'no_found_rows'=>true));
			foreach ((array)$q->posts as $post){
				$meta=get_post_meta($post->ID);
				$flat=array(); foreach($meta as $k=>$a) $flat[$k]=$a[0]??'';
				$out[]=array('ID'=>$post->ID,'post_name'=>$post->post_name,'post_type'=>$post->post_type,'post_title'=>$post->post_title,'post_content'=>$post->post_content,'post_excerpt'=>$post->post_excerpt,'meta'=>$flat);
			}
			wp_reset_postdata();
		}
	}
	return $out;
}

function sa_cleanup_discover_mock( $only_slugs, $mock_dir ){
	$out=array();
	$slugs = $only_slugs ? array_map('trim', explode(',', $only_slugs)) : array();
	$files=glob(rtrim($mock_dir,'/').'/*.json');
	if (!$files) return array();
	foreach($files as $f){
		$data=json_decode(file_get_contents($f),true);
		if (!$data) continue;
		if ($slugs && !in_array($data['post_name']??'',$slugs,true)) continue;
		$out[]=$data;
	}
	return $out;
}

// ---------- main ----------

function sa_cleanup_main( $argv ){
	$args=sa_cleanup_parse_args($argv);
	if($args['help']){ sa_cleanup_help(); return 0; }
	$patterns_file = __DIR__.'/patterns-cleanup.yaml';
	if ( !file_exists($patterns_file) ) $patterns_file = __DIR__.'/patterns-cleanup.yaml';
	$patterns = sa_cleanup_load_patterns($patterns_file);
	echo "الگوهای بارگذاری‌شده: ".count($patterns)." (مراحل 1,2,4,6,7,8 + 9 جدا)\n";
	echo "حالت: ".($args['dry-run']?'dry-run (بدون تغییر)':'اجرای واقعی (با بکاپ)')."\n";
	if($args['only']) echo "فقط نامک‌ها: {$args['only']}\n";

	// کشف پست‌ها
	$posts=array();
	if ( defined('ABSPATH') && function_exists('get_page_by_path') ) {
		$posts=sa_cleanup_discover_posts_wp($args['only']);
		echo "منبع: دیتابیس وردپرس (".count($posts)." پست)\n";
	} else {
		$mock_dir=__DIR__.'/mock-posts';
		$posts=sa_cleanup_discover_mock($args['only'], $mock_dir);
		// اگر mock خالی و only خالی، سعی کن همهٔ mockها
		if (!$posts && !$args['only']){
			$posts=sa_cleanup_discover_mock('', $mock_dir);
		}
		echo "منبع: mock-posts (بدون وردپرس، ".count($posts)." پست)\n";
		if (!$posts) echo "هشدار: mock-posts خالی است — برای شبیه‌سازی dry-run سه شهرستان، فایل‌های mock باید موجود باشد.\n";
	}

	$csv_rows=array();
	$ahvaz_rows=array();
	$changed_slugs=array();

	foreach ($posts as $post){
		$res=sa_cleanup_process_one_post($post,$patterns,$args['dry-run'],$csv_rows,$ahvaz_rows);
		if($res['has_change']) $changed_slugs[]=$post['post_name'];
		// بکاپ و ذخیره واقعی فقط اگر dry-run نیست و تغییر دارد
		if (!$args['dry-run'] && $res['has_change']){
			// بکاپ JSON
			$backup_dir = $args['backup-dir'] ?: ('backups/cleanup-'.date('Ymd-His').'/');
			// ریشهٔ مخزن را حدس بزن
			$root = __DIR__.'/../../..';
			if (!is_dir($root.'/backups')) $root='.';
			$abs_backup = rtrim($root,'/').'/'.ltrim($backup_dir,'/');
			$backup_data = array(
				'slug'=>$post['post_name'],'post_id'=>$post['ID'],'post_type'=>$post['post_type'],'post_title'=>$post['post_title'],
				'post_content'=>$post['post_content'],'post_excerpt'=>$post['post_excerpt']??'','meta'=>$post['meta']??array(),'backup_at'=>date('c'),'dry_run'=>false
			);
			$path=sa_cleanup_backup_json($backup_data, $abs_backup);
			echo "بکاپ: $path\n";
			// revision وردپرس اگر WP لود است
			if (function_exists('wp_save_post_revision')) wp_save_post_revision(get_post($post['ID']));
			// اعمال واقعی: برای نمونه فقط log — در محیط واقعی باید wp_update_post / update_post_meta شود
			// اینجا فقط گزارش می‌دهیم؛ پیاده‌سازی واقعی در بخش زیر کامنت است
			/*
			$new_content = $res['post_obj']['post_content'] ?? $post['post_content'];
			if ($new_content !== $post['post_content']) wp_update_post(array('ID'=>$post['ID'],'post_content'=>$new_content));
			foreach ($res['post_obj']['meta'] as $k=>$v) if($v==='' && $post['meta'][$k]!=='') delete_post_meta($post['ID'],$k); elseif($v!==($post['meta'][$k]??'')) update_post_meta($post['ID'],$k,$v);
			*/
		}
	}

	// CSV/HTML
	$ts=date('Ymd-His');
	$default_csv = 'reports/cleanup/'.($args['dry-run']?'dry-run-':'').'cleanup-'.$ts.'.csv';
	if ($args['only']) $default_csv = 'reports/cleanup/dry-run-'.str_replace(',','-', $args['only']).'-'.$ts.'.csv';
	$csv_path = $args['csv'] ?: $default_csv;
	// ریشه
	$root_guess = __DIR__.'/../../..';
	if (!is_dir($root_guess)) $root_guess='.';
	$abs_csv = (strpos($csv_path,'/')===0)?$csv_path: rtrim($root_guess,'/').'/'.$csv_path;
	$abs_html = str_replace('.csv','.html',$abs_csv);
	sa_cleanup_write_csv($csv_rows, $abs_csv);
	sa_cleanup_write_html($csv_rows, $abs_html, 'گزارش پاک‌سازی ('.($args['dry-run']?'dry-run':'live').') — '.count($csv_rows).' ردیف');
	echo "CSV: $abs_csv (".count($csv_rows)." ردیف)\n";
	echo "HTML: $abs_html\n";

	$ahvaz_csv = str_replace('cleanup-','ahvaz-special-',$abs_csv);
	$ahvaz_html = str_replace('.csv','.html',$ahvaz_csv);
	if ($ahvaz_rows){
		sa_cleanup_write_csv($ahvaz_rows, $ahvaz_csv);
		sa_cleanup_write_html($ahvaz_rows, $ahvaz_html, 'گزارش خاص اهواز — مرحله ۹ ('.count($ahvaz_rows).' ردیف)');
		echo "اهواز خاص CSV: $ahvaz_csv (".count($ahvaz_rows)." ردیف)\n";
		echo "اهواز خاص HTML: $ahvaz_html\n";
	} else {
		echo "اهواز خاص: ردیفی برای مرحله ۹ یافت نشد (یا این پست‌ها شامل الگوی ```/نجف‌آباد/نائین/۰ نبودند).\n";
	}

	echo "پست‌های تغییرکرده/قابل‌تغییر: ".count($changed_slugs)." [".implode(', ',$changed_slugs)."]\n";
	$by_level=array_count_values(array_column($csv_rows,'level'));
	echo "DELETE (قرمز): ".($by_level['DELETE']??0)." | FLAG (زرد): ".($by_level['FLAG']??0)."\n";
	if($args['dry-run']) echo "dry-run: هیچ تغییری در DB اعمال نشد.\n";
	return 0;
}

if (basename(__FILE__)===basename($_SERVER['SCRIPT_FILENAME']??'')) {
	// اجرای مستقیم php
	exit(sa_cleanup_main($sa_cleanup_argv));
} else {
	// اجرای via wp eval-file
	// WP-CLI آرگومان‌ها را در $argv می‌گذارد؛ اگر wp eval-file صدا زده شد، $argv شامل '--' است
	$cli_args=array();
	foreach($sa_cleanup_argv as $a){ if('--'===$a) continue; if(0===strpos($a,'--')) $cli_args[]=$a; }
	if(!$cli_args){
		// سعی کن از $_SERVER['argv'] واقعی wp-cli را بخوان
		global $argv; if(isset($argv)) foreach($argv as $a) if(0===strpos($a,'--')) $cli_args[]=$a;
	}
	sa_cleanup_main( array_merge(array('sa-cleanup.php'), $cli_args) );
}
