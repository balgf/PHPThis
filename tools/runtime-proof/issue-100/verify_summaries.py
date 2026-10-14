"""Verify one isolated canonical summary per checked response, including rejected work."""
import json
import sys
from pathlib import Path

report = json.loads(Path(sys.argv[1]).read_text())
log = Path(sys.argv[2]).read_text()
summaries = {}
for line in log.splitlines():
    start = line.find('{"schema_version":1,"event":"application.request_summary"')
    if start < 0:
        continue
    item = json.loads(line[start:])
    identity = item['correlation_id']
    assert identity not in summaries, 'Duplicate terminal summary.'
    summaries[identity] = item
for record in report['records']:
    summary = summaries[record['id']]
    assert summary['response_status'] == record['status']
    assert summary['query_count'] == record['queries'], (record, summary)
    assert summary['database_sources'][0]['budget_used'] == record['queries']
    assert summary['query_budget_exceeded'] == (record['path'] == '/budget')
    assert 'PRIVATE_' not in json.dumps(summary)
    assert record['path'] not in json.dumps(summary)
print(json.dumps({'passed': True, 'matched_summaries': len(report['records']), 'unique_summaries': len(summaries)}))
