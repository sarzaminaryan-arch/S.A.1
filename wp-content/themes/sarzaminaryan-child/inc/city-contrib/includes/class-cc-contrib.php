<?php
class CC_Contrib {
 public static function init(){add_action('admin_post_cc_review_submission',array(__CLASS__,'review')); add_action('admin_post_nopriv_cc_review_submission',array(__CLASS__,'review'));}
 /* نما (markup) مشارکت از نسخهٔ ۲٫۱۱ به CC_UI::contrib_block منتقل شد و درجای درست صفحهٔ شهر
    (پیش از منابع) چاپ می‌شود؛ دیگر در فوتر رها نمی‌شود. */
 public static function review(){if(!current_user_can('edit_others_posts')||!wp_verify_nonce($_POST['_wpnonce']??'','cc_review'))wp_die('دسترسی غیرمجاز');$id=absint($_POST['submission_id']??0);$status=in_array($_POST['status']??'',array('pending','publish','rejected'),true)?$_POST['status']:'pending';wp_update_post(array('ID'=>$id,'post_status'=>$status));if(function_exists('sa_gallery_sync_contribution_image_status'))sa_gallery_sync_contribution_image_status($id,$status);wp_safe_redirect(wp_get_referer()?:admin_url());exit;}
}
