<?php

/**
 * Plugin Name: ارسال محتوا به تلگرام
 * Plugin URI: https://asgharmoeini.ir
 * Description: افزونه ارسال محصولات ووکامرس و مطالب وردپرس به تلگرام
 * Version: 1.0.0
 * Author: اصغر معینی
 * Author Email: as.moini@gmail.com
 * Text Domain: telegram-sender
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}
// بارگذاری فیلدهای محصول

// تعریف ثوابت افزونه
define('TELEGRAM_SENDER_VERSION', '1.0.0');
define('TELEGRAM_SENDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TELEGRAM_SENDER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TELEGRAM_SENDER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * کلاس اصلی افزونه
 */
class TelegramSender
{

    /**
     * Instance یکتای کلاس
     */
    private static $instance = null;

    /**
     * گرفتن instance یکتا
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * سازنده کلاس
     */
    private function __construct()
    {
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * اولیه‌سازی هوک‌ها
     */
    private function init_hooks()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_telegram_send_product', array($this, 'ajax_send_product'));
        add_action('wp_ajax_telegram_send_all_products', array($this, 'ajax_send_all_products'));
        add_action('wp_ajax_telegram_send_post', array($this, 'ajax_send_post'));
        add_action('wp_ajax_telegram_send_all_posts', array($this, 'ajax_send_all_posts'));
        add_action('wp_ajax_telegram_update_product', array($this, 'ajax_update_product'));
        add_action('wp_ajax_telegram_sync_products', array($this, 'ajax_sync_products'));
        add_action('telegram_sender_scheduled_send', array($this, 'scheduled_send_callback'));
        add_action('wp_ajax_telegram_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_telegram_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_telegram_refresh_logs', array($this, 'ajax_refresh_logs'));
    }

    /**
     * بارگذاری وابستگی‌ها
     */
    private function load_dependencies()
    {
        require_once TELEGRAM_SENDER_PLUGIN_DIR . 'includes/class-telegram-api.php';
        require_once TELEGRAM_SENDER_PLUGIN_DIR . 'includes/class-database.php';
        require_once TELEGRAM_SENDER_PLUGIN_DIR . 'includes/functions.php';
        require_once TELEGRAM_SENDER_PLUGIN_DIR . 'product-fields.php';
    }

    /**
     * فعال‌سازی افزونه
     */
    public function activate()
    {
        TelegramSender_Database::create_tables();

        // افزودن تنظیمات پیش‌فرض
        add_option('telegram_sender_bot_token', '');
        add_option('telegram_sender_chat_ids', array());
        add_option('telegram_sender_send_interval', 5);

        // تنظیم قوانین URL rewrite
        flush_rewrite_rules();
    }

    /**
     * غیرفعال‌سازی افزونه
     */
    public function deactivate()
    {
        // حذف cron jobs
        wp_clear_scheduled_hook('telegram_sender_scheduled_send');
        flush_rewrite_rules();
    }

    /**
     * اولیه‌سازی افزونه
     */
    public function init()
    {
        // بارگذاری فایل‌های ترجمه
        load_plugin_textdomain('telegram-sender', false, dirname(TELEGRAM_SENDER_PLUGIN_BASENAME) . '/languages/');

        // بررسی وجود ووکامرس
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
        }
    }

    /**
     * اولیه‌سازی بخش مدیریت
     */
    public function admin_init()
    {
        // ثبت تنظیمات
        register_setting('telegram_sender_settings', 'telegram_sender_bot_token');
        register_setting('telegram_sender_settings', 'telegram_sender_chat_ids');
        register_setting('telegram_sender_settings', 'telegram_sender_send_interval');
    }

    /**
     * افزودن منو به پنل مدیریت
     */
    public function add_admin_menu()
    {
        add_menu_page(
            'ارسال به تلگرام',
            'ارسال به تلگرام',
            'manage_options',
            'telegram-sender',
            array($this, 'admin_page'),
            'dashicons-share',
            30
        );

        add_submenu_page(
            'telegram-sender',
            'تنظیمات تلگرام',
            'تنظیمات',
            'manage_options',
            'telegram-sender-settings',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'telegram-sender',
            'مدیریت محصولات',
            'محصولات',
            'manage_options',
            'telegram-sender-products',
            array($this, 'products_page')
        );

        add_submenu_page(
            'telegram-sender',
            'مدیریت نوشته‌ها',
            'نوشته‌ها',
            'manage_options',
            'telegram-sender-posts',
            array($this, 'posts_page')
        );
    }

