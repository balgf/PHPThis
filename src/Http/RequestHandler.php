<?php

declare(strict_types=1);

namespace PHPThis\Http;

interface RequestHandler
{
    public function handle(Request $request): Response;
}
