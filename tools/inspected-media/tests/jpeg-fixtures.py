"""Regenerate synthetic JPEG seeds with cjpeg 3.2.0; never used by composer check."""
import base64
import hashlib
import json
import shutil
import subprocess
from pathlib import Path

encoder = shutil.which('cjpeg')
if not encoder:
    raise SystemExit('cjpeg 3.2.0 required')
version = subprocess.run([encoder, '-version'], capture_output=True, timeout=5, check=True)
if (version.stdout + version.stderr).decode().strip() != 'libjpeg-turbo version 3.2.0 (build 20260630)':
    raise SystemExit('Unexpected encoder version')
cases = [
    ('minimum', 1, 1, []), ('sampling444', 16, 16, ['-sample', '1x1']),
    ('sampling422', 16, 16, ['-sample', '2x1']), ('sampling420', 16, 16, []),
    ('maximum', 1024, 1024, []), ('optimized', 16, 16, ['-optimize']),
    ('quality100', 16, 16, ['-quality', '100', '-baseline']),
    ('progressive', 16, 16, ['-progressive']), ('grayscale', 16, 16, ['-grayscale']),
    ('rgb', 16, 16, ['-rgb']), ('arithmetic', 16, 16, ['-arithmetic']),
    ('sampling440', 16, 16, ['-sample', '1x2']), ('sampling411', 16, 16, ['-sample', '4x1']),
    ('restart', 32, 32, ['-restart', '1B']), ('wide', 1025, 1, []), ('tall', 1, 1025, []),
]
fixtures = {}
for name, width, height, options in cases:
    ppm = f'P6\n{width} {height}\n255\n'.encode() + b'\x17\x71\xc9' * (width * height)
    result = subprocess.run([encoder, '-quality', '90', *options], input=ppm, capture_output=True, timeout=5, check=True)
    if result.stderr:
        raise SystemExit('Unexpected encoder diagnostics')
    fixtures[name] = {'sha256': hashlib.sha256(result.stdout).hexdigest(), 'base64': base64.b64encode(result.stdout).decode()}
Path(__file__).with_name('jpeg-fixtures.json').write_text(json.dumps(fixtures, indent=2) + '\n')
