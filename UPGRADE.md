# Upgrade Guide

## New Facade-Based API

Arbiter has been refactored to follow the **Facade → Manager → Conductors** pattern used across all Cline packages. This provides a more fluent, Laravel-friendly API.

### Breaking Changes

#### 1. Instantiation → Registration

**Before:**
```php
$arbiter = new Arbiter([$policy]);
```

**After:**
```php
use Cline\Arbiter\Facades\Arbiter;

Arbiter::register($policy);
```

#### 2. Policy-First Evaluation

**Before:**
```php
$arbiter->can('policy-name', Capability::Read, '/path');
```

**After:**
```php
Arbiter::for('policy-name')
    ->can('/path', Capability::Read)
    ->allowed();
```

#### 3. With Context

**Before:**
```php
$arbiter->can('policy', Capability::Read, '/path', ['key' => 'value']);
```

**After:**
```php
Arbiter::for('policy')
    ->with(['key' => 'value'])
    ->can('/path', Capability::Read)
    ->allowed();
```

#### 4. Evaluation Results

**Before:**
```php
$result = $arbiter->evaluate('policy', Capability::Read, '/path');
```

**After:**
```php
$result = Arbiter::for('policy')
    ->can('/path', Capability::Read)
    ->evaluate();
```

#### 5. List Accessible Paths

**Before:**
```php
$paths = $arbiter->listAccessiblePaths('policy', Capability::Read);
```

**After:**
```php
$paths = Arbiter::for('policy')
    ->can('*', Capability::Read)  // Dummy path
    ->accessiblePaths();
```

#### 6. Get Capabilities

**Before:**
```php
$caps = $arbiter->getCapabilities('policy', '/path', $context);
```

**After:**
```php
$caps = Arbiter::path('/path')
    ->against('policy')
    ->with($context)
    ->capabilities();
```

#### 7. Repository Configuration

**Before:**
```php
$arbiter = new Arbiter(new ArrayRepository($policies));
```

**After:**
```php
Arbiter::repository(new ArrayRepository($policies));
```

### New Features

#### Path-First API

Check what capabilities exist for a specific path:

```php
// Check specific capability
$allowed = Arbiter::path('/some/path')
    ->against('policy-name')
    ->allows(Capability::Read);

// Get all available capabilities
$caps = Arbiter::path('/some/path')
    ->against('policy-name')
    ->capabilities();
```

#### Denied Check

```php
$denied = Arbiter::for('policy')
    ->can('/path', Capability::Read)
    ->denied();  // Inverse of allowed()
```

#### Laravel Service Provider

```php
// config/app.php
'providers' => [
    Cline\Arbiter\ArbiterServiceProvider::class,
],

'aliases' => [
    'Arbiter' => Cline\Arbiter\Facades\Arbiter::class,
],
```

### Migration Strategy

1. **Update imports:**
   ```php
   // Old
   use Cline\Arbiter\Arbiter;

   // New
   use Cline\Arbiter\Facades\Arbiter;
   ```

2. **Replace instantiation with registration:**
   - Find all `new Arbiter(...)` calls
   - Replace with `Arbiter::register(...)` or `Arbiter::repository(...)`

3. **Update evaluation calls:**
   - Replace `$arbiter->can(...)` with fluent chain
   - Replace `$arbiter->evaluate(...)` with fluent chain ending in `->evaluate()`

4. **Update context passing:**
   - Move context from last parameter to `->with($context)` call

5. **Test thoroughly:**
   - Run test suite
   - Check all policy evaluations work as expected

### Architecture Changes

The new architecture separates concerns:

- **Facade** (`Cline\Arbiter\Facades\Arbiter`): Static entry point
- **Manager** (`Cline\Arbiter\ArbiterManager`): Orchestrates operations, creates conductors
- **Conductors**: Fluent APIs for policy/path evaluation
  - `PolicyEvaluationConductor`: Policy-first evaluation
  - `PathEvaluationConductor`: Path-first evaluation
- **Services**: Business logic
  - `EvaluationService`: Core evaluation logic
  - `PolicyRegistry`: Policy storage and retrieval
  - `SpecificityCalculator`: Rule specificity calculation

This follows the same pattern as `warden`, `toggl`, `bearer`, and `ancestry` packages.