public function admin_enqueue_scripts($hook) {
    // اضافه کردن این debug
    error_log('Current hook: ' . $hook);
    
    if (strpos($hook, 'telegram-sender') === false) {
        return;
    }
    
    // اول jQuery را بارگذاری کنید
    wp_enqueue_script('jquery');
    
    // سپس فایل admin.js
    wp_enqueue_script(
        'telegram-sender-admin',
        TELEGRAM_SENDER_PLUGIN_URL . 'assets/js/admin.js',
        array('jquery'), // وابستگی به jQuery
        TELEGRAM_SENDER_VERSION,
        true // در footer بارگذاری شود
    );
    
    // متغیرهای JavaScript
    wp_localize_script('telegram-sender-admin', 'telegram_sender_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('telegram_sender_nonce'),
        'strings' => array(
            'product_sent_success' => __('محصول با موفقیت ارسال شد', 'telegram-sender'),
            'unknown_error' => __('خطای ناشناخته', 'telegram-sender'),
            'request_error' => __('خطا در ارسال درخواست', 'telegram-sender'),
            'confirm_send_all_products' => __('آیا از ارسال همه محصولات اطمینان دارید؟', 'telegram-sender'),
            'confirm_send_all_posts' => __('آیا از ارسال همه نوشته‌ها اطمینان دارید؟', 'telegram-sender'),
            'invalid_time_interval' => __('لطفاً فاصله زمانی معتبر وارد کنید', 'telegram-sender'),
            'no_changes_to_save' => __('هیچ تغییری برای ذخیره وجود ندارد', 'telegram-sender'),
            'changes_saved' => __('تغییرات ذخیره شد', 'telegram-sender'),
            'save_error' => __('خطا در ذخیره', 'telegram-sender'),
            'post_sent_success' => __('نوشته با موفقیت ارسال شد', 'telegram-sender'),
            'test_connection_success' => __('اتصال موفقیت‌آمیز بود!', 'telegram-sender'),
            'test_connection_error' => __('خطا در اتصال', 'telegram-sender'),
        )
    ));
}

    /**
     * صفحه اصلی پنل مدیریت
     */
    public function admin_page()
    {
        include TELEGRAM_SENDER_PLUGIN_DIR . 'admin/main-page.php';
    }

    /**
     * صفحه تنظیمات
     */
    public function settings_page()
    {
        include TELEGRAM_SENDER_PLUGIN_DIR . 'admin/settings-page.php';
    }

    /**
     * صفحه مدیریت محصولات
     */
    public function products_page()
    {
        include TELEGRAM_SENDER_PLUGIN_DIR . 'admin/products-page.php';
    }

    /**
     * صفحه مدیریت نوشته‌ها
     */
    public function posts_page()
    {
        include TELEGRAM_SENDER_PLUGIN_DIR . 'admin/posts-page.php';
    }

    /**
     * نمایش اطلاعیه عدم وجود ووکامرس
     */
    public function woocommerce_missing_notice()
    {
?>
<div class="notice notice-error">
    <p>
        <strong>افزونه ارسال به تلگرام:</strong>
        این افزونه نیاز به ووکامرس دارد. لطفاً ابتدا ووکامرس را نصب و فعال کنید.
    </p>
</div>
<?php
    }

/**
 * ارسال تک محصول به تلگرام (فقط عنوان)
 */
