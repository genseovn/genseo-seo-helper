<?php
/**
 * WAF Compatibility - Tương thích Wordfence, Imunify360, ModSecurity
 *
 * Auto-detect WAF, thêm whitelist, CORS cho MCP endpoints,
 * ghi .htaccess rules, admin-ajax MCP proxy fallback.
 *
 * @package GenSeo_SEO_Helper
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_WAF_Compat
 *
 * Xử lý tương thích với Wordfence, Imunify360, ModSecurity.
 * Luôn chạy (cả admin và frontend) vì cần bypass WAF cho REST/MCP requests.
 */
class GenSeo_WAF_Compat {

    /**
     * Tên transient cho WAF status check
     */
    const WAF_STATUS_KEY = 'genseo_waf_status';

    /**
     * Tên option lưu trạng thái .htaccess backup
     */
    const HTACCESS_BACKUP_OPTION = 'genseo_htaccess_backup';

    /**
     * Khởi tạo class
     */
    public static function init() {
        // CORS + bypass cho MCP endpoints (bổ sung endpoint /mcp/ vào compat hiện tại)
        self::setup_cors_bypass();

        // Wordfence bypass mở rộng cho MCP routes
        self::setup_wordfence_bypass();

        // Admin-AJAX MCP proxy fallback (cả logged-in và non-logged-in vì desktop app dùng Basic Auth)
        add_action('wp_ajax_genseo_mcp_proxy', array(__CLASS__, 'ajax_mcp_proxy'));
        add_action('wp_ajax_nopriv_genseo_mcp_proxy', array(__CLASS__, 'ajax_mcp_proxy'));

        // Admin notices khi detect WAF blocking
        if (is_admin()) {
            add_action('admin_notices', array(__CLASS__, 'maybe_show_waf_notice'));

            // AJAX: tự động sửa .htaccess
            add_action('wp_ajax_genseo_fix_htaccess', array(__CLASS__, 'ajax_fix_htaccess'));

            // AJAX: rollback .htaccess
            add_action('wp_ajax_genseo_rollback_htaccess', array(__CLASS__, 'ajax_rollback_htaccess'));

            // AJAX: dismiss WAF notice
            add_action('wp_ajax_genseo_dismiss_waf_notice', array(__CLASS__, 'ajax_dismiss_notice'));
        }
    }

    // ============================================================
    // CORS + BYPASS CHO MCP ENDPOINTS
    // ============================================================

    /**
     * Thêm CORS headers cho cả endpoints GenSeo và MCP
     */
    private static function setup_cors_bypass() {
        add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
            $route = $request->get_route();

            // Whitelist cả genseo/v1 VÀ mcp/ namespace
            $is_genseo = strpos($route, '/genseo/v1/') === 0;
            $is_mcp = strpos($route, '/mcp/') === 0;

            if ($is_genseo || $is_mcp) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, User-Agent, X-GenSeo-Request, X-WP-Nonce');
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

                // Thêm header để WAF nhận biết request hợp lệ
                header('X-GenSeo-Plugin: ' . GENSEO_VERSION);
            }

