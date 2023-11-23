<?php

declare(strict_types=1);

namespace PhpTui\Cassowary;

use RuntimeException;
use Stringable;

class Variable implements Stringable
{
    private static int $idIndex = 0;

    private function __construct(public int $id, public ?string $label = null)
    {
    }

    public function __toString(): string
    {
        return sprintf('Variable(%d)', $this->id);
    }

    public function add(mixed $value): Expression
    {
        if ($value instanceof Variable) {
            return new Expression([
                new Term($this, 1.0),
                new Term($value, 1.0),
            ], 0.0);
        }

        if (is_float($value)) {
            return new Expression([
                new Term($this, 1.0),
            ], $value);
        }

        throw new RuntimeException(sprintf(
            'Do not know how to add %s to a Variable',
            get_debug_type($value)
        ));
    }

    public function toExpression(): Expression
    {
        return new Expression([new Term($this, 1.0)], 0.0);
    }

    public static function new(): self
    {
        return new self(self::$idIndex++);
    }

    public function sub(Variable $variable): Expression
    {
        return new Expression([new Term($this, 1.0), new Term($variable, -1.0)], 0.0);
    }
}