public function ajax_send_product() {
    error_log('AJAX send_product called');
    
    // بررسی nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'telegram_sender_nonce')) {
        error_log('Nonce verification failed');
        wp_send_json_error('خطا در احراز هویت');
        return;
    }
    
    // بررسی دسترسی
    if (!current_user_can('manage_options')) {
        wp_send_json_error('شما دسترسی لازم را ندارید');
        return;
    }
    
    // دریافت شناسه محصول
    $product_id = intval($_POST['product_id']);
    if (!$product_id) {
        wp_send_json_error('شناسه محصول نامعتبر است');
        return;
    }
    
    // بررسی وجود محصول
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('محصول یافت نشد');
        return;
    }
    
    // دریافت تنظیمات
    $bot_token = get_option('telegram_sender_bot_token');
    $chat_ids = get_option('telegram_sender_chat_ids', '');
    
    if (empty($bot_token)) {
        wp_send_json_error('توکن ربات تلگرام تنظیم نشده است');
        return;
    }
    
    if (empty($chat_ids)) {
        wp_send_json_error('چت آیدی تنظیم نشده است');
        return;
    }
    
    // تبدیل chat_ids به آرایه
    $chat_ids_array = array_map('trim', explode(',', $chat_ids));
    
    // ارسال محصول به تمام چت‌ها
    $success_count = 0;
    $error_messages = array();
    
    $telegram_api = new TelegramSender_API();
    foreach ($chat_ids_array as $chat_id) {
        if (empty($chat_id)) continue;
        
        $result = $telegram_api->send_product($product_id, $chat_id); // Use TelegramSender_API->send_product with product_id
        
        if ($result['success']) {
            $success_count++;
        } else {
            $error_messages[] = "خطا برای چت {$chat_id}: " . $result['message'];
            error_log("Telegram send error for chat {$chat_id}: " . $result['message']);
        }
    }
    
    if ($success_count > 0) {
        // بروزرسانی تعداد ارسال
        $current_count = get_post_meta($product_id, '_telegram_send_count', true) ?: 0;
        update_post_meta($product_id, '_telegram_send_count', $current_count + 1);
        update_post_meta($product_id, '_telegram_last_sent', current_time('mysql'));
        
        if (!empty($error_messages)) {
            wp_send_json_success("ارسال موفق به {$success_count} چت، اما با برخی خطاها: " . implode(', ', $error_messages));
        } else {
            wp_send_json_success("محصول با موفقیت به {$success_count} چت ارسال شد");
        }
    } else {
        wp_send_json_error('ارسال به هیچ چتی موفق نبود: ' . implode(', ', $error_messages));
    }
}

    /**
     * AJAX: ارسال همه محصولات
     */
    public function ajax_send_all_products()
    {
        check_ajax_referer('telegram_sender_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(-1, 403);
        }

        $interval = intval($_POST['interval']) ?: 5;
        $send_now = isset($_POST['send_now']) && $_POST['send_now'] === 'true';
        $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';
        $exclude_out_of_stock = isset($_POST['exclude_out_of_stock']) && $_POST['exclude_out_of_stock'] === '1' ? 1 : 0;
        $only_unsent = isset($_POST['only_unsent']) && $_POST['only_unsent'] === '1' ? 1 : 0;
        $only_price_updated = isset($_POST['only_price_updated']) && $_POST['only_price_updated'] === '1' ? 1 : 0;

        if ($send_now) {
            // ارسال فوری
            $telegram_api = new TelegramSender_API();
            $result = $telegram_api->send_all_products();

            if ($result['success']) {
                wp_send_json_success('همه محصولات با موفقیت ارسال شدند');
            } else {
                wp_send_json_error($result['message']);
            }
        } else {
            // ارسال برنامه‌ریزی شده
            $this->schedule_bulk_send('products', $interval, $start_time, $end_time, array(
                'exclude_out_of_stock' => $exclude_out_of_stock,
                'only_unsent' => $only_unsent,
                'only_price_updated' => $only_price_updated,
            ));
            wp_send_json_success('ارسال محصولات برنامه‌ریزی شد');
        }
    }

    /**
     * AJAX: ارسال یک نوشته
     */
    public function ajax_send_post()
    {
        check_ajax_referer('telegram_sender_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(-1, 403);
        }

        $post_id = intval($_POST['post_id']);

        if (!$post_id) {
            wp_send_json_error('شناسه نوشته نامعتبر است');
        }

        $telegram_api = new TelegramSender_API();
        $result = $telegram_api->send_post($post_id);

        if ($result['success']) {
            wp_send_json_success('نوشته با موفقیت ارسال شد');
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX: ارسال همه نوشته‌ها
     */
    public function ajax_send_all_posts()
    {
        check_ajax_referer('telegram_sender_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(-1, 403);
        }

        $interval = intval($_POST['interval']) ?: 5;
        $send_now = isset($_POST['send_now']) && $_POST['send_now'] === 'true';
        $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';

        if ($send_now) {
            // ارسال فوری
            $telegram_api = new TelegramSender_API();
            $result = $telegram_api->send_all_posts();

            if ($result['success']) {
                wp_send_json_success('همه نوشته‌ها با موفقیت ارسال شدند');
            } else {
                wp_send_json_error($result['message']);
            }
        } else {
            // ارسال برنامه‌ریزی شده
            $this->schedule_bulk_send('posts', $interval, $start_time, $end_time);
            wp_send_json_success('ارسال نوشته‌ها برنامه‌ریزی شد');
        }
    }

    /**
     * AJAX: بروزرسانی محصول
     */
    public function ajax_update_product()
    {
        check_ajax_referer('telegram_sender_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(-1, 403);
        }

        $product_id = intval($_POST['product_id']);
        $field = sanitize_text_field($_POST['field']);
        $value = sanitize_text_field($_POST['value']);

        if (!$product_id || !$field) {
            wp_send_json_error('اطلاعات نامعتبر');
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('محصول پیدا نشد');
        }

        $success = false;

        switch ($field) {
            case 'name':
                $product->set_name($value);
                $success = true;
                break;
            case 'price':
                $product->set_regular_price($value);
                $success = true;
                break;
            case 'sale_price':
                $product->set_sale_price($value);
                $success = true;
                break;
            case 'description':
                $product->set_description($value);
                $success = true;
                break;
        }

        if ($success) {
            $product->save();
            wp_send_json_success('محصول بروزرسانی شد');
        } else {
            wp_send_json_error('خطا در بروزرسانی');
        }
    }

/**
 * AJAX: همگام‌سازی محصولات
 */
public function ajax_sync_products()
{
    check_ajax_referer('telegram_sender_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(-1, 403);
    }

    // بررسی وجود ووکامرس
    if (!class_exists('WooCommerce')) {
        wp_send_json_error('ووکامرس فعال نیست');
        return;
    }

    try {
        $database = new TelegramSender_Database();
        $result = $database->sync_products();

        if ($result !== false && $result > 0) {
            wp_send_json_success("تعداد {$result} محصول همگام‌سازی شد");
        } elseif ($result === 0) {
            wp_send_json_success('هیچ محصول جدیدی برای همگام‌سازی پیدا نشد');
        } else {
            wp_send_json_error('خطا در همگام‌سازی محصولات');
        }
    } catch (Exception $e) {
        wp_send_json_error('خطا: ' . $e->getMessage());
    }
}

    /**
     * برنامه‌ریزی ارسال انبوه
     */
    private function schedule_bulk_send($type, $interval, $start_time = '', $end_time = '', $filters = array())
    {
        // حذف رویدادهای قبلی
        wp_clear_scheduled_hook('telegram_sender_scheduled_send');

        // برنامه‌ریزی رویداد جدید
        $args = array(
            'type' => $type,
            'interval' => $interval,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'filters' => $filters,
        );
        wp_schedule_single_event(time() + 60, 'telegram_sender_scheduled_send', array($args));

        // ذخیره اطلاعات در دیتابیس برای پیگیری
        update_option('telegram_sender_bulk_send_type', $type);
        update_option('telegram_sender_bulk_send_interval', $interval);
        update_option('telegram_sender_bulk_send_start_time', current_time('timestamp'));
        update_option('telegram_sender_bulk_send_window_start', $start_time);
        update_option('telegram_sender_bulk_send_window_end', $end_time);
        update_option('telegram_sender_bulk_send_filters', $filters);
    }

    /**
     * اجرای ارسال برنامه‌ریزی شده
     */
    public function scheduled_send_callback($args)
    {
        $type = $args['type'];
        $interval = $args['interval'];
        $start_time = isset($args['start_time']) ? $args['start_time'] : '';
        $end_time = isset($args['end_time']) ? $args['end_time'] : '';
        $filters = isset($args['filters']) && is_array($args['filters']) ? $args['filters'] : array();

        $telegram_api = new TelegramSender_API();

        if ($type === 'products') {
            $telegram_api->send_products_with_interval(
                $interval,
                $start_time,
                $end_time,
                !empty($filters['exclude_out_of_stock']),
                !empty($filters['only_unsent']),
                !empty($filters['only_price_updated'])
            );
        } elseif ($type === 'posts') {
            $telegram_api->send_posts_with_interval($interval, $start_time, $end_time);
        }
    }
/**
 * AJAX: تست اتصال
 */
public function ajax_test_connection() {
    check_ajax_referer('telegram_sender_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die(-1, 403);
    }
    
    $telegram_api = new TelegramSender_API();
    
    // ابتدا تست اتصال
    $test_result = $telegram_api->test_connection();
    
    if ($test_result['success']) {
        // ارسال تاریخ و ساعت به کانال
        $current_datetime = current_time('Y/m/d H:i:s');
        $send_result = $telegram_api->send_message($current_datetime);
        
        if ($send_result['success']) {
            wp_send_json_success('اتصال موفق! تاریخ و ساعت ارسال شد: ' . $current_datetime);
        } else {
            wp_send_json_success('اتصال موفق اما خطا در ارسال پیام: ' . $send_result['message']);
        }
    } else {
        wp_send_json_error($test_result['message']);
    }
}
   /**
 * AJAX: دریافت آمار
 */
public function ajax_get_stats() {
    check_ajax_referer('telegram_sender_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die(-1, 403);
    }
    
    $database = new TelegramSender_Database();
    $stats = $database->get_send_statistics();
    
    wp_send_json_success($stats);
}

/**
 * AJAX: رفرش لاگ‌ها
 */
public function ajax_refresh_logs() {
    check_ajax_referer('telegram_sender_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die(-1, 403);
    }
    
    $database = new TelegramSender_Database();
    $logs = $database->get_send_logs(10);
    
    wp_send_json_success($logs);
} 
}

// اولیه‌سازی افزونه
function telegram_sender_init()
{
    return TelegramSender::get_instance();
}

// شروع افزونه
add_action('plugins_loaded', 'telegram_sender_init');