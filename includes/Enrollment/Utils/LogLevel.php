<?php
/**
 * Log Level Constants
 *
 * Defines log level constants for the enrollment system logger.
 *
 * @package FieldsBright\Enrollment\Utils
 * @since   1.2.0
 */

namespace FieldsBright\Enrollment\Utils;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class LogLevel
 *
 * Provides log level constants for filtering and prioritizing log messages.
 *
 * @since 1.2.0
 */
class LogLevel
{
    /**
     * Debug level - Detailed information for debugging.
     */
    public const DEBUG = 0;

    /**
     * Info level - General informational messages.
     */
    public const INFO = 1;

    /**
     * Warning level - Warning messages for potentially problematic situations.
     */
    public const WARNING = 2;

    /**
     * Error level - Error messages for failures that don't stop execution.
     */
    public const ERROR = 3;

    /**
     * Critical level - Critical errors requiring immediate attention.
     */
    public const CRITICAL = 4;

    /**
     * Get log level name.
     *
     * @param int $level Log level constant.
     *
     * @return string Log level name.
     */
    public static function get_name(int $level): string
    {
        return match ($level) {
            self::DEBUG    => 'DEBUG',
            self::INFO     => 'INFO',
            self::WARNING  => 'WARNING',
            self::ERROR    => 'ERROR',
            self::CRITICAL => 'CRITICAL',
            default        => 'UNKNOWN',
        };
    }

    /**
     * Get all log levels.
     *
     * @return array<int, string> Array of log levels.
     */
    public static function get_all(): array
    {
        return [
            self::DEBUG    => 'DEBUG',
            self::INFO     => 'INFO',
            self::WARNING  => 'WARNING',
            self::ERROR    => 'ERROR',
            self::CRITICAL => 'CRITICAL',
        ];
    }

    /**
     * Check if level is valid.
     *
     * @param int $level Log level to check.
     *
     * @return bool Whether the level is valid.
     */
    public static function is_valid(int $level): bool
    {
        return $level >= self::DEBUG && $level <= self::CRITICAL;
    }
}

