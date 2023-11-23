<?php

declare(strict_types=1);

namespace PhpTui\Cassowary;

use Stringable;

final class Constraint implements Stringable
{
    public function __construct(
        public RelationalOperator $relationalOperator,
        public Expression $expression,
        public float $strength
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            '{operator: %s, expression: %s, strength: %s}',
            $this->relationalOperator->name,
            $this->expression->__toString(),
            $this->strength
        );
    }

    public static function new(RelationalOperator $operator, Variable|Expression $expr, Variable|Expression|float $rhs, float$strength): self
    {
        if ($expr instanceof Variable) {
            $expr = $expr->toExpression();
        }
        if ($rhs instanceof Variable) {
            $rhs = $rhs->toExpression();
        }
        if (is_float($rhs)) {
            $expr->constant($expr->constant + ($rhs * -1));
        } else {
            $expr = $expr->add($rhs->negate());
        }

        return new self($operator, $expr, $strength);
    }

    public static function greaterThanOrEqualTo(Variable|Expression $lhs, Variable|Expression|float $rhs, float $strength): Constraint
    {
        return self::new(RelationalOperator::GreaterThanOrEqualTo, $lhs, $rhs, $strength);
    }

    public static function lessThanOrEqualTo(Variable|Expression $lhs, Variable|Expression|float $rhs, float $strength): Constraint
    {
        return self::new(RelationalOperator::LessThanOrEqualTo, $lhs, $rhs, $strength);
    }

    public static function equalTo(Variable|Expression $lhs, Variable|Expression|float $rhs, float $strength): Constraint
    {
        return self::new(RelationalOperator::Equal, $lhs, $rhs, $strength);
    }

}
