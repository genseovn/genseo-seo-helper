<?php
/**
 * Plugin Name: GenSeo SEO Helper
 * Plugin URI: https://genseo.app
 * Description: Tối ưu SEO cho bài viết từ GenSeo Desktop - OpenGraph, Schema markup, RankMath/Yoast sync, MCP Abilities
 * Version: 2.3.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: GenSeo Team
 * Author URI: https://genseo.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: genseo-seo-helper
 * Domain Path: /languages
 * Update URI: https://server.genseo.vn/api/plugin/check-update
 *
 * @package GenSeo_SEO_Helper
 */

// Ngăn truy cập trực tiếp
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================
// PHP VERSION CHECK (trước khi load bất kỳ code nào)
// ============================================================
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>GenSeo SEO Helper:</strong> ';
        echo esc_html(sprintf('Plugin yêu cầu PHP 7.4 trở lên. Phiên bản PHP hiện tại: %s. Vui lòng nâng cấp PHP để sử dụng plugin.', PHP_VERSION));
        echo '</p></div>';
    });
    return;
}

// ============================================================
// WORDPRESS VERSION CHECK (runtime guard — không crash site)
// ============================================================
if (version_compare(get_bloginfo('version'), '6.0', '<')) {
    add_action('admin_notices', function () {
        global $wp_version;
        echo '<div class="notice notice-error"><p><strong>GenSeo SEO Helper:</strong> ';
        echo esc_html(sprintf('Plugin yêu cầu WordPress 6.0 trở lên. Phiên bản hiện tại: %s. Vui lòng nâng cấp WordPress để sử dụng plugin.', $wp_version));
        echo '</p></div>';
    });
    return;
}

// ============================================================
// CONSTANTS
// ============================================================

