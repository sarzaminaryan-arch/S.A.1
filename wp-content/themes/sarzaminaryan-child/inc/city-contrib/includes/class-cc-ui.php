<?php
if (!defined('ABSPATH')) exit;
class CC_UI {
 public static function fa_num($n){return function_exists('sa_fa_digits')?sa_fa_digits((string)$n):number_format_i18n($n);}
 public static function stars_html($avg,$id=''){$full=max(0,min(7,(int)round((float)$avg)));if($avg<=0)return '<span class="cc-stars cc-stars--empty" aria-hidden="true">☆☆☆☆☆☆☆</span>';return '<span class="cc-stars" aria-hidden="true">'.str_repeat('★',$full).str_repeat('☆',7-$full).'</span>';}
 public static function viewer_key(){if(is_user_logged_in())return 'u'.get_current_user_id();$tok=isset($_COOKIE['cc_voter'])?preg_replace('/[^a-f0-9]/i','',wp_unslash($_COOKIE['cc_voter'])):'';if(strlen($tok)<16)return '';return 'c'.substr(hash('sha256',strtolower($tok).wp_salt('nonce')),0,40);}
 public static function rating_block(){
  if(!is_singular('city')||!class_exists('CC_Rating'))return;
  $id=get_the_ID();$d=CC_Rating::get($id,self::viewer_key());
  $rank_txt=$d['rank']>0?sprintf('رتبهٔ %s از %s شهرستان',self::fa_num($d['rank']),self::fa_num($d['total'])):'';
  echo '<section class="cc-block cc-rating" id="cc-rating" data-cc-rating data-city="'.esc_attr($id).'" aria-label="امتیاز کاربران به این شهر">';
  echo '<h2 class="cc-block__title">امتیاز کاربران به '.esc_html(get_the_title()).'</h2>';
  echo '<div class="cc-rating__row">';
  if($d['count']>0){echo '<p class="cc-rating__avg">'.self::stars_html($d['average']).' <b>'.esc_html(self::fa_num(number_format($d['average'],1))).'</b> از ۷ <span>· '.esc_html(self::fa_num($d['count'])).' رأی</span></p>';if($rank_txt)echo '<p class="cc-rating__rank">🏆 '.esc_html($rank_txt).'</p>';}
  else echo '<p class="cc-rating__avg cc-rating__avg--empty">هنوز رأیی ثبت نشده؛ اولین نفر باشید.</p>';
  echo '</div>';
  echo '<div class="cc-rating__picker">';
  echo '<p class="cc-rating__picker-label">ستارهٔ خود را انتخاب کنید (۱ تا ۷) و سپس «ثبت امتیاز» را بزنید:</p>';
  echo '<div class="cc-rating__stars" role="group" aria-label="انتخاب امتیاز از یک تا هفت">';
  for($i=1;$i<=7;$i++)echo '<button type="button" data-cc-star="'.$i.'" aria-label="امتیاز '.$i.' از ۷"'.($d['locked']?' disabled':'').'><span class="cc-rating__num">'.esc_html(self::fa_num($i)).'</span><span class="cc-rating__glyph" aria-hidden="true">★</span></button>';
  echo '</div>';
  echo '<button type="button" class="cc-btn cc-btn--primary cc-rating__submit" data-cc-submit'.($d['locked']?' hidden':' disabled').'>'.($d['user_rating']>0?'تغییر رأی':'ثبت امتیاز').'</button>';
  echo '</div>';
  if($d['user_rating']>0&&$d['locked'])$msg='✅ رأی شما: '.self::fa_num($d['user_rating']).' ستاره ثبت شده است. تا '.wp_date('j F Y',$d['changeable_at']).' نمی‌توانید آن را تغییر دهید.';
  elseif($d['user_rating']>0)$msg='رأی شما: '.self::fa_num($d['user_rating']).' ستاره است. می‌توانید آن را تغییر دهید — ستاره‌ای را لمس کنید.';
  else $msg='بدون ثبت‌نام امتیاز بدهید: ستاره‌ای را لمس کنید و دکمهٔ «ثبت امتیاز» را بزنید. پس از ثبت، تا ۲۴ ساعت قابل تغییر نیست.';
  echo '<p class="cc-rating__status'.(($d['user_rating']>0&&$d['locked'])?' cc-status--ok':'').'" data-cc-status role="status">'.esc_html($msg).'</p>';
  echo '</section>';
 }
 public static function contrib_block(){
  if(!is_singular('city'))return;
  $id=get_the_ID();$province=function_exists('sa_gallery_city_province_id')?sa_gallery_city_province_id($id):(int)get_post_meta($id,'sa_province_id',true);
  echo '<section class="cc-block cc-contrib" id="cc-contrib" data-cc-contrib>';
  echo '<h2 class="cc-block__title">نمای برتر شهر/شهرستان‌تان را ارسال کنید</h2>';
  echo '<p class="cc-contrib__hint">یک عکس مجاز از طبیعت، چشم‌انداز یا مکان ارزشمند شهر/شهرستان بفرستید. فقط نام مکان الزامی است؛ شهرستان و استان به‌صورت خودکار از همین صفحه ثبت می‌شود و تصویر پس از بررسی مدیر وارد آلبوم می‌شود.</p>';
  echo '<button type="button" class="cc-btn cc-btn--primary cc-contrib__open" data-cc-open>📷 ارسال عکس برای آلبوم</button>';
  echo '<form hidden data-cc-form><input type="hidden" name="city_id" value="'.esc_attr($id).'"><input type="hidden" name="province_id" value="'.esc_attr($province).'">';
  echo '<input type="hidden" name="type" value="photo">';
  echo '<div class="cc-place-grid"><label>نام مکان <input type="text" name="place_name" required maxlength="120" placeholder="مثلاً دریاچه گهر، آبشار بیشه یا تنگه... "></label>';
  echo '<label>شهرستان <input type="text" value="'.esc_attr(get_the_title($id)).'" readonly></label>';
  echo '<label>استان <input type="text" value="'.esc_attr($province?get_the_title($province):'').'" readonly></label></div>';
  echo '<label>نام فرستنده (اختیاری)<input type="text" name="contributor_name" maxlength="80" placeholder="اگر می‌خواهید کنار تصویر نمایش داده شود"></label>';
  echo '<label>یادداشت اختیاری برای مدیر<textarea name="text" maxlength="700" placeholder="اختیاری: فصل عکس، مسیر دسترسی یا نکته کوتاه. این متن به‌صورت خودکار زیر تصویر منتشر نمی‌شود."></textarea></label>';
  echo '<label>تصویر نمای برتر <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>';
  echo '<p class="cc-contrib__note">پس از ارسال، تصویر به WebP سبک تبدیل می‌شود، فایل خام حذف می‌شود، نشان sarzaminaryan می‌گیرد و تا تأیید مدیر در سایت نمایش داده نمی‌شود.</p>';
  echo '<label class="cc-consent"><input type="checkbox" name="rights_confirm" value="1" required> تأیید می‌کنم تصویر را خودم گرفته‌ام یا اجازهٔ انتشار آن را دارم و با نمایش آن در سرزمین آریان موافقم.</label>';
  echo '<input class="cc-hp" name="website" tabindex="-1" autocomplete="off">';
  echo '<button type="submit" class="cc-btn cc-btn--primary">ارسال برای بررسی</button> <button type="button" class="cc-btn cc-btn--ghost" data-cc-close>انصراف</button>';
  echo '<p data-cc-message role="status"></p></form>';
  $url=get_permalink();$text='نمای برتر شهر من را ببین و عکس بفرست: '.get_permalink();
  echo '<div class="cc-share" data-url="'.esc_attr($url).'" data-text="'.esc_attr($text).'"><span class="cc-share__label">این شهرستان را با دوستان خود به اشتراک بگذارید:</span><button type="button" data-cc-share>ارسال برای دوستان</button><button type="button" data-cc-copy>کپی لینک</button><a href="https://t.me/share/url?url='.rawurlencode($url).'&text='.rawurlencode($text).'" target="_blank" rel="noopener">تلگرام</a><a href="https://wa.me/?text='.rawurlencode($text).'" target="_blank" rel="noopener">واتساپ</a></div>';
  echo '</section>';
 }
 public static function top_cities_block($limit=7){
  $top=CC_Rating::top((int)$limit);
  echo '<section class="sa-topcities" id="sa-topcities" aria-label="هفت اقلیم برتر سرزمین آریان"><div class="sa-wrap">';
  echo '<div class="cc-topbox">';
  echo '<div class="cc-topbox__head"><img class="cc-topbox__logo" src="'.esc_url(CC_URL.'assets/img/logo.webp').'" alt="سرزمین آریایی ها" loading="lazy"><div class="cc-topbox__intro"><h2 class="cc-topbox__title">۷ اقلیم برتر سرزمین آریان</h2><p class="cc-topbox__desc">هفت شهر برتر ایران از نگاه مخاطبان وب‌سایت <b>سرزمین آریایی ها</b><span>مجموع ستاره‌های ۱ تا ۷ خوانندگان، بدون نیاز به ثبت‌نام</span></p></div></div>';
  if(empty($top)){echo '<p class="sa-topcities__empty">هنوز رأیی ثبت نشده است — نخستین نفر باشید! در صفحهٔ هر شهر ستارهٔ خود (۱ تا ۷) را انتخاب و ثبت کنید تا جدول اقلیم‌های برتر همین‌جا ساخته شود.</p>';}
  else{
   echo '<table class="cc-toptable"><thead><tr><th>رتبه</th><th>شهر</th><th>استان</th><th>امتیاز</th></tr></thead><tbody>';
   foreach($top as $i=>$c){
    echo '<tr>';
    echo '<td><span class="cc-toplist__num">'.esc_html(self::fa_num($c['rank'])).'</span></td>';
    echo '<td><a class="cc-toptable__city" href="'.esc_url($c['url']).'">'.esc_html($c['title']).'</a></td>';
    echo '<td>'.($c['province']?esc_html($c['province']):'—').'</td>';
    echo '<td class="cc-toptable__score"><b>'.esc_html(self::fa_num($c['total'])).'</b><em>('.esc_html(self::fa_num(number_format($c['avg'],1))).' از ۷)</em></td>';
    echo '</tr>';
   }
   echo '</tbody></table>';
   echo '<p class="cc-toptable__note">امتیاز هر شهر، مجموع ستاره‌های ۱ تا ۷ خوانندگان است؛ در امتیاز برابر، شهری که با رأی‌دهندهٔ کمتر به آن رسیده بالاتر می‌ایستد.</p>';
   if(count($top)<(int)$limit)echo '<p class="sa-topcities__empty">با رأی دادن به شهرهای دیگر، این جدول کامل‌تر می‌شود.</p>';
  }
  echo '</div></div></section>';
 }
}
