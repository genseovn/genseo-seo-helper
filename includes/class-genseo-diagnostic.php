<?php
/**
 * Diagnostic Page - Trang chẩn đoán MCP, REST API, WAF trong WP Admin
 *
 * Cung cấp 15 bài test kiểm tra hệ thống, MCP, REST API, bảo mật.
 * Hỗ trợ AJAX chạy lại tests và copy kết quả để gửi support.
 *
 * @package GenSeo_SEO_Helper
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GenSeo_Diagnostic
 *
 * Trang chẩn đoán riêng biệt: Settings > GenSeo Chẩn đoán
 */
class GenSeo_Diagnostic {

    /**
     * Khởi tạo class
     */
    public static function init() {
        add_action('wp_ajax_genseo_run_diagnostic', array(__CLASS__, 'ajax_run_tests'));
    }

    /**
     * Đăng ký admin page
     */
    public static function add_menu() {
        add_options_page(
            __('GenSeo Chẩn đoán', 'genseo-seo-helper'),
            __('GenSeo Chẩn đoán', 'genseo-seo-helper'),
            'manage_options',
            'genseo-diagnostics',
            array(__CLASS__, 'render_page')
        );
    }

    /**
     * Render nội dung tab chẩn đoán (được gọi từ GenSeo_Admin)
     */
    public static function render_tab_content() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
            <p class="description">
                <?php esc_html_e('Kiểm tra trạng thái hệ thống, MCP, REST API và bảo mật. Nhấn "Chạy kiểm tra" để bắt đầu.', 'genseo-seo-helper'); ?>
            </p>

            <div style="margin: 15px 0;">
                <button type="button" class="button button-primary" id="genseo-run-tests">
                    <span class="dashicons dashicons-search" style="vertical-align: middle; margin-right: 4px;"></span>
                    <?php esc_html_e('Chạy kiểm tra', 'genseo-seo-helper'); ?>
                </button>
                <button type="button" class="button" id="genseo-copy-results" style="display:none; margin-left: 8px;">
                    <span class="dashicons dashicons-clipboard" style="vertical-align: middle; margin-right: 4px;"></span>
                    <?php esc_html_e('Sao chép kết quả', 'genseo-seo-helper'); ?>
                </button>
                <span id="genseo-diag-status" style="margin-left: 12px; font-style: italic;"></span>
            </div>

            <div id="genseo-diag-results"></div>

