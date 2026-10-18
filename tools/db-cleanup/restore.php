<?php
/**
 * بازگردانی بکاپ پاک‌سازی — هر JSON حاوی post_content + meta + revision
 * اجرا: wp eval-file tools/db-cleanup/restore.php -- --file=backups/cleanup-20261018-142000/ahvaz-city.json
 *       wp eval-file tools/db-cleanup/restore.php -- --file=backups/cleanup-20261018-142000/ahvaz-city.json --dry-run
 */
if (!defined('ABSPATH')) {
    // fallback CLI outside WP: just pretty print
    $file = null;
    foreach ($argv as $a) if (0===strpos($a,'--file=')) $file=substr($a,7);
    if ($file && file_exists($file)) {
        $data=json_decode(file_get_contents($file),true);
        echo "بکاپ: $file\nSlug: ".$data['slug']."\nپیش‌نمایش محتوا (۲۰۰ کاراکتر): ".mb_substr($data['post_content'],0,200)."\n";
        echo "متاهای بکاپ‌شده: ".implode(', ',array_keys($data['meta']??[]))."\n";
        echo "برای بازگردانی واقعی، با WP اجرا کنید: wp eval-file tools/db-cleanup/restore.php -- --file=$file\n";
        exit(0);
    }
    echo "Usage: php restore.php -- --file=backups/cleanup-.../slug.json [--dry-run]\n";
    exit(1);
}

$args=array('file'=>'','dry-run'=>false);
foreach ($argv as $a){
    if (0===strpos($a,'--file=')) $args['file']=substr($a,7);
    if ('--dry-run'===$a) $args['dry-run']=true;
}
if (!$args['file']||!file_exists($args['file'])){
    echo "فایل بکاپ پیدا نشد: {$args['file']}\n";
    exit(1);
}
$data=json_decode(file_get_contents($args['file']),true);
if (!$data|| empty($data['slug'])){
    echo "بکاپ نامعتبر است.\n"; exit(1);
}
$slug=$data['slug'];
$post=get_page_by_path($slug, OBJECT, array('city','province'));
if (!$post){
    echo "پست با نامک $slug در DB پیدا نشد — احتمالاً حذف شده است.\n"; exit(1);
}
echo "بازیابی $slug (ID {$post->ID}) از ".$args['file']."\n";
echo "قبل: ".mb_substr($post->post_content,0,120)."...\n";
echo "بعد (بکاپ): ".mb_substr($data['post_content'],0,120)."...\n";
if ($args['dry-run']){
    echo "dry-run: هیچ تغییری اعمال نشد.\n"; exit(0);
}
// revision قبل از بازگردانی
if (function_exists('wp_save_post_revision')) wp_save_post_revision($post);
wp_update_post(array('ID'=>$post->ID,'post_content'=>$data['post_content'],'post_excerpt'=>$data['post_excerpt']??''));
foreach ((array)($data['meta']??[]) as $k=>$v){
    if (''===$v) delete_post_meta($post->ID,$k);
    else update_post_meta($post->ID,$k,$v);
}
echo "بازگردانی انجام شد. Revision ساخته شد.\n";
