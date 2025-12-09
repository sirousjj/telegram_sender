<?php
/**
 * فیلدهای سفارشی محصول برای تلگرام
 * 
 * @package TelegramSender
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class TelegramSender_Product_Fields {
    
    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_telegram_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_telegram_fields'));
    }
    
    /**
     * اضافه کردن فیلدهای تلگرام به صفحه محصول
     */
    public function add_telegram_fields() {
        global $post;
        
        echo '<div class="options_group">';
        
        // عنوان بخش
        echo '<h4 style="padding: 10px 12px; margin: 0; background: #f9f9f9; border-left: 4px solid #0073aa;">تنظیمات تلگرام</h4>';
        
        // فیلد توضیحات تلگرام
        woocommerce_wp_textarea_input(
            array(
                'id' => '_telegram_description',
                'label' => 'توضیحات محصول برای تلگرام',
                'placeholder' => 'توضیحات ویژه این محصول برای نمایش در کانال تلگرام...',
                'description' => 'این متن در پیام تلگرام به جای توضیحات کوتاه محصول نمایش داده می‌شود',
                'desc_tip' => true,
                'rows' => 4,
                'cols' => 20,
                'value' => get_post_meta($post->ID, '_telegram_description', true)
            )
        );
        
        // فیلد هشتگ‌ها
        woocommerce_wp_text_input(
            array(
                'id' => '_telegram_hashtags',
                'label' => 'هشتگ‌های محصول',
                'placeholder' => '#کفش #ورزشی #نایک',
                'description' => 'هشتگ‌ها را با فاصله از هم جدا کنید. مثال: #کفش #ورزشی #تخفیف',
                'desc_tip' => true,
                'value' => get_post_meta($post->ID, '_telegram_hashtags', true)
            )
        );
        
        echo '</div>';
    }
    
    /**
     * ذخیره فیلدهای تلگرام
     */
    public function save_telegram_fields($post_id) {
        
        // توضیحات تلگرام
        if (isset($_POST['_telegram_description'])) {
            $telegram_description = sanitize_textarea_field($_POST['_telegram_description']);
            update_post_meta($post_id, '_telegram_description', $telegram_description);
        }
        
        // هشتگ‌ها
        if (isset($_POST['_telegram_hashtags'])) {
            $telegram_hashtags = sanitize_text_field($_POST['_telegram_hashtags']);
            // اطمینان از اینکه هر هشتگ با # شروع می‌شود
            $hashtags = explode(' ', $telegram_hashtags);
            $formatted_hashtags = array();
            
            foreach ($hashtags as $hashtag) {
                $hashtag = trim($hashtag);
                if (!empty($hashtag)) {
                    if (substr($hashtag, 0, 1) !== '#') {
                        $hashtag = '#' . $hashtag;
                    }
                    $formatted_hashtags[] = $hashtag;
                }
            }
            
            $final_hashtags = implode(' ', $formatted_hashtags);
            update_post_meta($post_id, '_telegram_hashtags', $final_hashtags);
        }
    }
}

// راه‌اندازی کلاس
new TelegramSender_Product_Fields();
// اضافه کردن استایل سفارشی
add_action('admin_head', function() {
    ?>
    <style>
    .woocommerce_options_panel .options_group h4 {
        font-size: 14px;
        font-weight: 600;
        color: #0073aa;
    }
    
    .woocommerce_options_panel #_telegram_description {
        min-height: 80px;
    }
    
    .woocommerce_options_panel .form-field._telegram_description_field,
    .woocommerce_options_panel .form-field._telegram_hashtags_field {
        border-left: 3px solid #0073aa;
        padding-left: 15px;
        background: #f8f9fa;
    }
    </style>
    <?php
});