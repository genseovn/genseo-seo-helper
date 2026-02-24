=== GenSeo SEO Helper ===
Contributors: genseoteam
Tags: seo, opengraph, schema, rankmath, yoast
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tối ưu SEO cho bài viết từ GenSeo Desktop - OpenGraph, Schema markup, tích hợp RankMath/Yoast.

== Description ==

**GenSeo SEO Helper** là plugin WordPress hỗ trợ tối ưu SEO cho các bài viết được đăng từ ứng dụng GenSeo Desktop.

= Tính năng chính =

* **OpenGraph Meta Tags** - Tự động output og:title, og:description, og:image cho bài viết
* **Twitter Cards** - Output twitter:card, twitter:title, twitter:image
* **Schema.org JSON-LD** - Generate structured data Article, HowTo, FAQ
* **RankMath Integration** - Tự động sync SEO data sang Rank Math SEO
* **Yoast SEO Integration** - Tự động sync SEO data sang Yoast SEO
* **REST API Endpoints** - Cung cấp endpoints cho GenSeo Desktop kết nối

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

= 1.1.0 =
Cải thiện tương thích với Wordfence và các firewall plugin. Khuyến khích cập nhật.

= 1.0.0 =
Phiên bản đầu tiên. Vui lòng backup trước khi cài đặt.
