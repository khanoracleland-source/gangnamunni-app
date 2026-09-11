"""Check actual PHP ordering and SQL parent validation without a production DB."""
import json
import re
import sqlite3
import subprocess
from pathlib import Path
root = Path(__file__).resolve().parents[2]
helper = root / 'hairwang_full/gangnamunni/includes/community_comments.php'
source = helper.read_text()
comments = [dict(id=1,parent_id=0),dict(id=2,parent_id=0),dict(id=3,parent_id=1),dict(id=4,parent_id=1),dict(id=5,parent_id=999)]
code = 'require ' + json.dumps(str(helper), ensure_ascii=False) + '; echo json_encode(community_comments_in_thread_order(json_decode(stream_get_contents(STDIN), true)));'
result = subprocess.run(['php','-n','-r',code],input=json.dumps(comments),text=True,capture_output=True,check=True)
ordered = json.loads(result.stdout)
assert [c['id'] for c in ordered] == [1,3,4,2,5]
assert ordered[-1]['parent_id'] == 0
sql = re.search(r"'(SELECT c.id[\s\S]*?)'",source).group(1).replace(' FOR UPDATE','')
db = sqlite3.connect(':memory:')
db.executescript('''CREATE TABLE gangnamunni_community_comments(id INTEGER PRIMARY KEY,post_id INTEGER);
CREATE TABLE gangnamunni_community_comment_threads(comment_id INTEGER PRIMARY KEY,parent_id INTEGER);
INSERT INTO gangnamunni_community_comments VALUES(1,10),(2,20),(3,10);
INSERT INTO gangnamunni_community_comment_threads VALUES(3,1);''')
assert db.execute(sql,(1,10)).fetchone() == (1,)
for args in [(1,20),(2,10),(3,10),(999,10)]:
    assert db.execute(sql,args).fetchone() is None, args
print('PASS: PHP thread order, legacy/orphan roots, cross-post/missing parents, nested-reply rejection.')
