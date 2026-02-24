# Laravel AI Agent Kit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/creativecrafts/laravel-ai-agent-kit/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/creativecrafts/laravel-ai-agent-kit/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)

Laravel AI Agent Kit is a Laravel package that delivers a full agent‑workflow toolkit built atop the official Laravel AI SDK. It simplifies how developers build, orchestrate, and deploy AI‑powered agents in their applications by providing structured pipelines, safe tooling, and out‑of‑the‑box resilience and observability.

## Installation

You can install the package via composer:

```bash
composer require creativecrafts/laravel-ai-agent-kit
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="ai-agent-kit-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="ai-agent-kit-config"
```

These are the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="ai-agent-kit-views"
```

## Usage

```php
$laravelAiAgentKit = new CreativeCrafts\LaravelAiAgentKit();
echo $laravelAiAgentKit->echoPhrase('Hello, CreativeCrafts!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Godspower Oduose](https://github.com/rockblings)
- [All Contributors](../../contributors)

## License

The MIT Licence (MIT). Please see [Licence File](LICENSE.md) for more information.
