"""Tiny helper to write Gutenberg blocks for replace batches (cites numbered from 6)."""
import html as _h
import json
import os


class Page:
    def __init__(self, start: int = 6):
        self.parts, self.cites, self.n = [], {}, start

    def cite(self, url: str) -> str:
        if url not in self.cites:
            self.cites[url] = self.n
            self.n += 1
        return f'<sup class="sa-cite"><a href="{_h.escape(url, quote=True)}">{self.cites[url]}</a></sup>'

    def h2(self, t):
        self.parts.append(f'<!-- wp:heading --><h2 class="wp-block-heading">{t}</h2><!-- /wp:heading -->')

    def h3(self, t):
        self.parts.append('<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">'
                          f'{t}</h3><!-- /wp:heading -->')

    def p(self, t, *urls):
        sup = "".join(self.cite(u) for u in urls)
        self.parts.append(f"<!-- wp:paragraph --><p>{t}{sup}</p><!-- /wp:paragraph -->")

    def html(self) -> str:
        return "\n\n".join(self.parts)


STRAY = "<!-- wp:paragraph --><p>```</p><!-- /wp:paragraph -->\n\n"


def spec(page: Page, words_original: int, modified_gmt: str, rewrites=None) -> dict:
    return {"words_original": words_original, "modified_gmt": modified_gmt,
            "rewrites": [[STRAY, ""]] + (rewrites or []),
            "insert_before_h2": "یک درخواست داریم", "blocks": page.html()}


def dump(add: dict, path: str):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    json.dump(add, open(path, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    print("ok", len(add))
