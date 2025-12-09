<?php

/**
 * توابع کمکی افزونه
 * 
 * @package TelegramSender
 * @author اصغر معینی <as.moini@gmail.com>
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

/**
 * گرفتن تنظیمات افزونه
 */
function telegram_sender_get_option($option_name, $default = '')
{
    return get_option('telegram_sender_' . $option_name, $default);
}

/**
 * ذخیره تنظیمات افزونه
 */
function telegram_sender_update_option($option_name, $value)
{
    return update_option('telegram_sender_' . $option_name, $value);
}

/**
 * بررسی فعال بودن ووکامرس
 */
function telegram_sender_is_woocommerce_active()
{
    return class_exists('WooCommerce');
}

/**
 * فرمت کردن قیمت
 */
function telegram_sender_format_price($price)
{
    if (telegram_sender_is_woocommerce_active()) {
        return wc_price($price);
    }

    return number_format($price) . ' تومان';
}

/**
 * پاک‌سازی متن برای ارسال به تلگرام
 */
function telegram_sender_clean_text($text)
{
    // حذف تگ‌های HTML
    $text = wp_strip_all_tags($text);

    // تبدیل entities
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // حذف فضاهای اضافی
    $text = preg_replace('/\s+/', ' ', $text);

    // تریم
    $text = trim($text);

    return $text;
}

/**
 * escape کردن متن برای HTML تلگرام
 */
function telegram_sender_escape_html($text)
{
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return $text;
}

/**
 * محدود کردن طول متن
 */
function telegram_sender_limit_text($text, $limit = 200, $suffix = '...')
{
    $text = telegram_sender_clean_text($text);

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return mb_substr($text, 0, $limit) . $suffix;
}

/**
 * اعتبارسنجی توکن تلگرام
 */
function telegram_sender_validate_token($token)
{
    if (empty($token)) {
        return false;
    }

    // فرمت توکن: BOT_ID:BOT_TOKEN
    if (!preg_match('/^\d+:[A-Za-z0-9_-]{35}$/', $token)) {
        return false;
    }

    return true;
}

/**
 * اعتبارسنجی چت آیدی
 */
function telegram_sender_validate_chat_id($chat_id)
{
    $chat_id = trim($chat_id);

    if (empty($chat_id)) {
        return false;
    }

    // چت آیدی عددی (مثبت یا منفی)
    if (preg_match('/^-?\d+$/', $chat_id)) {
        return true;
    }

    // یوزرنیم کانال یا گروه
    if (preg_match('/^@[a-zA-Z0-9_]{5,}$/', $chat_id)) {
        return true;
    }

    return false;
}

/**
 * تبدیل آرایه چت آیدی‌ها به رشته
 */
function telegram_sender_chat_ids_to_string($chat_ids)
{
    if (is_array($chat_ids)) {
        return implode("\n", array_filter($chat_ids));
    }

    return $chat_ids;
}

/**
 * تبدیل رشته چت آیدی‌ها به آرایه
 */
function telegram_sender_string_to_chat_ids($string)
{
    if (is_array($string)) {
        return $string;
    }

    $chat_ids = explode("\n", $string);
    $chat_ids = array_map('trim', $chat_ids);
    $chat_ids = array_filter($chat_ids);

    return $chat_ids;
}

/**
 * گرفتن URL تصویر محصول
 */
function telegram_sender_get_product_image($product_id)
{
    if (!telegram_sender_is_woocommerce_active()) {
        return '';
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return '';
    }

    $image_id = $product->get_image_id();
    if (!$image_id) {
        return '';
    }

    return wp_get_attachment_url($image_id);
}

/**
 * گرفتن URL تصویر شاخص نوشته
 */
function telegram_sender_get_post_image($post_id)
{
    $image_url = get_the_post_thumbnail_url($post_id, 'large');
    return $image_url ? $image_url : '';
}

/**
 * فرمت کردن تاریخ شمسی
 */
function telegram_sender_format_persian_date($timestamp = null)
{
    if (!$timestamp) {
        $timestamp = current_time('timestamp');
    }

    // اگر کتابخانه تاریخ شمسی موجود باشد
    if (function_exists('parsidate')) {
        return parsidate('Y/m/d H:i', $timestamp);
    }

    // در غیر این صورت تاریخ میلادی
    return date('Y/m/d H:i', $timestamp);
}

/**
 * نمایش پیام موفقیت
 */
function telegram_sender_admin_notice_success($message)
{
    add_action('admin_notices', function () use ($message) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    });
}

/**
 * نمایش پیام خطا
 */
function telegram_sender_admin_notice_error($message)
{
    add_action('admin_notices', function () use ($message) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
    });
}

/**
 * نمایش پیام اطلاع‌رسانی
 */
function telegram_sender_admin_notice_info($message)
{
    add_action('admin_notices', function () use ($message) {
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
    });
}

/**
 * بررسی مجوزهای کاربر
 */
function telegram_sender_current_user_can_manage()
{
    return current_user_can('manage_options');
}

/**
 * لاگ کردن اطلاعات
 */
function telegram_sender_log($message, $level = 'info')
{
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[TelegramSender] ' . $level . ': ' . $message);
    }
}

