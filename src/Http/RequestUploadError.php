<?php

declare(strict_types=1);

namespace PHPThis\Http;

enum RequestUploadError: int
{
    case Success = 0;
    case IniSize = 1;
    case FormSize = 2;
    case Partial = 3;
    case NoFile = 4;
    case NoTemporaryDirectory = 6;
    case CannotWrite = 7;
    case Extension = 8;
}
