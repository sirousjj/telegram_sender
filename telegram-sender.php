<?php

/**
 * Plugin Name: ارسال محتوا به تلگرام
 * Plugin URI: https://asgharmoeini.ir
 * Description: افزونه ارسال محصولات ووکامرس و مطالب وردپرس به تلگرام
 * Version: 1.0.1
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

// تعریف ثوابت افزونه
define('TELEGRAM_SENDER_VERSION', '1.0.1');
define('TELEGRAM_SENDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TELEGRAM_SENDER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TELEGRAM_SENDER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * کلاس اصلی افزونه
 */
class TelegramSender
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
        $this->load_dependencies();
    }

    private function init_hooks()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_telegram_send_product', array($this, 'ajax_send_product'));
        add_action('wp_ajax_telegram_send_all_products', array($this, 'ajax_send_all_products'));
        add_action('wp_ajax_telegram_send_post', array($this, 'ajax_send_post'));
        add_action('wp_ajax_telegram_send_all_posts', array($this, 'ajax_send_all_posts'));
        add_action('wp_ajax_telegram_update_product', array($this, 'ajax_update_product'));
        add_action('wp_ajax_telegram_sync_products', array($this, 'ajax_sync_products'));
        add_action('wp_ajax_telegram_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_telegram_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_telegram_refresh_logs', array($this, 'ajax_refresh_logs'));
        
        // Cron handlers - مهم!
        add_action('telegram_sender_scheduled_send', array($this, 'scheduled_send_callback'));
        add_action('telegram_sender_single_product_send', array($this, 'handle_single_product_send'));
        add_action('telegram_sender_single_post_send', array($this, 'handle_single_post_send'));
        
        // AJAX handlers برای دیباگ کرون
        add_action('wp_ajax_telegram_run_cron_now', array($this, 'ajax_run_cron_now'));
        add_action('wp_ajax_telegram_trigger_wp_cron', array($this, 'ajax_trigger_wp_cron'));
        add_action('wp_ajax_telegram_clear_all_crons', array($this, 'ajax_clear_all_crons'));
        add_action('wp_ajax_telegram_clear_cron_errors', array($this, 'ajax_clear_cron_errors'));
    }

    private function load_dependencies()
    {
        require_once TELEGRAM_SENDER_PLUGIN_DIR . 'includes/class-telegram-api.php';
        require_once TELEGRAM_SENDER_PLUGIN_DIR . 'includes/class-database.php';
        require_once TELEGRAM_SENDER_PLUGIN_DIR . 'includes/functions.php';
        require_once TELEGRAM_SENDER_PLUGIN_DIR . 'product-fields.php';
    }

    public function activate()
    {
        TelegramSender_Database::create_tables();
        add_option('telegram_sender_bot_token', '');
        add_option('telegram_sender_chat_ids', array());
        add_option('telegram_sender_send_interval', 5);
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        wp_clear_scheduled_hook('telegram_sender_scheduled_send');
        wp_clear_scheduled_hook('telegram_sender_single_product_send');
        wp_clear_scheduled_hook('telegram_sender_single_post_send');
        flush_rewrite_rules();
    }

    public function init()
    {
        load_plugin_textdomain('telegram-sender', false, dirname(TELEGRAM_SENDER_PLUGIN_BASENAME) . '/languages/');
        
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
        }
    }

    public function admin_init()
    {
        register_setting('telegram_sender_settings', 'telegram_sender_bot_token');
        register_setting('telegram_sender_settings', 'telegram_sender_chat_ids');
        register_setting('telegram_sender_settings', 'telegram_sender_send_interval');
    }

    public function add_admin_menu()
    {
        add_menu_page('ارسال به تلگرام', 'ارسال به تلگرام', 'manage_options', 'telegram-sender', array($this, 'admin_page'), 'dashicons-share', 30);
        add_submenu_page('telegram-sender', 'تنظیمات تلگرام', 'تنظیمات', 'manage_options', 'telegram-sender-settings', array($this, 'settings_page'));
        add_submenu_page('telegram-sender', 'مدیریت محصولات', 'محصولات', 'manage_options', 'telegram-sender-products', array($this, 'products_page'));
        add_submenu_page('telegram-sender', 'مدیریت نوشته‌ها', 'نوشته‌ها', 'manage_options', 'telegram-sender-posts', array($this, 'posts_page'));
        add_submenu_page('telegram-sender', 'دیباگ کرون', 'دیباگ کرون', 'manage_options', 'telegram-sender-cron-debug', array($this, 'cron_debug_page'));
    }

    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'telegram-sender') === false) return;
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('telegram-sender-admin', TELEGRAM_SENDER_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), TELEGRAM_SENDER_VERSION, true);
        
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

    public function admin_page() { include TELEGRAM_SENDER_PLUGIN_DIR . 'admin/main-page.php'; }
    public function settings_page() { include TELEGRAM_SENDER_PLUGIN_DIR . 'admin/settings-page.php'; }
    public function products_page() { include TELEGRAM_SENDER_PLUGIN_DIR . 'admin/products-page.php'; }
    public function posts_page() { include TELEGRAM_SENDER_PLUGIN_DIR . 'admin/posts-page.php'; }
    public function cron_debug_page() { include TELEGRAM_SENDER_PLUGIN_DIR . 'admin/cron-debug-page.php'; }

    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p><strong>افزونه ارسال به تلگرام:</strong> این افزونه نیاز به ووکامرس دارد.</p></div>';
    }

    public function ajax_send_product() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'telegram_sender_nonce')) {
            wp_send_json_error('خطا در احراز هویت');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('شما دسترسی لازم را ندارید');
        }
        
        $product_id = intval($_POST['product_id']);
        if (!$product_id || !wc_get_product($product_id)) {
            wp_send_json_error('محصول نامعتبر است');
        }
        
        $telegram_api = new TelegramSender_API();
        $result = $telegram_api->send_product($product_id);
        
        if ($result['success']) {
            $current_count = get_post_meta($product_id, '_telegram_send_count', true) ?: 0;
            update_post_meta($product_id, '_telegram_send_count', $current_count + 1);
            update_post_meta($product_id, '_telegram_last_sent', current_time('mysql'));
            wp_send_json_success('محصول با موفقیت ارسال شد');
        } else {
            wp_send_json_error($result['message']);
        }
    }

    public function ajax_send_all_products() {
        check_ajax_referer('telegram_sender_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        $interval = intval($_POST['interval']) ?: 5;
        $send_now = isset($_POST['send_now']) && $_POST['send_now'] === 'true';
        $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';
        $filters = array(
            'exclude_out_of_stock' => isset($_POST['exclude_out_of_stock']) && $_POST['exclude_out_of_stock'] === '1',
            'only_unsent' => isset($_POST['only_unsent']) && $_POST['only_unsent'] === '1',
            'only_price_updated' => isset($_POST['only_price_updated']) && $_POST['only_price_updated'] === '1',
        );

        if ($send_now) {
            $telegram_api = new TelegramSender_API();
            $result = $telegram_api->send_all_products();
            wp_send_json($result);
        } else {
            $this->schedule_bulk_send('products', $interval, $start_time, $end_time, $filters);
            wp_send_json_success('ارسال محصولات برنامه‌ریزی شد');
        }
    }

    public function ajax_send_post() {
        check_ajax_referer('telegram_sender_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        $post_id = intval($_POST['post_id']);
        $telegram_api = new TelegramSender_API();
        $result = $telegram_api->send_post($post_id);
        wp_send_json($result);
    }

    public function ajax_send_all_posts() {
        check_ajax_referer('telegram_sender_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        $interval = intval($_POST['interval']) ?: 5;
        $send_now = isset($_POST['send_now']) && $_POST['send_now'] === 'true';
        $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $end_time = isset($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : '';

        if ($send_now) {
            $telegram_api = new TelegramSender_API();
            $result = $telegram_api->send_all_posts();
            wp_send_json($result);
        } else {
            $this->schedule_bulk_send('posts', $interval, $start_time, $end_time);
            wp_send_json_success('ارسال نوشته‌ها برنامه‌ریزی شد');
        }
    }

    public function ajax_update_product() {
        check_ajax_referer('telegram_sender_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        $product_id = intval($_POST['product_id']);
        $field = sanitize_text_field($_POST['field']);
        $value = sanitize_text_field($_POST['value']);

        $product = wc_get_product($product_id);
        if (!$product) wp_send_json_error('محصول پیدا نشد');

        switch ($field) {
            case 'name': $product->set_name($value); break;
            case 'price': $product->set_regular_price($value); break;
            case 'sale_price': $product->set_sale_price($value); break;
            case 'description': $product->set_description($value); break;
            default: wp_send_json_error('فیلد نامعتبر');
        }

        $product->save();
        wp_send_json_success('محصول بروزرسانی شد');
    }

    public function ajax_sync_products() {
        check_ajax_referer('telegram_sender_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        $database = new TelegramSender_Database();
        $result = $database->sync_products();
        
        if ($result > 0) {
            wp_send_json_success("تعداد {$result} محصول همگام‌سازی شد");
        } else {
            wp_send_json_success('هیچ محصول جدیدی پیدا نشد');
        }
    }

    public function ajax_test_connection() {
        check_ajax_referer('telegram_sender_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        $telegram_api = new TelegramSender_API();
        $test_result = $telegram_api->test_connection();
        
        if ($test_result['success']) {
            $current_datetime = current_time('Y/m/d H:i:s');
            $send_result = $telegram_api->send_message($current_datetime);
            wp_send_json_success($send_result['success'] ? 'اتصال موفق! تاریخ ارسال شد' : 'اتصال موفق اما خطا در ارسال');
        } else {
            wp_send_json_error($test_result['message']);
        }
    }

    public function ajax_get_stats() {
        check_ajax_referer('telegram_sender_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        $database = new TelegramSender_Database();
        wp_send_json_success($database->get_send_statistics());
    }

    public function ajax_refresh_logs() {
        check_ajax_referer('telegram_sender_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        $database = new TelegramSender_Database();
        wp_send_json_success($database->get_send_logs(10));
    }

    // === بخش Cron Handlers ===
    public function handle_single_product_send($product_id) {
        error_log('Cron: ارسال محصول ' . $product_id);
        
        try {
            $telegram_api = new TelegramSender_API();
            $result = $telegram_api->send_product($product_id);
            
            if ($result['success']) {
                error_log('Cron: محصول ' . $product_id . ' با موفقیت ارسال شد');
            } else {
                error_log('Cron: خطا در ارسال محصول ' . $product_id . ': ' . $result['message']);
                $this->log_cron_error('telegram_sender_single_product_send', $result['message']);
            }
        } catch (Exception $e) {
            error_log('Cron Exception: ' . $e->getMessage());
            $this->log_cron_error('telegram_sender_single_product_send', $e->getMessage());
        }
    }

    public function handle_single_post_send($post_id) {
        error_log('Cron: ارسال نوشته ' . $post_id);
        
        try {
            $telegram_api = new TelegramSender_API();
            $result = $telegram_api->send_post($post_id);
            
            if ($result['success']) {
                error_log('Cron: نوشته ' . $post_id . ' با موفقیت ارسال شد');
            } else {
                error_log('Cron: خطا در ارسال نوشته ' . $post_id . ': ' . $result['message']);
                $this->log_cron_error('telegram_sender_single_post_send', $result['message']);
            }
        } catch (Exception $e) {
            error_log('Cron Exception: ' . $e->getMessage());
            $this->log_cron_error('telegram_sender_single_post_send', $e->getMessage());
        }
    }

    public function scheduled_send_callback($args) {
        $type = $args['type'];
        $interval = $args['interval'];
        $start_time = isset($args['start_time']) ? $args['start_time'] : '';
        $end_time = isset($args['end_time']) ? $args['end_time'] : '';
        $filters = isset($args['filters']) ? $args['filters'] : array();

        $telegram_api = new TelegramSender_API();

        if ($type === 'products') {
            $telegram_api->send_products_with_interval(
                $interval, $start_time, $end_time,
                !empty($filters['exclude_out_of_stock']),
                !empty($filters['only_unsent']),
                !empty($filters['only_price_updated'])
            );
        } elseif ($type === 'posts') {
            $telegram_api->send_posts_with_interval($interval, $start_time, $end_time);
        }
    }

    private function schedule_bulk_send($type, $interval, $start_time = '', $end_time = '', $filters = array()) {
        wp_clear_scheduled_hook('telegram_sender_scheduled_send');
        
        $args = array(
            'type' => $type,
            'interval' => $interval,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'filters' => $filters,
        );
        
        wp_schedule_single_event(time() + 60, 'telegram_sender_scheduled_send', array($args));
    }

    private function log_cron_error($hook, $message) {
        $errors = get_option('telegram_sender_cron_errors', array());
        $errors[] = array(
            'time' => current_time('Y-m-d H:i:s'),
            'hook' => $hook,
            'message' => $message
        );
        
        // نگه داشتن فقط 50 خطای آخر
        if (count($errors) > 50) {
            $errors = array_slice($errors, -50);
        }
        
        update_option('telegram_sender_cron_errors', $errors);
    }

    // === AJAX handlers برای دیباگ کرون ===
    public function ajax_run_cron_now() {
        check_ajax_referer('telegram_cron_debug', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        $hook = sanitize_text_field($_POST['hook']);
        $timestamp = intval($_POST['timestamp']);

        $crons = _get_cron_array();
        if (isset($crons[$timestamp][$hook])) {
            $args = reset($crons[$timestamp][$hook])['args'];
            do_action_ref_array($hook, $args);
            wp_send_json_success('کرون با موفقیت اجرا شد');
        } else {
            wp_send_json_error('کرون پیدا نشد');
        }
    }

    public function ajax_trigger_wp_cron() {
        check_ajax_referer('telegram_cron_debug', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        spawn_cron();
        wp_send_json_success('WP-Cron اجرا شد');
    }

    public function ajax_clear_all_crons() {
        check_ajax_referer('telegram_cron_debug', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        wp_clear_scheduled_hook('telegram_sender_single_product_send');
        wp_clear_scheduled_hook('telegram_sender_single_post_send');
        wp_clear_scheduled_hook('telegram_sender_scheduled_send');
        
        wp_send_json_success('تمام کرون‌ها پاک شدند');
    }

    public function ajax_clear_cron_errors() {
        check_ajax_referer('telegram_cron_debug', 'nonce');
        if (!current_user_can('manage_options')) wp_die(-1, 403);

        delete_option('telegram_sender_cron_errors');
        wp_send_json_success('خطاها پاک شدند');
    }
}

// اولیه‌سازی افزونه
function telegram_sender_init() {
    return TelegramSender::get_instance();
}

add_action('plugins_loaded', 'telegram_sender_init');
