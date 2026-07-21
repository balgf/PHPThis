<?php

declare(strict_types=1);

namespace Example\Documents\UpdateDocumentTitle;

enum DocumentTitleUpdateOutcome: string
{
    case Updated = 'updated';
    case NotFound = 'not_found';
}