define('GENSEO_VERSION', '2.3.1');
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
    // Kiểm tra phiên bản trước khi kích hoạt
    global $wp_version;
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            '<h1>GenSeo SEO Helper</h1>' .
            '<p>Plugin yêu cầu <strong>PHP 7.4</strong> trở lên.</p>' .
            '<p>Phiên bản PHP hiện tại: <strong>' . esc_html(PHP_VERSION) . '</strong></p>' .
            '<p>Vui lòng liên hệ nhà cung cấp hosting để nâng cấp PHP.</p>',
            'GenSeo SEO Helper - Yêu cầu hệ thống',
            array('back_link' => true)
        );
    }
    if (version_compare($wp_version, '6.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            '<h1>GenSeo SEO Helper</h1>' .
            '<p>Plugin yêu cầu <strong>WordPress 6.0</strong> trở lên.</p>' .
            '<p>Phiên bản WordPress hiện tại: <strong>' . esc_html($wp_version) . '</strong></p>' .
            '<p><a href="' . esc_url(admin_url('update-core.php')) . '">Cập nhật WordPress ngay</a></p>',
            'GenSeo SEO Helper - Yêu cầu hệ thống',
            array('back_link' => true)
        );
    }

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
        'api_key'              => '',
        'api_key_user_id'      => 0,
    );

    // Chỉ thêm nếu chưa tồn tại
    if (get_option('genseo_settings') === false) {
        add_option('genseo_settings', $default_options);
    }

    // Tạo API Key nếu chưa có
    // Key lưu riêng trong wp_options (genseo_api_key) để bảo tồn qua update/reinstall
    $existing_key = get_option('genseo_api_key', '');
    if (empty($existing_key)) {
        // Migration: lấy key cũ từ genseo_settings nếu có
        $settings = get_option('genseo_settings', array());
        if (!empty($settings['api_key'])) {
            $existing_key = $settings['api_key'];
        } else {
            $existing_key = wp_generate_password(64, false, false);
        }
        update_option('genseo_api_key', $existing_key, false);
    }

    // Lưu user_id riêng nếu chưa có
    $existing_user_id = get_option('genseo_api_key_user_id', 0);
    if (empty($existing_user_id)) {
        $settings = get_option('genseo_settings', array());
        $user_id = !empty($settings['api_key_user_id']) ? $settings['api_key_user_id'] : get_current_user_id();
        update_option('genseo_api_key_user_id', $user_id, false);
    }

    // Đồng bộ vào genseo_settings để tương thích ngược
    $settings = get_option('genseo_settings', array());
    $settings['api_key'] = $existing_key;
    $settings['api_key_user_id'] = get_option('genseo_api_key_user_id', get_current_user_id());
    update_option('genseo_settings', $settings);

    // Flush rewrite rules cho REST API
    flush_rewrite_rules();

    // Tạo bảng audit log
    GenSeo_Audit_Logger::create_table();

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

    // ============================================================
    // LOAD MCP ADAPTER (BUNDLED)
    // ============================================================
    // MCP Adapter được bundle sẵn trong thư mục mcp-adapter/
    // Không cần user cài riêng - tự động load nếu WP >= 6.9
    if (function_exists('wp_register_ability') && !class_exists('WP\\MCP\\Plugin')) {
        $mcp_adapter_file = GENSEO_PLUGIN_DIR . 'mcp-adapter/mcp-adapter.php';
        if (file_exists($mcp_adapter_file)) {
            require_once $mcp_adapter_file;
        }
    }

    // MCP Abilities (chỉ init nếu WP >= 6.9 có Abilities API trong core)
    if (function_exists('wp_register_ability')) {
        GenSeo_MCP_Abilities::init();
    } else {
        // WP < 6.9: hiện admin notice
        add_action('admin_notices', 'genseo_wp_version_notice');
    }

    // WAF Compatibility (Wordfence, Imunify360, ModSecurity) - luon chay
    GenSeo_WAF_Compat::init();

    // API Key Authentication - bypass WAF/Firewall
    GenSeo_API_Key_Auth::init();

    // Rate Limiter — bao ve MCP/REST endpoints khoi lam dung
    GenSeo_Rate_Limiter::init();

    // Audit Logger — ghi log các thao tác MCP write operations
    GenSeo_Audit_Logger::init();

    // Admin only
    if (is_admin()) {
        GenSeo_Admin::init();
        GenSeo_Updater::init();
        GenSeo_Diagnostic::init();
    }

    // Đăng ký admin-ajax fallback cho health check (WAF có thể chặn REST API nhưng không chặn admin-ajax)
    add_action('wp_ajax_genseo_health', 'genseo_ajax_health_check');
    add_action('wp_ajax_nopriv_genseo_health', 'genseo_ajax_health_check');

    // ============================================================
    // MCP FAILSAFE: deep debug (priority 98) + failsafe (priority 99)
    // ============================================================
    // Upstream MCP Adapter dùng nested hook pattern:
    //   rest_api_init:15 → McpAdapter::init() → tạo server → HttpTransport constructor
    //   → add_action('rest_api_init', 'register_routes', 16)
    // Chuỗi init có 10+ điểm thất bại âm thầm. Hook 2 hàm:
    //   - priority 98: deep debug — thu thập 12+ checkpoint vào transient
    //   - priority 99: failsafe — phát hiện + tự sửa server/route nếu upstream fail
    if (function_exists('wp_register_ability')) {
        add_action('rest_api_init', 'genseo_mcp_deep_debug', 98);
        add_action('rest_api_init', 'genseo_mcp_failsafe_route_check', 99);
    }
}
add_action('plugins_loaded', 'genseo_init');

// ============================================================
// MCP DEEP DEBUG — thu thập trạng thái chi tiết tại rest_api_init:98
// ============================================================

/**
 * Đọc private static property từ class bằng Reflection.
 * Trả về giá trị hoặc null nếu không đọc được.
 *
 * @param string $class Tên class đầy đủ namespace
 * @param string $prop  Tên property
 * @return mixed|null
 */
