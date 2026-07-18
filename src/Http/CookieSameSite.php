<?php

declare(strict_types=1);

namespace PHPThis\Http;

enum CookieSameSite: string
{
    case Lax = 'Lax';
    case Strict = 'Strict';
    case None = 'None';
}
