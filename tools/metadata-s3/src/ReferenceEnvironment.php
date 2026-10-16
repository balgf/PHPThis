<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

final class ReferenceEnvironment
{
    public static function writer(): WriterConfiguration
    {
        return new WriterConfiguration(S3Settings::fromInputs(
            \getenv('PHPTHIS_DOCUMENT_WRITER_ROOT'),
            \getenv('PHPTHIS_DOCUMENT_WRITER_BUCKET'),
            \getenv('PHPTHIS_DOCUMENT_WRITER_REGION'),
            \getenv('PHPTHIS_DOCUMENT_WRITER_ACCOUNT'),
            \getenv('PHPTHIS_DOCUMENT_WRITER_KMS'),
            \getenv('PHPTHIS_DOCUMENT_WRITER_CA_PATH'),
            \getenv('PHPTHIS_DOCUMENT_WRITER_CA_SHA256'),
            \getenv('PHPTHIS_DOCUMENT_WRITER_ACCESS_KEY'),
            \getenv('PHPTHIS_DOCUMENT_WRITER_SECRET'),
            \getenv('PHPTHIS_DOCUMENT_WRITER_TOKEN'),
            \getenv('PHPTHIS_DOCUMENT_WRITER_EXPIRES'),
        ));
    }

    public static function reconciler(): ReconcilerConfiguration
    {
        return new ReconcilerConfiguration(S3Settings::fromInputs(
            \getenv('PHPTHIS_DOCUMENT_RECONCILER_ROOT'),
            \getenv('PHPTHIS_DOCUMENT_RECONCILER_BUCKET'),
            \getenv('PHPTHIS_DOCUMENT_RECONCILER_REGION'),
            \getenv('PHPTHIS_DOCUMENT_RECONCILER_ACCOUNT'),
            \getenv('PHPTHIS_DOCUMENT_RECONCILER_KMS'),
            \getenv('PHPTHIS_DOCUMENT_RECONCILER_CA_PATH'),
            \getenv('PHPTHIS_DOCUMENT_RECONCILER_CA_SHA256'),
            \getenv('PHPTHIS_DOCUMENT_RECONCILER_ACCESS_KEY'),
            \getenv('PHPTHIS_DOCUMENT_RECONCILER_SECRET'),
            \getenv('PHPTHIS_DOCUMENT_RECONCILER_TOKEN'),
            \getenv('PHPTHIS_DOCUMENT_RECONCILER_EXPIRES'),
        ));
    }

    public static function deletion(): DeletionConfiguration
    {
        return new DeletionConfiguration(S3Settings::fromInputs(
            \getenv('PHPTHIS_DOCUMENT_DELETION_ROOT'),
            \getenv('PHPTHIS_DOCUMENT_DELETION_BUCKET'),
            \getenv('PHPTHIS_DOCUMENT_DELETION_REGION'),
            \getenv('PHPTHIS_DOCUMENT_DELETION_ACCOUNT'),
            \getenv('PHPTHIS_DOCUMENT_DELETION_KMS'),
            \getenv('PHPTHIS_DOCUMENT_DELETION_CA_PATH'),
            \getenv('PHPTHIS_DOCUMENT_DELETION_CA_SHA256'),
            \getenv('PHPTHIS_DOCUMENT_DELETION_ACCESS_KEY'),
            \getenv('PHPTHIS_DOCUMENT_DELETION_SECRET'),
            \getenv('PHPTHIS_DOCUMENT_DELETION_TOKEN'),
            \getenv('PHPTHIS_DOCUMENT_DELETION_EXPIRES'),
        ));
    }
}
