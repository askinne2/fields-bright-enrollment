<?php
/**
 * PSR-4 Autoloader for Fields Bright Enrollment System
 *
 * This autoloader follows PSR-4 standards and automatically loads classes
 * from the FieldsBright namespace.
 *
 * @package FieldsBright\Enrollment
 * @since   1.0.0
 */

namespace FieldsBright;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Autoloader
 *
 * Handles automatic loading of classes following PSR-4 conventions.
 *
 * @since 1.0.0
 */
class Autoloader
{
    /**
     * Namespace prefix for this autoloader.
     *
     * @var string
     */
    private const NAMESPACE_PREFIX = 'FieldsBright\\';

    /**
     * Base directory for the namespace prefix.
     *
     * @var string
     */
    private string $base_dir;

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

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
     *
     * Sets up the base directory for autoloading.
     */
    private function __construct()
    {
        $this->base_dir = dirname(__FILE__) . '/';
    }

    /**
     * Register the autoloader with SPL autoload stack.
     *
     * @return void
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Unregister the autoloader from SPL autoload stack.
     *
     * @return void
     */
    public function unregister(): void
    {
        spl_autoload_unregister([$this, 'loadClass']);
    }

    /**
     * Load a class file based on its fully-qualified name.
     *
     * @param string $class The fully-qualified class name.
     *
     * @return void
     */
    public function loadClass(string $class): void
    {
        // Check if the class uses our namespace prefix.
        $prefix_length = strlen(self::NAMESPACE_PREFIX);
        
        if (strncmp(self::NAMESPACE_PREFIX, $class, $prefix_length) !== 0) {
            // Not our namespace, let another autoloader handle it.
            return;
        }

        // Get the relative class name.
        $relative_class = substr($class, $prefix_length);

        // Replace namespace separators with directory separators.
        // and append .php extension.
        $file = $this->base_dir . str_replace('\\', '/', $relative_class) . '.php';

        // If the file exists, require it.
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

