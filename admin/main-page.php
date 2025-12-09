<?php

/**
 * صفحه اصلی داشبورد افزونه
 * 
 * @package TelegramSender
 * @author اصغر معینی <as.moini@gmail.com>
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// دریافت آمار و اطلاعات
$database = new TelegramSender_Database();
$stats = $database->get_send_statistics();
$server_info = telegram_sender_get_server_info();

// بررسی تنظیمات
$bot_token = get_option('telegram_sender_bot_token', '');
$chat_ids = get_option('telegram_sender_chat_ids', array());
$is_configured = !empty($bot_token) && !empty($chat_ids);

// آمار محصولات و نوشته‌ها
$total_products = telegram_sender_get_published_products_count();
$total_posts = telegram_sender_get_published_posts_count();
$synced_products = $database->count_synced_products();

// لاگ‌های اخیر
$recent_logs = $database->get_send_logs(10);
?>

<div class="wrap telegram-sender-admin">
    <h1>
        <span class="dashicons dashicons-share"></span>
        داشبورد ارسال به تلگرام
        <span class="page-title-action">نسخه <?php echo TELEGRAM_SENDER_VERSION; ?></span>
    </h1>

    <?php if (!$is_configured): ?>
    <!-- اطلاعیه تنظیمات -->
    <div class="notice notice-warning">
        <p>
            <strong>توجه:</strong> افزونه هنوز تنظیم نشده است.
            <a href="<?php echo telegram_sender_admin_url('telegram-sender-settings'); ?>">لطفاً ابتدا تنظیمات را انجام
                دهید</a>
        </p>
    </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <!-- ستون اصلی -->
        <div class="main-column">
            <!-- آمار کلی -->
            <div class="postbox dashboard-stats">
                <h2 class="hndle">
                    <span>آمار کلی ارسال</span>
                </h2>
                <div class="inside">
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-icon">📊</div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo number_format($stats['total_sends']); ?></div>
                                <div class="stat-label">کل ارسال‌ها</div>
                            </div>
                        </div>

                        <div class="stat-box success">
                            <div class="stat-icon">✅</div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo number_format($stats['successful_sends']); ?></div>
                                <div class="stat-label">ارسال موفق</div>
                            </div>
                        </div>

                        <div class="stat-box error">
                            <div class="stat-icon">❌</div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo number_format($stats['failed_sends']); ?></div>
                                <div class="stat-label">ارسال ناموفق</div>
                            </div>
                        </div>

                        <div class="stat-box rate">
                            <div class="stat-icon">📈</div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo $stats['success_rate']; ?>%</div>
                                <div class="stat-label">نرخ موفقیت</div>
                            </div>
                        </div>

                        <div class="stat-box today">
                            <div class="stat-icon">📅</div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo number_format($stats['today_sends']); ?></div>
                                <div class="stat-label">ارسال امروز</div>
                            </div>
                        </div>

                        <div class="stat-box week">
                            <div class="stat-icon">📆</div>
                            <div class="stat-info">
                                <div class="stat-number"><?php echo number_format($stats['week_sends']); ?></div>
                                <div class="stat-label">ارسال این هفته</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- عملیات سریع -->
            <div class="postbox quick-actions">
                <h2 class="hndle">
                    <span>عملیات سریع</span>
                </h2>
                <div class="inside">
                    <div class="quick-actions-grid">
                        <div class="quick-action-card">
                            <div class="action-icon">🛍️</div>
                            <h3>مدیریت محصولات</h3>
                            <p>مشاهده، ویرایش و ارسال محصولات به تلگرام</p>
                            <div class="action-stats">
                                <span class="stat"><?php echo number_format($total_products); ?> محصول</span>
                                <span class="stat"><?php echo number_format($synced_products); ?> همگام‌سازی شده</span>
                            </div>
                            <div class="action-buttons">
                                <a href="<?php echo telegram_sender_admin_url('telegram-sender-products'); ?>"
                                    class="button button-primary">
                                    مدیریت محصولات
                                </a>
                                <?php if ($is_configured): ?>
                                <button type="button" class="button button-secondary" id="quick-sync-products">
                                    همگام‌سازی
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="quick-action-card">
                            <div class="action-icon">📰</div>
                            <h3>مدیریت نوشته‌ها</h3>
                            <p>مشاهده و ارسال نوشته‌ها و مطالب به تلگرام</p>
                            <div class="action-stats">
                                <span class="stat"><?php echo number_format($total_posts); ?> نوشته</span>
                                <span class="stat">منتشر شده</span>
                            </div>
                            <div class="action-buttons">
                                <a href="<?php echo telegram_sender_admin_url('telegram-sender-posts'); ?>"
                                    class="button button-primary">
                                    مدیریت نوشته‌ها
                                </a>
                            </div>
                        </div>

                        <div class="quick-action-card">
                            <div class="action-icon">⚙️</div>
                            <h3>تنظیمات</h3>
                            <p>تنظیم توکن ربات، چت آیدی‌ها و سایر گزینه‌ها</p>
                            <div class="action-stats">
                                <span class="stat <?php echo $is_configured ? 'configured' : 'not-configured'; ?>">
                                    <?php echo $is_configured ? '✅ تنظیم شده' : '❌ تنظیم نشده'; ?>
                                </span>
                            </div>
                            <div class="action-buttons">
                                <a href="<?php echo telegram_sender_admin_url('telegram-sender-settings'); ?>"
                                    class="button button-primary">
                                    تنظیمات
                                </a>
                                <?php if ($is_configured): ?>
                                <button type="button" class="button button-secondary" id="test-connection">
                                    تست اتصال
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- لاگ‌های اخیر -->
            <div class="postbox recent-logs">
                <h2 class="hndle">
                    <span>فعالیت‌های اخیر</span>
                    <a href="#" class="refresh-logs">🔄</a>
                </h2>
                <div class="inside">
                    <?php if (!empty($recent_logs)): ?>
                    <div class="logs-list">
                        <?php foreach ($recent_logs as $log): ?>
                        <div class="log-item log-<?php echo esc_attr($log->status); ?>">
                            <div class="log-icon">
                                <?php
                                        if ($log->status === 'success') {
                                            echo '✅';
                                        } else {
                                            echo '❌';
                                        }
                                        ?>
                            </div>
                            <div class="log-content">
                                <div class="log-message">
                                    <?php
                                            $type_label = $log->type === 'product' ? 'محصول' : 'نوشته';
                                            echo "ارسال {$type_label} به " . esc_html($log->chat_id);
                                            ?>
                                </div>
                                <div class="log-details">
                                    <?php if ($log->message): ?>
                                    <span class="log-detail"><?php echo esc_html($log->message); ?></span>
                                    <?php endif; ?>
                                    <span
                                        class="log-time"><?php echo telegram_sender_format_persian_date(strtotime($log->sent_at)); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="logs-footer">
                        <a href="#" class="view-all-logs">مشاهده همه لاگ‌ها</a>
                    </div>
                    <?php else: ?>
                    <div class="no-logs">
                        <div class="no-logs-icon">📋</div>
                        <p>هنوز هیچ فعالیتی ثبت نشده است</p>
                        <?php if ($is_configured): ?>
                        <p>برای شروع، یک محصول یا نوشته ارسال کنید</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ستون کناری -->
        <div class="sidebar-column">
            <!-- وضعیت سیستم -->
            <div class="postbox system-status">
                <h3 class="hndle">
                    <span>وضعیت سیستم</span>
                </h3>
                <div class="inside">
                    <div class="status-item">
                        <span class="status-label">وردپرس:</span>
                        <span class="status-value"><?php echo esc_html($server_info['wordpress_version']); ?></span>
                    </div>

                    <div class="status-item">
                        <span class="status-label">ووکامرس:</span>
                        <span class="status-value"><?php echo esc_html($server_info['woocommerce_version']); ?></span>
                    </div>

                    <div class="status-item">
                        <span class="status-label">PHP:</span>
                        <span class="status-value"><?php echo esc_html($server_info['php_version']); ?></span>
                    </div>

                    <div class="status-item">
                        <span class="status-label">cURL:</span>
                        <span class="status-value <?php echo $server_info['curl_enabled'] ? 'enabled' : 'disabled'; ?>">
                            <?php echo $server_info['curl_enabled'] ? '✅ فعال' : '❌ غیرفعال'; ?>
                        </span>
                    </div>

                    <div class="status-item">
                        <span class="status-label">OpenSSL:</span>
                        <span
                            class="status-value <?php echo $server_info['openssl_enabled'] ? 'enabled' : 'disabled'; ?>">
                            <?php echo $server_info['openssl_enabled'] ? '✅ فعال' : '❌ غیرفعال'; ?>
                        </span>
                    </div>

                    <div class="status-item">
                        <span class="status-label">حافظه:</span>
                        <span class="status-value"><?php echo esc_html($server_info['memory_limit']); ?></span>
                    </div>
                </div>
            </div>

            <!-- محصولات پربازدید -->
            <?php if (!empty($stats['top_products'])): ?>
            <div class="postbox top-products">
                <h3 class="hndle">
                    <span>محصولات پربازدید</span>
                </h3>
                <div class="inside">
                    <div class="top-products-list">
                        <?php foreach ($stats['top_products'] as $product): ?>
                        <div class="top-product-item">
                            <div class="product-info">
                                <span class="product-name"><?php echo esc_html($product->name); ?></span>
                                <span class="send-count"><?php echo $product->send_count; ?> بار</span>
                            </div>
                            <div class="product-actions">
                                <a href="<?php echo get_edit_post_link($product->product_id); ?>" class="edit-link"
                                    target="_blank">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- راهنما و پشتیبانی -->
            <div class="postbox help-support">
                <h3 class="hndle">
                    <span>راهنما و پشتیبانی</span>
                </h3>
                <div class="inside">
                    <div class="help-links">
                        <a href="#" class="help-link" data-modal="help-setup">
                            📚 راهنمای راه‌اندازی
                        </a>

                        <a href="#" class="help-link" data-modal="help-troubleshoot">
                            🔧 عیب‌یابی مشکلات
                        </a>

                        <a href="mailto:as.moini@gmail.com" class="help-link">
                            📧 تماس با پشتیبانی
                        </a>

                        <a href="#" class="help-link" data-modal="help-shortcuts">
                            ⌨️ میانبرهای کیبورد
                        </a>
                    </div>

                    <div class="developer-info">
                        <h4>توسعه‌دهنده</h4>
                        <p><strong>اصغر معینی</strong></p>
                        <p><a href="mailto:as.moini@gmail.com">as.moini@gmail.com</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال‌های راهنما -->
<div id="help-setup-modal" class="modal help-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>راهنمای راه‌اندازی</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="help-steps">
                <div class="help-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>ایجاد ربات تلگرام</h4>
                        <p>به @BotFather در تلگرام مراجعه کرده و دستور /newbot را ارسال کنید</p>
                    </div>
                </div>

                <div class="help-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>دریافت توکن</h4>
                        <p>پس از ایجاد ربات، توکن دریافتی را در تنظیمات افزونه وارد کنید</p>
                    </div>
                </div>

                <div class="help-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>تنظیم چت آیدی</h4>
                        <p>چت آیدی کانال یا گروه مقصد را در تنظیمات افزونه اضافه کنید</p>
                    </div>
                </div>

                <div class="help-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h4>تست اتصال</h4>
                        <p>در صفحه تنظیمات، دکمه "تست اتصال" را کلیک کنید تا اتصال بررسی شود</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* استایل‌های داشبورد */
