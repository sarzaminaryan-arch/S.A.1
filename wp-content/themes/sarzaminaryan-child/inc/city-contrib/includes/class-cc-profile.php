<?php
if (!defined('ABSPATH')) exit;
class CC_Profile {
 public static function init(){add_shortcode('cc_my_submissions',array(__CLASS__,'shortcode'));add_shortcode('cc_leaderboard',array(__CLASS__,'leaderboard_shortcode'));}
 /* بارگذاری اسکریپت و تنظیمات این صفحه‌ها از نسخهٔ ۲٫۱۱ در CC_Plugin::assets انجام می‌شود تا هر دو شورت‌کد کار کنند. */
 public static function leaderboard_shortcode($atts){$atts=shortcode_atts(array('city_id'=>0,'limit'=>10),$atts);return '<section class="cc-block cc-leaderboard" data-cc-leaderboard data-city="'.esc_attr(absint($atts['city_id'])).'" data-limit="'.esc_attr(absint($atts['limit'])).'"><h2 class="cc-block__title">برترین مشارکت‌کنندگان</h2><div data-cc-leaderboard-list>در حال دریافت…</div></section>';}
 public static function shortcode(){if(!is_user_logged_in())return '<p class="cc-notice">برای دیدن مشارکت‌های خود ابتدا وارد شوید.</p>';return '<section class="cc-block cc-my-submissions" data-cc-my><h2 class="cc-block__title">مشارکت‌های من</h2><div data-cc-my-list>در حال دریافت…</div></section>';}
}
