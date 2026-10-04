<?php
if (!defined('ABSPATH')) exit;
final class CC_Plugin {
 const DB_VERSION='2.11.0';
 public static function boot(){
  require_once CC_DIR.'includes/class-cc-db.php'; require_once CC_DIR.'includes/class-cc-auth.php'; require_once CC_DIR.'includes/class-cc-rating.php'; require_once CC_DIR.'includes/class-cc-contrib.php'; require_once CC_DIR.'includes/class-cc-rest.php'; require_once CC_DIR.'includes/class-cc-share.php'; require_once CC_DIR.'includes/class-cc-otp.php'; require_once CC_DIR.'includes/class-cc-admin.php'; require_once CC_DIR.'includes/class-cc-profile.php'; require_once CC_DIR.'includes/class-cc-media.php'; require_once CC_DIR.'includes/class-cc-moderation.php'; require_once CC_DIR.'includes/class-cc-roles.php'; require_once CC_DIR.'includes/class-cc-gamification.php'; require_once CC_DIR.'includes/class-cc-ui.php'; require_once CC_DIR.'includes/class-cc-dashboard.php';
  CC_DB::init(); CC_Gamification::init(); CC_Roles::init(); CC_Moderation::init(); CC_Profile::init(); CC_Share::init(); CC_OTP::init(); CC_Contrib::init(); CC_Auth::init(); CC_Rating::init(); CC_REST::init(); CC_Admin::init(); CC_Dashboard::init();
  add_action('wp_enqueue_scripts',array(__CLASS__,'assets'));
 }
 public static function maybe_install(){
  require_once CC_DIR.'includes/class-cc-db.php';
  if(get_option('cc_db_version')===self::DB_VERSION)return;
  CC_DB::install();
  if(!post_type_exists('cc_submission'))self::register_submission();
  update_option('cc_db_version',self::DB_VERSION);
  flush_rewrite_rules();
 }
 public static function register_submission(){ register_post_type('cc_submission',array('labels'=>array('name'=>__('مشارکت‌ها','city-contrib')),'public'=>false,'show_ui'=>true,'show_in_menu'=>true,'supports'=>array('title','author','editor'),'capability_type'=>'post','show_in_rest'=>false)); }
 public static function assets(){ if(is_singular('city')||is_front_page()||self::page_has_cc_shortcode()){ wp_enqueue_style('cc-style',CC_URL.'assets/css/city-contrib.css',array(),CC_VERSION); wp_enqueue_script('cc-script',CC_URL.'assets/js/city-contrib.js',array(),CC_VERSION,true); wp_localize_script('cc-script','CC_CONFIG',array('api'=>esc_url_raw(rest_url()),'nonce'=>wp_create_nonce('wp_rest'),'logged'=>is_user_logged_in())); } }
 public static function page_has_cc_shortcode(){if(!is_page())return false;$content=(string)get_post_field('post_content',get_queried_object_id());return has_shortcode($content,'cc_my_submissions')||has_shortcode($content,'cc_leaderboard');}
}
