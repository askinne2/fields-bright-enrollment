<?php
/**
 * Centralized Logger
 *
 * Provides comprehensive logging functionality for the enrollment system
 * with log levels, context tracking, and process flow logging.
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
 * Class Logger
 *
 * Centralized logging system with verbose output and process tracking.
 *
 * @since 1.2.0
 */
class Logger
{
    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Current log level threshold.
     *
     * @var int
     */
    private int $log_level = LogLevel::INFO;

    /**
     * Active processes being tracked.
     *
     * @var array<string, array>
     */
    private array $active_processes = [];

    /**
     * Log prefix for identifying enrollment system logs.
     *
     * @var string
     */
    private string $log_prefix = '[Fields Bright]';

    /**
     * Maximum number of log entries to keep.
     *
     * @var int
     */
    private int $max_log_entries = 1000;

    /**
     * Option name for storing logs.
     *
     * @var string
     */
    private string $log_option = 'fields_bright_logs';

    /**
     * Maximum log file size in bytes (default: 10MB).
     *
     * @var int
     */
    private int $max_log_file_size = 10485760; // 10MB

    /**
     * Path to debug.log file.
     *
     * @var string
     */
    private string $debug_log_path;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        // Set log level based on WP_DEBUG.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log_level = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? LogLevel::DEBUG : LogLevel::INFO;
        }

        // Allow log level to be filtered.
        $this->log_level = apply_filters('fields_bright_log_level', $this->log_level);

        // Set debug.log path.
        $this->debug_log_path = WP_CONTENT_DIR . '/debug.log';

        // Allow max file size to be filtered (in bytes).
        $this->max_log_file_size = apply_filters('fields_bright_max_log_file_size', $this->max_log_file_size);
    }

    /**
     * Log a message.
     *
     * @param int    $level   Log level constant.
     * @param string $message Log message.
     * @param array  $context Additional context data.
     *
     * @return void
     */
    public function log(int $level, string $message, array $context = []): void
    {
        // Check if this level should be logged.
        if ($level < $this->log_level) {
            return;
        }

        // Enrich context with automatic data.
        $context = $this->enrich_context($context);

        // Format the log entry.
        $log_entry = $this->format_log_entry($level, $message, $context);

        // Write to error_log for real-time debugging.
        $this->write_to_error_log($log_entry);

        // Store in database for admin UI.
        $this->store_log_entry($level, $message, $context);

        // Fire action for custom logging handlers.
        do_action('fields_bright_log', $level, $message, $context);
    }

    /**
     * Log debug message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     *
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log info message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     *
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Log warning message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     *
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log error message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     *
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Log critical message.
     *
     * @param string $message Log message.
     * @param array  $context Additional context.
     *
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Start tracking a process.
     *
     * @param string $process_name Process identifier.
     * @param array  $context      Initial context.
     *
     * @return void
     */
    public function start_process(string $process_name, array $context = []): void
    {
        $this->active_processes[$process_name] = [
            'start_time' => microtime(true),
            'steps'      => [],
            'context'    => $context,
        ];

        $this->info("Process started: {$process_name}", array_merge($context, [
            'process' => $process_name,
            'action'  => 'start',
        ]));
    }

    /**
     * Log a step in a process.
     *
     * @param string $process_name Process identifier.
     * @param string $step         Step description.
     * @param array  $context      Step context.
     *
     * @return void
     */
    public function log_step(string $process_name, string $step, array $context = []): void
    {
        if (isset($this->active_processes[$process_name])) {
            $this->active_processes[$process_name]['steps'][] = [
                'step'      => $step,
                'timestamp' => microtime(true),
                'context'   => $context,
            ];
        }

        $this->debug("{$process_name}: {$step}", array_merge($context, [
            'process' => $process_name,
            'step'    => $step,
        ]));
    }

    /**
     * End a process and log completion.
     *
     * @param string $process_name Process identifier.
     * @param array  $context      Final context.
     *
     * @return void
     */
    public function end_process(string $process_name, array $context = []): void
    {
        if (! isset($this->active_processes[$process_name])) {
            $this->warning("Attempted to end non-existent process: {$process_name}");
            return;
        }

        $process_data = $this->active_processes[$process_name];
        $duration = microtime(true) - $process_data['start_time'];
        $step_count = count($process_data['steps']);

        $this->info("Process completed: {$process_name}", array_merge($context, [
            'process'    => $process_name,
            'action'     => 'complete',
            'duration'   => round($duration * 1000, 2) . 'ms',
            'step_count' => $step_count,
        ]));

        unset($this->active_processes[$process_name]);
    }

    /**
     * Enrich context with automatic data.
     *
     * @param array $context Existing context.
     *
     * @return array Enriched context.
     */
    private function enrich_context(array $context): array
    {
        // Add timestamp if not present.
        if (! isset($context['timestamp'])) {
            $context['timestamp'] = current_time('mysql');
        }

        // Add user ID if logged in.
        if (! isset($context['user_id']) && is_user_logged_in()) {
            $context['user_id'] = get_current_user_id();
        }

        // Add request URI for context.
        if (! isset($context['request_uri']) && isset($_SERVER['REQUEST_URI'])) {
            $context['request_uri'] = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
        }

        // Add IP address (anonymized for privacy).
        if (! isset($context['ip']) && isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
            $context['ip'] = wp_privacy_anonymize_ip($ip);
        }

        return $context;
    }

    /**
     * Format log entry for output.
     *
     * @param int    $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     *
     * @return string Formatted log entry.
     */
    private function format_log_entry(int $level, string $message, array $context): string
    {
        $level_name = LogLevel::get_name($level);
        $context_json = ! empty($context) ? wp_json_encode($context) : '';

        return sprintf(
            '%s [%s] %s %s',
            $this->log_prefix,
            $level_name,
            $message,
            $context_json
        );
    }

    /**
     * Write to WordPress error_log with rotation.
     *
     * @param string $log_entry Formatted log entry.
     *
     * @return void
     */
    private function write_to_error_log(string $log_entry): void
    {
        if (! defined('WP_DEBUG_LOG') || ! WP_DEBUG_LOG) {
            return;
        }

        // Check if we need to rotate the log file.
        $this->maybe_rotate_log_file();

        // Write to error_log.
            error_log($log_entry);
        }

    /**
     * Rotate log file if it exceeds maximum size.
     *
     * @return void
     */
    private function maybe_rotate_log_file(): void
    {
        // Check if file exists and exceeds max size.
        if (! file_exists($this->debug_log_path)) {
            return;
        }

        $file_size = filesize($this->debug_log_path);
        
        if ($file_size < $this->max_log_file_size) {
            return;
        }

        // Rotate: move current log to .old and create new one.
        $old_log_path = $this->debug_log_path . '.old';
        
        // Remove old backup if it exists.
        if (file_exists($old_log_path)) {
            @unlink($old_log_path);
        }

        // Move current log to backup.
        @rename($this->debug_log_path, $old_log_path);

        // Log the rotation event (use error_log directly to avoid recursion).
        error_log(sprintf(
            '%s [INFO] Log file rotated: %s -> %s (size: %s)',
            $this->log_prefix,
            basename($this->debug_log_path),
            basename($old_log_path),
            size_format($file_size)
        ));
    }

    /**
     * Store log entry in database.
     *
     * @param int    $level   Log level.
     * @param string $message Message.
     * @param array  $context Context data.
     *
     * @return void
     */
    private function store_log_entry(int $level, string $message, array $context): void
    {
        // Only store INFO and above in database to prevent bloat.
        if ($level < LogLevel::INFO) {
            return;
        }

        $logs = get_option($this->log_option, []);

        // Add new entry.
        $logs[] = [
            'level'     => $level,
            'level_name' => LogLevel::get_name($level),
            'message'   => $message,
            'context'   => $context,
            'timestamp' => current_time('mysql'),
        ];

        // Keep only recent entries.
        if (count($logs) > $this->max_log_entries) {
            $logs = array_slice($logs, -$this->max_log_entries);
        }

        update_option($this->log_option, $logs);
    }

    /**
     * Get logs with optional filtering.
     *
     * @param array $filters Optional filters (level, search, date_from, date_to, limit).
     *
     * @return array Filtered logs.
     */
    public function get_logs(array $filters = []): array
    {
        $logs = get_option($this->log_option, []);

        // Reverse to show newest first.
        $logs = array_reverse($logs);

        // Apply filters.
        if (! empty($filters['level'])) {
            $logs = array_filter($logs, function ($log) use ($filters) {
                return $log['level'] >= $filters['level'];
            });
        }

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $logs = array_filter($logs, function ($log) use ($search) {
                return stripos($log['message'], $search) !== false ||
                       stripos(wp_json_encode($log['context']), $search) !== false;
            });
        }

        if (! empty($filters['date_from'])) {
            $logs = array_filter($logs, function ($log) use ($filters) {
                return strtotime($log['timestamp']) >= strtotime($filters['date_from']);
            });
        }

        if (! empty($filters['date_to'])) {
            $logs = array_filter($logs, function ($log) use ($filters) {
                return strtotime($log['timestamp']) <= strtotime($filters['date_to']);
            });
        }

        // Apply limit.
        if (! empty($filters['limit'])) {
            $logs = array_slice($logs, 0, (int) $filters['limit']);
        }

        return array_values($logs);
    }

    /**
     * Clear all logs.
     *
     * @return bool Whether logs were cleared successfully.
     */
    public function clear_logs(): bool
    {
        return delete_option($this->log_option);
    }

    /**
     * Clear logs older than specified days.
     *
     * @param int $days Number of days.
     *
     * @return int Number of logs removed.
     */
    public function clear_old_logs(int $days = 30): int
    {
        $logs = get_option($this->log_option, []);
        $cutoff_date = strtotime("-{$days} days");
        $initial_count = count($logs);

        $logs = array_filter($logs, function ($log) use ($cutoff_date) {
            return strtotime($log['timestamp']) >= $cutoff_date;
        });

        update_option($this->log_option, array_values($logs));

        return $initial_count - count($logs);
    }

    /**
     * Export logs as JSON.
     *
     * @param array $filters Optional filters.
     *
     * @return string JSON string of logs.
     */
    public function export_logs(array $filters = []): string
    {
        $logs = $this->get_logs($filters);
        return wp_json_encode($logs, JSON_PRETTY_PRINT);
    }
}