            return $served;
        }, 10, 4);

        // Handle OPTIONS preflight
        add_action('rest_api_init', function () {
            // WordPress >= 6.5 tự handle OPTIONS nhưng WAF có thể chặn trước
            // Thêm early return cho preflight
            if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
                $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                if (strpos($uri, '/wp-json/genseo/') !== false || strpos($uri, '/wp-json/mcp/') !== false) {
                    header('Access-Control-Allow-Origin: *');
                    header('Access-Control-Allow-Headers: Content-Type, Authorization, User-Agent, X-GenSeo-Request, X-WP-Nonce');
                    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                    header('Access-Control-Max-Age: 86400');
                    header('Content-Length: 0');
                    header('Content-Type: text/plain');
                    status_header(204);
                    exit;
                }
            }
        }, 1);
    }

    /**
     * Mở rộng Wordfence bypass cho MCP endpoints
     */
    private static function setup_wordfence_bypass() {
        if (!class_exists('wfConfig') && !defined('WORDFENCE_VERSION')) {
            return;
        }

        // Bypass rate-limit cho GenSeo requests (nâng cấp: kiểm tra cả header X-GenSeo-Request)
        add_filter('wordfence_allow_ip', function ($allow, $ip) {
            // Kiểm tra header X-GenSeo-Request (khó giả mạo hơn User-Agent)
            if (isset($_SERVER['HTTP_X_GENSEO_REQUEST']) && $_SERVER['HTTP_X_GENSEO_REQUEST'] === '1') {
                return true;
            }
            // Fallback: kiểm tra User-Agent (backward compatible)
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            if (stripos($ua, 'GenSeo') !== false) {
                return true;
            }
            return $allow;
        }, 10, 2);

        // Bypass CAPTCHA cho cả GenSeo VÀ MCP endpoints
        add_filter('wordfence_ls_require_captcha', function ($require) {
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (strpos($uri, '/wp-json/genseo/') !== false
                || strpos($uri, 'rest_route=/genseo/') !== false
                || strpos($uri, '/wp-json/mcp/') !== false
                || strpos($uri, 'rest_route=/mcp/') !== false) {
                return false;
            }
            return $require;
        });

        // Bypass Login Security cho REST API endpoints
        add_filter('wordfence_ls_require_2fa', function ($require) {
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (strpos($uri, '/wp-json/genseo/') !== false
                || strpos($uri, '/wp-json/mcp/') !== false) {
                return false;
            }
            return $require;
        });
    }

    // ============================================================
    // ADMIN-AJAX MCP PROXY (ULTIMATE FALLBACK)
    // ============================================================

    /**
     * Proxy MCP requests qua admin-ajax khi REST API bị WAF chặn
     *
     * Desktop app gọi: POST admin-ajax.php?action=genseo_mcp_proxy
     * Body: JSON-RPC request giống hệt gửi tới MCP endpoint
     *
     * Chỉ hoạt động khi user đã authenticated (wp_ajax_ hook = logged in)
     */
    public static function ajax_mcp_proxy() {
        // Handle OPTIONS preflight
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, User-Agent, X-GenSeo-Request');
            header('Access-Control-Allow-Methods: POST, OPTIONS');
            header('Access-Control-Max-Age: 86400');
            status_header(204);
            exit;
        }

        // CORS headers
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, User-Agent, X-GenSeo-Request');

        // Lấy Authorization header từ client (Desktop app gửi Basic Auth)
        $auth_header = self::get_authorization_header();

        // Đọc body trước để kiểm tra API Key
        $raw_body = file_get_contents('php://input');
        $request_data = $raw_body ? json_decode($raw_body, true) : null;

        // Kiểm tra quyền: 3 cách xác thực
        // 1. Cookie auth (logged-in user)
        // 2. Basic Auth (Application Password)
        // 3. API Key (GenSeo plugin API Key trong JSON body)
        $api_key = isset($request_data['api_key']) ? sanitize_text_field($request_data['api_key']) : '';
        $has_api_key = !empty($api_key) && class_exists('GenSeo_API_Key_Auth') && GenSeo_API_Key_Auth::validate_key($api_key);

        if (!current_user_can('edit_posts') && empty($auth_header) && !$has_api_key) {
            wp_send_json(array(
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => array(
                    'code'    => -32600,
                    'message' => 'Không có quyền truy cập MCP. Cần đăng nhập, Application Password hoặc API Key.',
                ),
            ), 403);
            return;
        }

        // Nếu xác thực bằng API Key, set current user là key owner
        if ($has_api_key && !current_user_can('edit_posts')) {
            $user_id = GenSeo_API_Key_Auth::get_key_owner_user_id();
            if ($user_id) {
                wp_set_current_user($user_id);
            }
        }

        // Body đã đọc ở trên
        if (empty($raw_body)) {
            wp_send_json(array(
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => array(
                    'code'    => -32700,
                    'message' => 'Thiếu JSON body.',
                ),
            ), 400);
            return;
        }

        // $request_data đã decode ở trên, kiểm tra lại
        if (!is_array($request_data) || !isset($request_data['method'])) {
            wp_send_json(array(
                'jsonrpc' => '2.0',
                'id'      => isset($request_data['id']) ? $request_data['id'] : null,
                'error'   => array(
                    'code'    => -32600,
                    'message' => 'JSON-RPC request không hợp lệ.',
                ),
            ), 400);
            return;
        }

        // Forward request tới MCP endpoint internal
        // Loại bỏ api_key khỏi body trước khi forward
        $forward_data = $request_data;
        if (isset($forward_data['api_key'])) {
            unset($forward_data['api_key']);
        }
        $forward_body = wp_json_encode($forward_data);

        // Lấy Mcp-Session-Id từ incoming request (nếu có)
        $mcp_session_id = isset($_SERVER['HTTP_MCP_SESSION_ID']) ? sanitize_text_field($_SERVER['HTTP_MCP_SESSION_ID']) : '';

        // Nếu user đã được authenticate (API Key hoặc logged-in), dùng internal dispatch
        // thay vì HTTP loopback để giữ nguyên current_user context
        if (get_current_user_id() > 0 && empty($auth_header)) {
            // Internal REST dispatch - không cần HTTP request
            $rest_route = '/mcp/mcp-adapter-default-server';
            $internal_request = new \WP_REST_Request('POST', $rest_route);
            $internal_request->set_header('Content-Type', 'application/json');
            if (!empty($mcp_session_id)) {
                $internal_request->set_header('Mcp-Session-Id', $mcp_session_id);
            }
            $internal_request->set_body($forward_body);

            $rest_response = rest_do_request($internal_request);

            // rest_do_request() goi dispatch() nhung KHONG fire filter 'rest_post_dispatch'
            // MCP Adapter dung filter nay de set header Mcp-Session-Id vao response
            // Phai manually apply filter de header duoc set
            $rest_response = apply_filters('rest_post_dispatch', rest_ensure_response($rest_response), rest_get_server(), $internal_request);

            $response_data = rest_get_server()->response_to_data($rest_response, false);

            status_header($rest_response->get_status());
            // Forward response headers (bao gom Mcp-Session-Id)
            foreach ($rest_response->get_headers() as $key => $value) {
                header("{$key}: {$value}");
            }
            header('Content-Type: application/json; charset=utf-8');
            echo wp_json_encode($response_data);
            exit;
        }

        // Fallback: HTTP loopback cho Basic Auth (forward credentials)
        $mcp_url = rest_url('mcp/mcp-adapter-default-server');

        // Build headers cho loopback request
        $loopback_headers = array(
            'Content-Type'     => 'application/json',
            'X-GenSeo-Request' => '1',
        );

        // Forward Mcp-Session-Id nếu có
        if (!empty($mcp_session_id)) {
            $loopback_headers['Mcp-Session-Id'] = $mcp_session_id;
        }

        // Forward auth: client gửi Basic Auth, forward nó sang loopback
        if (!empty($auth_header)) {
            $loopback_headers['Authorization'] = $auth_header;
        } else {
            $loopback_headers['X-WP-Nonce'] = wp_create_nonce('wp_rest');
        }

        // Lấy cookies hiện tại để forward (cho logged-in users)
        $cookies = array();
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'wordpress_') === 0 || strpos($name, 'wp-') === 0) {
                $cookies[] = new WP_Http_Cookie(array(
                    'name'  => $name,
                    'value' => $value,
                ));
            }
        }

        $response = wp_remote_post($mcp_url, array(
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => $loopback_headers,
            'cookies'   => $cookies,
            'body'      => $forward_body,
        ));

        if (is_wp_error($response)) {
            wp_send_json(array(
                'jsonrpc' => '2.0',
                'id'      => isset($request_data['id']) ? $request_data['id'] : null,
                'error'   => array(
                    'code'    => -32603,
                    'message' => 'Không thể kết nối MCP endpoint nội bộ: ' . $response->get_error_message(),
                ),
            ), 502);
            return;
        }

        // Forward response trực tiếp
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        status_header($code);
        // Forward Mcp-Session-Id header nếu có
        $mcp_session_id = wp_remote_retrieve_header($response, 'Mcp-Session-Id');
        if ($mcp_session_id) {
            header('Mcp-Session-Id: ' . $mcp_session_id);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo $body;
        exit;
    }

    /**
     * Lấy Authorization header từ request
     * Hỗ trợ nhiều cách server truyền header
     *
     * @return string Authorization header value hoặc empty string
     */
    private static function get_authorization_header() {
        // Cách 1: HTTP_AUTHORIZATION (phổ biến nhất)
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        // Cách 2: REDIRECT_HTTP_AUTHORIZATION (Apache mod_rewrite)
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        // Cách 3: apache_request_headers() (fallback)
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                return $headers['Authorization'];
            }
            // Case-insensitive check
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    return $value;
                }
            }
        }

        return '';
    }

    // ============================================================
    // .HTACCESS WHITELIST
    // ============================================================

    /**
     * Marker comments cho GenSeo rules trong .htaccess
     */
    const HTACCESS_MARKER_BEGIN = '# BEGIN GenSeo WAF Whitelist';
    const HTACCESS_MARKER_END   = '# END GenSeo WAF Whitelist';

    /**
     * AJAX: Tự động thêm ModSecurity/Imunify360 whitelist rules vào .htaccess
     */
    public static function ajax_fix_htaccess() {
        check_ajax_referer('genseo_fix_htaccess', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền.'));
            return;
        }

        $htaccess_file = ABSPATH . '.htaccess';

        // Kiểm tra file tồn tại và writable
        if (!file_exists($htaccess_file)) {
            wp_send_json_error(array('message' => 'File .htaccess không tồn tại.'));
            return;
        }

        if (!is_writable($htaccess_file)) {
            wp_send_json_error(array('message' => 'File .htaccess không có quyền ghi. Chạy: chmod 644 .htaccess'));
            return;
        }

        // Đọc nội dung hiện tại
        $current_content = file_get_contents($htaccess_file);
        if ($current_content === false) {
            wp_send_json_error(array('message' => 'Không đọc được file .htaccess.'));
            return;
        }

        // Backup trước khi sửa
        update_option(self::HTACCESS_BACKUP_OPTION, $current_content);

        // Xóa rules cũ nếu có
        $current_content = self::remove_genseo_htaccess_rules($current_content);

        // Tạo rules mới
        $rules = self::get_htaccess_rules();

        // Chèn rules VÀO ĐẦU file (trước WordPress rules để có hiệu lực trước WAF)
        $new_content = $rules . "\n\n" . $current_content;

        // Ghi file
        $written = file_put_contents($htaccess_file, $new_content);
        if ($written === false) {
            wp_send_json_error(array('message' => 'Không ghi được file .htaccess.'));
            return;
        }

        wp_send_json_success(array(
            'message' => 'Đã thêm whitelist rules vào .htaccess thành công. Đã backup nội dung cũ.',
        ));
    }

    /**
     * AJAX: Rollback .htaccess từ backup
     */
    public static function ajax_rollback_htaccess() {
        check_ajax_referer('genseo_rollback_htaccess', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền.'));
            return;
        }

        $backup = get_option(self::HTACCESS_BACKUP_OPTION, '');
        if (empty($backup)) {
            wp_send_json_error(array('message' => 'Không tìm thấy bản backup .htaccess.'));
            return;
        }

        $htaccess_file = ABSPATH . '.htaccess';

        if (!is_writable($htaccess_file)) {
            wp_send_json_error(array('message' => 'File .htaccess không có quyền ghi.'));
            return;
        }

        $written = file_put_contents($htaccess_file, $backup);
        if ($written === false) {
            wp_send_json_error(array('message' => 'Không ghi được file .htaccess.'));
            return;
        }

        delete_option(self::HTACCESS_BACKUP_OPTION);

        wp_send_json_success(array(
            'message' => 'Đã khôi phục .htaccess từ bản backup.',
        ));
    }

    /**
     * Tạo nội dung .htaccess whitelist rules
     *
     * @return string
     */
    private static function get_htaccess_rules() {
        $rules = self::HTACCESS_MARKER_BEGIN . "\n";
        $rules .= "# GenSeo SEO Helper v" . GENSEO_VERSION . " - Whitelist MCP & REST API endpoints\n";
        $rules .= "# Thêm tự động bởi plugin. Rollback: Settings > GenSeo Chẩn đoán\n";
        $rules .= "\n";

        // ModSecurity rules
        $rules .= "<IfModule mod_security2.c>\n";
        $rules .= "    # Whitelist GenSeo REST API endpoints\n";
        $rules .= "    SecRule REQUEST_URI \"@beginsWith /wp-json/genseo/\" \"id:9999001,phase:1,pass,nolog,ctl:ruleRemoveTargetById=949110;ARGS,ctl:ruleRemoveTargetById=959100;ARGS\"\n";
        $rules .= "    SecRule REQUEST_URI \"@beginsWith /wp-json/mcp/\" \"id:9999002,phase:1,pass,nolog,ctl:ruleRemoveTargetById=949110;ARGS,ctl:ruleRemoveTargetById=959100;ARGS\"\n";
        $rules .= "\n";
        $rules .= "    # Whitelist admin-ajax.php với GenSeo actions\n";
        $rules .= "    SecRule REQUEST_URI \"@endsWith /admin-ajax.php\" \"id:9999003,phase:1,pass,nolog,chain\"\n";
        $rules .= "        SecRule ARGS:action \"@rx ^genseo_\" \"ctl:ruleRemoveTargetById=949110;ARGS,ctl:ruleRemoveTargetById=959100;ARGS\"\n";
        $rules .= "\n";
        $rules .= "    # Bỏ qua kiểm tra JSON body cho MCP (JSON-RPC format có thể bị nhầm là attack)\n";
        $rules .= "    SecRule REQUEST_URI \"@beginsWith /wp-json/mcp/\" \"id:9999004,phase:2,pass,nolog,ctl:ruleRemoveById=200002;200003\"\n";
        $rules .= "</IfModule>\n";
        $rules .= "\n";

        // LiteSpeed ModSecurity (Imunify360 thường chạy trên LiteSpeed)
        $rules .= "<IfModule LiteSpeed>\n";
        $rules .= "    # Whitelist GenSeo endpoints cho Imunify360/LiteSpeed WAF\n";
        $rules .= "    RewriteEngine On\n";
        $rules .= "    RewriteCond %{REQUEST_URI} ^/wp-json/(genseo|mcp)/ [NC]\n";
        $rules .= "    RewriteRule .* - [E=noabort:1,E=noconntimeout:1]\n";
        $rules .= "</IfModule>\n";

        $rules .= self::HTACCESS_MARKER_END;

        return $rules;
    }

    /**
     * Xóa GenSeo rules cũ khỏi nội dung .htaccess
     *
     * @param string $content Nội dung .htaccess
     * @return string Nội dung đã xóa rules cũ
     */
    private static function remove_genseo_htaccess_rules($content) {
        $pattern = '/' . preg_quote(self::HTACCESS_MARKER_BEGIN, '/')
                 . '.*?'
                 . preg_quote(self::HTACCESS_MARKER_END, '/')
                 . '\s*/s';
        return preg_replace($pattern, '', $content);
    }

    // ============================================================
    // ADMIN NOTICES
    // ============================================================

    /**
     * Hiển thị admin notice khi phát hiện WAF đang block endpoints
     */
    public static function maybe_show_waf_notice() {
        // Chỉ hiện cho admin
        if (!current_user_can('manage_options')) {
            return;
        }

        // Kiểm tra user đã dismiss chưa
        $user_id = get_current_user_id();
        $dismissed = get_user_meta($user_id, 'genseo_waf_notice_dismissed', true);
        if ($dismissed) {
            return;
        }

        // Check status (cache 24 giờ)
        $waf_status = get_transient(self::WAF_STATUS_KEY);

        if ($waf_status === false) {
            // Chưa check hoặc hết hạn - chạy check nhẹ
            $waf_status = self::quick_waf_check();
            set_transient(self::WAF_STATUS_KEY, $waf_status, DAY_IN_SECONDS);
        }

        // Chỉ hiện notice nếu detect blocking
        if (empty($waf_status['blocking'])) {
            return;
        }

        $diag_url = admin_url('options-general.php?page=genseo-settings&tab=diagnostics');
        $dismiss_nonce = wp_create_nonce('genseo_dismiss_waf_notice');
        ?>
        <div class="notice notice-warning is-dismissible" id="genseo-waf-notice">
            <p>
                <strong>GenSeo SEO Helper:</strong>
                <?php echo esc_html($waf_status['message']); ?>
                <a href="<?php echo esc_url($diag_url); ?>">
                    <?php esc_html_e('Mở trang Chẩn đoán', 'genseo-seo-helper'); ?>
                </a>
            </p>
        </div>
        <script>
        (function(){
            var notice = document.getElementById('genseo-waf-notice');
            if(!notice) return;
            var btn = notice.querySelector('.notice-dismiss');
            if(btn){
                btn.addEventListener('click', function(){
                    fetch(ajaxurl+'?action=genseo_dismiss_waf_notice&_wpnonce=<?php echo esc_js($dismiss_nonce); ?>');
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * AJAX: Dismiss WAF notice
     */
    public static function ajax_dismiss_notice() {
        check_ajax_referer('genseo_dismiss_waf_notice', '_wpnonce');
        $user_id = get_current_user_id();
        if ($user_id) {
            update_user_meta($user_id, 'genseo_waf_notice_dismissed', time());
        }
        wp_send_json_success();
    }

    /**
     * Quick check nhanh xem WAF có block không
     * Chỉ dùng cho admin notice, không cần chi tiết như diagnostic
     *
     * @return array
     */
    private static function quick_waf_check() {
        $result = array(
            'blocking' => false,
            'message'  => '',
        );

        // Test health endpoint
        $health_url = rest_url('genseo/v1/health');
        $response = wp_remote_get($health_url, array(
            'timeout'   => 5,
            'sslverify' => false,
            'headers'   => array(
                'User-Agent' => 'GenSeo-WAF-Check/' . GENSEO_VERSION,
            ),
        ));

        if (is_wp_error($response)) {
            $result['blocking'] = true;
            $result['message'] = 'REST API không kết nối được (có thể do firewall chặn). Vui lòng kiểm tra.';
            return $result;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 403) {
            $result['blocking'] = true;
            $result['message'] = 'REST API bị chặn (HTTP 403). Có thể do Wordfence, Imunify360, hoặc ModSecurity.';
            return $result;
        }

        if ($code === 406) {
            $result['blocking'] = true;
            $result['message'] = 'REST API bị chặn (HTTP 406). Có thể do Imunify360 ModSecurity rules.';
            return $result;
        }

        return $result;
    }

    // ============================================================
    // HELPER: KIỂM TRA HTACCESS ĐÃ CÓ RULES CHƯA
    // ============================================================

    /**
     * Kiểm tra .htaccess đã có GenSeo whitelist rules chưa
     *
     * @return bool
     */
    public static function has_htaccess_rules() {
        $htaccess_file = ABSPATH . '.htaccess';
        if (!file_exists($htaccess_file) || !is_readable($htaccess_file)) {
            return false;
        }
        $content = file_get_contents($htaccess_file);
        return $content !== false && strpos($content, self::HTACCESS_MARKER_BEGIN) !== false;
    }

    /**
     * Kiểm tra có backup .htaccess không
     *
     * @return bool
     */
    public static function has_htaccess_backup() {
        return !empty(get_option(self::HTACCESS_BACKUP_OPTION, ''));
    }
}
