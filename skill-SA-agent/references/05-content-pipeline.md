# 05 — AI content pipeline for entities (Level 7 in practice)

Sources: data model Level 7, `prompts.chat` (travel guide / SEO / FAQ / title / proofreader),
`awesome-claude-code-subagents/content-quality-editor` (AI-pattern stripping),
`prompt-engineer` (templates, few-shot, version control), `AI-Research-SKILLs`
(structured output idea: instructor/outlines/guidance → JSON constrained to a schema).

## A. Pipeline (per entity)

```
brief (slug, type, parent ids, facts) → research notes (sources) → structured JSON draft
→ Persian prose (5 sections) → quality pass (unslop + proofreader) → publish-gate check → import
```

1. **Brief**: entity type + slug + parents (city/province IDs) + known facts (coordinates,
   hours, prices, season) + 3 target queries. Missing facts are marked `null`, never invented.
2. **Structured JSON draft** — ask the model for JSON that matches
   `data-model/schema/data-model.json` fields for that entity (schema-constrained output).
   Validate with `build_json.py`-style checks before writing prose.
3. **Prose sections (mandatory, in this order — Level 7):**
   1. **Introduction** (معرفی) 120–200 words, hook + what/where/why.
   2. **Structured Data** (اطلاعات کلیدی) rendered from fields (table/facts), not free text.
   3. **FAQ** 3–6 real Q&A.
   4. **Internal Links** per Level 6 graph (parent, children, siblings).
   5. **SEO Data** (seo_title, seo_description, focus_keyword, og_*).
4. **Quality pass**: strip AI patterns (see §C), proofread Persian orthography, verify facts vs sources.
5. **Publish gate** (Level 7): relation ✔ SEO fields ✔ FAQ ✔ featured image ✔ primary taxonomy ✔ — else stays draft.

## B. Master prompt template (Persian output)

```
نقش: نویسنده راهنمای سفر ایران با دقت دایره‌المعارفی.
ورودی: {json_brief}
خروجی: JSON با کلیدهای دقیق زیر و بدون متن اضافه:
{
 "intro_fa": "...(۱۲۰–۲۰۰ کلمه، بدون کلیشه، جمله اول جذاب و حاوی نام {name})",
 "facts": { ...فیلدهای موجودیت طبق اسکیمای {entity_type}; مقادیر نامعلوم = null },
 "faq": [ {"q":"...؟","a":"...(≤۶۰ کلمه)"} × 3–6 ],
 "internal_links": {"parent": "...", "children": ["..."], "siblings": ["..."]},
 "seo": {"seo_title":"≤۶۰ کاراکتر","seo_description":"۷۰–۱۵۵ کاراکتر","focus_keyword":"...","og_title":"...","og_description":"..."}
}
قواعد: فارسی معیار، نیم‌فاصله درست، اعداد فارسی در متن، بدون ادعای بدون منبع، بدون «در این مقاله»، بدون تعارف.
```

Few-shot: keep 1 gold example per entity type under `assets/examples/` (add as you produce them).
Version prompts (`v1`, `v2`) and log which version generated each entity in post meta `sa_gen_prompt`.

## C. AI-pattern stripping (Persian adaptation of `unslop`)

Remove/replace: openers («در دنیای امروز», «بدون شک», «جای تعجب نیست که»); filler transitions
(«علاوه بر این», «در نهایت», «به طور کلی» when hollow); hedging stacks («شایان ذکر است که»,
«لازم به ذکر است»); vocabulary tics («بی‌نظیر», «فوق‌العاده», «تجربه‌ای فراموش‌نشدنی» > 1×/page);
lists of 5+ items that should be prose; headings that restate the paragraph; passive chains.
Checks: first sentence carries information; reading level: general audience; no English
loanwords where a common Persian word exists; ZWNJ (نیم‌فاصله) in «می‌رود», «کتاب‌ها».

## D. Fact discipline

- Facts (hours, prices, distances, populations) need a source URL + date in `sa_sources` meta.
- Prices in تومان with year; hours with weekday ranges; coordinates decimal WGS84 with 5 decimals.
- If a fact is unknown → leave empty; templates hide empty rows automatically.

## E. Batch production plan (v1 launch content)

1. 31 provinces (hub pages) → 2. 2–4 cities per province (~90) → 3. 3–6 attractions per city
(~300) → 4. 1–2 foods + 1–2 souvenirs per city → 5. 10 routes (weekend/3–5 days) → posts.
Publish in that order so Level 6 links always have targets.
