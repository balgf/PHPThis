"""Run the peer proof in an owned, network-isolated container using a local image."""
import argparse
import json
from pathlib import Path
import subprocess
import uuid

parser = argparse.ArgumentParser()
parser.add_argument('--image', default='phpthis-peer-proof:issue103')
args = parser.parse_args()
root = Path(__file__).resolve().parents[3]
name = 'phpthis-peer-proof-' + uuid.uuid4().hex
try:
    result = subprocess.run([
        'docker', 'run', '--rm', '--pull', 'never', '--name', name,
        '--network', 'none', '--read-only', '--user', '33:33', '--cap-drop', 'ALL',
        '--security-opt', 'no-new-privileges', '--pids-limit', '32', '--memory', '128m',
        '--cpus', '1', '--tmpfs', '/tmp:rw,nosuid,nodev,noexec,size=32m',
        '--mount', f'type=bind,src={root},dst=/framework,readonly',
        '--entrypoint', 'python3', args.image,
        '/framework/tools/runtime-proof/issue-103/probe.py',
    ], capture_output=True, text=True, timeout=45)
    if result.returncode != 0:
        raise RuntimeError(result.stderr)
    evidence = json.loads(result.stdout)
    evidence['image_id'] = subprocess.check_output(
        ['docker', 'image', 'inspect', '--format', '{{.Id}}', args.image], text=True, timeout=5,
    ).strip()
    print(json.dumps(evidence, indent=2))
finally:
    # The UUID belongs only to this run, including timeout/startup failure.
    subprocess.run(['docker', 'rm', '--force', name], capture_output=True, timeout=10, check=False)
