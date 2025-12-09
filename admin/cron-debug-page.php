<?php
/**
 * صفحه دیباگ کرون‌های برنامه‌ریزی شده
 * 
 * @package TelegramSender
 */

if (!defined('ABSPATH')) {
    exit;
}

// دریافت تمام کرون‌های برنامه‌ریزی شده
$crons = _get_cron_array();
$telegram_crons = array();

foreach ($crons as $timestamp => $cron) {
    foreach ($cron as $hook => $details) {
        if (strpos($hook, 'telegram_sender') !== false) {
            $telegram_crons[] = array(
                'hook' => $hook,
                'timestamp' => $timestamp,
                'time_until' => $timestamp - time(),
                'args' => $details
            );
        }
    }
}

// دریافت آخرین خطاهای کرون
$cron_errors = get_option('telegram_sender_cron_errors', array());
?>

<div class="wrap">
    <h1>🔧 دیباگ کرون‌های تلگرام</h1>

    <!-- وضعیت کلی کرون -->
    <div class="postbox">
        <h2 class="hndle">وضعیت کلی</h2>
        <div class="inside">
            <table class="widefat">
                <tr>
                    <th>وضعیت WP-Cron:</th>
                    <td>
                        <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON): ?>
                            <span style="color: red;">❌ غیرفعال</span>
                        <?php else: ?>
                            <span style="color: green;">✅ فعال</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>زمان فعلی سرور:</th>
                    <td><?php echo current_time('Y-m-d H:i:s'); ?></td>
                </tr>
                <tr>
                    <th>تعداد کرون‌های تلگرام:</th>
                    <td><?php echo count($telegram_crons); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- لیست کرون‌های برنامه‌ریزی شده -->
    <div class="postbox" style="margin-top: 20px;">
        <h2 class="hndle">کرون‌های برنامه‌ریزی شده</h2>
        <div class="inside">
            <?php if (!empty($telegram_crons)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Hook Name</th>
                            <th>زمان اجرا</th>
                            <th>باقیمانده تا اجرا</th>
                            <th>آرگومان‌ها</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($telegram_crons as $cron): ?>
                            <tr>
                                <td><code><?php echo esc_html($cron['hook']); ?></code></td>
                                <td><?php echo date('Y-m-d H:i:s', $cron['timestamp']); ?></td>
                                <td>
                                    <?php
                                    $seconds = $cron['time_until'];
                                    if ($seconds < 0) {
                                        echo '<span style="color: red;">❌ ' . abs($seconds) . ' ثانیه دیر شده</span>';
                                    } else {
                                        $minutes = floor($seconds / 60);
                                        $hours = floor($minutes / 60);
                                        if ($hours > 0) {
                                            echo $hours . ' ساعت و ' . ($minutes % 60) . ' دقیقه';
                                        } elseif ($minutes > 0) {
                                            echo $minutes . ' دقیقه';
                                        } else {
                                            echo $seconds . ' ثانیه';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <details>
                                        <summary>مشاهده</summary>
                                        <pre style="font-size: 11px; max-height: 100px; overflow: auto;"><?php 
                                            print_r($cron['args']); 
                                        ?></pre>
                                    </details>
                                </td>
                                <td>
                                    <button class="button button-small run-cron-now" 
                                            data-hook="<?php echo esc_attr($cron['hook']); ?>"
                                            data-timestamp="<?php echo esc_attr($cron['timestamp']); ?>">
                                        ▶️ اجرای فوری
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    هیچ کرون برنامه‌ریزی‌ای برای تلگرام وجود ندارد.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- خطاهای اخیر -->
    <?php if (!empty($cron_errors)): ?>
    <div class="postbox" style="margin-top: 20px;">
        <h2 class="hndle">خطاهای اخیر کرون</h2>
        <div class="inside">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>زمان</th>
                        <th>Hook</th>
                        <th>پیام خطا</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse(array_slice($cron_errors, -10)) as $error): ?>
                        <tr>
                            <td><?php echo esc_html($error['time']); ?></td>
                            <td><code><?php echo esc_html($error['hook']); ?></code></td>
                            <td style="color: red;"><?php echo esc_html($error['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top: 10px;">
                <button class="button" id="clear-cron-errors">پاک کردن خطاها</button>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ابزارهای دستی -->
    <div class="postbox" style="margin-top: 20px;">
        <h2 class="hndle">ابزارهای دستی</h2>
        <div class="inside">
            <p>
                <button class="button button-primary" id="trigger-wp-cron">
                    🔄 اجرای دستی WP-Cron
                </button>
                <button class="button button-secondary" id="clear-all-telegram-crons">
                    🗑️ پاک کردن تمام کرون‌های تلگرام
                </button>
            </p>
            <p class="description">
                <strong>توجه:</strong> دکمه "اجرای دستی WP-Cron" تمام کرون‌های سررسید شده را اجرا می‌کند.
            </p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // اجرای فوری یک کرون خاص
    $('.run-cron-now').on('click', function() {
        var hook = $(this).data('hook');
        var timestamp = $(this).data('timestamp');
        
        if (!confirm('آیا مطمئن هستید که می‌خواهید این کرون را الان اجرا کنید؟')) {
            return;
        }
        
        $(this).prop('disabled', true).text('در حال اجرا...');
        
        $.post(ajaxurl, {
            action: 'telegram_run_cron_now',
            hook: hook,
            timestamp: timestamp,
            nonce: '<?php echo wp_create_nonce('telegram_cron_debug'); ?>'
        }, function(response) {
            alert(response.data);
            location.reload();
        });
    });
    
    // اجرای دستی WP-Cron
    $('#trigger-wp-cron').on('click', function() {
        $(this).prop('disabled', true).text('در حال اجرا...');
        
        $.post(ajaxurl, {
            action: 'telegram_trigger_wp_cron',
            nonce: '<?php echo wp_create_nonce('telegram_cron_debug'); ?>'
        }, function(response) {
            alert(response.data);
            location.reload();
        });
    });
    
    // پاک کردن تمام کرون‌ها
    $('#clear-all-telegram-crons').on('click', function() {
        if (!confirm('آیا مطمئن هستید؟ تمام کرون‌های برنامه‌ریزی شده تلگرام حذف خواهند شد.')) {
            return;
        }
        
        $(this).prop('disabled', true).text('در حال پاک‌سازی...');
        
        $.post(ajaxurl, {
            action: 'telegram_clear_all_crons',
            nonce: '<?php echo wp_create_nonce('telegram_cron_debug'); ?>'
        }, function(response) {
            alert(response.data);
            location.reload();
        });
    });
    
    // پاک کردن خطاها
    $('#clear-cron-errors').on('click', function() {
        $.post(ajaxurl, {
            action: 'telegram_clear_cron_errors',
            nonce: '<?php echo wp_create_nonce('telegram_cron_debug'); ?>'
        }, function(response) {
            alert('خطاها پاک شدند');
            location.reload();
        });
    });
});
</script>

<style>
details summary {
    cursor: pointer;
    color: #0073aa;
}
details summary:hover {
    color: #005177;
}
</style>
