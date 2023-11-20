<?php

declare(strict_types=1);

namespace PhpTui\Cassowary;

enum RelationalOperator
{
    case GreaterThanOrEqualTo;
    case LessThanOrEqualTo;
    case Equal;
}
