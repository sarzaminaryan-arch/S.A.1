# Pending live checks — owner-run only

No requests were sent to the live site or to external URLs in this session. Run `tools/verify_live.py` yourself when an approved network environment is available; it must use GET, a 15-second timeout, and at least 1.5 seconds between requests. Do not add guessed redirects before those results are reviewed.

## Redirect snapshot findings

The offline crawl summary records **33 redirects** (31 `province_tax` redirects with no exact source URL in the tracked repository, one `/city/ijrud/` redirect, and one sitemap redirect), plus **3 timeouts**. The 31 taxonomy source URLs are not enumerated in the checkout; no `ostan/{slug}` source or target was inferred. The explicit `/city/ijrud/` mapping remains in `data/redirects.php` because the file documents owner-approved county identity evidence. The sitemap redirect has no content URL destination and is not mapped here.

Timed-out URLs (status and final destination unconfirmed):

- `/city/sabzevar/`
- `/city/narmashir/`
- `/attraction/babahur-forest-park-dorud/`

## Targets outside the tracked manifest

The snapshot notes **29 targets/links outside the manifest**, comprising the following exact paths, unmanifested relative-link tokens, and 13 additional ordinary targets that the report does not enumerate:

- `/city/dorud/` — snapshot says `/city/dorud-city/` exists in its inventory, but `/city/dorud/` status and final destination are unknown.
- `/editorial-policy/` — the tracked inventory contains `/policy/`, but no equivalence or redirect is proven.
- `/city/talqan/`
- `/city/sojablogh/`
- 12 relative `W_*`/unmanifested href tokens, recorded individually in `reports/url-audit-offline.csv`.
- 13 ordinary targets aggregated without exact URLs in the snapshot.

None of these has been assigned a guessed destination. Keep `/city/dorud/` and `/editorial-policy/` pending until the owner-run GET check confirms status and final URL. Do not change the titles “شهرستان خرمدره” or “شهرستان ایجرود”; only report them for editorial review.

See `reports/url-audit-offline.csv` for the detailed offline evidence and `reports/http-hosts-pending.md` for HTTP hosts awaiting protocol review.
