<?php

declare(strict_types=1);

namespace Bite\EntityRow\Repository;

enum ThrowOnFail
{
    case BadRequestException;
    case RuntimeException;
}
