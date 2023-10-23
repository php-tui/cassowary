PHP Cassowary
=============

[![CI](https://github.com/php-tui/cassowary/actions/workflows/ci.yml/badge.svg)](https://github.com/php-tui/cassowary/actions/workflows/ci.yml)

Implementation of the Cassowary constraint solving algorithm. Based heavily on
[cassowary-rs](https://github.com/dylanede/cassowary-rs) which is based on
[kiwi](https://github.com/nucleic/kiwi).

This library can be used to specify and resolve constraints for user
interfaces.

Status
------

I've ported just enough of the code to support solving constraints. I _have
not_ ported support for edit variables or changing constraints. PRs welcome.

Installation
------------

```
$ composer require phptui/cassowary
```

What does it do?
----------------

Given we want to render a layout on a screen with a defined size. The layout
have two two sections:

```
+-------+-------------------+
|   A   |         B         |
+-------+-------------------+
```

We would need to introduce variables for each of the points in sections `a` and `b`:

```
       0                           30

       ax1     ax2,bx1             bx2
0  y1  +-------+-------------------+
       |       |                   |
2  y2  +-------+-------------------+
```

And then specify the constraints that must be maintained:

```
ax1 = 0         // the left-most point is CONSTANT at 0
ax2 >= ax1      // ax2 is REQUIRED to be greater than equal to ax1
ax2 >= ax1 + 10 // ax2 must have a WEAK requirement to be greater than equal to ax1 plus 10
bx1 = ax2       // bx1 and bx2 are REQUIRED to be contiguous
bx2 = 30        // bx2 is REQUIRED be at the right-most point - 30
// etc
```

There are two interesting things:

- Constraints can relate to each other
- Constraints have a _strength_ which determines which constraint to take into
  account if there is a conflict.

The constraint solver is able to resolve such constraints into an optimal
solution.

Usage
-----

Using the above example:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use PhpTui\Cassowary\AddConstraintaintError;
use PhpTui\Cassowary\Constraint;
use PhpTui\Cassowary\RelationalOperator;
use PhpTui\Cassowary\Solver;
use PhpTui\Cassowary\Strength;
use PhpTui\Cassowary\Variable;

$ax1 = Variable::new();
$ax2 = Variable::new();
$bx1 = Variable::new();
$bx2 = Variable::new();
$y1 = Variable::new();
$y2 = Variable::new();

$s = Solver::new();
$s->addConstraints([
    Constraint::equalTo($ax1, 0.0, Strength::REQUIRED),
    Constraint::greaterThanOrEqualTo($ax2, $ax1, Strength::REQUIRED),
    Constraint::greaterThanOrEqualTo($ax2, $ax1->add(10.0), Strength::WEAK),
    Constraint::equalTo($bx1, $ax2, Strength::REQUIRED),
    Constraint::equalTo($bx2, 30.0, Strength::REQUIRED),
    Constraint::equalTo($y1, 0.0, Strength::REQUIRED),
    Constraint::equalTo($y2, 3.0, Strength::REQUIRED),
]);
$changes = $s->fetchChanges();
var_dump($changes->getValue($ax2)); // 10
var_dump($changes->getValue($bx1);  // 10
var_dump($changes->getValue($bx2)); // 30
var_dump($changes->getValue($y2));  // 3
```

> Note that `$changes` only contains values that have changed and by default variables start at 0.0. This API is more relevant for when variables can be updated.

How does it work?
-----------------

I have no idea, but it does! I just ported the code and debugged it with great
determination until it worked.

You can read the paper
[here](https://constraints.cs.washington.edu/solvers/cassowary-tochi.pdf).
