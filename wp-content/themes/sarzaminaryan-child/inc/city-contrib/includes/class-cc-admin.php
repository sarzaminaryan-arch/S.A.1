<?php
class CC_Admin {
 public static function init(){
  add_action('admin_init',function(){register_setting('cc_settings','cc_sms_endpoint',array('sanitize_callback'=>'esc_url_raw'));register_setting('cc_settings','cc_sms_key',array('sanitize_callback'=>'sanitize_text_field'));register_setting('cc_settings','cc_sms_sender',array('sanitize_callback'=>'sanitize_text_field'));});
  add_filter('manage_cc_submission_posts_columns',function($cols){return array('cb'=>'<input type="checkbox">','title'=>'عنوان','cc_city'=>'شهر','cc_type'=>'نوع','cc_image'=>'تصویر','cc_status'=>'وضعیت','date'=>'تاریخ');});
  add_action('manage_cc_submission_posts_custom_column',function($col,$id){if($col==='cc_city'){echo esc_html(get_the_title(get_post_meta($id,'cc_city_id',true)));}elseif($col==='cc_type'){echo esc_html(get_post_meta($id,'cc_type',true));}elseif($col==='cc_image'){$img=(int)get_post_meta($id,'cc_image_id',true);echo $img?'<span class="cc-chip cc-chip--img">تصویر دارد</span>':'—';}elseif($col==='cc_status'){$s=get_post_status($id);$labels=array('pending'=>'در انتظار بررسی','publish'=>'منتشر شده','rejected'=>'رد شده','needs_edit'=>'نیاز به اصلاح');$cls=array('pending'=>'warn','publish'=>'ok','rejected'=>'bad','needs_edit'=>'edit');echo '<span class="cc-chip cc-chip--'.esc_attr($cls[$s]??'').'">'.esc_html($labels[$s]??$s).'</span>';}},10,2);
 }
}
