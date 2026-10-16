# تولید مقالات «جاذبه‌های شاخص داشبورد»

این پوشه ابزار ساخت و بازبینی مقالاتی را نگه می‌دارد که برای جاذبه‌های شاخص
داشبورد (`data/attractions.php`) در `data/ready-articles.php` نوشته می‌شوند.

## روش کار

۱. برای هر جاذبه، یک رکورد Python با کلیدهای زیر ساخته می‌شود: `slug`, `title`,
   `short_fa`, `short_en`, `english`, `city_en`, `province_en`, `province`,
   `province_name`, `city`, `city_name`, `kind`, `terms`, `excerpt`,
   `gallery_caption`, `gallery_alt`, `meta` و `faq` و `content`.
۲. تصویر هر جاذبه در `assets/img/articles/<slug>/01-drone-view.webp` قرار می‌گیرد
   (۱۰۸۰×۶۰۸، نوار سفید نام انگلیسی سایت در یک‌سوم پایینی و سمت راست).
۳. اجرای سازنده، رکوردها را پیش از `);` انتهای فایل به `data/ready-articles.php`
   اضافه می‌کند:

   ```bash
   python3 tools/attraction-articles/build_attraction_articles.py /tmp/batches/batchN.py
   ```

## قواعدی که سازنده تضمین می‌کند

* کلید هر رکورد با `slug` یکسان است (آزمون `tests/test-internal-links.php`).
* هیچ پیوند `/attraction/<slug>/`ی به رکوردی که هنوز نوشته نشده اشاره نمی‌کند؛
  پیوندهای بی‌مقصد خودکار به متن ساده تبدیل می‌شوند.
* هر مقاله دست‌کم چهار سرفصل `h2` دارد (آزمون `tests/test-toc.php`).
* `remove_featured_image => true` روی هر رکورد (سیاست پنهان‌بودن تصویر شاخص دیدنی‌ها).
* فایل تصویر هر رکورد پیش از نوشتن بررسی می‌شود.

## بازبینی ساختاری بدون PHP

چون در محیط توسعه PHP نصب نیست، اسکنر توکن‌محور زیر ساختار فایل را (رشته‌ها،
کامنت‌ها، heredoc/nowdoc و توازن پرانتزها) بررسی می‌کند:

```bash
python3 tools/attraction-articles/php_structure_check.py \
  wp-content/themes/sarzaminaryan-child/data/ready-articles.php
```
