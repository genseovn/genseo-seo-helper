<?php
/**
 * Yoast SEO Integration - Sync SEO data sang Yoast SEO
 *
 * @package GenSeo_SEO_Helper
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_Yoast
 *
 * Tự động sync GenSeo meta fields sang Yoast SEO
 */
class GenSeo_Yoast {

    /**
     * Mapping GenSeo fields → Yoast fields
     *
     * @var array
     */
    private static $field_mapping = array(
        '_genseo_seo_title'     => '_yoast_wpseo_title',
        '_genseo_meta_desc'     => '_yoast_wpseo_metadesc',
        '_genseo_focus_keyword' => '_yoast_wpseo_focuskw',
        '_genseo_canonical_url' => '_yoast_wpseo_canonical',
        '_genseo_og_image'      => '_yoast_wpseo_opengraph-image',
        '_genseo_og_image_id'   => '_yoast_wpseo_opengraph-image-id',
    );

    /**
     * Khởi tạo class
     */
    public static function init() {
        // Sync sau khi post được lưu
        add_action('save_post', array(__CLASS__, 'sync_meta'), 20, 3);

        // Sync khi REST API update post
        add_action('rest_after_insert_post', array(__CLASS__, 'sync_meta_rest'), 20, 2);

        // Disable Yoast OG output cho GenSeo posts
        add_filter('wpseo_opengraph_url', array(__CLASS__, 'maybe_disable_og'), 10, 1);
    }

    /**
     * Sync meta khi save_post
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Is update or new post
     */
    public static function sync_meta($post_id, $post = null, $update = false) {
        // Bỏ qua autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Bỏ qua revisions
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Kiểm tra setting có bật không
        if (!genseo_get_setting('enable_yoast_sync', true)) {
            return;
        }

        // Chỉ sync posts
        if (get_post_type($post_id) !== 'post') {
            return;
        }

        // Chỉ sync bài từ GenSeo
        if (!genseo_is_genseo_post($post_id)) {
            return;
        }

        self::do_sync($post_id);
    }

    /**
     * Sync meta khi REST API insert/update
     *
     * @param WP_Post         $post    Post object
     * @param WP_REST_Request $request Request object
     */
    public static function sync_meta_rest($post, $request) {
        if (!genseo_get_setting('enable_yoast_sync', true)) {
            return;
        }

        if ($post->post_type !== 'post') {
            return;
        }

        if (!genseo_is_genseo_post($post->ID)) {
            return;
        }

        self::do_sync($post->ID);
    }

    /**
     * Thực hiện sync fields
     *
     * @param int $post_id Post ID
     */
    private static function do_sync($post_id) {
        // Sync các fields cơ bản
        foreach (self::$field_mapping as $genseo_key => $yoast_key) {
            $value = get_post_meta($post_id, $genseo_key, true);

            if (!empty($value)) {
                update_post_meta($post_id, $yoast_key, $value);
            }
        }

        // Xử lý robots meta đặc biệt
        $robots = get_post_meta($post_id, '_genseo_robots', true);
        if (!empty($robots)) {
            self::set_yoast_robots($post_id, $robots);
        }

        // Twitter image (nếu khác OG image)
        $og_image = get_post_meta($post_id, '_genseo_og_image', true);
        if (!empty($og_image)) {
            update_post_meta($post_id, '_yoast_wpseo_twitter-image', $og_image);
        }

        // Log sync
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'GenSeo: Synced post #%d to Yoast SEO',
                $post_id
            ));
        }
    }

    /**
     * Set Yoast robots meta
     *
     * @param int    $post_id Post ID
     * @param string $robots  Robots string
     */
    private static function set_yoast_robots($post_id, $robots) {
        $robots = strtolower($robots);

        // Noindex
        if (strpos($robots, 'noindex') !== false) {
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', '1');
        } else {
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', '0');
        }

        // Nofollow
        if (strpos($robots, 'nofollow') !== false) {
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', '1');
        } else {
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', '0');
        }

        // Advanced robots (Yoast Premium)
        $advanced = array();
        if (strpos($robots, 'noarchive') !== false) {
            $advanced[] = 'noarchive';
        }
        if (strpos($robots, 'nosnippet') !== false) {
            $advanced[] = 'nosnippet';
        }
        if (!empty($advanced)) {
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-adv', implode(',', $advanced));
        }
    }

    /**
     * Có nên disable Yoast OG output không
     * 
     * Yoast không có filter trực tiếp để disable OG
     * Workaround: Hook vào filter và return false khi cần
     *
     * @param string $url OG URL
     * @return string|false
     */
    public static function maybe_disable_og($url) {
        // Nếu đang xem bài GenSeo và GenSeo OG đang bật
        if (is_singular('post')) {
            $post_id = get_the_ID();

            if (genseo_is_genseo_post($post_id) && genseo_get_setting('enable_opengraph', true)) {
                // Disable Yoast OG
                // Tuy nhiên, cách tốt nhất là sync data và để Yoast output
                // Hoặc disable hoàn toàn Yoast OG qua filter
                add_filter('wpseo_frontend_presenters', array(__CLASS__, 'remove_yoast_og_presenters'), 10, 1);
            }
        }

        return $url;
    }

    /**
     * Remove Yoast OG presenters cho GenSeo posts
     *
     * @param array $presenters Array of presenter classes
     * @return array
     */
    public static function remove_yoast_og_presenters($presenters) {
        if (!is_singular('post')) {
            return $presenters;
        }

        $post_id = get_the_ID();
        if (!genseo_is_genseo_post($post_id)) {
            return $presenters;
        }

        if (!genseo_get_setting('enable_opengraph', true)) {
            return $presenters;
        }

        // Remove OG presenters
        $og_presenters = array(
            'Yoast\WP\SEO\Presenters\Open_Graph\Title_Presenter',
            'Yoast\WP\SEO\Presenters\Open_Graph\Description_Presenter',
            'Yoast\WP\SEO\Presenters\Open_Graph\Image_Presenter',
            'Yoast\WP\SEO\Presenters\Open_Graph\Url_Presenter',
            'Yoast\WP\SEO\Presenters\Open_Graph\Type_Presenter',
            'Yoast\WP\SEO\Presenters\Open_Graph\Site_Name_Presenter',
            'Yoast\WP\SEO\Presenters\Open_Graph\Locale_Presenter',
            'Yoast\WP\SEO\Presenters\Twitter\Card_Presenter',
            'Yoast\WP\SEO\Presenters\Twitter\Title_Presenter',
            'Yoast\WP\SEO\Presenters\Twitter\Description_Presenter',
            'Yoast\WP\SEO\Presenters\Twitter\Image_Presenter',
        );

        return array_filter($presenters, function($presenter) use ($og_presenters) {
            return !in_array(get_class($presenter), $og_presenters, true);
        });
    }
}
