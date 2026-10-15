"""Real PNG tools, HTTP provenance, quarantine and resource-failure proof. No paid service."""
import datetime
import hashlib
import http.client
import json
import os
from pathlib import Path
import re
import shutil
import socket
import struct
import subprocess
import time
import uuid
import zlib

from process_support import capture, stop_group

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
PHP = shutil.which('php')
ENV = {'PATH': os.environ.get('PATH', '/usr/local/bin:/usr/bin:/bin')}
DOCKER = ['docker', '--host', 'unix:///var/run/docker.sock']


def require(condition, code):
    if not condition:
        raise RuntimeError(code)


def run(command, *, env=None, timeout=20):
    return capture(command, cwd=ROOT, env=ENV if env is None else env, timeout=timeout)


def php(path, *args, env=None, timeout=30):
    return run([PHP, '-d', 'display_errors=0', '-d', 'log_errors=0', str(HERE / path), *args], env=env, timeout=timeout)


def image_id(tag):
    code, out, err = run(DOCKER + ['image', 'inspect', '--format', '{{.Id}}', tag])
    require(code == 0 and err == b'' and re.fullmatch(rb'sha256:[0-9a-f]{64}\n', out), 'image_identity')
    return out.decode().strip()


def chunk(kind, data):
    return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data))


def png():
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 1, 1, 8, 2, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress(b'\0abc')) + chunk(b'IEND', b''))


def multipart(data, *, name='image', filename='client-name.png', media='image/png', extra=b''):
    boundary = 'phpthis-catalog-proof'
    body = (f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{filename}"\r\n'
            f'Content-Type: {media}\r\n\r\n').encode() + data + b'\r\n' + extra + f'--{boundary}--\r\n'.encode()
    return body, 'multipart/form-data; boundary=' + boundary


def request(port, method, path, body=None, media=None, credential='fixture-owner-one', extra=None):
    client = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
    headers = {} if credential is None else {'Authorization': 'Bearer ' + credential}
    if media is not None:
        headers['Content-Type'] = media
    if extra:
        headers.update(extra)
    try:
        client.request(method, path, body=body, headers=headers)
        response = client.getresponse()
        data = response.read(1_048_577)
        require(len(data) <= 1_048_576, 'response_limit')
        return response.status, dict((k.lower(), v) for k, v in response.getheaders()), data
    finally:
        client.close()


