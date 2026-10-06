<?php
if (!defined('ABSPATH')) exit;
class CC_Dashboard {
 public static function init(){add_action('admin_menu',array(__CLASS__,'menu'));add_action('admin_enqueue_scripts',array(__CLASS__,'assets'));}
 public static function pending_count(){$q=new WP_Query(array('post_type'=>'cc_submission','post_status'=>'pending','posts_per_page'=>1,'no_found_rows'=>false,'fields'=>'ids'));return (int)$q->found_posts;}
 public static function menu(){$bubble=self::pending_count();$label='مشارکت مردمی'.($bubble?' <span class="awaiting-mod count-'.$bubble.'"><span class="pending-count">'.$bubble.'</span></span>':'');add_menu_page('مشارکت مردمی',$label,'edit_cc_submissions','city-contrib-dashboard',array(__CLASS__,'page'),'dashicons-star-filled',26);}
 public static function assets($hook){if($hook!=='toplevel_page_city-contrib-dashboard')return;wp_enqueue_style('cc-style',CC_URL.'assets/css/city-contrib.css',array(),CC_VERSION);}
 public static function fa($n){return function_exists('sa_fa_digits')?sa_fa_digits((string)$n):number_format_i18n($n);}
 public static function page(){
  if(!current_user_can('edit_cc_submissions'))wp_die('دسترسی غیرمجاز');
  $tab=isset($_GET['tab'])?sanitize_key($_GET['tab']):'stats';
  echo '<div class="wrap cc-dash"><h1>مشارکت مردمی «شهر من»</h1>';
  echo '<nav class="cc-dash__tabs">';
  foreach(array('stats'=>'📊 آمار و رتبه‌بندی','submissions'=>'✍️ مشارکت‌ها و تصاویر','settings'=>'⚙️ تنظیمات ارسال و پیامک') as $k=>$lb){$u=add_query_arg(array('page'=>'city-contrib-dashboard','tab'=>$k),admin_url('admin.php'));echo '<a class="cc-dash__tab'.($tab===$k?' is-active':'').'" href="'.esc_url($u).'">'.$lb.'</a>';}
  echo '</nav>';
  if($tab==='submissions')self::tab_submissions();elseif($tab==='settings')self::tab_settings();else self::tab_stats();
  echo '</div>';
 }
 public static function tab_stats(){
  global $wpdb;$t=CC_DB::table();
  $total_votes=(int)$wpdb->get_var("SELECT COUNT(*) FROM $t");
  $total_voters=(int)$wpdb->get_var("SELECT COUNT(DISTINCT voter_key) FROM $t");
  $rated_cities=count(CC_Rating::ranked_ids());
  $total_cities=CC_Rating::total_cities();
  $grand_avg=$total_votes?(float)$wpdb->get_var("SELECT AVG(stars) FROM $t"):0;
  $pending=self::pending_count();
  $with_image=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_type='cc_submission' AND m.meta_key='cc_image_id' AND m.meta_value+0>0");
  echo '<div class="cc-cards">';
  foreach(array(array('کل آرای ثبت‌شده',$total_votes,'dashicons-star-filled'),array('رأی‌دهندگان یکتا',$total_voters,'dashicons-groups'),array('میانگین امتیاز کل',number_format($grand_avg,1),'dashicons-chart-bar'),array('شهرهای دارای رأی / کل',$rated_cities.' / '.$total_cities,'dashicons-admin-home'),array('مشارکت در انتظار بررسی',$pending,'dashicons-visibility'),array('مشارکت همراه با تصویر',$with_image,'dashicons-format-image')) as $c){echo '<div class="cc-card"><span class="dashicons '.esc_attr($c[2]).'"></span><b>'.esc_html(self::fa($c[1])).'</b><span>'.esc_html($c[0]).'</span></div>';}
  echo '</div>';
  $top=CC_Rating::top(30);
  echo '<div class="cc-dash__cols"><div class="cc-panel"><h2>نمودار امتیاز ۳۰ شهر برتر</h2>';
  if(empty($top))echo '<p>هنوز رأیی ثبت نشده است.</p>';
  else{echo '<div class="cc-chart">';foreach($top as $c){$pct=$c['avg']>0?round($c['avg']/7*100):0;echo '<div class="cc-chart__row"><a href="'.esc_url(get_edit_post_link($c['id'])).'">'.esc_html($c['title']).'</a><span class="cc-chart__bar"><i style="width:'.esc_attr($pct).'%"></i></span><b>'.esc_html(self::fa(number_format($c['avg'],1))).'</b><em>'.esc_html(self::fa($c['count'])).' رأی</em></div>';}echo '</div>';}
  echo '</div><div class="cc-panel"><h2>راهنما</h2><ul class="cc-dash__help"><li>رتبهٔ هر شهر روی صفحهٔ همان شهر و کارت‌های صفحهٔ اصلی نمایش داده می‌شود.</li><li>هر بازدیدکننده بدون ثبت‌نام یک رأی ۱ تا ۷ دارد و فقط پس از ۲۴ ساعت می‌تواند آن را تغییر دهد.</li><li>برای بررسی مشارکت‌ها به زبانهٔ «مشارکت‌ها و تصاویر» بروید؛ موارد در انتظار با رنگ نارنجی مشخص‌اند.</li></ul></div></div>';
  $rows=CC_Rating::ranked_ids();
  echo '<div class="cc-panel"><h2>فهرست کامل شهرهای دارای رأی ('.self::fa(count($rows)).' شهر از '.self::fa($total_cities).' شهرستان)</h2>';
  echo '<p><input type="search" id="cc-rank-filter" class="regular-text" placeholder="جست‌وجوی نام شهر یا استان…"></p>';
  echo '<table class="widefat striped cc-rank-table"><thead><tr><th>رتبه</th><th>شهر</th><th>استان</th><th>میانگین</th><th>آرا</th><th>نمودار</th></tr></thead><tbody>';
  foreach($rows as $i=>$cid){$avg=(float)get_post_meta($cid,'cc_rating_avg',true);$cnt=(int)get_post_meta($cid,'cc_rating_count',true);$prov=CC_Rating::province_of($cid);$pct=$avg>0?round($avg/7*100):0;echo '<tr data-cc-row="'.esc_attr(strtolower($cid.'|'.sa_entity_display_name($cid,'').'|'.$prov)).'"><td>'.self::fa($i+1).'</td><td><a href="'.esc_url(get_edit_post_link($cid)).'">'.esc_html(sa_entity_display_name($cid,'')).'</a></td><td>'.esc_html($prov?:'—').'</td><td>'.esc_html(self::fa(number_format($avg,1))).'</td><td>'.esc_html(self::fa($cnt)).'</td><td><span class="cc-chart__bar cc-chart__bar--mini"><i style="width:'.esc_attr($pct).'%"></i></span></td></tr>';}
  echo '</tbody></table></div>';
  echo '<script>document.getElementById("cc-rank-filter").addEventListener("input",function(){var q=this.value.trim().toLowerCase();document.querySelectorAll("[data-cc-row]").forEach(function(tr){tr.style.display=tr.getAttribute("data-cc-row").indexOf(q)>-1?"":"none"})});</script>';
 }
 public static function tab_submissions(){
  $q=new WP_Query(array('post_type'=>'cc_submission','post_status'=>array('pending','publish','rejected','needs_edit'),'posts_per_page'=>100,'orderby'=>'date','order'=>'DESC'));
  if(!$q->have_posts()){echo '<div class="cc-panel"><p>هنوز مشارکتی ارسال نشده است.</p></div>';return;}
  $types=array('photo'=>'تصویر نمای برتر','place'=>'معرفی مکان/غذا','correction'=>'پیشنهاد اصلاح','report'=>'گزارش خطا','tip'=>'نکته محلی');
  echo '<div class="cc-panel"><table class="widefat cc-subm-table"><thead><tr><th>وضعیت</th><th>شهر</th><th>نوع</th><th>متن</th><th>تصویر</th><th>ارسال‌کننده</th><th>تاریخ</th><th>اقدام</th></tr></thead><tbody>';
  foreach($q->posts as $p){$st=$p->post_status;$row_cls=$st==='pending'?'cc-row--pending':($st==='publish'?'cc-row--ok':'');$labels=array('pending'=>'در انتظار بررسی','publish'=>'منتشر شده','rejected'=>'رد شده','needs_edit'=>'نیاز به اصلاح');$img=(int)get_post_meta($p->ID,'cc_image_id',true);$user=get_userdata($p->post_author);
   echo '<tr class="'.esc_attr($row_cls).'">';
   echo '<td><span class="cc-chip cc-chip--'.($st==='pending'?'warn':($st==='publish'?'ok':'bad')).'">'.esc_html($labels[$st]??$st).'</span></td>';
   echo '<td>'.esc_html(sa_entity_display_name((int)get_post_meta($p->ID,'cc_city_id',true),'')).'</td>';
   echo '<td>'.esc_html($types[get_post_meta($p->ID,'cc_type',true)]??'—').'</td>';
   echo '<td class="cc-subm-text">'.esc_html(wp_trim_words($p->post_content,25)).'</td>';
   echo '<td>'.($img?'<a href="'.esc_url(wp_get_attachment_url($img)).'" target="_blank" rel="noopener">'.wp_get_attachment_image($img,array(64,64)).'</a><span class="cc-chip cc-chip--img">تصویر</span>':'—').'</td>';
   echo '<td>'.esc_html($user?$user->display_name:'—').'</td>';
   echo '<td>'.esc_html(wp_date('Y/m/d',strtotime($p->post_date))).'</td>';
   echo '<td class="cc-subm-actions">';
   if($st!=='publish'){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline">'.wp_nonce_field('cc_review','_wpnonce',true,false).'<input type="hidden" name="action" value="cc_review_submission"><input type="hidden" name="submission_id" value="'.(int)$p->ID.'"><input type="hidden" name="status" value="publish"><button class="button button-primary">تأیید</button></form> ';}
   if($st!=='rejected'){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline">'.wp_nonce_field('cc_review','_wpnonce',true,false).'<input type="hidden" name="action" value="cc_review_submission"><input type="hidden" name="submission_id" value="'.(int)$p->ID.'"><input type="hidden" name="status" value="rejected"><button class="button">رد</button></form>';}
   echo '</td></tr>';}
  echo '</tbody></table></div>';
 }
 public static function tab_settings(){
  $max_files=class_exists('CC_Admin')?CC_Admin::upload_max_files():3;
  $max_mb=class_exists('CC_Admin')?CC_Admin::upload_max_mb():2;
  $daily=class_exists('CC_Admin')?CC_Admin::upload_daily_limit():3;
  $requires=class_exists('CC_Admin')&&CC_Admin::upload_requires_login();
  echo '<div class="cc-panel"><h2>تنظیمات ارسال تصویر شهروندان</h2><form method="post" action="'.esc_url(admin_url('options.php')).'">';
  settings_fields('cc_settings');
  echo '<table class="form-table">';
  echo '<tr><th>نیاز به ورود با شماره تلفن</th><td><label><input type="checkbox" name="cc_upload_requires_login" value="1" '.checked($requires,true,false).'> برای ارسال عکس، ورود سریع/شماره تلفن اجباری باشد</label><p class="description">فعلاً خاموش است؛ با خاموش بودن، کاربر مستقیم فرم آپلود را می‌بیند و تصویر پس از بررسی مدیر منتشر می‌شود.</p></td></tr>';
  echo '<tr><th><label for="cc_upload_max_files">تعداد تصویر در هر ارسال</label></th><td><input id="cc_upload_max_files" class="small-text" type="number" min="1" max="20" name="cc_upload_max_files" value="'.esc_attr($max_files).'"> <span>تصویر</span><p class="description">پیش‌فرض فعلی: ۳ تصویر. اگر بیشتر شود، فرم و API همان عدد را می‌پذیرند.</p></td></tr>';
  echo '<tr><th><label for="cc_upload_max_mb">حداکثر حجم هر تصویر</label></th><td><input id="cc_upload_max_mb" class="small-text" type="number" min="0.5" max="50" step="0.5" name="cc_upload_max_mb" value="'.esc_attr($max_mb).'"> <span>مگابایت</span><p class="description">پیش‌فرض فعلی: ۲ مگابایت برای هر فایل شهروند. تصویر بعد از آپلود به WebP سبک و واترمارک‌دار تبدیل می‌شود.</p></td></tr>';
  echo '<tr><th><label for="cc_upload_daily_limit">سقف روزانه هر کاربر/IP</label></th><td><input id="cc_upload_daily_limit" class="small-text" type="number" min="1" max="100" name="cc_upload_daily_limit" value="'.esc_attr($daily).'"> <span>تصویر در روز</span><p class="description">برای کاربران واردشده بر اساس حساب، و برای مهمان‌ها بر اساس شناسه امن مرورگر/IP محاسبه می‌شود.</p></td></tr>';
  echo '</table><hr><h2>تنظیمات پیامکِ ورود</h2>';
  echo '<table class="form-table"><tr><th>آدرس API پیامک</th><td><input class="regular-text" name="cc_sms_endpoint" value="'.esc_attr(get_option('cc_sms_endpoint','')).'"><p class="description">اگر خالی باشد، کدهای ورود به‌جای پیامک در گزارش خطا (debug log) ثبت می‌شوند. با خاموش بودن ورود اجباری، این بخش فقط برای امکانات حساب کاربری/مشارکت‌های من استفاده می‌شود.</p></td></tr>';
  echo '<tr><th>کلید API</th><td><input class="regular-text" type="password" name="cc_sms_key" value="'.esc_attr(get_option('cc_sms_key','')).'"></td></tr>';
  echo '<tr><th>شماره/نام فرستنده</th><td><input class="regular-text" name="cc_sms_sender" value="'.esc_attr(get_option('cc_sms_sender','')).'"></td></tr></table>';
  submit_button('ذخیره تنظیمات');
  echo '</form></div>';
 }
}
