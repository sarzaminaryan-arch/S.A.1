"""نرمال‌سازی فارسی فقط برای *پیدا کردن* جمله‌ها — متن خروجی هرگز با متن
نرمال‌شده جایگزین نمی‌شود و همیشه همان متن اصلی است.

قواعد (طبق قانون اصلی مالک):
- «ي/ك» عربی = «ی/ک» فارسی (و چند حرف هم‌خانواده مثل «ى/ة/أ/إ/آ»)
- نیم‌فاصله (ZWNJ)، فاصله و نبودِ فاصله یکسان‌اند («می‌کند» = «میکند» = «می کند»)
- اعراب، تشدید و کشیده «ـ» نادیده گرفته می‌شوند
- اعداد فارسی، عربی و انگلیسی یکسان‌اند
"""
from __future__ import annotations

import re
import unicodedata

_CHAR_MAP = str.maketrans(
    {
        "ي": "ی",
        "ك": "ک",
        "ى": "ی",
        "ة": "ه",
        "ؤ": "و",
        "إ": "ا",
        "أ": "ا",
        "آ": "ا",
        "ٱ": "ا",
        "\u0640": "",  # کشیده (tatweel)
        "\u200c": "",  # ZWNJ: چسباندن، تا «می‌کند» و «میکند» یکی شوند
        "\u200d": "",  # ZWJ
        "\u00a0": " ",  # NBSP
    }
)

# اعراب و تشدید
_DIACRITICS = re.compile(r"[\u064b-\u0652\u0670]")

# رقم‌های فارسی (۰-۹) و عربی (٠-٩) به انگلیسی
_DIGIT_MAP = {}
for _i in range(10):
    _DIGIT_MAP[chr(0x06F0 + _i)] = str(_i)  # ۰۱۲۳۴۵۶۷۸۹
    _DIGIT_MAP[chr(0x0660 + _i)] = str(_i)  # ٠١٢٣٤٥٦٧٨٩
_DIGITS = str.maketrans(_DIGIT_MAP)

FA_DIGITS = "۰۱۲۳۴۵۶۷۸۹"
AR_DIGITS = "٠١٢٣٤٥٦٧٨٩"


def normalize_for_match(text: str) -> str:
    """متن را فقط برای تطبیق با الگوها نرمال می‌کند؛ خروجی هرگز چاپ/ذخیره نمی‌شود."""
    text = unicodedata.normalize("NFC", text)
    text = text.translate(_CHAR_MAP)
    text = _DIACRITICS.sub("", text)
    text = text.translate(_DIGITS)
    return re.sub(r"[ \t]+", " ", text)


def to_digit_script(number: int, sample: str) -> str:
    """عدد را با همان خطِ رقمیِ نمونه برمی‌گرداند (فارسی/عربی/انگلیسی)."""
    s = str(number)
    if sample and sample[0] in FA_DIGITS:
        return s.translate(str.maketrans("0123456789", FA_DIGITS))
    if sample and sample[0] in AR_DIGITS:
        return s.translate(str.maketrans("0123456789", AR_DIGITS))
    return s


def parse_number(token: str) -> int | None:
    """عدد فارسی/عربی/انگلیسی را می‌خواند؛ اگر عدد نبود None."""
    try:
        return int(normalize_for_match(token).strip())
    except (ValueError, AttributeError):
        return None
