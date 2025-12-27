---
title: Policy Patterns
description: Common patterns and best practices for designing effective access control policies with Arbiter.
---

This guide covers common policy patterns and best practices for designing effective access control with Arbiter.

## Pattern: Hierarchical Resource Access

Control access to nested resources with increasing specificity.

```php
use Cline\Arbiter\Policy;
use Cline\Arbiter\Rule;
use Cline\Arbiter\Capability;

$policy = Policy::create('resource-hierarchy')
    // Broad access to top-level resources
    ->addRule(
        Rule::allow('/resources/*')
            ->capabilities(Capability::Read, Capability::List)
    )
    // More specific access to sub-resources
    ->addRule(
        Rule::allow('/resources/*/items/**')
            ->capabilities(Capability::Read, Capability::Create, Capability::Update)
    )
    // Deny sensitive sub-resources
    ->addRule(
        Rule::deny('/resources/*/secrets/**')
    );
```

**Use when**: Managing nested resource structures like folders, categories, or organizational hierarchies.

## Pattern: Tenant Isolation

Ensure users can only access their own tenant's resources.

```php
use Cline\Arbiter\Facades\Arbiter;

$policy = Policy::create('tenant-isolation')
    ->addRule(
        Rule::allow('/tenants/${tenant_id}/**')
            ->capabilities(Capability::Read, Capability::Update, Capability::Create, Capability::Delete)
    )
    ->addRule(
        Rule::deny('/tenants/*/admin/**')
            ->when('role', fn($role) => $role !== 'admin')
    );

// Usage
$context = ['tenant_id' => 'tenant-123', 'role' => 'user'];
Arbiter::for('tenant-isolation')->with($context)->can('/tenants/tenant-123/data', Capability::Read)->allowed();
// => true

Arbiter::for('tenant-isolation')->with($context)->can('/tenants/tenant-456/data', Capability::Read)->allowed();
// => false (different tenant)
```

**Use when**: Building SaaS applications where data must be strictly isolated by tenant/customer.

## Pattern: Role-Based Paths

Different paths accessible based on user roles.

```php
$policy = Policy::create('role-based-paths')
    // Public paths
    ->addRule(
        Rule::allow('/public/**')
            ->capabilities(Capability::Read)
    )
    // User paths
    ->addRule(
        Rule::allow('/users/**')
            ->capabilities(Capability::Read, Capability::Update)
            ->when('role', fn($r) => in_array($r, ['user', 'admin']))
    )
    // Admin paths
    ->addRule(
        Rule::allow('/admin/**')
            ->capabilities(Capability::Admin)
            ->when('role', 'admin')
    )
    // Moderator paths
    ->addRule(
        Rule::allow('/moderation/**')
            ->capabilities(Capability::Read, Capability::Update, Capability::Delete)
            ->when('role', fn($r) => in_array($r, ['moderator', 'admin']))
    );
```

**Use when**: Different user roles need access to different parts of your application.

## Pattern: Environment-Based Access

Control access based on deployment environment.

```php
$policy = Policy::create('environment-aware')
    // Production: read-only
    ->addRule(
        Rule::allow('/services/**')
            ->capabilities(Capability::Read, Capability::List)
            ->when('environment', 'production')
    )
    // Staging/Dev: full access
    ->addRule(
        Rule::allow('/services/**')
            ->capabilities(Capability::Admin)
            ->when('environment', ['staging', 'development'])
    )
    // Critical paths: admin only in production
    ->addRule(
        Rule::allow('/services/*/critical/**')
            ->capabilities(Capability::Update, Capability::Delete)
            ->when('environment', 'production')
            ->when('role', 'admin')
    );
```

**Use when**: Different access rules apply in different environments (prod vs staging vs dev).

## Pattern: Ownership-Based Access

Users can only access resources they own.

