<?php
/**
 * Twitter Cards - Output Twitter meta tags trong <head>
 *
 * @package GenSeo_SEO_Helper
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_Twitter
 *
 * Output Twitter Card meta tags cho bài viết từ GenSeo Desktop
 */
class GenSeo_Twitter {

    /**
     * Khởi tạo class
     */
    public static function init() {
        // Hook vào wp_head với priority 6 (sau OpenGraph)
        add_action('wp_head', array(__CLASS__, 'output'), 6);
    }

    /**
     * Output Twitter Card meta tags
     */
    public static function output() {
        try {
            self::do_output();
        } catch (\Throwable $e) {
            error_log('[GenSeo] Twitter Cards output error: ' . $e->getMessage());
        }
    }

    /**
     * Internal output (wrapped by try/catch in output())
     */
    private static function do_output() {
        // Chỉ output cho singular posts
        if (!is_singular('post')) {
            return;
        }

        // Kiểm tra setting có bật không
        if (!genseo_get_setting('enable_twitter_cards', true)) {
            return;
        }

        $post_id = get_the_ID();

        // Chỉ output cho bài từ GenSeo
        if (!genseo_is_genseo_post($post_id)) {
            return;
        }

        // Skip nếu RankMath/Yoast đã output (data đã sync sang)
        if (
            (genseo_is_rankmath_active() && genseo_get_setting('enable_rankmath_sync', true)) ||
            (genseo_is_yoast_active() && genseo_get_setting('enable_yoast_sync', true))
        ) {
            return;
        }

        // Lấy data
        $seo_title = genseo_get_post_meta($post_id, 'seo_title');
        $meta_desc = genseo_get_post_meta($post_id, 'meta_desc');
        $og_image  = genseo_get_post_meta($post_id, 'og_image');

        // Fallbacks
        if (empty($seo_title)) {
            $seo_title = get_the_title($post_id);
        }

        if (empty($meta_desc)) {
            $meta_desc = get_the_excerpt($post_id);
            if (empty($meta_desc)) {
                $meta_desc = wp_trim_words(get_the_content(null, false, $post_id), 30);
            }
        }

        // Image
        $image_url = self::get_twitter_image($post_id, $og_image);

        // Twitter username từ settings
        $twitter_username = genseo_get_setting('twitter_username', '');

        // Card type: summary_large_image nếu có ảnh, summary nếu không
        $card_type = !empty($image_url) ? 'summary_large_image' : 'summary';

        // Start output
        echo "\n<!-- GenSeo SEO Helper - Twitter Cards -->\n";

        self::output_tag('twitter:card', $card_type);
        self::output_tag('twitter:title', $seo_title);
        self::output_tag('twitter:description', $meta_desc);

        if (!empty($image_url)) {
            self::output_tag('twitter:image', $image_url);
            self::output_tag('twitter:image:alt', $seo_title);
        }

        if (!empty($twitter_username)) {
            // Đảm bảo có @ ở đầu
            $twitter_username = ltrim($twitter_username, '@');
            self::output_tag('twitter:site', '@' . $twitter_username);
            self::output_tag('twitter:creator', '@' . $twitter_username);
        }

        echo "<!-- /GenSeo SEO Helper - Twitter Cards -->\n\n";
    }

    /**
     * Lấy URL ảnh cho Twitter Card
     *
     * @param int    $post_id  Post ID
     * @param string $og_image Custom OG image URL
     * @return string
     */
    private static function get_twitter_image($post_id, $og_image) {
        // Ưu tiên 1: Custom OG image
        if (!empty($og_image)) {
            return $og_image;
        }

        // Ưu tiên 2: Featured image
        if (has_post_thumbnail($post_id)) {
            return get_the_post_thumbnail_url($post_id, 'large');
        }

        // Ưu tiên 3: Default từ settings
        $default_og_id = genseo_get_setting('default_og_image', 0);
        if ($default_og_id) {
            return wp_get_attachment_url($default_og_id);
        }

        return '';
    }

    /**
     * Output một Twitter meta tag
     *
     * @param string $name    Name attribute
     * @param string $content Content value
     */
    private static function output_tag($name, $content) {
        if (empty($content)) {
            return;
        }

        printf(
            '<meta name="%s" content="%s" />' . "\n",
            esc_attr($name),
            genseo_esc_meta($content)
        );
    }
}
