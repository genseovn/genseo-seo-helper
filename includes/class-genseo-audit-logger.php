<?php
/**
 * MCP Audit Logger - Ghi log các thao tác MCP quan trọng
 *
 * Log các MCP ability calls có tác động thay đổi dữ liệu (write operations)
 * vào bảng custom `genseo_audit_log` để phục vụ debug và bảo mật.
 *
 * @package GenSeo_SEO_Helper
 * @since 2.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_Audit_Logger
 *
 * Ghi log thao tác thay đổi dữ liệu qua MCP (update, delete, batch).
 * Tự động xoá log cũ hơn 30 ngày.
 */
class GenSeo_Audit_Logger {

    /**
     * Tên bảng DB (không prefix)
     */
    const TABLE_NAME = 'genseo_audit_log';

    /**
     * Số ngày giữ log
     */
    const RETENTION_DAYS = 30;

    /**
     * Danh sách ability patterns cần log (write operations)
     */
    const LOGGED_PATTERNS = array(
        'update-seo-meta',
        'bulk-update-seo',
        'update-redirect',
        'delete',
        'create',
        'batch',
    );

    /**
     * Khởi tạo audit logger hooks
     */
    public static function init() {
        // Hook vào MCP tool call — ghi log sau khi execute xong
        add_action('genseo_mcp_ability_executed', array(__CLASS__, 'log_ability'), 10, 4);

        // Cleanup cũ — chạy daily
        add_action('genseo_audit_cleanup', array(__CLASS__, 'cleanup_old_logs'));
        if (!wp_next_scheduled('genseo_audit_cleanup')) {
            wp_schedule_event(time(), 'daily', 'genseo_audit_cleanup');
        }

        // Tạo bảng khi activate (hook vào activation)
        add_action('genseo_activate', array(__CLASS__, 'create_table'));
    }

    /**
     * Tạo bảng audit log nếu chưa tồn tại
     */
    public static function create_table() {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_login VARCHAR(60) DEFAULT '',
            ability_name VARCHAR(191) NOT NULL,
            action_type VARCHAR(50) NOT NULL DEFAULT 'execute',
            post_id BIGINT UNSIGNED DEFAULT NULL,
            params_summary TEXT,
            result_status VARCHAR(20) NOT NULL DEFAULT 'success',
            ip_address VARCHAR(45) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_ability_name (ability_name),
            KEY idx_created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Log một MCP ability execution
     *
     * @param string $ability_name  Tên ability (VD: 'genseo/update-seo-meta').
     * @param array  $params        Input params (sẽ được summarize, không log toàn bộ).
     * @param mixed  $result        Kết quả thực thi.
     * @param bool   $success       Thao tác có thành công hay không.
     */
    public static function log_ability($ability_name, $params, $result, $success) {
        // Chỉ log write operations
        if (!self::should_log($ability_name)) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $user    = wp_get_current_user();
        $post_id = self::extract_post_id($params);

        // Sanitize summary — loại bỏ nội dung nhạy cảm, giới hạn 500 chars
        $summary = self::summarize_params($params, $ability_name);

        $wpdb->insert(
            $table,
            array(
                'user_id'        => $user->ID,
                'user_login'     => sanitize_user($user->user_login),
                'ability_name'   => sanitize_text_field($ability_name),
                'action_type'    => self::classify_action($ability_name),
                'post_id'        => $post_id,
                'params_summary' => wp_kses_post(mb_substr($summary, 0, 500)),
                'result_status'  => $success ? 'success' : 'error',
                'ip_address'     => self::get_client_ip(),
                'created_at'     => current_time('mysql', true),
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Kiểm tra ability có cần log không
     *
     * @param string $ability_name Tên ability.
     * @return bool
     */
    private static function should_log($ability_name) {
        foreach (self::LOGGED_PATTERNS as $pattern) {
            if (stripos($ability_name, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Phân loại action type
     *
     * @param string $ability_name Tên ability.
     * @return string 'create'|'update'|'delete'|'batch'|'execute'
     */
    private static function classify_action($ability_name) {
        if (stripos($ability_name, 'delete') !== false) {
            return 'delete';
        }
        if (stripos($ability_name, 'create') !== false) {
            return 'create';
        }
        if (stripos($ability_name, 'batch') !== false || stripos($ability_name, 'bulk') !== false) {
            return 'batch';
        }
        if (stripos($ability_name, 'update') !== false) {
            return 'update';
        }
        return 'execute';
    }

    /**
     * Trích xuất post_id từ params
     *
     * @param array $params Ability parameters.
     * @return int|null
     */
    private static function extract_post_id($params) {
        if (!is_array($params)) {
            return null;
        }

        // Các keys phổ biến chứa post ID
        foreach (array('post_id', 'postId', 'id') as $key) {
            if (isset($params[$key]) && is_numeric($params[$key])) {
                return absint($params[$key]);
            }
        }

        return null;
    }

    /**
     * Tóm tắt params (không log toàn bộ content/password)
     *
     * @param array  $params        Input params.
     * @param string $ability_name  Ability name.
     * @return string JSON summary
     */
    private static function summarize_params($params, $ability_name) {
        if (!is_array($params) || empty($params)) {
            return '{}';
        }

        $safe = array();
        // Danh sách keys an toàn để log
        $safe_keys = array(
            'post_id', 'postId', 'id', 'url', 'slug',
            'seo_title', 'meta_description', 'focus_keyword',
            'canonical_url', 'redirect_from', 'redirect_to',
            'redirect_type', 'status', 'action',
        );

        foreach ($params as $key => $value) {
            if (in_array($key, $safe_keys, true)) {
                if (is_string($value)) {
                    $safe[$key] = mb_substr($value, 0, 100);
                } elseif (is_numeric($value) || is_bool($value)) {
                    $safe[$key] = $value;
                }
            }
        }

        // Cho bulk operations — ghi số lượng items
        if (isset($params['items']) && is_array($params['items'])) {
            $safe['items_count'] = count($params['items']);
        }

        return wp_json_encode($safe, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Lấy IP client
     *
     * @return string
     */
    private static function get_client_ip() {
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return '';
    }

    /**
     * Xoá log cũ hơn RETENTION_DAYS ngày
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        $table    = $wpdb->prefix . self::TABLE_NAME;
        $cutoff   = gmdate('Y-m-d H:i:s', time() - (self::RETENTION_DAYS * DAY_IN_SECONDS));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff
            )
        );
    }
}
