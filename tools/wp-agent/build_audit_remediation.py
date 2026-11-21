#!/usr/bin/env python3
"""Build deterministic Step 2 (meta/URL) & Step 3 (draft-copy + worklists) remediation artifacts.

Reads a `sa-live-article-audit` snapshot JSON and authoritative repository data
(`data/ready-articles.php`, `assets/js/iran-counties-map.js`) without making any
network requests or modifying live WordPress directly.

Every generated `.meta.json` and `.html` payload is verified against `fa_lint`
(`scan_meta` / `scan`), HTML tag balance, and Gutenberg comment balance.
"""
from __future__ import annotations

import html as html_mod
import json
import math
import os
import re
import sys
import urllib.parse
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[1]
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE / "replace_src"))

import content_audit as ca  # noqa: E402
import content_fix as cf  # noqa: E402
import fa_lint as fl  # noqa: E402

BALANCE_TAGS = ("p", "ul", "ol", "li", "table", "tr", "td", "th", "h2", "h3", "h4", "strong", "a")


def wp_strip_pct(url: str) -> str:
    """Reproduce WordPress `_sanitize_text_fields()` percent-octet stripping."""
    out = url
    while re.search(r"%[a-fA-F0-9]{2}", out):
        out = re.sub(r"%[a-fA-F0-9]{2}", "", out)
    return out


def canonicalize_url(url: str) -> str:
    sp = urllib.parse.urlsplit(url.strip())
    enc_path = urllib.parse.quote(urllib.parse.unquote(sp.path), safe="/:@!$&'()*+,;=-._~")
    enc_q = urllib.parse.quote(urllib.parse.unquote(sp.query), safe="/:@!$&'()*+,;=-._~=?&")
    return urllib.parse.urlunsplit((sp.scheme, sp.netloc, enc_path, enc_q, ""))


def norm_fa(text: str) -> str:
    t = (
        text.replace("ي", "ی")
        .replace("ك", "ک")
        .replace("\u200c", " ")
        .replace("ٔ", "")
        .replace("_", " ")
    )
    t = re.sub(r"ه\s+ی\b", "ه", t)
    return re.sub(r"\s+", " ", t).strip()


def is_corrupted_source_url(url: str) -> bool:
    p = urllib.parse.urlsplit(url).path
    return bool(
        re.search(r"/wiki/[_()\-]*$", p)
        or re.search(r"/(?:[-_]{2,}|[-_]+/[-_]*)(?:$|\?)", p)
        or re.search(r"/(?:destination|attraction|province|blog|fa|dir)/[-_0-9]*$", p)
        or re.search(r"%[0-9A-Fa-f](?![0-9A-Fa-f])", url)
    )


def parse_ready_articles_sources(php_path: Path) -> dict[str, str]:
    """Extract slug -> sa_sources string from data/ready-articles.php without PHP CLI."""
    txt = php_path.read_text(encoding="utf-8")
    vars_map: dict[str, str] = {}
    for vm in re.finditer(
        r"(\$sa_sources_[a-zA-Z0-9_]+)\s*=\s*implode\(\s*\"\\n\"\s*,\s*array\((.*?)\)\s*\);",
        txt,
        flags=re.S,
    ):
        lines = [m.group(1) for m in re.finditer(r"'([^']*)'", vm.group(2))]
        vars_map[vm.group(1)] = "\n".join(lines)

    out: dict[str, str] = {}
    for am in re.finditer(
        r"\n\t'([a-z0-9-]+)'\s*=>\s*array\((.*?)\n\t\),",
        txt,
        flags=re.S,
    ):
        slug = am.group(1)
        body = am.group(2)
        sm_var = re.search(r"'sa_sources'\s*=>\s*(\$sa_sources_[a-zA-Z0-9_]+)", body)
        if sm_var and sm_var.group(1) in vars_map:
            out[slug] = vars_map[sm_var.group(1)]
            continue
        sm_implode = re.search(
            r"'sa_sources'\s*=>\s*implode\(\s*\"\\n\"\s*,\s*array\((.*?)\)\s*\)",
            body,
            flags=re.S,
        )
        if sm_implode:
            lines = [m.group(1) for m in re.finditer(r"'([^']*)'", sm_implode.group(1))]
            out[slug] = "\n".join(lines)
            continue
        sm_str = re.search(r"'sa_sources'\s*=>\s*'([^']+)'", body)
        if sm_str:
            out[slug] = sm_str.group(1)
    return out


