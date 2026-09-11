"""Build a conservative canonical sitemap from the supplied SEO ZIP.

Usage: python3 scripts/build_seoulbeauty_sitemap.py /path/to/seoulbeauty_SEO.zip
The export is a snapshot, not a complete database inventory. Unverified export
timestamps are deliberately omitted; never substitute build time for lastmod.
"""
import argparse
from pathlib import Path
from urllib.parse import parse_qs, urlencode, urlsplit
import xml.etree.ElementTree as ET
from zipfile import ZipFile

BASE = 'https://seoulbeauty.app/gangnamunni/'
NS = 'http://www.sitemaps.org/schemas/sitemap/0.9'
DETAILS = {'detail.php', 'doctor_detail.php', 'event_detail.php'}
PAGES = {
    'reviews.php', 'hospitals.php', 'events.php', 'tips.php', 'columns.php',
    'price_compare.php', 'treatments.php', 'hospital_ranking.php', 'qna.php',
    'terms_of_service.php', 'privacy_policy.php', 'community.php',
    'community_guidelines.php',
}


def build(source):
    urls = {BASE}
    for node in ET.fromstring(source).findall(f'{{{NS}}}url'):
        loc = node.findtext(f'{{{NS}}}loc', '')
        parts = urlsplit(loc)
        if parts.scheme != 'https' or parts.netloc != 'seoulbeauty.app':
            continue
        if not parts.path.startswith('/hairwang/gangnamunni/'):
            continue
        page = parts.path.removeprefix('/hairwang/gangnamunni/')
        query = parse_qs(parts.query)
        if page in DETAILS:
            ids = query.get('id', [])
            if len(ids) == 1 and ids[0].isascii() and ids[0].isdigit() and int(ids[0]) > 0:
                urls.add(BASE + page + '?' + urlencode({'id': int(ids[0])}))
        elif page in PAGES:
            # Filter/sort landing pages need independent content review first.
            urls.add(BASE + page)
    ET.register_namespace('', NS)
    root = ET.Element(f'{{{NS}}}urlset')
    for url in sorted(urls):
        ET.SubElement(ET.SubElement(root, f'{{{NS}}}url'), f'{{{NS}}}loc').text = url
    ET.indent(root, space='  ')
    return ET.tostring(root, encoding='utf-8', xml_declaration=True) + b'\n'


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive', type=Path)
    args = parser.parse_args()
    with ZipFile(args.archive) as archive:
        output = build(archive.read('02_stiemap/sitemap.xml'))
    target = Path(__file__).resolve().parents[1] / 'hairwang_full/sitemap.xml'
    target.write_bytes(output)
    print(f'{target}: {output.count(b"<loc>")} canonical URLs')
