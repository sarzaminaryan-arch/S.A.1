import unittest

from editorial_cleanup import clean_markdown, clean_php_content


class EditorialCleanupTest(unittest.TestCase):
    def test_removes_standalone_editor_note_and_keeps_facts(self):
        text = (
            "# عنوان\n\n"
            "شهر در دامنهٔ کوهستان قرار دارد و منبع رسمی باید بازبینی شود.\n\n"
            "این مقاله برای بازبینی و ویرایش انسانی آماده شده است.\n"
        )
        cleaned, notes = clean_markdown(text)
        self.assertIn("شهر در دامنهٔ کوهستان قرار دارد", cleaned)
        self.assertNotIn("آماده شده است", cleaned)
        self.assertIn("آمیخته", " ".join(notes))

    def test_removes_standalone_verification_note(self):
        text = "داده‌ها پیش از انتشار نهایی باید با مرکز آمار و وزارت کشور تطبیق داده شوند."
        cleaned, notes = clean_markdown(text)
        self.assertEqual(cleaned.strip(), "")
        self.assertTrue(notes)

    def test_removes_repeated_paragraph_only_within_article(self):
        para = "این یک بند کاملاً تکراری است که بیش از هشت واژه دارد و باید یک بار بماند."
        cleaned, notes = clean_markdown(f"{para}\n\n{para}\n")
        self.assertEqual(cleaned.count(para), 1)
        self.assertTrue(any("تکراری" in note for note in notes))

    def test_removes_google_source_and_renumbers_rest(self):
        text = (
            "## منابع\n\n"
            "1. منبع اول — https://example.org/one\n"
            "2. منبع بی‌ارزش — https://www.google.com/search?q=x\n"
            "3. منبع سوم — https://example.net/three\n"
        )
        cleaned, _ = clean_markdown(text)
        self.assertNotIn("google.com", cleaned)
        self.assertIn("2. منبع سوم", cleaned)
        self.assertNotIn("3. منبع سوم", cleaned)

    def test_mixed_editorial_sentence_is_not_deleted(self):
        text = "شهر در دامنهٔ کوهستان قرار دارد؛ اطلاعات باید پیش از انتشار با مرکز آمار تطبیق داده شود."
        cleaned, notes = clean_markdown(text)
        self.assertEqual(cleaned.strip(), text)
        self.assertTrue(notes)

    def test_php_only_cleans_article_content_heredoc(self):
        source = "<?php\n$meta = 'keep';\n'content' => <<<'ART'\n<p>یادداشت ویراستار</p>\nART,\n"
        cleaned, notes = clean_php_content(source)
        self.assertIn("$meta = 'keep';", cleaned)
        self.assertNotIn("یادداشت ویراستار", cleaned)
        self.assertTrue(notes)


if __name__ == "__main__":
    unittest.main()
