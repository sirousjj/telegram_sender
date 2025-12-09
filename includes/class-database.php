<?php

/**
 * کلاس مدیریت دیتابیس
 * 
 * @package TelegramSender
 * @author اصغر معینی <as.moini@gmail.com>
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class TelegramSender_Database
{

    /**
     * نام جدول محصولات
     */
    const PRODUCTS_TABLE = 'telegram_sender_products';

    /**
     * نام جدول لاگ‌ها
     */
    const LOGS_TABLE = 'telegram_sender_logs';

    /**
     * سازنده کلاس
     */
    public function __construct()
    {
        // هیچ اقدام خاصی در سازنده
    }

    /**
     * ایجاد جداول دیتابیس
     */
    public static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // جدول محصولات سینک شده
        $products_table = $wpdb->prefix . self::PRODUCTS_TABLE;
        $products_sql = "CREATE TABLE $products_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            name text NOT NULL,
            price decimal(10,2) DEFAULT NULL,
            sale_price decimal(10,2) DEFAULT NULL,
            description longtext,
            short_description text,
            image_url text,
            stock_status varchar(20) DEFAULT 'instock',
            sku varchar(100),
            last_synced datetime DEFAULT CURRENT_TIMESTAMP,
            last_sent datetime DEFAULT NULL,
            send_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_id (product_id),
            KEY stock_status (stock_status),
            KEY last_synced (last_synced)
        ) $charset_collate;";

        // جدول لاگ‌های ارسال
        $logs_table = $wpdb->prefix . self::LOGS_TABLE;
        $logs_sql = "CREATE TABLE $logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(20) NOT NULL,
            item_id bigint(20) NOT NULL,
            chat_id varchar(100) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            response_data longtext,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type_item (type, item_id),
            KEY status (status),
            KEY sent_at (sent_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($products_sql);
        dbDelta($logs_sql);

        // افزودن ایندکس‌های اضافی
        self::add_indexes();
    }

    /**
     * افزودن ایندکس‌های اضافی
     */
    private static function add_indexes()
    {
        global $wpdb;

        $products_table = $wpdb->prefix . self::PRODUCTS_TABLE;
        $logs_table = $wpdb->prefix . self::LOGS_TABLE;

        // بررسی وجود ایندکس‌ها قبل از افزودن
        $wpdb->query("ALTER TABLE $products_table ADD INDEX IF NOT EXISTS idx_name (name(50))");
        $wpdb->query("ALTER TABLE $logs_table ADD INDEX IF NOT EXISTS idx_chat_id (chat_id)");
    }

 /**
 * همگام‌سازی محصولات
 */
