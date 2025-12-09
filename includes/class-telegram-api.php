<?php

/**
 * کلاس مدیریت API تلگرام
 * 
 * @package TelegramSender
 * @author اصغر معینی <as.moini@gmail.com>
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}
error_log('تست دیباگ Telegram Sender');
error_log('فایل class-telegram-api.php لود شد');
class TelegramSender_API
{

    /**
     * توکن ربات تلگرام
     */
    private $bot_token;

    /**
     * لیست چت آیدی‌ها
     */
    private $chat_ids;

    /**
     * URL پایه API تلگرام
     */
    private $api_base_url;
/**
 * آدرس پروکسی سرور
 */
private $proxy_url;

/**
 * کلید امنیتی پروکسی
 */
private $proxy_secret;
    /**
     * سازنده کلاس
     */
public function __construct()
{
    $this->bot_token = get_option('telegram_sender_bot_token', '');
    $chat_ids_option = get_option('telegram_sender_chat_ids', array());

    // دیباگ نوع داده برای لاگ (موقت اضافه کن، بعد حذف)
    error_log('Chat IDs option type: ' . gettype($chat_ids_option) . ' | Value: ' . print_r($chat_ids_option, true));

    if (is_array($chat_ids_option)) {
        $this->chat_ids = array_map('trim', array_filter($chat_ids_option));
    } else {
        // امن کردن: cast به string قبل از explode
        $chat_ids_string = is_scalar($chat_ids_option) ? (string)$chat_ids_option : '';
        $this->chat_ids = array_map('trim', explode(',', $chat_ids_string));
        $this->chat_ids = array_filter($this->chat_ids);
    }

    $this->api_base_url = 'https://api.telegram.org/bot' . $this->bot_token . '/';
    // تنظیمات پروکسی
$this->proxy_url = get_option('telegram_sender_proxy_url', '');
$this->proxy_secret = get_option('telegram_sender_proxy_secret', '');
}

/**
 * ارسال پیام به تلگرام
 */
public function send_message($message, $chat_id = null, $parse_mode = 'HTML', $reply_markup = null)
{
    if (empty($this->bot_token)) {
        return array(
            'success' => false,
            'message' => 'توکن ربات تنظیم نشده است'
        );
    }

    $chat_ids_to_send = $chat_id ? array($chat_id) : $this->chat_ids;

    if (empty($chat_ids_to_send)) {
        return array(
            'success' => false,
            'message' => 'هیچ چت آیدی تنظیم نشده است'
        );
    }

    $results = array();
    $overall_success = true;

    foreach ($chat_ids_to_send as $chat_id) {
        $params = array(
            'chat_id' => trim($chat_id),
            'text' => $message,
            'parse_mode' => $parse_mode,
            'disable_web_page_preview' => false
        );

        // اضافه کردن دکمه‌ها اگر وجود داشته باشند
        if ($reply_markup) {
            $params['reply_markup'] = $reply_markup;
        }

        $response = $this->make_request('sendMessage', $params);

        if (!$response['success']) {
            $overall_success = false;
            $results[] = "خطا در ارسال به {$chat_id}: " . $response['message'];
        } else {
            $results[] = "پیام با موفقیت به {$chat_id} ارسال شد";
        }
    }

    return array(
        'success' => $overall_success,
        'message' => implode("\n", $results)
    );
}

/**
 * ارسال عکس به تلگرام
 */
public function send_photo($photo_url, $caption = '', $chat_id = null, $reply_markup = null)
{
    if (empty($this->bot_token)) {
        return array(
            'success' => false,
            'message' => 'توکن ربات تنظیم نشده است'
        );
    }

    $chat_ids_to_send = $chat_id ? array($chat_id) : $this->chat_ids;

    if (empty($chat_ids_to_send)) {
        return array(
            'success' => false,
            'message' => 'هیچ چت آیدی تنظیم نشده است'
        );
    }

    $results = array();
    $overall_success = true;

    foreach ($chat_ids_to_send as $chat_id) {
        $params = array(
            'chat_id' => trim($chat_id),
            'photo' => $photo_url,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        );

        // اضافه کردن دکمه‌ها اگر وجود داشته باشند
        if ($reply_markup) {
            $params['reply_markup'] = $reply_markup;
        }

        $response = $this->make_request('sendPhoto', $params);

        if (!$response['success']) {
            $overall_success = false;
            $results[] = "خطا در ارسال عکس به {$chat_id}: " . $response['message'];
        } else {
            $results[] = "عکس با موفقیت به {$chat_id} ارسال شد";
        }
    }

    return array(
        'success' => $overall_success,
        'message' => implode("\n", $results)
    );
}

