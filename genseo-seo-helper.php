<?php
/**
 * Plugin Name: GenSeo SEO Helper
 * Plugin URI: https://genseo.app
 * Description: Tối ưu SEO cho bài viết từ GenSeo Desktop - OpenGraph, Schema markup, RankMath/Yoast sync
 * Version: 1.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: GenSeo Team
 * Author URI: https://genseo.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: genseo-seo-helper
 * Domain Path: /languages
 *
 * @package GenSeo_SEO_Helper
 */

// Ngăn truy cập trực tiếp
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================
// CONSTANTS
// ============================================================

define('GENSEO_VERSION', '1.1.0');
define('GENSEO_PLUGIN_FILE', __FILE__);
define('GENSEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GENSEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GENSEO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// ============================================================
// AUTOLOADER
// ============================================================

/**
 * Autoload các class của plugin
 */
spl_autoload_register(function ($class) {
    // Chỉ autoload class của plugin
    $prefix = 'GenSeo_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    // Chuyển class name thành file name
    // GenSeo_Meta_Fields -> class-genseo-meta-fields.php
    $class_name = str_replace($prefix, '', $class);
    $class_name = strtolower(str_replace('_', '-', $class_name));
    $file = GENSEO_PLUGIN_DIR . 'includes/class-genseo-' . $class_name . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// ============================================================
// ACTIVATION / DEACTIVATION HOOKS
// ============================================================

/**
 * Kích hoạt plugin
 */
function genseo_activate() {
    // Tạo options mặc định
    $default_options = array(
        'enable_opengraph'     => true,
        'enable_twitter_cards' => true,
        'enable_schema'        => true,
        'enable_rankmath_sync' => true,
        'enable_yoast_sync'    => true,
        'default_og_image'     => 0,
        'publisher_logo'       => 0,
        'twitter_username'     => '',
        'schema_type_default'  => 'Article',
    );

    // Chỉ thêm nếu chưa tồn tại
    if (get_option('genseo_settings') === false) {
        add_option('genseo_settings', $default_options);
    }

    // Flush rewrite rules cho REST API
    flush_rewrite_rules();

    // Log activation
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('GenSeo SEO Helper activated - Version ' . GENSEO_VERSION);
    }
}
register_activation_hook(__FILE__, 'genseo_activate');

/**
 * Vô hiệu hóa plugin
 */
function genseo_deactivate() {
    // Không xóa options khi deactivate (chỉ xóa khi uninstall)
    flush_rewrite_rules();

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('GenSeo SEO Helper deactivated');
    }
}
register_deactivation_hook(__FILE__, 'genseo_deactivate');

// ============================================================
// KHỞI TẠO PLUGIN
// ============================================================

/**
 * Khởi tạo plugin sau khi WordPress load xong
 */
function genseo_init() {
    // Load text domain cho i18n
    load_plugin_textdomain(
        'genseo-seo-helper',
        false,
        dirname(GENSEO_PLUGIN_BASENAME) . '/languages'
    );

    // Khởi tạo các class chính
    GenSeo_Meta_Fields::init();
    GenSeo_REST_API::init();
    GenSeo_OpenGraph::init();
    GenSeo_Twitter::init();
    GenSeo_Schema::init();

    // Khởi tạo integrations dựa trên plugins đang active
    if (genseo_is_rankmath_active()) {
        GenSeo_RankMath::init();
    }

    if (genseo_is_yoast_active()) {
        GenSeo_Yoast::init();
    }

    // Admin only
    if (is_admin()) {
        GenSeo_Admin::init();
    }

    // Đăng ký admin-ajax fallback cho health check (Wordfence có thể chặn REST API nhưng không chặn admin-ajax)
    add_action('wp_ajax_genseo_health', 'genseo_ajax_health_check');
    add_action('wp_ajax_nopriv_genseo_health', 'genseo_ajax_health_check');

    // Wordfence compatibility: whitelist GenSeo REST endpoints
    genseo_wordfence_compat();
}
add_action('plugins_loaded', 'genseo_init');

// ============================================================
// ADMIN-AJAX FALLBACK (bypass Wordfence REST API blocking)
// ============================================================

/**
 * Health check qua admin-ajax.php
 * Dùng khi Wordfence/Sucuri chặn /wp-json/genseo/v1/health
 * Desktop app sẽ thử REST API trước, nếu thất bại sẽ fallback về đây
 */
function genseo_ajax_health_check() {
    // CORS headers cho Tauri app
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, User-Agent');

    global $wp_version;

    $registered_meta = get_registered_meta_keys('post', 'post');
    $meta_registered = isset($registered_meta['_genseo_source']);

    $seo_sync_enabled = genseo_get_setting('enable_rankmath_sync', true)
                     || genseo_get_setting('enable_yoast_sync', true);

    $response = array(
        'success' => true,
        'data'    => array(
            'status'                => 'healthy',
            'plugin_version'        => GENSEO_VERSION,
            'php_version'           => PHP_VERSION,
            'wp_version'            => $wp_version,
            'rest_api'              => true,
            'meta_fields_registered' => $meta_registered,
            'seo_sync_enabled'      => $seo_sync_enabled,
            'seo_plugin_detected'   => genseo_detect_seo_plugin(),
            'settings'              => array(
                'opengraph'      => genseo_get_setting('enable_opengraph', true),
                'twitter_cards'  => genseo_get_setting('enable_twitter_cards', true),
                'schema'         => genseo_get_setting('enable_schema', true),
                'rankmath_sync'  => genseo_get_setting('enable_rankmath_sync', true),
                'yoast_sync'     => genseo_get_setting('enable_yoast_sync', true),
            ),
        ),
    );

    wp_send_json($response);
}

// ============================================================
// WORDFENCE COMPATIBILITY
// ============================================================

/**
 * Tự động whitelist GenSeo REST endpoints trong Wordfence
 * - Thêm CORS headers cho namespace genseo/v1
 * - Cho phép request có User-Agent chứa "GenSeo" qua firewall
 */
function genseo_wordfence_compat() {
    // Thêm CORS + cache headers cho GenSeo REST endpoints
    add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
        $route = $request->get_route();
        if (strpos($route, '/genseo/v1/') === 0) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, User-Agent');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        }
        return $served;
    }, 10, 4);

    // Nếu Wordfence active, thử whitelist URL pattern
    if (class_exists('wfConfig') || defined('WORDFENCE_VERSION')) {
        // Cho phép GenSeo REST requests không bị rate-limited
        add_filter('wordfence_allow_ip', function($allow, $ip) {
            // Kiểm tra User-Agent chứa "GenSeo"
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            if (stripos($ua, 'GenSeo') !== false) {
                return true;
            }
            return $allow;
        }, 10, 2);

        // Whitelist REST endpoint URL pattern
        add_filter('wordfence_ls_require_captcha', function($require) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (strpos($request_uri, '/wp-json/genseo/') !== false) {
                return false;
            }
            if (strpos($request_uri, 'rest_route=/genseo/') !== false) {
                return false;
            }
            return $require;
        });
    }
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Lấy settings của plugin
 *
 * @param string $key     Tên setting
 * @param mixed  $default Giá trị mặc định
 * @return mixed
 */
