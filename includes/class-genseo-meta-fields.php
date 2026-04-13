<?php
/**
 * Meta Fields - Register custom meta cho REST API
 *
 * @package GenSeo_SEO_Helper
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_Meta_Fields
 *
 * Đăng ký custom post meta để nhận data từ GenSeo Desktop qua REST API
 */
class GenSeo_Meta_Fields {

    /**
     * Danh sách meta fields cần register
     *
     * @var array
     */
    private static $meta_fields = array(
        // SEO cơ bản
        '_genseo_seo_title' => array(
            'type'        => 'string',
            'description' => 'SEO Title cho bài viết (50-60 ký tự)',
            'default'     => '',
            'sanitize'    => 'sanitize_text_field',
        ),
        '_genseo_meta_desc' => array(
            'type'        => 'string',
            'description' => 'Meta Description (150-160 ký tự)',
            'default'     => '',
            'sanitize'    => 'sanitize_textarea_field',
        ),
        '_genseo_focus_keyword' => array(
            'type'        => 'string',
            'description' => 'Focus keyword chính',
            'default'     => '',
            'sanitize'    => 'sanitize_text_field',
        ),
        '_genseo_secondary_keywords' => array(
            'type'        => 'array',
            'description' => 'Danh sách keywords phụ',
            'default'     => array(),
            'sanitize'    => 'genseo_sanitize_array',
        ),

        // OpenGraph
        '_genseo_og_image' => array(
            'type'        => 'string',
            'description' => 'Custom OpenGraph image URL',
            'default'     => '',
            'sanitize'    => 'esc_url_raw',
        ),
        '_genseo_og_image_id' => array(
            'type'        => 'integer',
            'description' => 'WordPress Media ID cho OG image',
            'default'     => 0,
            'sanitize'    => 'absint',
        ),

        // Schema
        '_genseo_schema_type' => array(
            'type'        => 'string',
            'description' => 'Schema.org type: Article, HowTo, FAQ',
            'default'     => 'Article',
            'sanitize'    => 'sanitize_text_field',
        ),

        // Technical SEO
        '_genseo_canonical_url' => array(
            'type'        => 'string',
            'description' => 'Custom canonical URL',
            'default'     => '',
            'sanitize'    => 'esc_url_raw',
        ),
        '_genseo_robots' => array(
            'type'        => 'string',
            'description' => 'Robots meta: index, noindex, nofollow',
            'default'     => 'index',
            'sanitize'    => 'sanitize_text_field',
        ),

        // Source tracking
        '_genseo_source' => array(
            'type'        => 'string',
            'description' => 'Nguồn: genseo-desktop',
            'default'     => '',
            'sanitize'    => 'sanitize_text_field',
        ),
        '_genseo_source_video' => array(
            'type'        => 'string',
            'description' => 'YouTube video URL gốc',
            'default'     => '',
            'sanitize'    => 'esc_url_raw',
        ),
        '_genseo_search_intent' => array(
            'type'        => 'string',
            'description' => 'Search intent: informational, transactional, navigational, commercial',
            'default'     => '',
            'sanitize'    => 'sanitize_text_field',
        ),

        // Analytics
        '_genseo_word_count' => array(
            'type'        => 'integer',
            'description' => 'Số từ trong bài viết',
            'default'     => 0,
            'sanitize'    => 'absint',
        ),
        '_genseo_reading_time' => array(
            'type'        => 'integer',
            'description' => 'Thời gian đọc ước tính (phút)',
            'default'     => 0,
            'sanitize'    => 'absint',
        ),
        '_genseo_published_at' => array(
            'type'        => 'string',
            'description' => 'Timestamp từ Desktop app',
            'default'     => '',
            'sanitize'    => 'sanitize_text_field',
        ),

        // KW Plan tracking
        '_genseo_kwplan_id' => array(
            'type'        => 'string',
            'description' => 'KW Plan ID trong Desktop app',
            'default'     => '',
            'sanitize'    => 'sanitize_text_field',
        ),
        '_genseo_kwplan_item_id' => array(
            'type'        => 'string',
            'description' => 'KW Plan Item ID',
            'default'     => '',
            'sanitize'    => 'sanitize_text_field',
        ),

        // Rewrite tracking
        '_genseo_rewritten_at' => array(
            'type'        => 'string',
            'description' => 'Timestamp bài được viết lại lần cuối',
            'default'     => '',
            'sanitize'    => 'sanitize_text_field',
        ),
        '_genseo_rewrite_version' => array(
            'type'        => 'integer',
            'description' => 'Số lần bài đã được viết lại',
            'default'     => 0,
            'sanitize'    => 'absint',
        ),
        '_genseo_rewrite_source' => array(
            'type'        => 'string',
            'description' => 'Nguồn viết lại: genseo-rewriter',
            'default'     => '',
            'sanitize'    => 'sanitize_text_field',
        ),
        '_genseo_original_word_count' => array(
            'type'        => 'integer',
            'description' => 'Số từ bài gốc trước khi viết lại',
            'default'     => 0,
            'sanitize'    => 'absint',
        ),
    );

    /**
     * Khởi tạo class
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_meta_fields'));
        add_action('rest_api_init', array(__CLASS__, 'register_meta_fields'));
    }

    /**
     * Đăng ký meta fields
     */
    public static function register_meta_fields() {
        foreach (self::$meta_fields as $meta_key => $config) {
            $args = array(
                'type'              => $config['type'],
                'description'       => $config['description'],
                'single'            => true,
                'default'           => $config['default'],
                'show_in_rest'      => true,
                'auth_callback'     => array(__CLASS__, 'auth_callback'),
                'sanitize_callback' => $config['sanitize'],
            );

            // Xử lý array type
            if ($config['type'] === 'array') {
                $args['show_in_rest'] = array(
                    'schema' => array(
                        'type'  => 'array',
                        'items' => array('type' => 'string'),
                    ),
                );
            }

            register_post_meta('post', $meta_key, $args);
        }
    }

    /**
     * Callback xác thực quyền chỉnh sửa meta
     *
     * @param bool   $allowed   Có được phép không
     * @param string $meta_key  Meta key
     * @param int    $post_id   Post ID
     * @return bool
     */
    public static function auth_callback($allowed, $meta_key, $post_id) {
        return current_user_can('edit_post', $post_id);
    }

    /**
     * Lấy tất cả meta GenSeo của post
     *
     * @param int $post_id Post ID
     * @return array
     */
    public static function get_all_meta($post_id) {
        $meta = array();

        foreach (self::$meta_fields as $meta_key => $config) {
            $value = get_post_meta($post_id, $meta_key, true);
            // Bỏ prefix _genseo_ cho clean key
            $clean_key = str_replace('_genseo_', '', $meta_key);
            $meta[$clean_key] = !empty($value) ? $value : $config['default'];
        }

        return $meta;
    }

    /**
     * Lấy danh sách meta keys
     *
     * @return array
     */
    public static function get_meta_keys() {
        return array_keys(self::$meta_fields);
    }
}

/**
 * Sanitize array of strings
 *
 * @param mixed $value Value to sanitize
 * @return array
 */
function genseo_sanitize_array($value) {
    if (!is_array($value)) {
        // Nếu là string comma-separated
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        } else {
            return array();
        }
    }

    return array_map('sanitize_text_field', $value);
}
