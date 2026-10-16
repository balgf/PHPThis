<?php

declare(strict_types=1);

/** @return Generator<string, Closure(): void, mixed, void> */
function multipartSapiBehaviorTests(): Generator
{
    yield 'native multipart parsing proves real fields files limits and normalized-input limitations' => static function (): void {
        proveMultipartSapi(false, true, false);
    };
    yield 'multipart configuration and parsing failures precede sessions and handler work' => static function (): void {
        proveMultipartSapi(true, true, false);
        proveMultipartSapi(false, false, false);
        proveMultipartSapi(false, true, true);
    };
    yield 'configured multipart field limits prove larger input and rejection before session or body work' => static function (): void {
        proveMultipartFieldLimitsSapi(false, false);
        proveMultipartFieldLimitsSapi(true, false);
        proveMultipartFieldLimitsSapi(false, true);
    };
}

function multipartFieldPart(string $name, string $value = 'v'): string
{
    return "--phpthis-test\r\nContent-Disposition: form-data; name=\"" . $name . "\"\r\n\r\n" . $value . "\r\n";
}

function multipartFilePart(string $name, string $value = 'abc', string $filename = 'client-private.txt'): string
{
    return "--phpthis-test\r\nContent-Disposition: form-data; name=\"" . $name . '"; filename="' . $filename
        . "\"\r\nContent-Type: application/octet-stream\r\n\r\n" . $value . "\r\n";
}

