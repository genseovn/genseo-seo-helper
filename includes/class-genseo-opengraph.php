<?php
/**
 * OpenGraph - Output OG meta tags trong <head>
 *
 * @package GenSeo_SEO_Helper
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_OpenGraph
 *
 * Output OpenGraph meta tags cho bài viết từ GenSeo Desktop
 */
class GenSeo_OpenGraph {

    /**
     * Khởi tạo class
     */
    public static function init() {
        // Hook vào wp_head với priority 5 (chạy sớm)
        add_action('wp_head', array(__CLASS__, 'output'), 5);
    }

    /**
     * Output OpenGraph meta tags
     */
    public static function output() {
        try {
            self::do_output();
        } catch (\Throwable $e) {
            error_log('[GenSeo] OpenGraph output error: ' . $e->getMessage());
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
        if (!genseo_get_setting('enable_opengraph', true)) {
            return;
        }

        $post_id = get_the_ID();

        // Chỉ output cho bài từ GenSeo
        if (!genseo_is_genseo_post($post_id)) {
            return;
        }

        // Kiểm tra xem có SEO plugin khác đã output OG chưa
        if (self::should_skip_output()) {
            return;
        }

        // Lấy data
        $seo_title  = genseo_get_post_meta($post_id, 'seo_title');
        $meta_desc  = genseo_get_post_meta($post_id, 'meta_desc');
        $og_image   = genseo_get_post_meta($post_id, 'og_image');
        $og_image_id = genseo_get_post_meta($post_id, 'og_image_id');

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

        // OG Image: Ưu tiên custom > featured image > default
        $og_image_data = self::get_og_image($post_id, $og_image, $og_image_id);

        // Get post dates
        $post = get_post($post_id);
        $published_time = get_the_date('c', $post_id);
        $modified_time  = get_the_modified_date('c', $post_id);

        // Author
        $author_id = $post->post_author;
        $author_url = get_author_posts_url($author_id);

        // Site info
        $site_name = get_bloginfo('name');
        $locale    = get_locale();

        // Start output
        echo "\n<!-- GenSeo SEO Helper v" . GENSEO_VERSION . " - OpenGraph -->\n";

        // Basic OG tags
        self::output_tag('og:type', 'article');
        self::output_tag('og:title', $seo_title);
        self::output_tag('og:description', $meta_desc);
        self::output_tag('og:url', get_permalink($post_id));
        self::output_tag('og:site_name', $site_name);
        self::output_tag('og:locale', $locale);

        // OG Image
        if (!empty($og_image_data['url'])) {
            self::output_tag('og:image', $og_image_data['url']);
            if (!empty($og_image_data['width'])) {
                self::output_tag('og:image:width', $og_image_data['width']);
            }
            if (!empty($og_image_data['height'])) {
                self::output_tag('og:image:height', $og_image_data['height']);
            }
            if (!empty($og_image_data['type'])) {
                self::output_tag('og:image:type', $og_image_data['type']);
            }
            self::output_tag('og:image:alt', $seo_title);
        }

        // Article specific
        self::output_tag('article:published_time', $published_time);
        self::output_tag('article:modified_time', $modified_time);
        self::output_tag('article:author', $author_url);

        // Categories as article:section
        $categories = get_the_category($post_id);
        if (!empty($categories)) {
            self::output_tag('article:section', $categories[0]->name);
        }

        // Tags as article:tag
        $tags = get_the_tags($post_id);
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                self::output_tag('article:tag', $tag->name);
            }
        }

        echo "<!-- /GenSeo SEO Helper - OpenGraph -->\n\n";
    }

    /**
     * Lấy thông tin OG Image
     *
     * @param int    $post_id    Post ID
     * @param string $og_image   Custom OG image URL
     * @param int    $og_image_id OG image Media ID
     * @return array
     */
    private static function get_og_image($post_id, $og_image, $og_image_id) {
        $image_data = array(
            'url'    => '',
            'width'  => '',
            'height' => '',
            'type'   => '',
        );

        // Ưu tiên 1: Custom OG image từ GenSeo meta
        if (!empty($og_image)) {
            $image_data['url'] = $og_image;

            // Nếu có Media ID, lấy thêm dimensions
            if (!empty($og_image_id)) {
                $attachment = wp_get_attachment_metadata($og_image_id);
                if ($attachment) {
                    $image_data['width']  = $attachment['width'] ?? '';
                    $image_data['height'] = $attachment['height'] ?? '';
                    $image_data['type']   = get_post_mime_type($og_image_id);
                }
            }

            return $image_data;
        }

        // Ưu tiên 2: Featured image
        if (has_post_thumbnail($post_id)) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            $thumbnail = wp_get_attachment_image_src($thumbnail_id, 'full');

            if ($thumbnail) {
                $image_data['url']    = $thumbnail[0];
                $image_data['width']  = $thumbnail[1];
                $image_data['height'] = $thumbnail[2];
                $image_data['type']   = get_post_mime_type($thumbnail_id);

                return $image_data;
            }
        }

        // Ưu tiên 3: Default OG image từ settings
        $default_og_id = genseo_get_setting('default_og_image', 0);
        if ($default_og_id) {
            $default = wp_get_attachment_image_src($default_og_id, 'full');
            if ($default) {
                $image_data['url']    = $default[0];
                $image_data['width']  = $default[1];
                $image_data['height'] = $default[2];
                $image_data['type']   = get_post_mime_type($default_og_id);
            }
        }

        return $image_data;
    }

    /**
     * Kiểm tra có nên skip output không
     * (Nếu RankMath/Yoast đã output)
     *
     * @return bool
     */
    private static function should_skip_output() {
        // RankMath: nếu active + sync bật → data đã sync sang RankMath, để RankMath output
        if (genseo_is_rankmath_active() && genseo_get_setting('enable_rankmath_sync', true)) {
            return true;
        }

        // Yoast: tương tự
        if (genseo_is_yoast_active() && genseo_get_setting('enable_yoast_sync', true)) {
            return true;
        }

        // Không có SEO plugin → GenSeo tự output
        return false;
    }

    /**
     * Output một OG meta tag
     *
     * @param string $property Property name
     * @param string $content  Content value
     */
    private static function output_tag($property, $content) {
        if (empty($content)) {
            return;
        }

        printf(
            '<meta property="%s" content="%s" />' . "\n",
            esc_attr($property),
            genseo_esc_meta($content)
        );
    }
}
