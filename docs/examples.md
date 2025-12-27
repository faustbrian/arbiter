---
title: Real-World Examples
description: Complete real-world examples of using Arbiter for credential vaults, file systems, API authorization, and more.
---

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