function proveMultipartSapi(bool $automatic, bool $uploads, bool $begunSession): void
{
    $bootstrap = file_get_contents(__DIR__ . '/fixtures/multipart-bootstrap.php.fixture');
    if (!is_string($bootstrap)) {
        throw new RuntimeException('Cannot read multipart bootstrap.');
    }
    if ($begunSession) {
        $bootstrap = str_replace('$sessions = null;', <<<'PHP'
$sessions = new SessionLifecycle(new SessionConfiguration('multipartTest', 'multipartTest', false, CookieSameSite::Lax, sys_get_temp_dir()));
$sessions->begin(new Request('GET', '/'));
PHP, $bootstrap);
    }
    $temporary = dirname(__DIR__) . '/tmp/application-tests/multipart-' . bin2hex(random_bytes(8));
    $process = null;
    try {
        $root = createExampleFrontControllerProcessTree($temporary, $bootstrap);
        if (!mkdir($root . '/uploads', 0700) || file_put_contents($root . '/server.log', '') !== 0 || !chmod($root . '/server.log', 0600)) {
            throw new RuntimeException('Cannot prepare private multipart test storage.');
        }
        [$process, $port] = startExampleOuterHttpFailureServer($root, $root . '/server.log', iniSettings: [
            'enable_post_data_reading=' . ($automatic ? '1' : '0'), 'file_uploads=' . ($uploads ? '1' : '0'),
            'upload_tmp_dir=' . $root . '/uploads', 'session.upload_progress.enabled=0', 'mbstring.encoding_translation=0',
            // Intentionally lower global limits prove that explicit parse options own this parse.
            'post_max_size=64', 'upload_max_filesize=1', 'max_input_vars=1', 'max_file_uploads=1', 'max_multipart_body_parts=1',
            'max_input_nesting_level=4',
        ]);
        $end = "--phpthis-test--\r\n";
        if ($automatic || !$uploads) {
            assertMultipartHttp($root, $port, 'incompatible-settings', multipartFilePart('document') . $end, 500, 0);
            return;
        }
        if ($begunSession) {
            assertMultipartHttp($root, $port, 'nested-before-session', multipartFieldPart('nested[]') . $end, 400, 0);
            assertMultipartHttp($root, $port, 'native-limit-before-session', str_repeat(multipartFieldPart('duplicate'), 33) . $end, 400, 0);
            assertMultipartHttp($root, $port, 'valid-input-reaches-session', multipartFieldPart('title') . $end, 500, 0);
            return;
        }
        $text = assertMultipartHttp($root, $port, 'text', multipartFieldPart('title', "private-sentinel\n") . multipartFieldPart('empty', '') . $end, 200, 1);
        if ($text['fields'] !== ['title' => "private-sentinel\n", 'empty' => ''] || $text['files'] !== [] || $text['route'] !== 7) {
            throw new RuntimeException('Text-only multipart data or route was changed.');
        }
        $mixed = assertMultipartHttp($root, $port, 'mixed', multipartFieldPart('enabled', 'false') . multipartFilePart('first') . multipartFilePart('second', '') . $end, 200, 1);
        if ($mixed['fields'] !== ['enabled' => 'false'] || $mixed['files'] !== [
            'first' => ['error' => 'Success', 'bytes' => 3, 'actual' => 3],
            'second' => ['error' => 'Success', 'bytes' => 0, 'actual' => 0],
        ]) {
            throw new RuntimeException('Mixed uploads lost bytes, provenance or their empty value.');
        }
        $empty = assertMultipartHttp($root, $port, 'empty', $end, 200, 1);
        $missing = assertMultipartHttp($root, $port, 'no-file', multipartFilePart('document', '', '') . $end, 200, 1);
        if ($empty['fields'] !== [] || $empty['files'] !== [] || $missing['files'] !== ['document' => ['error' => 'NoFile', 'bytes' => 0, 'actual' => null]]) {
            throw new RuntimeException('Empty multipart and missing-upload semantics changed.');
        }
        $fields = '';
        for ($index = 0; $index < 32; ++$index) {
            $fields .= multipartFieldPart('f' . $index, '');
        }
        $files = '';
        for ($index = 0; $index < 8; ++$index) {
            $files .= multipartFilePart('u' . $index, '');
        }
        assertMultipartHttp($root, $port, 'all-counts-exact', $fields . $files . $end, 200, 1);
        assertMultipartHttp($root, $port, 'field-count', $fields . multipartFieldPart('extra') . $end, 400, 0);
        assertMultipartHttp($root, $port, 'duplicate-field-count', str_repeat(multipartFieldPart('same'), 33) . $end, 400, 0);
        assertMultipartHttp($root, $port, 'file-count', $files . multipartFilePart('extra') . $end, 400, 0);
        assertMultipartHttp($root, $port, 'duplicate-file-count', str_repeat(multipartFilePart('same'), 9) . $end, 400, 0);
        assertMultipartHttp($root, $port, 'raw-parts', str_repeat(multipartFilePart('empty', '', ''), 41) . $end, 400, 0);
        foreach ([
            ['field-value-exact', multipartFieldPart('a', str_repeat('v', 8_192)), 200, 1],
            ['field-value-over', multipartFieldPart('a', str_repeat('v', 8_193)), 413, 0],
            ['larger-json-default-rejection', multipartFieldPart('metadata', '{"text":"' . str_repeat('v', 16_373) . '"}'), 413, 0],
            ['name-exact', multipartFieldPart(str_repeat('n', 64)), 200, 1],
            ['name-over', multipartFieldPart(str_repeat('n', 65)), 400, 0],
            ['nested-field', multipartFieldPart('a[]'), 400, 0],
            ['nested-file', multipartFilePart('a[]'), 400, 0],
            ['collision', multipartFieldPart('a') . multipartFilePart('a'), 400, 0],
            ['per-file-exact', multipartFilePart('a', str_repeat('v', 512)), 200, 1],
            ['per-file-error', multipartFilePart('a', str_repeat('v', 513)) . multipartFilePart('b'), 413, 1],
            ['aggregate-file-exact', multipartFilePart('a', str_repeat('v', 350)) . multipartFilePart('b', str_repeat('v', 350)), 200, 1],
            ['aggregate-file-over', multipartFilePart('a', str_repeat('v', 350)) . multipartFilePart('b', str_repeat('v', 351)), 413, 0],
            ['filename-exact', multipartFilePart('a', '', str_repeat('n', 255)), 200, 1],
            ['filename-over', multipartFilePart('a', '', str_repeat('n', 256)), 400, 0],
            ['media-type-exact', str_replace('application/octet-stream', str_repeat('t', 255), multipartFilePart('a')), 200, 1],
            ['media-type-over', str_replace('application/octet-stream', str_repeat('t', 256), multipartFilePart('a')), 400, 0],
            ['full-path-exact', multipartFilePart('a', '', str_repeat('d', 4_094) . '/n'), 200, 1],
            ['full-path-over', multipartFilePart('a', '', str_repeat('d', 4_095) . '/n'), 400, 0],
            ['client-file-size', multipartFieldPart('MAX_FILE_SIZE', '2') . multipartFilePart('a'), 413, 1],
        ] as [$name, $parts, $status, $calls]) {
            assertMultipartHttp($root, $port, $name, $parts . $end, $status, $calls);
        }
        $aggregate = '';
        for ($index = 0; $index < 8; ++$index) {
            $aggregate .= multipartFieldPart('f00' . $index, str_repeat('v', 8_188));
        }
        assertMultipartHttp($root, $port, 'aggregate-fields-exact', $aggregate . $end, 200, 1);
        assertMultipartHttp($root, $port, 'aggregate-fields-over', $aggregate . multipartFieldPart('extra', '') . $end, 413, 0);
        $padded = str_pad(multipartFieldPart('a') . $end, 131_072, ' ');
        assertMultipartHttp($root, $port, 'request-exact', $padded, 200, 1);
        assertMultipartHttp($root, $port, 'request-over', $padded . ' ', 413, 0);
        $normalized = assertMultipartHttp($root, $port, 'normalized', multipartFieldPart('a.b', 'first') . multipartFieldPart('a b', 'last') . $end, 200, 1);
        if ($normalized['fields'] !== ['a_b' => 'last']) {
            throw new RuntimeException('The recorded PHP name-normalization limitation changed.');
        }
        $duplicates = assertMultipartHttp($root, $port, 'duplicate-files', multipartFilePart('same', 'first') . multipartFilePart('same', 'last') . $end, 200, 1);
        if ($duplicates['files'] !== ['same' => ['error' => 'Success', 'bytes' => 4, 'actual' => 4]]) {
            throw new RuntimeException('The recorded scalar duplicate-file limitation changed.');
        }
        foreach ([
            multipartFieldPart('title', 'v'),
            multipartFieldPart('title', 'v') . "--phpthis-test\r\n",
            multipartFieldPart('a[b][c][d][e][f]') . multipartFieldPart('title') . $end,
            multipartFilePart('bad[') . multipartFieldPart('title') . $end,
        ] as $incomplete) {
            $partial = assertMultipartHttp($root, $port, 'native-normalization-limit', $incomplete, 200, 1);
            if ($partial['fields'] !== ['title' => 'v']) {
                throw new RuntimeException('The recorded malformed-input normalization limit changed.');
            }
        }
        assertMultipartHttp($root, $port, 'partial-file', multipartFilePart('a'), 400, 1);
        foreach (['application/json' => '{"ok":true}', 'application/x-www-form-urlencoded' => 'a=v'] as $type => $body) {
            $ordinary = assertMultipartHttp($root, $port, 'ordinary', $body, 200, 1, $type);
            if ($ordinary !== ['body' => $body]) {
                throw new RuntimeException('Explicit multipart parsing changed ordinary input.');
            }
        }
        if (glob($root . '/uploads/*') !== []) {
            throw new RuntimeException('Native multipart requests left temporary uploads behind.');
        }
        $log = file_get_contents($root . '/server.log');
        if (!is_string($log) || str_contains($log, 'client-private') || str_contains($log, 'private-sentinel')) {
            throw new RuntimeException('Multipart values escaped into private terminal summaries.');
        }
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
        removeDocumentFileTestDirectory($temporary);
    }
}