def server(root, entrypoint):
    with socket.socket() as reservation:
        reservation.bind(('127.0.0.1', 0))
        port = reservation.getsockname()[1]
    env = ENV | {'PHPTHIS_PNG_HTTP_ROOT': str(root)}
    command = [PHP, '-d', 'enable_post_data_reading=0', '-d', 'file_uploads=1',
               '-d', 'session.upload_progress.enabled=0', '-d', 'mbstring.encoding_translation=0',
               '-d', 'upload_tmp_dir=' + str(root / 'php-uploads'), '-d', 'display_errors=0',
               '-d', 'log_errors=0', '-d', 'memory_limit=32M', '-d', 'max_execution_time=15',
               '-t', str(HERE / 'public'), '-S', f'127.0.0.1:{port}', str(HERE / entrypoint)]
    child = subprocess.Popen(command, cwd=ROOT, env=env, stdin=subprocess.DEVNULL,
                             stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
    deadline = time.monotonic() + 5
    try:
        while time.monotonic() < deadline:
            require(child.poll() is None, 'server_startup')
            try:
                request(port, 'GET', '/catalog-images/' + '0' * 32, credential=None)
                return child, port
            except OSError:
                time.sleep(0.05)
        raise RuntimeError('server_startup_timeout')
    except BaseException:
        stop_group(child)
        raise


def main():
    require(PHP is not None, 'php_unavailable')
    image = image_id('phpthis-inspected-media:issue108')
    faults = image_id('phpthis-inspected-media:issue108-faults')
    update = image_id('phpthis-inspected-media:issue108-update')
    recovery = image_id('phpthis-inspected-media:issue108-recovery')
    code, out, err = php('tests/real-parser.php', image, faults, timeout=100)
    require(code == 0 and err == b'', 'real_parser_failed')
    parser_evidence = json.loads(out)
    root = ROOT / 'tmp' / ('png-proof-' + uuid.uuid4().hex)
    children = []
    worker_env = ENV | {'PHPTHIS_PNG_WORKER_ROOT': str(root), 'PHPTHIS_PNG_WORKER_IMAGE': image}
    passed = []
    try:
        code, out, err = php('tests/provision.php', str(root), image)
        require((code, out, err) == (0, b'PROVISIONED\n', b''), 'provision')
        (root / 'php-uploads').mkdir(mode=0o700)
        source = (HERE / 'public/index.php').read_text()
        bootstrap = str(HERE / 'bootstrap.php').replace('\\', '\\\\').replace("'", "\\'")
        policy = str(HERE / 'tests/SyntheticPolicy.php').replace('\\', '\\\\').replace("'", "\\'")
        source = source.replace('use CatalogImageRecipe\\DenyCatalogAccess;', 'use CatalogImageRecipe\\Tests\\SyntheticPolicy as DenyCatalogAccess;')
        source = source.replace("require dirname(__DIR__) . '/bootstrap.php';", f"require '{bootstrap}'; require '{policy}';")
        fixture_front = root / 'fixture-index.php'
        fixture_front.write_text(source)
        child, port = server(root, fixture_front)
        children.append(child)
        deny_child, deny_port = server(root, 'public/index.php')
        children.append(deny_child)

        def command(name, *args, expected=None, failed=False):
            code, out, err = php('console.php', name, *args, env=worker_env, timeout=30)
            if failed:
                require((code, out, err) == (1, b'', b'{"error":"command_failed"}\n'), 'command_failure_contract')
            else:
                require(code == 0 and err == b'', 'command_success_contract')
                require(json.loads(out) == {'command': name, 'outcome': expected}, 'command_outcome')

        def inspect(expected=None, failed=False):
            time.sleep(1.05)
            command('images:inspect-one', expected=expected, failed=failed)

        def state(identifier):
            code, out, err = php('tests/control.php', str(root), 'state', identifier)
            require(code == 0 and err == b'', 'state_control')
            return json.loads(out)

        def upload(data, **kwargs):
            body, media = multipart(data, **kwargs)
            status, headers, response = request(port, 'POST', '/catalog-images', body, media)
            require(status == 202, 'upload_acceptance')
            identifier = json.loads(response)['data']['image_id']
            require(re.fullmatch('[0-9a-f]{32}', identifier) is not None, 'server_generated_identity')
            return identifier

        data, media = multipart(png())
        require(request(deny_port, 'POST', '/catalog-images', data, media)[0] == 401, 'default_deny')
        require(request(port, 'POST', '/catalog-images', data, media, credential=None)[0] == 401, 'unauthenticated')
        require(request(port, 'POST', '/catalog-images', data, media, credential='fixture-forbidden')[0] == 403, 'forbidden')
        require(request(port, 'POST', '/catalog-images', data, media, credential='fixture-outage')[0] == 500, 'policy_outage')
        require(list((root / 'objects').iterdir()) == [], 'denial_has_no_storage')
        passed.append('default_deny_authentication_authorization_outage_no_storage')

        first = upload(png(), filename='../misleading.php', media='text/html')
        path = '/catalog-images/' + first
        require(state(first)['state'] == 'pending' and request(port, 'GET', path)[0] == 404, 'pending_not_served')
        require(request(deny_port, 'GET', path)[0] == 401, 'protected_read_default_deny')
        require(request(port, 'GET', path, credential='fixture-owner-two')[0] == 404, 'owner_binding')
        inspect('accepted')
        status, headers, body = request(port, 'GET', path, extra={'Range': 'bytes=0-1'})
        require(status == 200 and body == png(), 'accepted_bytes')
        require(headers['content-type'] == 'image/png' and headers['content-disposition'] == 'attachment; filename="catalog-image.png"'
                and headers['x-content-type-options'] == 'nosniff' and headers['cache-control'] == 'private, no-store'
                and headers['accept-ranges'] == 'none' and int(headers['content-length']) == len(body), 'download_headers')
        require((root / 'objects' / first / 'content').stat().st_mode & 0o777 == 0o600, 'retained_mode')
        require(list((root / 'php-uploads').iterdir()) == [], 'php_temp_cleanup')
        passed.append('native_upload_provenance_misleading_metadata_promotion_full_attachment')

        worker_env['PHPTHIS_PNG_WORKER_IMAGE'] = update
        command('images:activate-definition', expected='activated')
        require(request(port, 'GET', path)[0] == 404, 'old_definition_not_served')
        inspect('accepted')
        require(request(port, 'GET', path)[2] == png(), 'reinspection_promotion')
        passed.append('definition_switch_immediately_blocks_old_acceptance_and_reinspects')

        # Missing immutable tool image is operational uncertainty, not a content rejection.
        worker_env['PHPTHIS_PNG_WORKER_IMAGE'] = 'sha256:' + '0' * 64
        command('images:activate-definition', expected='activated')
        inspect(failed=True)
        require(state(first)['state'] == 'scanner_error' and state(first)['attempts'] == 1, 'outage_quarantine')
        require(request(port, 'GET', path)[0] == 404, 'outage_not_served')
        command('images:retry', first, expected='retry_pending')
        inspect(failed=True)
        require(state(first)['attempts'] == 2, 'retry_bound')
        command('images:retry', first, expected='ineligible')
        worker_env['PHPTHIS_PNG_WORKER_IMAGE'] = image
        command('images:activate-definition', failed=True)
        worker_env['PHPTHIS_PNG_WORKER_IMAGE'] = recovery
        command('images:activate-definition', expected='activated')
        inspect('accepted')
        passed.append('parser_outage_quarantine_explicit_retry_bound_definition_recovery')

        bad = upload(b'<svg/>', filename='looks-valid.png', media='image/png')
        inspect('rejected')
        require(request(port, 'GET', '/catalog-images/' + bad)[0] == 404 and state(bad)['state'] == 'rejected', 'rejected_not_served')
        passed.append('bytes_classify_content_independently_of_metadata')

        for payload, expected in [(b'', 400), (b'x' * 1_048_577, 413)]:
            body, media = multipart(payload)
            require(request(port, 'POST', '/catalog-images', body, media)[0] == expected, 'file_byte_boundary')
        maximum = upload(b'x' * 1_048_576)
        require(state(maximum)['bytes'] == 1_048_576 and request(port, 'GET', '/catalog-images/' + maximum)[0] == 404, 'inclusive_file_limit')
        body, media = multipart(png(), name='other')
        require(request(port, 'POST', '/catalog-images', body, media)[0] == 400, 'field_boundary')
        extra = b'--phpthis-catalog-proof\r\nContent-Disposition: form-data; name="caption"\r\n\r\nno\r\n'
        body, media = multipart(png(), extra=extra)
        require(request(port, 'POST', '/catalog-images', body, media)[0] == 400, 'text_field_boundary')
        require(len(list((root / 'objects').iterdir())) == 3, 'input_failure_no_storage')
        passed.append('native_byte_limits_zero_unknown_and_extra_fields_no_storage')

        # Expiry is checked on reads, independently of whether the cleanup pass has run.
        code, out, err = php('tests/control.php', str(root), 'expire', first)
        require((code, out, err) == (0, b'EXPIRED\n', b''), 'expiry_control')
        require(request(port, 'GET', path)[0] == 404, 'expired_not_served')
        command('images:expire-one', expected='expired')
        require(state(first) is None and not (root / 'objects' / first).exists(), 'expiry_releases_after_absence')
        passed.append('expiry_denies_serving_before_bounded_deletion')

        # Thirty more uploads reach the 32-item ceiling with rejected/pending items retained.
        for _ in range(30):
            upload(png())
        body, media = multipart(png())
        require(request(port, 'POST', '/catalog-images', body, media)[0] == 503, 'aggregate_quota')
        require(len(list((root / 'objects').iterdir())) == 32, 'quota_reservation_bound')
        passed.append('retained_rejections_consume_quota_and_33rd_upload_is_denied')
        require(list((root / 'php-uploads').iterdir()) == [], 'final_php_temp_cleanup')
    finally:
        for child in children:
            stop_group(child)
        if (root / 'catalog.sqlite').exists():
            code, out, err = php('tests/control.php', str(root), 'containers', image, timeout=120)
            require((code, out, err) == (0, b'CLEAN\n', b''), 'owned_container_cleanup')
        if root.exists():
            shutil.rmtree(root)

    code, version, err = run([PHP, '-r', 'echo PHP_VERSION;'])
    require(code == 0 and err == b'', 'host_php_version')
    sources = {}
    for path in sorted(HERE.rglob('*')):
        if path.is_file() and '__pycache__' not in path.parts and path.name != 'evidence.json':
            sources[str(path.relative_to(ROOT))] = hashlib.sha256(path.read_bytes()).hexdigest()
    evidence = {'schema_version': 1, 'verified_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
                'scope': 'disposable single-host reference; no production adoption or malware-safety claim',
                'host_php': version.decode(), 'inspector_php': '8.4.26', 'pngcheck': '4.0.1', 'zlib': '1.3.2',
                'image_id': image, 'update_image_id': update, 'recovery_image_id': recovery, 'fault_image_id': faults,
                'parser': parser_evidence, 'http_and_lifecycle': passed,
                'cleanup': 'owned processes, containers, upload temporaries and retained fixture removed', 'source_sha256': sources}
    (ROOT / 'tmp' / 'inspected-media-evidence.json').write_text(json.dumps(evidence, indent=2) + '\n')
    print('PASS real PNG inspection, HTTP quarantine and resource-failure reference')


if __name__ == '__main__':
    try:
        main()
    except Exception as failure:
        # Only code-owned assertion labels are emitted; process streams and payloads stay private.
        if type(failure) is RuntimeError and re.fullmatch('[a-z_]+', str(failure)):
            print('FAIL ' + str(failure))
        else:
            print('FAIL inspected_media_proof')
        raise SystemExit(1)
