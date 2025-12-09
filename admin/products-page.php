<?php

/**
 * صفحه مدیریت محصولات
 * 
 * @package TelegramSender
 * @author اصغر معینی <as.moini@gmail.com>
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// بررسی وجود ووکامرس
if (!telegram_sender_is_woocommerce_active()) {
?>
<div class="wrap">
    <h1>مدیریت محصولات</h1>
    <div class="notice notice-error">
        <p>این بخش نیاز به ووکامرس دارد. لطفاً ابتدا ووکامرس را نصب و فعال کنید.</p>
    </div>
</div>
<?php
    return;
}

// پردازش درخواست‌ها
$database = new TelegramSender_Database();

// همگام‌سازی محصولات
if (isset($_POST['sync_products']) && wp_verify_nonce($_POST['_wpnonce'], 'telegram_sender_products')) {
    $synced_count = $database->sync_products();
    if ($synced_count !== false) {
        telegram_sender_admin_notice_success("تعداد {$synced_count} محصول همگام‌سازی شد");
    } else {
        telegram_sender_admin_notice_error('خطا در همگام‌سازی محصولات');
    }
}

// تنظیمات جستجو و صفحه‌بندی
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$offset = ($current_page - 1) * $per_page;

// گرفتن محصولات
$products = $database->get_synced_products($per_page, $offset, $search);
$total_products = $database->count_synced_products($search);
$total_pages = ceil($total_products / $per_page);

// آمار کلی
$total_wc_products = telegram_sender_get_published_products_count();
$synced_products_count = $database->count_synced_products();
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-products"></span>
        مدیریت محصولات
        <a href="#" class="page-title-action" id="sync-all-products">همگام‌سازی</a>
    </h1>

    <!-- آمار کلی -->
    <div class="telegram-sender-stats">
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($total_wc_products); ?></div>
                <div class="stat-label">محصولات ووکامرس</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($synced_products_count); ?></div>
                <div class="stat-label">محصولات همگام‌سازی شده</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($total_products); ?></div>
                <div class="stat-label">نتایج فیلتر شده</div>
            </div>
        </div>
    </div>

    <!-- ابزارهای بالای جدول -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('telegram_sender_products'); ?>
                <input type="submit" name="sync_products" class="button button-secondary" value="همگام‌سازی محصولات">
            </form>

            <button type="button" class="button button-primary" id="send-all-products">
                ارسال همه محصولات
            </button>

            <button type="button" class="button button-secondary" id="send-all-scheduled">
                ارسال برنامه‌ریزی شده
            </button>
        </div>

        <div class="alignright">
            <form method="get" style="display: inline;">
                <input type="hidden" name="page" value="telegram-sender-products">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجوی محصولات...">
                <input type="submit" class="button" value="جستجو">
                <?php if ($search): ?>
                <a href="<?php echo admin_url('admin.php?page=telegram-sender-products'); ?>" class="button">پاک
                    کردن</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- جدول محصولات -->
    <?php if (!empty($products)): ?>
    <table class="wp-list-table widefat fixed striped products-table">
        <thead>
            <tr>
                <th class="column-image">تصویر</th>
                <th class="column-name">نام محصول</th>
                <th class="column-sku">کد محصول</th>
                <th class="column-price">قیمت</th>
                <th class="column-stock">موجودی</th>
                <th class="column-send-count">تعداد ارسال</th>
                <th class="column-last-sent">آخرین ارسال</th>
                <th class="column-actions">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
            <tr data-product-id="<?php echo esc_attr($product->product_id); ?>">
                <td class="column-image">
                    <?php if ($product->image_url): ?>
                    <img src="<?php echo esc_url($product->image_url); ?>" alt="<?php echo esc_attr($product->name); ?>"
                        class="product-thumb">
                    <?php else: ?>
                    <div class="no-image">📦</div>
                    <?php endif; ?>
                </td>

                <td class="column-name">
                    <div class="product-name" contenteditable="true" data-field="name"
                        data-original="<?php echo esc_attr($product->name); ?>">
                        <?php echo esc_html($product->name); ?>
                    </div>
                    <div class="product-description">
                        <div contenteditable="true" data-field="description"
                            data-original="<?php echo esc_attr($product->short_description); ?>">
                            <?php echo esc_html(telegram_sender_limit_text($product->short_description, 100)); ?>
                        </div>
                    </div>
                </td>

                <td class="column-sku">
                    <div contenteditable="true" data-field="sku" data-original="<?php echo esc_attr($product->sku); ?>">
                        <?php echo esc_html($product->sku ?: '—'); ?>
                    </div>
                </td>

                <td class="column-price">
                    <div class="price-container">
                        <div class="regular-price">
                            <span>قیمت: </span>
                            <span contenteditable="true" data-field="price"
                                data-original="<?php echo esc_attr($product->price); ?>">
                                <?php echo $product->price ? number_format($product->price) : '—'; ?>
                            </span>
                        </div>
                        <?php if ($product->sale_price && $product->sale_price < $product->price): ?>
                        <div class="sale-price">
                            <span>تخفیف: </span>
                            <span contenteditable="true" data-field="sale_price"
                                data-original="<?php echo esc_attr($product->sale_price); ?>">
                                <?php echo number_format($product->sale_price); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>

                <td class="column-stock">
                    <span class="stock-status stock-<?php echo esc_attr($product->stock_status); ?>">
                        <?php echo telegram_sender_get_stock_status_icon($product->stock_status); ?>
                        <?php echo telegram_sender_get_stock_status_text($product->stock_status); ?>
                    </span>
                </td>

                <td class="column-send-count">
                    <span class="send-count"><?php echo intval($product->send_count); ?></span>
                </td>

                <td class="column-last-sent">
                    <?php if ($product->last_sent): ?>
                    <span class="last-sent" title="<?php echo esc_attr($product->last_sent); ?>">
                        <?php echo telegram_sender_format_persian_date(strtotime($product->last_sent)); ?>
                    </span>
                    <?php else: ?>
                    <span class="never-sent">هرگز</span>
                    <?php endif; ?>
                </td>

                <td class="column-actions">
                    <div class="actions-container">
                        <button type="button" class="button button-small send-product"
                            data-product-id="<?php echo esc_attr($product->product_id); ?>">
                            <span class="dashicons dashicons-share"></span>
                            ارسال
                        </button>

                        <button type="button" class="button button-small save-changes"
                            data-product-id="<?php echo esc_attr($product->product_id); ?>" style="display: none;">
                            <span class="dashicons dashicons-yes"></span>
                            ذخیره
                        </button>

                        <a href="<?php echo get_edit_post_link($product->product_id); ?>" class="button button-small"
                            target="_blank">
                            <span class="dashicons dashicons-edit"></span>
                            ویرایش
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- صفحه‌بندی -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
                    $pagination_args = array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo; قبلی',
                        'next_text' => 'بعدی &raquo;',
                        'current' => $current_page,
                        'total' => $total_pages,
                        'show_all' => false,
                        'end_size' => 1,
                        'mid_size' => 2
                    );

                    if ($search) {
                        $pagination_args['base'] = add_query_arg(array('s' => $search, 'paged' => '%#%'));
                    }

                    echo paginate_links($pagination_args);
                    ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="no-items">
        <p>
            <?php if ($search): ?>
            هیچ محصولی با عبارت "<?php echo esc_html($search); ?>" پیدا نشد.
            <?php else: ?>
            هیچ محصولی برای نمایش وجود ندارد. ابتدا محصولات را همگام‌سازی کنید.
            <?php endif; ?>
        </p>

        <?php if (!$search): ?>
        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('telegram_sender_products'); ?>
            <input type="submit" name="sync_products" class="button button-primary button-large"
                value="همگام‌سازی محصولات">
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- مودال تنظیمات ارسال برنامه‌ریزی شده -->
<div id="scheduled-send-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>ارسال برنامه‌ریزی شده محصولات</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="scheduled-send-form">
                <table class="form-table">
                    <tr>
                        <th><label for="send-interval">فاصله زمانی (دقیقه):</label></th>
                        <td>
                            <input type="number" id="send-interval" name="interval" value="5" min="1" max="60"
                                class="small-text">
                            <p class="description">فاصله زمانی بین ارسال هر محصول</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="send-start-time">شروع بازه (ساعت):</label></th>
                        <td>
                            <input type="time" id="send-start-time" name="start_time" value="08:00" class="regular-text">
                            <p class="description">ارسال‌ها از این ساعت به بعد شروع می‌شوند</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="send-end-time">پایان بازه (ساعت):</label></th>
                        <td>
                            <input type="time" id="send-end-time" name="end_time" value="22:00" class="regular-text">
                            <p class="description">ارسال‌ها تا قبل از این ساعت انجام می‌شوند</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="exclude-out-of-stock">محصولات ناموجود را ارسال نکن</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="exclude-out-of-stock" name="exclude_out_of_stock" value="1" checked>
                                محصولات ناموجود را ارسال نکن
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="only-unsent">فقط محصولات ارسال‌نشده</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="only-unsent" name="only_unsent" value="1">
                                فقط محصولاتی که تا الان ارسال نشدند ارسال شوند
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="only-price-updated">فقط محصولات با به‌روزرسانی قیمت</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="only-price-updated" name="only_price_updated" value="1">
                                فقط محصولاتی که اخیراً به‌روزرسانی قیمت داشتند ارسال شوند
                            </label>
                            <p class="description">بر اساس مقایسه با آخرین قیمت ارسال‌شده. اگر محصولی تاکنون ارسال نشده باشد، در این حالت ارسال نمی‌شود.</p>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-primary" id="confirm-scheduled-send">شروع ارسال</button>
            <button type="button" class="button" id="cancel-scheduled-send">انصراف</button>
        </div>
    </div>
</div>

<style>
.telegram-sender-stats {
    margin: 20px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    text-align: center;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #135e96;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 13px;
}

.products-table {
    margin-top: 0;
}

.products-table .column-image {
    width: 60px;
}

.products-table .column-name {
    width: 25%;
}

.products-table .column-sku {
    width: 100px;
}

.products-table .column-price {
    width: 120px;
}

.products-table .column-stock {
    width: 100px;
}

.products-table .column-send-count {
    width: 80px;
}

.products-table .column-last-sent {
    width: 120px;
}

.products-table .column-actions {
    width: 150px;
}

.product-thumb {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 4px;
}

.no-image {
    width: 40px;
    height: 40px;
    background: #f0f0f1;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.product-name {
    font-weight: bold;
    margin-bottom: 5px;
}

.product-description {
    font-size: 12px;
    color: #666;
}

.price-container .regular-price {
    margin-bottom: 3px;
}

.price-container .sale-price {
    color: #d63638;
    font-weight: bold;
}

.stock-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.stock-status.stock-instock {
    background: #d4edda;
    color: #155724;
}

.stock-status.stock-outofstock {
    background: #f8d7da;
    color: #721c24;
}

.stock-status.stock-onbackorder {
    background: #fff3cd;
    color: #856404;
}

.send-count {
    background: #f0f0f1;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: bold;
}

.last-sent {
    font-size: 11px;
    color: #666;
}

.never-sent {
    font-size: 11px;
    color: #999;
    font-style: italic;
}

.actions-container {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.actions-container .button {
    padding: 2px 8px;
    font-size: 11px;
    line-height: 1.4;
}

.actions-container .dashicons {
    font-size: 12px;
    margin-right: 2px;
}

/* ویرایش درجا */
[contenteditable="true"] {
    border: 1px solid transparent;
    padding: 2px 4px;
    border-radius: 3px;
    transition: all 0.2s;
}

[contenteditable="true"]:hover {
    background: #f8f9fa;
    border-color: #ddd;
}

[contenteditable="true"]:focus {
    background: #fff;
    border-color: #007cba;
    outline: none;
    box-shadow: 0 0 0 1px #007cba;
}

.editing [contenteditable="true"] {
    background: #fff3cd;
    border-color: #ffc107;
}

/* مودال */
.modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 0;
    border-radius: 6px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.modal-footer .button {
    margin-left: 10px;
}

.close {
    color: #aaa;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}

.close:hover {
    color: #000;
}

.no-items {
    text-align: center;
    padding: 40px 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.no-items p {
    font-size: 16px;
    color: #666;
    margin-bottom: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .products-table .column-image,
    .products-table .column-sku,
    .products-table .column-send-count,
    .products-table .column-last-sent {
        display: none;
    }

    .actions-container {
        flex-direction: column;
    }

    .modal-content {
        width: 95%;
        margin: 5% auto;
    }
}

/* حالت loading */
.loading {
    position: relative;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
}

.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }

    100% {
        transform: rotate(360deg);
    }
}
</style>