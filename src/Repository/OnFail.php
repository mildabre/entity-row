<?php

declare(strict_types=1);

namespace Bite\EntityRow\Repository;

enum OnFail
{
    case BadRequestException;
    case RuntimeException;
}
