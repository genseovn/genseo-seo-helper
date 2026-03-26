<?php
/**
 * Uninstall - Cleanup khi gỡ plugin
 *
 * @package GenSeo_SEO_Helper
 * @since 1.0.0
 */

// Chỉ chạy khi WordPress gọi uninstall
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Xóa tất cả data của plugin
 *
 * Lưu ý: Chỉ xóa options, KHÔNG xóa post meta
 * vì user có thể muốn giữ lại data SEO
 */

// Xóa plugin options (settings chung)
delete_option('genseo_settings');

// GIỮ LẠI genseo_api_key và genseo_api_key_user_id
// để khi cài lại plugin, các kết nối desktop app không bị mất
// Admin có thể tự xóa thủ công: wp option delete genseo_api_key genseo_api_key_user_id

// Xóa transients nếu có
delete_transient('genseo_cache');

// Xóa scheduled events nếu có
wp_clear_scheduled_hook('genseo_daily_cleanup');

// Log uninstall
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('GenSeo SEO Helper uninstalled and cleaned up');
}

/**
 * KHÔNG XÓA POST META
 * 
 * Lý do: User có thể reinstall plugin hoặc
 * muốn giữ lại data SEO đã có.
 * 
 * Nếu muốn xóa hoàn toàn, uncomment code bên dưới:
 */

/*
global $wpdb;

// Xóa tất cả GenSeo post meta
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
        '_genseo_%'
    )
);
*/
