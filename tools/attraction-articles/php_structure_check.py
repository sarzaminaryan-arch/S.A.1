#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Tokenise PHP well enough to prove a data file's structure is closed.

Handles: single/double-quoted strings, // and # and /* */ comments, heredoc/nowdoc
(<<<ID ... ID;) and plain code. Reports unbalanced () [] {}, unterminated strings,
unterminated heredocs and stray heredoc markers — the classes of mistake a
generator can introduce into data/ready-articles.php.
"""

import re
import sys

HEREDOC = re.compile(r"<<<[ \t]*(?:'([A-Za-z_][A-Za-z0-9_]*)'|\"([A-Za-z_][A-Za-z0-9_]*)\"|([A-Za-z_][A-Za-z0-9_]*))[ \t]*\r?\n")


def scan(path):
    text = open(path, encoding="utf-8", errors="replace").read()
    i = 0
    n = len(text)
    line = 1
    line_of = [0]
    for ch in text:
        line_of.append(line)
        if ch == "\n":
            line += 1

    def at(pos):
        return line_of[pos]

    stack = []
    errors = []
    strings = 0

    while i < n:
        ch = text[i]

        if ch == "\n":
            i += 1
            continue

        # comments
        if ch == "/" and i + 1 < n and text[i + 1] == "/":
            j = text.find("\n", i)
            i = n if j == -1 else j
            continue
        if ch == "#":
            j = text.find("\n", i)
            i = n if j == -1 else j
            continue
        if ch == "/" and i + 1 < n and text[i + 1] == "*":
            j = text.find("*/", i + 2)
            if j == -1:
                errors.append("line %d: unterminated block comment" % at(i))
                break
            i = j + 2
            continue

        # heredoc / nowdoc
        if text.startswith("<<<", i):
            match = HEREDOC.match(text, i)
            if not match:
                errors.append("line %d: malformed heredoc opener" % at(i))
                break
            marker = match.group(1) or match.group(2) or match.group(3)
            start_body = match.end()
            close = re.search(r"^[ \t]*" + re.escape(marker) + r"(?=[,;)\s]|$)", text[start_body:], re.M)
            if not close:
                errors.append("line %d: unterminated heredoc %s" % (at(i), marker))
                break
            end = start_body + close.start()
            body = text[start_body:end]
            for offset, body_line in enumerate(body.split("\n")):
                if body_line.startswith(marker):
                    errors.append("line %d: heredoc body line starts with the marker" % (at(start_body) + offset))
            i = start_body + close.end()
            continue

        # single-quoted string
        if ch == "'":
            j = i + 1
            while j < n:
                if text[j] == "\\":
                    j += 2
                    continue
                if text[j] == "'":
                    break
                j += 1
            if j >= n:
                errors.append("line %d: unterminated single-quoted string" % at(i))
                break
            strings += 1
            i = j + 1
            continue

        # double-quoted string
        if ch == '"':
            j = i + 1
            while j < n:
                if text[j] == "\\":
                    j += 2
                    continue
                if text[j] == '"':
                    break
                j += 1
            if j >= n:
                errors.append("line %d: unterminated double-quoted string" % at(i))
                break
            strings += 1
            i = j + 1
            continue

        if ch in "([{":
            stack.append((ch, at(i)))
            i += 1
            continue
        if ch in ")]}":
            pairs = {")": "(", "]": "[", "}": "{"}
            if not stack:
                errors.append("line %d: closing %s without opener" % (at(i), ch))
                break
            opener, oline = stack.pop()
            if opener != pairs[ch]:
                errors.append("line %d: %s closes %s opened on line %d" % (at(i), ch, opener, oline))
                break
            i += 1
            continue
        i += 1

    for opener, oline in stack:
        errors.append("line %d: unclosed %s" % (oline, opener))
    return errors, strings


if __name__ == "__main__":
    bad = 0
    for path in sys.argv[1:]:
        errors, strings = scan(path)
        if errors:
            bad = 1
            print("FAIL %s" % path)
            for err in errors[:12]:
                print("   ", err)
        else:
            print("OK   %s (%d strings)" % (path, strings))
    sys.exit(bad)
