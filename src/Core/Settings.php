<?php

/**
 * Central settings service.
 *
 * @package SmoothRestaurant
 */

declare(strict_types=1);

namespace SmoothRestaurant\Core;

/**
 * Class Settings
 *
 * Owns the `smooth_settings` option: reads merge the stored array over
 * DEFAULTS, writes persist the full array with autoload disabled. All
 * WordPress API usage is `function_exists`-guarded so unit tests without
 * WordPress run against an in-memory fallback.
 *
 * Extensible by subclasses: DEFAULTS and OPTION resolve through `static::`,
 * so a feature surface can narrow the storage option it owns without
 * redefining the shared behaviour.
 */
class Settings
{
    /**
     * Option key holding all Smooth settings.
     */
    public const OPTION = 'smooth_settings';

    /**
     * Default settings, keyed by `smooth_`-prefixed keys.
     *
     * @var array{smooth_currency: string, smooth_timezone_mode: string, smooth_guest_checkout: bool}
     */
    public const DEFAULTS = array(
        'smooth_currency'       => 'USD',
        'smooth_timezone_mode'  => 'site',
        'smooth_guest_checkout' => true,
    );

    /**
     * In-memory fallback storage for unit tests without WordPress.
     *
     * @var array<string, mixed>
     */
    private array $fallback = array();

    /**
     * All settings, stored values merged over defaults.
     *
     * Unknown stored keys are ignored so forward-compatible writes never
     * leak into the typed surface.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge(static::DEFAULTS, array_intersect_key($this->readStored(), static::DEFAULTS));
    }

    /**
     * Read one setting.
     *
     * @param string $key Setting key.
     * @return mixed Setting value.
     * @throws \InvalidArgumentException When the key is unknown.
     */
    public function get(string $key): mixed
    {
        if (! array_key_exists($key, static::DEFAULTS)) {
            throw new \InvalidArgumentException(sprintf('Unknown Smooth setting: %s.', $key));
        }

        $all = $this->all();

        return $all[ $key ];
    }

    /**
     * Write one setting.
     *
     * Validates the value type against the default's type, then persists
     * the full array via `update_option( ..., false )` (autoload disabled)
     * when WordPress is available, else stores in-memory for unit tests.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return void
     * @throws \InvalidArgumentException When the key is unknown or the type mismatches.
     */
    public function set(string $key, mixed $value): void
    {
        if (! array_key_exists($key, static::DEFAULTS)) {
            throw new \InvalidArgumentException(sprintf('Unknown Smooth setting: %s.', $key));
        }

        $expected = gettype(static::DEFAULTS[ $key ]);
        $actual   = gettype($value);
        if ($expected !== $actual) {
            throw new \InvalidArgumentException(
                sprintf('Invalid type for Smooth setting %s: expected %s, got %s.', $key, $expected, $actual)
            );
        }

        $stored         = $this->readStored();
        $stored[ $key ] = $value;
        $this->writeStored($stored);
    }

    /**
     * Stored values, without defaults.
     *
     * @return array<string, mixed>
     */
    private function readStored(): array
    {
        if (! function_exists('get_option')) {
            return $this->fallback;
        }

        $option = get_option(static::OPTION, array());

        return is_array($option) ? $option : array();
    }

    /**
     * Persist stored values.
     *
     * @param array<string, mixed> $stored Stored values.
     * @return void
     */
    private function writeStored(array $stored): void
    {
        if (function_exists('update_option') && function_exists('get_option')) {
            update_option(static::OPTION, $stored, false);

            return;
        }

        $this->fallback = $stored;
    }
}
