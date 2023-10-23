<?php

namespace PhpTui\Cassowary;

class Tag
{
    public function __construct(public Symbol $marker, public Symbol $other)
    {
    }
}
