<?php

namespace christopheraseidl\CircuitBreaker\Support;

/**
 * Simple configuration for framework-agnostic usage
 */
class Config
{
    private static ?array $config = null;

    private static ?string $configPath = null;

    private static bool $forceStandalone = false;

    /**
     * Set the configuration file path
     */
    public static function setConfigPath(string $path): void
    {
        self::$configPath = $path;
        self::$config = null; // Reset config to force reload
    }

    public static function forceStandaloneMode(bool $force = true): void
    {
        self::$forceStandalone = $force;
    }

    /**
     * Get configuration value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Laravel takes precedence
        if (! self::$forceStandalone && function_exists('config')) {
            return config("circuit-breaker.{$key}", $default);
        }

        // Lazy load config
        if (self::$config === null) {
            self::loadConfig();
        }

        // Navigate nested keys
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (! is_array($value) || ! isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set configuration value
     */
    public static function set(string $key, mixed $value): void
    {
        // Lazy load config if needed
        if (self::$config === null) {
            self::loadConfig();
        }

        // Handle nested keys
        $keys = explode('.', $key);
        $lastKey = array_pop($keys);

        // Navigate to the parent array
        $current = &self::$config;
        foreach ($keys as $k) {
            if (! isset($current[$k]) || ! is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        // Set the value
        $current[$lastKey] = $value;
    }

    /**
     * Reset configuration (useful for testing)
     */
    public static function reset(): void
    {
        self::$config = null;
        self::$configPath = null;
        self::$forceStandalone = false;
    }

    /**
     * Load configuration from file
     */
    private static function loadConfig(): void
    {
        if (self::$configPath && file_exists(self::$configPath)) {
            self::$config = require self::$configPath;
        } else {
            // Try common locations
            $possiblePaths = [
                getcwd().'/config/circuit-breaker.php',
                dirname(__DIR__, 4).'/config/circuit-breaker.php', // vendor/../config
                __DIR__.'/../../config/circuit-breaker.php', // package config
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    self::$config = require $path;
                    break;
                }
            }
        }

        // Fallback to empty array
        self::$config ??= [];
    }
}
