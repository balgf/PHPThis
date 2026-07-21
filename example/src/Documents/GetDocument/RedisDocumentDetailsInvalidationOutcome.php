<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

enum RedisDocumentDetailsInvalidationOutcome: string
{
    case NotAttempted = 'not_attempted';
    case Deleted = 'deleted';
    case Absent = 'absent';
    case BackendUnavailable = 'backend_unavailable';
}
