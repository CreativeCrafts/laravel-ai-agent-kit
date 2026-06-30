# CI matrix

The GitHub Actions workflow runs Pest, PHPStan, Pint, `composer validate`, and `composer audit` across the supported PHP and Laravel combinations.

## Supported combinations

| PHP | Laravel 12 | Laravel 13 |
|-----|------------|------------|
| 8.3 | Yes | Yes |
| 8.4 | Yes | Yes |
| 8.5 | Yes | Yes |

The repository commits `composer.lock`, which targets the current default Testbench line. Jobs that target older supported Laravel versions resolve the corresponding `illuminate/*` and Orchestra Testbench constraints before running tests.

## Coverage

Coverage runs once on the default lock target to avoid redundant uploads.

## Local parity

To approximate a Laravel 12 install locally:

~~~bash
composer update illuminate/contracts:^12.0 illuminate/database:^12.0 illuminate/support:^12.0 orchestra/testbench:^10.11 --with-all-dependencies
~~~

Restore the usual tree by resetting `composer.json` / `composer.lock` or reinstalling from the committed lock.
