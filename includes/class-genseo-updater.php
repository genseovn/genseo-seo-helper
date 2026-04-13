<?php
/**
 * Auto-Update Checker - Kiểm tra phiên bản mới từ plugin.genseo.vn
 *
 * Hook vào WordPress update system để hiển thị thông báo cập nhật
 * và cho phép 1-click update từ Plugins page.
 *
 * @package GenSeo_SEO_Helper
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_Updater
 *
 * Tự động kiểm tra phiên bản mới từ plugin.genseo.vn
 * và inject thông tin update vào WordPress transient.
 */
class GenSeo_Updater {

    /**
     * URL API kiểm tra update
     */
    const UPDATE_URL = 'https://server.genseo.vn/api/plugin/check-update';

    /**
     * Thời gian cache (12 giờ)
     */
    const CACHE_TTL = 43200;

    /**
     * Tên transient cache
     */
    const CACHE_KEY = 'genseo_update_check';

    /**
     * Khởi tạo class - chỉ gọi trong admin context
     */
    public static function init() {
        // Inject thông tin update khi WP check plugins
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'check_for_update'));

        // Hiển thị chi tiết plugin khi user click "Xem chi tiết"
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 20, 3);

        // Cleanup cache sau khi update xong
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_cache'), 10, 2);

        // Thêm link "Kiểm tra cập nhật" vào plugin action links
        add_filter('plugin_action_links_' . GENSEO_PLUGIN_BASENAME, array(__CLASS__, 'add_check_update_link'));

        // AJAX handler cho kiểm tra cập nhật thủ công
        add_action('wp_ajax_genseo_force_update_check', array(__CLASS__, 'ajax_force_check'));

        // Fix thư mục giải nén khi auto-update (đảm bảo đúng slug)
        add_filter('upgrader_source_selection', array(__CLASS__, 'fix_source_dir'), 10, 4);
    }

    /**
     * Sửa tên thư mục giải nén khi update plugin
     * WordPress có thể tạo thư mục dạng genseo-seo-helper-2.0.1 thay vì genseo-seo-helper
     *
     * @param string      $source        Đường dẫn thư mục nguồn (giải nén)
     * @param string      $remote_source Đường dẫn remote source
     * @param WP_Upgrader $upgrader      Upgrader instance
     * @param array       $hook_extra    Extra data
     * @return string|WP_Error
     */
    public static function fix_source_dir($source, $remote_source, $upgrader, $hook_extra) {
        // Chỉ xử lý khi update plugin GenSeo
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== GENSEO_PLUGIN_BASENAME) {
            return $source;
        }

        global $wp_filesystem;

        $correct_slug = 'genseo-seo-helper';
        $source_base = trailingslashit(basename($source));

        // Nếu thư mục đã đúng slug thì bỏ qua
        if ($source_base === $correct_slug . '/') {
            return $source;
        }

        // Rename thư mục về đúng slug
        $new_source = trailingslashit($remote_source) . $correct_slug . '/';

        if ($wp_filesystem->move($source, $new_source)) {
            return $new_source;
        }

        return new WP_Error(
            'genseo_rename_failed',
            'Không thể đổi tên thư mục plugin về đúng slug.'
        );
    }

    /**
     * Kiểm tra update khi WordPress refresh transient
     *
     * @param object $transient Update transient data
     * @return object Modified transient
     */
    public static function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = self::get_remote_info();
        if (!$remote || is_wp_error($remote)) {
            return $transient;
        }

        // So sánh version - chỉ inject nếu version mới hơn
        if (version_compare(GENSEO_VERSION, $remote->version, '<')) {
            $plugin_data = new stdClass();
            $plugin_data->slug        = 'genseo-seo-helper';
            $plugin_data->plugin      = GENSEO_PLUGIN_BASENAME;
            $plugin_data->new_version = $remote->version;
            $plugin_data->url         = 'https://genseo.app';
            $plugin_data->package     = $remote->download_url;

            // Thông tin tương thích
            if (isset($remote->requires)) {
                $plugin_data->requires = $remote->requires;
            }
            if (isset($remote->requires_php)) {
                $plugin_data->requires_php = $remote->requires_php;
            }
            if (isset($remote->tested)) {
                $plugin_data->tested = $remote->tested;
            }

            // Đánh dấu urgent nếu có
            if (!empty($remote->urgent)) {
                $plugin_data->upgrade_notice = isset($remote->upgrade_notice)
                    ? $remote->upgrade_notice
                    : 'Bản cập nhật quan trọng! Vui lòng cập nhật ngay.';
            }

            $transient->response[GENSEO_PLUGIN_BASENAME] = $plugin_data;
        } else {
            // Version hiện tại mới nhất - đảm bảo không hiện update sai
            $plugin_data = new stdClass();
            $plugin_data->slug        = 'genseo-seo-helper';
            $plugin_data->plugin      = GENSEO_PLUGIN_BASENAME;
            $plugin_data->new_version = GENSEO_VERSION;
            $plugin_data->url         = 'https://genseo.app';
            $plugin_data->package     = '';

            $transient->no_update[GENSEO_PLUGIN_BASENAME] = $plugin_data;
        }

        return $transient;
    }

    /**
     * Hiển thị thông tin chi tiết plugin khi user click "Xem chi tiết"
     *
     * @param false|object|array $result Plugin info result
     * @param string             $action API action
     * @param object             $args   Request args
     * @return false|object
     */
    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== 'genseo-seo-helper') {
            return $result;
        }

        $remote = self::get_remote_info();
        if (!$remote || is_wp_error($remote)) {
            return $result;
        }

        $info = new stdClass();
        $info->name          = isset($remote->name) ? $remote->name : 'GenSeo SEO Helper';
        $info->slug          = 'genseo-seo-helper';
        $info->version       = $remote->version;
        $info->author        = '<a href="https://genseo.app">GenSeo Team</a>';
        $info->author_profile = 'https://genseo.app';
        $info->homepage      = 'https://genseo.app';
        $info->requires      = isset($remote->requires) ? $remote->requires : '6.9';
        $info->requires_php  = isset($remote->requires_php) ? $remote->requires_php : '7.4';
        $info->tested        = isset($remote->tested) ? $remote->tested : '';
        $info->last_updated  = isset($remote->last_updated) ? $remote->last_updated : '';
        $info->download_link = isset($remote->download_url) ? $remote->download_url : '';

        // Sections (description, changelog, installation...)
        if (isset($remote->sections) && is_object($remote->sections)) {
            $info->sections = (array) $remote->sections;
        } else {
            $info->sections = array(
                'description' => 'Tối ưu SEO cho bài viết từ GenSeo Desktop.',
                'changelog'   => isset($remote->changelog) ? $remote->changelog : '',
            );
        }

        // Banners
        if (isset($remote->banners) && is_object($remote->banners)) {
            $info->banners = (array) $remote->banners;
        }

        return $info;
    }

    /**
     * Xóa cache sau khi update plugin xong
     *
     * @param WP_Upgrader $upgrader Upgrader object
     * @param array        $options  Update options
     */
    public static function clear_cache($upgrader, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient(self::CACHE_KEY);
            delete_site_transient('update_plugins');
        }
    }

    /**
     * Thêm link "Kiểm tra cập nhật" vào plugin action links
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public static function add_check_update_link($links) {
        $check_link = '<a href="#" id="genseo-check-update" onclick="genseoForceUpdateCheck(event)">'
                    . esc_html__('Kiểm tra cập nhật', 'genseo-seo-helper')
                    . '</a>';

        // Inline JS nhỏ gọn cho force check
        $check_link .= '<script>
        function genseoForceUpdateCheck(e){
            e.preventDefault();
            var el=document.getElementById("genseo-check-update");
            el.textContent="Đang kiểm tra...";
            fetch(ajaxurl+"?action=genseo_force_update_check&_wpnonce='
            . wp_create_nonce('genseo_force_update_check')
            . '").then(function(r){return r.json()}).then(function(d){
                if(d.success){
                    el.textContent=d.data.message;
                    if(d.data.has_update)setTimeout(function(){location.reload()},1500);
                }else{el.textContent="Lỗi kiểm tra";}
            }).catch(function(){el.textContent="Lỗi kết nối";});
        }
        </script>';

        $links[] = $check_link;
        return $links;
    }

    /**
     * AJAX handler - Force check update (xóa cache và kiểm tra lại)
     */
    public static function ajax_force_check() {
        check_ajax_referer('genseo_force_update_check', '_wpnonce');

        if (!current_user_can('update_plugins')) {
            wp_send_json_error(array('message' => 'Không có quyền.'));
            return;
        }

        // Xóa cache cũ
        delete_transient(self::CACHE_KEY);

        // Kiểm tra lại
        $remote = self::get_remote_info(true);
        if (!$remote || is_wp_error($remote)) {
            wp_send_json_error(array('message' => 'Không thể kết nối server.'));
            return;
        }

        $has_update = version_compare(GENSEO_VERSION, $remote->version, '<');

        // Xóa transient update để WP re-check
        delete_site_transient('update_plugins');

        wp_send_json_success(array(
            'message'     => $has_update
                ? sprintf('Có bản cập nhật v%s!', $remote->version)
                : 'Đang dùng phiên bản mới nhất.',
            'has_update'  => $has_update,
            'new_version' => $remote->version,
        ));
    }

    /**
     * Lấy thông tin update từ server (có cache)
     *
     * @param bool $force_refresh Bỏ qua cache
     * @return object|false|WP_Error
     */
    private static function get_remote_info($force_refresh = false) {
        // Kiểm tra cache trước
        if (!$force_refresh) {
            $cached = get_transient(self::CACHE_KEY);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Tham số gửi kèm để server biết context
        global $wp_version;
        $query_args = array(
            'version' => GENSEO_VERSION,
            'wp'      => $wp_version,
            'php'     => PHP_VERSION,
            'domain'  => wp_parse_url(home_url(), PHP_URL_HOST),
        );

        $url = add_query_arg($query_args, self::UPDATE_URL);

        $response = wp_remote_get($url, array(
            'timeout'   => 5,
            'sslverify' => true,
            'headers'   => array(
                'Accept'     => 'application/json',
                'User-Agent' => 'GenSeo-SEO-Helper/' . GENSEO_VERSION . ' WordPress/' . $wp_version,
            ),
        ));

        if (is_wp_error($response)) {
            // Log lỗi nhưng không block WordPress
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GenSeo Update Check failed: ' . $response->get_error_message());
            }
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GenSeo Update Check HTTP ' . $code);
            }
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data) || !isset($data->version)) {
            return false;
        }

        // Cache kết quả
        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);

        return $data;
    }
}
