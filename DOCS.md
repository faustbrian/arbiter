## Table of Contents

1. [Getting Started](#doc-docs-readme) (`docs/README.md`)
2. [Advanced Usage](#doc-docs-advanced-usage) (`docs/advanced-usage.md`)
3. [Examples](#doc-docs-examples) (`docs/examples.md`)
4. [Policy Patterns](#doc-docs-policy-patterns) (`docs/policy-patterns.md`)
<a id="doc-docs-readme"></a>

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

<a id="doc-docs-advanced-usage"></a>

This guide covers advanced features of Arbiter including policy repositories, detailed evaluation results, and custom implementations.

## Policy Repositories

Arbiter supports loading policies from various sources using the repository pattern.

### Array Repository

In-memory storage, useful for testing or programmatic policy definitions:

```php
use Cline\Arbiter\Facades\Arbiter;
use Cline\Arbiter\Repository\ArrayRepository;

$repository = new ArrayRepository([
    Policy::create('shipping-service')
        ->addRule(Rule::allow('/carriers/*')->capabilities(Capability::Read)),
    Policy::create('admin')
        ->addRule(Rule::allow('/**')->capabilities(Capability::Admin)),
]);

Arbiter::repository($repository);
```

### JSON Repository

Load policies from JSON files:

```php
use Cline\Arbiter\Repository\JsonRepository;

// Single file with multiple policies
$repository = new JsonRepository('/path/to/policies.json');

// Directory of policy files (one policy per file)
$repository = new JsonRepository('/path/to/policies/', perFile: true);

Arbiter::repository($repository);
```

**policies.json:**
```json
{
  "policies": [
    {
      "name": "shipping-service",
      "description": "Access policy for shipping microservice",
      "rules": [
        {
          "path": "/carriers/*",
          "effect": "allow",
          "capabilities": ["read", "list"]
        },
        {
          "path": "/customers/*/carriers/*",
          "effect": "allow",
          "capabilities": ["read"]
        },
        {
          "path": "/payments/**",
          "effect": "deny"
        }
      ]
    }
  ]
}
```

### YAML Repository

Load policies from YAML files:

```php
use Cline\Arbiter\Repository\YamlRepository;

// Single file
$repository = new YamlRepository('/path/to/policies.yaml');

// Directory of .yaml/.yml files
$repository = new YamlRepository('/path/to/policies/', perFile: true);

Arbiter::repository($repository);
```

**policies.yaml:**
```yaml
policies:
  - name: shipping-service
    description: Access policy for shipping microservice
    rules:
      - path: /carriers/*
        effect: allow
        capabilities: [read, list]
      - path: /customers/*/carriers/*
        effect: allow
        capabilities: [read]
      - path: /payments/**
        effect: deny
```

### Loading from Files

Policies can also be loaded directly:

```php
// From YAML
$policy = Policy::fromYaml('/path/to/policy.yaml');

// From JSON
$policy = Policy::fromJson('/path/to/policy.json');

// From array
$policy = Policy::fromArray([
    'name' => 'my-policy',
    'rules' => [
        [
            'path' => '/api/*',
            'capabilities' => ['read'],
        ],
    ],
]);
```

## Evaluation Results

Get detailed information about access decisions:

```php
use Cline\Arbiter\EvaluationResult;

$result = Arbiter::for('shipping-service')->with($context)->can('/some/path', Capability::Read)->evaluate();

// Check if access is allowed
$result->isAllowed();           // bool

// Check if access is denied
$result->isDenied();            // bool

// Check if it was an explicit deny (vs no matching rule)
$result->isExplicitDeny();      // bool

// Get the matched rule (if any)
$result->getMatchedRule();      // Rule|null

// Get the matched policy (if any)
$result->getMatchedPolicy();    // Policy|null

// Get explanation for the decision
$result->getReason();           // string

// Get all policies that were evaluated
$result->getEvaluatedPolicies(); // array<Policy>
```

### Example: Logging Access Decisions

```php
$result = Arbiter::for('api-client')->with($context)->can('/api/users/123', Capability::Update)->evaluate();

if ($result->isDenied()) {
    logger()->warning('Access denied', [
        'policy' => 'api-client',
        'capability' => 'update',
        'path' => '/api/users/123',
        'reason' => $result->getReason(),
        'explicit_deny' => $result->isExplicitDeny(),
        'context' => $context,
    ]);
}
```

### Example: Auditing with Matched Rules

```php
$result = Arbiter::for('admin')->with($context)->can('/critical/data', Capability::Delete)->evaluate();

if ($result->isAllowed()) {
    $matchedRule = $result->getMatchedRule();

    audit()->log([
        'action' => 'delete',
        'path' => '/critical/data',
        'policy' => $result->getMatchedPolicy()->getName(),
        'rule' => $matchedRule->getPath(),
        'rule_description' => $matchedRule->getDescription(),
        'user' => $context['user_id'],
    ]);
}
```

## Listing Accessible Paths

Get all paths a policy can access with a specific capability:

```php
$paths = Arbiter::for('shipping-service')->can('*', Capability::Read)->accessiblePaths();
// => ['/platform/carriers/*', '/customers/*/carriers/*']

// With multiple policies
$paths = Arbiter::for(['base', 'shipping-service'])->can('*', Capability::Read)->accessiblePaths();
```

### Example: Generating API Documentation

```php
$policies = ['api-v1', 'api-v2'];

foreach ($policies as $policyName) {
    echo "## {$policyName}\n\n";

    $readPaths = Arbiter::for($policyName)->can('*', Capability::Read)->accessiblePaths();
    echo "**Read access:**\n";
    foreach ($readPaths as $path) {
        echo "- `{$path}`\n";
    }

    $writePaths = Arbiter::for($policyName)->can('*', Capability::Update)->accessiblePaths();
    echo "\n**Write access:**\n";
    foreach ($writePaths as $path) {
        echo "- `{$path}`\n";
    }
}
```

## Getting Capabilities for a Path

Check what capabilities exist for a specific path:

```php
$caps = Arbiter::path('/platform/carriers/fedex')->against('shipping-service')->capabilities();
// => [Capability::Read, Capability::List]

// With context
$caps = Arbiter::path('/customers/cust-123/settings')
    ->against('customer-portal')
    ->with(['customer_id' => 'cust-123', 'role' => 'admin'])
    ->capabilities();
// => [Capability::Read, Capability::Update, Capability::Delete]
```

### Example: Dynamic UI Permissions

```php
function getUserPermissions(string $userId, string $resourcePath): array
{
    $context = [
        'user_id' => $userId,
        'role' => $user->role,
        'tenant_id' => $user->tenant_id,
    ];

    $capabilities = Arbiter::path($resourcePath)
        ->against(['base', 'tenant-access'])
        ->with($context)
        ->capabilities();

    return [
        'can_read' => in_array(Capability::Read, $capabilities),
        'can_create' => in_array(Capability::Create, $capabilities),
        'can_update' => in_array(Capability::Update, $capabilities),
        'can_delete' => in_array(Capability::Delete, $capabilities),
    ];
}

// In your UI
$permissions = getUserPermissions($user->id, '/projects/proj-123');

if ($permissions['can_update']) {
    echo '<button>Edit Project</button>';
}

if ($permissions['can_delete']) {
    echo '<button>Delete Project</button>';
}
```

## Variable Substitution

Use dynamic values in path patterns:

```php
$policy = Policy::create('customer-portal')
    ->addRule(
        Rule::allow('/customers/${customer_id}/**')
            ->capabilities(Capability::Read, Capability::Update, Capability::Create)
    )
    ->addRule(
        Rule::allow('/customers/${customer_id}/orders/${order_id}')
            ->capabilities(Capability::Read)
    );

Arbiter::register($policy);

// Context provides variable values
$context = [
    'customer_id' => 'cust-123',
    'order_id' => 'order-456',
];

Arbiter::for('customer-portal')->with($context)->can('/customers/cust-123/settings', Capability::Read)->allowed();
// => true (customer_id matches)

Arbiter::for('customer-portal')->with($context)->can('/customers/cust-456/settings', Capability::Read)->allowed();
// => false (customer_id mismatch)

Arbiter::for('customer-portal')->with($context)->can('/customers/cust-123/orders/order-456', Capability::Read)->allowed();
// => true (both variables match)
```

## Conditional Rules

Add conditions that must be satisfied:

```php
$policy = Policy::create('conditional-access')
    // Simple equality condition
    ->addRule(
        Rule::allow('/production/**')
            ->capabilities(Capability::Read)
            ->when('environment', 'production')
    )
    // In-array condition
    ->addRule(
        Rule::allow('/staging/**')
            ->capabilities(Capability::Read, Capability::Update)
            ->when('environment', ['staging', 'development'])
    )
    // Callable condition
    ->addRule(
        Rule::allow('/admin/**')
            ->capabilities(Capability::Admin)
            ->when('role', fn($role) => in_array($role, ['admin', 'superuser']))
    )
    // Multiple conditions (all must match)
    ->addRule(
        Rule::allow('/features/beta/**')
            ->capabilities(Capability::Read)
            ->when('beta_enabled', true)
            ->when('subscription', fn($s) => in_array($s, ['pro', 'enterprise']))
            ->when('region', 'us-west')
    );
```

### Example: Request-Based Conditions

```php
// In a Laravel middleware
public function handle(Request $request, Closure $next)
{
    $path = $this->extractResourcePath($request);
    $capability = $this->mapMethodToCapability($request->method());

    $context = [
        'user_id' => $request->user()->id,
        'role' => $request->user()->role,
        'environment' => config('app.env'),
        'ip_address' => $request->ip(),
        'time' => time(),
        'tenant_id' => $request->user()->tenant_id,
    ];

    if (!Arbiter::for('api-access')->with($context)->can($path, $capability)->allowed()) {
        abort(403, 'Access denied');
    }

    return $next($request);
}
```

## Rule Specificity

More specific rules take precedence:

```php
$policy = Policy::create('specificity-example')
    // Glob wildcard (least specific)
    ->addRule(Rule::allow('/api/**')->capabilities(Capability::Read))

    // Single wildcard (more specific)
    ->addRule(Rule::deny('/api/admin/*'))

    // Exact path (most specific)
    ->addRule(Rule::allow('/api/admin/health')->capabilities(Capability::Read));

Arbiter::register($policy);

Arbiter::for('specificity-example')->can('/api/users', Capability::Read)->allowed();
// => true (matched /** glob)

Arbiter::for('specificity-example')->can('/api/admin/users', Capability::Read)->allowed();
// => false (matched /admin/* deny, more specific than /**)

Arbiter::for('specificity-example')->can('/api/admin/health', Capability::Read)->allowed();
// => true (matched exact path, most specific)
```

**Specificity order (highest to lowest):**
1. Exact paths: `/api/users/123`
2. Paths with fewer wildcards: `/api/users/*`
3. Paths with more wildcards: `/api/**`
4. Glob patterns: `/**`

## Multiple Policies

Combine policies for complex authorization:

```php
$basePolicy = Policy::create('base')
    ->addRule(Rule::allow('/shared/**')->capabilities(Capability::Read));

$servicePolicy = Policy::create('shipping-service')
    ->addRule(Rule::allow('/carriers/**')->capabilities(Capability::Read));

$adminPolicy = Policy::create('admin')
    ->addRule(
        Rule::allow('/**')
            ->capabilities(Capability::Admin)
            ->when('role', 'admin')
    );

Arbiter::register($basePolicy);
Arbiter::register($servicePolicy);
Arbiter::register($adminPolicy);

// Check against multiple policies
$context = ['role' => 'user'];

// Access granted if ANY policy allows (and none explicitly deny)
Arbiter::for(['base', 'shipping-service'])->with($context)->can('/shared/config', Capability::Read)->allowed();
// => true (base policy allows)

Arbiter::for(['base', 'shipping-service'])->with($context)->can('/carriers/fedex', Capability::Read)->allowed();
// => true (shipping-service policy allows)

Arbiter::for(['admin'])->with(['role' => 'admin'])->can('/anything', Capability::Delete)->allowed();
// => true (admin policy with role condition)
```

## Admin Capability

The `Admin` capability implies all others:

```php
$policy = Policy::create('admin-access')
    ->addRule(
        Rule::allow('/platform/**')
            ->capabilities(Capability::Admin)
            ->when('role', 'admin')
    );

Arbiter::register($policy);
$context = ['role' => 'admin'];

// Admin capability grants all capabilities
Arbiter::for('admin-access')->with($context)->can('/platform/config', Capability::Read)->allowed();
// => true

Arbiter::for('admin-access')->with($context)->can('/platform/config', Capability::Update)->allowed();
// => true

Arbiter::for('admin-access')->with($context)->can('/platform/config', Capability::Delete)->allowed();
// => true

// All capabilities are available
$caps = Arbiter::path('/platform/config')->against('admin-access')->with($context)->capabilities();
// Contains all capabilities because Admin implies them
```

## Serialization

Policies and rules can be serialized:

```php
// To array
$array = $policy->toArray();

// To JSON
$json = json_encode($policy); // Uses JsonSerializable

// From array
$policy = Policy::fromArray($array);

// Round-trip
$originalPolicy = Policy::create('test')
    ->addRule(Rule::allow('/api/*')->capabilities(Capability::Read));

$array = $originalPolicy->toArray();
$restoredPolicy = Policy::fromArray($array);

// $restoredPolicy is equivalent to $originalPolicy
```

### Example: Policy Versioning

```php
class PolicyVersionControl
{
    public function saveVersion(Policy $policy, string $version): void
    {
        $data = $policy->toArray();
        $data['version'] = $version;
        $data['created_at'] = time();

        file_put_contents(
            "/policies/{$policy->getName()}/{$version}.json",
            json_encode($data, JSON_PRETTY_PRINT)
        );
    }

    public function loadVersion(string $policyName, string $version): Policy
    {
        $path = "/policies/{$policyName}/{$version}.json";
        return Policy::fromJson($path);
    }
}
```

## Exception Handling

Arbiter throws specific exceptions for error conditions:

```php
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Exception\InvalidPolicyException;

try {
    Arbiter::for('non-existent-policy')->can('/path', Capability::Read)->allowed();
} catch (PolicyNotFoundException $e) {
    // Policy not found in arbiter
    logger()->error("Policy not found: {$e->getMessage()}");
}

try {
    $policy = Policy::fromArray(['invalid' => 'data']);
} catch (InvalidPolicyException $e) {
    // Invalid policy structure
    logger()->error("Invalid policy: {$e->getMessage()}");
}
```

<a id="doc-docs-examples"></a>

This guide provides complete, production-ready examples of using Arbiter for common use cases.

## Credential Vault Access Control

A complete example of managing access to hierarchical credential storage.

```php
use Cline\Arbiter\Facades\Arbiter;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Rule;
use Cline\Arbiter\Capability;

class CredentialVault
{
    public function __construct(
        private CredentialStore $store
    ) {}

    public function getCredential(string $path, string $service, array $user): ?string
    {
        $context = [
            'service' => $service,
            'customer_id' => $user['customer_id'] ?? null,
            'role' => $user['role'],
            'environment' => config('app.env'),
        ];

        if (!Arbiter::for('credential-vault')->with($context)->can($path, Capability::Read)->allowed()) {
            throw new UnauthorizedException("Access denied to credential: {$path}");
        }

        return $this->store->get($path);
    }

    public function updateCredential(string $path, string $value, string $service, array $user): void
    {
        $context = [
            'service' => $service,
            'customer_id' => $user['customer_id'] ?? null,
            'role' => $user['role'],
            'environment' => config('app.env'),
        ];

        if (!Arbiter::for('credential-vault')->with($context)->can($path, Capability::Update)->allowed()) {
            throw new UnauthorizedException("Access denied to update credential: {$path}");
        }

        $this->store->set($path, $value);
    }
}

// Policy definition
$vaultPolicy = Policy::create('credential-vault')
    // Platform credentials (admins only)
    ->addRule(
        Rule::allow('/platform/carriers/**/credentials')
            ->capabilities(Capability::Read, Capability::Update)
            ->when('role', 'admin')
    )
    // Customer credentials (customer-scoped with service access)
    ->addRule(
        Rule::allow('/customers/${customer_id}/carriers/*/credentials')
            ->capabilities(Capability::Read)
    )
    // Service-specific credentials
    ->addRule(
        Rule::allow('/services/${service}/credentials')
            ->capabilities(Capability::Read)
    )
    // Deny production updates in production environment
    ->addRule(
        Rule::deny('/*/production/**')
            ->when('environment', 'production')
            ->when('role', fn($r) => $r !== 'admin')
    );

Arbiter::register($vaultPolicy);

// Usage
$vault = new CredentialVault($credentialStore);

// Customer accessing their own credentials
$user = ['customer_id' => 'cust-123', 'role' => 'user'];
$apiKey = $vault->getCredential(
    '/customers/cust-123/carriers/fedex/credentials',
    'shipping-service',
    $user
); // ✓ Allowed

// Service accessing its own credentials
$serviceUser = ['role' => 'service'];
$key = $vault->getCredential(
    '/services/order-service/credentials',
    'order-service',
    $serviceUser
); // ✓ Allowed

// Admin accessing platform credentials
$admin = ['role' => 'admin'];
$platformKey = $vault->getCredential(
    '/platform/carriers/fedex/credentials',
    'admin-console',
    $admin
); // ✓ Allowed
```

## API Authorization Middleware

Complete Laravel middleware for API access control.

```php
use Illuminate\Http\Request;
use Closure;
use Cline\Arbiter\Facades\Arbiter;
use Cline\Arbiter\Capability;

class ArbiterAuthorizationMiddleware
{
    public function handle(Request $request, Closure $next, string $policy = 'api-access')
    {
        $path = $this->extractResourcePath($request);
        $capability = $this->mapMethodToCapability($request->method());

        $context = $this->buildContext($request);

        $result = Arbiter::for($policy)->with($context)->can($path, $capability)->evaluate();

        if ($result->isDenied()) {
            logger()->warning('API access denied', [
                'policy' => $policy,
                'capability' => $capability->value,
                'path' => $path,
                'reason' => $result->getReason(),
                'explicit_deny' => $result->isExplicitDeny(),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Access denied',
                'message' => $result->getReason(),
            ], 403);
        }

        // Log successful access for audit
        logger()->info('API access granted', [
            'policy' => $policy,
            'capability' => $capability->value,
            'path' => $path,
            'matched_rule' => $result->getMatchedRule()?->getPath(),
            'user_id' => $request->user()?->id,
        ]);

        return $next($request);
    }

    private function extractResourcePath(Request $request): string
    {
        // Convert Laravel route to resource path
        // /api/v1/users/123 -> /users/123
        // /api/v1/posts/456/comments -> /posts/456/comments

        $path = $request->path();

        // Remove API version prefix
        $path = preg_replace('#^api/v\d+/#', '', $path);

        return '/' . $path;
    }

    private function mapMethodToCapability(string $method): Capability
    {
        return match($method) {
            'GET', 'HEAD' => Capability::Read,
            'POST' => Capability::Create,
            'PUT', 'PATCH' => Capability::Update,
            'DELETE' => Capability::Delete,
            default => Capability::Read,
        };
    }

    private function buildContext(Request $request): array
    {
        $user = $request->user();

        return [
            'user_id' => $user?->id,
            'role' => $user?->role,
            'tenant_id' => $user?->tenant_id,
            'environment' => config('app.env'),
            'ip_address' => $request->ip(),
            'authenticated' => $user !== null,
        ];
    }
}

// Policy for API
$apiPolicy = Policy::create('api-access')
    // Public endpoints
    ->addRule(
        Rule::allow('/auth/**')
            ->capabilities(Capability::Create, Capability::Read)
    )
    // User endpoints (authenticated users)
    ->addRule(
        Rule::allow('/users/${user_id}/**')
            ->capabilities(Capability::Read, Capability::Update)
            ->when('authenticated', true)
    )
    // Posts (authenticated for write, public for read)
    ->addRule(
        Rule::allow('/posts/**')
            ->capabilities(Capability::Read, Capability::List)
    )
    ->addRule(
        Rule::allow('/posts/**')
            ->capabilities(Capability::Create, Capability::Update, Capability::Delete)
            ->when('authenticated', true)
    )
    // Admin endpoints
    ->addRule(
        Rule::allow('/admin/**')
            ->capabilities(Capability::Admin)
            ->when('role', 'admin')
    )
    // Tenant-scoped resources
    ->addRule(
        Rule::allow('/tenants/${tenant_id}/**')
            ->capabilities(Capability::Read, Capability::Update, Capability::Create)
            ->when('authenticated', true)
    );

// Register middleware
Route::middleware(['arbiter:api-access'])->group(function () {
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);
});
```

## File System Permissions

Complete file system access control implementation.

```php
class FileSystem
{
    public function __construct(
        private string $basePath
    ) {}

    public function read(string $path, array $user): string
    {
        $this->authorize($path, Capability::Read, $user);

        return file_get_contents($this->basePath . $path);
    }

    public function write(string $path, string $contents, array $user): void
    {
        $this->authorize($path, Capability::Update, $user);

        file_put_contents($this->basePath . $path, $contents);
    }

    public function create(string $path, string $contents, array $user): void
    {
        $this->authorize($path, Capability::Create, $user);

        file_put_contents($this->basePath . $path, $contents);
    }

    public function delete(string $path, array $user): void
    {
        $this->authorize($path, Capability::Delete, $user);

        unlink($this->basePath . $path);
    }

    public function list(string $directory, array $user): array
    {
        $this->authorize($directory, Capability::List, $user);

        $files = scandir($this->basePath . $directory);

        // Filter files based on read permission
        return array_filter($files, function($file) use ($directory, $user) {
            if ($file === '.' || $file === '..') {
                return false;
            }

            $filePath = rtrim($directory, '/') . '/' . $file;

            return Arbiter::for('filesystem')
                ->with($this->buildContext($user))
                ->can($filePath, Capability::Read)
                ->allowed();
        });
    }

    private function authorize(string $path, Capability $capability, array $user): void
    {
        $context = $this->buildContext($user);

        if (!Arbiter::for('filesystem')->with($context)->can($path, $capability)->allowed()) {
            throw new UnauthorizedException(
                "Access denied: Cannot {$capability->value} file at {$path}"
            );
        }
    }

    private function buildContext(array $user): array
    {
        return [
            'user_id' => $user['id'],
            'group_id' => $user['group_id'],
            'role' => $user['role'],
        ];
    }
}

// Policy definition
$filesystemPolicy = Policy::create('filesystem')
    // Public directory (read-only)
    ->addRule(
        Rule::allow('/public/**')
            ->capabilities(Capability::Read, Capability::List)
    )
    // User home directories
    ->addRule(
        Rule::allow('/home/${user_id}/**')
            ->capabilities(Capability::Admin)
    )
    // Shared group directories
    ->addRule(
        Rule::allow('/shared/${group_id}/**')
            ->capabilities(Capability::Read, Capability::List, Capability::Create, Capability::Update)
    )
    // System directories (admins only)
    ->addRule(
        Rule::allow('/system/**')
            ->capabilities(Capability::Admin)
            ->when('role', 'admin')
    )
    // Deny write access to read-only directories
    ->addRule(
        Rule::deny('/readonly/**')
    );

// Usage
$fs = new FileSystem('/var/data');

$user = ['id' => 'user-123', 'group_id' => 'group-1', 'role' => 'user'];

// Read public file
$content = $fs->read('/public/readme.txt', $user); // ✓ Allowed

// Write to own home directory
$fs->write('/home/user-123/notes.txt', 'My notes', $user); // ✓ Allowed

// Create in shared group directory
$fs->create('/shared/group-1/doc.txt', 'Shared doc', $user); // ✓ Allowed

// Attempt to access another user's home directory
$fs->read('/home/user-456/private.txt', $user); // ✗ Denied
```

## Multi-Tenant SaaS Application

Complete multi-tenant access control with team hierarchies.

```php
class TenantController
{
    public function __construct(
        private TenantRepository $tenants
    ) {}

    public function show(Request $request, string $tenantId)
    {
        $user = $request->user();

        if (!$this->canAccessTenant($tenantId, Capability::Read, $user)) {
            abort(403, 'Access denied to tenant');
        }

        return $this->tenants->find($tenantId);
    }

    public function update(Request $request, string $tenantId)
    {
        $user = $request->user();

        if (!$this->canAccessTenant($tenantId, Capability::Update, $user)) {
            abort(403, 'Cannot update tenant');
        }

        $tenant = $this->tenants->find($tenantId);
        $tenant->update($request->validated());

        return $tenant;
    }

    public function listResources(Request $request, string $tenantId, string $resourceType)
    {
        $user = $request->user();
        $path = "/tenants/{$tenantId}/{$resourceType}";

        $context = [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'role' => $user->role,
            'team_id' => $user->team_id,
        ];

        if (!Arbiter::for('saas-access')->with($context)->can($path, Capability::List)->allowed()) {
            abort(403, 'Cannot list resources');
        }

        // Get capabilities for UI rendering
        $capabilities = Arbiter::path($path)->against('saas-access')->with($context)->capabilities();

        return [
            'resources' => $this->tenants->getResources($tenantId, $resourceType),
            'permissions' => [
                'can_create' => in_array(Capability::Create, $capabilities),
                'can_update' => in_array(Capability::Update, $capabilities),
                'can_delete' => in_array(Capability::Delete, $capabilities),
            ],
        ];
    }

    private function canAccessTenant(string $tenantId, Capability $capability, $user): bool
    {
        $context = [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'role' => $user->role,
            'team_id' => $user->team_id,
        ];

        return Arbiter::for('saas-access')
            ->with($context)
            ->can("/tenants/{$tenantId}", $capability)
            ->allowed();
    }
}

// Policy definition
$saasPolicy = Policy::create('saas-access')
    // Users can access their own tenant
    ->addRule(
        Rule::allow('/tenants/${tenant_id}/**')
            ->capabilities(Capability::Read, Capability::List)
    )
    // Team members can collaborate on team resources
    ->addRule(
        Rule::allow('/tenants/${tenant_id}/teams/${team_id}/**')
            ->capabilities(Capability::Read, Capability::Create, Capability::Update)
    )
    // Tenant admins have full access to their tenant
    ->addRule(
        Rule::allow('/tenants/${tenant_id}/**')
            ->capabilities(Capability::Admin)
            ->when('role', 'tenant-admin')
    )
    // Platform admins have access to all tenants
    ->addRule(
        Rule::allow('/tenants/**')
            ->capabilities(Capability::Admin)
            ->when('role', 'platform-admin')
    )
    // Billing info (tenant admins only)
    ->addRule(
        Rule::deny('/tenants/*/billing/**')
    )
    ->addRule(
        Rule::allow('/tenants/${tenant_id}/billing/**')
            ->capabilities(Capability::Read, Capability::Update)
            ->when('role', fn($r) => in_array($r, ['tenant-admin', 'platform-admin']))
    );
```

## Feature Flags System

Complete feature flag implementation with rollout control.

```php
class FeatureFlags
{
    public function __construct(
        private FeatureFlagRepository $flags
    ) {}

    public function isEnabled(string $feature, array $user): bool
    {
        $path = "/features/{$feature}";

        $context = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'subscription' => $user['subscription'],
            'created_at' => $user['created_at'],
            'beta_enabled' => $user['beta_enabled'] ?? false,
            'is_internal' => $this->isInternalEmail($user['email']),
        ];

        return Arbiter::for('features')->with($context)->can($path, Capability::Read)->allowed();
    }

    public function getEnabledFeatures(array $user): array
    {
        $allFeatures = ['beta', 'premium', 'experimental', 'v2-ui', 'advanced-analytics'];
        $enabled = [];

        foreach ($allFeatures as $feature) {
            if ($this->isEnabled($feature, $user)) {
                $enabled[] = $feature;
            }
        }

        return $enabled;
    }

    private function isInternalEmail(string $email): bool
    {
        return str_ends_with($email, '@company.com');
    }
}

// Policy definition
$featurePolicy = Policy::create('features')
    // Beta features (opt-in)
    ->addRule(
        Rule::allow('/features/beta')
            ->capabilities(Capability::Read)
            ->when('beta_enabled', true)
    )
    // Premium features (subscription-based)
    ->addRule(
        Rule::allow('/features/premium')
            ->capabilities(Capability::Read)
            ->when('subscription', fn($s) => in_array($s, ['pro', 'enterprise']))
    )
    // Experimental features (internal only)
    ->addRule(
        Rule::allow('/features/experimental')
            ->capabilities(Capability::Read)
            ->when('is_internal', true)
    )
    // New UI (gradual rollout by signup date)
    ->addRule(
        Rule::allow('/features/v2-ui')
            ->capabilities(Capability::Read)
            ->when('created_at', function($timestamp) {
                // Users who signed up after Jan 1, 2024
                return $timestamp > strtotime('2024-01-01');
            })
    )
    // Advanced analytics (premium + beta)
    ->addRule(
        Rule::allow('/features/advanced-analytics')
            ->capabilities(Capability::Read)
            ->when('subscription', fn($s) => in_array($s, ['pro', 'enterprise']))
            ->when('beta_enabled', true)
    );

// Usage in Blade template
@if($featureFlags->isEnabled('v2-ui', $user))
    @include('layouts.v2-navbar')
@else
    @include('layouts.navbar')
@endif

// Usage in controller
public function dashboard(Request $request, FeatureFlags $flags)
{
    $user = $request->user()->toArray();

    return view('dashboard', [
        'features' => $flags->getEnabledFeatures($user),
        'show_premium' => $flags->isEnabled('premium', $user),
        'show_analytics' => $flags->isEnabled('advanced-analytics', $user),
    ]);
}
```

## Content Management System

Complete CMS with hierarchical content permissions.

```php
class ContentController
{

    public function show(string $path, array $user)
    {
        if (!$this->canAccess($path, Capability::Read, $user)) {
            abort(403, 'Cannot view this content');
        }

        return Content::where('path', $path)->firstOrFail();
    }

    public function create(Request $request, string $parentPath, array $user)
    {
        if (!$this->canAccess($parentPath, Capability::Create, $user)) {
            abort(403, 'Cannot create content here');
        }

        return Content::create([
            'path' => $parentPath . '/' . $request->input('slug'),
            'title' => $request->input('title'),
            'body' => $request->input('body'),
            'author_id' => $user['id'],
            'status' => 'draft',
        ]);
    }

    public function publish(string $path, array $user)
    {
        // Publishing requires special permission
        if (!$this->canAccess($path, Capability::Update, $user, ['action' => 'publish'])) {
            abort(403, 'Cannot publish content');
        }

        $content = Content::where('path', $path)->firstOrFail();
        $content->update(['status' => 'published', 'published_at' => now()]);

        return $content;
    }

    private function canAccess(string $path, Capability $capability, array $user, array $extra = []): bool
    {
        $context = array_merge([
            'user_id' => $user['id'],
            'role' => $user['role'],
            'department' => $user['department'],
        ], $extra);

        return Arbiter::for('cms')->with($context)->can($path, $capability)->allowed();
    }
}

// Policy definition
$cmsPolicy = Policy::create('cms')
    // Public content (anyone can read)
    ->addRule(
        Rule::allow('/content/public/**')
            ->capabilities(Capability::Read)
    )
    // Authors can create drafts
    ->addRule(
        Rule::allow('/content/drafts/**')
            ->capabilities(Capability::Read, Capability::Create, Capability::Update)
            ->when('role', fn($r) => in_array($r, ['author', 'editor', 'admin']))
    )
    // Editors can publish
    ->addRule(
        Rule::allow('/content/**')
            ->capabilities(Capability::Update)
            ->when('action', 'publish')
            ->when('role', fn($r) => in_array($r, ['editor', 'admin']))
    )
    // Department-specific content
    ->addRule(
        Rule::allow('/content/departments/${department}/**')
            ->capabilities(Capability::Read, Capability::Create, Capability::Update)
    )
    // Admin content (admins only)
    ->addRule(
        Rule::allow('/content/admin/**')
            ->capabilities(Capability::Admin)
            ->when('role', 'admin')
    );
```

These examples demonstrate how Arbiter can be used to implement robust, flexible access control across different application types while maintaining clean, testable code.

<a id="doc-docs-policy-patterns"></a>

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
