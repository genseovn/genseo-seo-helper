<?php
/**
 * API Key Authentication - Bypass WAF/Firewall
 *
 * Cho phép GenSeo Desktop kết nối qua admin-ajax.php bằng API Key
 * thay vì Authorization header (bị Imunify360/ModSecurity chặn).
 *
 * API Key được lưu trong wp_options (genseo_settings['api_key']).
 * Desktop gửi key trong POST body → không cần header → bypass WAF.
 *
 * @package GenSeo_SEO_Helper
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GenSeo_API_Key_Auth {

    /**
     * Khởi tạo class - đăng ký AJAX handlers
     */
    public static function init() {
        // Main proxy endpoint - xu ly tat ca operations
        add_action('wp_ajax_genseo_api_proxy', array(__CLASS__, 'ajax_api_proxy'));
        add_action('wp_ajax_nopriv_genseo_api_proxy', array(__CLASS__, 'ajax_api_proxy'));

        // Test connection endpoint
        add_action('wp_ajax_genseo_api_test', array(__CLASS__, 'ajax_api_test'));
        add_action('wp_ajax_nopriv_genseo_api_test', array(__CLASS__, 'ajax_api_test'));

        // Regenerate key endpoint (admin only)
        add_action('wp_ajax_genseo_regenerate_api_key', array(__CLASS__, 'ajax_regenerate_key'));
    }

    // ============================================================
    // API KEY MANAGEMENT
    // ============================================================

    /**
     * Tạo API Key mới (64 ký tự random)
     */
    public static function generate_key() {
        return wp_generate_password(64, false, false);
    }

    /**
     * Lấy API Key hiện tại
     * Ưu tiên option riêng (bảo tồn qua reinstall), fallback về genseo_settings
     */
    public static function get_key() {
        $key = get_option('genseo_api_key', '');
        if (!empty($key)) {
            return $key;
        }
        return genseo_get_setting('api_key', '');
    }

    /**
     * Validate API Key (timing-safe comparison)
     *
     * @param string $provided_key Key tu request
     * @return bool
     */
    public static function validate_key($provided_key) {
        if (empty($provided_key) || !is_string($provided_key)) {
            return false;
        }

        $stored_key = self::get_key();
        if (empty($stored_key)) {
            return false;
        }

        return hash_equals($stored_key, $provided_key);
    }

    /**
     * Tạo key mới và lưu vào settings
     *
     * @return string Key mới
     */
    public static function regenerate_key() {
        $new_key = self::generate_key();

        // Lưu vào option riêng (bảo tồn qua reinstall)
        update_option('genseo_api_key', $new_key, false);
        update_option('genseo_api_key_user_id', get_current_user_id(), false);

        // Đồng bộ vào genseo_settings (tương thích ngược)
        genseo_update_setting('api_key', $new_key);
        genseo_update_setting('api_key_user_id', get_current_user_id());

        return $new_key;
    }

    /**
     * Lấy user ID của admin đã tạo API key
     * Dùng để set current user khi xử lý request
     */
    public static function get_key_owner_user_id() {
        // Ưu tiên option riêng
        $user_id = get_option('genseo_api_key_user_id', 0);
        if (empty($user_id)) {
            $user_id = genseo_get_setting('api_key_user_id', 0);
        }
        if ($user_id && get_user_by('id', $user_id)) {
            return (int) $user_id;
        }
        // Fallback: lấy admin user đầu tiên
        $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
        return !empty($admins) ? (int) $admins[0] : 0;
    }

    // ============================================================
    // CORS HEADERS
    // ============================================================

    private static function send_cors_headers() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, User-Agent, X-GenSeo-Request');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Content-Type: application/json; charset=utf-8');
    }

    private static function handle_preflight() {
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
            self::send_cors_headers();
            header('Access-Control-Max-Age: 86400');
            header('Content-Length: 0');
            status_header(204);
            exit;
        }
    }

    // ============================================================
    // RATE LIMITING
    // ============================================================

    /**
     * Simple rate limit: 120 requests/minute per IP
     * Dùng transient để đếm
     */
    private static function check_rate_limit() {
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $key = 'genseo_rl_' . md5($ip);
        $count = (int) get_transient($key);

        if ($count >= 120) {
            return false;
        }

        set_transient($key, $count + 1, 60); // 60 seconds TTL
        return true;
    }

    // ============================================================
    // BODY READER (WAF-safe)
    // ============================================================

    /**
     * Đọc request body, hỗ trợ cả json_payload form field (WAF-safe) và raw JSON
     * Desktop v0.8.5+ gửi form-urlencoded với json_payload field để bypass
     * WAF/OpenResty chặn Content-Type: application/json tới admin-ajax.php
     * @return array|null Parsed request data hoặc null nếu không đọc được
     */
    private static function read_request_body() {
        // Ưu tiên 1: json_payload form field (desktop mới gửi form-urlencoded)
        if (!empty($_POST['json_payload'])) {
            $decoded = json_decode(wp_unslash($_POST['json_payload']), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Ưu tiên 2: raw JSON body (backward compat với desktop cũ)
        $raw_body = file_get_contents('php://input');
        if ($raw_body) {
            $decoded = json_decode($raw_body, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Fallback: $_POST trực tiếp
        if (!empty($_POST)) {
            return $_POST;
        }

        return null;
    }

    // ============================================================
    // TEST CONNECTION ENDPOINT
    // ============================================================

    /**
     * AJAX handler: genseo_api_test
     * Test connection qua API Key
     * Desktop gọi để verify key hợp lệ
     */
    public static function ajax_api_test() {
        self::handle_preflight();
        self::send_cors_headers();

        if (!self::check_rate_limit()) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Rate limit exceeded. Vui lòng thử lại sau 1 phút.')), 429);
            return;
        }

        // Đọc body (hỗ trợ form-urlencoded json_payload + raw JSON + $_POST fallback)
        $request = self::read_request_body();

        $api_key = isset($request['api_key']) ? sanitize_text_field($request['api_key']) : '';

        if (!self::validate_key($api_key)) {
            $stored_key = self::get_key();
            wp_send_json(array(
                'success' => false,
                'data'    => array(
                    'message' => 'API Key không hợp lệ.',
                    'debug'   => array(
                        'received_length' => strlen($api_key),
                        'received_prefix' => substr($api_key, 0, 8),
                        'stored_length'   => strlen($stored_key),
                        'stored_prefix'   => substr($stored_key, 0, 8),
                        'stored_empty'    => empty($stored_key),
                    ),
                ),
            ), 403);
            return;
        }

        // Set current user
        $user_id = self::get_key_owner_user_id();
        if ($user_id) {
            wp_set_current_user($user_id);
        }

        $user = wp_get_current_user();
        global $wp_version;

        wp_send_json(array(
            'success' => true,
            'data'    => array(
                'message'        => 'Kết nối thành công qua API Key!',
                'user'           => array(
                    'id'   => $user->ID,
                    'name' => $user->display_name,
                    'slug' => $user->user_nicename,
                ),
                'site_info'      => array(
                    'name'        => get_bloginfo('name'),
                    'description' => get_bloginfo('description'),
                    'url'         => home_url(),
                    'home'        => home_url(),
                ),
                'wp_version'     => $wp_version,
                'plugin_version' => GENSEO_VERSION,
                'seo_plugin'     => genseo_detect_seo_plugin(),
                'auth_method'    => 'api_key',
            ),
        ));
    }

    // ============================================================
    // MAIN API PROXY ENDPOINT
    // ============================================================

    /**
     * AJAX handler: genseo_api_proxy
     * Dispatch các operations dựa trên action_type
     */
    public static function ajax_api_proxy() {
        self::handle_preflight();
        self::send_cors_headers();

        if (!self::check_rate_limit()) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Rate limit exceeded.')), 429);
            return;
        }

        // Đọc body (hỗ trợ form-urlencoded json_payload + raw JSON fallback)
        $request = self::read_request_body();

        if (!is_array($request)) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Invalid JSON body.')), 400);
            return;
        }

        // Validate API Key
        $api_key = isset($request['api_key']) ? sanitize_text_field($request['api_key']) : '';

        if (!self::validate_key($api_key)) {
            $stored_key = self::get_key();
            wp_send_json(array(
                'success' => false,
                'data'    => array(
                    'message' => 'API Key không hợp lệ.',
                    'debug'   => array(
                        'received_length' => strlen($api_key),
                        'received_prefix' => substr($api_key, 0, 8),
                        'stored_length'   => strlen($stored_key),
                        'stored_prefix'   => substr($stored_key, 0, 8),
                        'stored_empty'    => empty($stored_key),
                    ),
                ),
            ), 403);
            return;
        }

        // Set current user (admin user đã tạo key)
        $user_id = self::get_key_owner_user_id();
        if ($user_id) {
            wp_set_current_user($user_id);
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'User không có quyền edit posts.')), 403);
            return;
        }

        // Dispatch theo action_type từ request
        $action_type = isset($request['action_type']) ? sanitize_text_field($request['action_type']) : '';
        $data = isset($request['data']) ? $request['data'] : array();

        switch ($action_type) {
            case 'create_post':
                self::handle_create_post($data);
                break;

            case 'update_post':
                self::handle_update_post($data);
                break;

            case 'upload_media':
                self::handle_upload_media($data);
                break;

            case 'get_categories':
                self::handle_get_categories();
                break;

            case 'get_tags':
                self::handle_get_tags();
                break;

            case 'create_tag':
                self::handle_create_tag($data);
                break;

            case 'get_or_create_tag':
                self::handle_get_or_create_tag($data);
                break;

            case 'update_seo_meta':
                self::handle_update_seo_meta($data);
                break;

            case 'health':
                // Reuse health check logic
                genseo_ajax_health_check();
                break;

            case 'detect_seo':
                self::handle_detect_seo();
                break;

            case 'get_published_posts':
                self::handle_get_published_posts($data);
                break;

            case 'get_media':
                self::handle_get_media($data);
                break;

            case 'update_media_meta':
                self::handle_update_media_meta($data);
                break;

            case 'apply_rewrite':
                self::handle_apply_rewrite($data);
                break;

            case 'revert_rewrite':
                self::handle_revert_rewrite($data);
                break;

            case 'get_rewrite_candidates':
                self::handle_get_rewrite_candidates($data);
                break;

            case 'get_post_content':
                self::handle_get_post_content($data);
                break;

            default:
                wp_send_json(array(
                    'success' => false,
                    'data'    => array('message' => 'Unknown action_type: ' . $action_type),
                ), 400);
                break;
        }
    }

    // ============================================================
    // ACTION HANDLERS
    // ============================================================

    /**
     * Tạo bài viết mới
     */
    private static function handle_create_post($data) {
        if (!current_user_can('publish_posts')) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Không có quyền tạo bài viết.')), 403);
            return;
        }

        $post_data = array(
            'post_title'   => sanitize_text_field($data['title'] ?? ''),
            'post_content' => wp_kses_post($data['content'] ?? ''),
            'post_status'  => sanitize_text_field($data['status'] ?? 'draft'),
            'post_excerpt' => sanitize_text_field($data['excerpt'] ?? ''),
            'post_author'  => get_current_user_id(),
        );

        // SEO slug
        if (!empty($data['slug'])) {
            $post_data['post_name'] = sanitize_title($data['slug']);
        }

        // Categories
        if (!empty($data['categories']) && is_array($data['categories'])) {
            $post_data['post_category'] = array_map('absint', $data['categories']);
        }

        // Tags
        if (!empty($data['tags']) && is_array($data['tags'])) {
            $post_data['tags_input'] = array_map('sanitize_text_field', $data['tags']);
        }

        // Featured image
        if (!empty($data['featured_media'])) {
            // Sẽ set sau khi insert
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            wp_send_json(array(
                'success' => false,
                'data'    => array('message' => $post_id->get_error_message()),
            ), 500);
            return;
        }

        // Featured image
        if (!empty($data['featured_media'])) {
            set_post_thumbnail($post_id, absint($data['featured_media']));
        }

        // GenSeo meta fields
        if (!empty($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                // Chỉ cho phép meta keys bắt đầu bằng _genseo_
                if (strpos($key, '_genseo_') === 0) {
                    $safe_key = sanitize_key($key);
                    if (is_array($value)) {
                        update_post_meta($post_id, $safe_key, array_map('sanitize_text_field', $value));
                    } else {
                        update_post_meta($post_id, $safe_key, sanitize_text_field($value));
                    }
                }
            }
        }

        // Trigger GenSeo SEO sync hooks
        do_action('genseo_after_publish', $post_id, $data);

        $post = get_post($post_id);
        wp_send_json(array(
            'success' => true,
            'data'    => array(
                'id'     => $post_id,
                'link'   => get_permalink($post_id),
                'status' => $post->post_status,
            ),
        ));
    }

    /**
     * Cập nhật bài viết
     */
    private static function handle_update_post($data) {
        $post_id = absint($data['id'] ?? 0);
        if (!$post_id) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Thiếu post ID.')), 400);
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Không có quyền sửa bài viết này.')), 403);
            return;
        }

        $post_data = array('ID' => $post_id);

        if (isset($data['title'])) {
            $post_data['post_title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['content'])) {
            // Safety guard: Gutenberg block integrity
            $current_post = get_post($post_id);
            $current_content = $current_post ? $current_post->post_content : '';
            $current_has_gutenberg = preg_match('/<!--\s*wp:/', $current_content);
            $new_has_gutenberg = preg_match('/<!--\s*wp:/', $data['content']);

            if ($current_has_gutenberg && !$new_has_gutenberg && strlen($current_content) > 200) {
                wp_send_json(array(
                    'success' => false,
                    'data' => array(
                        'message' => 'Content hiện tại dùng Gutenberg blocks nhưng content mới không chứa block markers. Hủy cập nhật để bảo vệ nội dung.',
                        'code' => 'gutenberg_block_integrity',
                    ),
                ), 422);
                return;
            }

            $post_data['post_content'] = wp_kses_post($data['content']);
        }
        if (isset($data['status'])) {
            $post_data['post_status'] = sanitize_text_field($data['status']);
        }
        if (isset($data['excerpt'])) {
            $post_data['post_excerpt'] = sanitize_text_field($data['excerpt']);
        }

        // Categories
        if (!empty($data['categories']) && is_array($data['categories'])) {
            $post_data['post_category'] = array_map('absint', $data['categories']);
        }

        $result = wp_update_post($post_data, true);

        if (is_wp_error($result)) {
            wp_send_json(array(
                'success' => false,
                'data'    => array('message' => $result->get_error_message()),
            ), 500);
            return;
        }

        // Featured image
        if (!empty($data['featured_media'])) {
            set_post_thumbnail($post_id, absint($data['featured_media']));
        }

        // Meta fields
        if (!empty($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                if (strpos($key, '_genseo_') === 0) {
                    $safe_key = sanitize_key($key);
                    if (is_array($value)) {
                        update_post_meta($post_id, $safe_key, array_map('sanitize_text_field', $value));
                    } else {
                        update_post_meta($post_id, $safe_key, sanitize_text_field($value));
                    }
                }
            }
        }

        do_action('genseo_after_publish', $post_id, $data);

        wp_send_json(array(
            'success' => true,
            'data'    => array(
                'id'   => $post_id,
                'link' => get_permalink($post_id),
            ),
        ));
    }

    /**
     * Upload media (base64 trong body)
     */
    private static function handle_upload_media($data) {
        if (!current_user_can('upload_files')) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Không có quyền upload file.')), 403);
            return;
        }

        $base64 = isset($data['file']) ? $data['file'] : '';
        $filename = isset($data['filename']) ? sanitize_file_name($data['filename']) : 'upload.png';

        if (empty($base64)) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Thiếu file data (base64).')), 400);
            return;
        }

        // Kiểm tra kích thước base64 string trước khi decode (phát hiện truncation sớm)
        $base64_length = strlen($base64);

        // Parse base64 data URL
        $content_type = 'image/png';
        if (preg_match('/^data:([\w\/\+\-]+);base64,/', $base64, $matches)) {
            $content_type = $matches[1];
            $base64 = preg_replace('/^data:[\w\/\+\-]+;base64,/', '', $base64);
        }

        // Validate base64 string (phát hiện truncation: base64 hợp lệ phải có length chia hết cho 4)
        $clean_base64 = rtrim($base64);
        if (strlen($clean_base64) % 4 !== 0) {
            wp_send_json(array('success' => false, 'data' => array(
                'message' => 'Base64 data bị cắt (truncated). Ảnh quá lớn cho POST request. Hãy bật tính năng Resize ảnh trong cài đặt dự án GenSeo hoặc giảm kích thước ảnh.',
                'debug' => array(
                    'base64_length' => strlen($clean_base64),
                    'post_max_size' => ini_get('post_max_size'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                ),
            )), 400);
            return;
        }

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $file_data = base64_decode($base64, true);
        if ($file_data === false) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Base64 decode thất bại.')), 400);
            return;
        }

        // Max 10MB
        if (strlen($file_data) > 10 * 1024 * 1024) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'File quá lớn (max 10MB).')), 400);
            return;
        }

        // Validate ảnh sau decode: kiểm tra header PNG/JPEG/GIF/WEBP để đảm bảo không bị corrupt
        $header = substr($file_data, 0, 8);
        $is_valid_image = false;
        if (substr($header, 0, 8) === "\x89PNG\r\n\x1a\n") $is_valid_image = true;       // PNG
        elseif (substr($header, 0, 2) === "\xFF\xD8") $is_valid_image = true;              // JPEG
        elseif (substr($header, 0, 3) === 'GIF') $is_valid_image = true;                    // GIF
        elseif (substr($header, 0, 4) === 'RIFF' && substr($file_data, 8, 4) === 'WEBP') $is_valid_image = true; // WebP

        if (!$is_valid_image) {
            wp_send_json(array('success' => false, 'data' => array(
                'message' => 'File không phải ảnh hợp lệ sau decode. Dữ liệu có thể bị hỏng trong quá trình truyền.',
                'debug' => array('decoded_size' => strlen($file_data), 'base64_received' => $base64_length),
            )), 400);
            return;
        }

        // Ensure proper extension
        $ext_map = array(
            'image/png'  => '.png',
            'image/jpeg' => '.jpg',
            'image/jpg'  => '.jpg',
            'image/gif'  => '.gif',
            'image/webp' => '.webp',
        );
        $ext = isset($ext_map[$content_type]) ? $ext_map[$content_type] : '.png';
        if (!preg_match('/\.\w+$/', $filename)) {
            $filename .= $ext;
        }

        // Validate this is actually an image
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected_type = $finfo->buffer($file_data);
        $allowed_types = array('image/png', 'image/jpeg', 'image/gif', 'image/webp');
        if (!in_array($detected_type, $allowed_types, true)) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Chỉ chấp nhận file ảnh (PNG, JPG, GIF, WEBP).')), 400);
            return;
        }

        // Upload using WP functions
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload_dir = wp_upload_dir();

        // Dùng wp_unique_filename() để tránh collision (file trùng tên → WP thêm suffix → URL sai)
        $filename = wp_unique_filename($upload_dir['path'], $filename);
        $upload_path = $upload_dir['path'] . '/' . $filename;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $bytes_written = file_put_contents($upload_path, $file_data);
        if ($bytes_written === false) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Không ghi được file vào thư mục uploads.')), 500);
            return;
        }

        // Validate: số bytes ghi phải khớp với dữ liệu gốc
        if ($bytes_written !== strlen($file_data)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            @unlink($upload_path);
            wp_send_json(array('success' => false, 'data' => array(
                'message' => 'File ghi không đầy đủ (disk full hoặc lỗi I/O).',
                'debug' => array('expected' => strlen($file_data), 'written' => $bytes_written),
            )), 500);
            return;
        }

        $filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $filetype['type'] ?: $content_type,
            'post_title'     => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attach_id = wp_insert_attachment($attachment, $upload_path);
        if (is_wp_error($attach_id)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            @unlink($upload_path);
            wp_send_json(array('success' => false, 'data' => array('message' => $attach_id->get_error_message())), 500);
            return;
        }

        $attach_data = wp_generate_attachment_metadata($attach_id, $upload_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        wp_send_json(array(
            'success' => true,
            'data'    => array(
                'id'         => $attach_id,
                'source_url' => wp_get_attachment_url($attach_id),
            ),
        ));
    }

    /**
     * Lấy danh sách categories
     */
    private static function handle_get_categories() {
        $categories = get_categories(array(
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        $result = array();
        foreach ($categories as $cat) {
            $result[] = array(
                'id'     => $cat->term_id,
                'name'   => $cat->name,
                'slug'   => $cat->slug,
                'count'  => $cat->count,
                'parent' => $cat->parent,
            );
        }

        wp_send_json(array('success' => true, 'data' => $result));
    }

    /**
     * Lấy danh sách tags
     */
    private static function handle_get_tags() {
        $tags = get_tags(array(
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        $result = array();
        if (is_array($tags) && !is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $result[] = array(
                    'id'    => $tag->term_id,
                    'name'  => $tag->name,
                    'slug'  => $tag->slug,
                    'count' => $tag->count,
                );
            }
        }

        wp_send_json(array('success' => true, 'data' => $result));
    }

    /**
     * Tạo tag mới
     */
    private static function handle_create_tag($data) {
        $name = sanitize_text_field($data['name'] ?? '');
        if (empty($name)) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Thiếu tên tag.')), 400);
            return;
        }

        $result = wp_insert_term($name, 'post_tag');

        if (is_wp_error($result)) {
            // Tag đã tồn tại
            if ($result->get_error_code() === 'term_exists') {
                $existing = get_term($result->get_error_data(), 'post_tag');
                wp_send_json(array(
                    'success' => true,
                    'data'    => array(
                        'id'   => $existing->term_id,
                        'name' => $existing->name,
                        'slug' => $existing->slug,
                    ),
                ));
                return;
            }
            wp_send_json(array('success' => false, 'data' => array('message' => $result->get_error_message())), 500);
            return;
        }

        $tag = get_term($result['term_id'], 'post_tag');
        wp_send_json(array(
            'success' => true,
            'data'    => array(
                'id'   => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ),
        ));
    }

    /**
     * Tìm tag theo tên, nếu không có thì tạo mới
     */
    private static function handle_get_or_create_tag($data) {
        $name = sanitize_text_field($data['name'] ?? '');
        if (empty($name)) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Thiếu tên tag.')), 400);
            return;
        }

        // Tìm exact match
        $existing = get_term_by('name', $name, 'post_tag');
        if ($existing) {
            wp_send_json(array(
                'success' => true,
                'data'    => array(
                    'id'   => $existing->term_id,
                    'name' => $existing->name,
                    'slug' => $existing->slug,
                ),
            ));
            return;
        }

        // Tạo mới
        self::handle_create_tag($data);
    }

    /**
     * Cập nhật SEO meta cho bài viết
     */
    private static function handle_update_seo_meta($data) {
        $post_id = absint($data['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Thiếu post_id.')), 400);
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Không có quyền sửa bài viết này.')), 403);
            return;
        }

        $meta = isset($data['meta']) ? $data['meta'] : array();
        $updated = 0;

        foreach ($meta as $key => $value) {
            if (strpos($key, '_genseo_') === 0) {
                $safe_key = sanitize_key($key);
                if (is_array($value)) {
                    update_post_meta($post_id, $safe_key, array_map('sanitize_text_field', $value));
                } else {
                    update_post_meta($post_id, $safe_key, sanitize_text_field($value));
                }
                $updated++;
            }
        }

        do_action('genseo_after_publish', $post_id, $data);

        wp_send_json(array(
            'success' => true,
            'data'    => array(
                'post_id'      => $post_id,
                'meta_updated' => $updated,
            ),
        ));
    }

    /**
     * Detect SEO plugin
     */
    private static function handle_detect_seo() {
        wp_send_json(array(
            'success' => true,
            'data'    => genseo_detect_seo_plugin(),
        ));
    }

    /**
     * Lấy danh sách bài viết đã publish
     */
    private static function handle_get_published_posts($data) {
        $per_page = absint($data['per_page'] ?? 100);
        $page = absint($data['page'] ?? 1);
        $search = sanitize_text_field($data['search'] ?? '');

        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => min($per_page, 100),
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $posts = array();

        foreach ($query->posts as $post) {
            $posts[] = array(
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'url'           => get_permalink($post->ID),
                'focus_keyword' => get_post_meta($post->ID, '_genseo_focus_keyword', true),
            );
        }

        wp_send_json(array(
            'success' => true,
            'data'    => array(
                'posts' => $posts,
                'total' => $query->found_posts,
            ),
        ));
    }

    // ============================================================
    // ADMIN AJAX: REGENERATE KEY
    // ============================================================

    /**
     * Lấy thông tin media attachment theo ID
     */
    private static function handle_get_media($data) {
        $id = absint($data['id'] ?? 0);
        if (!$id) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Thiếu media ID.')), 400);
            return;
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== 'attachment') {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Không tìm thấy media.')), 404);
            return;
        }

        $metadata = wp_get_attachment_metadata($post->ID);

        wp_send_json(array(
            'success' => true,
            'data'    => array(
                'id'            => $post->ID,
                'source_url'    => wp_get_attachment_url($post->ID),
                'title'         => $post->post_title,
                'mime_type'     => $post->post_mime_type,
                'media_details' => array(
                    'width'    => isset($metadata['width']) ? (int) $metadata['width'] : 0,
                    'height'   => isset($metadata['height']) ? (int) $metadata['height'] : 0,
                    'filesize' => isset($metadata['filesize']) ? (int) $metadata['filesize'] : 0,
                ),
            ),
        ));
    }

    /**
     * Cập nhật alt_text và title cho media attachment
     */
    private static function handle_update_media_meta($data) {
        $id = absint($data['id'] ?? 0);
        if (!$id) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Thiếu media ID.')), 400);
            return;
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== 'attachment') {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Không tìm thấy media.')), 404);
            return;
        }

        $updated = false;

        if (!empty($data['alt_text'])) {
            $alt = sanitize_text_field($data['alt_text']);
            update_post_meta($id, '_wp_attachment_image_alt', $alt);
            $updated = true;
        }

        if (!empty($data['title'])) {
            $title = sanitize_text_field($data['title']);
            wp_update_post(array('ID' => $id, 'post_title' => $title));
            $updated = true;
        }

        wp_send_json(array(
            'success' => true,
            'data'    => array(
                'id'      => $id,
                'updated' => $updated,
            ),
        ));
    }

    // ============================================================
    // REWRITER ACTION HANDLERS
    // ============================================================

    /**
     * Áp dụng viết lại bài viết (proxy tới GenSeo_REST_API::apply_rewrite)
     */
    private static function handle_apply_rewrite($data) {
        $post_id = absint($data['id'] ?? 0);
        if (!$post_id) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Thiếu post ID.')), 400);
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Không có quyền sửa bài viết này.')), 403);
            return;
        }

        // Tạo WP_REST_Request giả để tái sử dụng logic apply_rewrite có sẵn
        $request = new WP_REST_Request('POST', '/genseo/v1/posts/' . $post_id . '/rewrite');
        $request->set_param('id', $post_id);
        $request->set_body(wp_json_encode($data));
        $request->set_header('Content-Type', 'application/json');

        $response = GenSeo_REST_API::apply_rewrite($request);

        if (is_wp_error($response)) {
            $status = $response->get_error_data()['status'] ?? 500;
            wp_send_json(array(
                'success' => false,
                'data'    => array('message' => $response->get_error_message()),
            ), $status);
            return;
        }

        // REST response → array
        $response_data = $response->get_data();
        wp_send_json($response_data);
    }

    /**
     * Hoàn tác viết lại bài viết (proxy tới GenSeo_REST_API::revert_rewrite)
     */
    private static function handle_revert_rewrite($data) {
        $post_id = absint($data['id'] ?? 0);
        if (!$post_id) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Thiếu post ID.')), 400);
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Không có quyền sửa bài viết này.')), 403);
            return;
        }

        $request = new WP_REST_Request('POST', '/genseo/v1/posts/' . $post_id . '/rewrite/revert');
        $request->set_param('id', $post_id);

        $response = GenSeo_REST_API::revert_rewrite($request);

        if (is_wp_error($response)) {
            $status = $response->get_error_data()['status'] ?? 500;
            wp_send_json(array(
                'success' => false,
                'data'    => array('message' => $response->get_error_message()),
            ), $status);
            return;
        }

        $response_data = $response->get_data();
        wp_send_json($response_data);
    }

    /**
     * Lấy danh sách bài viết cần viết lại (proxy tới GenSeo_REST_API::get_rewrite_candidates)
     */
    private static function handle_get_rewrite_candidates($data) {
        $request = new WP_REST_Request('GET', '/genseo/v1/posts/rewrite-candidates');

        // Map các filter params
        $params = array(
            'per_page', 'page', 'status', 'search', 'category',
            'date_after', 'date_before', 'min_age_days',
            'exclude_recently_rewritten', 'orderby', 'order',
        );
        foreach ($params as $param) {
            if (isset($data[$param])) {
                $request->set_param($param, $data[$param]);
            }
        }

        // Defaults (giống REST API registration)
        if (!$request->get_param('per_page')) $request->set_param('per_page', 50);
        if (!$request->get_param('page')) $request->set_param('page', 1);
        if (!$request->get_param('status')) $request->set_param('status', 'publish');
        if (!$request->get_param('orderby')) $request->set_param('orderby', 'modified');
        if (!$request->get_param('order')) $request->set_param('order', 'asc');

        $response = GenSeo_REST_API::get_rewrite_candidates($request);

        if (is_wp_error($response)) {
            $status = $response->get_error_data()['status'] ?? 500;
            wp_send_json(array(
                'success' => false,
                'data'    => array('message' => $response->get_error_message()),
            ), $status);
            return;
        }

        $response_data = $response->get_data();
        wp_send_json($response_data);
    }

    /**
     * Lấy nội dung bài viết + SEO meta
     */
    private static function handle_get_post_content($data) {
        $post_id = absint($data['id'] ?? 0);
        if (!$post_id) {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Thiếu post ID.')), 400);
            return;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            wp_send_json(array('success' => false, 'data' => array('message' => 'Không tìm thấy bài viết.')), 404);
            return;
        }

        // SEO meta
        $seo_meta = array();
        $seo_meta['genseo_meta_desc'] = get_post_meta($post_id, '_genseo_meta_desc', true);
        $seo_meta['genseo_seo_title'] = get_post_meta($post_id, '_genseo_seo_title', true);
        $seo_meta['genseo_focus_keyword'] = get_post_meta($post_id, '_genseo_focus_keyword', true);

        $seo_plugin = genseo_detect_seo_plugin();
        if ($seo_plugin['detected']) {
            if ($seo_plugin['type'] === 'rankmath') {
                $seo_meta['rank_math_title'] = get_post_meta($post_id, 'rank_math_title', true);
                $seo_meta['rank_math_description'] = get_post_meta($post_id, 'rank_math_description', true);
                $seo_meta['rank_math_focus_keyword'] = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            } elseif ($seo_plugin['type'] === 'yoast') {
                $seo_meta['yoast_title'] = get_post_meta($post_id, '_yoast_wpseo_title', true);
                $seo_meta['yoast_metadesc'] = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                $seo_meta['yoast_focuskw'] = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            }
        }

        wp_send_json(array(
            'success' => true,
            'data'    => array(
                'id'            => $post_id,
                'title'         => array('rendered' => $post->post_title),
                'content'       => array('rendered' => $post->post_content),
                'excerpt'       => array('rendered' => $post->post_excerpt),
                'slug'          => $post->post_slug ?? $post->post_name,
                'link'          => get_permalink($post_id),
                'status'        => $post->post_status,
                'date'          => $post->post_date,
                'modified'      => $post->post_modified,
                'seo_meta'      => $seo_meta,
                'seo_plugin'    => $seo_plugin,
                'featured_image' => get_the_post_thumbnail_url($post_id, 'full'),
            ),
        ));
    }

    /**
     * AJAX handler: Tạo lại API Key (chỉ admin)
     */
    public static function ajax_regenerate_key() {
        check_ajax_referer('genseo_regenerate_api_key', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền.'));
            return;
        }

        $new_key = self::regenerate_key();

        wp_send_json_success(array(
            'api_key' => $new_key,
            'message' => 'Đã tạo API Key mới. Vui lòng cập nhật key trong GenSeo Desktop.',
        ));
    }
}
