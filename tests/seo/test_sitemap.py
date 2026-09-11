import sys
sys.dont_write_bytecode = True
import importlib.util
from pathlib import Path
import unittest
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('sitemap', ROOT / 'scripts/build_seoulbeauty_sitemap.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class SitemapTest(unittest.TestCase):
    def test_canonicalization_and_untrusted_entries(self):
        urls = [
            'https://seoulbeauty.app/hairwang/gangnamunni/detail.php?id=8&amp;tab=reviews',
            'https://seoulbeauty.app/hairwang/gangnamunni/detail.php?id=8',
            'https://seoulbeauty.app/hairwang/gangnamunni/detail.php?id=-2',
            'https://seoulbeauty.app/hairwang/gangnamunni/auth/login.php',
            'https://other.test/hairwang/gangnamunni/events.php',
            'https://seoulbeauty.app/hairwang/gangnamunni/hospitals.php?sort=rating',
        ]
        source = ('<urlset xmlns="' + module.NS + '">' + ''.join(
            '<url><loc>' + url + '</loc><lastmod>2026-09-10</lastmod></url>' for url in urls
        ) + '</urlset>').encode()
        result = module.build(source)
        self.assertEqual([node[0].text for node in ET.fromstring(result)], [
            module.BASE, module.BASE + 'detail.php?id=8', module.BASE + 'hospitals.php',
        ])
        self.assertNotIn(b'lastmod', result)

    def test_published_snapshot(self):
        data = (ROOT / 'hairwang_full/sitemap.xml').read_bytes()
        urls = [node[0].text for node in ET.fromstring(data)]
        self.assertEqual(len(urls), len(set(urls)))
        self.assertIn(module.BASE, urls)
        self.assertTrue(all(url.startswith(module.BASE) for url in urls))
        for excluded in [b'lastmod', b'priority', b'login.php', b'tab=', b'sort=', b'hairwang.com']:
            self.assertNotIn(excluded, data)


if __name__ == '__main__':
    unittest.main()