public function sync_products()
{
    global $wpdb;

    $products_table = $wpdb->prefix . self::PRODUCTS_TABLE;

    // بررسی وجود جدول
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$products_table'");
    if (!$table_exists) {
        // ایجاد جدول اگر وجود ندارد
        self::create_tables();
    }

    // بررسی وجود ووکامرس
    if (!function_exists('wc_get_products')) {
        return false;
    }

    try {
        // گرفتن همه محصولات ووکامرس
        $wc_products = wc_get_products(array(
            'status' => array('publish', 'private'),
            'limit' => -1
        ));

        if (empty($wc_products)) {
            return 0; // هیچ محصولی پیدا نشد
        }

        $synced_count = 0;

        foreach ($wc_products as $product) {
            // آماده‌سازی داده‌های محصول
            $product_data = $this->prepare_product_data($product);
            
            if (empty($product_data)) {
                continue; // اگر داده نامعتبر بود، رد کن
            }

            // بررسی وجود محصول در جدول
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $products_table WHERE product_id = %d",
                $product->get_id()
            ));

            if ($existing) {
                // بروزرسانی محصول موجود
                $updated = $wpdb->update(
                    $products_table,
                    $product_data,
                    array('product_id' => $product->get_id()),
                    array('%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );

                if ($updated !== false) {
                    $synced_count++;
                }
            } else {
                // درج محصول جدید
                $product_data['product_id'] = $product->get_id();
                
                $inserted = $wpdb->insert(
                    $products_table,
                    $product_data,
                    array('%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s')
                );

                if ($inserted) {
                    $synced_count++;
                }
            }
        }

        // حذف محصولات حذف شده از ووکامرس
        $this->cleanup_deleted_products();

        return $synced_count;

    } catch (Exception $e) {
        // لاگ خطا
        error_log('Telegram Sender Sync Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * آماده‌سازی داده‌های محصول
 */
private function prepare_product_data($product)
{
    if (!$product || !is_object($product)) {
        return array();
    }

    try {
        return array(
            'name' => $product->get_name() ?: '',
            'price' => $product->get_regular_price() ? floatval($product->get_regular_price()) : null,
            'sale_price' => $product->get_sale_price() ? floatval($product->get_sale_price()) : null,
            'description' => $product->get_description() ?: '',
            'short_description' => $product->get_short_description() ?: '',
            'image_url' => wp_get_attachment_url($product->get_image_id()) ?: '',
            'stock_status' => $product->get_stock_status() ?: 'instock',
            'sku' => $product->get_sku() ?: '',
            'last_synced' => current_time('mysql')
        );
    } catch (Exception $e) {
        error_log('Telegram Sender Product Data Error: ' . $e->getMessage());
        return array();
    }
}

    /**
     * پاک‌سازی محصولات حذف شده
     */
    private function cleanup_deleted_products()
    {
        global $wpdb;

        $products_table = $wpdb->prefix . self::PRODUCTS_TABLE;

        // گرفتن آیدی تمام محصولات فعال ووکامرس
        $active_products = wc_get_products(array(
            'status' => array('publish', 'private'),
            'limit' => -1,
            'return' => 'ids'
        ));

        if (!empty($active_products)) {
            $active_ids = implode(',', array_map('intval', $active_products));

            // حذف محصولاتی که در ووکامرس وجود ندارند
            $wpdb->query("DELETE FROM $products_table WHERE product_id NOT IN ($active_ids)");
        } else {
            // اگر هیچ محصول فعالی نیست، همه را حذف کن
            $wpdb->query("TRUNCATE TABLE $products_table");
        }
    }

    /**
     * گرفتن لیست محصولات سینک شده
     */
    public function get_synced_products($limit = 20, $offset = 0, $search = '')
    {
        global $wpdb;

        $products_table = $wpdb->prefix . self::PRODUCTS_TABLE;

        $where_clause = '';
        $params = array();

        if (!empty($search)) {
            $where_clause = "WHERE name LIKE %s OR sku LIKE %s";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params = array($search_term, $search_term);
        }

        $sql = "SELECT * FROM $products_table $where_clause ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * شمارش کل محصولات
     */
    public function count_synced_products($search = '')
    {
        global $wpdb;

        $products_table = $wpdb->prefix . self::PRODUCTS_TABLE;

        $where_clause = '';
        $params = array();

        if (!empty($search)) {
            $where_clause = "WHERE name LIKE %s OR sku LIKE %s";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params = array($search_term, $search_term);
        }

        $sql = "SELECT COUNT(*) FROM $products_table $where_clause";

        if (!empty($params)) {
            return $wpdb->get_var($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_var($sql);
        }
    }

    /**
     * بروزرسانی اطلاعات محصول
     */
    public function update_product($product_id, $data)
    {
        global $wpdb;

        $products_table = $wpdb->prefix . self::PRODUCTS_TABLE;

        // اعتبارسنجی داده‌ها
        $allowed_fields = array('name', 'price', 'sale_price', 'description', 'short_description', 'stock_status', 'sku');
        $filtered_data = array();

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $filtered_data[$key] = $value;
            }
        }

        if (empty($filtered_data)) {
            return false;
        }

        $filtered_data['updated_at'] = current_time('mysql');

        return $wpdb->update(
            $products_table,
            $filtered_data,
            array('product_id' => $product_id)
        );
    }

    /**
     * ثبت لاگ ارسال
     */
    public function log_send($type, $item_id, $chat_id, $status, $message = '', $response_data = '')
    {
        global $wpdb;

        $logs_table = $wpdb->prefix . self::LOGS_TABLE;

        $log_data = array(
            'type' => $type,
            'item_id' => $item_id,
            'chat_id' => $chat_id,
            'status' => $status,
            'message' => $message,
            'response_data' => $response_data,
            'sent_at' => current_time('mysql')
        );

        $result = $wpdb->insert($logs_table, $log_data);

        // بروزرسانی شمارنده ارسال
        if ($result && $status === 'success') {
            $this->increment_send_count($type, $item_id);
        }

        return $result;
    }

    /**
     * افزایش شمارنده ارسال
     */
    private function increment_send_count($type, $item_id)
    {
        global $wpdb;

        if ($type === 'product') {
            $products_table = $wpdb->prefix . self::PRODUCTS_TABLE;

            $wpdb->query($wpdb->prepare(
                "UPDATE $products_table SET send_count = send_count + 1, last_sent = %s WHERE product_id = %d",
                current_time('mysql'),
                $item_id
            ));
        }
    }

    /**
     * گرفتن لاگ‌های ارسال
     */
    public function get_send_logs($limit = 50, $offset = 0, $type = '', $status = '')
    {
        global $wpdb;

        $logs_table = $wpdb->prefix . self::LOGS_TABLE;

        $where_conditions = array();
        $params = array();

        if (!empty($type)) {
            $where_conditions[] = "type = %s";
            $params[] = $type;
        }

        if (!empty($status)) {
            $where_conditions[] = "status = %s";
            $params[] = $status;
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $sql = "SELECT * FROM $logs_table $where_clause ORDER BY sent_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * شمارش لاگ‌ها
     */
    public function count_send_logs($type = '', $status = '')
    {
        global $wpdb;

        $logs_table = $wpdb->prefix . self::LOGS_TABLE;

        $where_conditions = array();
        $params = array();

        if (!empty($type)) {
            $where_conditions[] = "type = %s";
            $params[] = $type;
        }

        if (!empty($status)) {
            $where_conditions[] = "status = %s";
            $params[] = $status;
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $sql = "SELECT COUNT(*) FROM $logs_table $where_clause";

        if (!empty($params)) {
            return $wpdb->get_var($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_var($sql);
        }
    }

    /**
     * پاک‌سازی لاگ‌های قدیمی
     */
    public function cleanup_old_logs($days = 30)
    {
        global $wpdb;

        $logs_table = $wpdb->prefix . self::LOGS_TABLE;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $logs_table WHERE sent_at < %s",
            $cutoff_date
        ));
    }

    /**
     * گرفتن آمار ارسال
     */
    public function get_send_statistics()
    {
        global $wpdb;

        $logs_table = $wpdb->prefix . self::LOGS_TABLE;
        $products_table = $wpdb->prefix . self::PRODUCTS_TABLE;

        // آمار کلی
        $total_sends = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
        $successful_sends = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE status = 'success'");
        $failed_sends = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE status = 'error'");

        // آمار امروز
        $today = date('Y-m-d');
        $today_sends = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $logs_table WHERE DATE(sent_at) = %s",
            $today
        ));

        // آمار این هفته
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_sends = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $logs_table WHERE sent_at >= %s",
            $week_start
        ));

        // محصولات بیشترین ارسال
        $top_products = $wpdb->get_results(
            "SELECT product_id, name, send_count FROM $products_table ORDER BY send_count DESC LIMIT 5"
        );

        return array(
            'total_sends' => intval($total_sends),
            'successful_sends' => intval($successful_sends),
            'failed_sends' => intval($failed_sends),
            'today_sends' => intval($today_sends),
            'week_sends' => intval($week_sends),
            'success_rate' => $total_sends > 0 ? round(($successful_sends / $total_sends) * 100, 2) : 0,
            'top_products' => $top_products
        );
    }

    /**
     * حذف جداول (در زمان حذف افزونه)
     */
    public static function drop_tables()
    {
        global $wpdb;

        $products_table = $wpdb->prefix . self::PRODUCTS_TABLE;
        $logs_table = $wpdb->prefix . self::LOGS_TABLE;

        $wpdb->query("DROP TABLE IF EXISTS $products_table");
        $wpdb->query("DROP TABLE IF EXISTS $logs_table");
    }
}