function proveMultipartFieldLimitsSapi(bool $begunSession, bool $invalidConfiguration): void
{
    $bootstrap = file_get_contents(__DIR__ . '/fixtures/multipart-bootstrap.php.fixture');
    if (!is_string($bootstrap)) {
        throw new RuntimeException('Cannot read multipart field-limit bootstrap.');
    }
    $bootstrap = str_replace('$limits = new MultipartLimits(131_072, 512, 700);',
        '$limits = new MultipartLimits(131_072, 512, 700, maximumFieldBytes: ' . ($invalidConfiguration ? '0' : '16_384') . ', maximumTotalFieldBytes: 32_768);'
        . "\nerror_log('multipart.fixture_limits_constructed');", $bootstrap);
    if ($begunSession) {
        $bootstrap = str_replace('$sessions = null;', <<<'PHP'
$sessions = new SessionLifecycle(new SessionConfiguration('multipartTest', 'multipartTest', false, CookieSameSite::Lax, sys_get_temp_dir()));
$sessions->begin(new Request('GET', '/'));
PHP, $bootstrap);
    }
    $temporary = dirname(__DIR__) . '/tmp/application-tests/multipart-fields-' . bin2hex(random_bytes(8));
    $process = null;
    try {
        $root = createExampleFrontControllerProcessTree($temporary, $bootstrap);
        if (!mkdir($root . '/uploads', 0700) || file_put_contents($root . '/server.log', '') !== 0 || !chmod($root . '/server.log', 0600)) {
            throw new RuntimeException('Cannot prepare private multipart field-limit storage.');
        }
        [$process, $port] = startExampleOuterHttpFailureServer($root, $root . '/server.log', iniSettings: [
            'enable_post_data_reading=0', 'file_uploads=1', 'upload_tmp_dir=' . $root . '/uploads',
            'session.upload_progress.enabled=0', 'mbstring.encoding_translation=0',
        ]);
        $end = "--phpthis-test--\r\n";
        if ($invalidConfiguration) {
            $payload = multipartFieldPart('metadata', 'private-sentinel') . multipartFilePart('document') . $end;
            if (file_put_contents($root . '/request.bin', $payload) !== strlen($payload)) {
                throw new RuntimeException('Cannot prepare invalid-configuration input.');
            }
            $response = runDocumentFileCurl($root, 'response', ['--header', 'Content-Type: multipart/form-data; boundary=phpthis-test', '--data-binary', '@' . $root . '/request.bin'], 'http://127.0.0.1:' . $port . '/inspect/7');
            $log = file_get_contents($root . '/server.log');
            if (
                $response['status'] !== 500 || $response['body'] !== '{"error":{"code":"internal_server_error","message":"Internal server error."}}' . "\n"
                || stripos($response['headers'], 'X-Request-ID:') !== false || !is_string($log)
                || !str_contains($log, 'application.http_outer_failure failure_class=InvalidArgumentException')
                || str_contains($log, 'multipart.fixture_limits_constructed') || str_contains($log, 'multipart.fixture_handler')
                || str_contains($log, 'application.request_summary') || str_contains($log, 'private-sentinel')
            ) {
                throw new RuntimeException('Invalid field configuration did not fail before reader construction.');
            }
        } else {
            $json = '{"text":"' . str_repeat('v', 16_373) . '"}';
            $response = assertMultipartHttp($root, $port, 'configured-json-exact', multipartFieldPart('metadata', $json) . $end, $begunSession ? 500 : 200, $begunSession ? 0 : 1);
            if (!$begunSession && ($response['fields'] !== ['metadata' => $json] || $response['route'] !== 7)) {
                throw new RuntimeException('Configured native parsing changed the larger field bytes.');
            }
            assertMultipartHttp($root, $port, 'configured-field-over', multipartFieldPart('metadata', $json . ' ') . $end, 413, 0);
            $exact = multipartFieldPart('a', str_repeat('v', 16_383)) . multipartFieldPart('b', str_repeat('v', 16_383));
            assertMultipartHttp($root, $port, 'configured-aggregate-exact', $exact . $end, $begunSession ? 500 : 200, $begunSession ? 0 : 1);
            assertMultipartHttp($root, $port, 'configured-aggregate-over-name', $exact . multipartFieldPart('c', '') . $end, 413, 0);
            $over = multipartFieldPart('a', str_repeat('v', 16_384)) . multipartFieldPart('b', str_repeat('v', 16_383));
            assertMultipartHttp($root, $port, 'configured-aggregate-over-value', $over . $end, 413, 0);
        }
        if (glob($root . '/uploads/*') !== []) {
            throw new RuntimeException('Configured multipart parsing left temporary uploads behind.');
        }
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
        removeDocumentFileTestDirectory($temporary);
    }
}

