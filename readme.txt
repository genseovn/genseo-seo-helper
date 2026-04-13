=== GenSeo SEO Helper ===
Contributors: genseoteam
Tags: seo, opengraph, schema, rankmath, yoast
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tối ưu SEO cho bài viết từ GenSeo Desktop - OpenGraph, Schema markup, tích hợp RankMath/Yoast, MCP Abilities.

== Description ==

**GenSeo SEO Helper** là plugin WordPress hỗ trợ tối ưu SEO cho các bài viết được đăng từ ứng dụng GenSeo Desktop.

= Tính năng chính =

* **OpenGraph Meta Tags** - Tự động output og:title, og:description, og:image cho bài viết
* **Twitter Cards** - Output twitter:card, twitter:title, twitter:image
* **Schema.org JSON-LD** - Generate structured data Article, HowTo, FAQ
* **RankMath Integration** - Tự động sync SEO data sang Rank Math SEO
* **Yoast SEO Integration** - Tự động sync SEO data sang Yoast SEO
* **REST API Endpoints** - Cung cấp endpoints cho GenSeo Desktop kết nối
* **MCP Abilities** - 13 abilities cho GenSeo Desktop tương tác trực tiếp với WordPress qua MCP (tích hợp sẵn MCP Adapter)
* **Auto-Update** - Tự động kiểm tra và thông báo phiên bản mới từ plugin.genseo.vn
* **Diagnostic Page** - Trang chẩn đoán kiểm tra 15 hạng mục: hệ thống, MCP, REST API, firewall
* **WAF Compatibility** - Tương thích Wordfence, Imunify360, ModSecurity với auto-fix .htaccess

= Cách hoạt động =

1. Cài đặt plugin trên WordPress
2. Cấu hình GenSeo Desktop kết nối với WordPress
3. Khi publish bài từ Desktop, SEO data được gửi qua REST API
4. Plugin output OpenGraph, Twitter Cards, Schema.org
5. Nếu có RankMath/Yoast, data được sync tự động

= REST API Endpoints =

* `GET /wp-json/genseo/v1/health` - Kiểm tra plugin status
* `GET /wp-json/genseo/v1/info` - Lấy thông tin site
* `GET /wp-json/genseo/v1/posts` - Danh sách bài viết
* `POST /wp-admin/admin-ajax.php?action=genseo_health` - Health check fallback (bypass firewall)

= MCP Abilities (tích hợp sẵn từ v2.0.0) =

* `genseo/get-seo-meta` - Lấy SEO meta bài viết
* `genseo/update-seo-meta` - Cập nhật SEO meta (title, desc, keyword, schema, OG)
* `genseo/bulk-update-seo` - Cập nhật SEO hàng loạt
* `genseo/get-posts-needing-optimization` - Lấy bài cần tối ưu
* `genseo/update-internal-links` - Chèn internal links vào nội dung
* `genseo/get-post-content-summary` - Lấy tóm tắt nội dung
* `genseo/get-post-field` - Lấy field bài viết
* `genseo/get-posts` - Danh sách bài viết qua MCP
* `genseo/get-post` - Chi tiết bài viết qua MCP
* `genseo/update-post` - Cập nhật bài viết qua MCP
* `genseo/get-post-meta` - Lấy post meta
* `genseo/update-post-meta` - Cập nhật post meta
* `genseo/get-site-info` - Thông tin site WordPress

== Installation ==

= Cài đặt tự động =
1. Vào Plugins → Add New trong WordPress Admin
2. Tìm "GenSeo SEO Helper"
3. Click "Install Now" và sau đó "Activate"

= Cài đặt thủ công =
1. Download file plugin (.zip)
2. Vào Plugins → Add New → Upload Plugin
3. Chọn file và click "Install Now"
4. Activate plugin

= Cấu hình =
1. Vào Settings → GenSeo SEO Helper
2. Cấu hình các tùy chọn theo nhu cầu
3. Đảm bảo đã bật OpenGraph, Schema nếu cần

== Frequently Asked Questions ==

= Plugin có xung đột với RankMath/Yoast không? =

Không. Plugin được thiết kế để hoạt động cùng với RankMath và Yoast. Khi detect được SEO plugin, GenSeo sẽ sync data để SEO plugin output thay vì tự output.

= Tại sao cần plugin này? =

Khi publish bài từ GenSeo Desktop, bạn muốn SEO meta (title, description, keywords) được lưu vào WordPress. WordPress core không hỗ trợ custom meta qua REST API mặc định. Plugin này register các meta fields và xử lý output SEO tags.

