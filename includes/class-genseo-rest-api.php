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

        // GET /genseo/v1/posts/rewrite-candidates - Bài viết cần viết lại
        register_rest_route(self::NAMESPACE, '/posts/rewrite-candidates', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_rewrite_candidates'),
            'permission_callback' => array(__CLASS__, 'check_read_permission'),
            'args'                => self::get_rewrite_candidates_args(),
        ));

        // POST /genseo/v1/posts/{id}/rewrite - Apply viết lại bài
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)/rewrite', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'apply_rewrite'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));

        // POST /genseo/v1/posts/{id}/rewrite/revert - Hoàn tác viết lại
        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)/rewrite/revert', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'revert_rewrite'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
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

    /**
     * Format post cho rewrite candidates (mở rộng với word count, rewrite markers, inline images)
     *
     * @param WP_Post $post Post object
     * @return array
     */
    private static function format_rewrite_post($post) {
        $data = self::format_post($post, true);

        // Word count
        $content_text = wp_strip_all_tags($post->post_content);
        $data['word_count'] = str_word_count($content_text);

        // Đếm ảnh inline trong content
        preg_match_all('/<img[^>]+>/i', $post->post_content, $img_matches);
        $data['inline_images_count'] = count($img_matches[0]);

        // Featured image ID
        $data['featured_image_id'] = (int) get_post_thumbnail_id($post->ID);

        // Category names
        $categories = wp_get_post_categories($post->ID, array('fields' => 'all'));
        $data['categories'] = array_map(function($cat) {
            return array('id' => $cat->term_id, 'name' => $cat->name, 'slug' => $cat->slug);
        }, $categories);

        // Tag names
        $tags = wp_get_post_tags($post->ID);
        $data['tags'] = array_map(function($tag) {
            return array('id' => $tag->term_id, 'name' => $tag->name, 'slug' => $tag->slug);
        }, $tags ?: array());

        // Rewrite tracking markers
        $data['rewrite_info'] = array(
            'rewritten_at'      => get_post_meta($post->ID, '_genseo_rewritten_at', true),
            'rewrite_version'   => (int) get_post_meta($post->ID, '_genseo_rewrite_version', true),
            'rewrite_source'    => get_post_meta($post->ID, '_genseo_rewrite_source', true),
            'original_word_count' => (int) get_post_meta($post->ID, '_genseo_original_word_count', true),
        );

        // SEO meta từ Yoast hoặc RankMath (nếu có)
        $seo_plugin = genseo_detect_seo_plugin();
        if ($seo_plugin['detected']) {
            if ($seo_plugin['type'] === 'rankmath') {
                $data['seo_plugin_meta'] = array(
                    'title'       => get_post_meta($post->ID, 'rank_math_title', true),
                    'description' => get_post_meta($post->ID, 'rank_math_description', true),
                    'focus_keyword' => get_post_meta($post->ID, 'rank_math_focus_keyword', true),
                );
            } elseif ($seo_plugin['type'] === 'yoast') {
                $data['seo_plugin_meta'] = array(
                    'title'       => get_post_meta($post->ID, '_yoast_wpseo_title', true),
                    'description' => get_post_meta($post->ID, '_yoast_wpseo_metadesc', true),
                    'focus_keyword' => get_post_meta($post->ID, '_yoast_wpseo_focuskw', true),
                );
            }
        }

        return $data;
    }

    /**
     * Arguments cho GET /posts/rewrite-candidates
     *
     * @return array
     */
    private static function get_rewrite_candidates_args() {
        return array(
            'per_page' => array(
                'default'           => 50,
                'sanitize_callback' => 'absint',
                'validate_callback' => function($param) {
                    return $param >= 1 && $param <= 100;
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
            'date_after' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => 'Chỉ lấy bài đăng sau ngày này (YYYY-MM-DD)',
            ),
            'date_before' => array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => 'Chỉ lấy bài đăng trước ngày này (YYYY-MM-DD)',
            ),
            'min_age_days' => array(
                'default'           => 0,
                'sanitize_callback' => 'absint',
                'description'       => 'Chỉ lấy bài đăng ít nhất X ngày trước',
            ),
            'exclude_recently_rewritten' => array(
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'description'       => 'Loại trừ bài đã viết lại trong 30 ngày gần đây',
            ),
            'orderby' => array(
                'default'           => 'date',
                'sanitize_callback' => 'sanitize_text_field',
                'enum'              => array('date', 'modified', 'title', 'ID'),
            ),
            'order' => array(
                'default'           => 'ASC',
                'sanitize_callback' => 'sanitize_text_field',
                'enum'              => array('ASC', 'DESC'),
            ),
        );
    }

    /**
     * GET /posts/rewrite-candidates - Danh sách bài viết cần viết lại
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public static function get_rewrite_candidates($request) {
        $per_page    = $request->get_param('per_page');
        $page        = $request->get_param('page');
        $status      = $request->get_param('status');
        $search      = $request->get_param('search');
        $category    = $request->get_param('category');
        $date_after  = $request->get_param('date_after');
        $date_before = $request->get_param('date_before');
        $min_age_days = $request->get_param('min_age_days');
        $exclude_recently_rewritten = $request->get_param('exclude_recently_rewritten');
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

        // Date range filters
        $date_query = array();
        if (!empty($date_after)) {
            $date_query[] = array(
                'after'     => sanitize_text_field($date_after),
                'inclusive' => true,
            );
        }
        if (!empty($date_before)) {
            $date_query[] = array(
                'before'    => sanitize_text_field($date_before),
                'inclusive' => true,
            );
        }
        if ($min_age_days > 0) {
            $date_query[] = array(
                'before' => $min_age_days . ' days ago',
                'inclusive' => true,
            );
        }
        if (!empty($date_query)) {
            $args['date_query'] = $date_query;
        }

        // Loại trừ bài đã viết lại gần đây
        if ($exclude_recently_rewritten) {
            $meta_query = array();
            $meta_query['relation'] = 'OR';
            $meta_query[] = array(
                'key'     => '_genseo_rewritten_at',
                'compare' => 'NOT EXISTS',
            );
            $meta_query[] = array(
                'key'     => '_genseo_rewritten_at',
                'value'   => '',
                'compare' => '=',
            );
            $meta_query[] = array(
                'key'     => '_genseo_rewritten_at',
                'value'   => gmdate('Y-m-d H:i:s', strtotime('-30 days')),
                'compare' => '<',
                'type'    => 'DATETIME',
            );
            $args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($args);
        $posts = array();

        foreach ($query->posts as $post) {
            $posts[] = self::format_rewrite_post($post);
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
     * POST /posts/{id}/rewrite - Áp dụng viết lại bài
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public static function apply_rewrite($request) {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            return new WP_Error(
                'not_found',
                __('Không tìm thấy bài viết.', 'genseo-seo-helper'),
                array('status' => 404)
            );
        }

        // Kiểm tra quyền sửa bài cụ thể
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('Bạn không có quyền sửa bài viết này.', 'genseo-seo-helper'),
                array('status' => 403)
            );
        }

        $body = $request->get_json_params();

        // Lưu word count gốc trước khi viết lại
        $original_content = wp_strip_all_tags($post->post_content);
        $original_word_count = str_word_count($original_content);
        $current_version = (int) get_post_meta($post_id, '_genseo_rewrite_version', true);

        // Lưu nội dung gốc vào post meta (chỉ lần rewrite đầu tiên)
        add_post_meta($post_id, '_genseo_original_title', $post->post_title, true);
        add_post_meta($post_id, '_genseo_original_content', $post->post_content, true);
        add_post_meta($post_id, '_genseo_original_excerpt', $post->post_excerpt, true);

        // Lưu meta desc gốc (Yoast / RankMath / GenSeo)
        $seo_plugin_info = genseo_detect_seo_plugin();
        $original_meta_desc = '';
        if ($seo_plugin_info['detected']) {
            if ($seo_plugin_info['type'] === 'yoast') {
                $original_meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            } elseif ($seo_plugin_info['type'] === 'rankmath') {
                $original_meta_desc = get_post_meta($post_id, 'rank_math_description', true);
            }
        }
        if (empty($original_meta_desc)) {
            $original_meta_desc = get_post_meta($post_id, '_genseo_meta_desc', true);
        }
        add_post_meta($post_id, '_genseo_original_meta_desc', $original_meta_desc, true);

        // Lưu featured image gốc
        $original_thumbnail_id = get_post_thumbnail_id($post_id);
        if ($original_thumbnail_id) {
            add_post_meta($post_id, '_genseo_original_thumbnail_id', $original_thumbnail_id, true);
        }

        // Chuẩn bị update data
        $update_data = array('ID' => $post_id);

        if (isset($body['title'])) {
            $update_data['post_title'] = sanitize_text_field($body['title']);
        }
        if (isset($body['content'])) {
            $update_data['post_content'] = wp_kses_post($body['content']);
        }
        if (isset($body['excerpt'])) {
            $update_data['post_excerpt'] = sanitize_textarea_field($body['excerpt']);
        }

        // Cập nhật ngày xuất bản nếu yêu cầu
        if (!empty($body['update_date']) && $body['update_date'] === true) {
            $update_data['post_date']     = current_time('mysql');
            $update_data['post_date_gmt'] = current_time('mysql', true);
        }

        // Cập nhật bài viết (WordPress tự tạo revision)
        $result = wp_update_post($update_data, true);

        if (is_wp_error($result)) {
            return new WP_Error(
                'update_failed',
                $result->get_error_message(),
                array('status' => 500)
            );
        }

        // Cập nhật meta description (GenSeo)
        if (isset($body['meta_description'])) {
            update_post_meta($post_id, '_genseo_meta_desc', sanitize_textarea_field($body['meta_description']));
        }

        // Cập nhật SEO title (GenSeo)
        if (isset($body['seo_title'])) {
            update_post_meta($post_id, '_genseo_seo_title', sanitize_text_field($body['seo_title']));
        }

        // Cập nhật focus keyword
        if (isset($body['focus_keyword'])) {
            update_post_meta($post_id, '_genseo_focus_keyword', sanitize_text_field($body['focus_keyword']));
        }

        // Sync với Yoast/RankMath nếu có
        $seo_plugin = genseo_detect_seo_plugin();
        if ($seo_plugin['detected']) {
            if ($seo_plugin['type'] === 'rankmath') {
                if (isset($body['seo_title'])) {
                    update_post_meta($post_id, 'rank_math_title', sanitize_text_field($body['seo_title']));
                }
                if (isset($body['meta_description'])) {
                    update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($body['meta_description']));
                }
                if (isset($body['focus_keyword'])) {
                    update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($body['focus_keyword']));
                }
            } elseif ($seo_plugin['type'] === 'yoast') {
                if (isset($body['seo_title'])) {
                    update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($body['seo_title']));
                }
                if (isset($body['meta_description'])) {
                    update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($body['meta_description']));
                }
                if (isset($body['focus_keyword'])) {
                    update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($body['focus_keyword']));
                }
            }
        }

        // Upload + set featured image mới nếu có
        $new_thumbnail_id = null;
        if (!empty($body['thumbnail'])) {
            // Base64 data URL từ GenSEO Desktop (VD: data:image/png;base64,...)
            $thumbnail_data = $body['thumbnail'];
            $new_thumbnail_id = self::upload_base64_image($thumbnail_data, $post_id, $body['thumbnail_alt'] ?? '');
            if ($new_thumbnail_id && !is_wp_error($new_thumbnail_id)) {
                set_post_thumbnail($post_id, $new_thumbnail_id);
            } else {
                $new_thumbnail_id = null; // Reset nếu upload thất bại
            }
        } elseif (!empty($body['featured_image_url'])) {
            $image_url = esc_url_raw($body['featured_image_url']);
            $new_thumbnail_id = self::sideload_image($image_url, $post_id);
            if ($new_thumbnail_id && !is_wp_error($new_thumbnail_id)) {
                set_post_thumbnail($post_id, $new_thumbnail_id);
            }
        } elseif (!empty($body['featured_image_id'])) {
            $media_id = absint($body['featured_image_id']);
            if (get_post($media_id) && get_post($media_id)->post_type === 'attachment') {
                set_post_thumbnail($post_id, $media_id);
                $new_thumbnail_id = $media_id;
            }
        }

        // Cập nhật rewrite tracking meta
        update_post_meta($post_id, '_genseo_rewritten_at', current_time('mysql', true));
        update_post_meta($post_id, '_genseo_rewrite_version', $current_version + 1);
        update_post_meta($post_id, '_genseo_rewrite_source', 'genseo-rewriter');
        update_post_meta($post_id, '_genseo_original_word_count', $original_word_count);

        // Cập nhật word count mới
        $new_content = isset($body['content']) ? wp_strip_all_tags($body['content']) : $original_content;
        $new_word_count = str_word_count($new_content);
        update_post_meta($post_id, '_genseo_word_count', $new_word_count);

        // Lấy post mới nhất sau update
        $updated_post = get_post($post_id);

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Đã viết lại bài viết thành công.',
            'data'    => array(
                'id'                  => $post_id,
                'title'               => $updated_post->post_title,
                'url'                 => get_permalink($post_id),
                'rewrite_version'     => $current_version + 1,
                'original_word_count' => $original_word_count,
                'new_word_count'      => $new_word_count,
                'new_thumbnail_id'    => $new_thumbnail_id,
                'featured_image'      => get_the_post_thumbnail_url($post_id, 'full'),
                'modified'            => $updated_post->post_modified,
            ),
        ));
    }

    /**
     * Hoàn tác viết lại bài viết - khôi phục nội dung gốc
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public static function revert_rewrite($request) {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            return new WP_Error(
                'not_found',
                __('Không tìm thấy bài viết.', 'genseo-seo-helper'),
                array('status' => 404)
            );
        }

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('Bạn không có quyền sửa bài viết này.', 'genseo-seo-helper'),
                array('status' => 403)
            );
        }

        // Kiểm tra có bản gốc không
        $original_title   = get_post_meta($post_id, '_genseo_original_title', true);
        $original_content = get_post_meta($post_id, '_genseo_original_content', true);

        if (empty($original_title) && empty($original_content)) {
            return new WP_Error(
                'no_original',
                __('Không tìm thấy nội dung gốc để hoàn tác.', 'genseo-seo-helper'),
                array('status' => 400)
            );
        }

        $original_excerpt  = get_post_meta($post_id, '_genseo_original_excerpt', true);
        $original_meta_desc = get_post_meta($post_id, '_genseo_original_meta_desc', true);
        $original_thumbnail_id = get_post_meta($post_id, '_genseo_original_thumbnail_id', true);

        // Khôi phục bài viết
        $update_data = array('ID' => $post_id);
        if (!empty($original_title)) {
            $update_data['post_title'] = $original_title;
        }
        if (!empty($original_content)) {
            $update_data['post_content'] = $original_content;
        }
        if ($original_excerpt !== false) {
            $update_data['post_excerpt'] = $original_excerpt;
        }

        $result = wp_update_post($update_data, true);

        if (is_wp_error($result)) {
            return new WP_Error(
                'revert_failed',
                $result->get_error_message(),
                array('status' => 500)
            );
        }

        // Khôi phục meta description
        if (!empty($original_meta_desc)) {
            update_post_meta($post_id, '_genseo_meta_desc', $original_meta_desc);

            $seo_plugin = genseo_detect_seo_plugin();
            if ($seo_plugin['detected']) {
                if ($seo_plugin['type'] === 'yoast') {
                    update_post_meta($post_id, '_yoast_wpseo_metadesc', $original_meta_desc);
                } elseif ($seo_plugin['type'] === 'rankmath') {
                    update_post_meta($post_id, 'rank_math_description', $original_meta_desc);
                }
            }
        }

        // Khôi phục featured image
        if (!empty($original_thumbnail_id)) {
            set_post_thumbnail($post_id, (int) $original_thumbnail_id);
        }

        // Giảm rewrite version
        $current_version = (int) get_post_meta($post_id, '_genseo_rewrite_version', true);
        if ($current_version > 0) {
            update_post_meta($post_id, '_genseo_rewrite_version', $current_version - 1);
        }

        // Xóa backup meta (cho phép lưu lại nếu rewrite lần sau)
        delete_post_meta($post_id, '_genseo_original_title');
        delete_post_meta($post_id, '_genseo_original_content');
        delete_post_meta($post_id, '_genseo_original_excerpt');
        delete_post_meta($post_id, '_genseo_original_meta_desc');
        delete_post_meta($post_id, '_genseo_original_thumbnail_id');

        // Cập nhật word count
        $reverted_post = get_post($post_id);
        $reverted_content = wp_strip_all_tags($reverted_post->post_content);
        $reverted_word_count = str_word_count($reverted_content);
        update_post_meta($post_id, '_genseo_word_count', $reverted_word_count);

        // Ghi nhận thời gian revert
        update_post_meta($post_id, '_genseo_reverted_at', current_time('mysql', true));
        delete_post_meta($post_id, '_genseo_rewritten_at');

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Đã hoàn tác bài viết về nội dung gốc.',
            'data'    => array(
                'id'              => $post_id,
                'title'           => $reverted_post->post_title,
                'url'             => get_permalink($post_id),
                'rewrite_version' => max(0, $current_version - 1),
                'word_count'      => $reverted_word_count,
            ),
        ));
    }

    /**
     * Sideload ảnh từ URL vào WordPress Media Library
     *
     * @param string $image_url URL ảnh
     * @param int    $post_id   Post ID để attach
     * @return int|WP_Error Media attachment ID hoặc error
     */
    private static function sideload_image($image_url, $post_id) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download file tạm
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        // Lấy filename từ URL
        $url_path = wp_parse_url($image_url, PHP_URL_PATH);
        $filename = basename($url_path);
        if (empty($filename) || !preg_match('/\.(jpe?g|png|gif|webp|avif)$/i', $filename)) {
            $filename = 'rewrite-image-' . $post_id . '-' . time() . '.jpg';
        }

        $file_array = array(
            'name'     => sanitize_file_name($filename),
            'tmp_name' => $tmp,
        );

        // Sideload vào media library
        $attachment_id = media_handle_sideload($file_array, $post_id);

        // Xóa file tạm nếu sideload fail
        if (is_wp_error($attachment_id)) {
            @unlink($file_array['tmp_name']);
        }

        return $attachment_id;
    }

    /**
     * Upload ảnh từ base64 data URL vào media library
     *
     * @param string $base64_data Base64 data URL (VD: data:image/png;base64,iVBOR...)
     * @param int    $post_id     Post ID để attach
     * @param string $alt_text    Alt text cho ảnh
     * @return int|WP_Error Attachment ID hoặc WP_Error
     */
    private static function upload_base64_image($base64_data, $post_id, $alt_text = '') {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Parse data URL: data:image/png;base64,iVBOR...
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/s', $base64_data, $matches)) {
            $extension = $matches[1];
            $raw_data  = base64_decode($matches[2]);
        } else {
            // Không có prefix → thử decode trực tiếp
            $raw_data  = base64_decode($base64_data);
            $extension = 'png';
        }

        if ($raw_data === false || strlen($raw_data) < 100) {
            return new WP_Error('invalid_image', 'Dữ liệu ảnh base64 không hợp lệ');
        }

        // Giới hạn kích thước (10MB)
        if (strlen($raw_data) > 10 * 1024 * 1024) {
            return new WP_Error('image_too_large', 'Ảnh vượt quá 10MB');
        }

        // Map extension
        $ext_map = array('jpeg' => 'jpg', 'jpg' => 'jpg', 'png' => 'png', 'gif' => 'gif', 'webp' => 'webp');
        $ext = isset($ext_map[$extension]) ? $ext_map[$extension] : 'png';

        // Tạo file tạm
        $filename = 'rewrite-thumbnail-' . $post_id . '-' . time() . '.' . $ext;
        $tmp_file = wp_tempnam($filename);

        // Ghi ra file
        $written = file_put_contents($tmp_file, $raw_data);
        if ($written === false) {
            @unlink($tmp_file);
            return new WP_Error('write_failed', 'Không thể ghi file tạm');
        }

        // Kiểm tra MIME type thực sự (bảo mật)
        $file_info = wp_check_filetype_and_ext($tmp_file, $filename);
        if (!$file_info['type'] || strpos($file_info['type'], 'image/') !== 0) {
            @unlink($tmp_file);
            return new WP_Error('invalid_type', 'File không phải ảnh hợp lệ');
        }

        $file_array = array(
            'name'     => sanitize_file_name($filename),
            'tmp_name' => $tmp_file,
        );

        // Upload vào media library
        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp_file);
            return $attachment_id;
        }

        // Set alt text nếu có
        if (!empty($alt_text)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
        }

        return $attachment_id;
    }
}