```php
use Cline\Arbiter\Facades\Arbiter;

$policy = Policy::create('ownership')
    // Own profile
    ->addRule(
        Rule::allow('/profiles/${user_id}/**')
            ->capabilities(Capability::Read, Capability::Update)
    )
    // Own documents
    ->addRule(
        Rule::allow('/documents/owned/${user_id}/**')
            ->capabilities(Capability::Admin)
    )
    // Shared documents (read-only)
    ->addRule(
        Rule::allow('/documents/shared/*')
            ->capabilities(Capability::Read)
    );

// Usage
$context = ['user_id' => 'user-123'];
Arbiter::for('ownership')->with($context)->can('/profiles/user-123/settings', Capability::Update)->allowed();
// => true (own profile)

Arbiter::for('ownership')->with($context)->can('/profiles/user-456/settings', Capability::Update)->allowed();
// => false (different user)
```

**Use when**: Resources have clear ownership and users should only modify their own.

## Pattern: Time-Based Access

Control access based on time conditions.

```php
$policy = Policy::create('time-based')
    ->addRule(
        Rule::allow('/reports/**')
            ->capabilities(Capability::Read)
            ->when('time', function($time) {
                // Business hours only
                $hour = (int)date('H', $time);
                return $hour >= 9 && $hour < 17;
            })
            ->when('day', function($day) {
                // Weekdays only
                return !in_array($day, ['Saturday', 'Sunday']);
            })
    );

// Usage
$context = [
    'time' => time(),
    'day' => date('l'),
];
```

**Use when**: Access should be restricted to specific times or days.

## Pattern: Feature Flags

Control access to features based on flags.

```php
$policy = Policy::create('features')
    // Beta features
    ->addRule(
        Rule::allow('/features/beta/**')
            ->capabilities(Capability::Read, Capability::Update)
            ->when('beta_enabled', true)
    )
    // Premium features
    ->addRule(
        Rule::allow('/features/premium/**')
            ->capabilities(Capability::Admin)
            ->when('subscription', fn($s) => in_array($s, ['pro', 'enterprise']))
    )
    // Experimental features (internal only)
    ->addRule(
        Rule::allow('/features/experimental/**')
            ->capabilities(Capability::Admin)
            ->when('is_internal', true)
    );
```

**Use when**: Rolling out features incrementally or controlling access to premium features.

## Pattern: Deny-by-Default with Allowlist

Start with no access and explicitly grant permissions.

```php
$policy = Policy::create('allowlist')
    // Explicitly allow specific paths
    ->addRule(
        Rule::allow('/api/public/**')
            ->capabilities(Capability::Read)
    )
    ->addRule(
        Rule::allow('/api/authenticated/**')
            ->capabilities(Capability::Read, Capability::Create)
            ->when('authenticated', true)
    )
    // Everything else is implicitly denied
;

// No need for explicit deny rules - Arbiter denies by default
```

**Use when**: Security is paramount and you want to explicitly list what's allowed.

## Pattern: Multiple Policies (Union)

Combine multiple policies for complex authorization.

```php
use Cline\Arbiter\Facades\Arbiter;

$basePolicy = Policy::create('base')
    ->addRule(
        Rule::allow('/shared/**')
            ->capabilities(Capability::Read)
    );

$servicePolicy = Policy::create('shipping-service')
    ->addRule(
        Rule::allow('/carriers/**')
            ->capabilities(Capability::Read, Capability::List)
    );

$adminPolicy = Policy::create('admin')
    ->addRule(
        Rule::allow('/**')
            ->capabilities(Capability::Admin)
            ->when('role', 'admin')
    );

// Arbiter evaluates all attached policies
Arbiter::register($basePolicy);
Arbiter::register($servicePolicy);
Arbiter::register($adminPolicy);

// Access granted if ANY policy allows (and none deny)
Arbiter::for(['base', 'shipping-service'])->can('/shared/config', Capability::Read)->allowed();
// => true (from base policy)

Arbiter::for(['shipping-service'])->can('/carriers/fedex', Capability::Read)->allowed();
// => true (from shipping-service policy)
```