= Plugin có làm chậm website không? =

Không. Plugin chỉ chạy cho bài viết từ GenSeo Desktop (có meta `_genseo_source`). Các bài viết khác không bị ảnh hưởng.

= Làm sao biết plugin hoạt động đúng? =

Kiểm tra endpoint: `https://yoursite.com/wp-json/genseo/v1/health`
Nếu trả về `"status": "healthy"` là OK.

== Screenshots ==

1. Trang Settings của plugin
2. Meta box trong Post Editor
3. Output OpenGraph meta tags

== Changelog ==

= 2.2.0 =
* Đồng bộ thêm Twitter Card, Pillar Content cho RankMath
* Đồng bộ thêm Twitter title/description, reading time cho Yoast SEO
* Sửa lỗi API Key proxy không gửi slug khi tạo bài mới

= 2.0.3 =
* Them debug info vao response 403 khi API Key khong hop le (hien thi prefix/length de chan doan)

= 2.0.2 =
* Them get_media action_type cho API proxy - lay thong tin media attachment theo ID

= 2.0.1 =
* MCP proxy ho tro xac thuc bang API Key (khong can Application Password)
* Doi get_key_owner_user_id() sang public de dung tu WAF compat layer

= 2.0.0 =
* YÊU CẦU WordPress 6.9+ (Abilities API tích hợp trong core)
* Tích hợp MCP Adapter vào plugin - không cần cài riêng nữa (1 plugin thay vì 3)
* Thêm Auto-Update Checker - kiểm tra phiên bản mới từ plugin.genseo.vn mỗi 12 giờ
* Thêm trang Chẩn đoán (Settings > GenSeo Chẩn đoán) - 15 bài test chia 4 nhóm
* Thêm WAF Compatibility layer - tự động detect và bypass Wordfence, Imunify360, ModSecurity
* Tự động thêm CORS headers cho cả /genseo/v1/ và /mcp/ endpoints
* Admin-AJAX MCP proxy fallback khi REST API bị chặn
* Nút tự động sửa .htaccess với ModSecurity/LiteSpeed whitelist rules + rollback
* Smart admin notice khi phát hiện WAF blocking (dismiss per-user, cache 24h)
* Nút kiểm tra cập nhật ngay trong Dashboard
* Sao chép kết quả chẩn đoán dạng text để gửi support

= 1.2.0 =
* Thêm 13 MCP Abilities cho GenSeo Desktop tương tác trực tiếp với WordPress qua MCP Adapter
* SEO Abilities: get/update seo meta, bulk update, get posts needing optimization
* Content Abilities: update internal links, get post content summary
* CRUD Abilities: get/update posts, get/update post meta, get site info
* Tự động sync SEO meta sang Yoast SEO và RankMath khi update qua MCP
* Hỗ trợ schema JSON validation
* Internal links chèn theo vị trí paragraph chỉ định
* Optional dependency: chỉ kích hoạt MCP abilities khi MCP Adapter plugin active

= 1.1.0 =
* Thêm admin-ajax fallback cho health check - bypass Wordfence/Sucuri chặn REST API
* Tự động whitelist GenSeo REST endpoints trong Wordfence
* Thêm CORS headers cho genseo/v1 namespace
* Bypass Wordfence rate-limit cho request có User-Agent GenSeo
* Bypass Wordfence CAPTCHA cho GenSeo REST endpoints
* Tương thích WordPress 6.7

= 1.0.0 =
* Initial release
* OpenGraph meta tags output
* Twitter Cards output
* Schema.org Article JSON-LD
* RankMath integration
* Yoast SEO integration
* REST API endpoints (/info, /health, /posts)
* Admin settings page
* Post meta box hiển thị SEO info

== Upgrade Notice ==

= 2.0.0 =
Phiên bản lớn! Tích hợp MCP Adapter, thêm auto-update, diagnostic page, WAF compatibility. YÊU CẦU WordPress 6.9+. Backup trước khi cập nhật.

= 1.2.0 =
Thêm 13 MCP Abilities cho GenSeo Desktop. Yêu cầu cài MCP Adapter plugin để sử dụng.

= 1.1.0 =
Cải thiện tương thích với Wordfence và các firewall plugin. Khuyến khích cập nhật.

= 1.0.0 =
Phiên bản đầu tiên. Vui lòng backup trước khi cài đặt.
