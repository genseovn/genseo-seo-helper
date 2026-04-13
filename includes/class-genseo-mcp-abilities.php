<?php
/**
 * MCP Abilities - Đăng ký WordPress Abilities cho GenSeo Desktop qua MCP Adapter
 *
 * Yêu cầu: MCP Adapter plugin (cung cấp wp_register_ability())
 * Nếu MCP Adapter chưa cài, class này sẽ không làm gì cả.
 *
 * @package GenSeo_SEO_Helper
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_MCP_Abilities
 *
 * Đăng ký 13 MCP Abilities cho GenSeo Desktop:
 * - 6 SEO abilities (get/update meta, bulk, internal links, summary)
 * - 7 abilities bổ sung (CRUD posts, meta, site info)
 */
class GenSeo_MCP_Abilities {

    /**
     * Current API version for MCP abilities.
     * Bump major on breaking changes, minor on new abilities.
     */
    const API_VERSION = '1.0';

    /**
     * Minimum client API version supported by this server.
     */
    const MIN_CLIENT_VERSION = '1.0';

    // ============================================================
    // KHỞI TẠO
    // ============================================================

    /**
     * Khởi tạo class - hook vào wp_abilities_api_categories_init và wp_abilities_api_init
     * Chỉ gọi nếu wp_register_ability() tồn tại (Abilities API active)
     */
    public static function init() {
        // Đăng ký category TRƯỚC (categories_init fire trước abilities_init)
        add_action('wp_abilities_api_categories_init', array(__CLASS__, 'register_categories'));
        // Đăng ký abilities SAU khi category đã sẵn sàng
        add_action('wp_abilities_api_init', array(__CLASS__, 'register_abilities'));
    }

