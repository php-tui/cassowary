<?php

namespace PhpTui\Cassowary;

use Stringable;

class Symbol implements Stringable
{
    public function __construct(public int $id, public SymbolType $symbolType)
    {
    }

    public function __toString(): string
    {
        return sprintf('Symbol{id: %s type: %s}', $this->id, $this->symbolType->name);
    }

    public static function invalid(): self
    {
        return new self(0, SymbolType::Invalid);
    }

    public function isPivotable(): bool
    {
        return $this->symbolType === SymbolType::Slack || $this->symbolType === SymbolType::Error;
    }

}