/** @return array<array-key, mixed> */
function assertMultipartHttp(string $root, int $port, string $name, string $payload, int $status, int $handlerCalls, string $contentType = 'multipart/form-data; boundary=phpthis-test'): array
{
    $before = file_get_contents($root . '/server.log');
    if (!is_string($before) || file_put_contents($root . '/request.bin', $payload) !== strlen($payload)) {
        throw new RuntimeException('Cannot prepare multipart request.');
    }
    $response = runDocumentFileCurl($root, 'response', ['--header', 'Content-Type: ' . $contentType, '--data-binary', '@' . $root . '/request.bin'], 'http://127.0.0.1:' . $port . '/inspect/7');
    $after = file_get_contents($root . '/server.log');
    if (
        $response['status'] !== $status || !is_string($after)
        || substr_count($after, 'multipart.fixture_handler') - substr_count($before, 'multipart.fixture_handler') !== $handlerCalls
        || substr_count($after, 'application.request_summary') - substr_count($before, 'application.request_summary') !== 1
        || preg_match('/X-Request-ID: [a-f0-9]{32}/i', $response['headers']) !== 1
    ) {
        throw new RuntimeException('Unexpected multipart HTTP outcome: ' . $name . ' status=' . $response['status'] . ' expected=' . $status);
    }
    $decoded = json_decode($response['body'], true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Multipart response must be an object.');
    }
    if ($status === 400 && $decoded !== ['code' => 'invalid_request'] || $status === 413 && $decoded !== ['code' => 'request_body_too_large']) {
        throw new RuntimeException('Multipart rejection changed its generic body.');
    }
    return $decoded;
}
