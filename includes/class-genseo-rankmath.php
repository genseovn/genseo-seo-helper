<?php
/**
 * RankMath Integration - Sync SEO data sang Rank Math SEO
 *
 * @package GenSeo_SEO_Helper
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_RankMath
 *
 * Tự động sync GenSeo meta fields sang Rank Math SEO
 */
class GenSeo_RankMath {

    /**
     * Mapping GenSeo fields → RankMath fields
     *
     * @var array
     */
    private static $field_mapping = array(
        '_genseo_seo_title'     => 'rank_math_title',
        '_genseo_meta_desc'     => 'rank_math_description',
        '_genseo_focus_keyword' => 'rank_math_focus_keyword',
        '_genseo_canonical_url' => 'rank_math_canonical_url',
        '_genseo_og_image'      => 'rank_math_facebook_image',
        '_genseo_og_image_id'   => 'rank_math_facebook_image_id',
    );

    /**
     * Khởi tạo class
     */
    public static function init() {
        // Sync sau khi post được lưu
        add_action('save_post', array(__CLASS__, 'sync_meta'), 20, 3);

        // Sync khi REST API update post
        add_action('rest_after_insert_post', array(__CLASS__, 'sync_meta_rest'), 20, 2);

        // Không cần disable RankMath OG nữa
        // GenSeo sẽ tự skip output khi RankMath active + sync bật
        // Để RankMath output OG vì data đã được sync sang
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
        if (!genseo_get_setting('enable_rankmath_sync', true)) {
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
        if (!genseo_get_setting('enable_rankmath_sync', true)) {
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
        foreach (self::$field_mapping as $genseo_key => $rankmath_key) {
            $value = get_post_meta($post_id, $genseo_key, true);

            if (!empty($value)) {
                update_post_meta($post_id, $rankmath_key, $value);
            }
        }

        // Xử lý robots meta đặc biệt
        $robots = get_post_meta($post_id, '_genseo_robots', true);
        if (!empty($robots)) {
            $robots_array = self::parse_robots_meta($robots);
            update_post_meta($post_id, 'rank_math_robots', $robots_array);
        }

        // Xử lý secondary keywords
        $focus = get_post_meta($post_id, '_genseo_focus_keyword', true);
        $secondary = get_post_meta($post_id, '_genseo_secondary_keywords', true);

        if (!empty($secondary) && is_array($secondary)) {
            // RankMath lưu multiple focus keywords cách nhau bằng dấu phẩy
            $all_keywords = $focus;
            if (!empty($secondary)) {
                $all_keywords .= ',' . implode(',', $secondary);
            }
            update_post_meta($post_id, 'rank_math_focus_keyword', $all_keywords);
        }

        // Twitter Card: dùng chung data với Facebook OG
        update_post_meta($post_id, 'rank_math_twitter_use_facebook', 'on');
        update_post_meta($post_id, 'rank_math_twitter_card_type', 'summary_large_image');

        // Pillar content flag cho bài dài (>1500 từ)
        $word_count = get_post_meta($post_id, '_genseo_word_count', true);
        if (!empty($word_count) && intval($word_count) >= 1500) {
            update_post_meta($post_id, 'rank_math_pillar_content', 'on');
        }

        // Log sync
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'GenSeo: Synced post #%d to RankMath',
                $post_id
            ));
        }
    }

    /**
     * Parse robots meta string thành array cho RankMath
     *
     * @param string $robots Robots string (vd: "noindex, nofollow")
     * @return array
     */
    private static function parse_robots_meta($robots) {
        $robots_array = array();

        $robots = strtolower($robots);

        if (strpos($robots, 'noindex') !== false) {
            $robots_array[] = 'noindex';
        } else {
            $robots_array[] = 'index';
        }

        if (strpos($robots, 'nofollow') !== false) {
            $robots_array[] = 'nofollow';
        }

        if (strpos($robots, 'noarchive') !== false) {
            $robots_array[] = 'noarchive';
        }

        if (strpos($robots, 'nosnippet') !== false) {
            $robots_array[] = 'nosnippet';
        }

        return $robots_array;
    }

    /**
     * Có nên disable RankMath OG output không
     *
     * @param bool $disable Current disable status
     * @return bool
     */
    public static function maybe_disable_og($disable) {
        // Nếu đang xem bài GenSeo và GenSeo OG đang bật
        if (is_singular('post')) {
            $post_id = get_the_ID();

            if (genseo_is_genseo_post($post_id) && genseo_get_setting('enable_opengraph', true)) {
                // Để GenSeo output OG thay vì RankMath
                return true;
            }
        }

        return $disable;
    }
}
