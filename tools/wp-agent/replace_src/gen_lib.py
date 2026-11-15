"""Tiny helper to write Gutenberg blocks for replace batches (cites numbered from 6)."""
import html as _h
import json
import os


FA_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")


class Page:
    """start: first new cite number; fa: Persian digits like the base page;
    classic: plain HTML (no block comments) for pages written in the classic editor."""

    def __init__(self, start: int = 6, fa: bool = False, classic: bool = False):
        self.parts, self.cites, self.n = [], {}, start
        self.fa, self.classic = fa, classic

    def cite(self, url: str) -> str:
        if url not in self.cites:
            self.cites[url] = self.n
            self.n += 1
        num = str(self.cites[url])
        if self.fa:
            num = num.translate(FA_DIGITS)
        return f'<sup class="sa-cite"><a href="{_h.escape(url, quote=True)}">{num}</a></sup>'

    def h2(self, t):
        if self.classic:
            self.parts.append(f"<h2>{t}</h2>")
            return
        self.parts.append(f'<!-- wp:heading --><h2 class="wp-block-heading">{t}</h2><!-- /wp:heading -->')

    def h3(self, t):
        if self.classic:
            self.parts.append(f"<h3>{t}</h3>")
            return
        self.parts.append('<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">'
                          f'{t}</h3><!-- /wp:heading -->')

    def p(self, t, *urls):
        sup = "".join(self.cite(u) for u in urls)
        if self.classic:
            self.parts.append(f"<p>{t}{sup}</p>")
            return
        self.parts.append(f"<!-- wp:paragraph --><p>{t}{sup}</p><!-- /wp:paragraph -->")

    def ul(self, items):
        """items: list of (html, [urls]) tuples or plain strings."""
        lis = []
        for it in items:
            t, urls = (it, []) if isinstance(it, str) else (it[0], list(it[1:]))
            sup = "".join(self.cite(u) for u in urls)
            if self.classic:
                lis.append(f"<li>{t}{sup}</li>")
            else:
                lis.append(f"<!-- wp:list-item --><li>{t}{sup}</li><!-- /wp:list-item -->")
        if self.classic:
            self.parts.append("<ul>" + "".join(lis) + "</ul>")
            return
        self.parts.append('<!-- wp:list --><ul class="wp-block-list">' + "".join(lis) + "</ul><!-- /wp:list -->")

    def table(self, head, rows):
        """rows: list of (cells..., url_or_None) — the last item cites the last cell."""
        th = "".join(f"<th>{h}</th>" for h in head)
        trs = []
        for row in rows:
            *cells, url = row
            if url:
                cells[-1] = cells[-1] + " " + self.cite(url)
            trs.append("<tr>" + "".join(f"<td>{c}</td>" for c in cells) + "</tr>")
        tbl = f"<table><thead><tr>{th}</tr></thead><tbody>{''.join(trs)}</tbody></table>"
        if self.classic:
            self.parts.append(tbl)
            return
        self.parts.append(f'<!-- wp:table --><figure class="wp-block-table">{tbl}</figure><!-- /wp:table -->')

    def html(self) -> str:
        return "\n\n".join(self.parts)


STRAY = "<!-- wp:paragraph --><p>```</p><!-- /wp:paragraph -->\n\n"


def spec(page: Page, words_original: int, modified_gmt: str, rewrites=None,
         stray: bool = True) -> dict:
    """stray=False for bases that no longer contain the stray ``` paragraph."""
    return {"words_original": words_original, "modified_gmt": modified_gmt,
            "rewrites": ([[STRAY, ""]] if stray else []) + (rewrites or []),
            "insert_before_h2": "یک درخواست داریم", "blocks": page.html()}


def dump(add: dict, path: str):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    json.dump(add, open(path, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    print("ok", len(add))


REQUEST_H2 = "یک درخواست داریم"


def full_spec(page: Page, base_html: str, words_original: int, modified_gmt: str) -> dict:
    """Whole-body rewrite: drop everything before the reader-request H2 and put the
    new article there (the request section itself is kept as it is)."""
    import re
    m = [x for x in re.finditer(r"(?:<!-- wp:heading[^>]*-->\s*)?<h2[^>]*>(.*?)</h2>", base_html, re.S)
         if REQUEST_H2 in x.group(1)]
    if len(m) != 1:
        raise ValueError("request H2 not found exactly once")
    head = base_html[:m[0].start()]
    return {"words_original": words_original, "modified_gmt": modified_gmt,
            "rewrites": [[head, ""]] if head else [], "insert_before_h2": REQUEST_H2,
            "blocks": page.html()}