function genseo_reflection_get_static($class, $prop) {
    try {
        $ref = new \ReflectionClass($class);
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        return $p->getValue();
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Ghi private static property vào class bằng Reflection.
 *
 * @param string $class Tên class đầy đủ namespace
 * @param string $prop  Tên property
 * @param mixed  $value Giá trị mới
 * @return bool true nếu set thành công
 */
function genseo_reflection_set_static($class, $prop, $value) {
    try {
        $ref = new \ReflectionClass($class);
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue(null, $value);
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Deep debug: kiểm tra 12+ checkpoint trong chuỗi init MCP Adapter.
 * Chạy tại rest_api_init:98 (sau upstream init:15 nhưng trước failsafe:99).
 * Lưu kết quả vào transient genseo_mcp_debug_info (60s).
 */
function genseo_mcp_deep_debug() {
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $debug = array();

    // 1. Abilities API tồn tại?
    $debug['abilities_api_exists'] = function_exists('wp_register_ability');

    // 2. Plugin class loaded?
    $debug['mcp_plugin_class_exists'] = class_exists('WP\\MCP\\Plugin');

    // 3. McpAdapter class loaded?
    $debug['mcp_adapter_class_exists'] = class_exists('WP\\MCP\\Core\\McpAdapter');

    // 4. McpAdapter::$initialized (private static) — đọc bằng Reflection
    $debug['mcp_adapter_initialized'] = null;
    if ($debug['mcp_adapter_class_exists']) {
        $debug['mcp_adapter_initialized'] = genseo_reflection_get_static(
            'WP\\MCP\\Core\\McpAdapter',
            'initialized'
        );
    }

    // 5. Hook counts
    $debug['mcp_adapter_init_fired'] = did_action('mcp_adapter_init');
    $debug['wp_abilities_api_init_fired'] = did_action('wp_abilities_api_init');
    $debug['rest_api_init_fired'] = did_action('rest_api_init');

    // 6. Filter mcp_adapter_create_default_server
    $debug['create_default_server_filter'] = apply_filters('mcp_adapter_create_default_server', true);

    // 7. Handler classes tồn tại + implement đúng interface?
    $error_handler_class = 'WP\\MCP\\Infrastructure\\ErrorHandling\\ErrorLogMcpErrorHandler';
    $error_handler_iface = 'WP\\MCP\\Infrastructure\\ErrorHandling\\Contracts\\McpErrorHandlerInterface';
    $obs_handler_class = 'WP\\MCP\\Infrastructure\\Observability\\NullMcpObservabilityHandler';
    $obs_handler_iface = 'WP\\MCP\\Infrastructure\\Observability\\Contracts\\McpObservabilityHandlerInterface';

    $debug['error_handler_class_exists'] = class_exists($error_handler_class);
    $debug['error_handler_implements_interface'] = $debug['error_handler_class_exists']
        && in_array($error_handler_iface, class_implements($error_handler_class) ?: array(), true);
    $debug['observability_handler_class_exists'] = class_exists($obs_handler_class);
    $debug['observability_handler_implements_interface'] = $debug['observability_handler_class_exists']
        && in_array($obs_handler_iface, class_implements($obs_handler_class) ?: array(), true);

    // 8. HttpTransport class tồn tại?
    $debug['http_transport_class_exists'] = class_exists('WP\\MCP\\Transport\\HttpTransport');

    // 9. Servers count + default server check
    $debug['servers_count'] = 0;
    $debug['default_server_exists'] = false;
    $debug['server_ids'] = array();
    if ($debug['mcp_adapter_class_exists']) {
        try {
            $adapter = \WP\MCP\Core\McpAdapter::instance();
            $servers = $adapter->get_servers();
            $debug['servers_count'] = count($servers);
            $debug['server_ids'] = array_keys($servers);
            $debug['default_server_exists'] = $adapter->get_server('mcp-adapter-default-server') !== null;
        } catch (\Throwable $e) {
            $debug['adapter_access_error'] = $e->getMessage();
        }
    }

    // 10. Route registered?
    $debug['mcp_route_registered'] = false;
    try {
        $rest_server = rest_get_server();
        $routes = $rest_server->get_routes();
        $debug['mcp_route_registered'] = isset($routes['/mcp/mcp-adapter-default-server']);
    } catch (\Throwable $e) {
        $debug['route_check_error'] = $e->getMessage();
    }

    // 11. DefaultServerFactory class tồn tại?
    $debug['default_server_factory_exists'] = class_exists('WP\\MCP\\Servers\\DefaultServerFactory');

    // 12. Timestamp
    $debug['timestamp'] = gmdate('Y-m-d H:i:s');

    set_transient('genseo_mcp_debug_info', $debug, 60);
}

// ============================================================
// MCP FAILSAFE — phát hiện + tự sửa server/route nếu upstream fail
// ============================================================

/**
 * Failsafe tại rest_api_init:99.
 * Kiểm tra kết quả deep_debug và thử khắc phục nếu:
 * - McpAdapter chưa init → gọi init() trực tiếp
 * - Init rồi nhưng không có server → reset $initialized và retry, hoặc tạo server thủ công
 * - Server OK nhưng route thiếu → đăng ký route thủ công
 */
function genseo_mcp_failsafe_route_check() {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!class_exists('WP\MCP\Core\McpAdapter')) {
        set_transient('genseo_mcp_init_error', 'McpAdapter class không tồn tại. MCP Adapter chưa được load.', 300);
        return;
    }

    $adapter = \WP\MCP\Core\McpAdapter::instance();
    $servers = $adapter->get_servers();

    // ── Kịch bản 1: Không có server nào ──
    if (empty($servers)) {
        $initialized = genseo_reflection_get_static('WP\\MCP\\Core\\McpAdapter', 'initialized');
        $error_parts = array();

        // 1a. init() chưa chạy → gọi trực tiếp
        if ($initialized === false) {
            try {
                $adapter->init();
                $servers = $adapter->get_servers();
                if (!empty($servers)) {
                    $error_parts[] = 'Failsafe: init() chưa chạy, đã gọi trực tiếp thành công.';
                }
            } catch (\Throwable $e) {
                $error_parts[] = 'Failsafe: gọi init() thất bại: ' . $e->getMessage();
            }
        }

        // 1b. init() đã chạy nhưng servers rỗng → reset $initialized, thử lại
        if (empty($adapter->get_servers())) {
            $filter_val = apply_filters('mcp_adapter_create_default_server', true);
            if (!$filter_val) {
                $error_parts[] = 'Filter mcp_adapter_create_default_server bị tắt (return false). Có thể do plugin khác.';
            } else {
                // Reset $initialized để cho phép init() chạy lại
                $reset_ok = genseo_reflection_set_static('WP\\MCP\\Core\\McpAdapter', 'initialized', false);
                if ($reset_ok) {
                    try {
                        $adapter->init();
                        $servers = $adapter->get_servers();
                        if (!empty($servers)) {
                            $error_parts[] = 'Failsafe: reset $initialized và gọi lại init() thành công.';
                        } else {
                            $error_parts[] = 'Failsafe: reset + init() hoàn tất nhưng vẫn không tạo được server.';
                        }
                    } catch (\Throwable $e) {
                        $error_parts[] = 'Failsafe: retry init() thất bại: ' . $e->getMessage();
                    }
                } else {
                    $error_parts[] = 'Failsafe: không thể reset $initialized bằng Reflection.';
                }
            }
        }

        // 1c. Vẫn không có server → thử tạo thủ công bằng DefaultServerFactory
        if (empty($adapter->get_servers())) {
            if (class_exists('WP\\MCP\\Servers\\DefaultServerFactory')) {
                try {
                    // DefaultServerFactory::create() yêu cầu doing_action('mcp_adapter_init')
                    // Dùng do_action wrapper để mô phỏng context đúng
                    $factory_success = false;
                    $factory_error = '';
                    add_action('genseo_mcp_manual_init', function () use (&$factory_success, &$factory_error) {
                        // Tạm hook create vào mcp_adapter_init rồi fire
                        $had_action = did_action('mcp_adapter_init');
                        add_action('mcp_adapter_init', array('WP\\MCP\\Servers\\DefaultServerFactory', 'create'), 10);
                        try {
                            do_action('mcp_adapter_init', \WP\MCP\Core\McpAdapter::instance());
                            $factory_success = true;
                        } catch (\Throwable $e) {
                            $factory_error = $e->getMessage();
                        }
                    });
                    do_action('genseo_mcp_manual_init');

                    $servers = $adapter->get_servers();
                    if (!empty($servers)) {
                        $error_parts[] = 'Failsafe: tạo server thủ công qua DefaultServerFactory thành công.';
                    } else {
                        $msg = 'Failsafe: DefaultServerFactory::create() chạy nhưng không tạo được server.';
                        if ($factory_error) {
                            $msg .= ' Lỗi: ' . $factory_error;
                        }
                        $error_parts[] = $msg;
                    }
                } catch (\Throwable $e) {
                    $error_parts[] = 'Failsafe: tạo server thủ công thất bại: ' . $e->getMessage();
                }
            } else {
                $error_parts[] = 'DefaultServerFactory class không tồn tại — không thể tạo server thủ công.';
            }
        }

        // Lưu kết quả
        if (empty($adapter->get_servers())) {
            $base_msg = 'MCP Adapter không tạo được server nào.';
            if (!empty($error_parts)) {
                $base_msg .= ' Chi tiết: ' . implode(' | ', $error_parts);
            }
            set_transient('genseo_mcp_init_error', $base_msg, 300);
            return;
        } else {
            // Failsafe đã fix thành công
            $fix_msg = 'Failsafe đã khắc phục: ' . implode(' | ', $error_parts);
            set_transient('genseo_mcp_init_error', $fix_msg, 60);
        }
    }

    // ── Kịch bản 2: Có server nhưng thiếu default server ──
    $default_server = $adapter->get_server('mcp-adapter-default-server');
    if (!$default_server) {
        $server_ids = array_keys($adapter->get_servers());
        set_transient('genseo_mcp_init_error',
            'MCP Adapter có ' . count($server_ids) . ' server (' . implode(', ', $server_ids) . ') nhưng thiếu default server (mcp-adapter-default-server).',
            300
        );
        return;
    }

    // ── Kịch bản 3: Server OK nhưng route chưa registered ──
    $rest_server = rest_get_server();
    $routes = $rest_server->get_routes();
    if (isset($routes['/mcp/mcp-adapter-default-server'])) {
        delete_transient('genseo_mcp_init_error');
        return;
    }

    // Thử đăng ký route thủ công
    try {
        $transport_context = $default_server->create_transport_context();
        if (class_exists('WP\MCP\Transport\HttpTransport')) {
            $transport = new \WP\MCP\Transport\HttpTransport($transport_context);
            $transport->register_routes();

            // Xác nhận route đã registered
            $routes_after = $rest_server->get_routes();
            if (isset($routes_after['/mcp/mcp-adapter-default-server'])) {
                delete_transient('genseo_mcp_init_error');
            } else {
                set_transient('genseo_mcp_init_error', 'Failsafe: register_routes() chạy nhưng route vẫn không xuất hiện.', 300);
            }
        } else {
            set_transient('genseo_mcp_init_error', 'HttpTransport class không tồn tại — không thể đăng ký route thủ công.', 300);
        }
    } catch (\Throwable $e) {
        set_transient('genseo_mcp_init_error', 'Failsafe route registration thất bại: ' . $e->getMessage(), 300);
    }
}

// ============================================================
// MCP DIAGNOSTIC HELPER
// ============================================================

/**
 * Trả về mảng diagnostic flags để desktop biết vì sao MCP route không khả dụng.
 * Bao gồm deep debug info từ transient genseo_mcp_debug_info.
 * Dùng chung cho REST health, admin-ajax health, và ajax_mcp_proxy.
 *
 * @return array
 */
function genseo_get_mcp_diagnostic() {
    $mcp_adapter_file = defined('GENSEO_PLUGIN_DIR')
        ? GENSEO_PLUGIN_DIR . 'mcp-adapter/mcp-adapter.php'
        : '';

    $abilities_api_exists    = function_exists('wp_register_ability');
    $mcp_adapter_file_exists = !empty($mcp_adapter_file) && file_exists($mcp_adapter_file);
    $mcp_plugin_class_exists = class_exists('WP\\MCP\\Plugin');
    $mcp_adapter_class_exists = class_exists('WP\\MCP\\Core\\McpAdapter');

    // Intermediate checks
    $mcp_adapter_has_servers   = false;
    $mcp_default_server_exists = false;
    $mcp_servers_count         = 0;
    $mcp_adapter_initialized   = null;
    if ($mcp_adapter_class_exists) {
        try {
            $adapter = \WP\MCP\Core\McpAdapter::instance();
            $servers = $adapter->get_servers();
            $mcp_servers_count = count($servers);
            $mcp_adapter_has_servers = $mcp_servers_count > 0;
            $mcp_default_server_exists = $adapter->get_server('mcp-adapter-default-server') !== null;
            $mcp_adapter_initialized = genseo_reflection_get_static('WP\\MCP\\Core\\McpAdapter', 'initialized');
        } catch (\Throwable $e) {
            // Bỏ qua
        }
    }

    // Route check
    $mcp_route_registered = false;
    if (did_action('rest_api_init') || doing_action('rest_api_init')) {
        $server = rest_get_server();
        $routes = $server->get_routes();
        $mcp_route_registered = isset($routes['/mcp/mcp-adapter-default-server']);
    }

    // Lỗi init và deep debug info từ transient
    $mcp_init_error = get_transient('genseo_mcp_init_error');
    $debug_info = get_transient('genseo_mcp_debug_info');

    // Hook & filter info
    $mcp_adapter_init_fired = did_action('mcp_adapter_init');
    $abilities_api_init_fired = did_action('wp_abilities_api_init');
    $create_default_server_filter = apply_filters('mcp_adapter_create_default_server', true);

    return array(
        // Cơ bản (giữ tương thích desktop cũ)
        'abilities_api_exists'      => $abilities_api_exists,
        'mcp_adapter_file_exists'   => $mcp_adapter_file_exists,
        'mcp_plugin_class_exists'   => $mcp_plugin_class_exists,
        'mcp_adapter_class_exists'  => $mcp_adapter_class_exists,
        'mcp_adapter_has_servers'   => $mcp_adapter_has_servers,
        'mcp_default_server_exists' => $mcp_default_server_exists,
        'mcp_servers_count'         => $mcp_servers_count,
        'mcp_route_registered'      => $mcp_route_registered,
        'mcp_init_error'            => $mcp_init_error ? (string) $mcp_init_error : null,
        // Mở rộng v2.1.2 — deep debug
        'mcp_adapter_initialized'          => $mcp_adapter_initialized,
        'mcp_adapter_init_fired'           => $mcp_adapter_init_fired,
        'abilities_api_init_fired'         => $abilities_api_init_fired,
        'create_default_server_filter'     => (bool) $create_default_server_filter,
        'mcp_debug_info'                   => is_array($debug_info) ? $debug_info : null,
    );
}

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
            'server_limits'         => array(
                'post_max_size'        => ini_get('post_max_size'),
                'upload_max_filesize'  => ini_get('upload_max_filesize'),
                'memory_limit'         => ini_get('memory_limit'),
                'max_execution_time'   => (int) ini_get('max_execution_time'),
                'max_input_vars'       => (int) ini_get('max_input_vars'),
                'wp_max_upload_size'   => wp_max_upload_size(),
            ),
            'settings'              => array(
                'opengraph'      => genseo_get_setting('enable_opengraph', true),
                'twitter_cards'  => genseo_get_setting('enable_twitter_cards', true),
                'schema'         => genseo_get_setting('enable_schema', true),
                'rankmath_sync'  => genseo_get_setting('enable_rankmath_sync', true),
                'yoast_sync'     => genseo_get_setting('enable_yoast_sync', true),
            ),
            'mcp_diagnostic'    => function_exists('genseo_get_mcp_diagnostic')
                ? genseo_get_mcp_diagnostic()
                : null,
        ),
    );

    wp_send_json($response);
}

