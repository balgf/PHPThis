<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

enum DocumentDetailsCacheReadOutcome: string
{
    case NotAttempted = 'not_attempted';
    case Hit = 'hit';
    case Miss = 'miss';
    case Corrupt = 'corrupt';
    case BackendUnavailable = 'backend_unavailable';
}