/**
 * گرفتن آیکون وضعیت موجودی
 */
function telegram_sender_get_stock_status_icon($status)
{
    $icons = array(
        'instock' => '✅',
        'outofstock' => '❌',
        'onbackorder' => '⏳'
    );

    return isset($icons[$status]) ? $icons[$status] : '❓';
}

/**
 * گرفتن متن وضعیت موجودی
 */
function telegram_sender_get_stock_status_text($status)
{
    $texts = array(
        'instock' => 'موجود',
        'outofstock' => 'ناموجود',
        'onbackorder' => 'پیش‌سفارش'
    );

    return isset($texts[$status]) ? $texts[$status] : 'نامشخص';
}

/**
 * تولید nonce
 */
function telegram_sender_create_nonce($action = 'telegram_sender_nonce')
{
    return wp_create_nonce($action);
}

/**
 * بررسی nonce
 */
function telegram_sender_verify_nonce($nonce, $action = 'telegram_sender_nonce')
{
    return wp_verify_nonce($nonce, $action);
}

/**
 * گرفتن لیست نوع‌های پست قابل ارسال
 */
function telegram_sender_get_sendable_post_types()
{
    $post_types = get_post_types(array('public' => true), 'objects');

    $sendable_types = array();

    foreach ($post_types as $post_type) {
        // فقط پست‌ها و صفحات و نوع‌های سفارشی
        if (in_array($post_type->name, array('post', 'page')) || $post_type->_builtin === false) {
            $sendable_types[$post_type->name] = $post_type->label;
        }
    }

    return $sendable_types;
}

/**
 * گرفتن تعداد محصولات منتشر شده
 */
function telegram_sender_get_published_products_count()
{
    if (!telegram_sender_is_woocommerce_active()) {
        return 0;
    }

    $products = wc_get_products(array(
        'status' => 'publish',
        'limit' => -1,
        'return' => 'ids'
    ));

    return count($products);
}

/**
 * گرفتن تعداد نوشته‌های منتشر شده
 */
function telegram_sender_get_published_posts_count($post_type = 'post')
{
    $posts = get_posts(array(
        'post_status' => 'publish',
        'post_type' => $post_type,
        'numberposts' => -1,
        'fields' => 'ids'
    ));

    return count($posts);
}

/**
 * فرمت کردن حجم فایل
 */
function telegram_sender_format_bytes($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * بررسی حد مجاز اندازه پیام تلگرام
 */
function telegram_sender_check_message_length($message)
{
    $max_length = 4096; // حد مجاز تلگرام

    if (mb_strlen($message) > $max_length) {
        return array(
            'valid' => false,
            'length' => mb_strlen($message),
            'max_length' => $max_length,
            'excess' => mb_strlen($message) - $max_length
        );
    }

    return array(
        'valid' => true,
        'length' => mb_strlen($message),
        'max_length' => $max_length,
        'remaining' => $max_length - mb_strlen($message)
    );
}

/**
 * کوتاه کردن پیام طولانی
 */
function telegram_sender_truncate_message($message, $max_length = 4000)
{
    if (mb_strlen($message) <= $max_length) {
        return $message;
    }

    $truncated = mb_substr($message, 0, $max_length - 20);
    $truncated .= "\n\n... [ادامه در سایت]";

    return $truncated;
}

/**
 * تبدیل HTML به متن ساده با حفظ فرمت
 */
function telegram_sender_html_to_text($html)
{
    // تبدیل <br> و <p> به خط جدید
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
$html = preg_replace('/<\/p>/i', "\n\n", $html);

    // حذف تگ‌های HTML
    $text = wp_strip_all_tags($html);

    // تبدیل entities
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

    // حذف خطوط خالی اضافی
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return trim($text);
    }

    /**
    * ایجاد لینک ادمین
    */
    function telegram_sender_admin_url($page, $params = array())
    {
    $url = admin_url('admin.php?page=' . $page);

    if (!empty($params)) {
    $url = add_query_arg($params, $url);
    }

    return $url;
    }

    /**
    * بررسی اتصال اینترنت
    */
    function telegram_sender_check_internet_connection()
    {
    $response = wp_remote_get('https://api.telegram.org', array(
    'timeout' => 10,
    'sslverify' => false
    ));

    return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
    * گرفتن اطلاعات سرور
    */
    function telegram_sender_get_server_info()
    {
    return array(
    'php_version' => PHP_VERSION,
    'wordpress_version' => get_bloginfo('version'),
    'woocommerce_version' => telegram_sender_is_woocommerce_active() ? WC()->version : 'غیرفعال',
    'curl_enabled' => function_exists('curl_version'),
    'openssl_enabled' => extension_loaded('openssl'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize')
    );
    }

    /**
    * دریافت IP سرور
    */
    function telegram_sender_get_server_ip()
    {
    $ip_services = array(
    'https://api.ipify.org',
    'https://icanhazip.com',
    'https://ipecho.net/plain'
    );

    foreach ($ip_services as $service) {
    $response = wp_remote_get($service, array('timeout' => 5));

    if (!is_wp_error($response)) {
    $ip = trim(wp_remote_retrieve_body($response));
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
    return $ip;
    }
    }
    }

    return 'نامشخص';
    }