<?php
/**
 * Admin - Settings page và meta box
 *
 * @package GenSeo_SEO_Helper
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_Admin
 *
 * Quản lý trang settings và meta box trong admin
 */
class GenSeo_Admin {

    /**
     * Khởi tạo class
     */
    public static function init() {
        // Thêm menu settings
        add_action('admin_menu', array(__CLASS__, 'add_menu'));

        // Đăng ký settings
        add_action('admin_init', array(__CLASS__, 'register_settings'));

        // Thêm meta box trong post editor
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_box'));

        // Enqueue admin styles
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));

        // Thêm link settings vào plugin list
        add_filter('plugin_action_links_' . GENSEO_PLUGIN_BASENAME, array(__CLASS__, 'add_action_links'));
    }

    /**
     * Thêm menu settings
     */
    public static function add_menu() {
        add_options_page(
            __('GenSeo SEO Helper', 'genseo-seo-helper'),
            __('GenSeo SEO Helper', 'genseo-seo-helper'),
            'manage_options',
            'genseo-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    /**
     * Đăng ký settings
     */
    public static function register_settings() {
        register_setting(
            'genseo_settings_group',
            'genseo_settings',
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
            )
        );

        // Section: General
        add_settings_section(
            'genseo_general_section',
            __('Cài đặt chung', 'genseo-seo-helper'),
            array(__CLASS__, 'render_general_section'),
            'genseo-settings'
        );

        // Field: Enable OpenGraph
        add_settings_field(
            'enable_opengraph',
            __('OpenGraph', 'genseo-seo-helper'),
            array(__CLASS__, 'render_checkbox_field'),
            'genseo-settings',
            'genseo_general_section',
            array(
                'id'          => 'enable_opengraph',
                'description' => __('Output OpenGraph meta tags cho bài viết từ GenSeo', 'genseo-seo-helper'),
            )
        );

        // Field: Enable Twitter Cards
        add_settings_field(
            'enable_twitter_cards',
            __('Twitter Cards', 'genseo-seo-helper'),
            array(__CLASS__, 'render_checkbox_field'),
            'genseo-settings',
            'genseo_general_section',
            array(
                'id'          => 'enable_twitter_cards',
                'description' => __('Output Twitter Card meta tags', 'genseo-seo-helper'),
            )
        );

        // Field: Enable Schema
        add_settings_field(
            'enable_schema',
            __('Schema.org', 'genseo-seo-helper'),
            array(__CLASS__, 'render_checkbox_field'),
            'genseo-settings',
            'genseo_general_section',
            array(
                'id'          => 'enable_schema',
                'description' => __('Output Schema.org JSON-LD structured data', 'genseo-seo-helper'),
            )
        );

        // Section: SEO Plugin Sync
        add_settings_section(
            'genseo_sync_section',
            __('Đồng bộ SEO Plugin', 'genseo-seo-helper'),
            array(__CLASS__, 'render_sync_section'),
            'genseo-settings'
        );

        // Field: RankMath Sync
        add_settings_field(
            'enable_rankmath_sync',
            __('Rank Math SEO', 'genseo-seo-helper'),
            array(__CLASS__, 'render_checkbox_field'),
            'genseo-settings',
            'genseo_sync_section',
            array(
                'id'          => 'enable_rankmath_sync',
                'description' => __('Tự động sync SEO data sang Rank Math', 'genseo-seo-helper'),
            )
        );

        // Field: Yoast Sync
        add_settings_field(
            'enable_yoast_sync',
            __('Yoast SEO', 'genseo-seo-helper'),
            array(__CLASS__, 'render_checkbox_field'),
            'genseo-settings',
            'genseo_sync_section',
            array(
                'id'          => 'enable_yoast_sync',
                'description' => __('Tự động sync SEO data sang Yoast SEO', 'genseo-seo-helper'),
            )
        );

        // Section: Defaults
        add_settings_section(
            'genseo_defaults_section',
            __('Giá trị mặc định', 'genseo-seo-helper'),
            array(__CLASS__, 'render_defaults_section'),
            'genseo-settings'
        );

        // Field: Default OG Image
        add_settings_field(
            'default_og_image',
            __('Default OG Image', 'genseo-seo-helper'),
            array(__CLASS__, 'render_image_field'),
            'genseo-settings',
            'genseo_defaults_section',
            array(
                'id'          => 'default_og_image',
                'description' => __('Ảnh mặc định khi bài viết không có featured image', 'genseo-seo-helper'),
            )
        );

        // Field: Publisher Logo
        add_settings_field(
            'publisher_logo',
            __('Publisher Logo', 'genseo-seo-helper'),
            array(__CLASS__, 'render_image_field'),
            'genseo-settings',
            'genseo_defaults_section',
            array(
                'id'          => 'publisher_logo',
                'description' => __('Logo cho Schema.org Publisher', 'genseo-seo-helper'),
            )
        );

        // Field: Twitter Username
        add_settings_field(
            'twitter_username',
            __('Twitter Username', 'genseo-seo-helper'),
            array(__CLASS__, 'render_text_field'),
            'genseo-settings',
            'genseo_defaults_section',
            array(
                'id'          => 'twitter_username',
                'description' => __('@username cho Twitter Cards (không cần @)', 'genseo-seo-helper'),
                'placeholder' => 'genseoapp',
            )
        );

        // Field: Default Schema Type
        add_settings_field(
            'schema_type_default',
            __('Default Schema Type', 'genseo-seo-helper'),
            array(__CLASS__, 'render_select_field'),
            'genseo-settings',
            'genseo_defaults_section',
            array(
                'id'          => 'schema_type_default',
                'description' => __('Schema type mặc định cho bài viết', 'genseo-seo-helper'),
                'options'     => array(
                    'Article' => 'Article',
                    'HowTo'   => 'HowTo (Hướng dẫn)',
                    'FAQ'     => 'FAQ (Hỏi đáp)',
                ),
            )
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input values
     * @return array
     */
    public static function sanitize_settings($input) {
        $sanitized = array();

        // Checkboxes
        $checkboxes = array(
            'enable_opengraph',
            'enable_twitter_cards',
            'enable_schema',
            'enable_rankmath_sync',
            'enable_yoast_sync',
        );

        foreach ($checkboxes as $key) {
            $sanitized[$key] = !empty($input[$key]);
        }

        // Integers
        $sanitized['default_og_image'] = absint($input['default_og_image'] ?? 0);
        $sanitized['publisher_logo']   = absint($input['publisher_logo'] ?? 0);

        // Strings
        $sanitized['twitter_username']   = sanitize_text_field($input['twitter_username'] ?? '');
        $sanitized['schema_type_default'] = sanitize_text_field($input['schema_type_default'] ?? 'Article');

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Hiển thị thông báo lưu thành công
        settings_errors('genseo_settings');

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="genseo-admin-header">
                <p class="description">
                    <?php _e('Plugin hỗ trợ SEO cho bài viết được đăng từ GenSeo Desktop.', 'genseo-seo-helper'); ?>
                    <br>
                    <?php _e('Version:', 'genseo-seo-helper'); ?> <strong><?php echo GENSEO_VERSION; ?></strong>
                </p>
            </div>

            <?php self::render_status_box(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('genseo_settings_group');
                do_settings_sections('genseo-settings');
                submit_button(__('Lưu cài đặt', 'genseo-seo-helper'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render status box
     */
    private static function render_status_box() {
        $seo_plugin = genseo_detect_seo_plugin();
        $genseo_posts_count = self::count_genseo_posts();

        ?>
        <div class="genseo-status-box">
            <h3><?php _e('Trạng thái', 'genseo-seo-helper'); ?></h3>
            <table class="genseo-status-table">
                <tr>
                    <td><?php _e('SEO Plugin phát hiện:', 'genseo-seo-helper'); ?></td>
                    <td>
                        <?php if ($seo_plugin['detected']): ?>
                            <span class="genseo-status-ok">✓ <?php echo esc_html($seo_plugin['name']); ?> (v<?php echo esc_html($seo_plugin['version']); ?>)</span>
                        <?php else: ?>
                            <span class="genseo-status-warn">— Không có (GenSeo sẽ tự output SEO tags)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><?php _e('Bài viết từ GenSeo:', 'genseo-seo-helper'); ?></td>
                    <td><strong><?php echo number_format($genseo_posts_count); ?></strong> bài</td>
                </tr>
                <tr>
                    <td><?php _e('REST API:', 'genseo-seo-helper'); ?></td>
                    <td>
                        <code><?php echo esc_url(rest_url('genseo/v1/')); ?></code>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Đếm số bài viết từ GenSeo
     *
     * @return int
     */
    private static function count_genseo_posts() {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
            '_genseo_source',
            'genseo-desktop'
        ));
    }

    /**
     * Render section descriptions
     */
    public static function render_general_section() {
        echo '<p class="description">' . __('Cấu hình output SEO tags cho bài viết từ GenSeo Desktop.', 'genseo-seo-helper') . '</p>';
    }

    public static function render_sync_section() {
        echo '<p class="description">' . __('Tự động đồng bộ SEO data sang các plugin SEO phổ biến.', 'genseo-seo-helper') . '</p>';
    }

    public static function render_defaults_section() {
        echo '<p class="description">' . __('Giá trị mặc định khi bài viết không có data cụ thể.', 'genseo-seo-helper') . '</p>';
    }

    /**
     * Render checkbox field
     */
    public static function render_checkbox_field($args) {
        $value = genseo_get_setting($args['id'], true);
        ?>
        <label>
            <input type="checkbox" 
                   name="genseo_settings[<?php echo esc_attr($args['id']); ?>]" 
                   value="1" 
                   <?php checked($value, true); ?>>
            <?php echo esc_html($args['description']); ?>
        </label>
        <?php
    }

    /**
     * Render text field
     */
    public static function render_text_field($args) {
        $value = genseo_get_setting($args['id'], '');
        ?>
        <input type="text" 
               name="genseo_settings[<?php echo esc_attr($args['id']); ?>]" 
               value="<?php echo esc_attr($value); ?>"
               placeholder="<?php echo esc_attr($args['placeholder'] ?? ''); ?>"
               class="regular-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    /**
     * Render select field
     */
    public static function render_select_field($args) {
        $value = genseo_get_setting($args['id'], '');
        ?>
        <select name="genseo_settings[<?php echo esc_attr($args['id']); ?>]">
            <?php foreach ($args['options'] as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    /**
     * Render image field
     */
    public static function render_image_field($args) {
        $value = genseo_get_setting($args['id'], 0);
        $image_url = $value ? wp_get_attachment_url($value) : '';
        ?>
        <div class="genseo-image-field">
            <input type="hidden" 
                   name="genseo_settings[<?php echo esc_attr($args['id']); ?>]" 
                   value="<?php echo esc_attr($value); ?>"
                   id="<?php echo esc_attr($args['id']); ?>">
            
            <div class="genseo-image-preview" id="<?php echo esc_attr($args['id']); ?>_preview">
                <?php if ($image_url): ?>
                    <img src="<?php echo esc_url($image_url); ?>" style="max-width: 200px; height: auto;">
                <?php endif; ?>
            </div>
            
            <button type="button" 
                    class="button genseo-upload-button" 
                    data-target="<?php echo esc_attr($args['id']); ?>">
                <?php _e('Chọn ảnh', 'genseo-seo-helper'); ?>
            </button>
            
            <?php if ($value): ?>
                <button type="button" 
                        class="button genseo-remove-button" 
                        data-target="<?php echo esc_attr($args['id']); ?>">
                    <?php _e('Xóa', 'genseo-seo-helper'); ?>
                </button>
            <?php endif; ?>
            
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        </div>
        <?php
    }

    /**
     * Thêm meta box trong post editor
     */
    public static function add_meta_box() {
        add_meta_box(
            'genseo-seo-info',
            __('GenSeo SEO Info', 'genseo-seo-helper'),
            array(__CLASS__, 'render_meta_box'),
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render meta box
     *
     * @param WP_Post $post Post object
     */
    public static function render_meta_box($post) {
        if (!genseo_is_genseo_post($post->ID)) {
            echo '<p class="description">' . __('Bài viết này không được tạo từ GenSeo Desktop.', 'genseo-seo-helper') . '</p>';
            return;
        }

        $meta = GenSeo_Meta_Fields::get_all_meta($post->ID);

        ?>
        <div class="genseo-meta-box">
            <p><strong><?php _e('Nguồn:', 'genseo-seo-helper'); ?></strong> GenSeo Desktop</p>

            <?php if (!empty($meta['focus_keyword'])): ?>
                <p>
                    <strong><?php _e('Focus Keyword:', 'genseo-seo-helper'); ?></strong><br>
                    <code><?php echo esc_html($meta['focus_keyword']); ?></code>
                </p>
            <?php endif; ?>

            <?php if (!empty($meta['seo_title'])): ?>
                <p>
                    <strong><?php _e('SEO Title:', 'genseo-seo-helper'); ?></strong><br>
                    <span class="genseo-seo-title"><?php echo esc_html($meta['seo_title']); ?></span>
                    <br><small>(<?php echo strlen($meta['seo_title']); ?> ký tự)</small>
                </p>
            <?php endif; ?>

            <?php if (!empty($meta['meta_desc'])): ?>
                <p>
                    <strong><?php _e('Meta Description:', 'genseo-seo-helper'); ?></strong><br>
                    <span class="genseo-meta-desc"><?php echo esc_html($meta['meta_desc']); ?></span>
                    <br><small>(<?php echo strlen($meta['meta_desc']); ?> ký tự)</small>
                </p>
            <?php endif; ?>

            <?php if (!empty($meta['source_video'])): ?>
                <p>
                    <strong><?php _e('Video nguồn:', 'genseo-seo-helper'); ?></strong><br>
                    <a href="<?php echo esc_url($meta['source_video']); ?>" target="_blank" rel="noopener">
                        <?php _e('Xem trên YouTube', 'genseo-seo-helper'); ?> ↗
                    </a>
                </p>
            <?php endif; ?>

            <?php if (!empty($meta['word_count'])): ?>
                <p>
                    <strong><?php _e('Số từ:', 'genseo-seo-helper'); ?></strong>
                    <?php echo number_format($meta['word_count']); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page
     */
    public static function enqueue_assets($hook) {
        // Chỉ load trên trang settings hoặc post editor
        if ($hook !== 'settings_page_genseo-settings' && $hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        // Media uploader cho image fields
        wp_enqueue_media();

        // Admin CSS
        wp_enqueue_style(
            'genseo-admin',
            GENSEO_PLUGIN_URL . 'admin/css/genseo-admin.css',
            array(),
            GENSEO_VERSION
        );

        // Inline script cho media uploader
        wp_add_inline_script('jquery', self::get_media_uploader_script());
    }

    /**
     * Get media uploader script
     *
     * @return string
     */
    private static function get_media_uploader_script() {
        return "
        jQuery(document).ready(function($) {
            // Upload button
            $('.genseo-upload-button').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                var frame = wp.media({
                    title: 'Chọn ảnh',
                    multiple: false,
                    library: { type: 'image' }
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#' + target).val(attachment.id);
                    $('#' + target + '_preview').html('<img src=\"' + attachment.url + '\" style=\"max-width:200px;height:auto;\">');
                });
                
                frame.open();
            });
            
            // Remove button
            $('.genseo-remove-button').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                $('#' + target).val('');
                $('#' + target + '_preview').html('');
                $(this).hide();
            });
        });
        ";
    }

    /**
     * Thêm action links trong plugin list
     *
     * @param array $links Existing links
     * @return array
     */
    public static function add_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=genseo-settings'),
            __('Cài đặt', 'genseo-seo-helper')
        );

        array_unshift($links, $settings_link);
        return $links;
    }
}