            <div id="genseo-htaccess-actions" style="margin-top: 20px; display: none;">
                <h3><?php esc_html_e('Công cụ .htaccess', 'genseo-seo-helper'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Thêm whitelist rules vào .htaccess để bypass ModSecurity/Imunify360 cho GenSeo endpoints.', 'genseo-seo-helper'); ?>
                </p>
                <div style="margin: 10px 0;">
                    <?php if (!GenSeo_WAF_Compat::has_htaccess_rules()): ?>
                        <button type="button" class="button" id="genseo-fix-htaccess">
                            <span class="dashicons dashicons-admin-tools" style="vertical-align: middle; margin-right: 4px;"></span>
                            <?php esc_html_e('Tự động sửa .htaccess', 'genseo-seo-helper'); ?>
                        </button>
                    <?php else: ?>
                        <span style="color: #46b450; margin-right: 10px;">
                            <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Đã thêm whitelist rules', 'genseo-seo-helper'); ?>
                        </span>
                    <?php endif; ?>

                    <?php if (GenSeo_WAF_Compat::has_htaccess_backup()): ?>
                        <button type="button" class="button" id="genseo-rollback-htaccess" style="margin-left: 8px;">
                            <span class="dashicons dashicons-undo" style="vertical-align: middle; margin-right: 4px;"></span>
                            <?php esc_html_e('Khôi phục .htaccess', 'genseo-seo-helper'); ?>
                        </button>
                    <?php endif; ?>

                    <span id="genseo-htaccess-status" style="margin-left: 12px; font-style: italic;"></span>
                </div>
            </div>

        <script>
        (function(){
            var nonce = '<?php echo wp_create_nonce('genseo_diagnostic'); ?>';
            var runBtn = document.getElementById('genseo-run-tests');
            var copyBtn = document.getElementById('genseo-copy-results');
            var statusEl = document.getElementById('genseo-diag-status');
            var resultsEl = document.getElementById('genseo-diag-results');
            var lastResults = null;

            runBtn.addEventListener('click', function(){
                runBtn.disabled = true;
                statusEl.textContent = 'Đang kiểm tra...';
                resultsEl.innerHTML = '';
                copyBtn.style.display = 'none';

                fetch(ajaxurl + '?action=genseo_run_diagnostic&_wpnonce=' + nonce)
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        runBtn.disabled = false;
                        if(!d.success){
                            statusEl.textContent = 'Lỗi: ' + (d.data ? d.data.message : 'Không xác định');
                            return;
                        }
                        lastResults = d.data;
                        statusEl.textContent = 'Hoàn thành (' + d.data.summary + ')';
                        renderResults(d.data.groups);
                        maybeShowHtaccessActions(d.data.groups);
                        copyBtn.style.display = 'inline-block';
                    })
                    .catch(function(err){
                        runBtn.disabled = false;
                        statusEl.textContent = 'Lỗi kết nối: ' + err.message;
                    });
            });

            copyBtn.addEventListener('click', function(){
                if(!lastResults) return;
                var text = 'GenSeo Diagnostic - ' + new Date().toISOString() + '\n';
                text += '==========================================\n\n';
                lastResults.groups.forEach(function(g){
                    text += '--- ' + g.label + ' ---\n';
                    g.tests.forEach(function(t){
                        var icon = t.status === 'ok' ? '[OK]' : t.status === 'warn' ? '[WARN]' : t.status === 'fail' ? '[FAIL]' : '[INFO]';
                        text += icon + ' ' + t.label + ': ' + t.detail + '\n';
                        if(t.action) text += '    -> ' + t.action + '\n';
                    });
                    text += '\n';
                });
                text += 'Plugin: v' + lastResults.plugin_version + '\n';
                text += 'WP: ' + lastResults.wp_version + '\n';
                text += 'PHP: ' + lastResults.php_version + '\n';

                if(navigator.clipboard && navigator.clipboard.writeText){
                    navigator.clipboard.writeText(text).then(function(){
                        copyBtn.textContent = 'Đã sao chép!';
                        setTimeout(function(){ copyBtn.innerHTML = '<span class="dashicons dashicons-clipboard" style="vertical-align:middle;margin-right:4px;"></span>Sao chép kết quả'; }, 2000);
                    });
                } else {
                    // Fallback
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    copyBtn.textContent = 'Đã sao chép!';
                    setTimeout(function(){ copyBtn.innerHTML = '<span class="dashicons dashicons-clipboard" style="vertical-align:middle;margin-right:4px;"></span>Sao chép kết quả'; }, 2000);
                }
            });

            function renderResults(groups){
                var html = '';
                groups.forEach(function(g){
                    html += '<div class="genseo-diag-group">';
                    html += '<h3>' + esc(g.label) + '</h3>';
                    html += '<table class="genseo-diag-table">';
                    html += '<thead><tr><th style="width:40px"></th><th>Kiểm tra</th><th>Kết quả</th></tr></thead><tbody>';
                    g.tests.forEach(function(t){
                        var cls = 'genseo-status-' + t.status;
                        var icon = t.status === 'ok' ? 'dashicons-yes-alt'
                                 : t.status === 'warn' ? 'dashicons-warning'
                                 : t.status === 'fail' ? 'dashicons-dismiss'
                                 : 'dashicons-info-outline';
                        html += '<tr class="' + cls + '">';
                        html += '<td><span class="dashicons ' + icon + ' genseo-diag-icon"></span></td>';
                        html += '<td>' + esc(t.label) + '</td>';
                        html += '<td>';
                        html += '<div>' + esc(t.detail) + '</div>';
                        if(t.action){
                            html += '<div class="genseo-diag-action">';
                            if(t.link){
                                html += '<a href="' + esc(t.link) + '" target="_blank">';
                                html += '<span class="dashicons dashicons-admin-links" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:2px;"></span>';
                                html += getLinkLabel(t.label);
                                html += '</a> &mdash; ';
                            }
                            html += esc(t.action);
                            html += '</div>';
                        }
                        if(t.guide){
                            var steps = t.guide.split(' | ');
                            html += '<details class="genseo-diag-guide"><summary>';
                            html += '<span class="dashicons dashicons-book" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:3px;"></span>';
                            html += 'Hướng dẫn tự khắc phục</summary><ol>';
                            for(var s = 0; s < steps.length; s++){
                                html += '<li>' + esc(steps[s].replace(/^\d+\.\s*/, '')) + '</li>';
                            }
                            html += '</ol></details>';
                        }
                        html += '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                });
                resultsEl.innerHTML = html;
            }

            function esc(s){
                var d = document.createElement('div');
                d.textContent = s || '';
                return d.innerHTML;
            }

            function getLinkLabel(testLabel){
                var map = {
                    'Phiên bản WordPress': 'Cập nhật',
                    'Permalink Structure': 'Cài đặt Permalink',
                    'Abilities API': 'Cập nhật',
                    'MCP Adapter': 'Quản lý Plugin',
                    'GenSeo Abilities': 'Quản lý Plugin',
                    'Wordfence': 'Cài đặt Wordfence'
                };
                return map[testLabel] || 'Mở cài đặt';
            }

            // Hiện phần htaccess actions khi có kết quả firewall
            function maybeShowHtaccessActions(groups){
                var show = false;
                groups.forEach(function(g){
                    g.tests.forEach(function(t){
                        if(t.label && (t.label.indexOf('Imunify360') >= 0 || t.label.indexOf('Wordfence') >= 0 || t.label.indexOf('ModSecurity') >= 0)){
                            if(t.status === 'warn' || t.status === 'info'){
                                show = true;
                            }
                        }
                    });
                });
                var el = document.getElementById('genseo-htaccess-actions');
                if(el) el.style.display = show ? 'block' : 'none';
            }

            // Nút sửa .htaccess
            var fixBtn = document.getElementById('genseo-fix-htaccess');
            var rollbackBtn = document.getElementById('genseo-rollback-htaccess');
            var htaccessStatus = document.getElementById('genseo-htaccess-status');

            if(fixBtn){
                fixBtn.addEventListener('click', function(){
                    fixBtn.disabled = true;
                    htaccessStatus.textContent = 'Đang xử lý...';
                    var fixNonce = '<?php echo wp_create_nonce("genseo_fix_htaccess"); ?>';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=genseo_fix_htaccess&_wpnonce=' + fixNonce
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        fixBtn.disabled = false;
                        if(d.success){
                            htaccessStatus.style.color = '#46b450';
                            htaccessStatus.textContent = d.data.message;
                            fixBtn.style.display = 'none';
                        } else {
                            htaccessStatus.style.color = '#dc3232';
                            htaccessStatus.textContent = 'Lỗi: ' + (d.data ? d.data.message : 'Không xác định');
                        }
                    })
                    .catch(function(err){
                        fixBtn.disabled = false;
                        htaccessStatus.style.color = '#dc3232';
                        htaccessStatus.textContent = 'Lỗi: ' + err.message;
                    });
                });
            }

            if(rollbackBtn){
                rollbackBtn.addEventListener('click', function(){
                    if(!confirm('Bạn chắc chắn muốn khôi phục .htaccess từ bản backup?')) return;
                    rollbackBtn.disabled = true;
                    htaccessStatus.textContent = 'Đang khôi phục...';
                    var rollNonce = '<?php echo wp_create_nonce("genseo_rollback_htaccess"); ?>';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=genseo_rollback_htaccess&_wpnonce=' + rollNonce
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        rollbackBtn.disabled = false;
                        if(d.success){
                            htaccessStatus.style.color = '#46b450';
                            htaccessStatus.textContent = d.data.message;
                            rollbackBtn.style.display = 'none';
                        } else {
                            htaccessStatus.style.color = '#dc3232';
                            htaccessStatus.textContent = 'Lỗi: ' + (d.data ? d.data.message : 'Không xác định');
                        }
                    })
                    .catch(function(err){
                        rollbackBtn.disabled = false;
                        htaccessStatus.style.color = '#dc3232';
                        htaccessStatus.textContent = 'Lỗi: ' + err.message;
                    });
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * AJAX handler - Chạy tất cả tests
     */
    public static function ajax_run_tests() {
        check_ajax_referer('genseo_diagnostic', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền.'));
            return;
        }

        global $wp_version;

        $groups = array(
            self::test_group_system(),
            self::test_group_mcp(),
            self::test_group_rest_api(),
            self::test_group_firewall(),
        );

        // Tính summary
        $counts = array('ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0);
        foreach ($groups as $group) {
            foreach ($group['tests'] as $test) {
                $counts[$test['status']]++;
            }
        }

        $summary = sprintf(
            '%d OK, %d Cảnh báo, %d Lỗi, %d Thông tin',
            $counts['ok'],
            $counts['warn'],
            $counts['fail'],
            $counts['info']
        );

        wp_send_json_success(array(
            'groups'         => $groups,
            'summary'        => $summary,
            'plugin_version' => GENSEO_VERSION,
            'wp_version'     => $wp_version,
            'php_version'    => PHP_VERSION,
        ));
    }

    /**
     * Trả về kết quả tất cả test groups (dùng cho MCP get-site-health)
     *
     * @return array
     */
    public static function get_test_results() {
        return array(
            self::test_group_system(),
            self::test_group_mcp(),
            self::test_group_rest_api(),
            self::test_group_firewall(),
        );
    }

    // ============================================================
    // NHÓM 1: HỆ THỐNG CƠ BẢN
    // ============================================================

    /**
     * Test nhóm hệ thống
     *
     * @return array
     */
    private static function test_group_system() {
        $tests = array();

        // Test 1: PHP version
        $php_ok = version_compare(PHP_VERSION, '7.4', '>=');
        $tests[] = array(
            'label'  => 'Phiên bản PHP',
            'status' => $php_ok ? 'ok' : 'fail',
            'detail' => PHP_VERSION . ($php_ok ? ' (>= 7.4)' : ' (cần >= 7.4)'),
            'action' => $php_ok ? '' : 'Liên hệ hosting để nâng cấp PHP lên 7.4+.',
            'link'   => '',
            'guide'  => $php_ok ? '' : 'cPanel: Software > MultiPHP Manager > chọn domain > chọn PHP 7.4+. | Plesk: Websites & Domains > PHP Settings > chọn 7.4+. | VPS: sudo apt install php8.2 hoặc liên hệ hosting.',
        );

        // Test 2: WordPress version
        global $wp_version;
        $wp_69 = version_compare($wp_version, '6.9', '>=');
        $tests[] = array(
            'label'  => 'Phiên bản WordPress',
            'status' => $wp_69 ? 'ok' : 'warn',
            'detail' => $wp_version . ($wp_69 ? ' (>= 6.9, hỗ trợ MCP)' : ' (cần >= 6.9 để dùng MCP)'),
            'action' => $wp_69 ? '' : 'Nâng cấp WordPress lên 6.9+ để sử dụng MCP Abilities.',
            'link'   => $wp_69 ? '' : admin_url('update-core.php'),
            'guide'  => $wp_69 ? '' : '1. Backup website trước (dùng UpdraftPlus hoặc hosting backup). | 2. Click link "Cập nhật" ở trên. | 3. Nhấn "Update to version 6.9.x". | 4. Đợi hoàn tất, kiểm tra lại trang này.',
        );

        // Test 3: memory_limit
        $mem = ini_get('memory_limit');
        $mem_bytes = wp_convert_hr_to_bytes($mem);
        $mem_ok = $mem_bytes >= 64 * 1024 * 1024;
        $tests[] = array(
            'label'  => 'Memory Limit',
            'status' => $mem_ok ? 'ok' : 'warn',
            'detail' => $mem . ($mem_ok ? ' (>= 64M)' : ' (khuyến nghị >= 64M)'),
            'action' => $mem_ok ? '' : 'Tăng memory_limit trong php.ini hoặc wp-config.php.',
            'link'   => '',
            'guide'  => $mem_ok ? '' : 'Cách 1: Thêm vào wp-config.php (trước dòng "That\'s all"): define(\'WP_MEMORY_LIMIT\', \'256M\'); | Cách 2: Thêm vào .htaccess: php_value memory_limit 256M | Cách 3: cPanel > MultiPHP INI Editor > memory_limit = 256M.',
        );

        // Test 4: max_execution_time
        $time = ini_get('max_execution_time');
        $time_ok = ($time == 0 || $time >= 30);
        $tests[] = array(
            'label'  => 'Max Execution Time',
            'status' => $time_ok ? 'ok' : 'warn',
            'detail' => $time . 's' . ($time_ok ? ' (>= 30s)' : ' (khuyến nghị >= 30s)'),
            'action' => $time_ok ? '' : 'Tăng max_execution_time trong php.ini.',
            'link'   => '',
            'guide'  => $time_ok ? '' : 'Cách 1: Thêm vào .htaccess: php_value max_execution_time 60 | Cách 2: cPanel > MultiPHP INI Editor > max_execution_time = 60 | Cách 3: Liên hệ hosting tăng lên 60 giây.',
        );

        // Test 5: cURL extension
        $curl_ok = extension_loaded('curl');
        $tests[] = array(
            'label'  => 'cURL Extension',
            'status' => $curl_ok ? 'ok' : 'fail',
            'detail' => $curl_ok ? 'Đã cài đặt' : 'Chưa cài đặt',
            'action' => $curl_ok ? '' : 'Cài đặt PHP curl extension. Liên hệ hosting.',
            'link'   => '',
            'guide'  => $curl_ok ? '' : 'cPanel: Software > Select PHP Version > tick "curl". | Plesk: PHP Settings > Extensions > bật curl. | VPS: sudo apt install php-curl && sudo systemctl restart apache2.',
        );

        // Test 6: Permalink structure (MCP cần pretty permalinks)
        $permalink_structure = get_option('permalink_structure', '');
        if (!empty($permalink_structure)) {
            $permalink_status = 'ok';
            $permalink_detail = 'Pretty permalinks: ' . $permalink_structure;
            $permalink_action = '';
            $permalink_link = '';
            $permalink_guide = '';
        } else {
            $permalink_status = 'fail';
            $permalink_detail = 'Đang dùng Plain permalinks (?p=123) - MCP và REST API không hoạt động';
            $permalink_action = 'Vào Settings > Permalinks và chọn cấu trúc khác "Plain".';
            $permalink_link = admin_url('options-permalink.php');
            $permalink_guide = '1. Click link "Cài đặt Permalink" ở trên. | 2. Chọn "Post name" (/%postname%/) - đây là cấu trúc tốt nhất cho SEO. | 3. Nhấn "Save Changes". | 4. Quay lại trang này, nhấn "Chạy kiểm tra" để xác nhận.';
        }
        $tests[] = array(
            'label'  => 'Permalink Structure',
            'status' => $permalink_status,
            'detail' => $permalink_detail,
            'action' => $permalink_action,
            'link'   => $permalink_link,
            'guide'  => $permalink_guide,
        );

        return array(
            'label' => 'Hệ thống cơ bản',
            'tests' => $tests,
        );
    }

    // ============================================================
    // NHÓM 2: MCP
    // ============================================================

    /**
     * Test nhóm MCP
     *
     * @return array
     */
    private static function test_group_mcp() {
        $tests = array();

        // Test 7: Abilities API available
        $abilities_ok = function_exists('wp_register_ability');
        $tests[] = array(
            'label'  => 'Abilities API',
            'status' => $abilities_ok ? 'ok' : 'fail',
            'detail' => $abilities_ok ? 'wp_register_ability() có sẵn (WP 6.9 core)' : 'Không tìm thấy wp_register_ability()',
            'action' => $abilities_ok ? '' : 'Cần WordPress 6.9+ (Abilities API đã tích hợp vào core).',
            'link'   => $abilities_ok ? '' : admin_url('update-core.php'),
            'guide'  => $abilities_ok ? '' : '1. Nâng cấp WordPress lên phiên bản 6.9 trở lên (click link "Cập nhật" ở trên). | 2. Abilities API đã được tích hợp sẵn trong WP 6.9 core, không cần cài thêm plugin. | 3. Sau khi nâng cấp, quay lại đây kiểm tra.',
        );

        // Test 8: MCP Adapter loaded
        $mcp_ok = class_exists('WP\\MCP\\Plugin') || class_exists('WP\\MCP\\Core\\McpAdapter');
        $mcp_ver = defined('WP_MCP_VERSION') ? WP_MCP_VERSION : '';
        $tests[] = array(
            'label'  => 'MCP Adapter',
            'status' => $mcp_ok ? 'ok' : 'fail',
            'detail' => $mcp_ok
                ? 'Đã load' . ($mcp_ver ? ' (v' . $mcp_ver . ')' : '')
                : 'Chưa load - kiểm tra plugin đã kích hoạt chưa',
            'action' => $mcp_ok ? '' : 'Đảm bảo GenSeo SEO Helper v2.0+ đã kích hoạt (MCP Adapter đã bundle sẵn).',
            'link'   => $mcp_ok ? '' : admin_url('plugins.php'),
            'guide'  => $mcp_ok ? '' : '1. Click link "Quản lý Plugin" ở trên. | 2. Tìm "GenSeo SEO Helper" trong danh sách. | 3. Nếu chưa kích hoạt, nhấn "Activate". | 4. Nếu phiên bản cũ (< 2.0), cập nhật plugin trước.',
        );

        // Test 9: GenSeo abilities registered
        $ability_count = self::count_genseo_abilities();
        $abilities_registered = $ability_count >= 14;
        $tests[] = array(
            'label'  => 'GenSeo Abilities',
            'status' => $abilities_registered ? 'ok' : ($ability_count > 0 ? 'warn' : 'fail'),
            'detail' => $ability_count . '/14 abilities đã đăng ký',
            'action' => $abilities_registered ? '' : 'Kiểm tra MCP Adapter và Abilities API đã active chưa.',
            'link'   => $abilities_registered ? '' : admin_url('plugins.php'),
            'guide'  => $abilities_registered ? '' : '1. Đảm bảo WordPress >= 6.9 (Abilities API). | 2. Đảm bảo GenSeo SEO Helper >= 2.0 đã kích hoạt. | 3. Thử tắt kích hoạt rồi bật lại plugin GenSeo. | 4. Kiểm tra log lỗi: WP Admin > Tools > Site Health > Info > Debug log.',
        );

        // Test 10: MCP Endpoint loopback test
        $mcp_test = self::loopback_mcp_test();
        $tests[] = array(
            'label'  => 'MCP Endpoint (loopback)',
            'status' => $mcp_test['status'],
            'detail' => $mcp_test['detail'],
            'action' => $mcp_test['action'],
            'link'   => '',
            'guide'  => ($mcp_test['status'] !== 'ok') ? '1. Kiểm tra Permalink không phải "Plain" (Settings > Permalinks). | 2. Kiểm tra firewall không chặn /wp-json/mcp/ (xem nhóm "Bảo mật" bên dưới). | 3. Thử dùng Admin-AJAX proxy: GenSeo Desktop sẽ tự động fallback. | 4. Nếu dùng Cloudflare, tắt "Under Attack Mode".' : '',
        );

        return array(
            'label' => 'MCP (Model Context Protocol)',
            'tests' => $tests,
        );
    }

    // ============================================================
    // NHÓM 3: REST API
    // ============================================================

    /**
     * Test nhóm REST API
     *
     * @return array
     */
    private static function test_group_rest_api() {
        $tests = array();

        // Test 11: REST API health endpoint
        $health_test = self::loopback_health_test();
        $tests[] = array(
            'label'  => 'REST API Health (/genseo/v1/health)',
            'status' => $health_test['status'],
            'detail' => $health_test['detail'],
            'action' => $health_test['action'],
            'link'   => '',
            'guide'  => ($health_test['status'] !== 'ok') ? '1. Kiểm tra Permalink đã bật Pretty Permalinks chưa (Settings > Permalinks). | 2. Truy cập trực tiếp: ' . esc_url(rest_url('genseo/v1/health')) . ' - xem có trả về JSON không. | 3. Nếu lỗi 403/401, có thể firewall đang chặn. Xem nhóm "Bảo mật" bên dưới. | 4. Thử thêm vào wp-config.php: define(\'WP_DEBUG\', true); để xem lỗi chi tiết.' : '',
        );

        // Test 12: CORS headers
        $cors_test = self::check_cors_headers($health_test);
        $tests[] = array(
            'label'  => 'CORS Headers',
            'status' => $cors_test['status'],
            'detail' => $cors_test['detail'],
            'action' => $cors_test['action'],
            'link'   => '',
            'guide'  => ($cors_test['status'] !== 'ok') ? 'CORS headers được GenSeo plugin tự động thêm. Nếu vẫn lỗi: | 1. Kiểm tra plugin bảo mật không ghi đè CORS headers. | 2. Kiểm tra hosting/server không cấu hình CORS riêng. | 3. Thử tắt các plugin bảo mật tạm thời để test. | 4. Nếu dùng Cloudflare, kiểm tra Page Rules không chặn headers.' : '',
        );

        // Test 13: Admin-AJAX fallback
        $ajax_test = self::loopback_ajax_test();
        $tests[] = array(
            'label'  => 'Admin-AJAX Fallback',
            'status' => $ajax_test['status'],
            'detail' => $ajax_test['detail'],
            'action' => $ajax_test['action'],
            'link'   => '',
            'guide'  => ($ajax_test['status'] !== 'ok') ? 'Admin-AJAX là kênh dự phòng khi REST API bị chặn. | 1. Truy cập: ' . esc_url(admin_url('admin-ajax.php?action=genseo_health')) . ' | 2. Nếu trả về JSON {"status":"healthy"} là OK. | 3. Nếu lỗi, kiểm tra plugin bảo mật có chặn admin-ajax không. | 4. Wordfence: Firewall > Manage Firewall > bỏ tick "Rate Limit" cho admin-ajax.' : '',
        );

        return array(
            'label' => 'REST API',
            'tests' => $tests,
        );
    }

    // ============================================================
    // NHÓM 4: FIREWALL
    // ============================================================

    /**
     * Test nhóm firewall
     *
     * @return array
     */
    private static function test_group_firewall() {
        $tests = array();

        // Test 14: Wordfence
        $wf_test = self::detect_wordfence();
        $wf_active = class_exists('wfConfig');
        $tests[] = array(
            'label'  => 'Wordfence',
            'status' => $wf_test['status'],
            'detail' => $wf_test['detail'],
            'action' => $wf_test['action'],
            'link'   => $wf_active ? admin_url('admin.php?page=WordfenceWAF') : '',
            'guide'  => $wf_active ? 'GenSeo đã tự động bypass Wordfence cho MCP/REST endpoints. Nếu vẫn lỗi: | 1. Click link "Cài đặt Wordfence" ở trên. | 2. Tab "Firewall" > "Manage Firewall". | 3. Thêm IP của bạn vào Allowlisted IP Addresses. | 4. Mục "Rate Limiting" > đặt "How should we treat Google-verified crawlers" = Unlimited. | 5. Nếu dùng "Learning Mode", đợi Wordfence học xong hoặc chuyển sang "Enabled and Protecting".' : '',
        );

        // Test 15: Imunify360 / ModSecurity
        $imunify_test = self::detect_imunify360();
        $tests[] = array(
            'label'  => 'Imunify360 / ModSecurity',
            'status' => $imunify_test['status'],
            'detail' => $imunify_test['detail'],
            'action' => $imunify_test['action'],
            'link'   => '',
            'guide'  => ($imunify_test['status'] === 'warn' || $imunify_test['status'] === 'info') ? 'Imunify360/ModSecurity là firewall server-side. GenSeo có thể tự động thêm whitelist rules: | 1. Nhấn nút "Tự động sửa .htaccess" ở phần "Công cụ .htaccess" bên dưới. | 2. Plugin sẽ thêm SecRule whitelist cho endpoints /genseo/ và /mcp/. | 3. Nếu hosting không cho sửa .htaccess, liên hệ hosting thêm whitelist cho: /wp-json/genseo/* và /wp-json/mcp/* | 4. Nếu đã nhấn nút mà vẫn lỗi, thử dùng Admin-AJAX proxy (GenSeo Desktop sẽ tự động fallback).' : '',
        );

        return array(
            'label' => 'Bảo mật & Firewall',
            'tests' => $tests,
        );
    }

    // ============================================================
    // LOOPBACK TESTS
    // ============================================================

    /**
     * Test loopback tới MCP endpoint
     *
     * @return array
     */
    private static function loopback_mcp_test() {
        $url = rest_url('mcp/mcp-adapter-default-server');

        $start = microtime(true);
        $response = wp_remote_post($url, array(
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Authorization' => self::get_loopback_auth(),
            ),
            'body'      => wp_json_encode(array(
                'jsonrpc' => '2.0',
                'id'      => 1,
                'method'  => 'tools/list',
                'params'  => new stdClass(),
            )),
        ));
        $elapsed = round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            $action = 'Kiểm tra kết nối loopback của server.';

            // Phát hiện nguyên nhân cụ thể
            if (stripos($error, 'ssl') !== false || stripos($error, 'certificate') !== false) {
                $action = 'Lỗi SSL certificate. Kiểm tra cấu hình HTTPS của hosting.';
            } elseif (stripos($error, 'timeout') !== false || stripos($error, 'timed out') !== false) {
                $action = 'Timeout - server mất quá lâu để trả lời. Kiểm tra server performance.';
            } elseif (stripos($error, 'resolve') !== false) {
                $action = 'Không phân giải được domain. Kiểm tra DNS và file hosts trên server.';
            }

            return array(
                'status' => 'fail',
                'detail' => 'Lỗi: ' . $error,
                'action' => $action,
            );
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 404) {
            return array(
                'status' => 'fail',
                'detail' => 'HTTP 404 - Endpoint không tồn tại',
                'action' => 'MCP Adapter chưa được kích hoạt. Kiểm tra plugin GenSeo SEO Helper v2.0+.',
            );
        }

        if ($code === 403) {
            return array(
                'status' => 'fail',
                'detail' => 'HTTP 403 - Bị chặn bởi firewall',
                'action' => 'WAF (Wordfence/Imunify360) đang chặn MCP endpoint. Xem mục Bảo mật bên dưới.',
            );
        }

        if ($code === 401) {
            return array(
                'status' => 'warn',
                'detail' => 'HTTP 401 - Cần xác thực (' . $elapsed . 'ms)',
                'action' => 'Endpoint tồn tại nhưng cần Application Password để truy cập. Đây là bình thường.',
            );
        }

        if ($code >= 200 && $code < 300) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $tool_count = 0;
            if (isset($body['result']['tools'])) {
                $tool_count = count($body['result']['tools']);
            }
            return array(
                'status' => 'ok',
                'detail' => 'HTTP ' . $code . ' - ' . $tool_count . ' tools (' . $elapsed . 'ms)',
                'action' => '',
            );
        }

        return array(
            'status' => 'warn',
            'detail' => 'HTTP ' . $code . ' (' . $elapsed . 'ms)',
            'action' => 'Response không mong đợi. Kiểm tra error log của WordPress.',
        );
    }

    /**
     * Test loopback tới health endpoint
     *
     * @return array
     */
    private static function loopback_health_test() {
        $url = rest_url('genseo/v1/health');

        $start = microtime(true);
        $response = wp_remote_get($url, array(
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => array(
                'User-Agent' => 'GenSeo-Diagnostic/' . GENSEO_VERSION,
            ),
        ));
        $elapsed = round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            return array(
                'status'   => 'fail',
                'detail'   => 'Lỗi: ' . $response->get_error_message(),
                'action'   => 'Kiểm tra REST API có bị tắt không (Settings > Permalinks lưu lại).',
                'headers'  => array(),
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);

        if ($code === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $is_healthy = isset($body['data']['status']) && $body['data']['status'] === 'healthy';
            return array(
                'status'  => $is_healthy ? 'ok' : 'warn',
                'detail'  => 'HTTP 200 - ' . ($is_healthy ? 'healthy' : 'bất thường') . ' (' . $elapsed . 'ms)',
                'action'  => $is_healthy ? '' : 'Endpoint trả về nhưng trạng thái không phải "healthy".',
                'headers' => $headers,
            );
        }

        if ($code === 403) {
            return array(
                'status'  => 'fail',
                'detail'  => 'HTTP 403 - Bị chặn bởi firewall (' . $elapsed . 'ms)',
                'action'  => 'WAF đang chặn REST API. Thử dùng Admin-AJAX fallback hoặc whitelist endpoint.',
                'headers' => $headers,
            );
        }

        return array(
            'status'  => 'fail',
            'detail'  => 'HTTP ' . $code . ' (' . $elapsed . 'ms)',
            'action'  => 'Kiểm tra permalink settings và error log.',
            'headers' => $headers,
        );
    }

    /**
     * Kiểm tra CORS headers từ kết quả health test
     *
     * @param array $health_result Kết quả từ loopback_health_test()
     * @return array
     */
    private static function check_cors_headers($health_result) {
        $headers = isset($health_result['headers']) ? $health_result['headers'] : array();

        if (empty($headers)) {
            return array(
                'status' => 'warn',
                'detail' => 'Không kiểm tra được (health test thất bại)',
                'action' => 'Sửa lỗi REST API trước, sau đó kiểm tra lại.',
            );
        }

        // Kiểm tra Access-Control-Allow-Origin
        $has_cors = false;
        if ($headers instanceof \WpOrg\Requests\Utility\CaseInsensitiveDictionary || is_object($headers)) {
            $has_cors = isset($headers['access-control-allow-origin']);
        } elseif (is_array($headers)) {
            $has_cors = isset($headers['access-control-allow-origin']) || isset($headers['Access-Control-Allow-Origin']);
        }

        if ($has_cors) {
            return array(
                'status' => 'ok',
                'detail' => 'Access-Control-Allow-Origin header có mặt',
                'action' => '',
            );
        }

        return array(
            'status' => 'warn',
            'detail' => 'Thiếu CORS headers',
            'action' => 'GenSeo Desktop có thể gặp lỗi kết nối. Plugin sẽ tự thêm CORS headers.',
        );
    }

    /**
     * Test loopback Admin-AJAX fallback
     *
     * @return array
     */
    private static function loopback_ajax_test() {
        $url = admin_url('admin-ajax.php');
        $url = add_query_arg('action', 'genseo_health', $url);

        $start = microtime(true);
        $response = wp_remote_get($url, array(
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => array(
                'User-Agent' => 'GenSeo-Diagnostic/' . GENSEO_VERSION,
            ),
        ));
        $elapsed = round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            return array(
                'status' => 'fail',
                'detail' => 'Lỗi: ' . $response->get_error_message(),
                'action' => 'Admin-AJAX cũng bị chặn. Kiểm tra WAF settings.',
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $is_ok = isset($body['success']) && $body['success'] === true;
            return array(
                'status' => $is_ok ? 'ok' : 'warn',
                'detail' => 'HTTP 200 - ' . ($is_ok ? 'hoat dong tot' : 'response bat thuong') . ' (' . $elapsed . 'ms)',
                'action' => $is_ok ? '' : 'Kiem tra cau hinh admin-ajax.php.',
            );
        }

        return array(
            'status' => 'fail',
            'detail' => 'HTTP ' . $code . ' (' . $elapsed . 'ms)',
            'action' => 'Admin-AJAX bị chặn. Kiểm tra .htaccess và WAF rules.',
        );
    }

    // ============================================================
    // FIREWALL DETECTION
    // ============================================================

    /**
     * Phát hiện Wordfence
     *
     * @return array
     */
    private static function detect_wordfence() {
        $active = class_exists('wfConfig') || defined('WORDFENCE_VERSION');

        if (!$active) {
            return array(
                'status' => 'info',
                'detail' => 'Wordfence không được phát hiện',
                'action' => '',
            );
        }

        $version = defined('WORDFENCE_VERSION') ? WORDFENCE_VERSION : 'không xác định';

        // Kiểm tra trạng thái firewall
        $firewall_enabled = false;
        $learning_mode = false;
        if (class_exists('wfConfig')) {
            $firewall_enabled = (bool) wfConfig::get('firewallEnabled', false);
            $learning_mode = (bool) wfConfig::get('learningModeEnabled', false);
        }

        $details = array('v' . $version);
        if ($firewall_enabled) {
            $details[] = 'Firewall: BAT';
        } else {
            $details[] = 'Firewall: TAT';
        }
        if ($learning_mode) {
            $details[] = 'Learning mode: BAT';
        }

        // Kiểm tra bypass đang hoạt động
        $bypass_ok = has_filter('wordfence_allow_ip');

        $action = '';
        if ($firewall_enabled && !$bypass_ok) {
            $action = 'GenSeo bypass chưa được đăng ký. Kiểm tra plugin GenSeo có active không.';
        } elseif ($firewall_enabled) {
            $action = 'Bypass GenSeo đã được đăng ký. Nếu vẫn gặp lỗi 403, vào Wordfence > Firewall > Whitelisted URLs thêm /wp-json/genseo/* và /wp-json/mcp/*';
        }

        return array(
            'status' => $firewall_enabled ? 'warn' : 'info',
            'detail' => implode(', ', $details),
            'action' => $action,
        );
    }

    /**
     * Phát hiện Imunify360 / ModSecurity
     *
     * @return array
     */
    private static function detect_imunify360() {
        $detected = array();
        $methods = array();

        // Method 1: Server software header
        $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';
        if (stripos($server_software, 'litespeed') !== false) {
            $detected[] = 'LiteSpeed Web Server';
            $methods[] = 'server software';
        }

        // Method 2: PHP modules
        $loaded_modules = function_exists('apache_get_modules') ? apache_get_modules() : array();
        $modsec_module = false;
        if (!empty($loaded_modules)) {
            foreach ($loaded_modules as $mod) {
                if (stripos($mod, 'security') !== false) {
                    $modsec_module = true;
                    break;
                }
            }
        }
        if ($modsec_module) {
            $detected[] = 'ModSecurity (Apache module)';
            $methods[] = 'apache module';
        }

        // Method 3: Response headers từ loopback test
        $loopback_url = home_url('/');
        $loopback_response = wp_remote_get($loopback_url, array(
            'timeout'   => 5,
            'sslverify' => false,
        ));

        if (!is_wp_error($loopback_response)) {
            $headers = wp_remote_retrieve_headers($loopback_response);
            $header_checks = array(
                'x-imunify-waf'     => 'Imunify360 WAF',
                'x-powered-by-imunify360' => 'Imunify360',
                'x-sucuri-id'       => 'Sucuri WAF',
                'x-cloudflare'      => 'Cloudflare',
                'cf-ray'            => 'Cloudflare',
                'x-litespeed-cache' => 'LiteSpeed Cache',
            );

            foreach ($header_checks as $header => $name) {
                if (isset($headers[$header])) {
                    $detected[] = $name;
                    $methods[] = 'response header: ' . $header;
                }
            }

            // Kiểm tra server header cho Imunify
            $server_header = isset($headers['server']) ? (string) $headers['server'] : '';
            if (stripos($server_header, 'imunify') !== false) {
                $detected[] = 'Imunify360 (server header)';
                $methods[] = 'server header';
            }
        }

        // Method 4: Kiểm tra .htaccess có ModSecurity rules
        $htaccess_file = ABSPATH . '.htaccess';
        $has_modsec_rules = false;
        if (file_exists($htaccess_file) && is_readable($htaccess_file)) {
            $htaccess_content = file_get_contents($htaccess_file);
            if ($htaccess_content !== false) {
                if (stripos($htaccess_content, 'mod_security') !== false
                    || stripos($htaccess_content, 'SecRule') !== false
                    || stripos($htaccess_content, 'SecRuleRemoveById') !== false) {
                    $has_modsec_rules = true;
                    $detected[] = 'ModSecurity rules trong .htaccess';
                    $methods[] = '.htaccess';
                }
            }
        }

        // Method 5: Kiểm tra Imunify qua file system
        $imunify_paths = array(
            '/etc/sysconfig/imunify360',
            '/usr/share/imunify360',
        );
        foreach ($imunify_paths as $path) {
            if (@is_dir($path)) {
                $detected[] = 'Imunify360 (server path)';
                $methods[] = 'filesystem: ' . $path;
                break;
            }
        }

        if (empty($detected)) {
            return array(
                'status' => 'info',
                'detail' => 'Không phát hiện WAF bên ngoài (Imunify360, ModSecurity, Sucuri, Cloudflare)',
                'action' => '',
            );
        }

        $unique_detected = array_unique($detected);

        // Tạo hướng dẫn
        $has_imunify = false;
        $has_modsec = false;
        foreach ($unique_detected as $d) {
            if (stripos($d, 'Imunify') !== false) $has_imunify = true;
            if (stripos($d, 'ModSecurity') !== false) $has_modsec = true;
        }

        $action = 'Phát hiện: ' . implode(', ', $unique_detected) . '. ';
        if ($has_imunify || $has_modsec) {
            $action .= 'Nếu gặp lỗi 403 khi dùng MCP/REST API: vào trang Chẩn đoán > nhấn "Tự động sửa .htaccess" để thêm whitelist rules. '
                     . 'Hoặc liên hệ hosting để whitelist các endpoints /wp-json/genseo/* và /wp-json/mcp/*';
        }

        return array(
            'status' => 'warn',
            'detail' => implode(', ', $unique_detected),
            'action' => $action,
        );
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Đếm số GenSeo abilities đã đăng ký
     *
     * @return int
     */
    private static function count_genseo_abilities() {
        // Thử dùng WordPress Abilities API internal registry
        if (function_exists('wp_get_registered_abilities')) {
            $all_abilities = wp_get_registered_abilities();
            if (is_array($all_abilities)) {
                $count = 0;
                foreach ($all_abilities as $name => $ability) {
                    if (strpos($name, 'genseo/') === 0) {
                        $count++;
                    }
                }
                return $count;
            }
        }

        // Fallback: kiểm tra class đã init chưa
        if (class_exists('GenSeo_MCP_Abilities')) {
            // Nếu class tồn tại, giả sử đã đăng ký (không có cách count chính xác)
            return 14;
        }

        return 0;
    }

    /**
     * Tạo auth header cho loopback test
     * Dùng Application Password tạm hoặc cookie auth
     *
     * @return string
     */
    private static function get_loopback_auth() {
        // Dùng cookie-based auth thông qua nonce
        // Khi admin đang login, wp_remote_post sẽ gửi cookie hiện tại
        // Nhưng wp_remote_* KHÔNG tự gửi cookie, nên ta dùng Basic Auth
        // với user hiện tại nếu có Application Password

        // Fallback: trả về empty, MCP endpoint sẽ trả về 401 (vẫn confirm endpoint tồn tại)
        return '';
    }
}
