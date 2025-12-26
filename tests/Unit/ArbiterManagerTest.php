<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\ArbiterManager;
use Cline\Arbiter\Capability;
use Cline\Arbiter\Exception\PolicyNotFoundException;
use Cline\Arbiter\Policy;
use Cline\Arbiter\Rule;
use Cline\Arbiter\Services\EvaluationService;
use Cline\Arbiter\Services\PolicyRegistry;
use Cline\Arbiter\Services\SpecificityCalculator;

beforeEach(function (): void {
    $registry = new PolicyRegistry();
    $evaluator = new EvaluationService(
        new SpecificityCalculator(),
    );
    $this->manager = new ArbiterManager($registry, $evaluator);
});

describe('Basic matching', function (): void {
    test('exact path matches', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/foo/bar')->capabilities(Capability::Read));

        $this->manager->register($policy);

        expect($this->manager->for('test')->can('/foo/bar', Capability::Read)->allowed())->toBeTrue();
        expect($this->manager->for('test')->can('/foo/baz', Capability::Read)->allowed())->toBeFalse();
    });

    test('single wildcard matches one segment', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/foo/*')->capabilities(Capability::Read));

        $this->manager->register($policy);

        expect($this->manager->for('test')->can('/foo/bar')->allowed())->toBeTrue();
        expect($this->manager->for('test')->can('/foo/bar/baz')->allowed())->toBeFalse();
    });

    test('glob wildcard matches multiple segments', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/foo/**')->capabilities(Capability::Read));

        $this->manager->register($policy);

        expect($this->manager->for('test')->can('/foo/bar')->allowed())->toBeTrue();
        expect($this->manager->for('test')->can('/foo/bar/baz')->allowed())->toBeTrue();
        expect($this->manager->for('test')->can('/foo/bar/baz/qux')->allowed())->toBeTrue();
    });

    test('evaluate() returns full evaluation result', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/foo/bar')->capabilities(Capability::Read));

        $this->manager->register($policy);

        $result = $this->manager->for('test')->can('/foo/bar', Capability::Read)->evaluate();
        expect($result->isAllowed())->toBeTrue();

        $result = $this->manager->for('test')->can('/foo/baz', Capability::Read)->evaluate();
        expect($result->isAllowed())->toBeFalse();
    });
});

describe('Deny rules', function (): void {
    test('explicit deny overrides allow', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/foo/**')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/foo/secret'));

        $this->manager->register($policy);

        expect($this->manager->for('test')->can('/foo/bar')->allowed())->toBeTrue();
        expect($this->manager->for('test')->can('/foo/secret')->allowed())->toBeFalse();
    });

    test('explicit deny is indicated in evaluation result', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/foo/**')->capabilities(Capability::Read))
            ->addRule(Rule::deny('/foo/secret'));

        $this->manager->register($policy);

        $result = $this->manager->for('test')->can('/foo/secret')->evaluate();
        expect($result->isDenied())->toBeTrue();
        expect($result->isExplicitDeny())->toBeTrue();
        expect($result->getMatchedRule())->not->toBeNull();
    });

    test('no matching rule is implicit deny', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/foo/bar')->capabilities(Capability::Read));

        $this->manager->register($policy);

        $result = $this->manager->for('test')->can('/foo/baz')->evaluate();
        expect($result->isDenied())->toBeTrue();
        expect($result->isExplicitDeny())->toBeFalse();
        expect($result->getMatchedRule())->toBeNull();
    });

    test('denied() is inverse of allowed()', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/foo/bar')->capabilities(Capability::Read));

        $this->manager->register($policy);

        expect($this->manager->for('test')->can('/foo/bar')->denied())->toBeFalse();
        expect($this->manager->for('test')->can('/foo/baz')->denied())->toBeTrue();
    });
});

describe('Variables', function (): void {
    test('variable substitution from context', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/customers/${customer_id}/data')->capabilities(Capability::Read));

        $this->manager->register($policy);

        expect(
            $this->manager->for('test')
                ->with(['customer_id' => 'cust-123'])
                ->can('/customers/cust-123/data')
                ->allowed(),
        )->toBeTrue();

        expect(
            $this->manager->for('test')
                ->with(['customer_id' => 'cust-123'])
                ->can('/customers/cust-456/data')
                ->allowed(),
        )->toBeFalse();
    });
});

describe('Conditions', function (): void {
    test('simple equality condition', function (): void {
        $policy = Policy::create('test')
            ->addRule(
                Rule::allow('/foo/bar')
                    ->capabilities(Capability::Read)
                    ->when('environment', 'production'),
            );

        $this->manager->register($policy);

        expect(
            $this->manager->for('test')
                ->with(['environment' => 'production'])
                ->can('/foo/bar')
                ->allowed(),
        )->toBeTrue();

        expect(
            $this->manager->for('test')
                ->with(['environment' => 'staging'])
                ->can('/foo/bar')
                ->allowed(),
        )->toBeFalse();
    });
});

describe('Capabilities', function (): void {
    test('admin capability implies all others', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/admin/**')->capabilities(Capability::Admin));

        $this->manager->register($policy);

        expect($this->manager->for('test')->can('/admin/foo', Capability::Read)->allowed())->toBeTrue();
        expect($this->manager->for('test')->can('/admin/foo', Capability::Update)->allowed())->toBeTrue();
        expect($this->manager->for('test')->can('/admin/foo', Capability::Delete)->allowed())->toBeTrue();
    });

    test('accessible paths lists paths for capability', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/foo/*')->capabilities(Capability::Read))
            ->addRule(Rule::allow('/bar/**')->capabilities(Capability::Read));

        $this->manager->register($policy);

        $paths = $this->manager->for('test')->can('*', Capability::Read)->accessiblePaths();

        expect($paths)->toContain('/foo/*');
        expect($paths)->toContain('/bar/**');
    });
});

describe('Multiple policies', function (): void {
    test('evaluates multiple policies', function (): void {
        $policy1 = Policy::create('base')
            ->addRule(Rule::allow('/shared/**')->capabilities(Capability::Read));

        $policy2 = Policy::create('service')
            ->addRule(Rule::allow('/service/**')->capabilities(Capability::Read));

        $this->manager->register($policy1);
        $this->manager->register($policy2);

        expect($this->manager->for(['base', 'service'])->can('/shared/foo')->allowed())->toBeTrue();
        expect($this->manager->for(['base', 'service'])->can('/service/foo')->allowed())->toBeTrue();
    });
});

describe('Path-first API', function (): void {
    test('path() returns path evaluation conductor', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/foo/*')->capabilities(Capability::Read, Capability::Update));

        $this->manager->register($policy);

        expect($this->manager->path('/foo/bar')->against('test')->allows(Capability::Read))->toBeTrue();
        expect($this->manager->path('/foo/bar')->against('test')->denies(Capability::Delete))->toBeTrue();
    });

    test('capabilities() returns all available capabilities', function (): void {
        $policy = Policy::create('test')
            ->addRule(Rule::allow('/foo/*')->capabilities(Capability::Read, Capability::Update));

        $this->manager->register($policy);

        $caps = $this->manager->path('/foo/bar')->against('test')->capabilities();

        expect($caps)->toContain(Capability::Read);
        expect($caps)->toContain(Capability::Update);
        expect($caps)->not->toContain(Capability::Delete);
    });
});

describe('Registry management', function (): void {
    test('has() checks policy existence', function (): void {
        $policy = Policy::create('test');
        $this->manager->register($policy);

        expect($this->manager->has('test'))->toBeTrue();
        expect($this->manager->has('nonexistent'))->toBeFalse();
    });

    test('get() retrieves registered policy', function (): void {
        $policy = Policy::create('test');
        $this->manager->register($policy);

        expect($this->manager->get('test'))->toBe($policy);
    });

    test('get() throws when policy not found', function (): void {
        expect(fn () => $this->manager->get('nonexistent'))
            ->toThrow(PolicyNotFoundException::class);
    });
});

describe('Fluent conductor validation', function (): void {
    test('throws when evaluating without setting path and capability', function (): void {
        $policy = Policy::create('test');
        $this->manager->register($policy);

        expect(fn () => $this->manager->for('test')->allowed())
            ->toThrow(LogicException::class, 'Path and capability must be set');
    });

    test('throws when path conductor used without setting policies', function (): void {
        expect(fn () => $this->manager->path('/foo')->allows(Capability::Read))
            ->toThrow(LogicException::class, 'Policies must be set via against()');
    });
});
