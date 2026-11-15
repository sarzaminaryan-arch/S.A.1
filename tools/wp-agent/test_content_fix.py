"""Run: python3 tools/wp-agent/test_content_fix.py"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import content_fix as cf  # noqa: E402

S = '<sup class="sa-cite"><a href="https://fa.wikipedia.org/wiki/X">1</a></sup>'
html = (
    '<h2>معرفی شهرستان</h2>'
    '<ul><li><strong>قلعهٔ الموت</strong>؛ دژ تاریخی حسن صباح در دره‌های سرسبز الموت.' + S + '</li></ul>'
    '<p>اهواز در مرکز خوزستان و در دو سوی رودخانه کارون قرار دارد. نام مرکز و ساختار اداری پیش از انتشار باید با آخرین اسناد رسمی تطبیق داده شود.</p>'
    '<h2>مشخصات کلی</h2><figure><table><thead><tr><th>شاخص</th><th>مقدار</th><th>توضیح</th></tr></thead><tbody>'
    '<tr><td>جمعیت</td><td>۱۰۰ نفر</td><td>سرشماری ۱۳۹۵؛ بازبینی مرکز آمار لازم است ' + S + '</td></tr>'
    '<tr><td>مساحت</td><td>نیازمند تطبیق رسمی</td><td>دادهٔ پایه؛ تطبیق رسمی لازم است</td></tr></tbody></table></figure>'
    '<h2>موقعیت جغرافیایی</h2>'
    '<p>اهواز در مرکز خوزستان و در دو سوی رودخانه کارون قرار دارد. همسایه‌های شهرستان شامل حمیدیه و باوی هستند. مرزهای اداری و نام واحدهای پیرامونی باید با نقشهٔ رسمی بازبینی شوند.' + S + '</p>'
    '<h2>جاذبه‌ها</h2>'
    '<ul><li><strong>قلعهٔ الموت</strong>؛ دژ تاریخی حسن صباح در دره‌های سرسبز الموت.' + S + '</li></ul>'
    '<h3>عمارت جاجرمی</h3><p>عمارت جاجرمی از آثار معماری شهری بجنورد است و اطلاعات ثبت و ساعت بازدید باید از میراث فرهنگی کنترل شود.</p>'
    '<h3>بازار</h3><p>بازار عبدالحمید از فضاهای تاریخی اهواز است؛ وضعیت بنا و ساعت فعالیت باید به‌روز بررسی شود.</p>'
    '<h2>آب و هوا</h2><p>پاییز مناسب‌تر است و برنامهٔ تابستانی باید با گرما و کیفیت هوا هماهنگ شود.</p>'
    '<h2>مشاهیر</h2><ul><li>پژوهشگران تاریخ و میراث اهواز؛ نام‌ها باید مستند باشند.</li></ul>'
    '<p>این فهرست اولیه است و هیچ انتساب حدسی نباید در نسخهٔ انتشار باقی بماند.</p>'
    '<h2>پایان</h2><p>متن پایانی.</p>'
)
out, log = cf.clean(html)
assert out.count("دو سوی رودخانه کارون") == 1, "duplicate sentence removed (later copy)"
assert "همسایه‌های شهرستان شامل حمیدیه و باوی هستند." + S in out, "citation moves to the kept sentence"
assert "پیش از انتشار" not in out and "بازبینی" not in out and "نسخهٔ انتشار" not in out
assert "<td>سرشماری ۱۳۹۵ " + S + "</td>" in out, out
assert "<td>مساحت</td>" not in out, "placeholder row removed"
assert out.count("دژ تاریخی حسن صباح") == 1, "summary list item shortened"
assert "<li><strong>قلعهٔ الموت</strong>" + S + "</li>" in out
assert "عمارت جاجرمی از آثار معماری شهری بجنورد است." in out, "fact before «است و <note>» kept"
assert "بازار عبدالحمید از فضاهای تاریخی اهواز است." in out
assert "برنامهٔ تابستانی باید با گرما و کیفیت هوا هماهنگ شود" in out, "travel advice is not a note"
assert "مشاهیر" not in out, "section emptied by the cleanup loses its heading"
assert "<h2>پایان</h2>" in out
# a title heading over an image (already text-less) is not removed
img = '<h2>شهرستان الف؛ عنوان</h2><figure><img src="a.webp" alt=""></figure><h2>بعدی</h2><p>متن.</p>'
assert cf.clean(img)[0] == img
# exact rewrites must match once
try:
    cf.clean("<p>الف</p>", [("ب", "ج")])
    raise SystemExit("rewrite with no match must fail")
except ValueError:
    pass
assert not cf.is_note("پیش از سفر باید وضعیت راه و آب‌وهوا بررسی شود")
assert not cf.is_note("اگر فکر می‌کنید جاذبه‌ای باید بهتر معرفی شود، بنویسید")
assert cf.is_note("ساعت بازدید هر جاذبه باید پیش از انتشار و سفر از مرجع محلی بررسی شود")
assert cf.is_note("داده‌های اشتغال باید از منابع رسمی و با سال مرجع نقل شوند")
assert cf.find_notes("متن عادی است. نام مرکز پیش از انتشار باید تطبیق شود.") == ["پیش از انتشار"]
_cell = '<td>به روایت صفحه؛ سال ۱۳۹۵<sup class="sa-cite"><a href="https://x">۱</a></sup></td>'
assert cf._clean_cell(_cell[4:-5]) == _cell[4:-5], "untouched cells must stay byte-identical"
assert cf.is_note("این مقاله یک پیش‌نویس پژوهشی برای شناخت شهرستان است")
assert cf.is_note("در صورت اختلاف میان منابع، نام در نسخهٔ نهایی حذف خواهد شد")
print("OK: content_fix", len(log), "changes")
