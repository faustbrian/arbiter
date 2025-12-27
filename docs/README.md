---
title: Getting Started
description: Install, configure, and start using Arbiter for path-based policy evaluation and access control in your PHP application.
---

Welcome to Arbiter, a framework-agnostic policy evaluation engine for hierarchical path-based access control. This guide will help you install, configure, and start using Arbiter in your application.

## What is Arbiter?

Arbiter provides a powerful system for answering the question: **"Can service X perform action Y on resource path Z?"**

Think of it as a flexible authorization layer that works with hierarchical paths like:
- `/customers/cust-123/carriers/fedex/api-key`
- `/platform/carriers/*/credentials`
- `/internal/services/order-service/config`

## Installation

Install Arbiter via Composer:

```bash
composer require cline/arbiter
```

### Laravel Setup

If using Laravel, register the service provider in `config/app.php`:

```php
'providers' => [
    // ...
    Cline\Arbiter\ArbiterServiceProvider::class,
],

'aliases' => [
    // ...
    'Arbiter' => Cline\Arbiter\Facades\Arbiter::class,
],
```

Or add to `bootstrap/providers.php` in Laravel 11+:

```php
return [
    // ...
    Cline\Arbiter\ArbiterServiceProvider::class,
];
```

## Core Concepts

### Paths

Hierarchical resource identifiers separated by `/`:

```php
/platform/carriers/fedex/api-key
/customers/cust-123/carriers/ups/credentials
/internal/services/order-service/config
```

### Policies

Named collections of rules that define access:

```php
use Cline\Arbiter\Policy;
use Cline\Arbiter\Rule;
use Cline\Arbiter\Capability;

$policy = Policy::create('shipping-service')
    ->addRule(
        Rule::allow('/platform/carriers/*')
            ->capabilities(Capability::Read, Capability::List)
    )
    ->addRule(
        Rule::allow('/customers/*/carriers/*')
            ->capabilities(Capability::Read)
    )
    ->addRule(
        Rule::deny('/customers/*/payments/*')
    );
```

### Capabilities

Actions that can be performed:
- `read` - View/fetch resource
- `list` - List children at path
- `create` - Create new resource
- `update` - Modify existing resource
- `delete` - Remove resource
- `admin` - Full control (implies all others)
- `deny` - Explicit denial (overrides allows)

### Rule Matching

- **Exact**: `/carriers/fedex` matches only `/carriers/fedex`
- **Single wildcard**: `/carriers/*` matches `/carriers/fedex`, `/carriers/ups`
- **Glob wildcard**: `/customers/**` matches any depth under `/customers/`
- **Variables**: `/customers/${customer_id}/*` with runtime substitution

## Fluent API Approaches

Arbiter provides two fluent API styles:

### Policy-First (Most Common)

Start with the policy and check specific paths:

```php
Arbiter::for('policy-name')
    ->can('/path', Capability::Read)
    ->allowed();
```

### Path-First

Start with the path and check which capabilities exist:

```php
Arbiter::path('/some/path')
    ->against('policy-name')
    ->allows(Capability::Read);

// Or get all available capabilities
$caps = Arbiter::path('/some/path')
    ->against('policy-name')
    ->capabilities();
```

## Basic Usage

```php
use Cline\Arbiter\Facades\Arbiter;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Rule;
use Cline\Arbiter\Capability;

// Define a policy
$policy = Policy::create('shipping-service')
    ->addRule(
        Rule::allow('/platform/carriers/*')
            ->capabilities(Capability::Read, Capability::List)
    )
    ->addRule(
        Rule::allow('/customers/*/carriers/*')
            ->capabilities(Capability::Read)
    )
    ->addRule(
        Rule::deny('/customers/*/payments/*')
    );

// Register policy
Arbiter::register($policy);

// Check access using fluent API
Arbiter::for('shipping-service')
    ->can('/platform/carriers/fedex', Capability::Read)
    ->allowed();
// => true

Arbiter::for('shipping-service')
    ->can('/customers/cust-123/carriers/ups', Capability::Read)
    ->allowed();
// => true

Arbiter::for('shipping-service')
    ->can('/customers/cust-123/payments/stripe', Capability::Read)
    ->allowed();
// => false (explicit deny)

Arbiter::for('shipping-service')
    ->can('/platform/carriers/new', Capability::Create)
    ->allowed();
// => false (no create capability)
```

## Quick Examples

### Example 1: API Access Control

```php
$apiPolicy = Policy::create('api-client')
    ->addRule(
        Rule::allow('/api/v1/users/*')
            ->capabilities(Capability::Read, Capability::List)
    )
    ->addRule(
        Rule::allow('/api/v1/posts/**')
            ->capabilities(Capability::Read, Capability::Create, Capability::Update)
    )
    ->addRule(
        Rule::deny('/api/v1/admin/**')
    );

Arbiter::register($apiPolicy);

Arbiter::for('api-client')
    ->can('/api/v1/users/123', Capability::Read)
    ->allowed();
// => true

Arbiter::for('api-client')
    ->can('/api/v1/posts/new', Capability::Create)
    ->allowed();
// => true

Arbiter::for('api-client')
    ->can('/api/v1/admin/settings', Capability::Read)
    ->allowed();
// => false (explicit deny)
```

### Example 2: Multi-Tenant Access

```php
$tenantPolicy = Policy::create('tenant-app')
    ->addRule(
        Rule::allow('/tenants/${tenant_id}/**')
            ->capabilities(Capability::Read, Capability::Update, Capability::Create)
    );

Arbiter::register($tenantPolicy);

// Context provides variable values
$context = ['tenant_id' => 'tenant-123'];

Arbiter::for('tenant-app')
    ->with($context)
    ->can('/tenants/tenant-123/settings', Capability::Read)
    ->allowed();
// => true (tenant_id matches)

Arbiter::for('tenant-app')
    ->with($context)
    ->can('/tenants/tenant-456/settings', Capability::Read)
    ->allowed();
// => false (tenant_id mismatch)
```

### Example 3: Conditional Access

```php
$envPolicy = Policy::create('production-only')
    ->addRule(
        Rule::allow('/platform/**')
            ->capabilities(Capability::Read)
            ->when('environment', 'production')
    )
    ->addRule(
        Rule::allow('/platform/**')
            ->capabilities(Capability::Read, Capability::Update)
            ->when('environment', ['staging', 'development'])
            ->when('role', fn($v) => in_array($v, ['admin', 'developer']))
    );

Arbiter::register($envPolicy);

$context = ['environment' => 'production', 'role' => 'viewer'];
Arbiter::for('production-only')
    ->with($context)
    ->can('/platform/config', Capability::Read)
    ->allowed();
// => true

$context = ['environment' => 'staging', 'role' => 'admin'];
Arbiter::for('production-only')
    ->with($context)
    ->can('/platform/config', Capability::Update)
    ->allowed();
// => true
```

## Next Steps

- Learn about [policy patterns](/docs/arbiter/policy-patterns) for common use cases
- Explore [advanced features](/docs/arbiter/advanced-usage) like repositories and evaluation results
- See [real-world examples](/docs/arbiter/examples) for credential vaults, file systems, and more
- Review the [API reference](/docs/arbiter/api-reference) for complete documentation

## Use Cases

Arbiter is perfect for:

1. **Credential Vaults** - Control access to `/customers/*/carriers/*` paths
2. **File Systems** - Permission checks on hierarchical file paths
3. **API Authorization** - Route-based access control with wildcards
4. **Multi-Tenant Apps** - Tenant-scoped resource access
5. **Feature Flags** - Path-based feature toggles
6. **CMS/Content** - Hierarchical content permissions
