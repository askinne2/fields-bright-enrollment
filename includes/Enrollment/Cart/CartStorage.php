<?php
/**
 * Cart Storage Handler
 *
 * Handles persistence layer for cart data using cookies and transients.
 * Provides session ID generation and cart data storage/retrieval.
 *
 * @package FieldsBright\Enrollment\Cart
 * @since   1.1.0
 */

namespace FieldsBright\Enrollment\Cart;

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class CartStorage
 *
 * Manages cart data persistence using cookies and WordPress transients.
 *
 * @since 1.1.0
 */
class CartStorage
{
    /**
     * Cookie name for cart session.
     *
     * @var string
     */
    public const COOKIE_NAME = 'fb_cart_session';

    /**
     * Transient prefix for cart data.
     *
     * @var string
     */
    public const TRANSIENT_PREFIX = 'fb_cart_';

    /**
     * Cookie expiry in days.
     *
     * @var int
     */
    public const COOKIE_EXPIRY_DAYS = 30;

    /**
     * Transient expiry in seconds (30 days).
     *
     * @var int
     */
    public const TRANSIENT_EXPIRY = 2592000;

    /**
     * Current session ID.
     *
     * @var string|null
     */
    private ?string $session_id = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->init_session();
    }

    /**
     * Initialize the session.
     *
     * @return void
     */
    private function init_session(): void
    {
        // Try to get existing session from cookie (use wp_unslash for proper sanitization).
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            $this->session_id = sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]));
        }
    }

    /**
     * Get or create session ID.
     *
     * @return string
     */
    public function get_session_id(): string
    {
        if (! $this->session_id) {
            $this->session_id = $this->generate_session_id();
            $this->set_session_cookie();
        }
        return $this->session_id;
    }

    /**
     * Generate a unique session ID.
     *
     * @return string
     */
    private function generate_session_id(): string
    {
        return wp_generate_uuid4();
    }

    /**
     * Set the session cookie.
     *
     * @return void
     */
    private function set_session_cookie(): void
    {
        if (headers_sent()) {
            return;
        }

        $expiry = time() + (self::COOKIE_EXPIRY_DAYS * DAY_IN_SECONDS);
        $secure = is_ssl();
        $path = COOKIEPATH ?: '/';
        $domain = COOKIE_DOMAIN ?: '';

        setcookie(
            self::COOKIE_NAME,
            $this->session_id,
            $expiry,
            $path,
            $domain,
            $secure,
            true // httponly
        );

        // Also set in $_COOKIE for immediate availability.
        $_COOKIE[self::COOKIE_NAME] = $this->session_id;
    }

    /**
     * Get cart data from storage.
     *
     * @return array Cart items.
     */
    public function get_cart_data(): array
    {
        $session_id = $this->get_session_id();
        $data = get_transient(self::TRANSIENT_PREFIX . $session_id);
        
        if (! is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * Save cart data to storage.
     *
     * @param array $data Cart data to save.
     *
     * @return bool Whether the data was saved successfully.
     */
    public function save_cart_data(array $data): bool
    {
        $session_id = $this->get_session_id();
        return set_transient(self::TRANSIENT_PREFIX . $session_id, $data, self::TRANSIENT_EXPIRY);
    }

    /**
     * Clear cart data.
     *
     * @return bool Whether the data was cleared successfully.
     */
    public function clear_cart_data(): bool
    {
        if (! $this->session_id) {
            return true;
        }
        return delete_transient(self::TRANSIENT_PREFIX . $this->session_id);
    }

    /**
     * Check if cart has data.
     *
     * @return bool
     */
    public function has_cart_data(): bool
    {
        $data = $this->get_cart_data();
        return ! empty($data);
    }

    /**
     * Get cart item count.
     *
     * @return int
     */
    public function get_cart_count(): int
    {
        $data = $this->get_cart_data();
        return count($data);
    }

    /**
     * Migrate cart data to a user account.
     *
     * @param int    $user_id    WordPress user ID.
     * @param string $session_id Optional session ID to migrate from.
     *
     * @return bool Whether migration was successful.
     */
    public function migrate_to_user(int $user_id, ?string $session_id = null): bool
    {
        $session_id = $session_id ?: $this->session_id;
        
        if (! $session_id) {
            return false;
        }

        $cart_data = get_transient(self::TRANSIENT_PREFIX . $session_id);
        
        if (! is_array($cart_data) || empty($cart_data)) {
            return false;
        }

        // Store cart in user meta.
        update_user_meta($user_id, '_fb_cart_data', $cart_data);

        // Clear the transient.
        delete_transient(self::TRANSIENT_PREFIX . $session_id);

        return true;
    }

    /**
     * Load cart data from user account.
     *
     * @param int $user_id WordPress user ID.
     *
     * @return array Cart data from user account.
     */
    public function load_from_user(int $user_id): array
    {
        $data = get_user_meta($user_id, '_fb_cart_data', true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save cart data to user account.
     *
     * @param int   $user_id WordPress user ID.
     * @param array $data    Cart data.
     *
     * @return bool
     */
    public function save_to_user(int $user_id, array $data): bool
    {
        return (bool) update_user_meta($user_id, '_fb_cart_data', $data);
    }

    /**
     * Clear cart data from user account.
     *
     * @param int $user_id WordPress user ID.
     *
     * @return bool
     */
    public function clear_user_cart(int $user_id): bool
    {
        return delete_user_meta($user_id, '_fb_cart_data');
    }

    /**
     * Delete the session cookie.
     *
     * @return void
     */
    public function delete_session_cookie(): void
    {
        if (headers_sent()) {
            return;
        }

        $path = COOKIEPATH ?: '/';
        $domain = COOKIE_DOMAIN ?: '';

        setcookie(self::COOKIE_NAME, '', time() - 3600, $path, $domain, is_ssl(), true);
        unset($_COOKIE[self::COOKIE_NAME]);
        $this->session_id = null;
    }
}