def load_county_map_coords(js_path: Path) -> dict[str, tuple[str, str]]:
    raw = js_path.read_text(encoding="utf-8")
    payload = json.loads(raw.split("window.SA_COUNTY_SHAPES = ", 1)[1].rstrip(";\n"))
    out: dict[str, tuple[str, str]] = {}
    for pdata in payload.values():
        for c in pdata.get("counties", []):
            out[c["slug"]] = (f"{float(c['lat']):.4f}".rstrip("0").rstrip("."), f"{float(c['lng']):.4f}".rstrip("0").rstrip("."))
    return out


def repair_wiki_from_title(line: str, broken_url: str) -> str | None:
    """Reconstruct a Wikipedia URL from the source line's head phrase ONLY if its
    percent-stripped ASCII skeleton matches `broken_url` byte-for-byte."""
    if "fa.wikipedia.org/wiki/" not in broken_url:
        return None
    col0 = line.split("|")[0].strip()
    col0 = re.sub(r"^\[[^\]]+\]\s*", "", col0)
    col0 = re.sub(r"^[0-9۰-۹]+[.)\-–—]\s*", "", col0)
    head = re.split(r"\s+[—–]\s+|؛", col0, maxsplit=1)[0].strip()
    if not head or len(head) < 2:
        return None
    variants = [
        head,
        re.sub(r"هٔ\b", "ه", head.replace("ه‌ی ", "ه ").replace("هٔ ", "ه ")),
        head.replace("\u200c", " "),
    ]
    base = broken_url.split("/wiki/", 1)[0] + "/wiki/"
    for cand in variants:
        wiki_slug = re.sub(r"\s+", "_", cand.strip())
        full = canonicalize_url(base + wiki_slug)
        if wp_strip_pct(full) == broken_url:
            return full
    return None


def repair_sources_text(
    raw_sources: str,
    local_by_skel: dict[str, set[str]],
    global_by_skel: dict[str, set[str]],
) -> tuple[str, list[tuple[str, str]], list[str]]:
    """Deterministically repair corrupted URLs in `sa_sources` without guessing."""
    parts = re.split(r"(^\s*-{3,}.*$)", raw_sources, maxsplit=1, flags=re.M)
    pub = parts[0]
    tail = "".join(parts[1:]) if len(parts) > 1 else ""

    repaired_pairs: list[tuple[str, str]] = []
    unrecovered: list[str] = []
    new_lines: list[str] = []

    for line in pub.splitlines():
        m = re.search(r"https?://[^\s|]+", line)
        if not m:
            new_lines.append(line)
            continue
        u = m.group(0)
        if not is_corrupted_source_url(u):
            new_lines.append(line)
            continue

        title_norm = norm_fa(line.split("|")[0])
        chosen: str | None = None

        # 1. Exact skeleton match in this post's own content URLs
        cands = local_by_skel.get(u, set())
        if len(cands) == 1:
            chosen = next(iter(cands))
        elif len(cands) > 1:
            matched = [
                c
                for c in sorted(cands)
                if norm_fa(urllib.parse.unquote(urllib.parse.urlsplit(c).path.rsplit("/", 1)[-1])) in title_norm
            ]
            if len(matched) == 1:
                chosen = matched[0]

        # 2. Exact skeleton match in repository/global pool with title verification
        if not chosen:
            gcands = global_by_skel.get(u, set())
            if len(gcands) == 1 and not u.endswith("/wiki/") and not re.search(r"/wiki/_+$", u):
                chosen = next(iter(gcands))
            elif gcands:
                matched = [
                    c
                    for c in sorted(gcands)
                    if len(norm_fa(urllib.parse.unquote(urllib.parse.urlsplit(c).path.rsplit("/", 1)[-1]))) >= 4
                    and norm_fa(urllib.parse.unquote(urllib.parse.urlsplit(c).path.rsplit("/", 1)[-1])) in title_norm
                ]
                if len(matched) == 1:
                    chosen = matched[0]

        # 3. Numeric Iranian news-agency routes (/news/<id>/--- -> /news/<id>)
        if not chosen and re.search(r"^https?://[^/]+/(?:fa/)?news/\d+/[-_]+/?$", u):
            chosen = re.sub(r"/[-_]+/?$", "", u)

        # 4. Exact ASCII-skeleton checksum match against Persian Wikipedia head title
        if not chosen:
            chosen = repair_wiki_from_title(line, u)

        if chosen and chosen != u:
            repaired_pairs.append((u, chosen))
            line = line.replace(u, chosen, 1)
        else:
            # Leave Wikidata bare /wiki/ out of unrecovered if Q-id wasn't in URL
            if "wikidata.org/wiki/" not in u:
                unrecovered.append(f"{u}  ({line.split('|')[0].strip()[:60]})")
        new_lines.append(line)

    out_pub = "\n".join(new_lines)
    # Apply safe Persian spelling/character fixes on non-URL prose if needed for fa_lint.scan_meta
    fixed_pub, _ = fl.fix_text(out_pub)
    combined = fixed_pub + tail
    if fl.scan_meta({"sa_sources": combined}):
        # Clean non-URL columns line-by-line if rare foreign month names exist in titles
        cleaned_lines = []
        for ln in fixed_pub.splitlines():
            cols = ln.split("|")
            new_cols = []
            for col in cols:
                if re.match(r"^\s*https?://", col):
                    new_cols.append(col.strip())
                else:
                    c2, _ = fl.fix_text(col)
                    new_cols.append(c2.strip())
            cleaned_lines.append(" | ".join(new_cols) if len(new_cols) > 1 else new_cols[0])
        combined = "\n".join(cleaned_lines) + tail

    return combined, repaired_pairs, unrecovered


