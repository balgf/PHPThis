<?php

declare(strict_types=1);

namespace GeneratedS3Recipe;

final class ReferenceEnvironment
{
    public static function writer(): WriterConfiguration
    {
        return new WriterConfiguration(S3Settings::fromInputs(
            \getenv('PHPTHIS_EXPORT_WRITER_ROOT'),
            \getenv('PHPTHIS_EXPORT_WRITER_BUCKET'),
            \getenv('PHPTHIS_EXPORT_WRITER_REGION'),
            \getenv('PHPTHIS_EXPORT_WRITER_ACCOUNT'),
            \getenv('PHPTHIS_EXPORT_WRITER_KMS'),
            \getenv('PHPTHIS_EXPORT_WRITER_CA_PATH'),
            \getenv('PHPTHIS_EXPORT_WRITER_CA_SHA256'),
            \getenv('PHPTHIS_EXPORT_WRITER_ACCESS_KEY'),
            \getenv('PHPTHIS_EXPORT_WRITER_SECRET'),
            \getenv('PHPTHIS_EXPORT_WRITER_TOKEN'),
            \getenv('PHPTHIS_EXPORT_WRITER_EXPIRES'),
        ));
    }

    public static function reader(): ReaderConfiguration
    {
        return new ReaderConfiguration(S3Settings::fromInputs(
            \getenv('PHPTHIS_EXPORT_READER_ROOT'),
            \getenv('PHPTHIS_EXPORT_READER_BUCKET'),
            \getenv('PHPTHIS_EXPORT_READER_REGION'),
            \getenv('PHPTHIS_EXPORT_READER_ACCOUNT'),
            \getenv('PHPTHIS_EXPORT_READER_KMS'),
            \getenv('PHPTHIS_EXPORT_READER_CA_PATH'),
            \getenv('PHPTHIS_EXPORT_READER_CA_SHA256'),
            \getenv('PHPTHIS_EXPORT_READER_ACCESS_KEY'),
            \getenv('PHPTHIS_EXPORT_READER_SECRET'),
            \getenv('PHPTHIS_EXPORT_READER_TOKEN'),
            \getenv('PHPTHIS_EXPORT_READER_EXPIRES'),
        ));
    }

    public static function reconciler(): ReconcilerConfiguration
    {
        return new ReconcilerConfiguration(S3Settings::fromInputs(
            \getenv('PHPTHIS_EXPORT_RECONCILER_ROOT'),
            \getenv('PHPTHIS_EXPORT_RECONCILER_BUCKET'),
            \getenv('PHPTHIS_EXPORT_RECONCILER_REGION'),
            \getenv('PHPTHIS_EXPORT_RECONCILER_ACCOUNT'),
            \getenv('PHPTHIS_EXPORT_RECONCILER_KMS'),
            \getenv('PHPTHIS_EXPORT_RECONCILER_CA_PATH'),
            \getenv('PHPTHIS_EXPORT_RECONCILER_CA_SHA256'),
            \getenv('PHPTHIS_EXPORT_RECONCILER_ACCESS_KEY'),
            \getenv('PHPTHIS_EXPORT_RECONCILER_SECRET'),
            \getenv('PHPTHIS_EXPORT_RECONCILER_TOKEN'),
            \getenv('PHPTHIS_EXPORT_RECONCILER_EXPIRES'),
        ));
    }

    public static function deletion(): DeletionConfiguration
    {
        return new DeletionConfiguration(S3Settings::fromInputs(
            \getenv('PHPTHIS_EXPORT_DELETION_ROOT'),
            \getenv('PHPTHIS_EXPORT_DELETION_BUCKET'),
            \getenv('PHPTHIS_EXPORT_DELETION_REGION'),
            \getenv('PHPTHIS_EXPORT_DELETION_ACCOUNT'),
            \getenv('PHPTHIS_EXPORT_DELETION_KMS'),
            \getenv('PHPTHIS_EXPORT_DELETION_CA_PATH'),
            \getenv('PHPTHIS_EXPORT_DELETION_CA_SHA256'),
            \getenv('PHPTHIS_EXPORT_DELETION_ACCESS_KEY'),
            \getenv('PHPTHIS_EXPORT_DELETION_SECRET'),
            \getenv('PHPTHIS_EXPORT_DELETION_TOKEN'),
            \getenv('PHPTHIS_EXPORT_DELETION_EXPIRES'),
        ));
    }
}
