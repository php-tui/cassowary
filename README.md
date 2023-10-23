PHP Cassowary
=============

Implementation of the Cassowary constraint solving algorithm. Based heavily on
[cassowary-rs](https://github.com/dylanede/cassowary-rs) which is based on
[kiwi](https://github.com/nucleic/kiwi).

This library can be used to specify and resolve constraints for user
interfaces.

```php
$x1 = Variable::new();
$x2 = Variable::new();
$s->addConstraints([
    Constraint::equalTo($x1, 0),
    Constraint::equalTo($x1, $x2)
]);
$changes = $s->fetchChanges();
```