/**
 * ارسال محصول به تلگرام - نسخه بهبود یافته
 */
public function send_product($product_id, $chat_id = null)
{
    $product = wc_get_product($product_id);
    if (!$product) {
        return array(
            'success' => false,
            'message' => 'محصول پیدا نشد'
        );
    }
    
    // ساخت پیام کامل محصول
    $message = $this->format_product_message_enhanced($product);
    
    // ساخت دکمه‌ها
    $reply_markup = $this->create_product_keyboard($product);
    
    // ارسال عکس محصول (در صورت وجود) همراه با متن و دکمه‌ها
    $image_id = $product->get_image_id();
    if ($image_id) {
        $image_url = wp_get_attachment_image_url($image_id, 'full');
        if ($image_url) {
            $result = $this->send_photo($image_url, $message, $chat_id, $reply_markup);
        } else {
            $result = $this->send_message($message, $chat_id, 'HTML', $reply_markup);
        }
    } else {
        $result = $this->send_message($message, $chat_id, 'HTML', $reply_markup);
    }
    
    // ثبت لاگ
    $database = new TelegramSender_Database();
    $status = $result['success'] ? 'success' : 'error';
    $chat_ids = $chat_id ? array($chat_id) : $this->chat_ids;
    
    foreach ($chat_ids as $chat) {
        $database->log_send('product', $product_id, $chat, $status, $result['message']);
    }
    
    // به‌روزرسانی متاهای ارسال موفق برای فیلترها
    if ($result['success']) {
        $current_count = get_post_meta($product_id, '_telegram_send_count', true) ?: 0;
        update_post_meta($product_id, '_telegram_send_count', intval($current_count) + 1);
        update_post_meta($product_id, '_telegram_last_sent', current_time('mysql'));
        update_post_meta($product_id, '_telegram_last_sent_price', $product->get_price());
    }
    
    return $result;
}

    /**
     * فرمت کردن پیام محصول - نسخه بهبود یافته
     */
    private function format_product_message_enhanced($product)
    {
        // توضیحات تلگرام سفارشی
$telegram_description = get_post_meta($product->get_id(), '_telegram_description', true);
if (!empty($telegram_description)) {
    $message .= "📝 " . trim($telegram_description) . "\n\n";
}
        // عنوان محصول
        $message = "🛍️ <b>" . esc_html($product->get_name()) . "</b>\n\n";
        
        // قیمت محصول
        $price = $product->get_price();
        $sale_price = $product->get_sale_price();
        $regular_price = $product->get_regular_price();
        
if ($sale_price && $sale_price < $regular_price) {
    // محصول در حال تخفیف
    $formatted_regular_price = wc_price($regular_price);
    $formatted_sale_price = wc_price($sale_price);

    // حذف تگ‌ها، تبدیل entityها و پاک‌سازی فاصله‌های نامفهوم (مثل &nbsp; یا NBSP / zero-width)
    $regular_price_text = strip_tags($formatted_regular_price);
    $regular_price_text = html_entity_decode($regular_price_text, ENT_QUOTES, 'UTF-8');
    $regular_price_text = preg_replace('/[\x{00A0}\x{200B}]+/u', ' ', $regular_price_text);
    $regular_price_text = preg_replace('/\s+/u', ' ', $regular_price_text);
    $regular_price_text = trim($regular_price_text);

    $sale_price_text = strip_tags($formatted_sale_price);
    $sale_price_text = html_entity_decode($sale_price_text, ENT_QUOTES, 'UTF-8');
    $sale_price_text = preg_replace('/[\x{00A0}\x{200B}]+/u', ' ', $sale_price_text);
    $sale_price_text = preg_replace('/\s+/u', ' ', $sale_price_text);
    $sale_price_text = trim($sale_price_text);

    // محاسبه درصد تخفیف
    $discount_percent = round((($regular_price - $sale_price) / $regular_price) * 100);

    $message .= "💰 <b>قیمت:</b> <s>" . $regular_price_text . "</s> " . $sale_price_text . "\n";
    $message .= "🔥 <b>تخفیف:</b> " . $discount_percent . "% تخفیف!\n\n";
} elseif ($price) {
    $formatted_price = wc_price($price);
    $price_text = strip_tags($formatted_price);
    $price_text = html_entity_decode($price_text, ENT_QUOTES, 'UTF-8');
    $price_text = preg_replace('/[\x{00A0}\x{200B}]+/u', ' ', $price_text);
    $price_text = preg_replace('/\s+/u', ' ', $price_text);
    $price_text = trim($price_text);

    $message .= "💰 <b>قیمت:</b> " . $price_text . "\n\n";
} else {
    $message .= "💰 <b>قیمت:</b> تماس بگیرید\n\n";
}

        
        // دسته‌بندی محصول
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        if (!empty($categories)) {
            $category_names = array();
            foreach ($categories as $category) {
                $category_names[] = $category->name;
            }
            $message .= "📂 <b>دسته‌بندی:</b> " . implode(', ', $category_names) . "\n\n";
        }
        
        // کد محصول (SKU)
        $sku = $product->get_sku();
        if ($sku) {
            $message .= "🏷️ <b>کد محصول:</b> " . $sku . "\n\n";
        }
        
       // توضیحات تلگرام سفارشی یا توضیحات کوتاه محصول
$telegram_description = get_post_meta($product->get_id(), '_telegram_description', true);
if (!empty($telegram_description)) {
    // اگر توضیحات تلگرام موجود است
    $clean_description = strip_tags($telegram_description);
    $clean_description = wp_trim_words($clean_description, 30, '...');
    $message .= "📝 <b>توضیحات:</b>\n" . $clean_description . "\n\n";
}
// اگر توضیحات تلگرام خالی باشد، هیچی نمایش نمی‌دهیم
        
        // وضعیت موجودی
        if ($product->is_in_stock()) {
            $stock_quantity = $product->get_stock_quantity();
            if ($stock_quantity) {
                if ($stock_quantity <= 5) {
                    $message .= "⚠️ <b>موجودی:</b> " . $stock_quantity . " عدد (تعداد محدود!)\n\n";
                } else {
                    $message .= "✅ <b>موجودی:</b> " . $stock_quantity . " عدد\n\n";
                }
            } else {
                $message .= "✅ <b>وضعیت:</b> موجود\n\n";
            }
        } else {
            $message .= "❌ <b>وضعیت:</b> ناموجود\n\n";
        }
        
        // امتیاز محصول (اگر وجود دارد)
        $average_rating = $product->get_average_rating();
        $review_count = $product->get_review_count();
        if ($average_rating > 0) {
            $stars = str_repeat('⭐', floor($average_rating));
            $message .= "⭐ <b>امتیاز:</b> " . $stars . " (" . $average_rating . "/5 از " . $review_count . " نظر)\n\n";
        }
        
        // لینک محصول
        $product_url = get_permalink($product->get_id());
        $message .= "🔗 <a href='" . $product_url . "'><b>مشاهده و خرید محصول</b></a>\n\n";
        
        // هشتگ‌ها
$telegram_hashtags = get_post_meta($product->get_id(), '_telegram_hashtags', true);
if (!empty($telegram_hashtags)) {
    $message .= "\n" . $telegram_hashtags . "\n\n";
} else {
    $message .= "\n";
}
        // اطلاعات اضافی
        $message .= "🏪 <b>" . get_bloginfo('name') . "</b>";
        
        // اضافه کردن امضای پیام‌ها
        $signature = get_option('telegram_sender_message_signature', '');
        if (!empty($signature)) {
            $message .= "\n\n" . "━━━━━━━━━━━━\n" . trim($signature);
        }
        
        // محدود کردن طول پیام برای کپشن تلگرام (حداکثر 1024 کاراکتر)
        if (strlen($message) > 1024) {
            $message = mb_substr($message, 0, 1020, 'UTF-8') . '...';
        }
        
        return $message;
    }

    /**
     * ارسال یک نوشته به تلگرام
     */
    public function send_post($post_id, $chat_id = null)
    {
        $post = get_post($post_id);

        if (!$post) {
            return array(
                'success' => false,
                'message' => 'نوشته پیدا نشد'
            );
        }

        $message = $this->format_post_message($post);
        $featured_image_url = get_the_post_thumbnail_url($post_id, 'large');

        if ($featured_image_url) {
            // ارسال با عکس شاخص
            return $this->send_photo($featured_image_url, $message, $chat_id);
        } else {
            // ارسال بدون عکس
            return $this->send_message($message, $chat_id);
        }
    }

    /**
     * ارسال همه محصولات
     */
    public function send_all_products($chat_id = null)
    {
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1
        ));

        if (empty($products)) {
            return array(
                'success' => false,
                'message' => 'هیچ محصولی برای ارسال پیدا نشد'
            );
        }

        $sent_count = 0;
        $errors = array();

        foreach ($products as $product) {
            $result = $this->send_product($product->get_id(), $chat_id);

            if ($result['success']) {
                $sent_count++;
            } else {
                $errors[] = "خطا در ارسال محصول {$product->get_name()}: " . $result['message'];
            }

            // تاخیر کوتاه برای جلوگیری از محدودیت نرخ
            sleep(1);
        }

        $message = "{$sent_count} محصول با موفقیت ارسال شد";
        if (!empty($errors)) {
            $message .= "\nخطاها:\n" . implode("\n", $errors);
        }

        return array(
            'success' => $sent_count > 0,
            'message' => $message
        );
    }

    /**
     * ارسال همه نوشته‌ها
     */
    public function send_all_posts($chat_id = null)
    {
        $posts = get_posts(array(
            'post_status' => 'publish',
            'post_type' => 'post',
            'numberposts' => -1
        ));

        if (empty($posts)) {
            return array(
                'success' => false,
                'message' => 'هیچ نوشته‌ای برای ارسال پیدا نشد'
            );
        }

        $sent_count = 0;
        $errors = array();

        foreach ($posts as $post) {
            $result = $this->send_post($post->ID, $chat_id);

            if ($result['success']) {
                $sent_count++;
            } else {
                $errors[] = "خطا در ارسال نوشته {$post->post_title}: " . $result['message'];
            }

            // تاخیر کوتاه برای جلوگیری از محدودیت نرخ
            sleep(1);
        }

        $message = "{$sent_count} نوشته با موفقیت ارسال شد";
        if (!empty($errors)) {
            $message .= "\nخطاها:\n" . implode("\n", $errors);
        }

        return array(
            'success' => $sent_count > 0,
            'message' => $message
        );
    }



    /**
     * ارسال تک محصول برنامه‌ریزی شده
     */
    public function send_single_product_scheduled($product_id)
    {
        $this->send_product($product_id);
    }

    /**
     * ارسال تک نوشته برنامه‌ریزی شده
     */
    public function send_single_post_scheduled($post_id)
    {
        $this->send_post($post_id);
    }

    /**
     * فرمت کردن پیام محصول - تابع قدیمی (برای سازگاری با بخش‌های دیگر)
     */
    public function format_product_message($product)
    {
        $name = $product->get_name();
        $price = $product->get_price();
        $sale_price = $product->get_sale_price();
        $description = $product->get_description();
        $short_description = $product->get_short_description();
        $url = $product->get_permalink();
        $stock_status = $product->get_stock_status();
        $sku = $product->get_sku();

// فرمت قیمت (با پاک‌سازی entityها و فاصله‌های نامفهوم ولی حفظ تگ‌های HTML مثل <s>)
$price_text = '';
if ($sale_price && $sale_price < $price) {
    $price_html_regular = wc_price($price);
    $price_html_sale = wc_price($sale_price);

    // تبدیل entityها و حذف فاصله‌های نامفهوم (ولی حفظ تگ‌های HTML مانند <s>)
    $price_html_regular = html_entity_decode($price_html_regular, ENT_QUOTES, 'UTF-8');
    $price_html_sale = html_entity_decode($price_html_sale, ENT_QUOTES, 'UTF-8');

    $price_html_regular = preg_replace('/[\x{00A0}\x{200B}]+/u', ' ', $price_html_regular);
    $price_html_sale = preg_replace('/[\x{00A0}\x{200B}]+/u', ' ', $price_html_sale);

    $price_html_regular = preg_replace('/\s+/u', ' ', $price_html_regular);
    $price_html_sale = preg_replace('/\s+/u', ' ', $price_html_sale);

    $price_text = "💰 قیمت: <s>" . $price_html_regular . "</s> " . $price_html_sale;
} elseif ($price) {
    $price_html = wc_price($price);
    $price_html = html_entity_decode($price_html, ENT_QUOTES, 'UTF-8');
    $price_html = preg_replace('/[\x{00A0}\x{200B}]+/u', ' ', $price_html);
    $price_html = preg_replace('/\s+/u', ' ', $price_html);
    $price_text = "💰 قیمت: " . $price_html;
}


        // وضعیت موجودی
        $stock_text = '';
        switch ($stock_status) {
            case 'instock':
                $stock_text = "✅ موجود";
                break;
            case 'outofstock':
                $stock_text = "❌ ناموجود";
                break;
            case 'onbackorder':
                $stock_text = "⏳ پیش‌سفارش";
                break;
        }

        $message = "🛍️ <b>{$name}</b>\n\n";

        if ($sku) {
            $message .= "🏷️ کد محصول: {$sku}\n";
        }

        if ($price_text) {
            $message .= "{$price_text}\n";
        }

        if ($stock_text) {
            $message .= "📦 وضعیت: {$stock_text}\n";
        }

        $message .= "\n";

        if ($short_description) {
            $message .= "📝 توضیح کوتاه:\n" . wp_strip_all_tags($short_description) . "\n\n";
        } elseif ($description) {
            $description_text = wp_strip_all_tags($description);
            if (strlen($description_text) > 200) {
                $description_text = substr($description_text, 0, 200) . '...';
            }
            $message .= "📝 توضیحات:\n" . $description_text . "\n\n";
        }

        $message .= "🔗 <a href='{$url}'>مشاهده و خرید محصول</a>";

        // اضافه کردن امضای پیام‌ها
        $signature = get_option('telegram_sender_message_signature', '');
        if (!empty($signature)) {
            $message .= "\n\n" . "━━━━━━━━━━━━━━━━━━━\n" . trim($signature);
        }

        // اطمینان از اینکه طول پیام از حداکثر مجاز تلگرام تجاوز نکند (1024 کاراکتر برای کپشن)
        if (strlen($message) > 1024) {
            $message = mb_substr($message, 0, 1020, 'UTF-8') . '...'; // 1020 + ... = 1023
        }

        return $message;
    }

    /**
     * فرمت کردن پیام نوشته
     */
    public function format_post_message($post)
    {
        $title = $post->post_title;
        $content = $post->post_content;
        $excerpt = $post->post_excerpt;
        $url = get_permalink($post->ID);
        $author = get_the_author_meta('display_name', $post->post_author);
        $date = get_the_date('Y/m/d', $post->ID);

        $message = "📰 <b>{$title}</b>\n\n";

        $message .= "👤 نویسنده: {$author}\n";
        $message .= "📅 تاریخ انتشار: {$date}\n\n";

        if ($excerpt) {
            $content_text = wp_strip_all_tags($excerpt);
        } else {
            $content_text = wp_strip_all_tags($content);
        }

        if (strlen($content_text) > 300) {
            $content_text = substr($content_text, 0, 300) . '...';
        }

        $message .= "📝 خلاصه:\n" . $content_text . "\n\n";
        $message .= "🔗 <a href='{$url}'>ادامه مطلب</a>";

        // اضافه کردن امضای پیام‌ها
        $signature = get_option('telegram_sender_message_signature', '');
        if (!empty($signature)) {
            $message .= "\n\n" . "━━━━━━━━━━━━━━━━━━━\n" . trim($signature);
        }

        return $message;
    }

    /**
     * تست اتصال به تلگرام
     */
    public function test_connection()
    {
        if (empty($this->bot_token)) {
            return array(
                'success' => false,
                'message' => 'توکن ربات تنظیم نشده است'
            );
        }

        $response = $this->make_request('getMe');

        if ($response['success']) {
            $bot_info = $response['data'];
            return array(
                'success' => true,
                'message' => "اتصال موفق! نام ربات: " . $bot_info['first_name'],
                'data' => $bot_info
            );
        } else {
            return array(
                'success' => false,
                'message' => 'خطا در اتصال: ' . $response['message']
            );
        }
    }

    
    /**
 * ارسال درخواست - با پشتیبانی از پروکسی
 */
