# GitHub Actions CI matrix

The [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) workflow runs **Pest**, **PHPStan** (Larastan), **Pint**, `composer validate`, and `composer audit` across supported **PHP** and **Laravel** combinations.

## Supported combinations

| PHP   | Laravel 12 | Laravel 13 |
|-------|------------|------------|
| 8.3   | Yes        | Yes        |
| 8.4   | Yes        | Yes        |
| 8.5   | Yes        | Yes        |

The repository `composer.lock` is generated against **Laravel 13** (Orchestra Testbench 11). Jobs that target **Laravel 12** run an extra `composer update` step to resolve `illuminate/*` `^12` and `orchestra/testbench` `^10.11` before tests.

## Coverage job

Code coverage (`pest --coverage`) runs once on **PHP 8.3** with the **default lock** (Laravel 13) to avoid redundant uploads.

## Local parity

To approximate a Laravel 12 install locally:

```bash
composer update illuminate/contracts:^12.0 illuminate/database:^12.0 illuminate/support:^12.0 orchestra/testbench:^10.11 --with-all-dependencies
```

Restore your usual tree with `git checkout composer.json composer.lock` (or re-run `composer install` after resetting the lock).
