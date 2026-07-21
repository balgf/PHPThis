<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

enum DocumentDetailsCacheWriteOutcome: string
{
    case NotAttempted = 'not_attempted';
    case Stored = 'stored';
    case PayloadRejected = 'payload_rejected';
    case BackendUnavailable = 'backend_unavailable';
}