private function make_request($method, $params = array())
{
    if (empty($this->bot_token)) {
        return array(
            'success' => false,
            'message' => 'توکن ربات تنظیم نشده است'
        );
    }

    // اگر پروکسی تنظیم شده، از آن استفاده کن
    if (!empty($this->proxy_url) && !empty($this->proxy_secret)) {
        return $this->make_proxy_request($method, $params);
    }

    // اتصال مستقیم (کد قبلی)
    $url = 'https://api.telegram.org/bot' . $this->bot_token . '/' . $method;

    $args = array(
        'method' => 'POST',
        'timeout' => 30,
        'sslverify' => false,
        'headers' => array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        ),
        'body' => json_encode($params)
    );

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => $response->get_error_message()
        );
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        error_log("Telegram API HTTP Error ({$response_code}): {$response_body}");
        return array(
            'success' => false,
            'message' => "خطای HTTP: {$response_code}"
        );
    }

    $data = json_decode($response_body, true);

    if (!$data) {
        return array(
            'success' => false,
            'message' => 'خطا در تجزیه پاسخ JSON'
        );
    }

    if (!$data['ok']) {
        $error_message = isset($data['description']) ? $data['description'] : 'خطای نامشخص';
        return array(
            'success' => false,
            'message' => $error_message
        );
    }

    return array(
        'success' => true,
        'data' => $data['result']
    );
}

