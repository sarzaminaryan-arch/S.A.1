#!/usr/bin/env python3
"""سازندهٔ اسکریپت SQL اصلاح «تایپوی دامنه» در پایگاه‌دادهٔ وردپرس.

پیش‌زمینه (۲۰۲۶-۱۱-۲۷): در `wp_options` مقدار `home` و `siteurl` به‌جای دامنهٔ
واقعی `sarzaminaryan.ir` به‌صورت `sarzaminyaran.ir` (یک «y» اضافه) نوشته شده
بود. آن دامنه وجود ندارد (NXDOMAIN)، پس وردپرس هر بازدید را به آدرسی مرده
هدایت می‌کرد و سایت از دسترس خارج می‌شد.

این ابزار فقط **متن SQL را می‌سازد**؛ هیچ اتصالی به پایگاه‌داده ندارد، هیچ
دستوری اجرا نمی‌کند و هیچ تغییری روی سایت نمی‌دهد. اجرای SQL، تصمیم و کار
مالک در phpMyAdmin است (با پشتیبان‌گیری قبلی).

نکتهٔ امنیتی کلیدی: جایگزینی رشته‌ای با طول برابر انجام می‌شود
(`sarzaminyaran` → `sarzaminaryan`، هر دو ۱۳ نویسه)، پس طول مقدارهای
سریالایز شده (`a:2:{...}`) دست‌نخورده می‌ماند و ساختار آن‌ها خراب نمی‌شود.

کاربرد:
    python3 tools/ops/domain_typo_fix.py --print          # فقط نمایش
    python3 tools/ops/domain_typo_fix.py --out docs/ops/sql/domain-typo-fix.sql
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

WRONG = "sarzaminyaran"
RIGHT = "sarzaminaryan"
PREFIX = "IR_"

# هر هدف: (جدول، ستون مقدار، نام فارسی برای گزارش، شرط WHERE)
# شرط‌ها عمداً محدود نوشته شده‌اند تا فقط ردیف‌های لازم لمس شوند.
TARGETS: list[tuple[str, str, str, str]] = [
    (
        "options",
        "option_value",
        "آدرس سایت (اولین کاری که سایت را برمی‌گرداند)",
        "option_name IN ('siteurl','home')",
    ),
    (
        "options",
        "option_value",
        "نقشهٔ سایت لایت‌اسپید و اتصال Google Site Kit",
        "option_name IN ('litespeed.conf.crawler-sitemap','googlesitekit_search-console_settings')",
    ),
    (
        "postmeta",
        "meta_value",
        "نشانی منو، نقشهٔ اسکیما و پشتیبان‌های ایجنت",
        "meta_key IN ('_menu_item_url','sa_schema_contained_in','_sa_backup_json')",
    ),
    (
        "posts",
        "post_content",
        "لینک تصویرها و ناوبری داخل متن جاذبه/راهنما/صفحه",
        "post_type IN ('attraction','guide','page','wp_navigation','attachment')",
    ),
    (
        "users",
        "user_url",
        "آدرس سایت در پروفایل کاربر",
        "ID = 1",
    ),
]

# کلید اصلی هر جدول، برای دستور بازگردانی
PK = {"options": "option_id", "postmeta": "meta_id", "posts": "ID", "users": "ID",
      "iaa_reports": "id", "clarity_collect_events": "id"}

# این‌ها عمداً لمس نمی‌شوند و در سند توضیح داده شده‌اند.
SKIPPED = [
    ("posts", "post_content", "نسخه‌های بازبینی (post_type='revision')", "خودشان با ویرایش جدید جایگزین می‌شوند"),
    ("iaa_reports", "post_url", "گزارش‌های موتور ممیزی (اکتبر)", "تاریخی‌اند؛ با اجرای ممیزی تازه دوباره ساخته می‌شوند"),
    ("clarity_collect_events", "payload", "رخدادهای Clarity", "دادهٔ خام آنالیتیکس است و روی نمایش سایت اثر ندارد"),
]


def build_sql(prefix: str = PREFIX, wrong: str = WRONG, right: str = RIGHT, stamp: str = "20261127") -> str:
    assert len(wrong) == len(right), "جایگزینی باید هم‌طول باشد تا سریالایزها نشکنند"
    p = prefix
    L: list[str] = []
    A = L.append
    A("-- ============================================================================")
    A("-- اصلاح تایپوی دامنه در پایگاه‌دادهٔ وردپرس")
    A(f"-- {wrong}.ir  ⟶  {right}.ir   (هر دو {len(wrong)} نویسه — طول‌ها دست‌نخورده می‌مانند)")
    A("-- ساخته‌شده با tools/ops/domain_typo_fix.py — بازبینی‌شده و فقط‌خواندنی تا لحظهٔ اجرا")
    A("-- ============================================================================")
    A("")
    A("-- گام ۰ — پشتیبان. اگر این بخش خطا داد، هیچ UPDATE‌ای اجرا نکنید.")
    A("-- (هشدار: اگر جدول پشتیبان از قبل باشد، CREATE … IF NOT EXISTS آن را دست نمی‌زند؛")
    A("--  پس برای اجرای دوباره اول نام پسوند تاریخ را عوض کنید.)")
    seen = set()
    for table, col, label, where in TARGETS:
        if (table, col) in seen:
            continue
        seen.add((table, col))
        A(f"CREATE TABLE IF NOT EXISTS {p}bak{stamp}_{table}_{col} AS")
        A(f"  SELECT * FROM {p}{table} WHERE {col} LIKE '%{wrong}%';")
    A("")
    A("-- گام ۱ — پیش‌شمارش: چند ردیف قرار است تغییر کند؟ (قبل از UPDATE بخوانید)")
    for table, col, label, where in TARGETS:
        A(f"SELECT '{table}.{col}' AS target, COUNT(*) AS rows_to_fix FROM {p}{table}")
        A(f"  WHERE {col} LIKE '%{wrong}%' AND {where};")
    A("")
    A("-- گام ۲ — اصلاح. ترتیب مهم است: اول siteurl/home تا سایت فوراً برگردد.")
    for table, col, label, where in TARGETS:
        A(f"-- {label}")
        A(f"UPDATE {p}{table}")
        A(f"   SET {col} = REPLACE({col}, '{wrong}', '{right}')")
        A(f" WHERE {col} LIKE '%{wrong}%' AND {where};")
        A("")
    A("-- گام ۳ — راستی‌آزمایی: همهٔ این عددها باید صفر باشند.")
    for i, (table, col, label, where) in enumerate(TARGETS, 1):
        A(f"SELECT COUNT(*) AS remaining_{i}_{table} FROM {p}{table} WHERE {col} LIKE '%{wrong}%' AND {where};")
    A("")
    A("-- گام ۴ — تأیید مقدار نهایی آدرس سایت.")
    A(f"SELECT option_name, option_value FROM {p}options WHERE option_name IN ('siteurl','home');")
    A("")
    A("-- عمداً لمس نشد (توضیح در docs/ops/DOMAIN-TYPO-FIX-2026-11-27.md):")
    for table, col, label, why in SKIPPED:
        A(f"--   {p}{table}.{col} — {label} — {why}")
    A("")
    A("-- بازگردانی در صورت نیاز (هر ردیف پشتیبان را جای اصلی برمی‌گرداند):")
    restored = set()
    for table, col, label, where in TARGETS:
        if (table, col) in restored:
            continue
        restored.add((table, col))
        key = PK.get(table, "ID")
        A(f"-- UPDATE {p}{table} t JOIN {p}bak{stamp}_{table}_{col} b ON t.{key} = b.{key} SET t.{col} = b.{col};")
    return "\n".join(L) + "\n"


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description="ساخت SQL اصلاح تایپوی دامنه (بدون اجرا)")
    ap.add_argument("--prefix", default=PREFIX, help="پیشوند جداول (پیش‌فرض IR_)")
    ap.add_argument("--wrong", default=WRONG)
    ap.add_argument("--right", default=RIGHT)
    ap.add_argument("--stamp", default="20261127", help="پسوند نام جدول‌های پشتیبان")
    ap.add_argument("--out", type=Path, help="نوشتن در فایل (پیش‌فرض: فقط چاپ)")
    args = ap.parse_args(argv)

    if len(args.wrong) != len(args.right):
        print("خطا: طول رشتهٔ اشتباه و درست باید برابر باشد تا مقدارهای سریالایز نشکنند.", file=sys.stderr)
        return 2

    sql = build_sql(args.prefix, args.wrong, args.right, args.stamp)
    if args.out:
        args.out.parent.mkdir(parents=True, exist_ok=True)
        args.out.write_text(sql, encoding="utf-8")
        print(f"نوشته شد: {args.out}  ({len(sql.splitlines())} خط)")
    else:
        print(sql)
    return 0


if __name__ == "__main__":
    sys.exit(main())
