-- ============================================================================
-- اصلاح تایپوی دامنه در پایگاه‌دادهٔ وردپرس
-- sarzaminyaran.ir  ⟶  sarzaminaryan.ir   (هر دو 13 نویسه — طول‌ها دست‌نخورده می‌مانند)
-- ساخته‌شده با tools/ops/domain_typo_fix.py — بازبینی‌شده و فقط‌خواندنی تا لحظهٔ اجرا
-- ============================================================================

-- گام ۰ — پشتیبان. اگر این بخش خطا داد، هیچ UPDATE‌ای اجرا نکنید.
-- (هشدار: اگر جدول پشتیبان از قبل باشد، CREATE … IF NOT EXISTS آن را دست نمی‌زند؛
--  پس برای اجرای دوباره اول نام پسوند تاریخ را عوض کنید.)
CREATE TABLE IF NOT EXISTS IR_bak20261127_options_option_value AS
  SELECT * FROM IR_options WHERE option_value LIKE '%sarzaminyaran%';
CREATE TABLE IF NOT EXISTS IR_bak20261127_postmeta_meta_value AS
  SELECT * FROM IR_postmeta WHERE meta_value LIKE '%sarzaminyaran%';
CREATE TABLE IF NOT EXISTS IR_bak20261127_posts_post_content AS
  SELECT * FROM IR_posts WHERE post_content LIKE '%sarzaminyaran%';
CREATE TABLE IF NOT EXISTS IR_bak20261127_users_user_url AS
  SELECT * FROM IR_users WHERE user_url LIKE '%sarzaminyaran%';

-- گام ۱ — پیش‌شمارش: چند ردیف قرار است تغییر کند؟ (قبل از UPDATE بخوانید)
SELECT 'options.option_value' AS target, COUNT(*) AS rows_to_fix FROM IR_options
  WHERE option_value LIKE '%sarzaminyaran%' AND option_name IN ('siteurl','home');
SELECT 'options.option_value' AS target, COUNT(*) AS rows_to_fix FROM IR_options
  WHERE option_value LIKE '%sarzaminyaran%' AND option_name IN ('litespeed.conf.crawler-sitemap','googlesitekit_search-console_settings');
SELECT 'postmeta.meta_value' AS target, COUNT(*) AS rows_to_fix FROM IR_postmeta
  WHERE meta_value LIKE '%sarzaminyaran%' AND meta_key IN ('_menu_item_url','sa_schema_contained_in','_sa_backup_json');
SELECT 'posts.post_content' AS target, COUNT(*) AS rows_to_fix FROM IR_posts
  WHERE post_content LIKE '%sarzaminyaran%' AND post_type IN ('attraction','guide','page','wp_navigation','attachment');
SELECT 'users.user_url' AS target, COUNT(*) AS rows_to_fix FROM IR_users
  WHERE user_url LIKE '%sarzaminyaran%' AND ID = 1;

-- گام ۲ — اصلاح. ترتیب مهم است: اول siteurl/home تا سایت فوراً برگردد.
-- آدرس سایت (اولین کاری که سایت را برمی‌گرداند)
UPDATE IR_options
   SET option_value = REPLACE(option_value, 'sarzaminyaran', 'sarzaminaryan')
 WHERE option_value LIKE '%sarzaminyaran%' AND option_name IN ('siteurl','home');

-- نقشهٔ سایت لایت‌اسپید و اتصال Google Site Kit
UPDATE IR_options
   SET option_value = REPLACE(option_value, 'sarzaminyaran', 'sarzaminaryan')
 WHERE option_value LIKE '%sarzaminyaran%' AND option_name IN ('litespeed.conf.crawler-sitemap','googlesitekit_search-console_settings');

-- نشانی منو، نقشهٔ اسکیما و پشتیبان‌های ایجنت
UPDATE IR_postmeta
   SET meta_value = REPLACE(meta_value, 'sarzaminyaran', 'sarzaminaryan')
 WHERE meta_value LIKE '%sarzaminyaran%' AND meta_key IN ('_menu_item_url','sa_schema_contained_in','_sa_backup_json');

-- لینک تصویرها و ناوبری داخل متن جاذبه/راهنما/صفحه
UPDATE IR_posts
   SET post_content = REPLACE(post_content, 'sarzaminyaran', 'sarzaminaryan')
 WHERE post_content LIKE '%sarzaminyaran%' AND post_type IN ('attraction','guide','page','wp_navigation','attachment');

-- آدرس سایت در پروفایل کاربر
UPDATE IR_users
   SET user_url = REPLACE(user_url, 'sarzaminyaran', 'sarzaminaryan')
 WHERE user_url LIKE '%sarzaminyaran%' AND ID = 1;

-- گام ۳ — راستی‌آزمایی: همهٔ این عددها باید صفر باشند.
SELECT COUNT(*) AS remaining_1_options FROM IR_options WHERE option_value LIKE '%sarzaminyaran%' AND option_name IN ('siteurl','home');
SELECT COUNT(*) AS remaining_2_options FROM IR_options WHERE option_value LIKE '%sarzaminyaran%' AND option_name IN ('litespeed.conf.crawler-sitemap','googlesitekit_search-console_settings');
SELECT COUNT(*) AS remaining_3_postmeta FROM IR_postmeta WHERE meta_value LIKE '%sarzaminyaran%' AND meta_key IN ('_menu_item_url','sa_schema_contained_in','_sa_backup_json');
SELECT COUNT(*) AS remaining_4_posts FROM IR_posts WHERE post_content LIKE '%sarzaminyaran%' AND post_type IN ('attraction','guide','page','wp_navigation','attachment');
SELECT COUNT(*) AS remaining_5_users FROM IR_users WHERE user_url LIKE '%sarzaminyaran%' AND ID = 1;

-- گام ۴ — تأیید مقدار نهایی آدرس سایت.
SELECT option_name, option_value FROM IR_options WHERE option_name IN ('siteurl','home');

-- عمداً لمس نشد (توضیح در docs/ops/DOMAIN-TYPO-FIX-2026-11-27.md):
--   IR_posts.post_content — نسخه‌های بازبینی (post_type='revision') — خودشان با ویرایش جدید جایگزین می‌شوند
--   IR_iaa_reports.post_url — گزارش‌های موتور ممیزی (اکتبر) — تاریخی‌اند؛ با اجرای ممیزی تازه دوباره ساخته می‌شوند
--   IR_clarity_collect_events.payload — رخدادهای Clarity — دادهٔ خام آنالیتیکس است و روی نمایش سایت اثر ندارد

-- بازگردانی در صورت نیاز (هر ردیف پشتیبان را جای اصلی برمی‌گرداند):
-- UPDATE IR_options t JOIN IR_bak20261127_options_option_value b ON t.option_id = b.option_id SET t.option_value = b.option_value;
-- UPDATE IR_postmeta t JOIN IR_bak20261127_postmeta_meta_value b ON t.meta_id = b.meta_id SET t.meta_value = b.meta_value;
-- UPDATE IR_posts t JOIN IR_bak20261127_posts_post_content b ON t.ID = b.ID SET t.post_content = b.post_content;
-- UPDATE IR_users t JOIN IR_bak20261127_users_user_url b ON t.ID = b.ID SET t.user_url = b.user_url;
