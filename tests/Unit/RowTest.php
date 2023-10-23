<?php

namespace PhpTui\Cassowary\Tests\Unit;

use PhpTui\Cassowary\Row;
use PhpTui\Cassowary\Symbol;
use PHPUnit\Framework\TestCase;

class RowTest extends TestCase
{
    public function testInsertSymbol(): void
    {
        $row = Row::new(10.0);
        $row->insertSymbol(Symbol::invalid(), 1.0);
        self::assertCount(1, $row);
    }
}
