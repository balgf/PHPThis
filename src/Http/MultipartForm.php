<?php

declare(strict_types=1);

namespace PHPThis\Http;

use RuntimeException;

final readonly class MultipartForm
{
    public const int MAXIMUM_FIELDS = 32;
    public const int MAXIMUM_UPLOADS = 8;
    public const int MAXIMUM_PARTS = 40;

    /**
     * @param array<string, string> $fields
     * @param array<string, RequestUpload> $uploads
     */
    private function __construct(public array $fields, public array $uploads)
    {
    }

    /**
     * @param array<array-key, mixed> $fields
     * @param array<array-key, mixed> $files
     */
    public static function fromPhp(
        array $fields,
        array $files,
        int $declaredRequestBytes,
        MultipartLimits $limits,
    ): self {
        if ($declaredRequestBytes < 0) {
            throw new InvalidRequest('Multipart request bytes cannot be negative.');
        }
        if ($declaredRequestBytes > $limits->maximumRequestBytes) {
            throw new RequestBodyTooLarge('Declared multipart request exceeds its byte limit.');
        }
        if (count($fields) > self::MAXIMUM_FIELDS || count($files) > self::MAXIMUM_UPLOADS) {
            throw new InvalidRequest('Multipart input contains too many fields or uploads.');
        }
        $parsedFields = [];
        $fieldBytes = 0;
        foreach ($fields as $name => $value) {
            if (!is_string($name) || preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,63}$/D', $name) !== 1 || !is_string($value)) {
                throw new InvalidRequest('Multipart fields require bounded names and flat string values.');
            }
            if (array_key_exists($name, $files)) {
                throw new InvalidRequest('Multipart field and upload names must be distinct.');
            }
            if (strlen($value) > 8_192) {
                throw new RequestBodyTooLarge('Multipart field exceeds its byte limit.');
            }
            $fieldBytes += strlen($name) + strlen($value);
            if ($fieldBytes > 65_536) {
                throw new RequestBodyTooLarge('Multipart fields exceed their aggregate byte limit.');
            }
            $parsedFields[$name] = $value;
        }
        $uploads = [];
        $fileBytes = 0;
        foreach ($files as $name => $file) {
            if (
                !is_string($name) || preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,63}$/D', $name) !== 1
                || !is_array($file)
                || array_diff(array_keys($file), ['name', 'full_path', 'type', 'tmp_name', 'error', 'size']) !== []
                || !isset($file['name'], $file['type'], $file['tmp_name'], $file['error'], $file['size'])
                || !is_string($file['name']) || !is_string($file['type']) || !is_string($file['tmp_name'])
                || !is_int($file['error']) || !is_int($file['size'])
            ) {
                throw new InvalidRequest('The uploaded file is missing typed flat metadata.');
            }
            $fullPath = array_key_exists('full_path', $file) ? $file['full_path'] : '';
            if (
                !is_string($fullPath) || $file['size'] < 0 || $file['size'] > $declaredRequestBytes
                || strlen($file['name']) > 255 || strlen($file['type']) > 255
                || strlen($fullPath) > 4_096 || strlen($file['tmp_name']) > 4_096
                || preg_match('/[\x00-\x1F\x7F]/', $file['name'] . $fullPath . $file['type'] . $file['tmp_name']) === 1
            ) {
                throw new InvalidRequest('The uploaded file metadata has an invalid representation.');
            }
            if ($file['size'] > $limits->maximumFileBytes || $file['size'] > $limits->maximumTotalFileBytes - $fileBytes) {
                throw new RequestBodyTooLarge('Multipart uploads exceed their file or aggregate byte limit.');
            }
            $fileBytes += $file['size'];
            $error = RequestUploadError::tryFrom($file['error']);
            if ($error === null) {
                throw new RuntimeException('PHP reported an unknown upload error.');
            }
            if ($error === RequestUploadError::Success && ($file['name'] === '' || $file['tmp_name'] === '')) {
                throw new InvalidRequest('A successful upload requires a client filename and temporary path.');
            }
            if ($error === RequestUploadError::NoFile && ($file['name'] !== '' || $fullPath !== '' || $file['type'] !== '' || $file['tmp_name'] !== '' || $file['size'] !== 0)) {
                throw new InvalidRequest('A missing upload cannot contain file metadata.');
            }
            $uploads[$name] = new RequestUpload($file['name'], $file['type'], $file['tmp_name'], $file['size'], $error);
        }
        if ($fieldBytes > $declaredRequestBytes - $fileBytes) {
            throw new InvalidRequest('Normalized multipart bytes exceed the declared request.');
        }
        return new self($parsedFields, $uploads);
    }
}
