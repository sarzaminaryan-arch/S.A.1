"""Run: python3 tools/wp-agent/test_build_replace_batch.py"""
import json
import sys
import tempfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import build_replace_batch as brb  # noqa: E402


def para(t):
    return f"<!-- wp:paragraph --><p>{t}</p><!-- /wp:paragraph -->"


def h2(t):
    return f'<!-- wp:heading --><h2 class="wp-block-heading">{t}</h2><!-- /wp:heading -->'


CITE = '<sup class="sa-cite"><a href="https://www.mehrnews.com/news/1">6</a></sup>'
BASE = "\n\n".join([
    "<!-- wp:paragraph --><p>```</p><!-- /wp:paragraph -->",
    h2("معرفی"), para("شهر نمونه در کنار رودخانه قرار دارد و بازار قدیمی آن دیدنی است."),
    h2("یک درخواست داریم"), para("اگر تجربه‌ای دارید برای ما بنویسید."),
])
NEW = "\n\n".join([
    h2("سال‌های اخیر"),
    para("در سال گذشته پل تازه‌ای روی رودخانه افتتاح شد و رفت‌وآمد مردم آسان‌تر شد." + CITE),
    para("به نظر من بهترین زمان دیدن شهر پاییز است، وقتی هوا معتدل و آسمان صاف است."),
])
STRAY = "<!-- wp:paragraph --><p>```</p><!-- /wp:paragraph -->\n\n"


def spec(blocks=NEW, words_original=20, rewrites=None):
    return {"words_original": words_original, "modified_gmt": "2026-11-15 00:00:00",
            "rewrites": rewrites if rewrites is not None else [[STRAY, ""]],
            "insert_before_h2": "یک درخواست داریم", "blocks": blocks}


def run(specs):
    with tempfile.TemporaryDirectory() as d:
        d = Path(d)
        read = {"steps": [{"result": {"id": 1, "slug": "nemune", "title": "شهرستان نمونه"}}]}
        (d / "read.json").write_text(json.dumps(read), encoding="utf-8")
        (d / "base").mkdir()
        (d / "base" / "nemune-1.html").write_text(BASE, encoding="utf-8")
        (d / "add.json").write_text(json.dumps({"nemune-1": specs}, ensure_ascii=False), encoding="utf-8")
        rows = brb.main(str(d / "read.json"), str(d / "base"), str(d / "add.json"), str(d / "out"))
        out = (d / "out" / "nemune-1.html").read_text(encoding="utf-8")
        assert (d / "out" / "summary.json").exists()
        return rows[0], out


# 1. happy path: stray fence removed, new section inserted before the request H2
row, out = run(spec())
assert row["ok"], row
assert "```" not in out
assert out.index("سال‌های اخیر") < out.index("یک درخواست داریم")
assert row["words_after"] > row["words_live"]

# 2. volume floor: fewer words than the original page fails
row, _ = run(spec(words_original=10_000))
assert not row["ok"] and not row["volume_ok"]

# 3. banned phrases (wp_agent.BANNED and the owner's extra list) fail the page
for bad in ("اسرائیل", "اسراییل", "راستش", "ChatGPT"):
    row, _ = run(spec(blocks=NEW + "\n\n" + para(f"جملهٔ آزمایشی با {bad} در متن.")))
    assert not row["ok"] and row["banned"], (bad, row)
assert brb.banned_hits("متن تمیز بدون واژهٔ ممنوع") == []

# 4. non-https citation fails
row, _ = run(spec(blocks=NEW.replace("https://www.mehrnews", "http://www.mehrnews")))
assert not row["ok"] and row["bad_cites"]

# 5. duplicate sentence fails
dup = "شهر نمونه در کنار رودخانه قرار دارد و بازار قدیمی آن دیدنی است."
row, _ = run(spec(blocks=NEW + "\n\n" + para(dup)))
assert not row["ok"] and row["dup_after"]

# 6. unbalanced Gutenberg comments fail
row, _ = run(spec(blocks=NEW + "\n\n<!-- wp:paragraph --><p>بدون بستن</p>"))
assert not row["ok"]

# 7. rewrites must match exactly once; insert anchor must match one H2
for bad_spec in (spec(rewrites=[["متنی که وجود ندارد", ""]]),):
    try:
        brb.apply(BASE, bad_spec)
        raise AssertionError("apply should reject a rewrite that does not match")
    except ValueError:
        pass
try:
    brb.apply(BASE, {**spec(), "insert_before_h2": "عنوانی که نیست"})
    raise AssertionError("apply should reject a missing H2 anchor")
except ValueError:
    pass

# 8. classic-editor pages (no block comments): the anchor is a bare <h2>
CLASSIC = "<p>مقدمه</p>\n\n<h2>حقایق جالب</h2>\n\n<p>متن</p>\n\n<h2>از مردم عزیز، یک درخواست داریم</h2>\n\n<p>پایان</p>"
out, _ = brb.apply(CLASSIC, {"insert_before_h2": "یک درخواست داریم", "blocks": "<h2>بخش تازه</h2>\n\n<p>تازه</p>"})
assert out.index("بخش تازه") < out.index("یک درخواست داریم") and out.count("<h2>") == 3, out

print("test_build_replace_batch: ok")
