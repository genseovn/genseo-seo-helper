<?php
/**
 * Rate Limiter - Giới hạn tần suất request MCP/REST
 *
 * Bảo vệ server khỏi bị lạm dụng bằng cách giới hạn
 * số requests/phút theo user (authenticated) hoặc IP.
 *
 * @package GenSeo_SEO_Helper
 * @since 2.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_Rate_Limiter
 *
 * Sliding window rate limiting dùng WordPress transients.
 * Giới hạn mặc định: 60 requests/phút cho MCP, 120 cho REST.
 */
class GenSeo_Rate_Limiter {

    /**
     * Transient prefix cho rate limit counters
     */
    const TRANSIENT_PREFIX = 'genseo_rl_';

    /**
     * Giới hạn mặc định cho MCP endpoints (requests/phút)
     */
    const MCP_LIMIT = 60;

    /**
     * Giới hạn mặc định cho REST endpoints (requests/phút)
     */
    const REST_LIMIT = 120;

    /**
     * Thời gian window (giây)
     */
    const WINDOW_SECONDS = 60;

    /**
     * Khởi tạo rate limiter hooks
     */
    public static function init() {
        // Rate limit cho MCP REST endpoint (/wp-json/mcp/*)
        add_filter('rest_pre_dispatch', array(__CLASS__, 'check_mcp_rate_limit'), 5, 3);

        // Rate limit cho GenSeo REST endpoint (/wp-json/genseo/v1/*)
        add_filter('rest_pre_dispatch', array(__CLASS__, 'check_genseo_rate_limit'), 5, 3);

        // Rate limit cho admin-ajax MCP proxy
        add_action('wp_ajax_genseo_mcp_proxy', array(__CLASS__, 'check_ajax_rate_limit'), 1);
        add_action('wp_ajax_nopriv_genseo_mcp_proxy', array(__CLASS__, 'check_ajax_rate_limit'), 1);
    }

    /**
     * Check rate limit cho MCP REST requests
     *
     * @param mixed            $result  Response to replace the requested version with.
     * @param WP_REST_Server   $server  Server instance.
     * @param WP_REST_Request  $request Request used to generate the response.
     * @return mixed|WP_Error
     */
    public static function check_mcp_rate_limit($result, $server, $request) {
        // Chỉ apply cho MCP routes
        $route = $request->get_route();
        if (strpos($route, '/mcp/') !== 0) {
            return $result;
        }

        $limit = self::get_limit('mcp');
        $key   = self::get_client_key('mcp');
        $check = self::consume($key, $limit);

        if (is_wp_error($check)) {
            self::set_rate_limit_headers($limit, 0, $check->get_error_data('retry_after'));
            return $check;
        }

        self::set_rate_limit_headers($limit, $check['remaining'], 0);
        return $result;
    }

    /**
     * Check rate limit cho GenSeo REST requests
     *
     * @param mixed            $result  Response.
     * @param WP_REST_Server   $server  Server instance.
     * @param WP_REST_Request  $request Request.
     * @return mixed|WP_Error
     */
    public static function check_genseo_rate_limit($result, $server, $request) {
        $route = $request->get_route();
        if (strpos($route, '/genseo/v1/') !== 0) {
            return $result;
        }

        $limit = self::get_limit('rest');
        $key   = self::get_client_key('rest');
        $check = self::consume($key, $limit);

        if (is_wp_error($check)) {
            self::set_rate_limit_headers($limit, 0, $check->get_error_data('retry_after'));
            return $check;
        }

        self::set_rate_limit_headers($limit, $check['remaining'], 0);
        return $result;
    }

    /**
     * Check rate limit cho admin-ajax MCP proxy
     * Chạy ở priority 1, trước WAF compat (priority 10).
     */
    public static function check_ajax_rate_limit() {
        $limit = self::get_limit('mcp');
        $key   = self::get_client_key('mcp_ajax');
        $check = self::consume($key, $limit);

        if (is_wp_error($check)) {
            status_header(429);
            header('Retry-After: ' . intval($check->get_error_data('retry_after')));
            wp_send_json_error(
                array('message' => $check->get_error_message()),
                429
            );
        }
    }

    /**
     * Consume 1 request từ quota.
     * Dùng sliding window đơn giản: đếm requests trong WINDOW_SECONDS vừa qua.
     *
     * @param string $key   Transient key (đã prefix).
     * @param int    $limit Tối đa requests trong window.
     * @return array{remaining: int}|WP_Error
     */
    private static function consume($key, $limit) {
        $now       = time();
        $window    = self::WINDOW_SECONDS;
        $data      = get_transient($key);
        $timestamps = is_array($data) ? $data : array();

        // Loại bỏ timestamps ngoài window
        $timestamps = array_values(array_filter($timestamps, function ($ts) use ($now, $window) {
            return ($now - $ts) < $window;
        }));

        if (count($timestamps) >= $limit) {
            // Tính thời gian chờ tới khi slot cũ nhất hết hạn
            $oldest     = min($timestamps);
            $retry_after = $window - ($now - $oldest);
            if ($retry_after < 1) {
                $retry_after = 1;
            }

            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    /* translators: %d: number of seconds */
                    __('Quá nhiều requests. Vui lòng thử lại sau %d giây.', 'genseo-seo-helper'),
                    $retry_after
                ),
                array('retry_after' => $retry_after)
            );
        }

        // Ghi timestamp mới
        $timestamps[] = $now;
        set_transient($key, $timestamps, $window + 10);

        return array('remaining' => $limit - count($timestamps));
    }

    /**
     * Tạo client key duy nhất.
     * Ưu tiên user ID (authenticated), fallback IP.
     *
     * @param string $scope 'mcp'|'rest'|'mcp_ajax'
     * @return string Transient key
     */
    private static function get_client_key($scope) {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $identifier = 'u' . $user_id;
        } else {
            // Sanitize: chỉ lấy phần IP, loại proxy headers
            $ip = self::get_client_ip();
            $identifier = 'ip' . md5($ip);
        }

        // Transient key max 172 chars (WP limit) — prefix + scope + identifier luôn < 50
        return self::TRANSIENT_PREFIX . $scope . '_' . $identifier;
    }

    /**
     * Lấy IP client (hỗ trợ reverse proxy)
     *
     * @return string IP address
     */
    private static function get_client_ip() {
        // Chỉ trust X-Forwarded-For nếu REMOTE_ADDR là trusted proxy
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return '127.0.0.1';
    }

    /**
     * Lấy limit từ settings (cho phép admin tùy chỉnh)
     *
     * @param string $type 'mcp'|'rest'
     * @return int
     */
    private static function get_limit($type) {
        $settings = get_option('genseo_settings', array());

        if ($type === 'mcp') {
            return isset($settings['rate_limit_mcp'])
                ? absint($settings['rate_limit_mcp'])
                : self::MCP_LIMIT;
        }

        return isset($settings['rate_limit_rest'])
            ? absint($settings['rate_limit_rest'])
            : self::REST_LIMIT;
    }

    /**
     * Set standard rate limit response headers
     *
     * @param int $limit      Max requests per window.
     * @param int $remaining  Remaining requests.
     * @param int $retry_after Seconds until reset (0 if not exceeded).
     */
    private static function set_rate_limit_headers($limit, $remaining, $retry_after) {
        header('X-RateLimit-Limit: ' . intval($limit));
        header('X-RateLimit-Remaining: ' . intval($remaining));
        if ($retry_after > 0) {
            header('Retry-After: ' . intval($retry_after));
        }
    }
}