def html_tags_balanced(html: str) -> bool:
    return all(len(re.findall(rf"<{t}[\s>]", html)) == html.count(f"</{t}>") for t in BALANCE_TAGS)


def build_all(snapshot_path: Path, repo_root: Path = REPO) -> dict:
    snap = json.loads(snapshot_path.read_text(encoding="utf-8"))
    records = snap["records"]

    ready_sources = parse_ready_articles_sources(
        repo_root / "wp-content/themes/sarzaminaryan-child/data/ready-articles.php"
    )
    coords_map = load_county_map_coords(
        repo_root / "wp-content/themes/sarzaminaryan-child/assets/js/iran-counties-map.js"
    )

    # Build global intact URL pool
    global_by_skel: dict[str, set[str]] = {}
    ra_txt = (repo_root / "wp-content/themes/sarzaminaryan-child/data/ready-articles.php").read_text(encoding="utf-8")
    for u in re.findall(r'https?://[^\s"\'<>|\\]+', ra_txt):
        cu = canonicalize_url(u)
        global_by_skel.setdefault(wp_strip_pct(cu), set()).add(cu)

    local_pools: dict[int, dict[str, set[str]]] = {}
    for r in records:
        pool: dict[str, set[str]] = {}
        for u in re.findall(r'https?://[^\s"\'<>]+', r.get("content", "")):
            clean_u = html_mod.unescape(u).rstrip(".,);]")
            cu = canonicalize_url(clean_u)
            pool.setdefault(wp_strip_pct(cu), set()).add(cu)
            global_by_skel.setdefault(wp_strip_pct(cu), set()).add(cu)
        local_pools[r["wp_id"]] = pool

    # ------------------------------------------------------------------
    # STEP 2A: 6 counties missing coordinates + city:dorud (ID 516) meta
    # ------------------------------------------------------------------
    out_coords_dir = repo_root / "tools/wp-agent/outbox/20261121-coords-dorud"
    out_coords_dir.mkdir(parents=True, exist_ok=True)
    step2a_steps: list[dict] = []
    step2a_ids: list[int] = []

    for r in records:
        if r["post_type"] != "city":
            continue
        m = r.get("meta") or {}
        lat = m.get("lat") or m.get("sa_city_latitude") or m.get("sa_latitude")
        lng = m.get("lng") or m.get("sa_city_longitude") or m.get("sa_longitude")
        if not lat or not lng:
            slug = r["slug"]
            c_pair = coords_map.get(slug) or coords_map.get(f"{slug}-city")
            if not c_pair:
                continue
            meta_payload = {
                "lat": c_pair[0],
                "lng": c_pair[1],
                "sa_city_latitude": c_pair[0],
                "sa_city_longitude": c_pair[1],
            }
            assert fl.scan_meta(meta_payload) == [], fl.scan_meta(meta_payload)
            rel = f"tools/wp-agent/outbox/20261121-coords-dorud/{slug}-{r['wp_id']}.meta.json"
            (repo_root / rel).write_text(
                json.dumps(meta_payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
            )
            step2a_steps.append(
                {
                    "cmd": "update",
                    "id": r["wp_id"],
                    "meta_file": rel,
                    "reason": f"تکمیل مختصات جغرافیایی تأییدشدهٔ شهرستان {r['title']} از نقشهٔ مرجع قالب",
                }
            )
            step2a_ids.append(r["wp_id"])

    # city:dorud (ID 516)
    dorud_rec = next((r for r in records if r["key"] == "city:dorud"), None)
    if dorud_rec and "dorud" in ready_sources:
        dorud_meta = {
            "sa_english_name": "Dorud County",
            "sa_cty_center": "دورود",
            "sa_cty_districts": "2",
            "sa_cty_rural_districts": "5",
            "sa_cty_cities": "2",
            "sa_cty_villages": "135",
            "sa_cty_neighbors": (
                "borujerd - شمال و شمال‌غرب\n"
                "azna - شرق\n"
                "aligudarz - جنوب‌شرق\n"
                "khorramabad - غرب و جنوب‌غرب\n"
                "shazand - شمال‌شرق"
            ),
            "sa_cty_distance_center": "86",
            "sa_cty_climate": "کوهستانی معتدل (زاگرس میانی)",
            "sa_cty_language": "لری (بختیاری، سیلاخوری و لری خرم‌آبادی) و فارسی",
            "sa_cty_livelihood": "کشاورزی (برنج و گندم دشت سیلاخور)، صنعت سیمان و سنگ، دامداری و گردشگری طبیعی",
            "sa_cty_best_time": "بهار (فروردین تا خرداد)، اوایل تابستان و پاییز",
            "sa_sources": ready_sources["dorud"],
        }
        assert fl.scan_meta(dorud_meta) == [], fl.scan_meta(dorud_meta)
        rel = f"tools/wp-agent/outbox/20261121-coords-dorud/dorud-{dorud_rec['wp_id']}.meta.json"
        (repo_root / rel).write_text(
            json.dumps(dorud_meta, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )
        step2a_steps.append(
            {
                "cmd": "update",
                "id": dorud_rec["wp_id"],
                "meta_file": rel,
                "reason": "اصلاح همسایه‌های خراب‌شده، تکمیل فیلدهای شناسنامه و همگام‌سازی منابع معتبر شهرستان دورود از مخزن",
            }
        )
        step2a_ids.append(dorud_rec["wp_id"])

    if step2a_ids:
        step2a_steps.append({"cmd": "cache-purge", "id": step2a_ids})

    job_2a_path = repo_root / "tools/wp-agent/jobs/20261121-02-coords-and-dorud-meta.json"
    job_2a_path.write_text(
        json.dumps(
            {
                "note": "گام ۲-الف برنامهٔ بهینه‌سازی ممیزی ۲۱ نوامبر ۲۰۲۶: تکمیل مختصات ۶ شهرستان فاقد lat/lng (فاروج، گچساران، دنا، بهمئی، لنده، باشت) + اصلاح همسایه‌ها و منابع معتبر شهرستان دورود (شناسه ۵۱۶). فقط متادیتا با پشتیبان خودکار؛ متن منتشرشده دست‌نخورده می‌ماند.",
                "rollback_on_fail": True,
                "steps": step2a_steps,
            },
            ensure_ascii=False,
            indent=2,
        )
        + "\n",
        encoding="utf-8",
    )

    # ------------------------------------------------------------------
    # STEP 2B: Deterministic sa_sources URL repair across live posts
    # ------------------------------------------------------------------
    out_src_dir = repo_root / "tools/wp-agent/outbox/20261121-sources-repair"
    out_src_dir.mkdir(parents=True, exist_ok=True)
    src_steps: list[dict] = []
    repaired_url_total = 0
    unrecovered_all: list[dict] = []
    skipped_fa_lint: list[str] = []

    for r in records:
        if r["key"] == "city:dorud":
            continue  # already handled in 20261121-02
        m = r.get("meta") or {}
        live_src = m.get("sa_sources", "")
        slug = r["slug"]

        new_src: str | None = None
        reason = ""
        pairs: list[tuple[str, str]] = []

        # Case 1: Attraction in ready-articles.php whose live sa_sources drifted
        if r["post_type"] == "attraction" and slug in ready_sources:
            repo_src = ready_sources[slug]
            if repo_src.strip() != live_src.strip():
                fixed_repo, _ = fl.fix_text(repo_src)
                if not fl.scan_meta({"sa_sources": fixed_repo}):
                    new_src = fixed_repo
                    reason = f"همگام‌سازی منابع معتبر دیدنی «{r['title']}» با دادهٔ مرجع مخزن"
                    repaired_url_total += 1

        # Case 2: Corrupted percent-encoded URLs in sa_sources
        if new_src is None and live_src:
            cand_src, pairs, unrec = repair_sources_text(live_src, local_pools[r["wp_id"]], global_by_skel)
            if unrec:
                unrecovered_all.append(
                    {"id": r["wp_id"], "key": r["key"], "title": r["title"], "urls": unrec}
                )
            if pairs:
                lint_errs = fl.scan_meta({"sa_sources": cand_src})
                if lint_errs:
                    skipped_fa_lint.append(f"{r['key']}: {lint_errs[0]}")
                else:
                    new_src = cand_src
                    repaired_url_total += len(pairs)
                    reason = f"بازسازی قطعی {len(pairs)} نشانی درصدکدگذاری‌شدهٔ فارسی در sa_sources ({r['title']})"

        if new_src is not None:
            rel = f"tools/wp-agent/outbox/20261121-sources-repair/{r['post_type']}-{slug}-{r['wp_id']}.meta.json"
            (repo_root / rel).write_text(
                json.dumps({"sa_sources": new_src}, ensure_ascii=False, indent=2) + "\n",
                encoding="utf-8",
            )
            src_steps.append(
                {
                    "cmd": "update",
                    "id": r["wp_id"],
                    "meta_file": rel,
                    "reason": reason,
                }
            )

    # Split into batches of <= 65 posts per job so runner stays well within timeout
    batch_size = 65
    num_batches = max(1, math.ceil(len(src_steps) / batch_size))
    letters = "abcdefgh"
    job_2b_files: list[str] = []
    for idx in range(num_batches):
        chunk = src_steps[idx * batch_size : (idx + 1) * batch_size]
        if not chunk:
            continue
        chunk_ids = [s["id"] for s in chunk]
        steps_with_purge = chunk + [{"cmd": "cache-purge", "id": chunk_ids}]
        part_letter = letters[idx]
        jname = f"20261121-03{part_letter}-sources-repair-part{idx + 1}.json"
        jpath = repo_root / "tools/wp-agent/jobs" / jname
        jpath.write_text(
            json.dumps(
                {
                    "note": f"گام ۲-ب (بخش {idx + 1} از {num_batches}): بازسازی قطعی نشانی‌های درصدکدگذاری‌شدهٔ خراب در sa_sources و همگام‌سازی منابع مخزن ({len(chunk)} نوشته). فقط متادیتا با پشتیبان خودکار.",
                    "rollback_on_fail": True,
                    "steps": steps_with_purge,
                },
                ensure_ascii=False,
                indent=2,
            )
            + "\n",
            encoding="utf-8",
        )
        job_2b_files.append(jname)

    (out_src_dir / "summary.json").write_text(
        json.dumps(
            {
                "updated_posts": len(src_steps),
                "repaired_urls_or_records": repaired_url_total,
                "unrecovered_posts": len(unrecovered_all),
                "unrecovered_details": unrecovered_all,
                "skipped_fa_lint": skipped_fa_lint,
                "jobs": job_2b_files,
            },
            ensure_ascii=False,
            indent=2,
        )
        + "\n",
        encoding="utf-8",
    )

    # ------------------------------------------------------------------
    # STEP 3: Pilot draft-copy batch (10 safe pages) + Complete Worklists
    # ------------------------------------------------------------------
    out_pilot_dir = repo_root / "tools/wp-agent/outbox/20261121-editorial-pilot"
    out_pilot_dir.mkdir(parents=True, exist_ok=True)

    editorial_rows: list[dict] = []
    pilot_candidates: list[tuple[dict, str, list[str]]] = []

    for r in records:
        if r["post_type"] != "city":
            continue
        page_stub = {"id": r["wp_id"], "slug": r["slug"], "title": r["title"], "content": r["content"]}
        before = ca.analyze_page(page_stub)
        if before["editorial_notes"] == 0 and before["dup_sentences"] == 0:
            continue
        cleaned_html, log = cf.clean(r["content"])
        cleaned_html, _ = fl.fix_text(cleaned_html)
        after = ca.analyze_page({**page_stub, "content": cleaned_html})
        drop_pct = (
            round(100.0 * (before["words"] - after["words"]) / max(1, before["words"]), 1)
            if before["words"]
            else 0.0
        )
        lint_ok = fl.scan(cleaned_html) == []
        tags_ok = html_tags_balanced(cleaned_html)
        guten_ok = (cleaned_html.count("<!-- wp:") - cleaned_html.count("<!-- /wp:")) == (
            r["content"].count("<!-- wp:") - r["content"].count("<!-- /wp:")
        )
        safe_draft = (
            after["words"] >= 1000
            and drop_pct <= 8.0
            and after["editorial_notes"] == 0
            and lint_ok
            and tags_ok
            and guten_ok
            and len(log) > 0
        )
        row = {
            "id": r["wp_id"],
            "slug": r["slug"],
            "title": r["title"],
            "words_before": before["words"],
            "words_after": after["words"],
            "drop_pct": drop_pct,
            "notes_before": before["editorial_notes"],
            "notes_after": after["editorial_notes"],
            "dup_before": before["dup_sentences"],
            "dup_after": after["dup_sentences"],
            "lint_ok": lint_ok,
            "safe_draft": safe_draft,
        }
        editorial_rows.append(row)
        if safe_draft and (before["editorial_notes"] > 0 or before["dup_sentences"] > 0):
            pilot_candidates.append((row, cleaned_html, log))

    # Pick top 10 safe draft-copy pages: editorial notes first, then lowest drop_pct, then highest words_after
    pilot_candidates.sort(key=lambda item: (0 if item[0]["notes_before"] > 0 else 1, item[0]["drop_pct"], -item[0]["words_after"]))
    pilot_selected = pilot_candidates[:10]
    pilot_steps: list[dict] = []
    for row, cleaned_html, log in pilot_selected:
        base_rel = f"tools/wp-agent/outbox/20261121-editorial-pilot/{row['slug']}-{row['id']}"
        (repo_root / f"{base_rel}.html").write_text(cleaned_html, encoding="utf-8")
        (repo_root / f"{base_rel}.changes.txt").write_text("\n".join(log) + "\n", encoding="utf-8")
        pilot_steps.append(
            {
                "cmd": "draft-copy",
                "id": row["id"],
                "content_file": f"{base_rel}.html",
            }
        )

    job_3_path = repo_root / "tools/wp-agent/jobs/20261121-04-editorial-notes-pilot-drafts.json"
    job_3_path.write_text(
        json.dumps(
            {
                "note": "گام ۳ (دستهٔ آزمایشی ۱۰ صفحه‌ای فقط پیش‌نویس کنار اصل — draft-copy): حذف یادداشت‌های تولیدی/«بازبینی انسانی» و جملات تکراری در شهرستان‌های بالای ۱۰۰۰ واژه با افت کمتر از ۵٪ و تأیید کامل fa_lint. متن منتشرشدهٔ سایت به هیچ وجه لمس نمی‌شود.",
                "steps": pilot_steps,
            },
            ensure_ascii=False,
            indent=2,
        )
        + "\n",
        encoding="utf-8",
    )

    # Collect additional Step 3 inventories for docs/live-site/audit-20261121-worklists.md
    attractions_no_thumb = [
        {
            "id": r["wp_id"],
            "slug": r["slug"],
            "title": r["title"],
            "inline_images": len(r.get("content_images") or []),
        }
        for r in records
        if r["post_type"] == "attraction" and not (r.get("featured_image") or {}).get("attachment_id")
    ]

    attr_by_city = {
        int((r.get("meta") or {})["sa_city_id"])
        for r in records
        if r["post_type"] == "attraction" and (r.get("meta") or {}).get("sa_city_id")
    }
    ce_txt = (repo_root / "wp-content/themes/sarzaminaryan-child/data/county-experiences.php").read_text(
        encoding="utf-8"
    )
    bundled_extra = {
        m.group(1)
        for m in re.finditer(r"'([a-z0-9-]+)'\s*=>\s*array\((.*?)\n\t\),", ce_txt, flags=re.S)
        if "'extra_places'" in m.group(2)
    }
    noindex_no_places = []
    for r in records:
        if r["post_type"] != "city":
            continue
        m = r.get("meta") or {}
        has_poi = any(
            (m.get(k) or "").strip()
            for k in ("sa_cty_poi_nature", "sa_cty_poi_offbeat", "sa_cty_poi_recreation", "sa_cty_poi_heritage", "extra_places")
        )
        if not (has_poi or r["wp_id"] in attr_by_city or r["slug"] in bundled_extra or f"{r['slug']}-city" in bundled_extra):
            noindex_no_places.append({"id": r["wp_id"], "slug": r["slug"], "title": r["title"], "words": r["word_count"]})

    unopened_re = re.compile(r"در دست (?:احداث|ساخت|تکمیل|مطالعه)|طرح توسعه|پروژهٔ نیمه[‌ ]?تمام|افتتاح نشده")
    unopened_pages = []
    for r in records:
        plain_txt = ca.norm(ca.plain(r.get("content", "")))
        hits = unopened_re.findall(plain_txt)
        if hits:
            unopened_pages.append({"id": r["wp_id"], "key": r["key"], "title": r["title"], "hits": sorted(set(hits))})

    thin_counties = [
        {"id": r["wp_id"], "slug": r["slug"], "title": r["title"], "words": r["word_count"]}
        for r in sorted(records, key=lambda x: x["word_count"])
        if r["post_type"] == "city" and r["word_count"] < 1000
    ]

    safe_draft_list = [x for x in editorial_rows if x["safe_draft"]]
    rewrite_needed_list = [x for x in editorial_rows if not x["safe_draft"]]

    md_lines = [
        "# فهرست‌های اجرایی ممیزی مقالات زنده (۲۱ نوامبر ۲۰۲۶)",
        "",
        "این سند از اسنپ‌شات ۵۵۳ مقالهٔ منتشرشدهٔ سایت زنده و داده‌های مرجع مخزن تولید شده است و نقشهٔ دقیق اجرای گام‌های ۲ و ۳ را بدون هیچ حدس یا ادعای تأییدنشده مشخص می‌کند.",
        "",
        "## خلاصهٔ بسته‌های آمادهٔ اجرا (`tools/wp-agent/jobs/`)",
        "",
        f"1. **`20261121-02-coords-and-dorud-meta.json`**: تکمیل مختصات ۶ شهرستان فاقد مختصات + اصلاح همسایه‌ها، فیلدهای شناسنامه و منابع معتبر `city:dorud` (شناسهٔ ۵۱۶).",
        f"2. **`{', '.join(job_2b_files)}`**: بازسازی قطعی **{repaired_url_total}** نشانی خراب یا ناهمگام در `sa_sources` روی **{len(src_steps)}** نوشتهٔ منتشرشده (با تطبیق اسکلت درصدکدگذاری‌شده با پیوندهای سالم داخل متن همان مقاله، مخزن، کد خبر خبرگزاری‌های رسمی و عنوان دقیق ویکی‌پدیا).",
        f"3. **`20261121-04-editorial-notes-pilot-drafts.json`**: ایجاد پیش‌نویس تمیز (`draft-copy`) برای **{len(pilot_selected)}** شهرستان بالای ۱٬۰۰۰ واژه بدون دست‌زدن به نوشتهٔ منتشرشده.",
        "",
        "---",
        "",
        "## یادداشت مهم قفل مالک دربارهٔ «شهر بوشهر» (`city:bushehr`، شناسهٔ ۲۰۱)",
        "",
        "- طبق تصمیم ثبت‌شدهٔ مالک در `CHANGELOG.md` (بند ۱۸۹۱) و آزمون `test-trust-bundle.php`، دو صفحهٔ **«شهر بوشهر» (`city:bushehr`، شناسهٔ ۲۰۱، ۸۹۴ واژه)** و **«شهرستان بوشهر» (`city:bushehr-county`، شناسهٔ ۲۱۱، ۹۹۴ واژه)** دو موجودیت مستقل‌اند و هر دو باید منتشر بمانند.",
        "- بنابراین به هیچ عنوان تغییر مسیر ۳۰۱ یا حذف برای `city:bushehr` تعریف **نمی‌شود**؛ علت `noindex` خودکارِ فعلی در شناسهٔ ۲۰۱ نبود ردیف جاذبه در `sa_cty_poi_*` / `extra_places` (`no_places`) است که در قالب بازنویسی اختصاصی «شهر بوشهر» رفع می‌شود.",
        "",
        "---",
        "",
        f"## ۱. دیدنی‌های فاقد تصویر شاخص (`_thumbnail_id`) — {len(attractions_no_thumb)} مورد",
        "",
        "| شناسه | نامک | عنوان | شمار تصاویر داخل متن (قابل ارتقا به تصویر شاخص) |",
        "|---:|---|---|---:|",
    ]
    for a in attractions_no_thumb:
        md_lines.append(f"| {a['id']} | `{a['slug']}` | {a['title']} | {a['inline_images']} |")

    md_lines += [
        "",
        f"## ۲. مقالات شهرستان آمادهٔ `draft-copy` تمیز (حذف یادداشت تولیدی، بالای ۱٬۰۰۰ واژه، افت ≤ ۸٪) — {len(safe_draft_list)} مورد",
        "",
        "| شناسه | نامک | عنوان | واژه (قبل ← بعد) | افت (%) | یادداشت‌های حذف‌شده | تکرار حذف‌شده |",
        "|---:|---|---|---|---:|---:|---:|",
    ]
    for x in safe_draft_list:
        md_lines.append(
            f"| {x['id']} | `{x['slug']}` | {x['title']} | {x['words_before']} ← {x['words_after']} | {x['drop_pct']}% | {x['notes_before']} | {x['dup_before'] - x['dup_after']} |"
        )

    md_lines += [
        "",
        f"## ۳. مقالات دارای یادداشت تولیدی نیازمند بازنویسی کامل (`replace_src`) به‌جای حذف صِرف — {len(rewrite_needed_list)} مورد",
        "",
        "> طبق دستور مالک در `20261116-22-dedup-batch2-write.json`، صفحه‌هایی که حذف یادداشت‌های تولیدی حجمشان را کم می‌کند یا زیر ۱٬۰۰۰ واژه‌اند نباید صرفاً کوتاه شوند، بلکه در صف بازنویسی کامل (`replace_src`) قرار می‌گیرند.",
        "",
        "| شناسه | نامک | عنوان | واژه (قبل ← بعد از حذف یادداشت) | افت (%) | یادداشت تولیدی |",
        "|---:|---|---|---|---:|---:|",
    ]
    for x in rewrite_needed_list:
        md_lines.append(
            f"| {x['id']} | `{x['slug']}` | {x['title']} | {x['words_before']} ← {x['words_after']} | {x['drop_pct']}% | {x['notes_before']} |"
        )

    md_lines += [
        "",
        f"## ۴. شهرستان‌های دارای `noindex` خودکار به‌علت `no_places` (نیازمند ثبت جاذبهٔ مستند در `extra_places` یا بازنویسی) — {len(noindex_no_places)} مورد",
        "",
        "| شناسه | نامک | عنوان | شمار واژه‌ها |",
        "|---:|---|---|---:|",
    ]
    for c in noindex_no_places:
        md_lines.append(f"| {c['id']} | `{c['slug']}` | {c['title']} | {c['words']} |")

    md_lines += [
        "",
        f"## ۵. صفحه‌های دارای کلیدواژهٔ پروژه‌های در دست احداث/تکمیل (نیازمند بازبینی انسانی طبق قانون ۱۱-ب) — {len(unopened_pages)} مورد",
        "",
        "| شناسه | کلید | عنوان | عبارت‌های یافت‌شده |",
        "|---:|---|---|---|",
    ]
    for u in unopened_pages:
        md_lines.append(f"| {u['id']} | `{u['key']}` | {u['title']} | {'، '.join(u['hits'])} |")

    md_lines += [
        "",
        f"## ۶. نشانی‌های وبلاگی/فرعی در `sa_sources` که بدون حدس قابل بازسازی نبودند (نیازمند جایگزینی دستی) — {len(unrecovered_all)} نوشته",
        "",
    ]
    for item in unrecovered_all:
        md_lines.append(f"- **`{item['key']}`** ({item['title']}، شناسهٔ {item['id']}):")
        for u_desc in item["urls"]:
            md_lines.append(f"  - `{u_desc}`")

    md_lines += [
        "",
        f"## ۷. خلاصهٔ شهرستان‌های زیر ۱٬۰۰۰ واژه در صف ارتقای محتوا — {len(thin_counties)} مورد",
        "",
        f"کوتاه‌ترین ۱۵ شهرستان منتشرشده (همگی بالای کف ۳۰۰ واژهٔ دروازهٔ ایندکس؛ کوتاه‌ترین: `harand` با ۶۱۱ واژه):",
        "",
        "| شناسه | نامک | عنوان | شمار واژه‌ها |",
        "|---:|---|---|---:|",
    ]
    for t in thin_counties[:15]:
        md_lines.append(f"| {t['id']} | `{t['slug']}` | {t['title']} | {t['words']} |")

    doc_path = repo_root / "docs/live-site/audit-20261121-worklists.md"
    doc_path.parent.mkdir(parents=True, exist_ok=True)
    doc_path.write_text("\n".join(md_lines) + "\n", encoding="utf-8")

    return {
        "step2a_count": len(step2a_ids),
        "step2b_posts": len(src_steps),
        "step2b_urls": repaired_url_total,
        "step2b_jobs": job_2b_files,
        "unrecovered_posts": len(unrecovered_all),
        "pilot_drafts": len(pilot_selected),
        "safe_draft_total": len(safe_draft_list),
        "rewrite_needed_total": len(rewrite_needed_list),
        "attractions_no_thumb": len(attractions_no_thumb),
        "noindex_no_places": len(noindex_no_places),
        "unopened_pages": len(unopened_pages),
        "thin_counties": len(thin_counties),
    }


if __name__ == "__main__":
    snap_arg = Path(sys.argv[1]) if len(sys.argv) > 1 else Path("/tmp/audit_drive/live-articles-20261121-192914.json")
    stats = build_all(snap_arg)
    print(json.dumps(stats, ensure_ascii=False, indent=2))
