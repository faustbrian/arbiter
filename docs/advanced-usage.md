---
title: Advanced Usage
description: Advanced Arbiter features including policy repositories, evaluation results, and custom implementations.
---

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