    /**
     * Đăng ký ability categories cho GenSeo
     * Hook: wp_abilities_api_categories_init (fire trước wp_abilities_api_init)
     */
    public static function register_categories() {
        wp_register_ability_category('genseo', array(
            'label'       => 'GenSeo SEO Helper',
            'description' => 'Quản lý SEO cho bài viết từ GenSeo Desktop - OpenGraph, Schema, RankMath/Yoast sync',
        ));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GenSeo: Đã đăng ký ability category "genseo"');
        }
    }

    /**
     * Đăng ký tất cả MCP Abilities cho GenSeo
     * Hook: wp_abilities_api_init (fire sau wp_abilities_api_categories_init)
     */
    public static function register_abilities() {
        // === 6 SEO Abilities chính ===
        self::register_get_seo_meta();
        self::register_update_seo_meta();
        self::register_bulk_update_seo();
        self::register_get_posts_needing_optimization();
        self::register_update_internal_links();
        self::register_get_post_content_summary();
        self::register_get_batch_content_summaries();

        // === 7 Abilities bổ sung cho Desktop App ===
        self::register_get_post_field();
        self::register_get_posts();
        self::register_get_post();
        self::register_update_post();
        self::register_get_post_meta();
        self::register_update_post_meta();
        self::register_get_site_info();

        // === Phase 8: Abilities mới cho Fix Expansion ===
        self::register_create_redirect();
        self::register_update_image_alt();

        // === Post Type Expansion ===
        self::register_get_post_types();

        // === Phase 9: SEO Optimizer Upgrade (10 new abilities) ===
        // Phase 1: Redirect & Link Intelligence
        self::register_get_redirects();
        self::register_update_redirect();
        self::register_get_internal_link_graph();
        self::register_detect_broken_links();
        // Phase 2: Content Deep Analysis
        self::register_get_schema_full();
        self::register_get_images_detail();
        self::register_batch_get_links_in_content();
        // Phase 3: Sitemap, Robots & Indexation
        self::register_get_sitemap_urls();
        self::register_get_robots_txt();
        self::register_get_content_stats();

        // === Phase 10: Taxonomy & Revision SEO (Phase 4) + WordPress Site Intelligence (Phase 5) ===
        // Phase 4: Taxonomy & Revision SEO
        self::register_get_taxonomy_seo();
        self::register_get_post_revisions();
        // Phase 5: WordPress Site Intelligence
        self::register_get_active_plugins();
        self::register_get_theme_info();
        self::register_get_navigation_structure();
        self::register_get_site_health();
        self::register_analyze_permalink_structure();
        self::register_get_site_settings();

        // === API Versioning ===
        self::register_get_api_version();

        // === Phase 11: Abilities Expansion ===
        // P0: Composite + CRUD completion
        self::register_get_site_context();
        self::register_update_taxonomy_seo();
        self::register_bulk_import_redirects();
        // P1: WooCommerce SEO
        if (class_exists('WooCommerce')) {
            self::register_get_woo_products();
            self::register_update_woo_product_seo();
            self::register_bulk_update_woo_seo();
        }
        // P1: Media & Search
        self::register_get_media_items();
        self::register_bulk_update_image_alt();
        self::register_get_posts_search();
        // P2: Multilingual
        if (defined('ICL_SITEPRESS_VERSION') || function_exists('pll_languages_list')) {
            self::register_get_multilingual_info();
            self::register_sync_seo_across_translations();
        }
        // P2: Stats & Analysis
        self::register_get_comments_stats();
        self::register_get_orphan_pages();
        self::register_validate_schema();
        self::register_get_anchor_diversity();
        // P3: Cache Management
        self::register_purge_post_cache();

        $count = 36 + 7 + 1; // base + always-on new abilities + cache purge
        if (class_exists('WooCommerce')) $count += 3;
        if (defined('ICL_SITEPRESS_VERSION') || function_exists('pll_languages_list')) $count += 2;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GenSeo: Đã đăng ký ' . $count . ' MCP abilities');
        }
    }

    // ============================================================
    // 6 SEO ABILITIES CHÍNH
    // ============================================================

    /**
     * 1. genseo/get-seo-meta — Lấy SEO meta của bài viết
     */
    private static function register_get_seo_meta() {
        wp_register_ability('genseo/get-seo-meta', array(
            'label'       => 'Lấy SEO meta bài viết',
            'description' => 'Lấy title, description, schema, OG, robots, focus keyword của bài viết',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'ID bài viết'),
                ),
                'required' => array('post_id'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_seo_meta'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 2. genseo/update-seo-meta — Cập nhật SEO meta
     */
    private static function register_update_seo_meta() {
        wp_register_ability('genseo/update-seo-meta', array(
            'label'       => 'Cập nhật SEO meta bài viết',
            'description' => 'Cập nhật title, description, focus keyword, schema, OG, robots',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id'          => array('type' => 'integer', 'description' => 'ID bài viết'),
                    'seo_title'        => array('type' => 'string', 'description' => 'SEO Title (50-60 ký tự)'),
                    'meta_description' => array('type' => 'string', 'description' => 'Meta Description (150-160 ký tự)'),
                    'focus_keyword'    => array('type' => 'string', 'description' => 'Focus keyword chính'),
                    'schema_json'      => array('type' => 'string', 'description' => 'Schema JSON-LD string'),
                    'og_title'         => array('type' => 'string', 'description' => 'OpenGraph title'),
                    'og_description'   => array('type' => 'string', 'description' => 'OpenGraph description'),
                    'canonical_url'    => array('type' => 'string', 'description' => 'Canonical URL'),
                    'robots'           => array('type' => 'string', 'description' => 'Robots meta (index, noindex, nofollow)'),
                ),
                'required' => array('post_id'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_update_seo_meta'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 3. genseo/bulk-update-seo — Cập nhật SEO hàng loạt
     */
    private static function register_bulk_update_seo() {
        wp_register_ability('genseo/bulk-update-seo', array(
            'label'       => 'Cập nhật SEO hàng loạt',
            'description' => 'Cập nhật SEO meta cho nhiều bài viết cùng lúc',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'items' => array(
                        'type'  => 'array',
                        'items' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'post_id'          => array('type' => 'integer'),
                                'seo_title'        => array('type' => 'string'),
                                'meta_description' => array('type' => 'string'),
                                'focus_keyword'    => array('type' => 'string'),
                                'schema_json'      => array('type' => 'string'),
                                'og_title'         => array('type' => 'string'),
                                'og_description'   => array('type' => 'string'),
                                'canonical_url'    => array('type' => 'string'),
                                'robots'           => array('type' => 'string'),
                            ),
                            'required' => array('post_id'),
                        ),
                    ),
                ),
                'required' => array('items'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_bulk_update_seo'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 4. genseo/get-posts-needing-optimization — Lấy bài cần tối ưu SEO
     */
    private static function register_get_posts_needing_optimization() {
        wp_register_ability('genseo/get-posts-needing-optimization', array(
            'label'       => 'Lấy bài cần tối ưu SEO',
            'description' => 'Lấy danh sách bài viết thiếu SEO meta (title, desc, keyword)',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'limit'     => array('type' => 'integer', 'description' => 'Số bài tối đa (mặc định 50)'),
                    'offset'    => array('type' => 'integer', 'description' => 'Bỏ qua N bài đầu'),
                    'post_type' => array('type' => 'string', 'description' => 'Post type(s), phân cách bằng dấu phẩy. Mặc định: post,page'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_posts_needing_optimization'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 5. genseo/update-internal-links — Chèn internal links vào nội dung
     */
    private static function register_update_internal_links() {
        wp_register_ability('genseo/update-internal-links', array(
            'label'       => 'Cập nhật internal links',
            'description' => 'Chèn internal links vào nội dung bài viết theo vị trí chỉ định',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'ID bài viết'),
                    'links'   => array(
                        'type'  => 'array',
                        'items' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'anchor_text' => array('type' => 'string', 'description' => 'Văn bản hiển thị'),
                                'target_url'  => array('type' => 'string', 'description' => 'URL đích'),
                                'position'    => array('type' => 'string', 'description' => 'Vị trí: after_paragraph_N (VD: after_paragraph_3)'),
                            ),
                            'required' => array('anchor_text', 'target_url'),
                        ),
                    ),
                ),
                'required' => array('post_id', 'links'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_update_internal_links'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 6. genseo/get-post-content-summary — Lấy tóm tắt nội dung
     */
    private static function register_get_post_content_summary() {
        wp_register_ability('genseo/get-post-content-summary', array(
            'label'       => 'Lấy tóm tắt nội dung bài viết',
            'description' => 'Trích xuất text thuần, headings kèm cấp, ảnh (alt), links (internal/external), schema',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id'    => array('type' => 'integer', 'description' => 'ID bài viết'),
                    'max_length' => array('type' => 'integer', 'description' => 'Số ký tự tối đa cho summary (mặc định 500)'),
                ),
                'required' => array('post_id'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_post_content_summary'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 6b. genseo/get-batch-content-summaries — Lấy tóm tắt nội dung hàng loạt
     */
    private static function register_get_batch_content_summaries() {
        wp_register_ability('genseo/get-batch-content-summaries', array(
            'label'       => 'Lấy tóm tắt nội dung hàng loạt',
            'description' => 'Trích xuất summaries cho nhiều bài viết trong 1 request',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_ids'   => array(
                        'type'  => 'array',
                        'items' => array('type' => 'integer'),
                        'description' => 'Danh sách post IDs',
                    ),
                    'max_length' => array('type' => 'integer', 'description' => 'Số ký tự tối đa cho mỗi summary (mặc định 500)'),
                ),
                'required' => array('post_ids'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_batch_content_summaries'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    // ============================================================
    // 7 ABILITIES BỔ SUNG CHO DESKTOP APP
    // ============================================================

    /**
     * 7. genseo/get-post-field — Lấy 1 field cụ thể của bài viết
     */
    private static function register_get_post_field() {
        wp_register_ability('genseo/get-post-field', array(
            'label'       => 'Lấy field bài viết',
            'description' => 'Lấy 1 field cụ thể: title, content, excerpt',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'ID bài viết'),
                    'field'   => array('type' => 'string', 'description' => 'Field cần lấy: title, content, excerpt'),
                ),
                'required' => array('post_id', 'field'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_post_field'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 8. genseo/get-posts — Lấy danh sách bài viết
     */
    private static function register_get_posts() {
        wp_register_ability('genseo/get-posts', array(
            'label'       => 'Lấy danh sách bài viết',
            'description' => 'Lấy danh sách bài viết với filter, pagination, sorting',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'per_page'  => array('type' => 'integer', 'description' => 'Số bài mỗi trang (mặc định 100)'),
                    'page'      => array('type' => 'integer', 'description' => 'Trang (mặc định 1)'),
                    'status'    => array('type' => 'string', 'description' => 'Trạng thái: publish, draft, any'),
                    'search'    => array('type' => 'string', 'description' => 'Từ khóa tìm kiếm'),
                    'orderby'   => array('type' => 'string', 'description' => 'Sắp xếp theo: date, modified, title'),
                    'order'     => array('type' => 'string', 'description' => 'Thứ tự: asc, desc'),
                    'post_type' => array('type' => 'string', 'description' => 'Post type(s), phân cách bằng dấu phẩy. Mặc định: post,page'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_posts'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 9. genseo/get-post — Lấy chi tiết 1 bài viết
     */
    private static function register_get_post() {
        wp_register_ability('genseo/get-post', array(
            'label'       => 'Lấy chi tiết bài viết',
            'description' => 'Lấy toàn bộ thông tin bài viết kèm GenSeo meta',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'ID bài viết'),
                ),
                'required' => array('post_id'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_post'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 10. genseo/update-post — Cập nhật bài viết
     */
    private static function register_update_post() {
        wp_register_ability('genseo/update-post', array(
            'label'       => 'Cập nhật bài viết',
            'description' => 'Cập nhật title, content, excerpt, status của bài viết',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'ID bài viết'),
                    'title'   => array('type' => 'string', 'description' => 'Tiêu đề mới'),
                    'content' => array('type' => 'string', 'description' => 'Nội dung mới'),
                    'excerpt' => array('type' => 'string', 'description' => 'Tóm tắt mới'),
                    'status'  => array('type' => 'string', 'description' => 'Trạng thái: publish, draft'),
                ),
                'required' => array('post_id'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_update_post'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 11. genseo/get-post-meta — Lấy post meta
     */
    private static function register_get_post_meta() {
        wp_register_ability('genseo/get-post-meta', array(
            'label'       => 'Lấy post meta',
            'description' => 'Lấy meta keys chỉ định hoặc tất cả GenSeo meta của bài viết',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id'   => array('type' => 'integer', 'description' => 'ID bài viết'),
                    'meta_keys' => array(
                        'type'        => 'array',
                        'items'       => array('type' => 'string'),
                        'description' => 'Danh sách meta keys cần lấy (null = tất cả GenSeo meta)',
                    ),
                ),
                'required' => array('post_id'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_post_meta'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 12. genseo/update-post-meta — Cập nhật post meta
     */
    private static function register_update_post_meta() {
        wp_register_ability('genseo/update-post-meta', array(
            'label'       => 'Cập nhật post meta',
            'description' => 'Cập nhật nhiều meta keys cùng lúc cho bài viết',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'ID bài viết'),
                    'meta'    => array(
                        'type'        => 'object',
                        'description' => 'Object { meta_key: value } cần cập nhật',
                    ),
                ),
                'required' => array('post_id', 'meta'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_update_post_meta'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 13. genseo/get-site-info — Lấy thông tin site WordPress
     */
    private static function register_get_site_info() {
        wp_register_ability('genseo/get-site-info', array(
            'label'       => 'Lấy thông tin site',
            'description' => 'Lấy thông tin site, categories, tags, SEO plugin, user',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => new stdClass(), // Không cần input
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_site_info'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    // ============================================================
    // PHASE 8: ABILITIES MỚI CHO FIX EXPANSION
    // ============================================================

    /**
     * 14. genseo/create-redirect — Tạo redirect 301/302
     */
    private static function register_create_redirect() {
        wp_register_ability('genseo/create-redirect', array(
            'label'       => 'Tạo redirect',
            'description' => 'Tạo redirect 301/302 từ URL cũ sang URL mới',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'from_url'    => array('type' => 'string', 'description' => 'URL nguồn (relative hoặc absolute)'),
                    'to_url'      => array('type' => 'string', 'description' => 'URL đích'),
                    'status_code' => array('type' => 'integer', 'description' => 'Mã HTTP redirect (301 hoặc 302, mặc định 301)'),
                ),
                'required' => array('from_url', 'to_url'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_create_redirect'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 15. genseo/update-image-alt — Cập nhật alt text cho ảnh trong bài viết
     */
    private static function register_update_image_alt() {
        wp_register_ability('genseo/update-image-alt', array(
            'label'       => 'Cập nhật alt text ảnh',
            'description' => 'Cập nhật alt text cho ảnh trong bài viết (attachment meta + nội dung bài)',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'ID bài viết chứa ảnh'),
                    'images'  => array(
                        'type'  => 'array',
                        'items' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'attachment_id' => array('type' => 'integer', 'description' => 'ID attachment (0 nếu không biết)'),
                                'src'           => array('type' => 'string', 'description' => 'URL ảnh (dùng khi không có attachment_id)'),
                                'alt_text'      => array('type' => 'string', 'description' => 'Alt text mới'),
                            ),
                            'required' => array('alt_text'),
                        ),
                    ),
                ),
                'required' => array('post_id', 'images'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_update_image_alt'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 17. genseo/get-post-types — Lấy danh sách public post types
     */
    private static function register_get_post_types() {
        wp_register_ability('genseo/get-post-types', array(
            'label'       => 'Lấy danh sách post types',
            'description' => 'Lấy danh sách public post types của site (post, page, product...)',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_post_types'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    // ============================================================
    // PHASE 9: REGISTRATION — REDIRECT & LINK INTELLIGENCE
    // ============================================================

    /**
     * 18. genseo/get-redirects — Đọc danh sách redirect rules
     */
    private static function register_get_redirects() {
        wp_register_ability('genseo/get-redirects', array(
            'label'       => 'Lấy danh sách redirects',
            'description' => 'Đọc tất cả redirect rules (301/302) đã tạo, hỗ trợ filter và phân trang',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'status_code' => array('type' => 'integer', 'description' => 'Filter theo status code (301 hoặc 302)'),
                    'search'      => array('type' => 'string', 'description' => 'Tìm kiếm trong from_url hoặc to_url'),
                    'limit'       => array('type' => 'integer', 'description' => 'Số lượng trả về (default 100, max 500)'),
                    'offset'      => array('type' => 'integer', 'description' => 'Offset phân trang'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_redirects'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 19. genseo/update-redirect — Cập nhật hoặc xóa redirect rule
     */
    private static function register_update_redirect() {
        wp_register_ability('genseo/update-redirect', array(
            'label'       => 'Cập nhật/xóa redirect',
            'description' => 'Cập nhật hoặc xóa redirect rule theo from_url',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'from_url'    => array('type' => 'string', 'description' => 'URL nguồn (key xác định rule)'),
                    'action'      => array('type' => 'string', 'description' => 'Hành động: update hoặc delete', 'enum' => array('update', 'delete')),
                    'to_url'      => array('type' => 'string', 'description' => 'URL đích mới (bắt buộc khi action=update)'),
                    'status_code' => array('type' => 'integer', 'description' => 'Status code mới (301 hoặc 302)'),
                ),
                'required' => array('from_url', 'action'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_update_redirect'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 20. genseo/get-internal-link-graph — Phân tích liên kết nội bộ toàn site
     */
    private static function register_get_internal_link_graph() {
        wp_register_ability('genseo/get-internal-link-graph', array(
            'label'       => 'Lấy đồ thị liên kết nội bộ',
            'description' => 'Phân tích internal links toàn site: nodes, edges, orphan pages, top linked pages',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_type'     => array('type' => 'string', 'description' => 'CSV post types (default: post,page)'),
                    'limit'         => array('type' => 'integer', 'description' => 'Max posts (default 500, max 2000)'),
                    'include_stats' => array('type' => 'boolean', 'description' => 'Tính orphan pages, top linked (default true)'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_internal_link_graph'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 21. genseo/detect-broken-links — Phát hiện liên kết hỏng
     */
    private static function register_detect_broken_links() {
        wp_register_ability('genseo/detect-broken-links', array(
            'label'       => 'Phát hiện liên kết hỏng',
            'description' => 'Kiểm tra internal links trong content, phát hiện links trỏ tới trang 404/draft/trash',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id'        => array('type' => 'integer', 'description' => 'Kiểm tra 1 post cụ thể'),
                    'limit'          => array('type' => 'integer', 'description' => 'Hoặc scan nhiều posts (default 50, max 200)'),
                    'check_external' => array('type' => 'boolean', 'description' => 'Kiểm tra cả external links (chậm hơn, default false)'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_detect_broken_links'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    // ============================================================
    // PHASE 9: REGISTRATION — CONTENT DEEP ANALYSIS
    // ============================================================

    /**
     * 22. genseo/get-schema-full — Lấy JSON-LD schema đầy đủ
     */
    private static function register_get_schema_full() {
        wp_register_ability('genseo/get-schema-full', array(
            'label'       => 'Lấy schema JSON-LD đầy đủ',
            'description' => 'Trả về JSON-LD schema đầy đủ (Article/FAQ/HowTo) thay vì chỉ type name',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'ID bài viết'),
                ),
                'required' => array('post_id'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_schema_full'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 23. genseo/get-images-detail — Chi tiết ảnh trong bài viết
     */
    private static function register_get_images_detail() {
        wp_register_ability('genseo/get-images-detail', array(
            'label'       => 'Lấy chi tiết ảnh bài viết',
            'description' => 'Trả về chi tiết tất cả images: src, alt, dimensions, format, file size, attachment ID',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id'                => array('type' => 'integer', 'description' => 'ID bài viết'),
                    'include_featured'       => array('type' => 'boolean', 'description' => 'Bao gồm featured image (default true)'),
                    'include_content_images' => array('type' => 'boolean', 'description' => 'Bao gồm ảnh trong content (default true)'),
                ),
                'required' => array('post_id'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_images_detail'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 24. genseo/batch-get-links-in-content — Trích xuất links từ nhiều bài
     */
    private static function register_batch_get_links_in_content() {
        wp_register_ability('genseo/batch-get-links-in-content', array(
            'label'       => 'Trích xuất links từ nhiều bài',
            'description' => 'Lấy chi tiết tất cả links (URL, anchor text, type, nofollow) từ nhiều posts',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_ids'  => array(
                        'type'  => 'array',
                        'items' => array('type' => 'integer'),
                        'description' => 'Danh sách post IDs (max 100)',
                    ),
                    'link_type' => array('type' => 'string', 'description' => 'Filter: internal, external, all (default all)', 'enum' => array('internal', 'external', 'all')),
                ),
                'required' => array('post_ids'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_batch_get_links_in_content'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    // ============================================================
    // PHASE 9: REGISTRATION — SITEMAP, ROBOTS & INDEXATION
    // ============================================================

    /**
     * 25. genseo/get-sitemap-urls — Phân tích sitemap coverage
     */
    private static function register_get_sitemap_urls() {
        wp_register_ability('genseo/get-sitemap-urls', array(
            'label'       => 'Phân tích sitemap coverage',
            'description' => 'Parse sitemap.xml và so sánh với published posts để tìm gaps',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'compare_with_posts' => array('type' => 'boolean', 'description' => 'So sánh với published posts (default true)'),
                    'post_type'          => array('type' => 'string', 'description' => 'CSV post types để compare (default post,page)'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_sitemap_urls'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 26. genseo/get-robots-txt — Đọc và phân tích robots.txt
     */
    private static function register_get_robots_txt() {
        wp_register_ability('genseo/get-robots-txt', array(
            'label'       => 'Đọc robots.txt',
            'description' => 'Đọc và phân tích robots.txt, phát hiện issues (block Googlebot, thiếu sitemap...)',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'analyze' => array('type' => 'boolean', 'description' => 'Phân tích rules và phát hiện issues (default true)'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_robots_txt'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 27. genseo/get-content-stats — Thống kê chất lượng content toàn site
     */
    private static function register_get_content_stats() {
        wp_register_ability('genseo/get-content-stats', array(
            'label'       => 'Thống kê content toàn site',
            'description' => 'Quick snapshot: thin content, missing SEO meta, images, headings, date distribution',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_type'   => array('type' => 'string', 'description' => 'CSV post types (default post,page)'),
                    'post_status' => array('type' => 'string', 'description' => 'Post status (default publish)'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_content_stats'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    // ============================================================
    // PHASE 4: TAXONOMY & REVISION SEO (2 abilities)
    // ============================================================

    /**
     * 28. genseo/get-taxonomy-seo — SEO meta cho categories/tags/custom taxonomies
     */
    private static function register_get_taxonomy_seo() {
        wp_register_ability('genseo/get-taxonomy-seo', array(
            'label'       => 'SEO cho taxonomy terms',
            'description' => 'Lấy SEO meta (title, description, focus keyword) cho category/tag/custom taxonomy terms. Hỗ trợ Yoast & RankMath.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'required'   => array('taxonomy'),
                'properties' => array(
                    'taxonomy'   => array('type' => 'string', 'description' => 'Taxonomy name: category, post_tag, product_cat, etc.'),
                    'hide_empty' => array('type' => 'boolean', 'description' => 'Ẩn terms không có posts (default true)'),
                    'limit'      => array('type' => 'integer', 'description' => 'Max terms (default 100, max 500)'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_taxonomy_seo'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 29. genseo/get-post-revisions — Lịch sử revision và thay đổi SEO meta
     */
    private static function register_get_post_revisions() {
        wp_register_ability('genseo/get-post-revisions', array(
            'label'       => 'Lịch sử revision bài viết',
            'description' => 'Lấy danh sách revisions của bài viết, so sánh thay đổi title/content/excerpt.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'required'   => array('post_id'),
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'Post ID'),
                    'limit'   => array('type' => 'integer', 'description' => 'Max revisions (default 10, max 50)'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_post_revisions'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    // ============================================================
    // PHASE 5: WORDPRESS SITE INTELLIGENCE (6 abilities)
    // ============================================================

    /**
     * 30. genseo/get-active-plugins — Danh sách plugins đang active
     */
    private static function register_get_active_plugins() {
        wp_register_ability('genseo/get-active-plugins', array(
            'label'       => 'Plugins đang hoạt động',
            'description' => 'Danh sách plugins + phân loại SEO/cache/WooCommerce. Hỗ trợ Smart Instructions.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_active_plugins'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 31. genseo/get-theme-info — Thông tin theme hiện tại
     */
    private static function register_get_theme_info() {
        wp_register_ability('genseo/get-theme-info', array(
            'label'       => 'Thông tin theme',
            'description' => 'Theme name, version, child theme, supports (title-tag, thumbnails, WooCommerce).',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_theme_info'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 32. genseo/get-navigation-structure — Cấu trúc menu navigation
     */
    private static function register_get_navigation_structure() {
        wp_register_ability('genseo/get-navigation-structure', array(
            'label'       => 'Cấu trúc navigation menu',
            'description' => 'Danh sách menus + items hierarchy. Hỗ trợ orphan page detection.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'menu_location' => array('type' => 'string', 'description' => 'Menu location cụ thể (e.g. primary). Không truyền = tất cả'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_navigation_structure'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 33. genseo/get-site-health — Chẩn đoán sức khỏe site (reuse GenSeo_Diagnostic)
     */
    private static function register_get_site_health() {
        wp_register_ability('genseo/get-site-health', array(
            'label'       => 'Chẩn đoán sức khỏe site',
            'description' => 'PHP/WP version, memory, MCP status, REST API, WAF detection. Reuse diagnostic tests.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_site_health'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 34. genseo/analyze-permalink-structure — Phân tích cấu trúc permalink
     */
    private static function register_analyze_permalink_structure() {
        wp_register_ability('genseo/analyze-permalink-structure', array(
            'label'       => 'Phân tích permalink',
            'description' => 'Permalink structure, SEO-friendly check, sample URLs, issues detection.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_analyze_permalink_structure'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * 35. genseo/get-site-settings — Cài đặt WordPress ảnh hưởng SEO
     */
    private static function register_get_site_settings() {
        wp_register_ability('genseo/get-site-settings', array(
            'label'       => 'Cài đặt WordPress',
            'description' => 'Indexing (blog_public), content settings, media sizes, locale, registration. Critical: phát hiện site blocking search engines.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_site_settings'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    // ============================================================
    // PERMISSION CALLBACK
    // ============================================================

    /**
     * Kiểm tra quyền edit_posts
     *
     * @return bool
     */
    public static function check_edit_permission() {
        return current_user_can('edit_posts');
    }

    // ============================================================
    // HANDLERS — 6 SEO ABILITIES
    // ============================================================

    /**
     * Handler: genseo/get-seo-meta
     * Lấy toàn bộ SEO meta của bài viết bao gồm cả meta từ Yoast/RankMath
     *
     * @param array $params { post_id: int }
     * @return array|WP_Error
     */
    public static function handle_get_seo_meta($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // GenSeo meta
        $result = array(
            'post_id'          => $post_id,
            'seo_title'        => get_post_meta($post_id, '_genseo_seo_title', true),
            'meta_description' => get_post_meta($post_id, '_genseo_meta_desc', true),
            'focus_keyword'    => get_post_meta($post_id, '_genseo_focus_keyword', true),
            'schema_type'      => get_post_meta($post_id, '_genseo_schema_type', true),
            'schema_json'      => get_post_meta($post_id, '_genseo_schema_json', true),
            'canonical_url'    => get_post_meta($post_id, '_genseo_canonical_url', true),
            'robots'           => get_post_meta($post_id, '_genseo_robots', true),
            'og_image'         => get_post_meta($post_id, '_genseo_og_image', true),
        );

        // Thêm OG title/description (lấy từ Yoast/RankMath nếu có)
        $result['og_title']       = self::get_og_field($post_id, 'title');
        $result['og_description'] = self::get_og_field($post_id, 'description');

        // Fallback seo_title từ Yoast/RankMath nếu GenSeo trống
        if (empty($result['seo_title'])) {
            $result['seo_title'] = self::get_seo_plugin_meta($post_id, 'title');
        }
        if (empty($result['meta_description'])) {
            $result['meta_description'] = self::get_seo_plugin_meta($post_id, 'description');
        }
        if (empty($result['focus_keyword'])) {
            $result['focus_keyword'] = self::get_seo_plugin_meta($post_id, 'focus_keyword');
        }

        return $result;
    }

    /**
     * Handler: genseo/update-seo-meta
     * Cập nhật SEO meta + sync tới Yoast/RankMath
     *
     * @param array $params { post_id, seo_title?, meta_description?, ... }
     * @return array|WP_Error
     */
    public static function handle_update_seo_meta($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Kiểm tra quyền edit bài cụ thể
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('forbidden', 'Không có quyền chỉnh sửa bài viết này', array('status' => 403));
        }

        // Mapping param → meta key
        $field_map = array(
            'seo_title'        => '_genseo_seo_title',
            'meta_description' => '_genseo_meta_desc',
            'focus_keyword'    => '_genseo_focus_keyword',
            'canonical_url'    => '_genseo_canonical_url',
            'robots'           => '_genseo_robots',
            'og_title'         => '_genseo_og_title',
            'og_description'   => '_genseo_og_description',
        );

        // Sanitize functions cho từng field
        $sanitize_map = array(
            'seo_title'        => 'sanitize_text_field',
            'meta_description' => 'sanitize_textarea_field',
            'focus_keyword'    => 'sanitize_text_field',
            'canonical_url'    => 'esc_url_raw',
            'robots'           => 'sanitize_text_field',
            'og_title'         => 'sanitize_text_field',
            'og_description'   => 'sanitize_textarea_field',
        );

        $updated_fields = array();

        // Cập nhật GenSeo meta
        foreach ($field_map as $param_key => $meta_key) {
            if (isset($params[$param_key]) && $params[$param_key] !== '') {
                $value = $params[$param_key];
                // Sanitize
                if (isset($sanitize_map[$param_key]) && function_exists($sanitize_map[$param_key])) {
                    $value = call_user_func($sanitize_map[$param_key], $value);
                }
                update_post_meta($post_id, $meta_key, $value);
                $updated_fields[] = $param_key;
            }
        }

        // Xử lý schema_json riêng (cần validate JSON)
        if (isset($params['schema_json']) && !empty($params['schema_json'])) {
            $schema = $params['schema_json'];
            // Validate JSON nếu là string
            if (is_string($schema)) {
                $decoded = json_decode($schema, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new WP_Error('invalid_schema', 'schema_json không phải JSON hợp lệ', array('status' => 400));
                }
                // Lưu dưới dạng string JSON
                update_post_meta($post_id, '_genseo_schema_json', $schema);
            }
            $updated_fields[] = 'schema_json';
        }

        // Đánh dấu bài từ GenSeo
        update_post_meta($post_id, '_genseo_source', 'genseo-desktop');

        // Sync tới SEO plugin
        self::sync_to_seo_plugin($post_id);

        $result = array(
            'success'        => true,
            'post_id'        => $post_id,
            'updated_fields' => $updated_fields,
        );

        // Audit log
        do_action('genseo_mcp_ability_executed', 'genseo/update-seo-meta', $params, $result, true);

        return $result;
    }

    /**
     * Handler: genseo/bulk-update-seo
     * Cập nhật SEO cho nhiều bài cùng lúc
     *
     * @param array $params { items: [{ post_id, seo_title?, ... }] }
     * @return array|WP_Error
     */
    public static function handle_bulk_update_seo($params) {
        if (empty($params['items']) || !is_array($params['items'])) {
            return new WP_Error('invalid_items', 'Thiếu hoặc sai định dạng items', array('status' => 400));
        }

        $results = array(
            'success' => true,
            'total'   => count($params['items']),
            'updated' => 0,
            'failed'  => 0,
            'errors'  => array(),
        );

        foreach ($params['items'] as $index => $item) {
            $result = self::handle_update_seo_meta($item);

            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = array(
                    'index'   => $index,
                    'post_id' => isset($item['post_id']) ? (int) $item['post_id'] : null,
                    'error'   => $result->get_error_message(),
                );
            } else {
                $results['updated']++;
            }
        }

        $results['success'] = ($results['failed'] === 0);

        // Audit log
        do_action('genseo_mcp_ability_executed', 'genseo/bulk-update-seo', $params, $results, $results['success']);

        return $results;
    }

    /**
     * Handler: genseo/get-posts-needing-optimization
     * Lấy bài viết thiếu SEO meta
     *
     * @param array $params { limit?, offset? }
     * @return array
     */
    public static function handle_get_posts_needing_optimization($params) {
        $limit  = isset($params['limit']) ? absint($params['limit']) : 50;
        $offset = isset($params['offset']) ? absint($params['offset']) : 0;

        // Parse post_type param — CSV hoặc mặc định post,page
        $post_types = self::parse_post_type_param($params);

        // Giới hạn tối đa
        $limit = min($limit, 500);

        // Query bài publish thiếu SEO meta
        // Dùng meta_query với OR: thiếu seo_title HOẶC meta_desc HOẶC focus_keyword
        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                'relation' => 'OR',
                // Không có seo_title
                array(
                    'key'     => '_genseo_seo_title',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_genseo_seo_title',
                    'value'   => '',
                    'compare' => '=',
                ),
                // Không có meta_desc
                array(
                    'key'     => '_genseo_meta_desc',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_genseo_meta_desc',
                    'value'   => '',
                    'compare' => '=',
                ),
                // Không có focus_keyword
                array(
                    'key'     => '_genseo_focus_keyword',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_genseo_focus_keyword',
                    'value'   => '',
                    'compare' => '=',
                ),
            ),
        );

        $query = new WP_Query($args);
        $posts = array();

        foreach ($query->posts as $post) {
            $missing = array();

            $seo_title = get_post_meta($post->ID, '_genseo_seo_title', true);
            $meta_desc = get_post_meta($post->ID, '_genseo_meta_desc', true);
            $focus_kw  = get_post_meta($post->ID, '_genseo_focus_keyword', true);

            // Cũng kiểm tra Yoast/RankMath meta
            if (empty($seo_title)) {
                $seo_title = self::get_seo_plugin_meta($post->ID, 'title');
            }
            if (empty($meta_desc)) {
                $meta_desc = self::get_seo_plugin_meta($post->ID, 'description');
            }
            if (empty($focus_kw)) {
                $focus_kw = self::get_seo_plugin_meta($post->ID, 'focus_keyword');
            }

            if (empty($seo_title))  $missing[] = 'seo_title';
            if (empty($meta_desc))  $missing[] = 'meta_description';
            if (empty($focus_kw))   $missing[] = 'focus_keyword';

            // Chỉ thêm nếu thực sự thiếu (sau khi check Yoast/RankMath)
            if (!empty($missing)) {
                $posts[] = array(
                    'id'             => $post->ID,
                    'title'          => $post->post_title,
                    'url'            => get_permalink($post->ID),
                    'date'           => $post->post_date,
                    'missing_fields' => $missing,
                );
            }
        }

        return array(
            'posts' => $posts,
            'total' => count($posts),
            'query_total' => (int) $query->found_posts,
        );
    }

    /**
     * Handler: genseo/update-internal-links
     * Chèn internal links vào nội dung bài viết
     *
     * @param array $params { post_id, links: [{ anchor_text, target_url, position? }] }
     * @return array|WP_Error
     */
    public static function handle_update_internal_links($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('forbidden', 'Không có quyền chỉnh sửa bài viết này', array('status' => 403));
        }

        if (empty($params['links']) || !is_array($params['links'])) {
            return new WP_Error('invalid_links', 'Thiếu hoặc sai định dạng links', array('status' => 400));
        }

        $post = get_post($post_id);
        $content = $post->post_content;
        $site_url = get_site_url();
        $links_added = 0;

        // Sắp xếp links theo vị trí giảm dần để chèn từ dưới lên (tránh lệch index)
        $links = $params['links'];
        usort($links, function($a, $b) {
            $pos_a = self::parse_paragraph_position($a['position'] ?? '');
            $pos_b = self::parse_paragraph_position($b['position'] ?? '');
            return $pos_b - $pos_a;
        });

        foreach ($links as $link) {
            $anchor = sanitize_text_field($link['anchor_text'] ?? '');
            $url    = esc_url_raw($link['target_url'] ?? '');

            if (empty($anchor) || empty($url)) {
                continue;
            }

            // Validate internal URL
            if (!self::is_internal_url($url, $site_url)) {
                continue;
            }

            // Validate URL trỏ đến bài viết thật đã publish (tránh chèn link 404)
            $target_post_id = url_to_postid($url);
            if (!$target_post_id) {
                // Thử với full URL nếu là relative path
                $full_url = (strpos($url, '/') === 0 && strpos($url, '//') !== 0) ? $site_url . $url : $url;
                $target_post_id = url_to_postid($full_url);
            }
            if (!$target_post_id || get_post_status($target_post_id) !== 'publish') {
                continue; // URL không tồn tại hoặc chưa publish → bỏ qua
            }

            // Tạo link HTML
            $link_html = self::build_link_html($anchor, $url);

            // Chèn theo vị trí
            $position = $link['position'] ?? '';
            $paragraph_num = self::parse_paragraph_position($position);

            if ($paragraph_num > 0) {
                $content = self::insert_after_paragraph($content, $paragraph_num, $link_html);
            } else {
                // Mặc định: chèn cuối bài
                $content .= "\n" . $link_html;
            }

            $links_added++;
        }

        // Lưu content
        if ($links_added > 0) {
            $update_result = wp_update_post(array(
                'ID'           => $post_id,
                'post_content' => $content,
            ), true);

            if (is_wp_error($update_result)) {
                return $update_result;
            }
        }

        $result = array(
            'success'     => true,
            'post_id'     => $post_id,
            'links_added' => $links_added,
        );

        // Audit log
        do_action('genseo_mcp_ability_executed', 'genseo/update-internal-links', $params, $result, true);

        return $result;
    }

    /**
     * Handler: genseo/get-post-content-summary
     * Lấy tóm tắt nội dung + thống kê
     *
     * @param array $params { post_id, max_length? }
     * @return array|WP_Error
     */
    public static function handle_get_post_content_summary($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $max_length = isset($params['max_length']) ? absint($params['max_length']) : 500;
        $max_length = min($max_length, 5000);

        $post    = get_post($post_id);
        $content = $post->post_content;

        // Render Gutenberg blocks → HTML thuần (để regex tìm headings chính xác)
        if (function_exists('do_blocks')) {
            $content = do_blocks($content);
        }

        // Text thuần (bỏ HTML)
        $plain_text = wp_strip_all_tags($content);
        $plain_text = preg_replace('/\s+/', ' ', trim($plain_text));

        // Summary (cắt theo max_length)
        $summary = mb_substr($plain_text, 0, $max_length);
        if (mb_strlen($plain_text) > $max_length) {
            $summary .= '...';
        }

        // Thống kê cơ bản
        $word_count      = str_word_count($plain_text);
        $paragraph_count = self::count_paragraphs($content);

        // Headings kèm cấp bậc
        $headings = array();
        if (preg_match_all('/<(h[1-6])\b[^>]*>(.*?)<\/\1>/si', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $headings[] = array(
                    'tag'  => strtolower($m[1]),
                    'text' => wp_strip_all_tags($m[2]),
                );
            }
        }

        // Ảnh — tổng và thiếu alt
        $images_total = 0;
        $images_without_alt = 0;
        if (preg_match_all('/<img\s[^>]*>/si', $content, $img_matches)) {
            $images_total = count($img_matches[0]);
            foreach ($img_matches[0] as $img_tag) {
                if (!preg_match('/alt=["\'][^"\']+["\']/i', $img_tag)) {
                    $images_without_alt++;
                }
            }
        }

        // Links — phân loại internal/external
        $site_url = get_site_url();
        $internal_links_count = 0;
        $external_links_count = 0;
        if (preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/si', $content, $link_matches)) {
            foreach ($link_matches[1] as $href) {
                if (self::is_internal_url($href, $site_url)) {
                    $internal_links_count++;
                } else {
                    $external_links_count++;
                }
            }
        }

        // Schema (JSON-LD)
        $schema_types = array();
        $has_schema   = false;
        if (preg_match_all('/<script\s[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $content, $schema_matches)) {
            $has_schema = true;
            foreach ($schema_matches[1] as $json_str) {
                $data = json_decode($json_str, true);
                if (is_array($data) && isset($data['@type'])) {
                    $schema_types[] = $data['@type'];
                }
            }
        }

        // Kiểm tra có H1 trong content không — nếu không, theme sẽ render title thành H1
        $has_h1_in_content = false;
        foreach ($headings as $h) {
            if ($h['tag'] === 'h1') {
                $has_h1_in_content = true;
                break;
            }
        }
        $title_as_h1 = !$has_h1_in_content;

        return array(
            'post_id'              => $post_id,
            'plain_text'           => $summary,
            'word_count'           => $word_count,
            'headings'             => $headings,
            'paragraph_count'      => $paragraph_count,
            'images_total'         => $images_total,
            'images_without_alt'   => $images_without_alt,
            'internal_links_count' => $internal_links_count,
            'external_links_count' => $external_links_count,
            'has_schema'           => $has_schema,
            'schema_types'         => $schema_types,
            'content_length'       => mb_strlen($plain_text),
            'title_as_h1'          => $title_as_h1,
        );
    }

    /**
     * Handler: genseo/get-batch-content-summaries
     * Lấy tóm tắt nội dung cho nhiều bài viết trong 1 request
     *
     * @param array $params { post_ids: int[], max_length?: int }
     * @return array|WP_Error
     */
    public static function handle_get_batch_content_summaries($params) {
        if (empty($params['post_ids']) || !is_array($params['post_ids'])) {
            return new WP_Error('missing_post_ids', 'Cần truyền mảng post_ids', array('status' => 400));
        }

        $post_ids = array_map('absint', $params['post_ids']);
        $post_ids = array_filter($post_ids); // Bỏ 0

        // Giới hạn batch size (tránh quá tải)
        if (count($post_ids) > 200) {
            $post_ids = array_slice($post_ids, 0, 200);
        }

        $max_length = isset($params['max_length']) ? absint($params['max_length']) : 500;
        $max_length = min($max_length, 5000);

        $summaries = array();

        foreach ($post_ids as $pid) {
            $post = get_post($pid);
            if (!$post || $post->post_status !== 'publish') {
                continue;
            }

            $result = self::handle_get_post_content_summary(array(
                'post_id'    => $pid,
                'max_length' => $max_length,
            ));

            if (!is_wp_error($result)) {
                // Thêm modified date để client cache
                $result['modified'] = $post->post_modified;
                $summaries[] = $result;
            }
        }

        return array(
            'summaries' => $summaries,
            'total'     => count($summaries),
        );
    }

    // ============================================================
    // HANDLERS — 7 ABILITIES BỔ SUNG
    // ============================================================

    /**
     * Handler: genseo/get-post-field
     *
     * @param array $params { post_id, field }
     * @return string|WP_Error
     */
    public static function handle_get_post_field($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $field = sanitize_text_field($params['field'] ?? '');
        $allowed_fields = array('title', 'content', 'excerpt');

        if (!in_array($field, $allowed_fields, true)) {
            return new WP_Error('invalid_field', 'Field không hợp lệ. Chấp nhận: title, content, excerpt', array('status' => 400));
        }

        $post = get_post($post_id);

        switch ($field) {
            case 'title':
                return $post->post_title;
            case 'content':
                return $post->post_content;
            case 'excerpt':
                return $post->post_excerpt;
            default:
                return '';
        }
    }

    /**
     * Handler: genseo/get-posts
     *
     * @param array $params { per_page?, page?, status?, search?, orderby?, order? }
     * @return array
     */
    public static function handle_get_posts($params) {
        $per_page = isset($params['per_page']) ? absint($params['per_page']) : 100;
        $page     = isset($params['page']) ? absint($params['page']) : 1;
        $status   = sanitize_text_field($params['status'] ?? 'publish');
        $search   = sanitize_text_field($params['search'] ?? '');
        $orderby  = sanitize_text_field($params['orderby'] ?? 'date');
        $order    = strtoupper(sanitize_text_field($params['order'] ?? 'DESC'));

        // Parse post_type param — CSV hoặc mặc định post,page
        $post_types = self::parse_post_type_param($params);

        // Giới hạn
        $per_page = min($per_page, 500);
        if (!in_array($order, array('ASC', 'DESC'), true)) {
            $order = 'DESC';
        }
        if (!in_array($orderby, array('date', 'modified', 'title', 'ID'), true)) {
            $orderby = 'date';
        }

        $args = array(
            'post_type'      => $post_types,
            'post_status'    => $status === 'any' ? array('publish', 'draft', 'pending', 'private') : $status,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => $orderby,
            'order'          => $order,
        );

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $posts = array();

        foreach ($query->posts as $post) {
            $posts[] = self::format_post_basic($post);
        }

        return array(
            'posts'       => $posts,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page'        => $page,
            'per_page'    => $per_page,
        );
    }

    /**
     * Handler: genseo/get-post
     *
     * @param array $params { post_id }
     * @return array|WP_Error
     */
    public static function handle_get_post($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $post = get_post($post_id);

        return self::format_post_full($post);
    }

    /**
     * Handler: genseo/update-post
     *
     * @param array $params { post_id, title?, content?, excerpt?, status? }
     * @return array|WP_Error
     */
    public static function handle_update_post($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('forbidden', 'Không có quyền chỉnh sửa bài viết này', array('status' => 403));
        }

        $update_data = array('ID' => $post_id);

        if (isset($params['title'])) {
            $update_data['post_title'] = sanitize_text_field($params['title']);
        }
        if (isset($params['content'])) {
            // Safety guard: Gutenberg block integrity
            // Nếu content hiện tại dùng Gutenberg blocks nhưng content mới không có → reject
            $current_post = get_post($post_id);
            $current_content = $current_post ? $current_post->post_content : '';
            $current_has_gutenberg = preg_match('/<!--\s*wp:/', $current_content);
            $new_has_gutenberg = preg_match('/<!--\s*wp:/', $params['content']);

            if ($current_has_gutenberg && !$new_has_gutenberg && strlen($current_content) > 200) {
                return new WP_Error(
                    'gutenberg_block_integrity',
                    'Content hiện tại dùng WordPress Gutenberg blocks nhưng content mới không chứa block markers. ' .
                    'Ghi đè sẽ phá hỏng cấu trúc Gutenberg. Hủy cập nhật để bảo vệ nội dung.',
                    array('status' => 422)
                );
            }

            $update_data['post_content'] = wp_kses_post($params['content']);
        }
        if (isset($params['excerpt'])) {
            $update_data['post_excerpt'] = sanitize_textarea_field($params['excerpt']);
        }
        if (isset($params['status'])) {
            $allowed_statuses = array('publish', 'draft', 'pending', 'private');
            $status = sanitize_text_field($params['status']);
            if (in_array($status, $allowed_statuses, true)) {
                $update_data['post_status'] = $status;
            }
        }

        // Cập nhật post
        $result = wp_update_post($update_data, true);
        if (is_wp_error($result)) {
            return $result;
        }

        // Cập nhật meta nếu có
        if (isset($params['meta']) && is_array($params['meta'])) {
            foreach ($params['meta'] as $key => $value) {
                $key = sanitize_text_field($key);
                update_post_meta($post_id, $key, $value);
            }
        }

        $result = array(
            'success' => true,
            'post_id' => $post_id,
        );

        // Audit log
        do_action('genseo_mcp_ability_executed', 'genseo/update-post', $params, $result, true);

        return $result;
    }

    /**
     * Handler: genseo/get-post-meta
     *
     * @param array $params { post_id, meta_keys? }
     * @return array|WP_Error
     */
    public static function handle_get_post_meta($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $meta_keys = $params['meta_keys'] ?? null;

        // Nếu chỉ định keys cụ thể
        if (!empty($meta_keys) && is_array($meta_keys)) {
            $result = array();
            foreach ($meta_keys as $key) {
                $key = sanitize_text_field($key);
                $result[$key] = get_post_meta($post_id, $key, true);
            }
            return $result;
        }

        // Mặc định: trả về tất cả GenSeo meta
        return GenSeo_Meta_Fields::get_all_meta($post_id);
    }

    /**
     * Handler: genseo/update-post-meta
     *
     * @param array $params { post_id, meta: { key: value } }
     * @return array|WP_Error
     */
    public static function handle_update_post_meta($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('forbidden', 'Không có quyền chỉnh sửa bài viết này', array('status' => 403));
        }

        if (empty($params['meta']) || !is_array($params['meta'])) {
            return new WP_Error('invalid_meta', 'Thiếu hoặc sai định dạng meta', array('status' => 400));
        }

        $updated_keys = array();

        foreach ($params['meta'] as $key => $value) {
            $key = sanitize_text_field($key);

            // Sanitize value dựa trên loại
            if (is_string($value)) {
                // URL fields
                if (strpos($key, 'url') !== false || strpos($key, 'canonical') !== false) {
                    $value = esc_url_raw($value);
                }
                // Textarea fields
                elseif (strpos($key, 'desc') !== false || strpos($key, 'description') !== false) {
                    $value = sanitize_textarea_field($value);
                }
                // Các field khác
                else {
                    $value = sanitize_text_field($value);
                }
            }

            update_post_meta($post_id, $key, $value);
            $updated_keys[] = $key;
        }

        // Sync tới SEO plugin nếu có GenSeo meta
        $has_genseo_meta = false;
        foreach ($updated_keys as $key) {
            if (strpos($key, '_genseo_') === 0 || strpos($key, '_yoast_') === 0 || strpos($key, 'rank_math_') === 0) {
                $has_genseo_meta = true;
                break;
            }
        }
        if ($has_genseo_meta) {
            self::sync_to_seo_plugin($post_id);
        }

        $result = array(
            'success'      => true,
            'post_id'      => $post_id,
            'updated_keys' => $updated_keys,
        );

        do_action('genseo_mcp_ability_executed', 'genseo/update-post-meta', $params, $result);

        return $result;
    }

    /**
     * Handler: genseo/get-site-info
     * Tương tự REST API /info nhưng qua MCP
     *
     * @param array $params (không cần)
     * @return array
     */
    public static function handle_get_site_info($params) {
        $current_user = wp_get_current_user();

        // Categories
        $categories = get_categories(array('hide_empty' => false, 'orderby' => 'name'));
        $categories_data = array_map(function($cat) {
            return array(
                'id'     => $cat->term_id,
                'name'   => $cat->name,
                'slug'   => $cat->slug,
                'parent' => $cat->parent,
                'count'  => $cat->count,
            );
        }, $categories);

        // Tags (top 100)
        $tags = get_tags(array('hide_empty' => false, 'number' => 100, 'orderby' => 'count', 'order' => 'DESC'));
        $tags_data = array_map(function($tag) {
            return array(
                'id'    => $tag->term_id,
                'name'  => $tag->name,
                'slug'  => $tag->slug,
                'count' => $tag->count,
            );
        }, $tags ?: array());

        return array(
            'plugin_version'      => GENSEO_VERSION,
            'api_version'         => self::API_VERSION,
            'min_client_version'  => self::MIN_CLIENT_VERSION,
            'mcp_abilities_count' => 36,
            'site_name'           => get_bloginfo('name'),
            'site_url'            => get_site_url(),
            'site_language'       => get_bloginfo('language'),
            'timezone'            => wp_timezone_string(),
            'permalink_structure' => get_option('permalink_structure'),
            'categories'          => $categories_data,
            'tags'                => $tags_data,
            'seo_plugin'          => genseo_detect_seo_plugin(),
            'user'                => array(
                'id'           => $current_user->ID,
                'display_name' => $current_user->display_name,
                'capabilities' => array(
                    'edit_posts'    => current_user_can('edit_posts'),
                    'publish_posts' => current_user_can('publish_posts'),
                    'upload_files'  => current_user_can('upload_files'),
                ),
            ),
        );
    }

    // ============================================================
    // HANDLER — POST TYPE EXPANSION
    // ============================================================

    /**
     * Handler: genseo/get-post-types
     * Trả về danh sách public post types của site
     *
     * @param array $params (không có tham số)
     * @return array
     */
    public static function handle_get_post_types($params) {
        $post_types = get_post_types(array('public' => true), 'objects');
        $result = array();

        foreach ($post_types as $pt) {
            // Bỏ qua attachment
            if ($pt->name === 'attachment') {
                continue;
            }
            $count = wp_count_posts($pt->name);
            $result[] = array(
                'name'  => $pt->name,
                'label' => $pt->labels->singular_name ?: $pt->label,
                'count' => isset($count->publish) ? (int) $count->publish : 0,
            );
        }

        return array('post_types' => $result);
    }

    // ============================================================
    // HANDLERS — PHASE 8: FIX EXPANSION
    // ============================================================

    /**
     * Handler: genseo/create-redirect
     * Lưu redirect rule vào GenSeo redirect table (wp_options)
     *
     * @param array $params { from_url, to_url, status_code? }
     * @return array|WP_Error
     */
    public static function handle_create_redirect($params) {
        $from_url = sanitize_text_field($params['from_url'] ?? '');
        $to_url   = esc_url_raw($params['to_url'] ?? '');

        if (empty($from_url) || empty($to_url)) {
            return new WP_Error('missing_urls', 'Cần truyền from_url và to_url', array('status' => 400));
        }

        $status_code = isset($params['status_code']) ? absint($params['status_code']) : 301;
        if (!in_array($status_code, array(301, 302), true)) {
            $status_code = 301;
        }

        // Lưu redirect rules trong option (array of redirects)
        $redirects = get_option('genseo_redirects', array());
        if (!is_array($redirects)) {
            $redirects = array();
        }

        // Normalize from_url thành relative path
        $site_url = get_site_url();
        $from_path = $from_url;
        if (strpos($from_url, $site_url) === 0) {
            $from_path = substr($from_url, strlen($site_url));
        }
        if (empty($from_path)) {
            $from_path = '/';
        }

        // Kiểm tra trùng lặp
        foreach ($redirects as $existing) {
            if (($existing['from'] ?? '') === $from_path) {
                // Cập nhật redirect hiện có
                $existing['to']     = $to_url;
                $existing['status'] = $status_code;
                $existing['updated'] = current_time('mysql');
                update_option('genseo_redirects', $redirects);
                return array(
                    'success'     => true,
                    'action'      => 'updated',
                    'from'        => $from_path,
                    'to'          => $to_url,
                    'status_code' => $status_code,
                );
            }
        }

        // Thêm redirect mới
        $redirects[] = array(
            'from'    => $from_path,
            'to'      => $to_url,
            'status'  => $status_code,
            'created' => current_time('mysql'),
        );

        update_option('genseo_redirects', $redirects);

        return array(
            'success'     => true,
            'action'      => 'created',
            'from'        => $from_path,
            'to'          => $to_url,
            'status_code' => $status_code,
            'total_redirects' => count($redirects),
        );
    }

    /**
     * Handler: genseo/update-image-alt
     * Cập nhật alt text cho ảnh: attachment meta + trong nội dung bài viết
     *
     * @param array $params { post_id, images: [{ attachment_id?, src?, alt_text }] }
     * @return array|WP_Error
     */
    public static function handle_update_image_alt($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('forbidden', 'Không có quyền chỉnh sửa bài viết này', array('status' => 403));
        }

        if (empty($params['images']) || !is_array($params['images'])) {
            return new WP_Error('invalid_images', 'Thiếu hoặc sai định dạng images', array('status' => 400));
        }

        $post    = get_post($post_id);
        $content = $post->post_content;
        $updated_count = 0;

        foreach ($params['images'] as $image) {
            $alt_text = sanitize_text_field($image['alt_text'] ?? '');
            if (empty($alt_text)) {
                continue;
            }

            $attachment_id = absint($image['attachment_id'] ?? 0);
            $src           = esc_url_raw($image['src'] ?? '');

            // 1. Cập nhật attachment meta nếu có ID
            if ($attachment_id > 0) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
            }

            // 2. Cập nhật alt trong nội dung bài viết
            if (!empty($src)) {
                // Tìm img tag có src khớp và thay/thêm alt
                $escaped_src = preg_quote($src, '/');
                // Thay thế alt="" hoặc thêm alt nếu chưa có
                $content = preg_replace_callback(
                    '/(<img\s[^>]*src=["\'])(' . $escaped_src . ')(["\'][^>]*)(\/?>)/si',
                    function($matches) use ($alt_text) {
                        $before_src = $matches[1];
                        $src_val    = $matches[2];
                        $after_src  = $matches[3];
                        $closing    = $matches[4];

                        $full_tag = $before_src . $src_val . $after_src . $closing;

                        // Bỏ alt cũ nếu có
                        $full_tag = preg_replace('/\salt=["\'][^"\']*["\']/i', '', $full_tag);

                        // Thêm alt mới trước closing
                        $full_tag = preg_replace(
                            '/(\s*\/?>)$/',
                            ' alt="' . esc_attr($alt_text) . '"$1',
                            $full_tag
                        );

                        return $full_tag;
                    },
                    $content
                );
                $updated_count++;
            } elseif ($attachment_id > 0) {
                // Không có src nhưng có attachment_id → tìm URL attachment
                $att_url = wp_get_attachment_url($attachment_id);
                if ($att_url) {
                    $escaped_att_url = preg_quote($att_url, '/');
                    $content = preg_replace_callback(
                        '/(<img\s[^>]*src=["\'])(' . $escaped_att_url . ')(["\'][^>]*)(\/?>)/si',
                        function($matches) use ($alt_text) {
                            $full_tag = $matches[0];
                            $full_tag = preg_replace('/\salt=["\'][^"\']*["\']/i', '', $full_tag);
                            $full_tag = preg_replace(
                                '/(\s*\/?>)$/',
                                ' alt="' . esc_attr($alt_text) . '"$1',
                                $full_tag
                            );
                            return $full_tag;
                        },
                        $content
                    );
                    $updated_count++;
                }
            }
        }

        // Lưu nội dung đã cập nhật alt
        if ($updated_count > 0) {
            $result = wp_update_post(array(
                'ID'           => $post_id,
                'post_content' => $content,
            ), true);

            if (is_wp_error($result)) {
                return $result;
            }
        }

        return array(
            'success'       => true,
            'post_id'       => $post_id,
            'images_updated' => $updated_count,
        );
    }

    // ============================================================
    // HANDLERS — PHASE 1: REDIRECT & LINK INTELLIGENCE
    // ============================================================

    /**
     * Handler: genseo/get-redirects
     * Đọc danh sách redirect rules với filter và phân trang
     */
    public static function handle_get_redirects($params) {
        $redirects = get_option('genseo_redirects', array());
        if (!is_array($redirects)) {
            $redirects = array();
        }

        $status_code = isset($params['status_code']) ? absint($params['status_code']) : 0;
        $search      = sanitize_text_field($params['search'] ?? '');
        $limit       = min(absint($params['limit'] ?? 100), 500);
        $offset      = absint($params['offset'] ?? 0);

        // Filter
        $filtered = array();
        foreach ($redirects as $r) {
            if ($status_code && ($r['status'] ?? 301) !== $status_code) {
                continue;
            }
            if ($search) {
                $haystack = ($r['from'] ?? '') . ' ' . ($r['to'] ?? '');
                if (stripos($haystack, $search) === false) {
                    continue;
                }
            }
            $filtered[] = array(
                'from_url'    => $r['from'] ?? '',
                'to_url'      => $r['to'] ?? '',
                'status_code' => $r['status'] ?? 301,
                'created_at'  => $r['created'] ?? null,
                'hit_count'   => $r['hits'] ?? 0,
            );
        }

        $total = count($filtered);
        $paged = array_slice($filtered, $offset, $limit);

        return array(
            'redirects' => $paged,
            'total'     => $total,
            'limit'     => $limit,
            'offset'    => $offset,
        );
    }

    /**
     * Handler: genseo/update-redirect
     * Cập nhật hoặc xóa redirect rule
     */
    public static function handle_update_redirect($params) {
        $from_url = sanitize_text_field($params['from_url'] ?? '');
        $action   = sanitize_text_field($params['action'] ?? '');

        if (empty($from_url) || !in_array($action, array('update', 'delete'), true)) {
            return new WP_Error('invalid_params', 'Cần from_url và action (update/delete)', array('status' => 400));
        }

        $redirects = get_option('genseo_redirects', array());
        if (!is_array($redirects)) {
            $redirects = array();
        }

        // Normalize from_url
        $site_url  = get_site_url();
        $from_path = $from_url;
        if (strpos($from_url, $site_url) === 0) {
            $from_path = substr($from_url, strlen($site_url));
        }

        $found_index = -1;
        foreach ($redirects as $i => $r) {
            if (($r['from'] ?? '') === $from_path) {
                $found_index = $i;
                break;
            }
        }

        if ($found_index === -1) {
            return new WP_Error('not_found', 'Không tìm thấy redirect rule cho: ' . $from_path, array('status' => 404));
        }

        if ($action === 'delete') {
            array_splice($redirects, $found_index, 1);
            update_option('genseo_redirects', $redirects);
            $result = array(
                'success'  => true,
                'action'   => 'deleted',
                'from_url' => $from_path,
            );
            do_action('genseo_mcp_ability_executed', 'genseo/update-redirect', $params, $result, true);
            return $result;
        }

        // Update
        $to_url = esc_url_raw($params['to_url'] ?? '');
        if (empty($to_url)) {
            return new WP_Error('missing_to_url', 'Cần to_url khi action=update', array('status' => 400));
        }

        // Chặn redirect loop
        $to_path = $to_url;
        if (strpos($to_url, $site_url) === 0) {
            $to_path = substr($to_url, strlen($site_url));
        }
        if ($from_path === $to_path) {
            return new WP_Error('redirect_loop', 'Redirect loop: from_url và to_url giống nhau', array('status' => 400));
        }

        $status_code = isset($params['status_code']) ? absint($params['status_code']) : ($redirects[$found_index]['status'] ?? 301);
        if (!in_array($status_code, array(301, 302), true)) {
            $status_code = 301;
        }

        $redirects[$found_index]['to']      = $to_url;
        $redirects[$found_index]['status']   = $status_code;
        $redirects[$found_index]['updated']  = current_time('mysql');

        update_option('genseo_redirects', $redirects);

        $result = array(
            'success'     => true,
            'action'      => 'updated',
            'from_url'    => $from_path,
            'to_url'      => $to_url,
            'status_code' => $status_code,
        );

        // Audit log
        do_action('genseo_mcp_ability_executed', 'genseo/update-redirect', $params, $result, true);

        return $result;
    }

    /**
     * Handler: genseo/get-internal-link-graph
     * Phân tích internal links toàn site
     */
    public static function handle_get_internal_link_graph($params) {
        $post_types    = self::parse_post_type_param($params);
        $limit         = min(absint($params['limit'] ?? 500), 2000);
        $include_stats = isset($params['include_stats']) ? (bool) $params['include_stats'] : true;
        $site_url      = get_site_url();

        $posts = get_posts(array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'numberposts'    => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $nodes = array();
        $edges = array();
        $inbound_count = array(); // post_id => count

        foreach ($posts as $post) {
            $permalink = get_permalink($post);

            // A4: Lấy focus keyword từ SEO plugins (Yoast, RankMath, GenSeo)
            $focus_keyword = '';
            $yoast_kw = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
            if (!empty($yoast_kw)) {
                $focus_keyword = $yoast_kw;
            } else {
                $rm_kw = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
                if (!empty($rm_kw)) {
                    // RankMath stores comma-separated, take first
                    $parts = explode(',', $rm_kw);
                    $focus_keyword = trim($parts[0]);
                } else {
                    $gs_kw = get_post_meta($post->ID, '_genseo_focus_keyword', true);
                    if (!empty($gs_kw)) {
                        $focus_keyword = $gs_kw;
                    }
                }
            }

            $nodes[] = array(
                'post_id'       => $post->ID,
                'url'           => str_replace($site_url, '', $permalink),
                'title'         => $post->post_title,
                'post_type'     => $post->post_type,
                'focus_keyword' => $focus_keyword,
            );

            // Parse links
            $content = do_blocks($post->post_content);
            if (preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $content, $matches)) {
                foreach ($matches[1] as $i => $href) {
                    // Chỉ internal links
                    $is_internal = (strpos($href, $site_url) === 0) || (strpos($href, '/') === 0 && strpos($href, '//') !== 0);
                    if (!$is_internal) {
                        continue;
                    }

                    $full_url  = (strpos($href, '/') === 0) ? $site_url . $href : $href;
                    $target_id = url_to_postid($full_url);
                    $is_nofollow = (bool) preg_match('/rel=["\'][^"\']*nofollow[^"\']*["\']/', $matches[0][$i]);

                    $edges[] = array(
                        'source_id'   => $post->ID,
                        'target_id'   => $target_id ?: null,
                        'target_url'  => str_replace($site_url, '', $href),
                        'anchor_text' => wp_strip_all_tags($matches[2][$i]),
                        'is_nofollow' => $is_nofollow,
                    );

                    if ($target_id) {
                        $inbound_count[$target_id] = ($inbound_count[$target_id] ?? 0) + 1;
                    }
                }
            }
        }

        $result = array(
            'nodes'           => $nodes,
            'edges'           => $edges,
            'total_processed' => count($posts),
        );

        if ($include_stats) {
            $node_ids = array_column($nodes, 'post_id');
            $orphan_pages = array();
            $top_linked   = array();
            $low_linked   = array();

            foreach ($nodes as $node) {
                $pid   = $node['post_id'];
                $count = $inbound_count[$pid] ?? 0;
                if ($count === 0) {
                    $orphan_pages[] = array(
                        'post_id' => $pid,
                        'url'     => $node['url'],
                        'title'   => $node['title'],
                    );
                }
            }

            // Sort inbound_count descending
            arsort($inbound_count);
            $i = 0;
            foreach ($inbound_count as $pid => $count) {
                $top_linked[] = array('post_id' => $pid, 'inbound_count' => $count);
                if (++$i >= 10) break;
            }

            $total_links = count($edges);
            $total_nodes = count($nodes);

            $result['stats'] = array(
                'total_nodes'                => $total_nodes,
                'total_edges'                => $total_links,
                'orphan_pages'               => $orphan_pages,
                'top_linked'                 => $top_linked,
                'avg_internal_links_per_post' => $total_nodes > 0 ? round($total_links / $total_nodes, 1) : 0,
            );
        }

        return $result;
    }

    /**
     * Handler: genseo/detect-broken-links
     * Phát hiện liên kết hỏng (internal + optional external)
     */
    public static function handle_detect_broken_links($params) {
        $site_url       = get_site_url();
        $check_external = !empty($params['check_external']);

        // Lấy posts để scan
        if (!empty($params['post_id'])) {
            $post = get_post(absint($params['post_id']));
            if (!$post) {
                return new WP_Error('not_found', 'Post không tồn tại', array('status' => 404));
            }
            $posts = array($post);
        } else {
            $limit = min(absint($params['limit'] ?? 50), 200);
            $posts = get_posts(array(
                'post_type'   => array('post', 'page'),
                'post_status' => 'publish',
                'numberposts' => $limit,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ));
        }

        $broken_links      = array();
        $total_checked     = 0;
        $checked_externals = array(); // cache

        foreach ($posts as $post) {
            $content = do_blocks($post->post_content);
            if (!preg_match_all('/<a\s[^>]*href=["\']([^"\'#]+)["\'][^>]*>(.*?)<\/a>/si', $content, $matches)) {
                continue;
            }

            foreach ($matches[1] as $i => $href) {
                $is_internal = (strpos($href, $site_url) === 0) || (strpos($href, '/') === 0 && strpos($href, '//') !== 0);

                if ($is_internal) {
                    $total_checked++;
                    $full_url  = (strpos($href, '/') === 0) ? $site_url . $href : $href;
                    $target_id = url_to_postid($full_url);

                    if ($target_id === 0) {
                        // URL không match post nào → possible broken
                        $broken_links[] = array(
                            'source_post_id' => $post->ID,
                            'source_url'     => str_replace($site_url, '', get_permalink($post)),
                            'target_url'     => $href,
                            'anchor_text'    => wp_strip_all_tags($matches[2][$i]),
                            'status'         => '404',
                            'link_type'      => 'internal',
                        );
                    } else {
                        $target_status = get_post_status($target_id);
                        if (in_array($target_status, array('trash', 'draft', 'private'), true)) {
                            $broken_links[] = array(
                                'source_post_id' => $post->ID,
                                'source_url'     => str_replace($site_url, '', get_permalink($post)),
                                'target_url'     => $href,
                                'anchor_text'    => wp_strip_all_tags($matches[2][$i]),
                                'status'         => $target_status,
                                'link_type'      => 'internal',
                            );
                        }
                    }
                } elseif ($check_external && preg_match('/^https?:\/\//', $href)) {
                    $total_checked++;
                    // Cache external check
                    if (!isset($checked_externals[$href])) {
                        $response = wp_remote_head($href, array('timeout' => 5, 'redirection' => 3));
                        if (is_wp_error($response)) {
                            $checked_externals[$href] = 'error';
                        } else {
                            $checked_externals[$href] = wp_remote_retrieve_response_code($response);
                        }
                    }
                    $ext_status = $checked_externals[$href];
                    if ($ext_status === 'error' || (is_int($ext_status) && $ext_status >= 400)) {
                        $broken_links[] = array(
                            'source_post_id' => $post->ID,
                            'source_url'     => str_replace($site_url, '', get_permalink($post)),
                            'target_url'     => $href,
                            'anchor_text'    => wp_strip_all_tags($matches[2][$i]),
                            'status'         => (string) $ext_status,
                            'link_type'      => 'external',
                        );
                    }
                }
            }
        }

        return array(
            'broken_links'       => $broken_links,
            'total_links_checked' => $total_checked,
            'broken_count'       => count($broken_links),
            'healthy_count'      => $total_checked - count($broken_links),
        );
    }

    // ============================================================
    // HANDLERS — PHASE 2: CONTENT DEEP ANALYSIS
    // ============================================================

    /**
     * Handler: genseo/get-schema-full
     * Trả về JSON-LD schema đầy đủ (Article/FAQ/HowTo)
     */
    public static function handle_get_schema_full($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $schema_type = get_post_meta($post_id, '_genseo_schema_type', true);
        if (empty($schema_type)) {
            $schema_type = 'Article';
        }

        $schemas        = array();
        $detected_types = array();
        $has_faq        = false;
        $has_howto      = false;

        // Article schema — luôn generate
        $article = GenSeo_Schema::generate_article_schema($post_id);
        if ($article) {
            $schemas[] = $article;
            $detected_types[] = 'Article';
        }

        // FAQ schema
        $faq_data = get_post_meta($post_id, '_genseo_faq_data', true);
        if (!empty($faq_data)) {
            $faq = GenSeo_Schema::generate_faq_schema($post_id);
            if ($faq) {
                $schemas[]  = $faq;
                $has_faq    = true;
                $detected_types[] = 'FAQPage';
            }
        }

        // HowTo schema
        $howto_data = get_post_meta($post_id, '_genseo_howto_data', true);
        if (!empty($howto_data)) {
            $howto = GenSeo_Schema::generate_howto_schema($post_id);
            if ($howto) {
                $schemas[]  = $howto;
                $has_howto  = true;
                $detected_types[] = 'HowTo';
            }
        }

        // Build raw JSON-LD giống output trong <head>
        $raw_jsonld = '';
        foreach ($schemas as $schema) {
            $raw_jsonld .= '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
        }

        return array(
            'schemas'        => $schemas,
            'detected_types' => $detected_types,
            'has_faq'        => $has_faq,
            'has_howto'      => $has_howto,
            'raw_jsonld'     => $raw_jsonld,
        );
    }

    /**
     * Handler: genseo/get-images-detail
     * Chi tiết images trong bài viết (featured + content)
     */
    public static function handle_get_images_detail($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $include_featured       = isset($params['include_featured']) ? (bool) $params['include_featured'] : true;
        $include_content_images = isset($params['include_content_images']) ? (bool) $params['include_content_images'] : true;

        $images    = array();
        $total_kb  = 0;
        $formats   = array();
        $missing_alt = 0;

        // Featured image
        if ($include_featured) {
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $img = self::get_image_detail($thumb_id, true);
                if ($img) {
                    $images[] = $img;
                    $total_kb += $img['file_size_kb'] ?? 0;
                    $ext = $img['format'] ?? 'unknown';
                    $formats[$ext] = ($formats[$ext] ?? 0) + 1;
                    if (empty($img['alt'])) $missing_alt++;
                }
            }
        }

        // Content images
        if ($include_content_images) {
            $post = get_post($post_id);
            $content = do_blocks($post->post_content);
            if (preg_match_all('/<img\s[^>]+>/si', $content, $img_matches)) {
                foreach ($img_matches[0] as $img_tag) {
                    $src = '';
                    if (preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $m)) {
                        $src = $m[1];
                    }
                    if (empty($src)) continue;

                    $alt = '';
                    if (preg_match('/alt=["\']([^"\']*?)["\']/i', $img_tag, $m)) {
                        $alt = $m[1];
                    }

                    // Attr width/height
                    $attr_w = 0;
                    $attr_h = 0;
                    if (preg_match('/width=["\'](\d+)["\']/i', $img_tag, $m)) $attr_w = (int) $m[1];
                    if (preg_match('/height=["\'](\d+)["\']/i', $img_tag, $m)) $attr_h = (int) $m[1];

                    // Lookup trong media library
                    $att_id = attachment_url_to_postid($src);
                    if ($att_id) {
                        $img = self::get_image_detail($att_id, false);
                        if ($img) {
                            // Override alt nếu HTML tag có
                            if (!empty($alt)) $img['alt'] = $alt;
                            $img['has_alt'] = !empty($alt);
                            $images[] = $img;
                            $total_kb += $img['file_size_kb'] ?? 0;
                            $ext = $img['format'] ?? 'unknown';
                            $formats[$ext] = ($formats[$ext] ?? 0) + 1;
                            if (empty($alt)) $missing_alt++;
                            continue;
                        }
                    }

                    // External image — chỉ biết src + alt
                    $ext = strtolower(pathinfo(wp_parse_url($src, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
                    if (empty($ext)) $ext = 'unknown';
                    $formats[$ext] = ($formats[$ext] ?? 0) + 1;
                    if (empty($alt)) $missing_alt++;

                    $images[] = array(
                        'src'              => $src,
                        'alt'              => $alt,
                        'width'            => $attr_w ?: null,
                        'height'           => $attr_h ?: null,
                        'format'           => $ext,
                        'file_size_kb'     => null,
                        'attachment_id'    => null,
                        'is_featured'      => false,
                        'has_alt'          => !empty($alt),
                        'in_media_library' => false,
                    );
                }
            }
        }

        return array(
            'images'               => $images,
            'total_images'         => count($images),
            'missing_alt_count'    => $missing_alt,
            'external_images_count' => count(array_filter($images, function($i) { return empty($i['in_media_library']); })),
            'total_size_kb'        => round($total_kb, 1),
            'formats'              => $formats,
        );
    }

    /**
     * Helper: Lấy chi tiết ảnh từ attachment ID
     */
    private static function get_image_detail($attachment_id, $is_featured) {
        $meta = wp_get_attachment_metadata($attachment_id);
        $url  = wp_get_attachment_url($attachment_id);
        $alt  = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

        if (!$url) return null;

        $file_path = get_attached_file($attachment_id);
        $file_size = $file_path && file_exists($file_path) ? round(filesize($file_path) / 1024, 1) : null;
        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));

        return array(
            'src'              => $url,
            'alt'              => $alt ?: '',
            'width'            => $meta['width'] ?? null,
            'height'           => $meta['height'] ?? null,
            'format'           => $ext ?: 'unknown',
            'file_size_kb'     => $file_size,
            'attachment_id'    => $attachment_id,
            'is_featured'      => $is_featured,
            'has_alt'          => !empty($alt),
            'in_media_library' => true,
        );
    }

    /**
     * Handler: genseo/batch-get-links-in-content
     * Trích xuất tất cả links từ nhiều posts
     */
    public static function handle_batch_get_links_in_content($params) {
        $post_ids  = $params['post_ids'] ?? array();
        $link_type = sanitize_text_field($params['link_type'] ?? 'all');
        $site_url  = get_site_url();

        if (!is_array($post_ids) || empty($post_ids)) {
            return new WP_Error('missing_post_ids', 'Cần truyền post_ids (array)', array('status' => 400));
        }
        $post_ids = array_slice(array_map('absint', $post_ids), 0, 100);

        $results = array();
        foreach ($post_ids as $pid) {
            $post = get_post($pid);
            if (!$post || $post->post_status === 'trash') continue;

            $content = do_blocks($post->post_content);
            $links = array();
            $internal_count = 0;
            $external_count = 0;

            if (preg_match_all('/<a\s[^>]*href=["\']([^"\'#]+)["\'][^>]*>(.*?)<\/a>/si', $content, $matches)) {
                foreach ($matches[1] as $i => $href) {
                    $is_internal = (strpos($href, $site_url) === 0) || (strpos($href, '/') === 0 && strpos($href, '//') !== 0);
                    $type        = $is_internal ? 'internal' : 'external';

                    if ($link_type !== 'all' && $link_type !== $type) {
                        if ($is_internal) $internal_count++;
                        else $external_count++;
                        continue;
                    }

                    $is_nofollow = (bool) preg_match('/rel=["\'][^"\']*nofollow[^"\']*["\']/', $matches[0][$i]);
                    $target_id   = null;
                    if ($is_internal) {
                        $full_url  = (strpos($href, '/') === 0) ? $site_url . $href : $href;
                        $target_id = url_to_postid($full_url) ?: null;
                        $internal_count++;
                    } else {
                        $external_count++;
                    }

                    $links[] = array(
                        'url'            => $href,
                        'anchor_text'    => wp_strip_all_tags($matches[2][$i]),
                        'type'           => $type,
                        'is_nofollow'    => $is_nofollow,
                        'target_post_id' => $target_id,
                    );
                }
            }

            $results[] = array(
                'post_id'        => $pid,
                'links'          => $links,
                'internal_count' => $internal_count,
                'external_count' => $external_count,
            );
        }

        return array(
            'results'     => $results,
            'total_posts' => count($results),
        );
    }

    // ============================================================
    // HANDLERS — PHASE 3: SITEMAP, ROBOTS & INDEXATION
    // ============================================================

    /**
     * Handler: genseo/get-sitemap-urls
     * Phân tích sitemap coverage
     */
    public static function handle_get_sitemap_urls($params) {
        $compare     = isset($params['compare_with_posts']) ? (bool) $params['compare_with_posts'] : true;
        $post_types  = self::parse_post_type_param($params);
        $site_url    = get_site_url();

        $sitemap_urls    = array();
        $sitemap_source  = 'unknown';
        $sitemaps_detected = array();

        // Thử WP 5.5+ Sitemaps API
        if (function_exists('wp_sitemaps_get_server')) {
            $server = wp_sitemaps_get_server();
            if ($server) {
                $sitemap_source = 'wp-core';
                $registry = $server->registry;
                $providers = $registry->get_all_sitemaps();

                foreach ($providers as $name => $provider) {
                    $object_subtypes = $provider->get_object_subtypes();
                    if (empty($object_subtypes)) {
                        $object_subtypes = array('' => null);
                    }
                    foreach ($object_subtypes as $subtype => $obj) {
                        $max_pages = $provider->get_max_num_pages($subtype);
                        for ($page = 1; $page <= $max_pages; $page++) {
                            $entries = $provider->get_url_list($page, $subtype);
                            foreach ($entries as $entry) {
                                $url = $entry['loc'] ?? '';
                                if ($url) {
                                    $sitemap_urls[] = array(
                                        'url'     => $url,
                                        'lastmod' => $entry['lastmod'] ?? null,
                                    );
                                }
                            }
                        }
                        $sitemaps_detected[] = 'wp-sitemap-' . $name . ($subtype ? '-' . $subtype : '') . '.xml';
                    }
                }
                $sitemaps_detected[] = 'wp-sitemap.xml';
            }
        }

        // Fallback: parse sitemap.xml
        if (empty($sitemap_urls)) {
            $sitemap_path = ABSPATH . 'sitemap.xml';
            if (file_exists($sitemap_path)) {
                $sitemap_source = 'file';
                $xml_content = file_get_contents($sitemap_path);
                if ($xml_content && preg_match_all('/<loc>([^<]+)<\/loc>/i', $xml_content, $m)) {
                    foreach ($m[1] as $url) {
                        $sitemap_urls[] = array('url' => $url, 'lastmod' => null);
                    }
                }
                $sitemaps_detected[] = 'sitemap.xml';
            }
        }

        $result = array(
            'sitemap_urls'      => $sitemap_urls,
            'total_in_sitemap'  => count($sitemap_urls),
            'sitemap_source'    => $sitemap_source,
            'sitemaps_detected' => $sitemaps_detected,
        );

        if ($compare) {
            $published = get_posts(array(
                'post_type'   => $post_types,
                'post_status' => 'publish',
                'numberposts' => -1,
                'fields'      => 'ids',
            ));

            $post_urls = array();
            foreach ($published as $pid) {
                $post_urls[$pid] = get_permalink($pid);
            }

            $sitemap_url_list = array_column($sitemap_urls, 'url');
            $in_sitemap     = 0;
            $not_in_sitemap = array();

            foreach ($post_urls as $pid => $url) {
                if (in_array($url, $sitemap_url_list, true)) {
                    $in_sitemap++;
                } else {
                    // Detect reason
                    $reason = 'unknown';
                    $noindex_yoast = get_post_meta($pid, '_yoast_wpseo_meta-robots-noindex', true);
                    $noindex_rm    = get_post_meta($pid, 'rank_math_robots', true);
                    if ($noindex_yoast === '1') {
                        $reason = 'noindex (Yoast)';
                    } elseif (is_string($noindex_rm) && strpos($noindex_rm, 'noindex') !== false) {
                        $reason = 'noindex (RankMath)';
                    }

                    $not_in_sitemap[] = array(
                        'post_id' => $pid,
                        'url'     => str_replace($site_url, '', $url),
                        'title'   => get_the_title($pid),
                        'reason'  => $reason,
                    );
                }
            }

            // Sitemap-only URLs
            $sitemap_only = array();
            foreach ($sitemap_url_list as $surl) {
                $pid = url_to_postid($surl);
                if ($pid === 0) {
                    $sitemap_only[] = array('url' => $surl, 'exists' => false);
                }
            }

            $total_posts = count($post_urls);
            $result['comparison'] = array(
                'posts_in_sitemap'     => $in_sitemap,
                'posts_not_in_sitemap' => $not_in_sitemap,
                'sitemap_only_urls'    => $sitemap_only,
                'coverage_percentage'  => $total_posts > 0 ? round(($in_sitemap / $total_posts) * 100, 1) : 0,
            );
        }

        return $result;
    }

    /**
     * Handler: genseo/get-robots-txt
     * Đọc và phân tích robots.txt
     */
    public static function handle_get_robots_txt($params) {
        $analyze = isset($params['analyze']) ? (bool) $params['analyze'] : true;

        $raw_content = '';
        $source      = 'none';
        $is_virtual  = false;

        // Đọc file robots.txt
        $robots_path = ABSPATH . 'robots.txt';
        if (file_exists($robots_path)) {
            $raw_content = file_get_contents($robots_path);
            $source = 'file';
        } else {
            // WP virtual robots.txt
            ob_start();
            do_action('do_robots');
            $raw_content = ob_get_clean();
            if (!empty($raw_content)) {
                $source = 'virtual';
                $is_virtual = true;
            }
        }

        if (empty($raw_content)) {
            return array(
                'raw_content'       => '',
                'rules'             => array(),
                'sitemap_references' => array(),
                'issues'            => array(array(
                    'severity'   => 'warning',
                    'message'    => 'Không tìm thấy robots.txt',
                    'suggestion' => 'Tạo robots.txt hoặc bật trong Settings > Reading',
                )),
                'source'     => $source,
                'is_virtual' => $is_virtual,
            );
        }

        // Parse rules
        $rules = array();
        $sitemap_refs = array();
        $current_agent = '*';

        $lines = explode("\n", $raw_content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;

            if (preg_match('/^User-agent:\s*(.+)/i', $line, $m)) {
                $current_agent = trim($m[1]);
                if (!isset($rules[$current_agent])) {
                    $rules[$current_agent] = array('user_agent' => $current_agent, 'allow' => array(), 'disallow' => array());
                }
            } elseif (preg_match('/^Allow:\s*(.+)/i', $line, $m)) {
                $rules[$current_agent]['allow'][] = trim($m[1]);
            } elseif (preg_match('/^Disallow:\s*(.+)/i', $line, $m)) {
                $rules[$current_agent]['disallow'][] = trim($m[1]);
            } elseif (preg_match('/^Sitemap:\s*(.+)/i', $line, $m)) {
                $sitemap_refs[] = trim($m[1]);
            }
        }

        $result = array(
            'raw_content'        => $raw_content,
            'rules'              => array_values($rules),
            'sitemap_references' => $sitemap_refs,
            'source'             => $source,
            'is_virtual'         => $is_virtual,
        );

        if ($analyze) {
            $issues = array();

            // Check block Googlebot
            if (isset($rules['Googlebot'])) {
                $gb = $rules['Googlebot'];
                if (in_array('/', $gb['disallow'], true)) {
                    $issues[] = array(
                        'severity'   => 'critical',
                        'message'    => 'Googlebot bị block hoàn toàn (Disallow: /)',
                        'suggestion' => 'Xóa dòng "Disallow: /" trong User-agent: Googlebot',
                    );
                }
            }

            // Check block all
            if (isset($rules['*'])) {
                $all = $rules['*'];
                if (in_array('/', $all['disallow'], true)) {
                    $issues[] = array(
                        'severity'   => 'critical',
                        'message'    => 'Tất cả bots bị block (User-agent: * / Disallow: /)',
                        'suggestion' => 'Có thể site đang ẩn khỏi search engines. Xóa "Disallow: /" nếu muốn index.',
                    );
                }
            }

            // Check sitemap reference
            if (empty($sitemap_refs)) {
                $issues[] = array(
                    'severity'   => 'warning',
                    'message'    => 'Thiếu Sitemap reference trong robots.txt',
                    'suggestion' => 'Thêm: Sitemap: ' . get_site_url() . '/wp-sitemap.xml',
                );
            }

            $result['issues'] = $issues;
        }

        return $result;
    }

    /**
     * Handler: genseo/get-content-stats
     * Thống kê chất lượng content toàn site
     */
    public static function handle_get_content_stats($params) {
        $post_types  = self::parse_post_type_param($params);
        $post_status = sanitize_text_field($params['post_status'] ?? 'publish');

        $posts = get_posts(array(
            'post_type'   => $post_types,
            'post_status' => $post_status,
            'numberposts' => -1,
        ));

        $total = count($posts);
        $thin_threshold = 300;

        // Counters
        $total_word_count     = 0;
        $thin_count           = 0;
        $no_images            = 0;
        $no_headings          = 0;
        $no_featured_image    = 0;
        $with_seo_title       = 0;
        $with_meta_desc       = 0;
        $with_focus_keyword   = 0;
        $with_schema          = 0;
        $with_og_image        = 0;
        $categories_dist      = array();
        $date_dist            = array();

        foreach ($posts as $post) {
            $content = wp_strip_all_tags($post->post_content);
            $wc      = str_word_count($content);
            $total_word_count += $wc;
            if ($wc < $thin_threshold) $thin_count++;

            // Images check
            if (!preg_match('/<img\s/i', $post->post_content)) $no_images++;
            // Headings check
            if (!preg_match('/<h[1-6][^>]*>/i', $post->post_content)) $no_headings++;
            // Featured image
            if (!has_post_thumbnail($post->ID)) $no_featured_image++;

            // SEO meta — check GenSeo + Yoast + RankMath
            $seo_title  = get_post_meta($post->ID, '_genseo_seo_title', true) ?: self::get_seo_plugin_meta($post->ID, 'title');
            $meta_desc  = get_post_meta($post->ID, '_genseo_meta_description', true) ?: self::get_seo_plugin_meta($post->ID, 'description');
            $focus_kw   = get_post_meta($post->ID, '_genseo_focus_keyword', true) ?: self::get_seo_plugin_meta($post->ID, 'focus_keyword');
            $schema     = get_post_meta($post->ID, '_genseo_schema_type', true);
            $og_image   = get_post_meta($post->ID, '_genseo_og_image', true) ?: self::get_seo_plugin_meta($post->ID, 'og_image');

            if (!empty($seo_title)) $with_seo_title++;
            if (!empty($meta_desc)) $with_meta_desc++;
            if (!empty($focus_kw))  $with_focus_keyword++;
            if (!empty($schema))    $with_schema++;
            if (!empty($og_image))  $with_og_image++;

            // Category distribution
            $cats = get_the_category($post->ID);
            foreach ($cats as $cat) {
                $categories_dist[$cat->name] = ($categories_dist[$cat->name] ?? 0) + 1;
            }

            // Date distribution (month)
            $month = date('Y-m', strtotime($post->post_date));
            $date_dist[$month] = ($date_dist[$month] ?? 0) + 1;
        }

        arsort($categories_dist);
        ksort($date_dist);

        return array(
            'total_posts' => $total,
            'content_quality' => array(
                'avg_word_count'              => $total > 0 ? round($total_word_count / $total) : 0,
                'thin_content_count'          => $thin_count,
                'thin_content_threshold'      => $thin_threshold,
                'posts_without_images'        => $no_images,
                'posts_without_headings'      => $no_headings,
                'posts_without_featured_image' => $no_featured_image,
            ),
            'seo_coverage' => array(
                'posts_with_seo_title'        => $with_seo_title,
                'posts_with_meta_description'  => $with_meta_desc,
                'posts_with_focus_keyword'     => $with_focus_keyword,
                'posts_with_schema'            => $with_schema,
                'posts_with_og_image'          => $with_og_image,
            ),
            'categories_distribution' => $categories_dist,
            'date_distribution'       => $date_dist,
        );
    }

    // ============================================================
    // PHASE 4 HANDLERS: TAXONOMY & REVISION SEO
    // ============================================================

    /**
     * Handler: get-taxonomy-seo
     * Lấy SEO meta cho taxonomy terms (Yoast/RankMath)
     *
     * @param array $params
     * @return array|WP_Error
     */
    public static function handle_get_taxonomy_seo($params) {
        if (empty($params['taxonomy'])) {
            return new WP_Error('missing_taxonomy', 'Thiếu param taxonomy', array('status' => 400));
        }

        $taxonomy = sanitize_key($params['taxonomy']);
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error('invalid_taxonomy', 'Taxonomy không tồn tại: ' . $taxonomy, array('status' => 400));
        }

        $hide_empty = isset($params['hide_empty']) ? (bool) $params['hide_empty'] : true;
        $limit      = min(absint($params['limit'] ?? 100), 500);

        $terms = get_terms(array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => $hide_empty,
            'number'     => $limit,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ));

        if (is_wp_error($terms)) {
            return $terms;
        }

        $result_terms    = array();
        $terms_with_seo  = 0;
        $terms_without   = 0;

        foreach ($terms as $term) {
            $seo_title   = self::get_seo_plugin_term_meta($term->term_id, 'title');
            $meta_desc   = self::get_seo_plugin_term_meta($term->term_id, 'description');
            $focus_kw    = self::get_seo_plugin_term_meta($term->term_id, 'focus_keyword');
            $has_seo     = !empty($seo_title) || !empty($meta_desc) || !empty($focus_kw);

            if ($has_seo) {
                $terms_with_seo++;
            } else {
                $terms_without++;
            }

            $seo_source = '';
            if ($has_seo) {
                $seo_source = genseo_is_yoast_active() ? 'yoast' : (genseo_is_rankmath_active() ? 'rankmath' : 'unknown');
            }

            $term_link = get_term_link($term);

            $result_terms[] = array(
                'term_id'          => $term->term_id,
                'name'             => $term->name,
                'slug'             => $term->slug,
                'url'              => is_wp_error($term_link) ? '' : $term_link,
                'count'            => $term->count,
                'parent_id'        => $term->parent,
                'seo_title'        => $seo_title,
                'meta_description' => $meta_desc,
                'focus_keyword'    => $focus_kw,
                'has_seo_meta'     => $has_seo,
                'seo_plugin_source' => $seo_source,
            );
        }

        return array(
            'terms'            => $result_terms,
            'total'            => count($result_terms),
            'terms_with_seo'   => $terms_with_seo,
            'terms_without_seo' => $terms_without,
        );
    }

    /**
     * Handler: get-post-revisions
     * Lịch sử revision của bài viết
     *
     * @param array $params
     * @return array|WP_Error
     */
    public static function handle_get_post_revisions($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $limit = min(absint($params['limit'] ?? 10), 50);

        $revisions = wp_get_post_revisions($post_id, array(
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $parent_post    = get_post($post_id);
        $result_revisions = array();
        $prev_title     = $parent_post->post_title;
        $prev_content   = $parent_post->post_content;
        $prev_excerpt   = $parent_post->post_excerpt;

        // Revisions are in DESC order, but we compare each with the one before it
        $revisions_arr = array_values($revisions);

        for ($i = 0; $i < count($revisions_arr); $i++) {
            $rev = $revisions_arr[$i];
            // Next revision (older) for comparison
            $older = isset($revisions_arr[$i + 1]) ? $revisions_arr[$i + 1] : $parent_post;

            $title_changed   = $rev->post_title !== $older->post_title;
            $content_changed = $rev->post_content !== $older->post_content;
            $excerpt_changed = $rev->post_excerpt !== $older->post_excerpt;

            $author = get_userdata($rev->post_author);

            $result_revisions[] = array(
                'revision_id'     => $rev->ID,
                'date'            => $rev->post_date,
                'author'          => $author ? $author->display_name : 'Unknown',
                'title_changed'   => $title_changed,
                'content_changed' => $content_changed,
                'excerpt_changed' => $excerpt_changed,
                'title_before'    => $title_changed ? $older->post_title : null,
                'title_after'     => $title_changed ? $rev->post_title : null,
            );
        }

        return array(
            'revisions'       => $result_revisions,
            'total_revisions' => wp_revisions_enabled($parent_post) ? count(wp_get_post_revisions($post_id, array('posts_per_page' => -1))) : 0,
        );
    }

    // ============================================================
    // PHASE 5 HANDLERS: WORDPRESS SITE INTELLIGENCE
    // ============================================================

    /**
     * Handler: get-active-plugins
     * Danh sách plugins + phân loại
     *
     * @param array $params
     * @return array
     */
    public static function handle_get_active_plugins($params) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins   = get_plugins();
        $active_slugs  = get_option('active_plugins', array());
        $seo_keywords  = array('seo', 'yoast', 'rankmath', 'rank-math', 'aioseo', 'all-in-one-seo');
        $cache_keywords = array('cache', 'wp-super-cache', 'w3-total-cache', 'litespeed', 'wp-fastest', 'autoptimize', 'wp-rocket');

        $plugins_list  = array();
        $seo_plugins   = array();
        $cache_plugins = array();
        $has_woo       = false;
        $total_active  = 0;

        foreach ($all_plugins as $slug => $plugin_data) {
            $is_active = in_array($slug, $active_slugs, true);
            if ($is_active) {
                $total_active++;
            }

            $plugins_list[] = array(
                'name'      => $plugin_data['Name'] ?? '',
                'slug'      => $slug,
                'version'   => $plugin_data['Version'] ?? '',
                'is_active' => $is_active,
                'author'    => wp_strip_all_tags($plugin_data['Author'] ?? ''),
            );

            if ($is_active) {
                $slug_lower = strtolower($slug);
                foreach ($seo_keywords as $kw) {
                    if (strpos($slug_lower, $kw) !== false) {
                        $seo_plugins[] = $slug;
                        break;
                    }
                }
                foreach ($cache_keywords as $kw) {
                    if (strpos($slug_lower, $kw) !== false) {
                        $cache_plugins[] = $slug;
                        break;
                    }
                }
                if (strpos($slug_lower, 'woocommerce') !== false) {
                    $has_woo = true;
                }
            }
        }

        return array(
            'plugins'         => $plugins_list,
            'total_active'    => $total_active,
            'total_installed' => count($all_plugins),
            'seo_plugins'     => $seo_plugins,
            'cache_plugins'   => $cache_plugins,
            'has_woocommerce' => $has_woo,
        );
    }

    /**
     * Handler: get-theme-info
     * Thông tin theme hiện tại
     *
     * @param array $params
     * @return array
     */
    public static function handle_get_theme_info($params) {
        $theme = wp_get_theme();
        $parent = $theme->parent();

        $check_supports = array('title-tag', 'custom-logo', 'post-thumbnails', 'woocommerce');
        $supports = array();
        foreach ($check_supports as $feature) {
            $supports[$feature] = current_theme_supports($feature);
        }

        return array(
            'name'           => $theme->get('Name'),
            'version'        => $theme->get('Version'),
            'is_child_theme' => $theme->parent() !== false,
            'parent_theme'   => $parent ? $parent->get('Name') : null,
            'template_dir'   => get_template_directory(),
            'supports'       => $supports,
        );
    }

    /**
     * Handler: get-navigation-structure
     * Cấu trúc menu navigation (hierarchy)
     *
     * @param array $params
     * @return array
     */
    public static function handle_get_navigation_structure($params) {
        $locations      = get_nav_menu_locations();
        $registered     = get_registered_nav_menus();
        $menu_location  = !empty($params['menu_location']) ? sanitize_text_field($params['menu_location']) : '';

        $menus_result = array();

        foreach ($registered as $loc_slug => $loc_label) {
            if ($menu_location && $loc_slug !== $menu_location) {
                continue;
            }

            $menu_id = isset($locations[$loc_slug]) ? $locations[$loc_slug] : 0;
            if (!$menu_id) {
                continue;
            }

            $menu_obj = wp_get_nav_menu_object($menu_id);
            if (!$menu_obj) {
                continue;
            }

            $items = wp_get_nav_menu_items($menu_id, array('update_post_term_cache' => false));
            if (!$items) {
                $items = array();
            }

            // Build hierarchy
            $items_by_parent = array();
            foreach ($items as $item) {
                $parent_id = (int) $item->menu_item_parent;
                $items_by_parent[$parent_id][] = $item;
            }

            $hierarchy = self::build_menu_hierarchy($items_by_parent, 0);

            $menus_result[] = array(
                'name'        => $menu_obj->name,
                'location'    => $loc_slug,
                'items'       => $hierarchy,
                'total_items' => count($items),
            );
        }

        return array(
            'menus'                => $menus_result,
            'registered_locations' => array_keys($registered),
            'total_menus'          => count($menus_result),
        );
    }

    /**
     * Build menu hierarchy recursively
     *
     * @param array $items_by_parent Items grouped by parent
     * @param int   $parent_id       Parent ID
     * @return array
     */
    private static function build_menu_hierarchy($items_by_parent, $parent_id, $depth = 0) {
        if ($depth > 10 || empty($items_by_parent[$parent_id])) {
            return array();
        }

        $result = array();
        foreach ($items_by_parent[$parent_id] as $item) {
            $entry = array(
                'title'     => $item->title,
                'url'       => $item->url,
                'type'      => $item->type,
                'object_id' => (int) $item->object_id ?: null,
                'children'  => self::build_menu_hierarchy($items_by_parent, (int) $item->ID, $depth + 1),
            );
            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Handler: get-site-health
     * Chẩn đoán sức khỏe site — reuse GenSeo_Diagnostic
     *
     * @param array $params
     * @return array
     */
    public static function handle_get_site_health($params) {
        global $wp_version;

        // Basic system info
        $result = array(
            'wordpress' => array(
                'version'      => $wp_version,
                'is_multisite' => is_multisite(),
            ),
            'php' => array(
                'version'            => PHP_VERSION,
                'memory_limit'       => ini_get('memory_limit'),
                'max_execution_time' => (int) ini_get('max_execution_time'),
            ),
            'server' => array(
                'software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown',
                'is_https' => is_ssl(),
            ),
            'debug_mode'       => defined('WP_DEBUG') && WP_DEBUG,
            'max_upload_size_mb' => round(wp_max_upload_size() / (1024 * 1024), 1),
        );

        // Reuse diagnostic tests if available
        if (class_exists('GenSeo_Diagnostic')) {
            try {
                $diag_groups = GenSeo_Diagnostic::get_test_results();
                $result['diagnostic_groups'] = $diag_groups;
            } catch (\Throwable $e) {
                $result['diagnostic_groups'] = array();
                $result['diagnostic_error']  = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Handler: analyze-permalink-structure
     * Phân tích cấu trúc permalink
     *
     * @param array $params
     * @return array
     */
    public static function handle_analyze_permalink_structure($params) {
        $structure = get_option('permalink_structure', '');
        $issues    = array();

        // Check SEO-friendliness
        $is_plain = empty($structure);
        $is_seo_friendly = !$is_plain && strpos($structure, '%postname%') !== false;

        if ($is_plain) {
            $issues[] = array(
                'severity'   => 'critical',
                'message'    => 'Đang dùng Plain permalink (?p=123) — rất xấu cho SEO',
                'suggestion' => 'Đổi sang /%postname%/ trong Settings > Permalinks',
            );
        } elseif (!$is_seo_friendly) {
            $issues[] = array(
                'severity'   => 'warning',
                'message'    => 'Permalink không chứa %postname% — URL không mô tả nội dung',
                'suggestion' => 'Cân nhắc đổi sang /%postname%/ hoặc /%category%/%postname%/',
            );
        }

        // Check for date in URL
        if (strpos($structure, '%year%') !== false || strpos($structure, '%monthnum%') !== false) {
            $issues[] = array(
                'severity'   => 'info',
                'message'    => 'URL chứa ngày tháng — có thể khiến content cũ trông outdated',
                'suggestion' => 'Cân nhắc đổi sang /%postname%/ (cần setup redirects nếu site đã lâu)',
            );
        }

        // Check for category in URL
        if (strpos($structure, '%category%') !== false) {
            $issues[] = array(
                'severity'   => 'info',
                'message'    => 'URL chứa category — URL dài hơn cần thiết',
                'suggestion' => 'Cân nhắc đổi sang /%postname%/ cho URL ngắn gọn hơn',
            );
        }

        $has_trailing_slash = !empty($structure) && substr($structure, -1) === '/';

        // Sample URLs
        $sample_urls = array();
        $sample_post = get_posts(array('numberposts' => 1, 'post_type' => 'post', 'post_status' => 'publish'));
        if ($sample_post) {
            $sample_urls['post'] = get_permalink($sample_post[0]);
        }
        $sample_page = get_posts(array('numberposts' => 1, 'post_type' => 'page', 'post_status' => 'publish'));
        if ($sample_page) {
            $sample_urls['page'] = get_permalink($sample_page[0]);
        }
        $sample_cat = get_terms(array('taxonomy' => 'category', 'number' => 1, 'hide_empty' => false));
        if (!is_wp_error($sample_cat) && !empty($sample_cat)) {
            $sample_urls['category'] = get_term_link($sample_cat[0]);
        }

        return array(
            'structure'          => $structure ?: '?p=ID (Plain)',
            'is_seo_friendly'    => $is_seo_friendly,
            'sample_urls'        => $sample_urls,
            'issues'             => $issues,
            'has_trailing_slash' => $has_trailing_slash,
            'uses_www'           => strpos(home_url(), '://www.') !== false,
        );
    }

    /**
     * Handler: get-site-settings
     * Cài đặt WordPress ảnh hưởng SEO
     *
     * @param array $params
     * @return array
     */
    public static function handle_get_site_settings($params) {
        $blog_public = (int) get_option('blog_public', 1);

        return array(
            'indexing' => array(
                'blog_public'                => $blog_public,
                'is_blocking_search_engines' => $blog_public === 0,
            ),
            'content' => array(
                'posts_per_page'      => (int) get_option('posts_per_page', 10),
                'posts_per_rss'       => (int) get_option('posts_per_rss', 10),
                'default_category'    => get_cat_name((int) get_option('default_category', 1)),
                'default_post_format' => get_option('default_post_format', 'standard') ?: 'standard',
            ),
            'media' => array(
                'thumbnail_size' => array(
                    'width'  => (int) get_option('thumbnail_size_w', 150),
                    'height' => (int) get_option('thumbnail_size_h', 150),
                ),
                'medium_size' => array(
                    'width'  => (int) get_option('medium_size_w', 300),
                    'height' => (int) get_option('medium_size_h', 300),
                ),
                'large_size' => array(
                    'width'  => (int) get_option('large_size_w', 1024),
                    'height' => (int) get_option('large_size_h', 1024),
                ),
                'uploads_use_yearmonth_folders' => (bool) get_option('uploads_use_yearmonth_folders', 1),
            ),
            'locale' => array(
                'language'    => get_locale(),
                'charset'     => get_bloginfo('charset'),
                'timezone'    => wp_timezone_string(),
                'date_format' => get_option('date_format'),
            ),
            'registration' => array(
                'users_can_register' => (bool) get_option('users_can_register'),
                'default_role'       => get_option('default_role', 'subscriber'),
            ),
        );
    }

    // ============================================================
    // HELPER: VALIDATION
    // ============================================================

    /**
     * Validate post_id từ params
     *
     * @param array $params Params chứa post_id
     * @return int|WP_Error Post ID hợp lệ hoặc WP_Error
     */
    private static function validate_post_id($params) {
        if (empty($params['post_id'])) {
            return new WP_Error('missing_post_id', 'Thiếu post_id', array('status' => 400));
        }

        $post_id = absint($params['post_id']);
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Không tìm thấy bài viết với ID: ' . $post_id, array('status' => 404));
        }

        // Chỉ cho phép public post types
        $allowed_types = get_post_types(array('public' => true));
        if (!in_array($post->post_type, $allowed_types, true)) {
            return new WP_Error('invalid_post_type', 'Post type không được hỗ trợ: ' . $post->post_type, array('status' => 400));
        }

        return $post_id;
    }

    /**
     * Parse post_type param từ request — CSV hoặc mặc định post,page
     * Chỉ cho phép public post types (loại attachment)
     *
     * @param array $params
     * @return array Mảng post type names hợp lệ
     */
    private static function parse_post_type_param($params) {
        $allowed = get_post_types(array('public' => true));
        unset($allowed['attachment']);

        if (!empty($params['post_type'])) {
            $requested = array_map('trim', explode(',', sanitize_text_field($params['post_type'])));
            $valid = array_values(array_intersect($requested, array_keys($allowed)));
            if (!empty($valid)) {
                return $valid;
            }
        }

        // Mặc định: post + page
        return array('post', 'page');
    }

    // ============================================================
    // HELPER: SEO PLUGIN META
    // ============================================================

    /**
     * Lấy SEO field cho post: ưu tiên GenSeo meta, fallback sang Yoast/RankMath
     *
     * @param int    $post_id Post ID
     * @param string $field   'title' | 'description' | 'focus_keyword'
     * @return string
     */
    private static function get_seo_field($post_id, $field) {
        $genseo_keys = array(
            'title'         => '_genseo_seo_title',
            'description'   => '_genseo_meta_desc',
            'focus_keyword' => '_genseo_focus_keyword',
        );

        $meta_key = $genseo_keys[$field] ?? '';
        if ($meta_key) {
            $value = get_post_meta($post_id, $meta_key, true);
            if (!empty($value)) {
                return $value;
            }
        }

        return self::get_seo_plugin_meta($post_id, $field);
    }

    /**
     * Lấy SEO meta từ Yoast hoặc RankMath (fallback)
     *
     * @param int    $post_id Post ID
     * @param string $field   'title' | 'description' | 'focus_keyword'
     * @return string
     */
    private static function get_seo_plugin_meta($post_id, $field) {
        // Yoast
        if (genseo_is_yoast_active()) {
            switch ($field) {
                case 'title':
                    return get_post_meta($post_id, '_yoast_wpseo_title', true) ?: '';
                case 'description':
                    return get_post_meta($post_id, '_yoast_wpseo_metadesc', true) ?: '';
                case 'focus_keyword':
                    return get_post_meta($post_id, '_yoast_wpseo_focuskw', true) ?: '';
            }
        }

        // RankMath
        if (genseo_is_rankmath_active()) {
            switch ($field) {
                case 'title':
                    return get_post_meta($post_id, 'rank_math_title', true) ?: '';
                case 'description':
                    return get_post_meta($post_id, 'rank_math_description', true) ?: '';
                case 'focus_keyword':
                    return get_post_meta($post_id, 'rank_math_focus_keyword', true) ?: '';
            }
        }

        return '';
    }

    /**
     * Lấy SEO meta từ Yoast hoặc RankMath cho taxonomy term
     *
     * @param int    $term_id Term ID
     * @param string $field   'title' | 'description' | 'focus_keyword'
     * @return string
     */
    private static function get_seo_plugin_term_meta($term_id, $field) {
        // Yoast (term meta stored via wpseo_taxonomy_meta option or term meta)
        if (genseo_is_yoast_active()) {
            switch ($field) {
                case 'title':
                    return get_term_meta($term_id, 'wpseo_title', true) ?: '';
                case 'description':
                    return get_term_meta($term_id, 'wpseo_desc', true) ?: '';
                case 'focus_keyword':
                    return get_term_meta($term_id, 'wpseo_focuskw', true) ?: '';
            }
        }

        // RankMath
        if (genseo_is_rankmath_active()) {
            switch ($field) {
                case 'title':
                    return get_term_meta($term_id, 'rank_math_title', true) ?: '';
                case 'description':
                    return get_term_meta($term_id, 'rank_math_description', true) ?: '';
                case 'focus_keyword':
                    return get_term_meta($term_id, 'rank_math_focus_keyword', true) ?: '';
            }
        }

        return '';
    }

    /**
     * Lấy OG field từ Yoast hoặc RankMath
     *
     * @param int    $post_id Post ID
     * @param string $field   'title' | 'description'
     * @return string
     */
    private static function get_og_field($post_id, $field) {
        if (genseo_is_yoast_active()) {
            switch ($field) {
                case 'title':
                    return get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true) ?: '';
                case 'description':
                    return get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true) ?: '';
            }
        }

        if (genseo_is_rankmath_active()) {
            switch ($field) {
                case 'title':
                    return get_post_meta($post_id, 'rank_math_facebook_title', true) ?: '';
                case 'description':
                    return get_post_meta($post_id, 'rank_math_facebook_description', true) ?: '';
            }
        }

        // Fallback: GenSeo OG meta
        switch ($field) {
            case 'title':
                return get_post_meta($post_id, '_genseo_og_title', true) ?: '';
            case 'description':
                return get_post_meta($post_id, '_genseo_og_description', true) ?: '';
        }

        return '';
    }

    // ============================================================
    // HELPER: SEO PLUGIN SYNC
    // ============================================================

    /**
     * Sync GenSeo meta sang SEO plugin đang active (Yoast/RankMath)
     *
     * @param int $post_id Post ID
     */
    private static function sync_to_seo_plugin($post_id) {
        // Sync sang RankMath
        if (genseo_is_rankmath_active() && genseo_get_setting('enable_rankmath_sync', true)) {
            self::sync_to_rankmath($post_id);
        }

        // Sync sang Yoast
        if (genseo_is_yoast_active() && genseo_get_setting('enable_yoast_sync', true)) {
            self::sync_to_yoast($post_id);
        }
    }

    /**
     * Sync GenSeo meta sang RankMath
     *
     * @param int $post_id Post ID
     */
    private static function sync_to_rankmath($post_id) {
        // Mapping: GenSeo meta → RankMath meta
        $mapping = array(
            '_genseo_seo_title'     => 'rank_math_title',
            '_genseo_meta_desc'     => 'rank_math_description',
            '_genseo_focus_keyword' => 'rank_math_focus_keyword',
            '_genseo_canonical_url' => 'rank_math_canonical_url',
            '_genseo_og_image'      => 'rank_math_facebook_image',
            '_genseo_og_image_id'   => 'rank_math_facebook_image_id',
        );

        foreach ($mapping as $genseo_key => $rankmath_key) {
            $value = get_post_meta($post_id, $genseo_key, true);
            if (!empty($value)) {
                update_post_meta($post_id, $rankmath_key, $value);
            }
        }

        // OG title/description
        $og_title = get_post_meta($post_id, '_genseo_og_title', true);
        if (!empty($og_title)) {
            update_post_meta($post_id, 'rank_math_facebook_title', $og_title);
        }
        $og_desc = get_post_meta($post_id, '_genseo_og_description', true);
        if (!empty($og_desc)) {
            update_post_meta($post_id, 'rank_math_facebook_description', $og_desc);
        }

        // Schema JSON (RankMath schema Article)
        // RankMath mong đợi PHP array (WordPress sẽ serialize), KHÔNG phải JSON string.
        // Nếu ghi JSON string trực tiếp, RankMath sẽ crash khi đọc vì cố truy cập $schema['@type'] trên string.
        $schema_json = get_post_meta($post_id, '_genseo_schema_json', true);
        if (!empty($schema_json)) {
            $schema_array = json_decode($schema_json, true);
            if (is_array($schema_array) && !empty($schema_array['@type'])) {
                update_post_meta($post_id, 'rank_math_schema_Article', $schema_array);
            }
        }

        // Robots meta
        $robots = get_post_meta($post_id, '_genseo_robots', true);
        if (!empty($robots)) {
            $robots_lower = strtolower($robots);
            $robots_array = array();
            if (strpos($robots_lower, 'noindex') !== false)  $robots_array[] = 'noindex';
            else $robots_array[] = 'index';
            if (strpos($robots_lower, 'nofollow') !== false) $robots_array[] = 'nofollow';
            update_post_meta($post_id, 'rank_math_robots', $robots_array);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('GenSeo MCP: Synced post #%d to RankMath', $post_id));
        }
    }

    /**
     * Sync GenSeo meta sang Yoast SEO
     *
     * @param int $post_id Post ID
     */
    private static function sync_to_yoast($post_id) {
        // Mapping: GenSeo meta → Yoast meta
        $mapping = array(
            '_genseo_seo_title'     => '_yoast_wpseo_title',
            '_genseo_meta_desc'     => '_yoast_wpseo_metadesc',
            '_genseo_focus_keyword' => '_yoast_wpseo_focuskw',
            '_genseo_canonical_url' => '_yoast_wpseo_canonical',
            '_genseo_og_image'      => '_yoast_wpseo_opengraph-image',
            '_genseo_og_image_id'   => '_yoast_wpseo_opengraph-image-id',
        );

        foreach ($mapping as $genseo_key => $yoast_key) {
            $value = get_post_meta($post_id, $genseo_key, true);
            if (!empty($value)) {
                update_post_meta($post_id, $yoast_key, $value);
            }
        }

        // OG title/description
        $og_title = get_post_meta($post_id, '_genseo_og_title', true);
        if (!empty($og_title)) {
            update_post_meta($post_id, '_yoast_wpseo_opengraph-title', $og_title);
        }
        $og_desc = get_post_meta($post_id, '_genseo_og_description', true);
        if (!empty($og_desc)) {
            update_post_meta($post_id, '_yoast_wpseo_opengraph-description', $og_desc);
        }

        // Twitter image (dùng chung OG image)
        $og_image = get_post_meta($post_id, '_genseo_og_image', true);
        if (!empty($og_image)) {
            update_post_meta($post_id, '_yoast_wpseo_twitter-image', $og_image);
        }

        // Robots meta
        $robots = get_post_meta($post_id, '_genseo_robots', true);
        if (!empty($robots)) {
            $robots_lower = strtolower($robots);
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex',
                strpos($robots_lower, 'noindex') !== false ? '1' : '0');
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow',
                strpos($robots_lower, 'nofollow') !== false ? '1' : '0');
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('GenSeo MCP: Synced post #%d to Yoast SEO', $post_id));
        }
    }

    // ============================================================
    // HELPER: INTERNAL LINKS
    // ============================================================

    /**
     * Chèn HTML sau paragraph thứ N
     *
     * @param string $content       Nội dung HTML
     * @param int    $paragraph_num Số thứ tự paragraph (1-based)
     * @param string $html          HTML cần chèn
     * @return string Nội dung đã chèn
     */
    private static function insert_after_paragraph($content, $paragraph_num, $html) {
        // Tìm tất cả vị trí </p>
        $closing_tags = array();
        $offset = 0;

        while (($pos = strpos($content, '</p>', $offset)) !== false) {
            $closing_tags[] = $pos + 4; // Vị trí sau </p>
            $offset = $pos + 4;
        }

        // Kiểm tra paragraph_num hợp lệ
        if ($paragraph_num > count($closing_tags)) {
            // Nếu vượt quá số paragraphs, chèn cuối
            $paragraph_num = count($closing_tags);
        }

        if ($paragraph_num < 1 || empty($closing_tags)) {
            return $content . "\n" . $html;
        }

        // Chèn sau paragraph thứ N (1-based index → array 0-based)
        $insert_pos = $closing_tags[$paragraph_num - 1];

        return substr($content, 0, $insert_pos) . "\n" . $html . substr($content, $insert_pos);
    }

    /**
     * Tạo link HTML
     *
     * @param string $anchor_text Văn bản hiển thị
     * @param string $url         URL đích
     * @return string HTML <a> tag
     */
    private static function build_link_html($anchor_text, $url) {
        return sprintf(
            '<p><a href="%s" title="%s">%s</a></p>',
            esc_url($url),
            esc_attr($anchor_text),
            esc_html($anchor_text)
        );
    }

    /**
     * Kiểm tra URL có phải internal không
     *
     * @param string $url      URL cần kiểm tra
     * @param string $site_url URL site hiện tại
     * @return bool
     */
    private static function is_internal_url($url, $site_url) {
        // Nếu là relative URL → internal
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return true;
        }

        // So sánh domain
        $url_host  = wp_parse_url($url, PHP_URL_HOST);
        $site_host = wp_parse_url($site_url, PHP_URL_HOST);

        if (empty($url_host) || empty($site_host)) {
            return false;
        }

        return strtolower($url_host) === strtolower($site_host);
    }

    /**
     * Parse vị trí paragraph từ string "after_paragraph_N"
     *
     * @param string $position String vị trí (VD: "after_paragraph_3")
     * @return int Số paragraph (0 nếu không hợp lệ)
     */
    private static function parse_paragraph_position($position) {
        if (empty($position)) {
            return 0;
        }

        if (preg_match('/after_paragraph_(\d+)/i', $position, $matches)) {
            return absint($matches[1]);
        }

        // Thử parse số thuần
        if (is_numeric($position)) {
            return absint($position);
        }

        return 0;
    }

    // ============================================================
    // HELPER: CONTENT ANALYSIS
    // ============================================================

    /**
     * Đếm số headings (H1-H6) trong HTML
     *
     * @param string $content Nội dung HTML
     * @return int
     */
    private static function count_headings($content) {
        return preg_match_all('/<h[1-6]\b/i', $content);
    }

    /**
     * Đếm số paragraphs trong HTML
     *
     * @param string $content Nội dung HTML
     * @return int
     */
    private static function count_paragraphs($content) {
        return preg_match_all('/<p\b/i', $content);
    }

    // ============================================================
    // HELPER: FORMAT POST DATA
    // ============================================================

    /**
     * Format post data cơ bản (cho danh sách)
     *
     * @param WP_Post $post Post object
     * @return array
     */
    private static function format_post_basic($post) {
        return array(
            'id'             => $post->ID,
            'title'          => $post->post_title,
            'slug'           => $post->post_name,
            'url'            => get_permalink($post->ID),
            'type'           => $post->post_type,
            'status'         => $post->post_status,
            'date'           => $post->post_date,
            'modified'       => $post->post_modified,
            'author_id'      => (int) $post->post_author,
            'category_ids'   => wp_get_post_categories($post->ID),
            'featured_image' => get_the_post_thumbnail_url($post->ID, 'full') ?: null,
            'genseo_meta'    => array(
                'source'        => get_post_meta($post->ID, '_genseo_source', true),
                'focus_keyword' => get_post_meta($post->ID, '_genseo_focus_keyword', true),
            ),
        );
    }

    /**
     * Format post data đầy đủ (cho chi tiết)
     *
     * @param WP_Post $post Post object
     * @return array
     */
    private static function format_post_full($post) {
        $basic = self::format_post_basic($post);

        $basic['content']     = $post->post_content;
        $basic['excerpt']     = $post->post_excerpt;
        $basic['date_gmt']    = $post->post_date_gmt;
        $basic['modified_gmt'] = $post->post_modified_gmt;
        $basic['tag_ids']     = wp_get_post_tags($post->ID, array('fields' => 'ids'));
        $basic['genseo_meta'] = GenSeo_Meta_Fields::get_all_meta($post->ID);

        return $basic;
    }

    // ============================================================
    // API VERSIONING
    // ============================================================

    /**
     * Register genseo/get-api-version ability
     */
    private static function register_get_api_version() {
        wp_register_ability('genseo/get-api-version', array(
            'label'       => 'Lấy API version',
            'description' => 'Trả về phiên bản API hiện tại, phiên bản client tối thiểu được hỗ trợ, và danh sách abilities',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'client_version' => array(
                        'type'        => 'string',
                        'description' => 'Client API version (e.g. "1.0") — server sẽ trả deprecation warnings nếu cần',
                    ),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_api_version'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * Handler: genseo/get-api-version
     *
     * @param array $params { client_version?: string }
     * @return array
     */
    public static function handle_get_api_version($params) {
        $client_version = isset($params['client_version']) ? sanitize_text_field($params['client_version']) : null;

        $response = array(
            'api_version'        => self::API_VERSION,
            'min_client_version' => self::MIN_CLIENT_VERSION,
            'plugin_version'     => GENSEO_VERSION,
            'abilities_count'    => 36,
            'deprecated'         => array(),
            'warnings'           => array(),
        );

        // Check client version compatibility
        if ($client_version !== null) {
            if (version_compare($client_version, self::MIN_CLIENT_VERSION, '<')) {
                $response['warnings'][] = sprintf(
                    'Client API version %s is below minimum supported version %s. Please update your GenSeo Desktop app.',
                    $client_version,
                    self::MIN_CLIENT_VERSION
                );
            }
        }

        return $response;
    }

    // ============================================================
    // PHASE 11: NEW ABILITIES — P0
    // ============================================================

    /**
     * Register genseo/get-site-context — composite call (13→1)
     */
    private static function register_get_site_context() {
        wp_register_ability('genseo/get-site-context', array(
            'label'       => 'Lấy toàn bộ site context',
            'description' => 'Gộp 13 calls riêng lẻ (settings, plugins, theme, nav, permalink, health, sitemap, robots, content stats, redirects, link graph, taxonomy SEO) thành 1 response duy nhất.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'include' => array(
                        'type'        => 'array',
                        'items'       => array('type' => 'string'),
                        'description' => 'Sections to include (default: all). Valid: site_settings, active_plugins, theme_info, navigation, permalink_analysis, site_health, sitemap_urls, robots_txt, content_stats, redirects, link_graph, taxonomy_seo',
                    ),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_site_context'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * Handler: genseo/get-site-context
     */
    public static function handle_get_site_context($params) {
        $all_sections = array(
            'site_settings', 'active_plugins', 'theme_info', 'navigation',
            'permalink_analysis', 'site_health', 'sitemap_urls', 'robots_txt',
            'content_stats', 'redirects', 'link_graph', 'taxonomy_seo',
        );

        $include = $all_sections;
        if (!empty($params['include']) && is_array($params['include'])) {
            $include = array_intersect(array_map('sanitize_key', $params['include']), $all_sections);
        }

        $result = array();

        $section_map = array(
            'site_settings'      => 'handle_get_site_settings',
            'active_plugins'     => 'handle_get_active_plugins',
            'theme_info'         => 'handle_get_theme_info',
            'navigation'         => 'handle_get_navigation_structure',
            'permalink_analysis' => 'handle_analyze_permalink_structure',
            'site_health'        => 'handle_get_site_health',
            'sitemap_urls'       => 'handle_get_sitemap_urls',
            'robots_txt'         => 'handle_get_robots_txt',
            'content_stats'      => 'handle_get_content_stats',
            'redirects'          => 'handle_get_redirects',
            'link_graph'         => 'handle_get_internal_link_graph',
        );

        foreach ($include as $section) {
            if ($section === 'taxonomy_seo') {
                $result['taxonomy_seo'] = array(
                    'category' => self::handle_get_taxonomy_seo(array('taxonomy' => 'category', 'hide_empty' => false)),
                    'post_tag' => self::handle_get_taxonomy_seo(array('taxonomy' => 'post_tag', 'hide_empty' => false)),
                );
            } elseif (isset($section_map[$section])) {
                $handler = $section_map[$section];
                $handler_params = array();
                if ($section === 'robots_txt') {
                    $handler_params = array('include_content' => true);
                }
                $data = self::$handler($handler_params);
                if (!is_wp_error($data)) {
                    $result[$section] = $data;
                }
            }
        }

        return $result;
    }

    /**
     * Register genseo/update-taxonomy-seo
     */
    private static function register_update_taxonomy_seo() {
        wp_register_ability('genseo/update-taxonomy-seo', array(
            'label'       => 'Cập nhật SEO cho taxonomy term',
            'description' => 'Cập nhật seo_title, meta_description, focus_keyword, canonical_url, robots, og_title, og_description cho category/tag/custom taxonomy.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'taxonomy'         => array('type' => 'string', 'description' => 'Tên taxonomy (category, post_tag, ...)'),
                    'term_id'          => array('type' => 'integer', 'description' => 'Term ID'),
                    'seo_title'        => array('type' => 'string', 'description' => 'SEO Title'),
                    'meta_description' => array('type' => 'string', 'description' => 'Meta Description'),
                    'focus_keyword'    => array('type' => 'string', 'description' => 'Focus keyword'),
                    'canonical_url'    => array('type' => 'string', 'description' => 'Canonical URL'),
                    'robots'           => array('type' => 'string', 'description' => 'Robots meta (index, noindex, nofollow)'),
                    'og_title'         => array('type' => 'string', 'description' => 'OpenGraph title'),
                    'og_description'   => array('type' => 'string', 'description' => 'OpenGraph description'),
                ),
                'required' => array('taxonomy', 'term_id'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_update_taxonomy_seo'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * Handler: genseo/update-taxonomy-seo
     */
    public static function handle_update_taxonomy_seo($params) {
        $taxonomy = sanitize_key($params['taxonomy'] ?? '');
        $term_id  = absint($params['term_id'] ?? 0);

        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            return new WP_Error('invalid_taxonomy', 'Taxonomy không hợp lệ', array('status' => 400));
        }
        if (!$term_id || !term_exists($term_id, $taxonomy)) {
            return new WP_Error('invalid_term', 'Term không tồn tại', array('status' => 404));
        }
        if (!current_user_can('manage_categories')) {
            return new WP_Error('forbidden', 'Không có quyền chỉnh sửa taxonomy', array('status' => 403));
        }

        $updated_fields = array();
        $field_map = array(
            'seo_title'        => 'title',
            'meta_description' => 'description',
            'focus_keyword'    => 'focus_keyword',
        );

        foreach ($field_map as $param_key => $meta_field) {
            if (isset($params[$param_key])) {
                $value = sanitize_text_field($params[$param_key]);
                self::set_seo_plugin_term_meta($term_id, $meta_field, $value);
                $updated_fields[] = $param_key;
            }
        }

        // GenSeo-specific term meta
        $genseo_fields = array('canonical_url', 'robots', 'og_title', 'og_description');
        foreach ($genseo_fields as $field) {
            if (isset($params[$field])) {
                $value = $field === 'canonical_url'
                    ? esc_url_raw($params[$field])
                    : sanitize_text_field($params[$field]);
                update_term_meta($term_id, '_genseo_' . $field, $value);
                $updated_fields[] = $field;
            }
        }

        $result = array(
            'success'        => true,
            'term_id'        => $term_id,
            'taxonomy'       => $taxonomy,
            'updated_fields' => $updated_fields,
        );

        do_action('genseo_mcp_ability_executed', 'genseo/update-taxonomy-seo', $params, $result);

        return $result;
    }

    /**
     * Write SEO meta to the active SEO plugin's term meta fields
     */
    private static function set_seo_plugin_term_meta($term_id, $field, $value) {
        if (genseo_is_yoast_active()) {
            switch ($field) {
                case 'title':
                    update_term_meta($term_id, 'wpseo_title', $value);
                    break;
                case 'description':
                    update_term_meta($term_id, 'wpseo_desc', $value);
                    break;
                case 'focus_keyword':
                    update_term_meta($term_id, 'wpseo_focuskw', $value);
                    break;
            }
        } elseif (genseo_is_rankmath_active()) {
            switch ($field) {
                case 'title':
                    update_term_meta($term_id, 'rank_math_title', $value);
                    break;
                case 'description':
                    update_term_meta($term_id, 'rank_math_description', $value);
                    break;
                case 'focus_keyword':
                    update_term_meta($term_id, 'rank_math_focus_keyword', $value);
                    break;
            }
        }

        // Always store in GenSeo meta too
        update_term_meta($term_id, '_genseo_seo_' . $field, $value);
    }

    /**
     * Register genseo/bulk-import-redirects
     */
    private static function register_bulk_import_redirects() {
        wp_register_ability('genseo/bulk-import-redirects', array(
            'label'       => 'Import redirects hàng loạt',
            'description' => 'Import nhiều redirects cùng lúc từ array. Hỗ trợ skip/overwrite khi trùng.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'redirects' => array(
                        'type' => 'array',
                        'items' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'from_url'    => array('type' => 'string'),
                                'to_url'      => array('type' => 'string'),
                                'status_code' => array('type' => 'integer'),
                                'enabled'     => array('type' => 'boolean'),
                            ),
                            'required' => array('from_url', 'to_url'),
                        ),
                    ),
                    'conflict_strategy' => array(
                        'type'        => 'string',
                        'description' => 'skip | overwrite | error (default: skip)',
                    ),
                ),
                'required' => array('redirects'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_bulk_import_redirects'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * Handler: genseo/bulk-import-redirects
     */
    public static function handle_bulk_import_redirects($params) {
        if (empty($params['redirects']) || !is_array($params['redirects'])) {
            return new WP_Error('missing_redirects', 'Cần truyền array redirects', array('status' => 400));
        }

        $items    = array_slice($params['redirects'], 0, 200); // giới hạn 200/lần
        $strategy = sanitize_key($params['conflict_strategy'] ?? 'skip');
        if (!in_array($strategy, array('skip', 'overwrite', 'error'), true)) {
            $strategy = 'skip';
        }

        $redirects = get_option('genseo_redirects', array());
        if (!is_array($redirects)) {
            $redirects = array();
        }

        // Index existing by from path for quick lookup
        $existing_map = array();
        foreach ($redirects as $idx => $r) {
            $existing_map[$r['from'] ?? ''] = $idx;
        }

        $site_url  = get_site_url();
        $imported  = 0;
        $skipped   = 0;
        $errors    = array();

        foreach ($items as $item) {
            $from_url = sanitize_text_field($item['from_url'] ?? '');
            $to_url   = esc_url_raw($item['to_url'] ?? '');

            if (empty($from_url) || empty($to_url)) {
                $errors[] = array('from_url' => $from_url, 'reason' => 'URL trống');
                continue;
            }

            $status_code = isset($item['status_code']) ? absint($item['status_code']) : 301;
            if (!in_array($status_code, array(301, 302, 307), true)) {
                $status_code = 301;
            }

            // Normalize to relative path
            $from_path = $from_url;
            if (strpos($from_url, $site_url) === 0) {
                $from_path = substr($from_url, strlen($site_url));
            }
            if (empty($from_path)) $from_path = '/';

            // Check conflict
            if (isset($existing_map[$from_path])) {
                if ($strategy === 'skip') {
                    $skipped++;
                    continue;
                } elseif ($strategy === 'error') {
                    $errors[] = array('from_url' => $from_path, 'reason' => 'Đã tồn tại');
                    continue;
                }
                // overwrite: update existing
                $redirects[$existing_map[$from_path]] = array(
                    'from'    => $from_path,
                    'to'      => $to_url,
                    'status'  => $status_code,
                    'created' => $redirects[$existing_map[$from_path]]['created'] ?? current_time('mysql'),
                    'updated' => current_time('mysql'),
                );
                $imported++;
                continue;
            }

            // Add new
            $redirects[] = array(
                'from'    => $from_path,
                'to'      => $to_url,
                'status'  => $status_code,
                'created' => current_time('mysql'),
            );
            $existing_map[$from_path] = count($redirects) - 1;
            $imported++;
        }

        update_option('genseo_redirects', $redirects);

        $result = array(
            'success'         => true,
            'imported'        => $imported,
            'skipped'         => $skipped,
            'errors'          => $errors,
            'total_redirects' => count($redirects),
        );

        do_action('genseo_mcp_ability_executed', 'genseo/bulk-import-redirects', $params, $result);

        return $result;
    }

    // ============================================================
    // PHASE 11: NEW ABILITIES — P1 WooCommerce
    // ============================================================

    private static function register_get_woo_products() {
        wp_register_ability('genseo/get-woo-products', array(
            'label'       => 'Lấy danh sách sản phẩm WooCommerce',
            'description' => 'Danh sách sản phẩm WooCommerce kèm SEO meta (title, description, focus keyword, schema).',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'per_page' => array('type' => 'integer', 'description' => 'Số sản phẩm mỗi trang (default: 50, max: 200)'),
                    'page'     => array('type' => 'integer', 'description' => 'Trang (default: 1)'),
                    'status'   => array('type' => 'string', 'description' => 'post status: publish, draft, any'),
                    'category' => array('type' => 'string', 'description' => 'Category slug filter'),
                    'search'   => array('type' => 'string', 'description' => 'Tìm kiếm theo tên'),
                    'orderby'  => array('type' => 'string', 'description' => 'date, title, price, popularity'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_woo_products'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_get_woo_products($params) {
        $per_page = min(absint($params['per_page'] ?? 50), 200);
        $page     = max(1, absint($params['page'] ?? 1));
        $status   = sanitize_key($params['status'] ?? 'publish');
        $search   = sanitize_text_field($params['search'] ?? '');
        $orderby  = sanitize_key($params['orderby'] ?? 'date');

        $args = array(
            'post_type'      => 'product',
            'post_status'    => $status === 'any' ? array('publish', 'draft', 'pending') : $status,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => in_array($orderby, array('date', 'title', 'modified'), true) ? $orderby : 'date',
            'order'          => 'DESC',
        );

        if ($search) {
            $args['s'] = $search;
        }

        if (!empty($params['category'])) {
            $args['tax_query'] = array(array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($params['category']),
            ));
        }

        $query = new WP_Query($args);
        $products = array();

        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;

            $products[] = array(
                'id'                   => $post->ID,
                'name'                 => $product->get_name(),
                'slug'                 => $product->get_slug(),
                'url'                  => get_permalink($post->ID),
                'status'               => $post->post_status,
                'type'                 => $product->get_type(),
                'price'                => $product->get_price(),
                'regular_price'        => $product->get_regular_price(),
                'sku'                  => $product->get_sku(),
                'stock_status'         => $product->get_stock_status(),
                'image'                => wp_get_attachment_url($product->get_image_id()),
                'categories'           => wp_get_post_terms($post->ID, 'product_cat', array('fields' => 'names')),
                'short_description_length' => mb_strlen(wp_strip_all_tags($product->get_short_description())),
                'description_length'       => mb_strlen(wp_strip_all_tags($product->get_description())),
                'seo_title'            => self::get_seo_field($post->ID, 'title'),
                'meta_description'     => self::get_seo_field($post->ID, 'description'),
                'focus_keyword'        => self::get_seo_field($post->ID, 'focus_keyword'),
                'has_seo_meta'         => !empty(self::get_seo_field($post->ID, 'title')) || !empty(self::get_seo_field($post->ID, 'description')),
            );
        }

        return array(
            'products'   => $products,
            'total'      => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page'       => $page,
            'per_page'   => $per_page,
        );
    }

    private static function register_update_woo_product_seo() {
        wp_register_ability('genseo/update-woo-product-seo', array(
            'label'       => 'Cập nhật SEO cho sản phẩm WooCommerce',
            'description' => 'Cập nhật seo_title, meta_description, focus_keyword, og, schema cho 1 sản phẩm.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'product_id'       => array('type' => 'integer'),
                    'seo_title'        => array('type' => 'string'),
                    'meta_description' => array('type' => 'string'),
                    'focus_keyword'    => array('type' => 'string'),
                    'og_title'         => array('type' => 'string'),
                    'og_description'   => array('type' => 'string'),
                    'schema_json'      => array('type' => 'string'),
                ),
                'required' => array('product_id'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_update_woo_product_seo'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_update_woo_product_seo($params) {
        $product_id = absint($params['product_id'] ?? 0);
        if (!$product_id || get_post_type($product_id) !== 'product') {
            return new WP_Error('invalid_product', 'Product không tồn tại', array('status' => 404));
        }
        if (!current_user_can('edit_post', $product_id)) {
            return new WP_Error('forbidden', 'Không có quyền chỉnh sửa sản phẩm', array('status' => 403));
        }

        // Reuse update-seo-meta handler (products are posts)
        $seo_params = array('post_id' => $product_id);
        $seo_fields = array('seo_title', 'meta_description', 'focus_keyword', 'og_title', 'og_description', 'schema_json');
        foreach ($seo_fields as $f) {
            if (isset($params[$f])) {
                $seo_params[$f] = $params[$f];
            }
        }

        $result = self::handle_update_seo_meta($seo_params);
        if (is_wp_error($result)) return $result;

        do_action('genseo_mcp_ability_executed', 'genseo/update-woo-product-seo', $params, $result);
        return $result;
    }

    private static function register_bulk_update_woo_seo() {
        wp_register_ability('genseo/bulk-update-woo-seo', array(
            'label'       => 'Cập nhật SEO hàng loạt cho WooCommerce',
            'description' => 'Bulk update SEO meta cho nhiều sản phẩm WooCommerce cùng lúc.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'items' => array(
                        'type' => 'array',
                        'items' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'product_id'       => array('type' => 'integer'),
                                'seo_title'        => array('type' => 'string'),
                                'meta_description' => array('type' => 'string'),
                                'focus_keyword'    => array('type' => 'string'),
                            ),
                            'required' => array('product_id'),
                        ),
                    ),
                ),
                'required' => array('items'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_bulk_update_woo_seo'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_bulk_update_woo_seo($params) {
        if (empty($params['items']) || !is_array($params['items'])) {
            return new WP_Error('missing_items', 'Cần truyền array items', array('status' => 400));
        }

        $items   = array_slice($params['items'], 0, 100);
        $success = 0;
        $failed  = 0;
        $results = array();

        foreach ($items as $item) {
            $r = self::handle_update_woo_product_seo($item);
            if (is_wp_error($r)) {
                $failed++;
                $results[] = array(
                    'product_id' => absint($item['product_id'] ?? 0),
                    'status'     => 'error',
                    'error'      => $r->get_error_message(),
                );
            } else {
                $success++;
                $results[] = array(
                    'product_id' => absint($item['product_id'] ?? 0),
                    'status'     => 'ok',
                );
            }
        }

        $result = array(
            'success' => $success,
            'failed'  => $failed,
            'results' => $results,
        );

        do_action('genseo_mcp_ability_executed', 'genseo/bulk-update-woo-seo', $params, $result);
        return $result;
    }

    // ============================================================
    // PHASE 11: NEW ABILITIES — P1 Media & Search
    // ============================================================

    private static function register_get_media_items() {
        wp_register_ability('genseo/get-media-items', array(
            'label'       => 'Lấy danh sách media/attachments',
            'description' => 'Browse media library — lọc theo mime_type, missing alt text, post parent.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'per_page'    => array('type' => 'integer', 'description' => 'Số items/trang (default: 50, max: 200)'),
                    'page'        => array('type' => 'integer', 'description' => 'Trang (default: 1)'),
                    'mime_type'   => array('type' => 'string', 'description' => 'Filter: image, image/jpeg, video, ...'),
                    'missing_alt' => array('type' => 'boolean', 'description' => 'Chỉ lấy images thiếu alt text'),
                    'post_parent' => array('type' => 'integer', 'description' => 'Filter theo post parent'),
                    'search'      => array('type' => 'string', 'description' => 'Tìm theo tên file'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_media_items'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_get_media_items($params) {
        $per_page = min(absint($params['per_page'] ?? 50), 200);
        $page     = max(1, absint($params['page'] ?? 1));

        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if (!empty($params['mime_type'])) {
            $args['post_mime_type'] = sanitize_text_field($params['mime_type']);
        }
        if (!empty($params['post_parent'])) {
            $args['post_parent'] = absint($params['post_parent']);
        }
        if (!empty($params['search'])) {
            $args['s'] = sanitize_text_field($params['search']);
        }

        // missing_alt filter: use meta_query
        if (!empty($params['missing_alt'])) {
            $args['post_mime_type'] = $args['post_mime_type'] ?? 'image';
            $args['meta_query'] = array(
                'relation' => 'OR',
                array('key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS'),
                array('key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '='),
            );
        }

        $query = new WP_Query($args);
        $items = array();

        foreach ($query->posts as $post) {
            $metadata = wp_get_attachment_metadata($post->ID);
            $items[] = array(
                'id'          => $post->ID,
                'url'         => wp_get_attachment_url($post->ID),
                'title'       => $post->post_title,
                'alt_text'    => get_post_meta($post->ID, '_wp_attachment_image_alt', true) ?: '',
                'caption'     => $post->post_excerpt,
                'mime_type'   => $post->post_mime_type,
                'width'       => $metadata['width'] ?? null,
                'height'      => $metadata['height'] ?? null,
                'filesize'    => $metadata['filesize'] ?? null,
                'post_parent' => $post->post_parent,
                'date'        => $post->post_date,
            );
        }

        return array(
            'items'       => $items,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page'        => $page,
            'per_page'    => $per_page,
        );
    }

    private static function register_bulk_update_image_alt() {
        wp_register_ability('genseo/bulk-update-image-alt', array(
            'label'       => 'Cập nhật alt text hàng loạt',
            'description' => 'Bulk update alt text và title cho nhiều attachments cùng lúc.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'items' => array(
                        'type' => 'array',
                        'items' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'attachment_id' => array('type' => 'integer'),
                                'alt_text'      => array('type' => 'string'),
                                'title'         => array('type' => 'string'),
                            ),
                            'required' => array('attachment_id', 'alt_text'),
                        ),
                    ),
                ),
                'required' => array('items'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_bulk_update_image_alt'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_bulk_update_image_alt($params) {
        if (empty($params['items']) || !is_array($params['items'])) {
            return new WP_Error('missing_items', 'Cần truyền array items', array('status' => 400));
        }

        $items   = array_slice($params['items'], 0, 100);
        $success = 0;
        $failed  = 0;
        $results = array();

        foreach ($items as $item) {
            $attachment_id = absint($item['attachment_id'] ?? 0);
            if (!$attachment_id || get_post_type($attachment_id) !== 'attachment') {
                $failed++;
                $results[] = array('attachment_id' => $attachment_id, 'status' => 'error', 'error' => 'Invalid attachment');
                continue;
            }
            if (!current_user_can('edit_post', $attachment_id)) {
                $failed++;
                $results[] = array('attachment_id' => $attachment_id, 'status' => 'error', 'error' => 'Permission denied');
                continue;
            }

            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($item['alt_text']));

            if (isset($item['title'])) {
                wp_update_post(array('ID' => $attachment_id, 'post_title' => sanitize_text_field($item['title'])));
            }

            $success++;
            $results[] = array('attachment_id' => $attachment_id, 'status' => 'ok');
        }

        $result = array('success' => $success, 'failed' => $failed, 'results' => $results);
        do_action('genseo_mcp_ability_executed', 'genseo/bulk-update-image-alt', $params, $result);
        return $result;
    }

    private static function register_get_posts_search() {
        wp_register_ability('genseo/get-posts-search', array(
            'label'       => 'Tìm kiếm bài viết theo SEO meta',
            'description' => 'Full-text search bao gồm seo_title, meta_description, focus_keyword. Tìm duplicate/missing SEO meta.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'query'     => array('type' => 'string', 'description' => 'Từ khóa tìm kiếm'),
                    'search_in' => array(
                        'type' => 'array',
                        'items' => array('type' => 'string'),
                        'description' => 'Nơi tìm: title, content, seo_title, meta_description, focus_keyword (default: all)',
                    ),
                    'per_page'  => array('type' => 'integer'),
                    'page'      => array('type' => 'integer'),
                    'post_type' => array('type' => 'string', 'description' => 'Post type (default: post)'),
                ),
                'required' => array('query'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_posts_search'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_get_posts_search($params) {
        $query    = sanitize_text_field($params['query'] ?? '');
        $per_page = min(absint($params['per_page'] ?? 50), 200);
        $page     = max(1, absint($params['page'] ?? 1));
        $post_type = sanitize_key($params['post_type'] ?? 'post');

        if (empty($query)) {
            return new WP_Error('missing_query', 'Cần truyền query', array('status' => 400));
        }

        $search_in = array('title', 'content', 'seo_title', 'meta_description', 'focus_keyword');
        if (!empty($params['search_in']) && is_array($params['search_in'])) {
            $search_in = array_intersect(
                array_map('sanitize_key', $params['search_in']),
                array('title', 'content', 'seo_title', 'meta_description', 'focus_keyword')
            );
        }

        // First, search in post title + content via WP_Query
        $wp_fields = array_intersect($search_in, array('title', 'content'));
        $meta_fields = array_intersect($search_in, array('seo_title', 'meta_description', 'focus_keyword'));

        $post_ids = array();

        // WP native search for title/content
        if (!empty($wp_fields)) {
            $wp_query = new WP_Query(array(
                'post_type'      => $post_type,
                'post_status'    => array('publish', 'draft'),
                'posts_per_page' => 500,
                's'              => $query,
                'fields'         => 'ids',
            ));
            $post_ids = array_merge($post_ids, $wp_query->posts);
        }

        // Meta search for SEO fields
        if (!empty($meta_fields)) {
            $meta_keys = array();
            foreach ($meta_fields as $field) {
                switch ($field) {
                    case 'seo_title':
                        $meta_keys[] = '_genseo_seo_title';
                        if (genseo_is_yoast_active()) $meta_keys[] = '_yoast_wpseo_title';
                        if (genseo_is_rankmath_active()) $meta_keys[] = 'rank_math_title';
                        break;
                    case 'meta_description':
                        $meta_keys[] = '_genseo_meta_desc';
                        if (genseo_is_yoast_active()) $meta_keys[] = '_yoast_wpseo_metadesc';
                        if (genseo_is_rankmath_active()) $meta_keys[] = 'rank_math_description';
                        break;
                    case 'focus_keyword':
                        $meta_keys[] = '_genseo_focus_keyword';
                        if (genseo_is_yoast_active()) $meta_keys[] = '_yoast_wpseo_focuskw';
                        if (genseo_is_rankmath_active()) $meta_keys[] = 'rank_math_focus_keyword';
                        break;
                }
            }

            if (!empty($meta_keys)) {
                global $wpdb;
                $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
                $sql_params = array_merge($meta_keys, array('%' . $wpdb->esc_like($query) . '%', $post_type));
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $meta_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE pm.meta_key IN ($placeholders)
                     AND pm.meta_value LIKE %s
                     AND p.post_type = %s
                     AND p.post_status IN ('publish','draft')
                     LIMIT 500",
                    $sql_params
                ));
                $post_ids = array_merge($post_ids, array_map('absint', $meta_ids));
            }
        }

        $post_ids = array_unique($post_ids);
        $total = count($post_ids);
        $paged_ids = array_slice($post_ids, ($page - 1) * $per_page, $per_page);

        $posts = array();
        foreach ($paged_ids as $pid) {
            $post = get_post($pid);
            if (!$post) continue;

            $seo_title = self::get_seo_field($pid, 'title');
            $meta_desc = self::get_seo_field($pid, 'description');
            $focus_kw  = self::get_seo_field($pid, 'focus_keyword');

            // Build match_context
            $match_in = array();
            if (stripos($post->post_title, $query) !== false) $match_in[] = 'title';
            if (stripos($post->post_content, $query) !== false) $match_in[] = 'content';
            if (stripos($seo_title, $query) !== false) $match_in[] = 'seo_title';
            if (stripos($meta_desc, $query) !== false) $match_in[] = 'meta_description';
            if (stripos($focus_kw, $query) !== false) $match_in[] = 'focus_keyword';

            $posts[] = array(
                'post_id'          => $pid,
                'title'            => $post->post_title,
                'url'              => get_permalink($pid),
                'status'           => $post->post_status,
                'seo_title'        => $seo_title,
                'meta_description' => $meta_desc,
                'focus_keyword'    => $focus_kw,
                'match_in'         => $match_in,
            );
        }

        return array(
            'posts'       => $posts,
            'total'       => $total,
            'total_pages' => (int) ceil($total / $per_page),
            'page'        => $page,
            'per_page'    => $per_page,
        );
    }

    // ============================================================
    // PHASE 11: NEW ABILITIES — P2 Multilingual
    // ============================================================

    private static function register_get_multilingual_info() {
        wp_register_ability('genseo/get-multilingual-info', array(
            'label'       => 'Lấy thông tin đa ngôn ngữ',
            'description' => 'Phát hiện WPML/Polylang, danh sách ngôn ngữ, translations cho post.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'Post ID để lấy translations (optional)'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_multilingual_info'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_get_multilingual_info($params) {
        $result = array(
            'plugin'           => null,
            'default_language' => '',
            'languages'        => array(),
            'translations'     => null,
        );

        // WPML
        if (defined('ICL_SITEPRESS_VERSION')) {
            $result['plugin'] = 'wpml';
            $result['default_language'] = apply_filters('wpml_default_language', '');

            $languages = apply_filters('wpml_active_languages', array(), array('skip_missing' => 0));
            foreach ($languages as $lang) {
                $result['languages'][] = array(
                    'code'       => $lang['code'] ?? '',
                    'name'       => $lang['translated_name'] ?? $lang['native_name'] ?? '',
                    'flag_url'   => $lang['country_flag_url'] ?? '',
                    'is_default' => !empty($lang['active']),
                );
            }

            if (!empty($params['post_id'])) {
                $post_id = absint($params['post_id']);
                $trid = apply_filters('wpml_element_trid', null, $post_id, 'post_' . get_post_type($post_id));
                if ($trid) {
                    $translations_raw = apply_filters('wpml_get_element_translations', array(), $trid, 'post_' . get_post_type($post_id));
                    $translations = array();
                    foreach ($translations_raw as $t) {
                        $translations[] = array(
                            'language_code' => $t->language_code ?? '',
                            'post_id'       => (int) ($t->element_id ?? 0),
                            'title'         => get_the_title($t->element_id ?? 0),
                            'url'           => get_permalink($t->element_id ?? 0),
                            'status'        => get_post_status($t->element_id ?? 0),
                        );
                    }
                    $result['translations'] = $translations;
                }
            }
        }

        // Polylang
        elseif (function_exists('pll_languages_list')) {
            $result['plugin'] = 'polylang';
            $result['default_language'] = pll_default_language();

            $langs = pll_languages_list(array('fields' => ''));
            foreach ($langs as $lang) {
                $result['languages'][] = array(
                    'code'       => $lang->slug,
                    'name'       => $lang->name,
                    'flag_url'   => $lang->flag_url ?? '',
                    'is_default' => $lang->slug === pll_default_language(),
                );
            }

            if (!empty($params['post_id'])) {
                $post_id = absint($params['post_id']);
                $translations = array();
                foreach (pll_languages_list() as $lang_code) {
                    $translated_id = pll_get_post($post_id, $lang_code);
                    if ($translated_id) {
                        $translations[] = array(
                            'language_code' => $lang_code,
                            'post_id'       => $translated_id,
                            'title'         => get_the_title($translated_id),
                            'url'           => get_permalink($translated_id),
                            'status'        => get_post_status($translated_id),
                        );
                    }
                }
                $result['translations'] = $translations;
            }
        }

        return $result;
    }

    private static function register_sync_seo_across_translations() {
        wp_register_ability('genseo/sync-seo-across-translations', array(
            'label'       => 'Sync SEO meta sang bản dịch',
            'description' => 'Copy SEO meta (title, description, schema, OG) từ post gốc sang các bản dịch.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'source_post_id' => array('type' => 'integer', 'description' => 'Post ID nguồn'),
                    'target_language_codes' => array(
                        'type' => 'array',
                        'items' => array('type' => 'string'),
                        'description' => 'Danh sách language codes đích (default: all)',
                    ),
                    'fields' => array(
                        'type' => 'array',
                        'items' => array('type' => 'string'),
                        'description' => 'Fields to sync: seo_title, meta_description, focus_keyword, schema_json, og_title, og_description (default: all)',
                    ),
                ),
                'required' => array('source_post_id'),
            ),
            'meta' => array('mcp' => array('public' => true)),
            'execute_callback'    => array(__CLASS__, 'handle_sync_seo_across_translations'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_sync_seo_across_translations($params) {
        $source_id = absint($params['source_post_id'] ?? 0);
        if (!$source_id || !get_post($source_id)) {
            return new WP_Error('invalid_post', 'Source post không tồn tại', array('status' => 404));
        }

        // Get source SEO meta — use canonical meta keys matching handle_update_seo_meta
        $all_fields = array('seo_title', 'meta_description', 'focus_keyword', 'schema_json', 'og_title', 'og_description');
        $field_to_meta_key = array(
            'seo_title'        => '_genseo_seo_title',
            'meta_description' => '_genseo_meta_desc',
            'focus_keyword'    => '_genseo_focus_keyword',
            'schema_json'      => '_genseo_schema_json',
            'og_title'         => '_genseo_og_title',
            'og_description'   => '_genseo_og_description',
        );

        $fields = $all_fields;
        if (!empty($params['fields']) && is_array($params['fields'])) {
            $fields = array_intersect(array_map('sanitize_key', $params['fields']), $all_fields);
        }

        $source_meta = array();
        foreach ($fields as $field) {
            $meta_key = $field_to_meta_key[$field] ?? ('_genseo_' . $field);
            $source_meta[$field] = get_post_meta($source_id, $meta_key, true) ?: '';
        }

        // Get translations
        $target_ids = array();
        $target_langs = !empty($params['target_language_codes']) ? array_map('sanitize_text_field', $params['target_language_codes']) : null;

        $multilingual = self::handle_get_multilingual_info(array('post_id' => $source_id));
        if (empty($multilingual['translations'])) {
            return new WP_Error('no_translations', 'Không tìm thấy bản dịch', array('status' => 404));
        }

        $synced = array();
        foreach ($multilingual['translations'] as $t) {
            $t_id = (int) $t['post_id'];
            if ($t_id === $source_id) continue;
            if ($target_langs && !in_array($t['language_code'], $target_langs, true)) continue;
            if (!current_user_can('edit_post', $t_id)) continue;

            $updated = array();
            foreach ($source_meta as $field => $value) {
                if ($value !== '') {
                    $meta_key = $field_to_meta_key[$field] ?? ('_genseo_' . $field);
                    update_post_meta($t_id, $meta_key, $value);
                    $updated[] = $field;
                }
            }

            // Sync to SEO plugin too
            self::sync_to_seo_plugin($t_id);

            $synced[] = array(
                'language_code'  => $t['language_code'],
                'post_id'        => $t_id,
                'fields_updated' => $updated,
            );
        }

        $result = array('success' => true, 'synced' => $synced);
        do_action('genseo_mcp_ability_executed', 'genseo/sync-seo-across-translations', $params, $result);
        return $result;
    }

    // ============================================================
    // PHASE 11: NEW ABILITIES — P2 Stats & Analysis
    // ============================================================

    private static function register_get_comments_stats() {
        wp_register_ability('genseo/get-comments-stats', array(
            'label'       => 'Thống kê comment',
            'description' => 'Lấy số lượng comments (approved, pending, spam) cho danh sách post IDs.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_ids' => array(
                        'type' => 'array',
                        'items' => array('type' => 'integer'),
                        'description' => 'Danh sách post IDs (max 200). Không truyền = top 50 posts.',
                    ),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_comments_stats'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_get_comments_stats($params) {
        $post_ids = array();
        if (!empty($params['post_ids']) && is_array($params['post_ids'])) {
            $post_ids = array_slice(array_map('absint', $params['post_ids']), 0, 200);
        } else {
            // Default: top 50 posts by comment count
            $posts = get_posts(array(
                'posts_per_page' => 50,
                'orderby'        => 'comment_count',
                'order'          => 'DESC',
                'fields'         => 'ids',
            ));
            $post_ids = $posts;
        }

        $stats = array();
        foreach ($post_ids as $pid) {
            $counts = get_comment_count($pid);
            $latest = get_comments(array(
                'post_id' => $pid,
                'number'  => 1,
                'status'  => 'approve',
                'orderby' => 'comment_date',
                'order'   => 'DESC',
            ));

            $stats[] = array(
                'post_id'     => $pid,
                'approved'    => (int) ($counts['approved'] ?? 0),
                'pending'     => (int) ($counts['awaiting_moderation'] ?? 0),
                'spam'        => (int) ($counts['spam'] ?? 0),
                'total'       => (int) ($counts['total_comments'] ?? 0),
                'latest_date' => !empty($latest) ? $latest[0]->comment_date : null,
            );
        }

        return array('stats' => $stats);
    }

    private static function register_get_orphan_pages() {
        wp_register_ability('genseo/get-orphan-pages', array(
            'label'       => 'Tìm trang orphan (không có internal links)',
            'description' => 'Tìm các trang published không có inbound internal links trỏ tới.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_type'         => array('type' => 'string', 'description' => 'Post type (default: post)'),
                    'min_inbound_links' => array('type' => 'integer', 'description' => 'Ngưỡng min (default: 0 = hoàn toàn orphan)'),
                ),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_orphan_pages'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_get_orphan_pages($params) {
        $post_type = sanitize_key($params['post_type'] ?? 'post');
        $min_links = absint($params['min_inbound_links'] ?? 0);

        // Get all published posts
        $posts = get_posts(array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'fields'         => 'ids',
        ));

        if (empty($posts)) {
            return array('orphan_pages' => array(), 'total' => 0);
        }

        // Build inbound link count from content scanning
        $site_url = get_site_url();
        $link_counts = array_fill_keys($posts, 0);

        // Scan all published content for links
        $all_posts = get_posts(array(
            'post_type'      => array('post', 'page'),
            'post_status'    => 'publish',
            'posts_per_page' => 500,
        ));

        foreach ($all_posts as $p) {
            if (empty($p->post_content)) continue;
            // Extract hrefs
            if (preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $p->post_content, $matches)) {
                foreach ($matches[1] as $href) {
                    // Resolve relative URLs
                    if (strpos($href, '/') === 0) {
                        $href = $site_url . $href;
                    }
                    // Check if it points to one of our posts
                    $target_id = url_to_postid($href);
                    if ($target_id && isset($link_counts[$target_id]) && $target_id !== $p->ID) {
                        $link_counts[$target_id]++;
                    }
                }
            }
        }

        // Filter orphans
        $orphans = array();
        foreach ($link_counts as $pid => $count) {
            if ($count <= $min_links) {
                $orphans[] = array(
                    'post_id'            => $pid,
                    'url'                => get_permalink($pid),
                    'title'              => get_the_title($pid),
                    'inbound_link_count' => $count,
                );
            }
        }

        // Sort by link count ascending
        usort($orphans, function($a, $b) { return $a['inbound_link_count'] - $b['inbound_link_count']; });

        return array(
            'orphan_pages' => $orphans,
            'total'        => count($orphans),
        );
    }

    private static function register_validate_schema() {
        wp_register_ability('genseo/validate-schema', array(
            'label'       => 'Validate JSON-LD Schema',
            'description' => 'Kiểm tra và validate JSON-LD schema markup của 1 post.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'Post ID'),
                ),
                'required' => array('post_id'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_validate_schema'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_validate_schema($params) {
        $post_id = absint($params['post_id'] ?? 0);
        if (!$post_id || !get_post($post_id)) {
            return new WP_Error('invalid_post', 'Post không tồn tại', array('status' => 404));
        }

        $schema_json = get_post_meta($post_id, '_genseo_schema_json', true) ?: '';
        $errors   = array();
        $warnings = array();
        $types    = array();

        if (empty($schema_json)) {
            return array(
                'valid'              => false,
                'errors'             => array(array('path' => '/', 'message' => 'Không có schema markup', 'severity' => 'warning')),
                'warnings'           => array(),
                'schema_types_found' => array(),
            );
        }

        $parsed = json_decode($schema_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'valid'              => false,
                'errors'             => array(array('path' => '/', 'message' => 'JSON không hợp lệ: ' . json_last_error_msg(), 'severity' => 'error')),
                'warnings'           => array(),
                'schema_types_found' => array(),
            );
        }

        // Validate @context
        $items = isset($parsed['@graph']) ? $parsed['@graph'] : (isset($parsed['@type']) ? array($parsed) : array());

        foreach ($items as $idx => $item) {
            $type = $item['@type'] ?? null;
            $path = '/@graph/' . $idx;

            if (!$type) {
                $errors[] = array('path' => $path, 'message' => 'Thiếu @type', 'severity' => 'error');
                continue;
            }

            $types[] = is_array($type) ? implode(', ', $type) : $type;

            // Basic required field checks per type
            $type_str = is_array($type) ? $type[0] : $type;
            $required_fields = self::get_schema_required_fields($type_str);
            foreach ($required_fields as $rf) {
                if (empty($item[$rf])) {
                    $warnings[] = array('path' => $path . '/' . $rf, 'message' => "Thiếu field khuyến nghị: {$rf} cho {$type_str}");
                }
            }
        }

        return array(
            'valid'              => empty($errors),
            'errors'             => $errors,
            'warnings'           => $warnings,
            'schema_types_found' => array_unique($types),
        );
    }

    /**
     * Get recommended required fields per schema type
     */
    private static function get_schema_required_fields($type) {
        $map = array(
            'Article'     => array('headline', 'author', 'datePublished', 'image'),
            'BlogPosting' => array('headline', 'author', 'datePublished', 'image'),
            'Product'     => array('name', 'description', 'offers'),
            'FAQPage'     => array('mainEntity'),
            'HowTo'       => array('name', 'step'),
            'Recipe'      => array('name', 'recipeIngredient', 'recipeInstructions'),
            'LocalBusiness' => array('name', 'address', 'telephone'),
            'Organization'  => array('name', 'url'),
            'Person'        => array('name'),
            'WebPage'       => array('name'),
            'BreadcrumbList' => array('itemListElement'),
        );
        return $map[$type] ?? array();
    }

    private static function register_get_anchor_diversity() {
        wp_register_ability('genseo/get-anchor-diversity', array(
            'label'       => 'Phân tích anchor diversity',
            'description' => 'Phân tích đa dạng anchor text của inbound internal links cho 1 URL.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'url' => array('type' => 'string', 'description' => 'URL cần phân tích (full hoặc relative)'),
                ),
                'required' => array('url'),
            ),
            'meta' => array(
                'mcp'         => array('public' => true),
                'annotations' => array('readOnlyHint' => true),
            ),
            'execute_callback'    => array(__CLASS__, 'handle_get_anchor_diversity'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    public static function handle_get_anchor_diversity($params) {
        $target_url = sanitize_text_field($params['url'] ?? '');
        if (empty($target_url)) {
            return new WP_Error('missing_url', 'Cần truyền url', array('status' => 400));
        }

        $site_url = get_site_url();
        // Normalize target
        if (strpos($target_url, '/') === 0) {
            $target_url = $site_url . $target_url;
        }
        $target_path = wp_parse_url($target_url, PHP_URL_PATH) ?: '/';

        // Scan published content for links to this URL
        $posts = get_posts(array(
            'post_type'      => array('post', 'page'),
            'post_status'    => 'publish',
            'posts_per_page' => 500,
        ));

        $anchors = array(); // anchor_text => array of source_urls

        foreach ($posts as $p) {
            if (empty($p->post_content)) continue;

            // Find all links in content
            if (preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $p->post_content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $href = $m[1];
                    $text = wp_strip_all_tags(trim($m[2]));

                    // Resolve relative
                    if (strpos($href, '/') === 0) {
                        $href = $site_url . $href;
                    }

                    $href_path = wp_parse_url($href, PHP_URL_PATH) ?: '/';

                    if ($href_path === $target_path && !empty($text)) {
                        $key = mb_strtolower($text);
                        if (!isset($anchors[$key])) {
                            $anchors[$key] = array('text' => $text, 'count' => 0, 'source_urls' => array());
                        }
                        $anchors[$key]['count']++;
                        $source_url = get_permalink($p->ID);
                        if (!in_array($source_url, $anchors[$key]['source_urls'])) {
                            $anchors[$key]['source_urls'][] = $source_url;
                        }
                    }
                }
            }
        }

        $anchors_list = array_values($anchors);
        usort($anchors_list, function($a, $b) { return $b['count'] - $a['count']; });

        $total = array_sum(array_column($anchors_list, 'count'));
        $unique = count($anchors_list);
        $diversity_score = $total > 0 ? round(($unique / $total) * 100, 1) : 0;

        // Over-optimized: top anchor > 70% of total
        $over_optimized = false;
        if ($total > 2 && !empty($anchors_list)) {
            $top_pct = ($anchors_list[0]['count'] / $total) * 100;
            $over_optimized = $top_pct > 70;
        }

        return array(
            'url'              => $target_url,
            'total_inbound'    => $total,
            'unique_anchors'   => $unique,
            'diversity_score'  => $diversity_score,
            'anchors'          => $anchors_list,
            'over_optimized'   => $over_optimized,
        );
    }

    // ============================================================
    // CACHE MANAGEMENT
    // ============================================================

    /**
     * genseo/purge-post-cache — Xóa cache cho bài viết cụ thể
     * Hỗ trợ page cache plugins phổ biến: WP Super Cache, W3 Total Cache,
     * LiteSpeed Cache, WP Fastest Cache, WP Rocket, và WordPress core.
     */
    private static function register_purge_post_cache() {
        wp_register_ability('genseo/purge-post-cache', array(
            'label'       => 'Xóa cache bài viết',
            'description' => 'Xóa page cache cho bài viết cụ thể sau khi cập nhật SEO meta. Hỗ trợ WP Super Cache, W3TC, LiteSpeed, WP Fastest Cache, WP Rocket.',
            'category'    => 'genseo',
            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array('type' => 'integer', 'description' => 'ID bài viết cần xóa cache'),
                ),
                'required' => array('post_id'),
            ),
            'meta' => array(
                'mcp' => array('public' => true),
                'annotations' => array('readOnlyHint' => false),
            ),
            'execute_callback'   => array(__CLASS__, 'handle_purge_post_cache'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
        ));
    }

    /**
     * Handler: Xóa cache cho 1 bài viết
     * Detect và gọi API purge của từng cache plugin đang active.
     */
    public static function handle_purge_post_cache($params) {
        $post_id = self::validate_post_id($params);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $plugins_cleared = array();

        // WordPress core — luôn gọi
        clean_post_cache($post_id);
        $plugins_cleared[] = 'wordpress_core';

        // WP Super Cache
        if (function_exists('wp_cache_post_change')) {
            wp_cache_post_change($post_id);
            $plugins_cleared[] = 'wp_super_cache';
        }

        // W3 Total Cache
        if (function_exists('w3tc_flush_post')) {
            w3tc_flush_post($post_id);
            $plugins_cleared[] = 'w3_total_cache';
        }

        // LiteSpeed Cache
        if (has_action('litespeed_purge_post')) {
            do_action('litespeed_purge_post', $post_id);
            $plugins_cleared[] = 'litespeed_cache';
        }

        // WP Fastest Cache
        if (has_action('wpfc_clear_post_cache_by_id')) {
            do_action('wpfc_clear_post_cache_by_id', $post_id);
            $plugins_cleared[] = 'wp_fastest_cache';
        }

        // WP Rocket
        if (function_exists('rocket_clean_post')) {
            rocket_clean_post($post_id);
            $plugins_cleared[] = 'wp_rocket';
        }

        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
            $plugins_cleared[] = 'autoptimize';
        }

        // Hummingbird
        if (has_action('wphb_clear_page_cache')) {
            do_action('wphb_clear_page_cache', $post_id);
            $plugins_cleared[] = 'hummingbird';
        }

        // SG Optimizer (SiteGround)
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache(get_permalink($post_id));
            $plugins_cleared[] = 'sg_optimizer';
        }

        // Object cache flush cho post
        wp_cache_delete($post_id, 'posts');
        wp_cache_delete($post_id, 'post_meta');

        $result = array(
            'success'         => true,
            'post_id'         => $post_id,
            'plugins_cleared' => $plugins_cleared,
        );

        do_action('genseo_mcp_ability_executed', 'genseo/purge-post-cache', $params, $result, true);

        return $result;
    }
}
