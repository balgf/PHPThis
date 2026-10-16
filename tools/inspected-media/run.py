"""Real PNG/JPEG/MP4 tools, HTTP provenance, quarantine and resource-failure proof. No paid service."""
import base64
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


def checked_parser_result(code, out, err):
    if code != 0 or err != b'':
        # Accept only this fixture's finite case/stage protocol; never echo a
        # PHP exception, Docker stream, pathname, image reference or input byte.
        failure = re.fullmatch(
            rb'REAL_PARSER_FAIL (setup_(?:arguments|definition|fixtures|fault_definition|report)'
            rb'|format_(?:valid_rgb|valid_rgba|large_pipe_input|maximum_dimensions|svg|missing_iend'
            rb'|truncated_idat|bad_crc|short_row|missing_row|extra_decoded|expansion_bomb|bad_filter'
            rb'|oversize_dimension|forbidden_metadata|animation|trailing_bytes|file_byte_limit)_(?:inspect|cleanup)'
            rb'|jpeg_(?:minimum|sampling444|sampling422|sampling420|maximum|optimized|quality100|jfif102|density'
            rb'|progressive|grayscale|rgb|arithmetic|sampling440|sampling411|restart|wide|tall|zero_width|zero_height'
            rb'|precision12|four_components|component_order|scan_order|spectral_selection|successive_approximation'
            rb'|exif|icc|xmp|comment|thumbnail|jfif_version|density_unit|zero_density|no_jfif|duplicate_jfif'
            rb'|leading_bytes|trailing_bytes|concatenated|no_eoi|truncated_header|short_length|long_length|marker_padding'
            rb'|entropy_marker|empty_entropy|zero_quantization|wide_quantization|missing_quantization|duplicate_quantization'
            rb'|quantization_after_frame|duplicate_huffman|huffman_before_frame|invalid_huffman_selector|huffman_symbol_overflow'
            rb'|missing_huffman|no_sos|byte_limit|png|empty|truncated_entropy|corrupt_entropy)_(?:inspect|cleanup)'
            rb'|mp4_(?:valid_320x240|minimum_frame|max_dimensions_duration_frames|24fps_single_frame|25fps|duration_excess|frame_rate_excess|width_excess|height_excess|high_profile|fragmented|title_metadata|b_frames|seed|empty|oversized|truncated|trailing|zero_box|large_box|wrong_brand|unknown_box|external_reference|audio_track|high_profile_declaration|second_sps|second_description|duration_mismatch|extra_timing_entry|zero_delta|sample_count_excess|outside_offset|size_count_mismatch|sample_size_excess|rotation|non_square_pixels|container_crop|hidden_sps_width|hidden_sps_height|sps_range|png|jpeg|corrupt_frame)_(?:inspect|cleanup)'
            rb'|mp4_probe_(?:stdout|stderr|timeout|exit)_(?:inspect|cleanup)'
            rb'|(?:(?:jpeg|mp4)_)?resource_(?:exit|output|cpu|memory|memory_small|memory_kill|timeout)_(?:inspect|container_state|termination|cleanup)'
            rb'|(?:(?:jpeg|mp4)_)?resource_timeout_elapsed) cleanup=(not_started|passed|failed)\n', err)
        if code == 1 and out == b'' and failure is not None:
            raise RuntimeError('real_parser_' + failure[1].decode() + '_cleanup_' + failure[2].decode())
        raise RuntimeError('real_parser_failed')
    return json.loads(out)


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
    code, out, err = php('tests/real-parser.php', image, faults, timeout=240)
    parser_evidence = checked_parser_result(code, out, err)
    probe_faults = [image_id('phpthis-inspected-media:issue141-probe-' + mode) for mode in ('stdout', 'stderr', 'timeout', 'exit')]
    code, out, err = php('tests/probe-failures.php', *probe_faults, timeout=60)
    parser_evidence.update(checked_parser_result(code, out, err))
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

        def upload(data, *, route='/catalog-images', **kwargs):
            if route == '/catalog-video-clips':
                kwargs.setdefault('name', 'clip')
            body, media = multipart(data, **kwargs)
            status, headers, response = request(port, 'POST', route, body, media)
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

        jpeg_seed = json.loads((HERE / 'tests/jpeg-fixtures.json').read_text())['sampling420']
        jpeg = base64.b64decode(jpeg_seed['base64'], validate=True)
        require(hashlib.sha256(jpeg).hexdigest() == jpeg_seed['sha256'], 'jpeg_seed_digest')
        data, media = multipart(jpeg)
        require(request(deny_port, 'POST', '/catalog-jpeg-images', data, media)[0] == 401, 'jpeg_default_deny')
        require(request(port, 'POST', '/catalog-jpeg-images', data, media, credential=None)[0] == 401, 'jpeg_unauthenticated')
        require(request(port, 'POST', '/catalog-jpeg-images', data, media, credential='fixture-forbidden')[0] == 403, 'jpeg_forbidden')
        require(request(port, 'POST', '/catalog-jpeg-images', data, media, credential='fixture-outage')[0] == 500, 'jpeg_policy_outage')
        require(list((root / 'objects').iterdir()) == [], 'jpeg_denial_has_no_storage')
        passed.append('jpeg_default_deny_authentication_authorization_outage_no_storage')

        mp4_seed = json.loads((HERE / 'tests/mp4-fixtures.json').read_text())['valid-320x240']
        mp4 = base64.b64decode(mp4_seed['base64'], validate=True)
        require(hashlib.sha256(mp4).hexdigest() == mp4_seed['sha256'], 'mp4_seed_digest')
        data, media = multipart(mp4, name='clip')
        require(request(deny_port, 'POST', '/catalog-video-clips', data, media)[0] == 401, 'mp4_default_deny')
        for credential, expected in [(None, 401), ('fixture-forbidden', 403), ('fixture-outage', 500)]:
            require(request(port, 'POST', '/catalog-video-clips', data, media, credential=credential)[0] == expected, 'mp4_authority')
        require(list((root / 'objects').iterdir()) == [], 'mp4_denial_has_no_storage')
        passed.append('mp4_denial_and_policy_failure_have_no_storage')

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

        jpeg_id = upload(jpeg, route='/catalog-jpeg-images', filename='../fake.png', media='text/html')
        jpeg_path = '/catalog-jpeg-images/' + jpeg_id
        require(state(jpeg_id)['format'] == 'jpeg' and state(first)['format'] == 'png', 'persisted_route_format')
        require(request(port, 'GET', jpeg_path)[0] == 404, 'jpeg_pending_not_served')
        inspect('accepted')
        status, headers, body = request(port, 'GET', jpeg_path, extra={'Range': 'bytes=0-1'})
        require(status == 200 and body == jpeg, 'jpeg_original_bytes')
        require(headers['content-type'] == 'image/jpeg' and headers['content-disposition'] == 'attachment; filename="catalog-image.jpg"'
                and headers['x-content-type-options'] == 'nosniff' and headers['cache-control'] == 'private, no-store'
                and headers['accept-ranges'] == 'none' and int(headers['content-length']) == len(jpeg), 'jpeg_download_headers')
        require(request(port, 'GET', '/catalog-images/' + jpeg_id)[0] == 404
                and request(port, 'GET', '/catalog-jpeg-images/' + first)[0] == 404, 'wrong_format_route')
        require(request(port, 'GET', jpeg_path, credential='fixture-owner-two')[0] == 404, 'jpeg_owner_binding')
        require(request(deny_port, 'GET', jpeg_path)[0] == 401, 'jpeg_protected_read')
        require((root / 'objects' / jpeg_id / 'content').stat().st_mode & 0o777 == 0o600, 'jpeg_private_mode')
        passed.append('jpeg_native_upload_misleading_labels_fixed_attachment_owner_and_format_binding')

        mp4_id = upload(mp4, route='/catalog-video-clips', filename='../fake.php', media='text/html')
        mp4_path = '/catalog-video-clips/' + mp4_id
        require(state(mp4_id)['format'] == 'mp4' and request(port, 'GET', mp4_path)[0] == 404, 'mp4_pending_format')
        inspect('accepted')
        status, headers, body = request(port, 'GET', mp4_path, extra={'Range': 'bytes=0-1'})
        require(status == 200 and body == mp4, 'mp4_exact_original_bytes')
        require(headers['content-type'] == 'video/mp4' and headers['content-disposition'] == 'attachment; filename="catalog-clip.mp4"'
                and headers['x-content-type-options'] == 'nosniff' and headers['cache-control'] == 'private, no-store'
                and headers['accept-ranges'] == 'none' and int(headers['content-length']) == len(mp4), 'mp4_download_headers')
        require(request(port, 'GET', mp4_path, credential='fixture-owner-two')[0] == 404
                and request(deny_port, 'GET', mp4_path)[0] == 401, 'mp4_read_authority')
        require(request(port, 'GET', '/catalog-images/' + mp4_id)[0] == 404
                and request(port, 'GET', '/catalog-jpeg-images/' + mp4_id)[0] == 404
                and request(port, 'GET', '/catalog-video-clips/' + first)[0] == 404
                and request(port, 'GET', '/catalog-video-clips/' + jpeg_id)[0] == 404, 'mp4_cross_format_reads')
        require((root / 'objects' / mp4_id / 'content').stat().st_mode & 0o777 == 0o600, 'mp4_private_mode')
        passed.append('mp4_native_upload_quarantine_original_attachment_and_owner_format_binding')

        worker_env['PHPTHIS_PNG_WORKER_IMAGE'] = update
        command('images:activate-definition', expected='activated')
        require(request(port, 'GET', path)[0] == 404, 'old_definition_not_served')
        require(request(port, 'GET', jpeg_path)[0] == 404, 'old_jpeg_definition_not_served')
        require(request(port, 'GET', mp4_path)[0] == 404, 'old_mp4_definition_not_served')
        inspect('accepted')
        inspect('accepted')
        inspect('accepted')
        require(request(port, 'GET', path)[2] == png() and request(port, 'GET', jpeg_path)[2] == jpeg, 'reinspection_promotion')
        require(request(port, 'GET', mp4_path)[2] == mp4, 'mp4_reinspection_promotion')
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
        inspect('accepted')
        inspect('accepted')
        require(request(port, 'GET', jpeg_path)[2] == jpeg, 'jpeg_definition_recovery')
        require(request(port, 'GET', mp4_path)[2] == mp4, 'mp4_definition_recovery')
        passed.append('parser_outage_quarantine_explicit_retry_bound_definition_recovery')

        bad = upload(b'<svg/>', filename='looks-valid.png', media='image/png')
        inspect('rejected')
        require(request(port, 'GET', '/catalog-images/' + bad)[0] == 404 and state(bad)['state'] == 'rejected', 'rejected_not_served')
        passed.append('bytes_classify_content_independently_of_metadata')

        mislabeled_jpeg = upload(jpeg)
        inspect('rejected')
        mislabeled_png = upload(png(), route='/catalog-jpeg-images')
        inspect('rejected')
        require(state(mislabeled_jpeg)['state'] == 'rejected' and state(mislabeled_png)['state'] == 'rejected', 'trusted_route_selects_parser')
        entropy = jpeg.index(b'\xff\xda') + 14
        corrupt = upload(jpeg[:entropy + 1] + b'\xff\xd9', route='/catalog-jpeg-images')
        inspect(failed=True)
        require(state(corrupt)['state'] == 'scanner_error' and request(port, 'GET', '/catalog-jpeg-images/' + corrupt)[0] == 404, 'jpeg_decoder_failure_nonserving')
        passed.append('cross_format_bytes_rejected_and_strict_decoder_failure_quarantined')

        mislabeled_clip = upload(png(), route='/catalog-video-clips')
        inspect('rejected')
        require(state(mislabeled_clip)['state'] == 'rejected', 'mp4_wrong_format_bytes')
        start = mp4.index(b'mdat') + 4
        length = struct.unpack_from('>I', mp4, start)[0]
        broken = mp4[:start + 5] + b'\0' * (length - 1) + mp4[start + 4 + length:]
        broken_id = upload(broken, route='/catalog-video-clips')
        inspect(failed=True)
        require(state(broken_id)['state'] == 'scanner_error' and request(port, 'GET', '/catalog-video-clips/' + broken_id)[0] == 404, 'mp4_decoder_error_nonserving')
        passed.append('mp4_route_controls_classification_and_decoder_errors_stay_quarantined')

        for payload, expected in [(b'', 400), (b'x' * 1_048_577, 413)]:
            body, media = multipart(payload)
            require(request(port, 'POST', '/catalog-images', body, media)[0] == expected, 'file_byte_boundary')
            require(request(port, 'POST', '/catalog-jpeg-images', body, media)[0] == expected, 'jpeg_file_byte_boundary')
            clip_body, clip_media = multipart(payload, name='clip')
            require(request(port, 'POST', '/catalog-video-clips', clip_body, clip_media)[0] == expected, 'mp4_file_byte_boundary')
        maximum = upload(b'x' * 1_048_576)
        require(state(maximum)['bytes'] == 1_048_576 and request(port, 'GET', '/catalog-images/' + maximum)[0] == 404, 'inclusive_file_limit')
        mp4_maximum = upload(b'x' * 1_048_576, route='/catalog-video-clips')
        require(state(mp4_maximum)['bytes'] == 1_048_576 and request(port, 'GET', '/catalog-video-clips/' + mp4_maximum)[0] == 404, 'mp4_inclusive_file_limit')
        body, media = multipart(png(), name='other')
        require(request(port, 'POST', '/catalog-images', body, media)[0] == 400, 'field_boundary')
        extra = b'--phpthis-catalog-proof\r\nContent-Disposition: form-data; name="caption"\r\n\r\nno\r\n'
        body, media = multipart(png(), extra=extra)
        require(request(port, 'POST', '/catalog-images', body, media)[0] == 400, 'text_field_boundary')
        require(request(port, 'POST', '/catalog-jpeg-images', body, media)[0] == 400, 'jpeg_text_field_boundary')
        body, media = multipart(mp4, name='clip', extra=extra)
        require(request(port, 'POST', '/catalog-video-clips', body, media)[0] == 400, 'mp4_text_field_boundary')
        body, media = multipart(mp4)
        require(request(port, 'POST', '/catalog-video-clips', body, media)[0] == 400, 'mp4_wrong_field_boundary')
        require(len(list((root / 'objects').iterdir())) == 11, 'input_failure_no_storage')
        passed.append('native_byte_limits_zero_unknown_and_extra_fields_no_storage')

        # Expiry is checked on reads, independently of whether the cleanup pass has run.
        code, out, err = php('tests/control.php', str(root), 'expire', first)
        require((code, out, err) == (0, b'EXPIRED\n', b''), 'expiry_control')
        require(request(port, 'GET', path)[0] == 404, 'expired_not_served')
        command('images:expire-one', expected='expired')
        require(state(first) is None and not (root / 'objects' / first).exists(), 'expiry_releases_after_absence')
        passed.append('expiry_denies_serving_before_bounded_deletion')

        code, out, err = php('tests/control.php', str(root), 'expire', jpeg_id)
        require((code, out, err) == (0, b'EXPIRED\n', b''), 'jpeg_expiry_control')
        require(request(port, 'GET', jpeg_path)[0] == 404, 'jpeg_expired_not_served')
        command('images:expire-one', expected='expired')
        require(state(jpeg_id) is None and not (root / 'objects' / jpeg_id).exists(), 'jpeg_expiry_cleanup')
        passed.append('jpeg_expiry_and_exact_cleanup_share_the_existing_lifecycle')

        code, out, err = php('tests/control.php', str(root), 'expire', mp4_id)
        require((code, out, err) == (0, b'EXPIRED\n', b''), 'mp4_expiry_control')
        require(request(port, 'GET', mp4_path)[0] == 404, 'mp4_expired_not_served')
        command('images:expire-one', expected='expired')
        require(state(mp4_id) is None and not (root / 'objects' / mp4_id).exists(), 'mp4_expiry_cleanup')
        passed.append('mp4_expiry_and_exact_cleanup')

        # All three routes count every retained reservation, including rejection/error states.
        require(len(list((root / 'objects').iterdir())) == 8, 'mixed_quota_baseline')
        for index in range(24):
            if index % 3 == 0:
                upload(png())
            elif index % 3 == 1:
                upload(jpeg, route='/catalog-jpeg-images')
            else:
                upload(mp4, route='/catalog-video-clips')
        body, media = multipart(png())
        require(request(port, 'POST', '/catalog-images', body, media)[0] == 503, 'aggregate_quota')
        require(request(port, 'POST', '/catalog-jpeg-images', body, media)[0] == 503, 'jpeg_aggregate_quota')
        body, media = multipart(mp4, name='clip')
        require(request(port, 'POST', '/catalog-video-clips', body, media)[0] == 503, 'mp4_aggregate_quota')
        require(len(list((root / 'objects').iterdir())) == 32, 'quota_reservation_bound')
        passed.append('mixed_formats_and_errors_share_quota_and_all_routes_deny_33rd_upload')
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
                'host_php': version.decode(), 'inspector_php': '8.4.26', 'pngcheck': '4.0.1', 'zlib': '1.3.2', 'libjpeg_turbo': '3.2.0', 'ffmpeg': '9.0.2',
                'image_id': image, 'update_image_id': update, 'recovery_image_id': recovery, 'fault_image_id': faults,
                'probe_fault_image_ids': probe_faults, 'parser': parser_evidence, 'http_and_lifecycle': passed,
                'cleanup': 'owned processes, containers, upload temporaries and retained fixture removed', 'source_sha256': sources}
    (ROOT / 'tmp' / 'inspected-media-evidence.json').write_text(json.dumps(evidence, indent=2) + '\n')
    print('PASS real PNG/JPEG/MP4 inspection, HTTP quarantine and resource-failure reference')


if __name__ == '__main__':
    try:
        main()
    except Exception as failure:
        # Only code-owned assertion labels are emitted; process streams and payloads stay private.
        if type(failure) is RuntimeError and re.fullmatch('[a-z0-9_]+', str(failure)):
            print('FAIL ' + str(failure))
        else:
            print('FAIL inspected_media_proof')
        raise SystemExit(1)
