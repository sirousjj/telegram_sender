<?php

/**
 * صفحه تنظیمات افزونه
 * 
 * @package TelegramSender
 * @author اصغر معینی <as.moini@gmail.com>
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// پردازش فرم تنظیمات
if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'telegram_sender_settings')) {

    $bot_token = sanitize_text_field($_POST['bot_token']);
    $chat_ids_string = sanitize_textarea_field($_POST['chat_ids']);
    $send_interval = intval($_POST['send_interval']);
    $message_signature = sanitize_textarea_field($_POST['message_signature']); // فیلد جدید
    // تنظیمات دکمه‌ها
$buy_button_text = sanitize_text_field($_POST['buy_button_text']);
$support_button_text = sanitize_text_field($_POST['support_button_text']);  
$support_button_link = sanitize_url($_POST['support_button_link']);
// تنظیمات جدید پروکسی
$proxy_url = sanitize_url($_POST['proxy_url']);
$proxy_secret = sanitize_text_field($_POST['proxy_secret']);
// نمایش دکمه‌های شیشه‌ای
$show_inline_buttons = isset($_POST['show_inline_buttons']) ? '1' : '0';
    // اعتبارسنجی توکن
    if (!empty($bot_token) && !telegram_sender_validate_token($bot_token)) {
        telegram_sender_admin_notice_error('فرمت توکن ربات نامعتبر است');
    } else {
        update_option('telegram_sender_bot_token', $bot_token);

        // پردازش چت آیدی‌ها
        $chat_ids = telegram_sender_string_to_chat_ids($chat_ids_string);
        $valid_chat_ids = array();

        foreach ($chat_ids as $chat_id) {
            if (telegram_sender_validate_chat_id($chat_id)) {
                $valid_chat_ids[] = $chat_id;
            }
        }

        $chat_ids_string = implode("\n", $valid_chat_ids); // تبدیل آرایه به string با \n
        update_option('telegram_sender_chat_ids', $chat_ids_string);
        update_option('telegram_sender_send_interval', max(1, $send_interval));
        
        // ذخیره امضای پیام‌ها
        update_option('telegram_sender_message_signature', $message_signature);
        // ذخیره تنظیمات دکمه‌ها
update_option('telegram_sender_buy_button_text', $buy_button_text);
update_option('telegram_sender_support_button_text', $support_button_text);
update_option('telegram_sender_support_button_link', $support_button_link);
// ذخیره تنظیمات پروکسی
update_option('telegram_sender_proxy_url', $proxy_url);
update_option('telegram_sender_proxy_secret', $proxy_secret);
// ذخیره نمایش دکمه‌های شیشه‌ای
update_option('telegram_sender_show_inline_buttons', $show_inline_buttons);
        telegram_sender_admin_notice_success('تنظیمات با موفقیت ذخیره شد');
    }
}

// تست اتصال
if (isset($_POST['test_connection']) && wp_verify_nonce($_POST['_wpnonce'], 'telegram_sender_settings')) {
    $telegram_api = new TelegramSender_API();
    $test_result = $telegram_api->test_connection();

    if ($test_result['success']) {
        telegram_sender_admin_notice_success($test_result['message']);
    } else {
        telegram_sender_admin_notice_error($test_result['message']);
    }
}

// گرفتن تنظیمات فعلی
$bot_token = get_option('telegram_sender_bot_token', '');
$chat_ids = get_option('telegram_sender_chat_ids', array());
$chat_ids_string = telegram_sender_chat_ids_to_string($chat_ids);
$send_interval = get_option('telegram_sender_send_interval', 5);
$message_signature = get_option('telegram_sender_message_signature', ''); // گرفتن امضای پیام‌ها
$buy_button_text = get_option('telegram_sender_buy_button_text', 'خرید محصول');
$support_button_text = get_option('telegram_sender_support_button_text', 'پشتیبانی');
$support_button_link = get_option('telegram_sender_support_button_link', '');
$proxy_url = get_option('telegram_sender_proxy_url', '');
$proxy_secret = get_option('telegram_sender_proxy_secret', '');
$show_inline_buttons = get_option('telegram_sender_show_inline_buttons', '1');
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-admin-settings"></span>
        تنظیمات ارسال به تلگرام
    </h1>

    <div class="telegram-sender-settings">
        <div class="row">
            <div class="col-main">
                <div class="postbox">
                    <h2 class="hndle">
                        <span>تنظیمات اصلی</span>
                    </h2>
                    <div class="inside">
                        <form method="post" action="">
                            <?php wp_nonce_field('telegram_sender_settings'); ?>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="bot_token">توکن ربات تلگرام</label>
                                    </th>
                                    <td>
                                        <input type="text" id="bot_token" name="bot_token"
                                            value="<?php echo esc_attr($bot_token); ?>" class="regular-text"
                                            placeholder="123456789:ABC-DEF1234567890abcdef" />
                                        <p class="description">
                                            توکن ربات خود را از <a href="https://t.me/BotFather"
                                                target="_blank">@BotFather</a> دریافت کنید
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="chat_ids">چت آیدی‌ها</label>
                                    </th>
                                    <td>
                                        <textarea id="chat_ids" name="chat_ids" rows="5" class="large-text"
                                            placeholder="@channel_username&#10;-1234567890&#10;987654321"><?php echo esc_textarea($chat_ids_string); ?></textarea>
                                        <p class="description">
                                            هر چت آیدی را در خط جداگانه وارد کنید. می‌توانید از یوزرنیم کانال (@channel)
                                            یا شناسه عددی استفاده کنید
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="send_interval">فاصله زمانی ارسال (دقیقه)</label>
                                    </th>
                                    <td>
                                        <input type="number" id="send_interval" name="send_interval"
                                            value="<?php echo esc_attr($send_interval); ?>" min="1" max="60"
                                            class="small-text" />
                                        <p class="description">
                                            فاصله زمانی بین ارسال پیام‌ها در حالت ارسال انبوه (حداقل 1 دقیقه)
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="message_signature">امضای پیام‌ها</label>
                                    </th>
                                    <td>
                                        <textarea id="message_signature" name="message_signature" rows="4" class="large-text"
                                            placeholder="📞 تماس با ما: 09123456789&#10;💬 پشتیبانی: @support&#10;📢 کانال ما: @mychannel"><?php echo esc_textarea($message_signature); ?></textarea>
                                        <p class="description">
                                            متنی که در انتهای تمام پیام‌ها اضافه می‌شود. می‌توانید از ایموجی، شماره تماس، آیدی کانال یا متن دلخواه استفاده کنید
                                        </p>
                                        <?php if (!empty($message_signature)): ?>
                                        <div class="signature-preview">
                                            <strong>پیش‌نمایش امضا:</strong>
                                            <div class="preview-content"><?php echo nl2br(esc_html($message_signature)); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <!-- بخش تنظیمات پروکسی -->
<tr style="border-top: 2px solid #ddd;">
    <th colspan="2" style="background: #f9f9f9; padding: 15px;">
        <h3 style="margin: 0; color: #333;">تنظیمات پروکسی (برای سرورهای ایرانی)</h3>
        <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">
            برای استفاده در سرورهای ایرانی که دسترسی مستقیم به تلگرام ندارند
        </p>
    </th>
</tr>

<tr>
    <th scope="row">
        <label for="proxy_url">آدرس پروکسی سرور</label>
    </th>
    <td>
        <input type="url" id="proxy_url" name="proxy_url"
            value="<?php echo esc_attr($proxy_url); ?>" class="large-text" />
        <p class="description">
            آدرس کامل فایل پروکسی روی سرور خارجی شما
        </p>
    </td>
</tr>

<tr>
    <th scope="row">
        <label for="proxy_secret">کلید امنیتی پروکسی</label>
    </th>
    <td>
        <input type="password" id="proxy_secret" name="proxy_secret"
            value="<?php echo esc_attr($proxy_secret); ?>" class="regular-text" />
        <button type="button" id="toggle_proxy_secret" class="button">نمایش</button>
        <p class="description"></p>
    </td>
</tr>
                          <!-- بخش تنظیمات دکمه‌ها -->
                                <tr style="border-top: 2px solid #ddd;">
                                    <th colspan="2" style="background: #f1f8ff; padding: 15px;">
                                        <h3 style="margin: 0; color: #333;">🔘 تنظیمات دکمه‌های پیام</h3>
                                        <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">
                                            دکمه‌های شیشه‌ای که زیر هر پیام محصول نمایش داده می‌شوند
                                        </p>
                                    </th>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="show_inline_buttons">نمایش دکمه های شیشه ای</label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="show_inline_buttons" name="show_inline_buttons" value="1" <?php checked($show_inline_buttons, '1'); ?> />
                                            نمایش دکمه های شیشه ای
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="buy_button_text">متن دکمه خرید</label>
                                    </th>
                                    <td>
                                        <input type="text" id="buy_button_text" name="buy_button_text"
                                            value="<?php echo esc_attr($buy_button_text); ?>" class="regular-text"
                                            placeholder="خرید محصول" />
                                        <p class="description">
                                            متنی که روی دکمه خرید نمایش داده می‌شود
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="support_button_text">متن دکمه پشتیبانی</label>
                                    </th>
                                    <td>
                                        <input type="text" id="support_button_text" name="support_button_text"
                                            value="<?php echo esc_attr($support_button_text); ?>" class="regular-text"
                                            placeholder="پشتیبانی" />
                                        <p class="description">
                                            متنی که روی دکمه پشتیبانی نمایش داده می‌شود
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="support_button_link">لینک دکمه پشتیبانی</label>
                                    </th>
                                    <td>
                                        <input type="url" id="support_button_link" name="support_button_link"
                                            value="<?php echo esc_attr($support_button_link); ?>" class="large-text"
                                            placeholder="https://t.me/support یا https://wa.me/989123456789" />
                                        <p class="description">
                                            لینک پشتیبانی (تلگرام، واتساپ، یا هر لینک دیگری)
                                            <br><strong>مثال‌ها:</strong>
                                            <br>• تلگرام: <code>https://t.me/your_support</code>
                                            <br>• واتساپ: <code>https://wa.me/989123456789</code>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <div class="submit-wrap">
                                <?php submit_button('ذخیره تنظیمات', 'primary', 'submit', false); ?>
                                <?php submit_button('تست اتصال', 'secondary', 'test_connection', false); ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sidebar">
                <!-- راهنمای تنظیمات -->
                <div class="postbox">
                    <h3 class="hndle">
                        <span>راهنمای تنظیمات</span>
                    </h3>
                    <div class="inside">
                        <h4>🤖 ایجاد ربات تلگرام</h4>
                        <ol>
                            <li>وارد تلگرام شوید و <a href="https://t.me/BotFather" target="_blank">@BotFather</a> را
                                پیدا کنید</li>
                            <li>دستور <code>/newbot</code> را ارسال کنید</li>
                            <li>نام و یوزرنیم ربات را انتخاب کنید</li>
                            <li>توکن دریافتی را در بالا وارد کنید</li>
                        </ol>

                        <hr>

                        <h4>🆔 پیدا کردن چت آیدی</h4>
                        <p><strong>برای کانال:</strong></p>
                        <ul>
                            <li>اگر کانال عمومی است: <code>@channel_username</code></li>
                            <li>اگر کانال خصوصی است: از <a href="https://t.me/userinfobot"
                                    target="_blank">@userinfobot</a> استفاده کنید</li>
                        </ul>

                        <p><strong>برای چت شخصی:</strong></p>
                        <ul>
                            <li>ربات را به چت اضافه کنید</li>
                            <li>پیامی ارسال کنید</li>
                            <li>از <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a> برای گرفتن آیدی
                                استفاده کنید</li>
                        </ul>

                        <hr>

                        <h4>✍️ راهنمای امضای پیام‌ها</h4>
                        <p><strong>مثال‌های کاربردی:</strong></p>
                        <ul>
                            <li><code>📞 تماس: 09123456789</code></li>
                            <li><code>💬 پشتیبانی: @support</code></li>
                            <li><code>📢 کانال: @mychannel</code></li>
                            <li><code>🌐 سایت: example.com</code></li>
                        </ul>
                        <p class="description">
                            این متن در انتهای تمام پیام‌های ارسالی (محصولات و نوشته‌ها) اضافه خواهد شد.
                        </p>
                    </div>
                </div>

                <!-- اطلاعات سیستم -->
                <div class="postbox">
                    <h3 class="hndle">
                        <span>اطلاعات سیستم</span>
                    </h3>
                    <div class="inside">
                        <?php
                        $server_info = telegram_sender_get_server_info();
                        $has_internet = telegram_sender_check_internet_connection();
                        ?>

                        <table class="widefat">
                            <tr>
                                <td><strong>نسخه PHP:</strong></td>
                                <td><?php echo esc_html($server_info['php_version']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>نسخه وردپرس:</strong></td>
                                <td><?php echo esc_html($server_info['wordpress_version']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>نسخه ووکامرس:</strong></td>
                                <td><?php echo esc_html($server_info['woocommerce_version']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>cURL:</strong></td>
                                <td>
                                    <?php if ($server_info['curl_enabled']): ?>
                                    <span class="status-enabled">✅ فعال</span>
                                    <?php else: ?>
                                    <span class="status-disabled">❌ غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>OpenSSL:</strong></td>
                                <td>
                                    <?php if ($server_info['openssl_enabled']): ?>
                                    <span class="status-enabled">✅ فعال</span>
                                    <?php else: ?>
                                    <span class="status-disabled">❌ غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>اتصال اینترنت:</strong></td>
                                <td>
                                    <?php if ($has_internet): ?>
                                    <span class="status-enabled">✅ برقرار</span>
                                    <?php else: ?>
                                    <span class="status-disabled">❌ قطع</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>

                        <?php if (!$server_info['curl_enabled'] || !$server_info['openssl_enabled'] || !$has_internet): ?>
                        <div class="notice notice-warning inline">
                            <p>
                                <strong>هشدار:</strong> برای عملکرد صحیح افزونه، cURL و OpenSSL باید فعال و اتصال
                                اینترنت برقرار باشد.
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- آمار ارسال -->
                <?php
                $database = new TelegramSender_Database();
                $stats = $database->get_send_statistics();
                ?>
                <div class="postbox">
                    <h3 class="hndle">
                        <span>آمار ارسال</span>
                    </h3>
                    <div class="inside">
                        <table class="widefat">
                            <tr>
                                <td><strong>کل ارسال‌ها:</strong></td>
                                <td><?php echo number_format($stats['total_sends']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>ارسال موفق:</strong></td>
                                <td class="success-count"><?php echo number_format($stats['successful_sends']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>ارسال ناموفق:</strong></td>
                                <td class="error-count"><?php echo number_format($stats['failed_sends']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>نرخ موفقیت:</strong></td>
                                <td>
                                    <span class="success-rate"><?php echo $stats['success_rate']; ?>%</span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>ارسال امروز:</strong></td>
                                <td><?php echo number_format($stats['today_sends']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>ارسال این هفته:</strong></td>
                                <td><?php echo number_format($stats['week_sends']); ?></td>
                            </tr>
                        </table>

                        <?php if (!empty($stats['top_products'])): ?>
                        <h4>محصولات پربازدید:</h4>
                        <ul class="top-products-list">
                            <?php foreach ($stats['top_products'] as $product): ?>
                            <li>
                                <?php echo esc_html($product->name); ?>
                                <span class="send-count">(<?php echo $product->send_count; ?> بار)</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
jQuery(document).ready(function($) {
    // تست اتصال در صفحه تنظیمات
    $('input[name="test_connection"]').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalValue = $button.val();
        
        $button.val('در حال تست...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'telegram_test_connection',
                nonce: '<?php echo wp_create_nonce('telegram_sender_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // نمایش پیام موفقیت
                    $('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>')
                        .insertAfter('.wrap h1');
                } else {
                    // نمایش پیام خطا
                    $('<div class="notice notice-error is-dismissible"><p>خطا: ' + response.data + '</p></div>')
                        .insertAfter('.wrap h1');
                }
                
                // اسکرول به بالا
                $('html, body').animate({scrollTop: 0}, 300);
            },
            error: function() {
                $('<div class="notice notice-error is-dismissible"><p>خطا در برقراری ارتباط با سرور</p></div>')
                    .insertAfter('.wrap h1');
                $('html, body').animate({scrollTop: 0}, 300);
            },
            complete: function() {
                $button.val(originalValue).prop('disabled', false);
                
                // حذف خودکار نوتیفیکیشن پس از 5 ثانیه
                setTimeout(function() {
                    $('.notice').fadeOut();
                }, 5000);
            }
        });
    });
    
    // پیش‌نمایش زنده امضای پیام‌ها
    $('#message_signature').on('input', function() {
        var signatureText = $(this).val();
        var $preview = $('.signature-preview');
        
        if (signatureText.trim() !== '') {
            if ($preview.length === 0) {
                $(this).closest('td').append('<div class="signature-preview"><strong>پیش‌نمایش امضا:</strong><div class="preview-content"></div></div>');
                $preview = $('.signature-preview');
            }
            
            // تبدیل \n به <br> برای نمایش صحیح
            var htmlText = $('<div>').text(signatureText).html().replace(/\n/g, '<br>');
            $preview.find('.preview-content').html(htmlText);
            $preview.show();
        } else {
            $preview.hide();
        }
    });
});
</script>
</div>

<style>
.telegram-sender-settings .row {
    display: flex;
    gap: 20px;
}

.telegram-sender-settings .col-main {
    flex: 2;
}

.telegram-sender-settings .col-sidebar {
    flex: 1;
}

.submit-wrap {
    display: flex;
    gap: 10px;
    align-items: center;
}

.status-enabled {
    color: #00a32a;
}

.status-disabled {
    color: #d63638;
}

.success-count {
    color: #00a32a;
    font-weight: bold;
}

.error-count {
    color: #d63638;
    font-weight: bold;
}

.success-rate {
    font-weight: bold;
}

.top-products-list {
    margin: 10px 0;
}

.top-products-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    border-bottom: 1px solid #eee;
}

.send-count {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

/* استایل پیش‌نمایش امضا */
.signature-preview {
    margin-top: 10px;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.signature-preview strong {
    display: block;
    margin-bottom: 5px;
    color: #333;
}

.preview-content {
    background: #fff;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 3px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.4;
    color: #444;
}

@media (max-width: 768px) {
    .telegram-sender-settings .row {
        flex-direction: column;
    }

    .submit-wrap {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
<script>
jQuery(document).ready(function($) {
    $('#test-connection').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('در حال تست...').prop('disabled', true);
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'telegram_test_connection',
                nonce: '<?php echo wp_create_nonce('telegram_sender_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data);
                } else {
                    alert('❌ خطا: ' + response.data);
                }
            },
            error: function() {
                alert('❌ خطا در برقراری ارتباط');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
});
</script>