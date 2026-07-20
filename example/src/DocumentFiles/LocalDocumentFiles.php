<?php

declare(strict_types=1);

namespace Example\DocumentFiles;

use InvalidArgumentException;
use PHPThis\Http\LocalFileBody;

final readonly class LocalDocumentFiles
{
    public function __construct(private string $directory)
    {
        $isAbsolute = DIRECTORY_SEPARATOR === '\\'
            ? preg_match('/\A[A-Za-z]:[\\\\\/]/D', $directory) === 1
            : str_starts_with($directory, '/');

        if (
            $directory === ''
            || strlen($directory) > 4_096
            || !$isAbsolute
            || str_ends_with($directory, '/')
            || str_ends_with($directory, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $directory) === 1
        ) {
            throw new InvalidArgumentException('Document file directory must be a bounded absolute path.');
        }
    }

    public function store(PendingDocumentUpload $upload): DocumentFileId
    {
        if (!@is_dir($this->directory)) {
            if (!@mkdir($this->directory, 0700) && !@is_dir($this->directory)) {
                throw new DocumentFileUnavailable('Document file directory could not be created.');
            }
        }
        $this->requirePrivateDirectory($this->directory);

        $id = DocumentFileId::generate();
        $documentDirectory = $this->directory . DIRECTORY_SEPARATOR . $id->value;

        if (!@mkdir($documentDirectory, 0700)) {
            throw new DocumentFileUnavailable('Document file identity could not be reserved.');
        }
        try {
            $this->requirePrivateDirectory($documentDirectory);
        } catch (DocumentFileUnavailable $failure) {
            @rmdir($documentDirectory);
            throw $failure;
        }

        $destination = $documentDirectory . DIRECTORY_SEPARATOR . 'content';

        if (!@move_uploaded_file($upload->temporaryPath, $destination)) {
            @rmdir($documentDirectory);
            throw new DocumentFileUnavailable('Document upload could not be moved into application storage.');
        }

        $filePermissions = @chmod($destination, 0600) ? @fileperms($destination) : false;
        if (!is_int($filePermissions) || ($filePermissions & 0777) !== 0600) {
            @unlink($destination);
            @rmdir($documentDirectory);
            throw new DocumentFileUnavailable('Stored document permissions could not be restricted.');
        }

        return $id;
    }

    public function read(DocumentFileId $id): LocalFileBody
    {
        if (!@is_dir($this->directory)) {
            throw new DocumentFileNotFound('Document file was not found.');
        }
        $this->requirePrivateDirectory($this->directory);
        $documentDirectory = $this->directory
            . DIRECTORY_SEPARATOR
            . $id->value;
        if (!@is_dir($documentDirectory)) {
            throw new DocumentFileNotFound('Document file was not found.');
        }
        $this->requirePrivateDirectory($documentDirectory);
        $path = $documentDirectory . DIRECTORY_SEPARATOR . 'content';

        if (!@is_file($path)) {
            throw new DocumentFileNotFound('Document file was not found.');
        }

        $filePermissions = @fileperms($path);
        if (
            @is_link($path)
            || !is_int($filePermissions)
            || ($filePermissions & 0777) !== 0600
        ) {
            throw new DocumentFileUnavailable('Stored document path is not a regular application file.');
        }

        $sizeBytes = @filesize($path);

        if (!is_int($sizeBytes)) {
            throw new DocumentFileUnavailable('Stored document size could not be read.');
        }

        return new LocalFileBody($path, $sizeBytes);
    }

    private function requirePrivateDirectory(string $directory): void
    {
        $permissions = @fileperms($directory);
        if (
            @is_link($directory)
            || !@is_dir($directory)
            || !is_int($permissions)
            || ($permissions & 0777) !== 0700
        ) {
            throw new DocumentFileUnavailable('Document file directory permissions are unavailable.');
        }
    }
}
