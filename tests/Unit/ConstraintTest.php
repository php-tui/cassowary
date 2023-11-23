<?php

declare(strict_types=1);

namespace PhpTui\Cassowary\Tests\Unit;

use PhpTui\Cassowary\Constraint;
use PhpTui\Cassowary\Expression;
use PhpTui\Cassowary\RelationalOperator;
use PhpTui\Cassowary\Strength;
use PhpTui\Cassowary\Term;
use PhpTui\Cassowary\Variable;
use PHPUnit\Framework\TestCase;

class ConstraintTest extends TestCase
{
    public function testGreaterThan(): void
    {
        $var1 = Variable::new();
        $c = Constraint::greaterThanOrEqualTo($var1, 10.0, Strength::WEAK);

        self::assertEquals(Strength::WEAK, $c->strength);
        self::assertEquals(RelationalOperator::GreaterThanOrEqualTo, $c->relationalOperator);
        self::assertEquals(new Expression(
            terms: [
                new Term($var1, 1.0),
            ],
            constant: -10.0
        ), $c->expression);
    }

    public function testLessThan(): void
    {
        $var1 = Variable::new();
        $c = Constraint::lessThanOrEqualTo($var1, 10.0, Strength::WEAK);

        self::assertEquals(Strength::WEAK, $c->strength);
        self::assertEquals(RelationalOperator::LessThanOrEqualTo, $c->relationalOperator);
        self::assertEquals(new Expression(
            terms: [
                new Term($var1, 1.0),
            ],
            constant: -10.0
        ), $c->expression);
    }

    public function testEqualTo(): void
    {
        $var1 = Variable::new();
        $c = Constraint::equalTo($var1, 10.0, Strength::WEAK);

        self::assertEquals(Strength::WEAK, $c->strength);
        self::assertEquals(RelationalOperator::Equal, $c->relationalOperator);
        self::assertEquals(new Expression(
            terms: [
                new Term($var1, 1.0),
            ],
            constant: -10.0
        ), $c->expression);
    }

    public function testRightHandSideIsAVariableOrExpression(): void
    {
        $var1 = Variable::new();
        $var2 = Variable::new();
        $var3 = Variable::new();

        $c = Constraint::equalTo($var1, $var2, Strength::WEAK);

        self::assertEquals(new Expression(
            terms: [
                new Term($var1, 1.0),
                new Term($var2, -1.0),
            ],
            constant: 0
        ), $c->expression);

        $c = Constraint::equalTo($var1, $var2->add($var3), Strength::WEAK);

        self::assertEquals(new Expression(
            terms: [
                new Term($var1, 1.0),
                new Term($var2, -1.0),
                new Term($var3, -1.0),
            ],
            constant: 0
        ), $c->expression);
    }

    public function testAddBothSides(): void
    {
        $x = Variable::new();
        $y = Variable::new();

        $c = Constraint::equalTo($x->add(2.0), $y->add(10.0), Strength::REQUIRED);

        self::assertEquals(
            new Expression(
                terms: [
                    new Term($x, 1.0),
                    new Term($y, -1.0),
                ],
                constant: -8.0,
            ),
            $c->expression
        );
    }

    public function testDiv(): void
    {
        $x = Variable::new();
        $y = Variable::new();

        $c = Constraint::equalTo($x, $y->add($x)->div(2), Strength::REQUIRED);

        self::assertEquals(
            new Expression(
                terms: [
                    new Term($x, 1.0),
                    new Term($y, -0.5),
                    new Term($x, -0.5),
                ],
                constant: 0.0,
            ),
            $c->expression
        );
    }
}
