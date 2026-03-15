<?php
/**
 * Schema.org - Output JSON-LD structured data
 *
 * @package GenSeo_SEO_Helper
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_Schema
 *
 * Output Schema.org JSON-LD cho bài viết từ GenSeo Desktop
 */
class GenSeo_Schema {

    /**
     * Khởi tạo class
     */
    public static function init() {
        // Hook vào wp_head với priority 10
        add_action('wp_head', array(__CLASS__, 'output'), 10);
    }

    /**
     * Output Schema.org JSON-LD
     */
    public static function output() {
        // Chỉ output cho singular posts
        if (!is_singular('post')) {
            return;
        }

        // Kiểm tra setting có bật không
        if (!genseo_get_setting('enable_schema', true)) {
            return;
        }

        $post_id = get_the_ID();

        // Chỉ output cho bài từ GenSeo
        if (!genseo_is_genseo_post($post_id)) {
            return;
        }

        // Skip nếu RankMath/Yoast đã output Schema (data đã sync sang)
        if (
            (genseo_is_rankmath_active() && genseo_get_setting('enable_rankmath_sync', true)) ||
            (genseo_is_yoast_active() && genseo_get_setting('enable_yoast_sync', true))
        ) {
            return;
        }

        // Lấy schema type
        $schema_type = genseo_get_post_meta($post_id, 'schema_type');
        if (empty($schema_type)) {
            $schema_type = genseo_get_setting('schema_type_default', 'Article');
        }

        // Generate schema based on type
        switch ($schema_type) {
            case 'HowTo':
                $schema = self::generate_howto_schema($post_id);
                break;
            case 'FAQ':
                $schema = self::generate_faq_schema($post_id);
                break;
            case 'Article':
            default:
                $schema = self::generate_article_schema($post_id);
                break;
        }

        if (empty($schema)) {
            return;
        }

        // Output JSON-LD
        echo "\n<!-- GenSeo SEO Helper - Schema.org JSON-LD -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\n</script>\n";
        echo "<!-- /GenSeo SEO Helper - Schema.org -->\n\n";
    }

