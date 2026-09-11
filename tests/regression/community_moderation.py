"""Exercise the PHP queries against an isolated database (no production data).
Run: python3 tests/regression/community_moderation.py
This tests SQL state transitions; MySQL DDL and web sessions require integration QA.
"""
import re
import sqlite3
from pathlib import Path

root = Path(__file__).resolve().parents[2] / 'hairwang_full/gangnamunni'
write = (root / 'community_write.php').read_text()
detail = (root / 'detail_community.php').read_text()
admin = (root / 'admin_community_posts.php').read_text()

def query(source, prefix):
    return next(q for q in re.findall(r'"([^"\n]+)"', source) if q.startswith(prefix))

con = sqlite3.connect(':memory:')
con.execute('CREATE TABLE gangnamunni_community_posts (id INTEGER PRIMARY KEY, mb_id TEXT, author_name TEXT, category TEXT, title TEXT, content TEXT, photos TEXT, status TEXT)')
insert = query(write, 'INSERT INTO gangnamunni_community_posts')
edit = query(write, 'UPDATE gangnamunni_community_posts SET category=')
# The PHP query ends with a concatenated community_content_condition() call;
# '1=1' is what it returns when nobody has blocked anybody (this test's scope).
visible = query(detail, 'SELECT id FROM gangnamunni_community_posts WHERE') + '1=1'
moderate = query(admin, 'UPDATE gangnamunni_community_posts SET status=')
con.execute(insert, ('owner', 'Author', '일상', 'Title', 'Long enough content', None))
assert con.execute(visible, (1,)).fetchone() is None, 'Pending post leaked'
assert con.execute(moderate, ('active', 1)).rowcount == 1
assert con.execute(visible, (1,)).fetchone() == (1,)
assert con.execute(edit, ('일상', 'Changed', 'Updated content', None, 1, 'other')).rowcount == 0
assert con.execute(visible, (1,)).fetchone() == (1,)
assert con.execute(edit, ('일상', 'Changed', 'Updated content', None, 1, 'owner')).rowcount == 1
assert con.execute(visible, (1,)).fetchone() is None, 'Edit bypassed review'
assert con.execute(moderate, ('rejected', 1)).rowcount == 1
assert con.execute(visible, (1,)).fetchone() is None
assert con.execute(moderate, ('active', 1)).rowcount == 0, 'Stale approval accepted'
assert con.execute(edit, ('일상', 'Resubmit', 'Updated content', None, 1, 'owner')).rowcount == 1
assert con.execute(moderate, ('active', 1)).rowcount == 1
con.execute("UPDATE gangnamunni_community_posts SET status='deleted' WHERE id=1")
assert con.execute(edit, ('일상', 'Restore', 'Updated content', None, 1, 'owner')).rowcount == 0
assert con.execute(visible, (1,)).fetchone() is None
print('PASS: pending → approved → edited/pending → rejected → resubmitted → approved; ownership and deleted guards.')
