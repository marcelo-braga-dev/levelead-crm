<?php

use App\Models\Setting;

it('returns the default when a setting key does not exist', function () {
    expect(Setting::get('does.not.exist', 'fallback'))->toBe('fallback');
});

it('returns the stored value once a setting is set', function () {
    Setting::set('a.test.key', 42);

    expect(Setting::get('a.test.key', 0))->toBe(42);
});

it('updates the stored value and invalidates the cache on a second set', function () {
    Setting::set('a.test.key', 1);
    expect(Setting::get('a.test.key'))->toBe(1);

    Setting::set('a.test.key', 2);

    expect(Setting::get('a.test.key'))->toBe(2);
    expect(Setting::query()->where('key', 'a.test.key')->count())->toBe(1);
});

it('preserves the value type (string, bool, float) through json encoding', function () {
    Setting::set('a.string', 'hello');
    Setting::set('a.bool', true);
    Setting::set('a.float', 1.5);

    expect(Setting::get('a.string'))->toBe('hello');
    expect(Setting::get('a.bool'))->toBe(true);
    expect(Setting::get('a.float'))->toBe(1.5);
});
