# 04 — SEO, structured data & Google standards for the entity site

Sources: `awesome-claude-code-subagents/seo-specialist` (audit elements, white-hat rules),
`prompts.chat` (SEO outline / FAQ / title prompts), data model Levels 4–7, Google Search Central
essentials (title links, snippets, structured data policies, Core Web Vitals, mobile-first).

## A. Per-page contract (implemented in child `inc/seo.php`)

| Element | Rule |
|---|---|
| `<title>` | `seo_title` meta if set, else `{name} \| {parent} \| سرزمین آریان` ≤ 60 chars; unique per page; keyword first |
| meta description | `seo_description` (70–155 chars) else trimmed excerpt/summary; unique |
| canonical | `canonical_url` meta if set else permalink; paginated archives self-canonical |
| Open Graph / Twitter | `og:title`, `og:description`, `og:image` (featured, ≥1200×630), `og:type` (`article` for posts, `website` home, `place` for entities), `og:locale fa_IR`, `twitter:card summary_large_image` |
| robots | `noindex,follow` for: search results, `?s=`, date archives, attachment pages, paged author pages, tag pages with < 3 posts, any entity that fails the publish gate (should be draft anyway) |
| headings | exactly one `<h1>` = entity name; `h2` for sections (معرفی، اطلاعات، جاذبه‌ها، غذاها، سوغات، مسیرها، سوالات متداول) |
| images | `alt` mandatory (Persian, descriptive); featured image `loading=eager fetchpriority=high`; others lazy; WebP where possible |
| internal links | follow Level 6 graph: every entity links up (city→province), down (province→cities), sideways (similar attractions), plus breadcrumb |
| URLs | Level 4 slugs, lowercase ASCII, hyphens, stable; changed slug ⇒ 301 (`wp_old_slug_redirect` covers post_name changes) |
| sitemap | core `wp-sitemap.xml` includes public CPTs/taxonomies automatically; exclude `travel_season/budget/duration` term archives if thin (filter `wp_sitemaps_taxonomies`) |
| hreflang | none (single language) |

## B. JSON-LD types per entity (implemented in child `inc/schema.php`)

Emit one `@graph` per page containing `WebSite` (+`SearchAction` on home), `Organization`
(name سرزمین آریان, logo, sameAs), `BreadcrumbList`, the page node, and `FAQPage` when FAQ exists.

| Entity | `@type` | Key properties (from model fields) |
|---|---|---|
| Province | `AdministrativeArea` (+`TouristDestination` via `additionalType`) | name, description, geo(lat/long), image, containsPlace → cities, url |
| City | `City` | name, description, geo, image, containedInPlace → province, url |
| Attraction | `TouristAttraction` | name, description, image, geo, address, openingHours, isAccessibleForFree / `offers` (ticket_price), touristType (attraction_type), containedInPlace → city |
| TravelRoute | `TouristTrip` | name, description, image, itinerary → `ItemList` of cities/attractions, estimatedCost (`MonetaryAmount` from budget tier), touristType |
| LocalFood | `Recipe` (light) | name, description, image, recipeIngredient[] (main_ingredients), recipeCuisine `Iranian`, spatialCoverage → city |
| Souvenir | `Product` | name, description, image, brand/`countryOfOrigin` IR, `additionalProperty` purchase_location; **no fake offers/ratings** |
| Accommodation 🟡 | `LodgingBusiness` / `Hotel` / `Campground` | reserved |
| Post | `Article`/`BlogPosting` | headline, image, datePublished/dateModified (ISO 8601 Gregorian!), author, publisher |
| Page | `WebPage` | name, description |

Rules: dates ISO-8601 Gregorian; `image` as absolute URL; no properties you cannot fill;
validate with Rich Results Test + Schema Markup Validator; no rich-result spam (FAQ only real Q&A).

## C. Breadcrumbs (visible + `BreadcrumbList`)

خانه › استان‌ها › {استان} › {شهر} › {جاذبه}. Foods/souvenirs: خانه › {استان} › {شهر} › غذاهای محلی › {غذا}.
Routes: خانه › مسیرهای سفر › {مسیر}. Use `<nav aria-label="مسیر صفحه">` + `<ol>`; current item
`aria-current="page"`, not linked.

## D. Content quality signals (Google helpful-content / E-E-A-T)

- Real first-hand detail (access, hours, prices, seasons) beats generic prose; cite official sources.
- Author box with a real person (محمدرضا لک) + about/contact pages + editorial policy page.
- No doorway/thin pages: an entity below ~300 words of unique text stays draft.
- FAQ: 3–6 real questions users ask (بهترین فصل؟ هزینه بلیت؟ چطور برسیم؟ نزدیک‌ترین شهر؟).
- Freshness: `dateModified` updates when facts change; keep prices/hours with a "به‌روزرسانی" note.

## E. Technical SEO audit list (from seo-specialist, trimmed to what applies)

Crawl errors · broken internal links · duplicate titles/descriptions · thin/orphan entities (no
inbound from parent/hub) · redirect chains · mixed content after HTTPS · mobile usability (menu
toggle visible, tap targets ≥ 48px, no horizontal scroll) · CWV: LCP < 2.5s, INP < 200ms,
CLS < 0.1 (reserve image aspect ratios, fonts with swap, no layout-shifting ads) · sitemap
submitted in Search Console · robots.txt allows `/wp-content/uploads/` and CSS/JS.

## F. Prompts adapted from prompts.chat (use inside the content pipeline)

- **Title generator**: «۵ عنوان فارسی ≤ ۶۰ کاراکتر برای {entity} با کلیدواژه اصلی در ابتدا، بدون کلیک‌بیت».
- **SEO outline**: «سرفصل‌های H2/H3 برای صفحه {entity} با پوشش قصد جست‌وجو (اطلاعاتی/ناوبری)، شامل بخش سوالات متداول بر اساس «People also ask»، تخمین تعداد کلمه هر بخش، فهرست کلیدواژه‌های LSI فارسی».
- **FAQ generator**: «۵ پرسش واقعی کاربران درباره {entity} + پاسخ ≤ ۶۰ کلمه، بدون تکرار متن بدنه».
- **Proofreader**: «متن را از نظر املا، نیم‌فاصله، علائم و یکدستی اصطلاحات بررسی کن؛ فقط اصلاحات را برگردان».