/**
 * ارسال درخواست به پروکسی سرور
 */
private function make_proxy_request($method, $params = array())
{
    $proxy_data = array(
        'method' => $method,
        'params' => $params,
        'bot_token' => $this->bot_token,
        'secret_key' => $this->proxy_secret
    );

    $args = array(
        'method' => 'POST',
        'timeout' => 45,
        'sslverify' => false,
        'headers' => array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'TelegramSender/' . get_bloginfo('version')
        ),
        'body' => json_encode($proxy_data)
    );

    $response = wp_remote_request($this->proxy_url, $args);

    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => 'خطا در اتصال به پروکسی: ' . $response->get_error_message()
        );
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        return array(
            'success' => false,
            'message' => "خطای پروکسی HTTP: {$response_code}"
        );
    }

    $data = json_decode($response_body, true);

    if (!$data) {
        return array(
            'success' => false,
            'message' => 'خطا در تجزیه پاسخ پروکسی'
        );
    }

    if (isset($data['error'])) {
        return array(
            'success' => false,
            'message' => 'خطای پروکسی: ' . $data['error']
        );
    }

    if (!isset($data['ok']) || !$data['ok']) {
        $error_message = isset($data['description']) ? $data['description'] : 'خطای نامشخص تلگرام';
        return array(
            'success' => false,
            'message' => $error_message
        );
    }

    return array(
        'success' => true,
        'data' => $data['result']
    );
}

    /**
     * اعتبارسنجی توکن
     */
    public function validate_token($token)
    {
        if (empty($token) || !preg_match('/^\d+:[A-Za-z0-9_-]{35}$/', $token)) {
            return false;
        }

        return true;
    }

    /**
     * اعتبارسنجی چت آیدی
     */
    public function validate_chat_id($chat_id)
    {
        $chat_id = trim($chat_id);

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
 * ساخت دکمه‌های شیشه‌ای برای محصولات
 */
private function create_product_keyboard($product)
{
    // اگر نمایش دکمه‌ها غیرفعال باشد، هیچ دکمه‌ای برنگردان
    $show_buttons = get_option('telegram_sender_show_inline_buttons', '1');
    if ($show_buttons !== '1') {
        return null;
    }

    $buttons = array();
    
    // دکمه خرید محصول
    $buy_button_text = get_option('telegram_sender_buy_button_text', 'خرید محصول');
    $product_url = get_permalink($product->get_id());
    
    $buttons[] = array(
        'text' => $buy_button_text,
        'url' => $product_url
    );
    
    // دکمه پشتیبانی
    $support_button_text = get_option('telegram_sender_support_button_text', 'پشتیبانی');
    $support_button_link = get_option('telegram_sender_support_button_link', '');
    
    if (!empty($support_button_link)) {
        $buttons[] = array(
            'text' => $support_button_text,
            'url' => $support_button_link
        );
    }
    
    // اگر دکمه‌ای وجود دارد، keyboard را برگردان
    if (!empty($buttons)) {
        return json_encode(array(
            'inline_keyboard' => array($buttons)
        ));
    }
    
    return null;
}

    /**
     * ارسال محصولات با فاصله زمانی، در بازه زمانی روزانه مشخص
     */
    public function send_products_with_interval($interval_minutes, $start_time = '', $end_time = '', $exclude_out_of_stock = false, $only_unsent = false, $only_price_updated = false)
    {
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1
        ));

        if (empty($products)) {
            return;
        }

        // اعمال فیلترها
        $filtered_ids = array();
        foreach ($products as $product) {
            $product_id = $product->get_id();

            if ($exclude_out_of_stock && !$product->is_in_stock()) {
                continue;
            }

            if ($only_unsent) {
                $send_count = intval(get_post_meta($product_id, '_telegram_send_count', true));
                if ($send_count > 0) {
                    continue;
                }
            }

            if ($only_price_updated) {
                $last_sent_price = get_post_meta($product_id, '_telegram_last_sent_price', true);
                if ($last_sent_price === '' || $last_sent_price === null) {
                    // اگر تاکنون قیمت ارسال نشده، در این حالت عبور نکن
                    continue;
                }
                $current_price = $product->get_price();
                if ((string)$current_price === (string)$last_sent_price) {
                    continue;
                }
            }

            $filtered_ids[] = $product_id;
        }

        if (empty($filtered_ids)) {
            return;
        }

        $this->schedule_sequence_with_window(
            $filtered_ids,
            'telegram_sender_single_product_send',
            $interval_minutes,
            $start_time,
            $end_time
        );

        // ثبت رویداد برای ارسال تک محصول
        if (!wp_next_scheduled('telegram_sender_single_product_send')) {
            add_action('telegram_sender_single_product_send', array($this, 'send_single_product_scheduled'));
        }
    }

    /**
     * ارسال نوشته‌ها با فاصله زمانی، در بازه زمانی روزانه مشخص
     */
    public function send_posts_with_interval($interval_minutes, $start_time = '', $end_time = '')
    {
        $posts = get_posts(array(
            'post_status' => 'publish',
            'post_type' => 'post',
            'numberposts' => -1
        ));

        if (empty($posts)) {
            return;
        }

        $this->schedule_sequence_with_window(
            array_map(function($p){ return $p->ID; }, $posts),
            'telegram_sender_single_post_send',
            $interval_minutes,
            $start_time,
            $end_time
        );

        // ثبت رویداد برای ارسال تک نوشته
        if (!wp_next_scheduled('telegram_sender_single_post_send')) {
            add_action('telegram_sender_single_post_send', array($this, 'send_single_post_scheduled'));
        }
    }

    /**
     * زمان‌بندی توالی ارسال با درنظر گرفتن بازه ساعتی روزانه و فاصله
     */
    private function schedule_sequence_with_window($ids, $hook, $interval_minutes, $start_time, $end_time)
    {
        $interval_seconds = max(1, intval($interval_minutes)) * 60;
        $now = current_time('timestamp');

        // پارس ساعت شروع/پایان به ثانیه از ابتدای روز
        list($start_h, $start_m) = $this->parse_hhmm($start_time ?: '08:00');
        list($end_h, $end_m) = $this->parse_hhmm($end_time ?: '22:00');

        $start_seconds = $start_h * 3600 + $start_m * 60;
        $end_seconds = $end_h * 3600 + $end_m * 60;

        // تابع کمکی: محاسبه timestamp شروع پنجره برای روزی که شامل $base_ts باشد
        $get_day_window = function($base_ts) use ($start_seconds, $end_seconds) {
            $day_start = strtotime(date('Y-m-d 00:00:00', $base_ts));
            return array($day_start + $start_seconds, $day_start + $end_seconds);
        };

        // اولین زمان ارسال: نزدیک‌ترین اسلات در داخل پنجره جاری یا بعدی
        list($win_start, $win_end) = $get_day_window($now);
        $send_time = $now;

        if ($send_time < $win_start) {
            $send_time = $win_start;
        } elseif ($send_time > $win_end) {
            // حرکت به روز بعد
            $tomorrow = $now + 86400;
            list($win_start, $win_end) = $get_day_window($tomorrow);
            $send_time = $win_start;
        }

        foreach ($ids as $index => $id) {
            // اگر خارج از پنجره شدیم، به شروع پنجره روز بعد برویم
            if ($send_time > $win_end) {
                $next_day = $send_time + 86400;
                list($win_start, $win_end) = $get_day_window($next_day);
                $send_time = $win_start;
            }

            wp_schedule_single_event($send_time, $hook, array($id));
            $send_time += $interval_seconds;
        }
    }

    private function parse_hhmm($hhmm)
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) {
            $h = max(0, min(23, intval($m[1])));
            $mm = max(0, min(59, intval($m[2])));
            return array($h, $mm);
        }
        return array(8, 0);
    }
}