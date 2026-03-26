<?php
/**
 * REST API - Custom endpoints cho GenSeo Desktop
 *
 * @package GenSeo_SEO_Helper
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_REST_API
 *
 * Đăng ký custom REST API endpoints
 */
class GenSeo_REST_API {

    /**
     * Namespace cho REST API
     */
    const NAMESPACE = 'genseo/v1';

    /**
     * Khởi tạo class
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /**
     * Đăng ký các routes
     */
    public static function register_routes() {
        // GET /genseo/v1/info - Thông tin site
        register_rest_route(self::NAMESPACE, '/info', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_site_info'),
            'permission_callback' => array(__CLASS__, 'check_read_permission'),
        ));

        // GET /genseo/v1/health - Health check
        register_rest_route(self::NAMESPACE, '/health', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_health'),
            'permission_callback' => '__return_true', // Public endpoint
        ));

        // GET /genseo/v1/posts - Danh sách posts
        register_rest_route(self::NAMESPACE, '/posts', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_posts'),
            'permission_callback' => array(__CLASS__, 'check_read_permission'),
            'args'                => self::get_posts_args(),
        ));

        // GET /genseo/v1/posts/{id} - Chi tiết post
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_post'),
            'permission_callback' => array(__CLASS__, 'check_read_permission'),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
    }

    /**
     * Kiểm tra quyền đọc
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public static function check_read_permission($request) {
        if (!current_user_can('read')) {
            return new WP_Error(
                'rest_forbidden',
                __('Bạn không có quyền truy cập.', 'genseo-seo-helper'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * Kiểm tra quyền chỉnh sửa
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public static function check_edit_permission($request) {
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                __('Bạn không có quyền chỉnh sửa.', 'genseo-seo-helper'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * GET /info - Thông tin site đầy đủ
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public static function get_site_info($request) {
        $current_user = wp_get_current_user();

        // Lấy categories
        $categories = get_categories(array(
            'hide_empty' => false,
            'orderby'    => 'name',
        ));
        $categories_data = array_map(function($cat) {
            return array(
                'id'     => $cat->term_id,
                'name'   => $cat->name,
                'slug'   => $cat->slug,
                'parent' => $cat->parent,
                'count'  => $cat->count,
            );
        }, $categories);

        // Lấy tags
        $tags = get_tags(array(
            'hide_empty' => false,
            'number'     => 100,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ));
        $tags_data = array_map(function($tag) {
            return array(
                'id'    => $tag->term_id,
                'name'  => $tag->name,
                'slug'  => $tag->slug,
                'count' => $tag->count,
            );
        }, $tags ?: array());

        // User capabilities
        $capabilities = array(
            'publish_posts' => current_user_can('publish_posts'),
            'upload_files'  => current_user_can('upload_files'),
            'edit_posts'    => current_user_can('edit_posts'),
            'delete_posts'  => current_user_can('delete_posts'),
        );

        // Max upload size
        $max_upload = wp_max_upload_size();
        $allowed_types = get_allowed_mime_types();
        $image_types = array_filter($allowed_types, function($mime) {
            return strpos($mime, 'image/') === 0;
        });

        $response = array(
            'success' => true,
            'data'    => array(
                'plugin_version'     => GENSEO_VERSION,
                'site_name'          => get_bloginfo('name'),
                'site_url'           => get_site_url(),
                'site_language'      => get_bloginfo('language'),
                'timezone'           => wp_timezone_string(),
                'gmt_offset'         => get_option('gmt_offset'),
                'permalink_structure' => get_option('permalink_structure'),
                'categories'         => $categories_data,
                'tags'               => $tags_data,
                'seo_plugin'         => genseo_detect_seo_plugin(),
                'user'               => array(
                    'id'           => $current_user->ID,
                    'login'        => $current_user->user_login,
                    'display_name' => $current_user->display_name,
                    'email'        => $current_user->user_email,
                    'capabilities' => $capabilities,
                ),
                'media'              => array(
                    'max_upload_size'       => $max_upload,
                    'max_upload_size_human' => size_format($max_upload),
                    'allowed_types'         => array_keys($image_types),
                ),
            ),
        );

        return rest_ensure_response($response);
    }

    /**
     * GET /health - Health check
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public static function get_health($request) {
        global $wp_version;

        // Kiểm tra meta fields đã register chưa
        // WP 6.x: get_registered_meta_keys(object_type, object_subtype)
        $registered_meta = get_registered_meta_keys('post', 'post');
        $meta_registered = isset($registered_meta['_genseo_source']);

        // Kiểm tra SEO sync có bật không
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
                'mcp_diagnostic'    => function_exists('genseo_get_mcp_diagnostic')
                    ? genseo_get_mcp_diagnostic()
                    : null,
            ),
        );

        return rest_ensure_response($response);
    }

    /**
     * Arguments cho GET /posts
     *
     * @return array
     */
    private static function get_posts_args() {
        return array(
            'per_page' => array(
                'default'           => 100,
                'sanitize_callback' => 'absint',
                'validate_callback' => function($param) {
                    return $param >= 1 && $param <= 500;
                },
            ),
            'page' => array(
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'status' => array(
                'default'           => 'publish',
                'sanitize_callback' => 'sanitize_text_field',
                'enum'              => array('publish', 'draft', 'pending', 'private', 'any'),
            ),
            'search' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'category' => array(
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ),
            'genseo_only' => array(
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ),
            'orderby' => array(
                'default'           => 'date',
                'sanitize_callback' => 'sanitize_text_field',
                'enum'              => array('date', 'modified', 'title', 'ID'),
            ),
            'order' => array(
                'default'           => 'DESC',
                'sanitize_callback' => 'sanitize_text_field',
                'enum'              => array('ASC', 'DESC'),
            ),
        );
    }

    /**
     * GET /posts - Danh sách posts
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public static function get_posts($request) {
        $per_page    = $request->get_param('per_page');
        $page        = $request->get_param('page');
        $status      = $request->get_param('status');
        $search      = $request->get_param('search');
        $category    = $request->get_param('category');
        $genseo_only = $request->get_param('genseo_only');
        $orderby     = $request->get_param('orderby');
        $order       = $request->get_param('order');

        $args = array(
            'post_type'      => 'post',
            'post_status'    => $status === 'any' ? array('publish', 'draft', 'pending', 'private') : $status,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => $orderby,
            'order'          => $order,
        );

        // Search
        if (!empty($search)) {
            $args['s'] = $search;
        }

        // Category filter
        if ($category > 0) {
            $args['cat'] = $category;
        }

        // Chỉ lấy posts từ GenSeo
        if ($genseo_only) {
            $args['meta_query'] = array(
                array(
                    'key'     => '_genseo_source',
                    'value'   => 'genseo-desktop',
                    'compare' => '=',
                ),
            );
        }

        $query = new WP_Query($args);
        $posts = array();

        foreach ($query->posts as $post) {
            $posts[] = self::format_post($post);
        }

        $response = array(
            'success' => true,
            'data'    => array(
                'posts'       => $posts,
                'total'       => (int) $query->found_posts,
                'total_pages' => (int) $query->max_num_pages,
                'page'        => $page,
                'per_page'    => $per_page,
            ),
        );

        return rest_ensure_response($response);
    }

    /**
     * GET /posts/{id} - Chi tiết một post
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public static function get_post($request) {
        $post_id = $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            return new WP_Error(
                'not_found',
                __('Không tìm thấy bài viết.', 'genseo-seo-helper'),
                array('status' => 404)
            );
        }

        $response = array(
            'success' => true,
            'data'    => self::format_post($post, true),
        );

        return rest_ensure_response($response);
    }

    /**
     * Format post data
     *
     * @param WP_Post $post      Post object
     * @param bool    $full_meta Có lấy full meta không
     * @return array
     */
    private static function format_post($post, $full_meta = false) {
        $data = array(
            'id'            => $post->ID,
            'title'         => $post->post_title,
            'slug'          => $post->post_name,
            'url'           => get_permalink($post->ID),
            'status'        => $post->post_status,
            'date'          => $post->post_date,
            'date_gmt'      => $post->post_date_gmt,
            'modified'      => $post->post_modified,
            'modified_gmt'  => $post->post_modified_gmt,
            'author_id'     => (int) $post->post_author,
            'category_ids'  => wp_get_post_categories($post->ID),
            'tag_ids'       => wp_get_post_tags($post->ID, array('fields' => 'ids')),
            'featured_image' => get_the_post_thumbnail_url($post->ID, 'full'),
        );

        // GenSeo meta cơ bản
        $data['genseo_meta'] = array(
            'source'        => get_post_meta($post->ID, '_genseo_source', true),
            'focus_keyword' => get_post_meta($post->ID, '_genseo_focus_keyword', true),
            'source_video'  => get_post_meta($post->ID, '_genseo_source_video', true),
        );

        // Full meta nếu yêu cầu
        if ($full_meta) {
            $data['genseo_meta'] = GenSeo_Meta_Fields::get_all_meta($post->ID);
            $data['excerpt'] = $post->post_excerpt;
            $data['content'] = $post->post_content;
        }

        return $data;
    }
}
