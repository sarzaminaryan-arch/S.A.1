<?php
if (!defined('ABSPATH')) exit;
class CC_Gamification {
 public static function init(){}
 public static function award_badges($user){global $wpdb;$total=self::total($user);$count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cc_points_log WHERE user_id=%d",$user));$rules=array("first_contribution"=>($count>=1),"active_local"=>($total>=100),"local_guide"=>($total>=400),"city_ambassador"=>($total>=1000));foreach($rules as $key=>$ok){if($ok&&!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}cc_badges_users WHERE user_id=%d AND badge_key=%s",$user,$key)))$wpdb->insert($wpdb->prefix."cc_badges_users",array("user_id"=>$user,"badge_key"=>$key,"awarded_at"=>current_time("mysql")),array("%d","%s","%s"));}}
 public static function points_table(){global $wpdb;return $wpdb->prefix.'cc_points_log';}
 public static function award($user,$points,$reason,$submission=0){global $wpdb;$wpdb->insert(self::points_table(),array('user_id'=>$user,'points'=>$points,'reason'=>$reason,'submission_id'=>$submission,'created_at'=>current_time('mysql')),array('%d','%d','%s','%d','%s'));}
 public static function total($user){global $wpdb;return (int)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(points),0) FROM '.self::points_table().' WHERE user_id=%d',$user));}
 public static function level($n){return $n>=1000?'سفیر شهر':($n>=400?'راهنمای محلی':($n>=100?'ساکن فعال':'تازه‌وارد'));}
 public static function leaderboard($city=0,$limit=20){global $wpdb;$where=$city?'WHERE pm.meta_value=%d':'';$sql='SELECT p.user_id,SUM(p.points) total FROM '.self::points_table().' p '.($city?'JOIN '.$wpdb->usermeta.' pm ON pm.user_id=p.user_id AND pm.meta_key="cc_city_id" ':'').' GROUP BY p.user_id ORDER BY total DESC LIMIT '.absint($limit);$rows=$city?$wpdb->get_results($wpdb->prepare($sql,$city)):$wpdb->get_results($sql);return array_map(function($r){$u=get_user_by('id',$r->user_id);return array('user_id'=>(int)$r->user_id,'name'=>$u?$u->display_name:'کاربر','points'=>(int)$r->total,'level'=>self::level((int)$r->total));},$rows);}
}
