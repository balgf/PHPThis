<?php

declare(strict_types=1);

namespace Example\Observability;

interface RequestSummarySink
{
    public function emit(RequestSummary $summary): void;
}