**Use when**: Different aspects of your application have different access requirements.

## Pattern: Credential Vault Access

Control access to hierarchical credential storage.

```php
$vaultPolicy = Policy::create('credential-vault')
    // Platform credentials (admins only)
    ->addRule(
        Rule::allow('/platform/**')
            ->capabilities(Capability::Read, Capability::Update)
            ->when('role', 'admin')
    )
    // Customer credentials (customer-scoped)
    ->addRule(
        Rule::allow('/customers/${customer_id}/**')
            ->capabilities(Capability::Read)
            ->when('customer_id', fn($ctx, $value) => $ctx['authenticated_customer_id'] === $value)
    )
    // Service credentials (service-specific)
    ->addRule(
        Rule::allow('/services/${service_name}/credentials')
            ->capabilities(Capability::Read)
            ->when('service_name', fn($ctx, $value) => $ctx['service'] === $value)
    )
    // Deny write access to production credentials
    ->addRule(
        Rule::deny('/*/production/**')
            ->when('environment', 'production')
    );
```

**Use when**: Managing sensitive credentials with fine-grained access control.

## Best Practices

### 1. Order Rules by Specificity

```php
// Good: Most specific first
$policy = Policy::create('ordered')
    ->addRule(Rule::deny('/api/admin/critical'))           // Most specific
    ->addRule(Rule::allow('/api/admin/*'))                 // Specific
    ->addRule(Rule::allow('/api/**'));                     // Least specific
```

### 2. Use Explicit Denies Sparingly

```php
// Good: Deny-by-default
$policy = Policy::create('secure')
    ->addRule(Rule::allow('/allowed/path'));
// Everything else is implicitly denied

// Use explicit denies only when needed
$policy = Policy::create('with-deny')
    ->addRule(Rule::allow('/api/**'))
    ->addRule(Rule::deny('/api/sensitive'));  // Explicit override
```

### 3. Group Related Rules in Policies

```php
// Good: Cohesive policies
$readPolicy = Policy::create('read-access')
    ->addRule(Rule::allow('/public/**')->capabilities(Capability::Read))
    ->addRule(Rule::allow('/shared/**')->capabilities(Capability::Read));

$writePolicy = Policy::create('write-access')
    ->addRule(Rule::allow('/data/**')->capabilities(Capability::Create, Capability::Update));
```

### 4. Use Variables for Dynamic Paths

```php
// Good: Dynamic paths with variables
Rule::allow('/customers/${customer_id}/data/**')

// Avoid: Hardcoding IDs
Rule::allow('/customers/cust-123/data/**')  // Bad
```

### 5. Combine Conditions for Complex Logic

```php
Rule::allow('/features/advanced/**')
    ->capabilities(Capability::Read)
    ->when('subscription', fn($s) => in_array($s, ['pro', 'enterprise']))
    ->when('beta_enabled', true)
    ->when('region', fn($r) => $r !== 'restricted');
```

## Anti-Patterns to Avoid

### ❌ Overly Broad Wildcards

```php
// Bad: Too permissive
Rule::allow('/**')->capabilities(Capability::Admin)

// Good: Specific paths
Rule::allow('/admin/**')->capabilities(Capability::Admin)
```

### ❌ Complex Conditions in Rules

```php
// Bad: Business logic in conditions
->when('permission', function($p) {
    // 50 lines of logic
    return complexCalculation($p);
})

// Good: Pre-compute in context
$context['has_permission'] = $this->calculatePermission($user);
->when('has_permission', true)
```

### ❌ Mixing Concerns

```php
// Bad: Authentication + authorization in one policy
Rule::allow('/api/**')
    ->when('authenticated', true)  // Authentication concern
    ->when('role', 'admin')        // Authorization concern

// Good: Separate concerns
// Authentication: Verify user first
// Authorization: Check with Arbiter
```
