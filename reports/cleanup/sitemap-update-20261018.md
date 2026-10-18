# به‌روزرسانی sitemap پس از پاک‌سازی (فاز ۸)

**دستور اجرا روی استیجینگ/تولید:**
```bash
wp eval-file tools/db-cleanup/sa-cleanup.php -- --only=kuhsorkh,kashmar,ahvaz-city
wp eval-file tools/db-cleanup/sa-cleanup.php  # اجرای واقعی همه (483 city + 31 province)
wp sitemap regenerate  # یا wp rocket purge + cloudflare purge
wp transient delete sa_sitemap_thin_city
curl -s https://sarzaminaryan.ir/wp-sitemap.xml | grep -o "<loc>[^<]*</loc>" | sort -u | wc -l
```

**نتیجه:**
- قبل از پاک‌سازی: `wp-sitemap.xml` شامل 612 URL (483 شهرستان + 31 استان + 38 دیدنی + 60 آرشیو/برچسب)
- بعد از `sa_seo_sitemap_exclude_thin_city` (مرحله ۵ فاز ۷ + فاز ۸): **589 URL** (23 شهرستان نازک حذف)
- 23 حذف‌شده = `reports/cleanup/noindex-list-20261018-180852.csv` (دلیل: `no_attractions` یا `thin <300 words`)

**نمونهٔ حذف‌شده‌ها:** `kuhsorkh`, `ahvaz-city`, `bam`, `khusf`, `sarayan`, `dargaz`, `jajarm`, ...

**تأیید noindex:**
```bash
curl -I https://staging.sarzaminaryan.ir/city/kuhsorkh/ | grep -i "X-Robots-Tag"
# X-Robots-Tag: noindex, follow
curl -I https://staging.sarzaminaryan.ir/city/mashhad/ | grep -i "X-Robots-Tag"
# (no tag) + Link: <...sitemap...> — index,follow
```

**رفتار خودکار پس از تکمیل محتوا:**
وقتی شهرستان `word_count >=300` و `has_attractions=true` شود، `sa_city_is_thin()==false` → `sa_seo_robots` به `index,follow` برمی‌گردد و transient پاک می‌شود → در sitemap بعدی خودکار برمی‌گردد.

