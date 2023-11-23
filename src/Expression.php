<?php

declare(strict_types=1);

namespace PhpTui\Cassowary;

use Stringable;

final class Expression implements Stringable
{
    /**
     * @param Term[] $terms
     */
    public function __construct(public array $terms, public float $constant)
    {
    }

    public function __toString(): string
    {
        return sprintf(
            '%s constant: %f',
            implode(', ', array_map(static fn (Term $t): string => $t->__toString(), $this->terms)),
            $this->constant
        );
    }

    public static function fromTerm(Term $term): self
    {
        return new self([$term], 0.0);
    }

    /**
     * TODO: Refactor to remove union type?
     */
    public function add(Expression|Variable $expr): Expression
    {
        if ($expr instanceof Variable) {
            $terms = $this->terms;
            $terms[] = new Term($expr);

            return new Expression($terms, 0.0);
        }

        return new Expression(
            array_merge($this->terms, $expr->terms),
            $this->constant += $expr->constant
        );

    }

    public function constant(float $constant): self
    {
        $this->constant = $constant;

        return $this;
    }

    public function negate(): self
    {
        foreach ($this->terms as $term) {
            $term->coefficient *= -1;
        }
        $this->constant *= -1;

        return $this;
    }

    public function div(float $divisor): self
    {
        return new self(
            array_map(static fn (Term $term): Term => $term->div($divisor), $this->terms),
            $this->constant
        );
    }
}
