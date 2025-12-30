[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

A flexible policy-based authorization library for Laravel applications. Arbiter provides path-based access control with support for capabilities, conditions, and context-aware evaluation through an intuitive conductor API.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/) and Laravel 12+**

## Installation

```bash
composer require cline/arbiter
```

## Documentation

- **[Getting Started](cookbook/getting-started.md)** - Installation, configuration, and basic concepts
- **[Policies and Rules](cookbook/policies-and-rules.md)** - Creating and managing access control policies
- **[Capabilities](cookbook/capabilities.md)** - Read, Write, Delete, and Admin capabilities
- **[Path Patterns](cookbook/path-patterns.md)** - Wildcard matching and path-based access control
- **[Conditions](cookbook/conditions.md)** - Context-aware authorization rules
- **[Evaluation](cookbook/evaluation.md)** - Checking permissions and evaluating access
- **[Advanced Usage](cookbook/advanced-usage.md)** - Specificity, repositories, and advanced patterns

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://git.cline.sh/faustbrian/arbiter/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/arbiter.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/arbiter.svg

[link-tests]: https://git.cline.sh/faustbrian/arbiter/actions
[link-packagist]: https://packagist.org/packages/cline/arbiter
[link-downloads]: https://packagist.org/packages/cline/arbiter
[link-security]: https://git.cline.sh/faustbrian/arbiter/security
[link-maintainer]: https://git.cline.sh/faustbrian
[link-contributors]: ../../contributors