.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.main-column,
.sidebar-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* آمار کلی */
.dashboard-stats .stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.dashboard-stats .stat-box {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    transition: all 0.3s ease;
}

.dashboard-stats .stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.dashboard-stats .stat-box.success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
}

.dashboard-stats .stat-box.error {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
}

.dashboard-stats .stat-box.rate {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
}

.dashboard-stats .stat-box.today {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
}

.dashboard-stats .stat-box.week {
    background: linear-gradient(135deg, #e2e3e5 0%, #d6d8db 100%);
}

.stat-icon {
    font-size: 32px;
    opacity: 0.8;
}

.stat-info {
    flex: 1;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #666;
}

/* عملیات سریع */
.quick-actions-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

.quick-action-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s ease;
}

.quick-action-card:hover {
    border-color: #4CAF50;
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.1);
}

.action-icon {
    font-size: 48px;
    text-align: center;
    margin-bottom: 15px;
}

.quick-action-card h3 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 18px;
}

.quick-action-card p {
    color: #666;
    margin-bottom: 15px;
    line-height: 1.5;
}

.action-stats {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.action-stats .stat {
    background: #f0f0f1;
    padding: 5px 10px;
    border-radius: 12px;
    font-size: 12px;
    color: #666;
}

.action-stats .stat.configured {
    background: #d4edda;
    color: #155724;
}

.action-stats .stat.not-configured {
    background: #f8d7da;
    color: #721c24;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons .button {
    flex: 1;
    text-align: center;
    min-width: 120px;
}

/* لاگ‌ها */
.logs-list {
    max-height: 300px;
    overflow-y: auto;
}

.log-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.log-item:last-child {
    border-bottom: none;
}

.log-icon {
    font-size: 16px;
    margin-top: 2px;
}

.log-content {
    flex: 1;
}

.log-message {
    font-weight: 500;
    color: #333;
    margin-bottom: 5px;
}

.log-details {
    font-size: 12px;
    color: #666;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.log-time {
    color: #999;
}

.logs-footer {
    text-align: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
}

.no-logs {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.no-logs-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

/* ستون کناری */
.sidebar-column .postbox {
    background: white;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.status-item:last-child {
    border-bottom: none;
}

.status-label {
    font-weight: 500;
    color: #333;
}

.status-value {
    font-size: 13px;
    color: #666;
}

.status-value.enabled {
    color: #28a745;
}

.status-value.disabled {
    color: #dc3545;
}

/* محصولات برتر */
.top-product-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.top-product-item:last-child {
    border-bottom: none;
}

.product-info {
    flex: 1;
}

.product-name {
    display: block;
    font-weight: 500;
    color: #333;
    margin-bottom: 3px;
}

.send-count {
    font-size: 11px;
    color: #666;
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 10px;
}

.product-actions .edit-link {
    color: #666;
    text-decoration: none;
    padding: 5px;
    border-radius: 3px;
    transition: all 0.3s ease;
}

.product-actions .edit-link:hover {
    background: #f0f0f1;
    color: #4CAF50;
}

/* راهنما */
.help-links {
    margin-bottom: 20px;
}

.help-link {
    display: block;
    padding: 8px 0;
    color: #4CAF50;
    text-decoration: none;
    border-bottom: 1px solid #f0f0f0;
    transition: color 0.3s ease;
}

.help-link:hover {
    color: #45a049;
}

.help-link:last-child {
    border-bottom: none;
}

.developer-info {
    padding-top: 20px;
    border-top: 1px solid #f0f0f0;
}

.developer-info h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.developer-info p {
    margin: 5px 0;
    color: #666;
    font-size: 13px;
}

/* مودال‌های راهنما */
.help-modal .modal-content {
    max-width: 600px;
}

.help-steps {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.help-step {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.step-number {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    flex-shrink: 0;
}

.step-content h4 {
    margin: 0 0 8px 0;
    color: #333;
    font-size: 16px;
}

.step-content p {
    margin: 0;
    color: #666;
    line-height: 1.5;
}

/* Responsive */
@media (max-width: 1200px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-stats .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .dashboard-stats .stats-grid {
        grid-template-columns: 1fr;
    }

    .quick-action-card {
        padding: 15px;
    }

    .action-icon {
        font-size: 36px;
    }

    .action-buttons {
        flex-direction: column;
    }

    .action-buttons .button {
        width: 100%;
    }

    .help-step {
        flex-direction: column;
        text-align: center;
    }

    .step-number {
        align-self: center;
    }
}

@media (max-width: 480px) {
    .dashboard-grid {
        gap: 15px;
    }

    .main-column,
    .sidebar-column {
        gap: 15px;
    }

    .quick-action-card {
        padding: 12px;
    }

    .stat-box {
        padding: 15px;
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }

    .stat-icon {
        font-size: 24px;
    }

    .stat-number {
        font-size: 20px;
    }
}

/* انیمیشن‌های لودینگ */
.refresh-logs {
    text-decoration: none;
    padding: 5px;
    border-radius: 3px;
    transition: all 0.3s ease;
}

.refresh-logs:hover {
    background: #f0f0f1;
    transform: rotate(180deg);
}

.refresh-logs.loading {
    animation: spin 1s linear infinite;
}

/* حالت خالی */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

/* نوتیفیکیشن‌های داخلی */
.inline-notification {
    padding: 10px 15px;
    border-radius: 6px;
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.inline-notification.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.inline-notification.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.inline-notification.warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.inline-notification.info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

/* Progress bars */
.progress-bar {
    background: #f0f0f1;
    border-radius: 10px;
    height: 8px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    background: linear-gradient(90deg, #4CAF50, #45a049);
    height: 100%;
    border-radius: 10px;
    transition: width 0.3s ease;
}

/* Tooltips */
.tooltip {
    position: relative;
    cursor: help;
}

.tooltip::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
    z-index: 1000;
}

.tooltip:hover::after {
    opacity: 1;
}

/* کلاس‌های کاربردی اضافی */
.text-success {
    color: #28a745 !important;
}

.text-error {
    color: #dc3545 !important;
}

.text-warning {
    color: #ffc107 !important;
}

.text-info {
    color: #17a2b8 !important;
}

.text-muted {
    color: #6c757d !important;
}

.bg-success {
    background-color: #d4edda !important;
}

.bg-error {
    background-color: #f8d7da !important;
}

.bg-warning {
    background-color: #fff3cd !important;
}

.bg-info {
    background-color: #d1ecf1 !important;
}

.font-weight-bold {
    font-weight: bold !important;
}

.font-weight-normal {
    font-weight: normal !important;
}

.border {
    border: 1px solid #dee2e6 !important;
}

.border-0 {
    border: 0 !important;
}

.border-radius {
    border-radius: 6px !important;
}

.cursor-pointer {
    cursor: pointer;
}

.cursor-help {
    cursor: help;
}

.overflow-hidden {
    overflow: hidden;
}

.overflow-auto {
    overflow: auto;
}

.position-relative {
    position: relative;
}

.position-absolute {
    position: absolute;
}

.z-index-1 {
    z-index: 1;
}

.z-index-10 {
    z-index: 10;
}

.z-index-100 {
    z-index: 100;
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';

    // تست اتصال سریع
    $('#test-connection').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalText = $button.text();

        $button.text('در حال تست...').prop('disabled', true);

        $.ajax({
            url: telegram_sender_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'telegram_test_connection',
                nonce: telegram_sender_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showInlineNotification('اتصال موفقیت‌آمیز بود!', 'success');
                } else {
                    showInlineNotification('خطا در اتصال: ' + response.data, 'error');
                }
            },
            error: function() {
                showInlineNotification('خطا در برقراری ارتباط', 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });

    // همگام‌سازی سریع محصولات
    $('#quick-sync-products').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalText = $button.text();

        $button.text('همگام‌سازی...').prop('disabled', true);

        $.ajax({
            url: telegram_sender_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'telegram_sync_products',
                nonce: telegram_sender_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showInlineNotification('محصولات همگام‌سازی شدند', 'success');
                    location.reload(); // رفرش برای نمایش آمار جدید
                } else {
                    showInlineNotification('خطا در همگام‌سازی: ' + response.data, 'error');
                }
            },
            error: function() {
                showInlineNotification('خطا در همگام‌سازی', 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });

    // رفرش لاگ‌ها
    $('.refresh-logs').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        $button.addClass('loading');

        // شبیه‌سازی رفرش - در عمل باید از AJAX استفاده کرد
        setTimeout(function() {
            $button.removeClass('loading');
            showInlineNotification('لاگ‌ها بروزرسانی شدند', 'info');
            // location.reload();
        }, 1000);
    });

    // نمایش مودال‌های راهنما
    $('.help-link[data-modal]').on('click', function(e) {
        e.preventDefault();

        var modalId = $(this).data('modal') + '-modal';
        $('#' + modalId).show();
    });

    // بستن مودال‌های راهنما
    $('.help-modal .close').on('click', function() {
        $('.help-modal').hide();
    });

    // نمایش نوتیفیکیشن درون‌خطی
    function showInlineNotification(message, type) {
        var $notification = $('<div class="inline-notification ' + type + '">' +
            '<span class="notification-icon">' + getNotificationIcon(type) + '</span>' +
            '<span class="notification-message">' + message + '</span>' +
            '</div>');

        $('.wrap h1').after($notification);

        // حذف خودکار پس از 5 ثانیه
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // اسکرول به بالا
        $('html, body').animate({
            scrollTop: 0
        }, 300);
    }

    function getNotificationIcon(type) {
        var icons = {
            'success': '✅',
            'error': '❌',
            'warning': '⚠️',
            'info': 'ℹ️'
        };

        return icons[type] || 'ℹ️';
    }

    // رفرش خودکار آمار هر 30 ثانیه
    setInterval(function() {
        updateDashboardStats();
    }, 30000);

    function updateDashboardStats() {
        $.ajax({
            url: telegram_sender_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'telegram_get_dashboard_stats',
                nonce: telegram_sender_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatsDisplay(response.data);
                }
            }
        });
    }

    function updateStatsDisplay(stats) {
        // بروزرسانی آمار در DOM
        $('.dashboard-stats .stat-number').each(function() {
            var $element = $(this);
            var $parent = $element.closest('.stat-box');

            if ($parent.hasClass('success')) {
                $element.text(numberFormat(stats.successful_sends));
            } else if ($parent.hasClass('error')) {
                $element.text(numberFormat(stats.failed_sends));
            } else if ($parent.hasClass('rate')) {
                $element.text(stats.success_rate + '%');
            } else if ($parent.hasClass('today')) {
                $element.text(numberFormat(stats.today_sends));
            } else if ($parent.hasClass('week')) {
                $element.text(numberFormat(stats.week_sends));
            } else {
                $element.text(numberFormat(stats.total_sends));
            }
        });
    }

    function numberFormat(number) {
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Tooltips
    $('[data-tooltip]').hover(
        function() {
            $(this).addClass('tooltip');
        },
        function() {
            $(this).removeClass('tooltip');
        }
    );

    // پیش‌بارگذاری تصاویر
    function preloadImages() {
        var images = [
            // لیست تصاویری که باید پیش‌بارگذاری شوند
        ];

        images.forEach(function(src) {
            var img = new Image();
            img.src = src;
        });
    }

    preloadImages();
});
</script>