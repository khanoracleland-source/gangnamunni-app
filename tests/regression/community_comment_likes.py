"""Run the comment-like SQL rules in isolation; MySQL locking needs integration QA."""
import re
import sqlite3
from pathlib import Path
source = (Path(__file__).resolve().parents[2] / 'hairwang_full/gangnamunni/includes/community_comment_likes.php').read_text()
def sql(prefix):
    return re.search("'(" + re.escape(prefix) + "[^']*)'", source).group(1)
db = sqlite3.connect(':memory:')
db.executescript('''
CREATE TABLE gangnamunni_community_posts (id INTEGER PRIMARY KEY, status TEXT);
CREATE TABLE gangnamunni_community_comments (id INTEGER PRIMARY KEY, post_id INTEGER);
CREATE TABLE gangnamunni_community_comment_likes (comment_id INTEGER, user_id TEXT, PRIMARY KEY(comment_id,user_id));
INSERT INTO gangnamunni_community_posts VALUES (1,'active'),(2,'pending');
INSERT INTO gangnamunni_community_comments VALUES (10,1),(20,2);
''')
insert = sql('INSERT IGNORE').replace('INSERT IGNORE', 'INSERT OR IGNORE')
delete = sql('DELETE FROM')
counts = sql('SELECT l.comment_id')
check = re.search(r'"(SELECT c.id[\s\S]*?)"', source).group(1).replace(' FOR UPDATE', '')
assert db.execute(check,(10,1)).fetchone()
assert not db.execute(check,(10,2)).fetchone(), 'Cross-post comment accepted'
assert not db.execute(check,(20,2)).fetchone(), 'Unpublished post accepted'
assert not db.execute(check,(999,1)).fetchone()
for user in ['alice','alice','bob']:
    db.execute(insert,(10,user))
assert db.execute(counts,('alice',1)).fetchall() == [(10,2,1)]
assert db.execute(counts,('guest',1)).fetchall() == [(10,2,0)]
for _ in range(2): db.execute(delete,(10,'alice'))
assert db.execute(counts,('alice',1)).fetchall() == [(10,1,0)]
assert db.execute(counts,('bob',1)).fetchall() == [(10,1,1)]
print('PASS: duplicate requests, independent users, unlike, persisted counts, post ownership and visibility.')
