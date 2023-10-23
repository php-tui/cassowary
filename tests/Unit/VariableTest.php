<?php

namespace PhpTui\Cassowary\Tests\Unit;

use PhpTui\Cassowary\Expression;
use PhpTui\Cassowary\Term;
use PhpTui\Cassowary\Variable;
use PHPUnit\Framework\TestCase;

class VariableTest extends TestCase
{
    public function testSub():void
    {
        $var1 = Variable::new();
        $var2 = Variable::new();
        self::assertEquals(
            new Expression(
                [
                    new Term(
                        $var1,
                        1.0
                    ),
                    new Term(
                        $var2,
                        -1.0
                    ),
                ],
                0.0
            ),
            $var1->sub($var2)
        );
    }
}
