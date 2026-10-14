"""Create only a disposable synthetic SQLite fixture, outside request execution."""
import sqlite3
import sys
from pathlib import Path

path = Path(sys.argv[1])
if path.exists():
    raise SystemExit('Refusing to overwrite an existing fixture.')
with sqlite3.connect(path) as db:
    db.executescript('''CREATE TABLE documents(id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, label TEXT NOT NULL);
    CREATE INDEX documents_tenant ON documents(tenant_id, id);
    CREATE TABLE events(id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, document_id INTEGER NOT NULL);
    CREATE INDEX events_document ON events(document_id, tenant_id);
    CREATE TABLE attempts(id INTEGER PRIMARY KEY);''')
    for tenant in (1, 2):
        for offset in range(1, 201):
            key = tenant * 1000 + offset
            db.execute('INSERT INTO documents VALUES (?, ?, ?)', (key, tenant, f'document{key}'))
            for event in range(3):
                db.execute('INSERT INTO events VALUES (?, ?, ?)', (key * 10 + event, tenant, key))
path.chmod(0o666)
