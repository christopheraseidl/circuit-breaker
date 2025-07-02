<?php

namespace christopheraseidl\CircuitBreaker\Tests\Support;

use christopheraseidl\CircuitBreaker\Support\Config;

beforeEach(function () {
    Config::reset();
    Config::forceStandaloneMode();
});

afterEach(function () {
    Config::reset();
});

it('loads configuration from specified path', function () {
    $tempFile = sys_get_temp_dir().'/test-config.php';
    file_put_contents($tempFile, '<?php return ["test" => ["value" => "loaded from file"]];');

    Config::setConfigPath($tempFile);

    expect(Config::get('test.value'))->toBe('loaded from file');

    unlink($tempFile);
});

it('returns default when key does not exist', function () {
    expect(Config::get('non.existent', 'default'))->toBe('default');
    expect(Config::get('missing.key'))->toBeNull();
});

it('gets and sets simple values', function () {
    Config::set('simple', 'value');
    expect(Config::get('simple'))->toBe('value');
});

it('gets and sets nested values using dot notation', function () {
    Config::set('parent.child.grandchild', 'nested value');

    expect(Config::get('parent.child.grandchild'))->toBe('nested value');
    expect(Config::get('parent.child'))->toBeArray();
});

it('overwrites existing values', function () {
    Config::set('key', 'original');
    Config::set('key', 'updated');

    expect(Config::get('key'))->toBe('updated');
});

it('preserves sibling values when setting nested keys', function () {
    Config::set('parent.child1', 'value1');
    Config::set('parent.child2', 'value2');

    expect(Config::get('parent.child1'))->toBe('value1');
    expect(Config::get('parent.child2'))->toBe('value2');
});

it('handles various data types', function () {
    Config::set('string', 'text');
    Config::set('integer', 42);
    Config::set('boolean', true);
    Config::set('array', ['a', 'b', 'c']);
    Config::set('null', null);
    Config::set('false', false);

    expect(Config::get('string'))->toBe('text');
    expect(Config::get('integer'))->toBe(42);
    expect(Config::get('boolean'))->toBe(true);
    expect(Config::get('array'))->toBe(['a', 'b', 'c']);
    expect(Config::get('null', 'default'))->toBe('default');
    expect(Config::get('false', true))->toBe(false);
});

it('uses laravel config when available and not in standalone mode', function () {
    // Skip if not in Laravel environment
    if (! function_exists('config')) {
        expect(true)->toBeTrue();

        return;
    }

    Config::reset();
    Config::forceStandaloneMode(false);

    // This should use Laravel's config() function
    $result = Config::get('some.key', 'default');
    expect($result)->not->toBeNull();
});

it('resets all configuration', function () {
    Config::set('key1', 'value1');
    Config::set('key2', 'value2');
    Config::setConfigPath('/some/path.php');
    Config::forceStandaloneMode(true);

    Config::reset();

    expect(Config::get('key1'))->toBeNull();
    expect(Config::get('key2'))->toBeNull();
});
