<?php

declare(strict_types=1);

use Example\DocumentFiles\LocalDocumentFiles;
use Example\DocumentFiles\UploadDocumentFileHandler;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\MultipartForm;
use PHPThis\Http\MultipartLimits;
use PHPThis\Http\PeerAddress;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestUploadError;
use PHPThis\Routing\PathParameters;

/** @return Generator<string, Closure(): void, mixed, void> */
function multipartBehaviorTests(): Generator
{
    yield 'multipart values preserve exact fields uploads and routing copies' => static function (): void {
        $limits = new MultipartLimits(1_024, 512, 700);
        $fields = ['empty' => '', 'enabled' => 'false', 'count' => '007', 'text' => "line\n\0\xff"];
        $form = MultipartForm::fromPhp($fields, ['first' => multipartTestFile(), 'second' => multipartTestFile(0)], 1_024, $limits);
        $request = new Request('POST', '/items/7', multipart: $form, peerAddress: PeerAddress::fromString('::1'));
        $copy = $request->withPathParameters(PathParameters::fromValues(['id' => 7], []));
        if (
            $form->fields !== $fields || array_key_exists('missing', $form->fields)
            || count($form->uploads) !== 2 || $form->uploads['second']->reportedSizeBytes !== 0
            || $form->uploads['first']->untrustedClientFilename !== '../client.txt'
            || $form->uploads['first']->untrustedClientMediaType !== 'text/html'
            || $copy->multipart !== $form || $copy->uploads !== $form->uploads
            || $copy->peerAddress !== $request->peerAddress || $copy->pathParameters->positiveInteger('id') !== 7
        ) {
            throw new RuntimeException('Multipart parsing or routing changed exact normalized values.');
        }
        $empty = MultipartForm::fromPhp([], [], 0, $limits);
        if ($empty->fields !== [] || $empty->uploads !== [] || (new Request('GET', '/'))->multipart !== null) {
            throw new RuntimeException('Empty multipart input must differ from no multipart representation.');
        }
    };

    yield 'multipart limits reject invalid configurations and enforce inclusive file budgets' => static function (): void {
        foreach ([[0, 1, 1], [1, 0, 1], [1, 1, 0], [-1, 1, 1], [10, 11, 10], [10, 1, 11], [PHP_INT_MAX, 1, 1]] as [$request, $file, $total]) {
            expectMultipartFailure(InvalidArgumentException::class, static fn () => new MultipartLimits($request, $file, $total));
        }
        $limits = new MultipartLimits(1_024, 512, 700);
        MultipartForm::fromPhp([], ['a' => multipartTestFile(512), 'b' => multipartTestFile(188)], 700, $limits);
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp([], [], 1_025, $limits));
        expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp([], [], -1, $limits));
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp([], ['a' => multipartTestFile(513)], 1_024, $limits));
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp([], ['a' => multipartTestFile(512), 'b' => multipartTestFile(189)], 1_024, $limits));
        expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp(['a' => 'v'], ['b' => multipartTestFile(10)], 11, $limits));
        $large = new MultipartLimits(PHP_INT_MAX - 1, PHP_INT_MAX - 1, PHP_INT_MAX - 1);
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp([], ['a' => multipartTestFile(PHP_INT_MAX - 1), 'b' => multipartTestFile(1)], PHP_INT_MAX - 1, $large));
    };

    yield 'multipart fields enforce flat names exact byte limits and collision rejection' => static function (): void {
        $limits = new MultipartLimits(1_000_000, 512, 700);
        $fields = [];
        for ($index = 0; $index < 32; ++$index) {
            $fields['f' . $index] = '';
        }
        MultipartForm::fromPhp($fields, [], 1_000_000, $limits);
        expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp($fields + ['extra' => ''], [], 1_000_000, $limits));
        MultipartForm::fromPhp([str_repeat('a', 64) => str_repeat('v', 8_192), '_A-z_0' => ''], [], 1_000_000, $limits);
        foreach (['', '1name', 'has.dot', 'has space', 'nested[]', "control\n", 'é', str_repeat('a', 65)] as $name) {
            expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp([$name => 'v'], [], 1_000_000, $limits));
            expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp([], [$name => multipartTestFile()], 1_000_000, $limits));
        }
        foreach ([null, 1, false, [], ['nested' => 'v'], new stdClass()] as $value) {
            expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp(['field' => $value], [], 1_000_000, $limits));
        }
        expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp([0 => 'value'], [], 1_000_000, $limits));
        expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp(['same' => ''], ['same' => multipartTestFile()], 1_000_000, $limits));
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp(['a' => str_repeat('v', 8_193)], [], 1_000_000, $limits));
        $aggregate = [];
        for ($index = 0; $index < 8; ++$index) {
            $aggregate['f00' . $index] = str_repeat('v', 8_188);
        }
        MultipartForm::fromPhp($aggregate, [], 65_536, $limits);
        $aggregate['f007'] .= 'v';
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp($aggregate, [], 1_000_000, $limits));
    };

    yield 'multipart uploads bound counts metadata and every exposed native shape' => static function (): void {
        $limits = new MultipartLimits(1_024, 512, 700);
        $files = [];
        for ($index = 0; $index < 8; ++$index) {
            $files['f' . $index] = multipartTestFile(0);
        }
        MultipartForm::fromPhp([], $files, 1_024, $limits);
        expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp([], $files + ['extra' => multipartTestFile(0)], 1_024, $limits));
        $exact = array_replace(multipartTestFile(), ['name' => str_repeat('n', 255), 'type' => str_repeat('t', 255), 'full_path' => str_repeat('f', 4_096), 'tmp_name' => '/' . str_repeat('t', 4_095)]);
        MultipartForm::fromPhp([], [str_repeat('a', 64) => $exact], 1_024, $limits);
        foreach (['name' => 255, 'type' => 255, 'full_path' => 4_096, 'tmp_name' => 4_096] as $key => $maximum) {
            foreach ([str_repeat('x', $maximum + 1), "private\r\nvalue", null, ['nested']] as $value) {
                expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp([], ['file' => array_replace(multipartTestFile(), [$key => $value])], 1_024, $limits));
            }
        }
        foreach (['name', 'type', 'tmp_name', 'size', 'error'] as $key) {
            $missing = multipartTestFile();
            unset($missing[$key]);
            expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp([], ['file' => $missing], 1_024, $limits));
        }
        foreach ([null, [], ['extra' => true], array_replace(multipartTestFile(), ['size' => '1']), array_replace(multipartTestFile(), ['size' => -1]), array_replace(multipartTestFile(), ['error' => '0']), array_replace(multipartTestFile(), ['name' => '']), array_replace(multipartTestFile(), ['tmp_name' => '']), multipartTestFile(12)] as $file) {
            expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp([], ['file' => $file], 11, $limits));
        }
        expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp([], [0 => multipartTestFile()], 1_024, $limits));
    };

    yield 'multipart preserves every native upload error without treating partial files as success' => static function (): void {
        $limits = new MultipartLimits(1_024, 512, 700);
        foreach (RequestUploadError::cases() as $error) {
            $file = $error === RequestUploadError::NoFile
                ? ['name' => '', 'full_path' => '', 'type' => '', 'tmp_name' => '', 'error' => $error->value, 'size' => 0]
                : array_replace(multipartTestFile(), ['error' => $error->value]);
            $form = MultipartForm::fromPhp([], ['first' => $file, 'second' => multipartTestFile()], 1_024, $limits);
            if ($form->uploads['first']->error !== $error || $form->uploads['second']->error !== RequestUploadError::Success) {
                throw new RuntimeException('Every native upload outcome must remain visible beside a successful file.');
            }
        }
        expectMultipartFailure(RuntimeException::class, static fn () => MultipartForm::fromPhp([], ['file' => array_replace(multipartTestFile(), ['error' => 5])], 1_024, $limits));
        foreach (['name' => 'private', 'full_path' => 'private', 'type' => 'private', 'tmp_name' => 'private', 'size' => 1] as $key => $value) {
            $file = ['name' => '', 'full_path' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0];
            $file[$key] = $value;
            expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp([], ['file' => $file], 1_024, $limits));
        }
    };

    yield 'expanded multipart ingress does not expand the single-document storage operation' => static function (): void {
        $limits = new MultipartLimits(1_024, 512, 700);
        $handler = new UploadDocumentFileHandler(new LocalDocumentFiles('/not-a-document-storage-root'));
        foreach ([
            MultipartForm::fromPhp(['title' => 'metadata'], ['document' => multipartTestFile()], 1_024, $limits),
            MultipartForm::fromPhp([], ['document' => multipartTestFile(), 'extra' => multipartTestFile()], 1_024, $limits),
        ] as $form) {
            expectMultipartFailure(InvalidRequest::class, static fn () => $handler->handle(new Request('POST', '/document-files', headers: ['content-type' => 'multipart/form-data; boundary=test'], multipart: $form)));
        }
    };

    yield 'multipart field configuration preserves defaults and validates finite ordered limits' => static function (): void {
        $limits = new MultipartLimits(1_024, 512, 700);
        if ($limits->maximumFieldBytes !== 8_192 || $limits->maximumTotalFieldBytes !== 65_536) {
            throw new RuntimeException('Existing multipart calls must retain their field defaults.');
        }
        foreach ([[0, 1], [-1, 1], [1, 0], [1, -1], [2, 1], [PHP_INT_MAX, PHP_INT_MAX], [1, PHP_INT_MAX]] as [$field, $total]) {
            expectMultipartFailure(InvalidArgumentException::class, static fn () => new MultipartLimits(1_024, 512, 700, maximumFieldBytes: $field, maximumTotalFieldBytes: $total));
        }
        MultipartForm::fromPhp(['a' => 'v'], [], 2, $limits);
        expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp(['a' => 'v'], [], 1, $limits));
    };

    yield 'multipart configured field bytes accept larger JSON and enforce smaller inclusive limits' => static function (): void {
        $json = '{"text":"' . str_repeat('v', 16_373) . '"}';
        $limits = new MultipartLimits(131_072, 512, 700, maximumFieldBytes: 16_384);
        $form = MultipartForm::fromPhp(['metadata' => $json], [], 131_072, $limits);
        $copy = (new Request('POST', '/items/7', multipart: $form))->withPathParameters(PathParameters::fromValues(['id' => 7], []));
        if (strlen($json) !== 16_384 || $form->fields !== ['metadata' => $json] || $copy->multipart !== $form) {
            throw new RuntimeException('Configured multipart input or its request copy changed.');
        }
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp(['metadata' => $json . ' '], [], 131_072, $limits));
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp(['metadata' => $json], [], 131_072, new MultipartLimits(131_072, 512, 700)));
        $small = new MultipartLimits(1_024, 512, 700, maximumFieldBytes: 2);
        MultipartForm::fromPhp(['a' => 'é'], [], 3, $small);
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp(['a' => 'éa'], [], 1_024, $small));
    };

    yield 'multipart configured aggregate field bytes count names and preserve file budgets' => static function (): void {
        $limits = new MultipartLimits(1_024, 512, 700, maximumFieldBytes: 16, maximumTotalFieldBytes: 20);
        $fields = ['a' => str_repeat('v', 10), 'bb' => str_repeat('v', 7)];
        MultipartForm::fromPhp($fields, ['file' => multipartTestFile(512)], 532, $limits);
        expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp($fields, ['file' => multipartTestFile(512)], 531, $limits));
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp($fields, ['file' => multipartTestFile(513)], 1_024, $limits));
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp($fields + ['c' => ''], [], 1_024, $limits));
        $fields['bb'] .= 'v';
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp($fields, [], 1_024, $limits));
        $tiny = new MultipartLimits(1_024, 512, 700, maximumFieldBytes: 1, maximumTotalFieldBytes: 1);
        MultipartForm::fromPhp(['a' => ''], [], 1, $tiny);
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp(['aa' => ''], [], 1_024, $tiny));
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp(['a' => 'v'], [], 1_024, $tiny));
        $large = new MultipartLimits(131_072, 512, 700, maximumFieldBytes: 65_536, maximumTotalFieldBytes: 65_537);
        MultipartForm::fromPhp(['a' => str_repeat('v', 65_536)], [], 65_537, $large);
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp(['a' => str_repeat('v', 65_536), 'b' => ''], [], 131_072, $large));
    };

    yield 'multipart field and file accounting stays bounded at integer limits' => static function (): void {
        $limits = new MultipartLimits(PHP_INT_MAX - 1, PHP_INT_MAX - 1, PHP_INT_MAX - 1, maximumFieldBytes: PHP_INT_MAX - 1, maximumTotalFieldBytes: PHP_INT_MAX - 1);
        MultipartForm::fromPhp(['a' => 'v'], ['file' => multipartTestFile(PHP_INT_MAX - 3)], PHP_INT_MAX - 1, $limits);
        expectMultipartFailure(InvalidRequest::class, static fn () => MultipartForm::fromPhp(['a' => 'v'], ['file' => multipartTestFile(PHP_INT_MAX - 2)], PHP_INT_MAX - 1, $limits));
        expectMultipartFailure(RequestBodyTooLarge::class, static fn () => MultipartForm::fromPhp([], ['a' => multipartTestFile(PHP_INT_MAX - 1), 'b' => multipartTestFile(1)], PHP_INT_MAX - 1, $limits));
    };
}

/** @return array{name: string, type: string, tmp_name: string, error: int, size: int} */
function multipartTestFile(int $size = 1): array
{
    return ['name' => '../client.txt', 'type' => 'text/html', 'tmp_name' => '/synthetic-upload', 'error' => 0, 'size' => $size];
}

/** @param class-string<Throwable> $class */
function expectMultipartFailure(string $class, Closure $operation): void
{
    try {
        $operation();
    } catch (Throwable $failure) {
        if ($failure::class === $class && !str_contains($failure->getMessage(), 'private')) {
            return;
        }
        throw new RuntimeException('Unexpected multipart failure or unredacted metadata.', previous: $failure);
    }
    throw new RuntimeException('Invalid multipart input did not fail as ' . $class . '.');
}
