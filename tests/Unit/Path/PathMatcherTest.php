<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Arbiter\Path\PathMatcher;

describe('PathMatcher', function (): void {
    describe('Exact Path Matching', function (): void {
        test('matches identical paths exactly', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/bar', '/foo/bar'))->toBeTrue();
        });

        test('rejects non-matching paths', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/bar', '/foo/baz'))->toBeFalse();
        });

        test('matches root path', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/', '/'))->toBeTrue();
        });

        test('normalizes paths before matching', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/bar/', '/foo/bar'))->toBeTrue();
            expect($matcher->matches('/foo//bar', '/foo/bar'))->toBeTrue();
            expect($matcher->matches('foo/bar', '/foo/bar'))->toBeTrue();
        });

        test('handles empty path as root', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('', '/'))->toBeTrue();
            expect($matcher->matches('/', ''))->toBeTrue();
        });
    });

    describe('Single Wildcard (*) Matching', function (): void {
        test('matches single segment with asterisk', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/*', '/foo/bar'))->toBeTrue();
        });

        test('does not match multiple segments with single asterisk', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/*', '/foo/bar/baz'))->toBeFalse();
        });

        test('matches different values in same position', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/*', '/foo/bar'))->toBeTrue();
            expect($matcher->matches('/foo/*', '/foo/baz'))->toBeTrue();
            expect($matcher->matches('/foo/*', '/foo/123'))->toBeTrue();
        });

        test('matches asterisk in middle of pattern', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/*/baz', '/foo/bar/baz'))->toBeTrue();
            expect($matcher->matches('/foo/*/baz', '/foo/xyz/baz'))->toBeTrue();
        });

        test('does not match if segment count differs', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/*', '/foo'))->toBeFalse();
            expect($matcher->matches('/foo/*/baz', '/foo/baz'))->toBeFalse();
        });

        test('matches multiple asterisks in different positions', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/*/bar/*', '/foo/bar/baz'))->toBeTrue();
            expect($matcher->matches('/*/bar/*', '/xyz/bar/abc'))->toBeTrue();
            expect($matcher->matches('/*/bar/*', '/foo/bar/baz/qux'))->toBeFalse();
        });

        test('asterisk does not match empty segment', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/*/bar', '/foo//bar'))->toBeFalse();
        });
    });

    describe('Glob Wildcard (**) Matching', function (): void {
        test('matches any depth with double asterisk', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/**', '/foo/bar'))->toBeTrue();
            expect($matcher->matches('/foo/**', '/foo/bar/baz'))->toBeTrue();
            expect($matcher->matches('/foo/**', '/foo/bar/baz/qux'))->toBeTrue();
        });

        test('matches zero segments with double asterisk', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/**', '/foo'))->toBeTrue();
        });

        test('matches double asterisk in middle of pattern', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/**/baz', '/foo/baz'))->toBeTrue();
            expect($matcher->matches('/foo/**/baz', '/foo/bar/baz'))->toBeTrue();
            expect($matcher->matches('/foo/**/baz', '/foo/bar/xyz/baz'))->toBeTrue();
        });

        test('does not match if prefix does not match', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/**', '/bar/foo'))->toBeFalse();
            expect($matcher->matches('/foo/**', '/bar'))->toBeFalse();
        });

        test('does not match if suffix does not match', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/**/baz', '/foo/bar/xyz'))->toBeFalse();
            expect($matcher->matches('/foo/**/baz', '/foo/baz/extra'))->toBeFalse();
        });

        test('matches complex patterns with double asterisk', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo/**/bar/**/baz', '/foo/bar/baz'))->toBeTrue();
            expect($matcher->matches('/foo/**/bar/**/baz', '/foo/x/bar/y/baz'))->toBeTrue();
            expect($matcher->matches('/foo/**/bar/**/baz', '/foo/x/y/bar/a/b/baz'))->toBeTrue();
        });
    });

    describe('Variable Substitution (${var})', function (): void {
        test('substitutes single variable from context', function (): void {
            $matcher = new PathMatcher();
            $context = ['id' => 'xyz'];

            expect($matcher->matches('/foo/${id}/bar', '/foo/xyz/bar', $context))->toBeTrue();
        });

        test('substitutes multiple variables from context', function (): void {
            $matcher = new PathMatcher();
            $context = ['customer_id' => 'cust-123', 'order_id' => 'ord-456'];

            expect(
                $matcher->matches(
                    '/customers/${customer_id}/orders/${order_id}',
                    '/customers/cust-123/orders/ord-456',
                    $context,
                ),
            )->toBeTrue();
        });

        test('does not match if variable value differs', function (): void {
            $matcher = new PathMatcher();
            $context = ['id' => 'xyz'];

            expect($matcher->matches('/foo/${id}/bar', '/foo/abc/bar', $context))->toBeFalse();
        });

        test('leaves variable placeholder if not in context', function (): void {
            $matcher = new PathMatcher();
            $context = [];

            expect($matcher->matches('/foo/${id}/bar', '/foo/${id}/bar', $context))->toBeTrue();
            expect($matcher->matches('/foo/${id}/bar', '/foo/xyz/bar', $context))->toBeFalse();
        });

        test('handles variables with underscores and numbers', function (): void {
            $matcher = new PathMatcher();
            $context = ['user_id_2' => 'user-789'];

            expect($matcher->matches('/users/${user_id_2}', '/users/user-789', $context))->toBeTrue();
        });

        test('combines variables with wildcards', function (): void {
            $matcher = new PathMatcher();
            $context = ['id' => 'xyz'];

            expect($matcher->matches('/foo/${id}/*', '/foo/xyz/bar', $context))->toBeTrue();
            expect($matcher->matches('/foo/${id}/**', '/foo/xyz/bar/baz', $context))->toBeTrue();
        });
    });

    describe('Variable Extraction from Paths', function (): void {
        test('extracts single variable from path', function (): void {
            $matcher = new PathMatcher();

            $result = $matcher->extractVariables('/foo/${id}/bar', '/foo/xyz/bar');

            expect($result)->toBe(['id' => 'xyz']);
        });

        test('extracts multiple variables from path', function (): void {
            $matcher = new PathMatcher();

            $result = $matcher->extractVariables(
                '/customers/${customer_id}/orders/${order_id}',
                '/customers/cust-123/orders/ord-456',
            );

            expect($result)->toBe([
                'customer_id' => 'cust-123',
                'order_id' => 'ord-456',
            ]);
        });

        test('returns empty array if pattern does not match', function (): void {
            $matcher = new PathMatcher();

            $result = $matcher->extractVariables('/foo/${id}/bar', '/foo/xyz/baz');

            expect($result)->toBe([]);
        });

        test('extracts variables with complex values', function (): void {
            $matcher = new PathMatcher();

            $result = $matcher->extractVariables('/users/${user_id}', '/users/user-123-abc_xyz');

            expect($result)->toBe(['user_id' => 'user-123-abc_xyz']);
        });

        test('normalizes paths before extraction', function (): void {
            $matcher = new PathMatcher();

            $result = $matcher->extractVariables('/foo/${id}/bar/', '/foo/xyz/bar');

            expect($result)->toBe(['id' => 'xyz']);
        });

        test('does not extract across path segments', function (): void {
            $matcher = new PathMatcher();

            $result = $matcher->extractVariables('/foo/${id}', '/foo/bar/baz');

            expect($result)->toBe([]);
        });
    });

    describe('Edge Cases and Complex Scenarios', function (): void {
        test('handles empty path pattern', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('', ''))->toBeTrue();
            expect($matcher->matches('', '/'))->toBeTrue();
        });

        test('handles multiple consecutive wildcards', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/**/**', '/foo/bar/baz'))->toBeTrue();
            expect($matcher->matches('/*/**', '/foo/bar/baz'))->toBeTrue();
        });

        test('handles root path with wildcards', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/*', '/foo'))->toBeTrue();
            expect($matcher->matches('/**', '/foo/bar'))->toBeTrue();
        });

        test('case sensitive matching', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/Foo/Bar', '/Foo/Bar'))->toBeTrue();
            expect($matcher->matches('/Foo/Bar', '/foo/bar'))->toBeFalse();
        });

        test('handles special characters in paths', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo-bar/baz_qux', '/foo-bar/baz_qux'))->toBeTrue();
            expect($matcher->matches('/foo.bar', '/foo.bar'))->toBeTrue();
        });

        test('does not treat regex special chars as regex', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/foo.bar', '/fooXbar'))->toBeFalse();
            expect($matcher->matches('/foo+bar', '/foo+bar'))->toBeTrue();
            expect($matcher->matches('/foo+bar', '/foobar'))->toBeFalse();
        });

        test('complex combination of patterns', function (): void {
            $matcher = new PathMatcher();
            $context = ['id' => '123'];

            expect($matcher->matches('/api/*/users/${id}/**', '/api/v1/users/123/profile', $context))->toBeTrue();
            expect($matcher->matches('/api/*/users/${id}/**', '/api/v2/users/123/settings/privacy', $context))->toBeTrue();
            expect($matcher->matches('/api/*/users/${id}/**', '/api/v1/users/456/profile', $context))->toBeFalse();
        });

        test('handles very long paths', function (): void {
            $matcher = new PathMatcher();

            $longPath = '/a/b/c/d/e/f/g/h/i/j/k/l/m/n/o/p/q/r/s/t/u/v/w/x/y/z';
            $longPattern = '/a/b/c/d/e/f/g/h/i/j/k/l/m/n/o/p/q/r/s/t/u/v/w/x/y/z';

            expect($matcher->matches($longPattern, $longPath))->toBeTrue();
            expect($matcher->matches('/a/**/z', $longPath))->toBeTrue();
        });

        test('handles paths with only separators', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/', '/'))->toBeTrue();
            expect($matcher->matches('///', '/'))->toBeTrue();
        });
    });

    describe('Integration Tests', function (): void {
        test('real world customer API pattern', function (): void {
            $matcher = new PathMatcher();
            $context = ['customer_id' => 'cust-abc-123'];

            expect(
                $matcher->matches(
                    '/api/v1/customers/${customer_id}/settings',
                    '/api/v1/customers/cust-abc-123/settings',
                    $context,
                ),
            )->toBeTrue();
        });

        test('real world file system pattern', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/src/**/*.php', '/src/Path/PathMatcher.php'))->toBeTrue();
            expect($matcher->matches('/src/**', '/src/Path/PathMatcher.php'))->toBeTrue();
        });

        test('real world route matching with multiple variables', function (): void {
            $matcher = new PathMatcher();

            $pattern = '/organizations/${org_id}/projects/${project_id}/tasks';
            $path = '/organizations/org-123/projects/proj-456/tasks';

            $variables = $matcher->extractVariables($pattern, $path);

            expect($variables)->toBe([
                'org_id' => 'org-123',
                'project_id' => 'proj-456',
            ]);

            expect($matcher->matches($pattern, $path, $variables))->toBeTrue();
        });

        test('real world wildcard API versioning', function (): void {
            $matcher = new PathMatcher();

            expect($matcher->matches('/api/*/users', '/api/v1/users'))->toBeTrue();
            expect($matcher->matches('/api/*/users', '/api/v2/users'))->toBeTrue();
            expect($matcher->matches('/api/*/users', '/api/beta/users'))->toBeTrue();
        });
    });
});
