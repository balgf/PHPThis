<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/autoload.php';
// Share the existing pinned optional SDK installation, not its generated-export domain.
require_once dirname(__DIR__) . '/generated-s3/dependencies.php';
require_once __DIR__ . '/src/DocumentInput.php';
require_once __DIR__ . '/src/Values.php';
require_once __DIR__ . '/src/DocumentLock.php';
require_once __DIR__ . '/src/S3Settings.php';
require_once __DIR__ . '/src/ReferenceEnvironment.php';
require_once __DIR__ . '/src/CredentialProvider.php';
require_once __DIR__ . '/src/Composition.php';
require_once __DIR__ . '/src/VerifiedUpload.php';
require_once __DIR__ . '/src/DocumentS3.php';
require_once __DIR__ . '/src/Documents.php';
require_once __DIR__ . '/src/UploadDocument.php';
require_once __DIR__ . '/src/DocumentInvocation.php';
require_once __DIR__ . '/src/HttpComposition.php';

// Reference-only reuse: adopting applications retain their own canonical coordinator.
require_once dirname(__DIR__, 2) . '/skeleton/src/Observability/CorrelationId.php';
require_once dirname(__DIR__, 2) . '/skeleton/src/Observability/FailureClass.php';
require_once dirname(__DIR__, 2) . '/skeleton/src/Observability/QuerySummarySource.php';
require_once dirname(__DIR__, 2) . '/skeleton/src/Observability/RequestSummary.php';
require_once dirname(__DIR__, 2) . '/skeleton/src/Observability/RequestSummarySink.php';
require_once dirname(__DIR__, 2) . '/skeleton/src/Observability/ErrorLogRequestSummarySink.php';
require_once dirname(__DIR__, 2) . '/skeleton/src/Observability/ErrorLogOuterFailureSink.php';
require_once dirname(__DIR__, 2) . '/skeleton/src/Observability/TerminalRequestCoordinator.php';
