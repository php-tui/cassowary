<?php

declare(strict_types=1);

namespace PhpTui\Cassowary;

enum SymbolType
{
    case External;
    case Error;
    case Dummy;
    case Invalid;
    case Slack;
}