// ============================================================
// WP VERSION NOTICE — BANNER TOÀN TRANG (< 6.9)
// ============================================================

/**
 * Enqueue CSS cho banner MCP upgrade trên mọi trang admin
 * (CSS chính chỉ load trên settings/post editor, banner cần load khắp nơi)
 */
function genseo_enqueue_banner_css() {
    if (!function_exists('wp_register_ability') && current_user_can('manage_options')) {
        wp_enqueue_style(
            'genseo-admin-banner',
            GENSEO_PLUGIN_URL . 'admin/css/genseo-admin.css',
            array(),
            GENSEO_VERSION
        );
    }
}
add_action('admin_enqueue_scripts', 'genseo_enqueue_banner_css');

/**
 * Hiển thị banner toàn trang nếu WordPress < 6.9 (không có Abilities API)
 * Banner nổi bật cảnh báo cần nâng cấp WP để dùng MCP cho SEO Optimizer
 * SEO features vẫn hoạt động, chỉ MCP bị tắt
 */
function genseo_wp_version_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Kiểm tra user đã dismiss chưa (ẩn 30 ngày)
    $user_id = get_current_user_id();
    $dismissed_until = get_user_meta($user_id, 'genseo_mcp_banner_dismissed', true);
    if (!empty($dismissed_until) && time() < (int) $dismissed_until) {
        return;
    }

    global $wp_version;
    $update_url = admin_url('update-core.php');
    $dismiss_nonce = wp_create_nonce('genseo_dismiss_mcp_banner');
    ?>
    <div class="genseo-mcp-upgrade-banner" id="genseo-mcp-upgrade-banner">
        <div class="genseo-mcp-banner-inner">
            <div class="genseo-mcp-banner-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="genseo-mcp-banner-content">
                <h3 class="genseo-mcp-banner-title">
                    <?php esc_html_e('Nâng cấp WordPress để sử dụng MCP cho SEO Optimizer', 'genseo-seo-helper'); ?>
                </h3>
                <p class="genseo-mcp-banner-desc">
                    <?php
                    printf(
                        esc_html__('MCP (Model Context Protocol) cho phép GenSeo Desktop tương tác trực tiếp với WordPress để tối ưu SEO tự động — bao gồm cập nhật meta, schema, phân tích nội dung và nhiều tính năng mạnh mẽ khác. Tính năng này yêu cầu WordPress %1$s trở lên.', 'genseo-seo-helper'),
                        '<strong>6.9</strong>'
                    );
                    ?>
                </p>
                <div class="genseo-mcp-banner-version">
                    <span class="genseo-mcp-version-current">
                        <?php
                        printf(
                            esc_html__('Phiên bản hiện tại: %s', 'genseo-seo-helper'),
                            '<strong>' . esc_html($wp_version) . '</strong>'
                        );
                        ?>
                    </span>
                    <span class="genseo-mcp-version-arrow">&#8594;</span>
                    <span class="genseo-mcp-version-required">
                        <?php
                        printf(
                            esc_html__('Yêu cầu: %s', 'genseo-seo-helper'),
                            '<strong>6.9+</strong>'
                        );
                        ?>
                    </span>
                </div>
                <p class="genseo-mcp-banner-note">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Các tính năng SEO cơ bản (OpenGraph, Schema, Twitter Cards, REST API, RankMath/Yoast sync) vẫn hoạt động bình thường.', 'genseo-seo-helper'); ?>
                </p>
            </div>
            <div class="genseo-mcp-banner-actions">
                <a href="<?php echo esc_url($update_url); ?>" class="button button-primary genseo-mcp-btn-update">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Cập nhật WordPress', 'genseo-seo-helper'); ?>
                </a>
                <button type="button" class="button genseo-mcp-btn-dismiss" id="genseo-mcp-dismiss-btn">
                    <?php esc_html_e('Nhắc lại sau', 'genseo-seo-helper'); ?>
                </button>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var btn = document.getElementById('genseo-mcp-dismiss-btn');
        if(!btn) return;
        btn.addEventListener('click', function(){
            var banner = document.getElementById('genseo-mcp-upgrade-banner');
            if(banner) banner.style.display = 'none';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('action=genseo_dismiss_mcp_banner&_wpnonce=<?php echo esc_js($dismiss_nonce); ?>');
        });
    })();
    </script>
    <?php
}

/**
 * AJAX handler: dismiss banner MCP upgrade (ẩn 30 ngày)
 */
function genseo_ajax_dismiss_mcp_banner() {
    check_ajax_referer('genseo_dismiss_mcp_banner');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }

    $dismiss_until = time() + (30 * DAY_IN_SECONDS);
    update_user_meta(get_current_user_id(), 'genseo_mcp_banner_dismissed', $dismiss_until);

    wp_send_json_success();
}
add_action('wp_ajax_genseo_dismiss_mcp_banner', 'genseo_ajax_dismiss_mcp_banner');

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