    /**
     * Generate Article Schema
     *
     * @param int $post_id Post ID
     * @return array
     */
    public static function generate_article_schema($post_id) {
        $post = get_post($post_id);

        // Lấy data
        $seo_title = genseo_get_post_meta($post_id, 'seo_title');
        $meta_desc = genseo_get_post_meta($post_id, 'meta_desc');
        $keywords  = genseo_get_post_meta($post_id, 'focus_keyword');
        $secondary = genseo_get_post_meta($post_id, 'secondary_keywords');
        $word_count = genseo_get_post_meta($post_id, 'word_count');

        // Fallbacks
        if (empty($seo_title)) {
            $seo_title = $post->post_title;
        }
        if (empty($meta_desc)) {
            $meta_desc = wp_trim_words(strip_tags($post->post_content), 30);
        }

        // Keywords array
        $all_keywords = array();
        if (!empty($keywords)) {
            $all_keywords[] = $keywords;
        }
        if (!empty($secondary) && is_array($secondary)) {
            $all_keywords = array_merge($all_keywords, $secondary);
        }

        // Author
        $author = get_userdata($post->post_author);
        $author_name = $author ? $author->display_name : 'Unknown';
        $author_url = $author ? get_author_posts_url($author->ID) : '';

        // Image
        $image_data = self::get_image_data($post_id);

        // Publisher
        $publisher = self::get_publisher_data();

        // Build schema
        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'Article',
            'headline'    => $seo_title,
            'description' => $meta_desc,
            'url'         => get_permalink($post_id),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => get_permalink($post_id),
            ),
            'datePublished' => get_the_date('c', $post_id),
            'dateModified'  => get_the_modified_date('c', $post_id),
            'author'        => array(
                '@type' => 'Person',
                'name'  => $author_name,
                'url'   => $author_url,
            ),
            'publisher'     => $publisher,
        );

        // Image
        if (!empty($image_data)) {
            $schema['image'] = $image_data;
        }

        // Word count
        if (!empty($word_count)) {
            $schema['wordCount'] = (int) $word_count;
        }

        // Keywords
        if (!empty($all_keywords)) {
            $schema['keywords'] = implode(', ', $all_keywords);
        }

        // Article section (category)
        $categories = get_the_category($post_id);
        if (!empty($categories)) {
            $schema['articleSection'] = $categories[0]->name;
        }

        // In language
        $schema['inLanguage'] = get_bloginfo('language');

        return $schema;
    }

    /**
     * Generate HowTo Schema
     * (Cho bài viết hướng dẫn)
     *
     * @param int $post_id Post ID
     * @return array
     */
    public static function generate_howto_schema($post_id) {
        $post = get_post($post_id);

        $seo_title = genseo_get_post_meta($post_id, 'seo_title');
        $meta_desc = genseo_get_post_meta($post_id, 'meta_desc');

        if (empty($seo_title)) {
            $seo_title = $post->post_title;
        }
        if (empty($meta_desc)) {
            $meta_desc = wp_trim_words(strip_tags($post->post_content), 30);
        }

        // Image
        $image_data = self::get_image_data($post_id);

        // Extract steps from content (H2 headings)
        $steps = self::extract_howto_steps($post->post_content);

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'HowTo',
            'name'        => $seo_title,
            'description' => $meta_desc,
            'url'         => get_permalink($post_id),
            'datePublished' => get_the_date('c', $post_id),
            'dateModified'  => get_the_modified_date('c', $post_id),
        );

        if (!empty($image_data)) {
            $schema['image'] = $image_data;
        }

        if (!empty($steps)) {
            $schema['step'] = $steps;
        }

        // Estimated time (giả định từ số từ)
        $word_count = genseo_get_post_meta($post_id, 'word_count');
        if (!empty($word_count)) {
            // Giả định đọc 200 từ/phút
            $minutes = max(1, ceil($word_count / 200));
            $schema['totalTime'] = 'PT' . $minutes . 'M';
        }

        return $schema;
    }

    /**
     * Generate FAQ Schema
     * (Cho bài viết FAQ)
     *
     * @param int $post_id Post ID
     * @return array
     */
    public static function generate_faq_schema($post_id) {
        $post = get_post($post_id);

        $seo_title = genseo_get_post_meta($post_id, 'seo_title');

        if (empty($seo_title)) {
            $seo_title = $post->post_title;
        }

        // Extract Q&A from content
        $faqs = self::extract_faq_items($post->post_content);

        if (empty($faqs)) {
            // Fallback to Article schema if no FAQ found
            return self::generate_article_schema($post_id);
        }

        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'name'       => $seo_title,
            'url'        => get_permalink($post_id),
            'mainEntity' => $faqs,
        );

        return $schema;
    }

    /**
     * Extract HowTo steps từ content
     *
     * @param string $content Post content
     * @return array
     */
    private static function extract_howto_steps($content) {
        $steps = array();

        // Tìm tất cả H2 headings
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content, $matches);

        if (empty($matches[1])) {
            return $steps;
        }

        foreach ($matches[1] as $index => $heading) {
            $heading = wp_strip_all_tags($heading);

            // Bỏ qua headings không liên quan
            if (stripos($heading, 'kết luận') !== false 
                || stripos($heading, 'conclusion') !== false
                || stripos($heading, 'tổng kết') !== false) {
                continue;
            }

            $steps[] = array(
                '@type' => 'HowToStep',
                'name'  => $heading,
                'position' => count($steps) + 1,
            );
        }

        return $steps;
    }

    /**
     * Extract FAQ items từ content
     * (Tìm pattern Q: ... A: ... hoặc H3 questions)
     *
     * @param string $content Post content
     * @return array
     */
    private static function extract_faq_items($content) {
        $faqs = array();

        // Pattern 1: H3 as question, following content as answer
        preg_match_all('/<h3[^>]*>(.*?)<\/h3>(.*?)(?=<h[23]|$)/is', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $question = wp_strip_all_tags($match[1]);
            $answer = wp_strip_all_tags($match[2]);
            $answer = trim($answer);

            // Chỉ lấy những câu hỏi (có dấu ? hoặc bắt đầu bằng từ hỏi)
            $is_question = preg_match('/\?$|^(what|how|why|when|where|who|which|can|do|does|is|are|was|were|tại sao|như thế nào|khi nào|ở đâu|ai |cái gì|là gì)/i', $question);

            if ($is_question && !empty($answer)) {
                $faqs[] = array(
                    '@type' => 'Question',
                    'name'  => $question,
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text'  => $answer,
                    ),
                );
            }
        }

        return $faqs;
    }

    /**
     * Lấy image data cho schema
     *
     * @param int $post_id Post ID
     * @return array|null
     */
    private static function get_image_data($post_id) {
        // Ưu tiên OG image
        $og_image = genseo_get_post_meta($post_id, 'og_image');
        $og_image_id = genseo_get_post_meta($post_id, 'og_image_id');

        if (!empty($og_image)) {
            $image = array(
                '@type' => 'ImageObject',
                'url'   => $og_image,
            );

            if (!empty($og_image_id)) {
                $metadata = wp_get_attachment_metadata($og_image_id);
                if ($metadata) {
                    $image['width'] = $metadata['width'] ?? '';
                    $image['height'] = $metadata['height'] ?? '';
                }
            }

            return $image;
        }

        // Featured image
        if (has_post_thumbnail($post_id)) {
            $thumbnail_id = get_post_thumbnail_id($post_id);
            $thumbnail = wp_get_attachment_image_src($thumbnail_id, 'full');

            if ($thumbnail) {
                return array(
                    '@type'  => 'ImageObject',
                    'url'    => $thumbnail[0],
                    'width'  => $thumbnail[1],
                    'height' => $thumbnail[2],
                );
            }
        }

        return null;
    }

    /**
     * Lấy publisher data
     *
     * @return array
     */
    private static function get_publisher_data() {
        $publisher = array(
            '@type' => 'Organization',
            'name'  => get_bloginfo('name'),
            'url'   => get_site_url(),
        );

        // Logo từ settings
        $logo_id = genseo_get_setting('publisher_logo', 0);
        if ($logo_id) {
            $logo = wp_get_attachment_image_src($logo_id, 'full');
            if ($logo) {
                $publisher['logo'] = array(
                    '@type'  => 'ImageObject',
                    'url'    => $logo[0],
                    'width'  => $logo[1],
                    'height' => $logo[2],
                );
            }
        } else {
            // Fallback to site icon
            $site_icon_id = get_option('site_icon');
            if ($site_icon_id) {
                $icon = wp_get_attachment_image_src($site_icon_id, 'full');
                if ($icon) {
                    $publisher['logo'] = array(
                        '@type'  => 'ImageObject',
                        'url'    => $icon[0],
                        'width'  => $icon[1],
                        'height' => $icon[2],
                    );
                }
            }
        }

        return $publisher;
    }
}