function genseo_get_setting($key, $default = null) {
    $settings = get_option('genseo_settings', array());
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Cập nhật setting của plugin
 *
 * @param string $key   Tên setting
 * @param mixed  $value Giá trị mới
 * @return bool
 */
function genseo_update_setting($key, $value) {
    $settings = get_option('genseo_settings', array());
    $settings[$key] = $value;
    return update_option('genseo_settings', $settings);
}

/**
 * Kiểm tra post có từ GenSeo không
 *
 * @param int $post_id Post ID
 * @return bool
 */
function genseo_is_genseo_post($post_id) {
    $source = get_post_meta($post_id, '_genseo_source', true);
    return $source === 'genseo-desktop';
}

/**
 * Kiểm tra RankMath có active không
 *
 * @return bool
 */
function genseo_is_rankmath_active() {
    return class_exists('RankMath') || defined('RANK_MATH_VERSION');
}

/**
 * Kiểm tra Yoast SEO có active không
 *
 * @return bool
 */
function genseo_is_yoast_active() {
    return defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
}

/**
 * Lấy thông tin SEO plugin đang active
 *
 * @return array
 */
function genseo_detect_seo_plugin() {
    if (genseo_is_rankmath_active()) {
        return array(
            'detected' => true,
            'type'     => 'rankmath',
            'name'     => 'Rank Math SEO',
            'version'  => defined('RANK_MATH_VERSION') ? RANK_MATH_VERSION : 'unknown',
        );
    }

    if (genseo_is_yoast_active()) {
        return array(
            'detected' => true,
            'type'     => 'yoast',
            'name'     => 'Yoast SEO',
            'version'  => defined('WPSEO_VERSION') ? WPSEO_VERSION : 'unknown',
        );
    }

    return array(
        'detected' => false,
        'type'     => null,
        'name'     => null,
        'version'  => null,
    );
}

/**
 * Lấy meta GenSeo của post
 *
 * @param int    $post_id Post ID
 * @param string $key     Meta key (không có prefix _genseo_)
 * @param mixed  $default Giá trị mặc định
 * @return mixed
 */
function genseo_get_post_meta($post_id, $key, $default = '') {
    $value = get_post_meta($post_id, '_genseo_' . $key, true);
    return !empty($value) ? $value : $default;
}

/**
 * Escape và sanitize output cho HTML
 *
 * @param string $text Text cần escape
 * @return string
 */
function genseo_esc_meta($text) {
    return esc_attr(wp_strip_all_tags($text));
}
