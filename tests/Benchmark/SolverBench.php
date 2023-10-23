<?php

namespace PhpTui\Cassowary\Tests\Benchmark;

use PhpBench\Attributes;
use PhpTui\Cassowary\Constraint;
use PhpTui\Cassowary\Strength;
use PhpTui\Cassowary\Solver;
use PhpTui\Cassowary\Variable;
use RuntimeException;

final class SolverBench
{
    #[Attributes\Iterations(10)]
    public function benchSimpleTuiExample(): void
    {
        $s = Solver::new();
        $v0 = Variable::new();
        $v1 = Variable::new();
        $v2 = Variable::new();
        $v3 = Variable::new();
        $v4 = Variable::new();
        $v5 = Variable::new();
        $v6 = Variable::new();
        $s->addConstraints([
            Constraint::greaterThanOrEqualTo($v0, 0, Strength::REQUIRED),
            Constraint::lessThanOrEqualTo($v1, 33.0, Strength::REQUIRED),
            Constraint::lessThanOrEqualTo($v0, $v1, Strength::REQUIRED),
            Constraint::greaterThanOrEqualTo($v2, 0, Strength::REQUIRED),

            Constraint::lessThanOrEqualTo($v3, 33.0, Strength::REQUIRED),
            Constraint::lessThanOrEqualTo($v2, $v3, Strength::REQUIRED),
            Constraint::greaterThanOrEqualTo($v4, 0.0, Strength::REQUIRED),
            Constraint::lessThanOrEqualTo($v5, 33.0, Strength::REQUIRED),

            Constraint::lessThanOrEqualTo($v4, $v5, Strength::REQUIRED),
            Constraint::equalTo($v1, $v2, Strength::REQUIRED),
            Constraint::equalTo($v1, $v4, Strength::REQUIRED),
            Constraint::equalTo($v0, 0.0, Strength::REQUIRED),

            Constraint::equalTo($v5, 33.0, Strength::REQUIRED),
            Constraint::equalTo($v1->sub($v0), 3.3, Strength::STRONG),
            Constraint::lessThanOrEqualTo($v3->sub($v2), 5.0, Strength::STRONG),
            Constraint::equalTo($v3->sub($v2), 5.0, Strength::MEDIUM),

            Constraint::greaterThanOrEqualTo($v5->sub($v4), 5.0, Strength::MEDIUM),
            Constraint::equalTo($v5->sub($v4), 1.0, Strength::MEDIUM),
        ]);

        // validate the result, note that using an assertion library here
        // creates a large overhead.
        $changes = $s->fetchChanges();

        if (
            [
                3.3,
                33.0,
                3.3,
                8.3,
            ] !==
            [
                $changes->getValue($v4),
                $changes->getValue($v5),
                $changes->getValue($v1),
                $changes->getValue($v3),
            ]
        ) {
            throw new RuntimeException('Solver returned unexepcted results in the benchmark');
        }
    }
}
