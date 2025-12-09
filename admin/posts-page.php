<?php

/**
 * صفحه مدیریت نوشته‌ها
 * 
 * @package TelegramSender
 * @author اصغر معینی <as.moini@gmail.com>
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// تنظیمات صفحه‌بندی و جستجو
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'post';

// تنظیمات query
$args = array(
    'post_status' => 'publish',
    'post_type' => $post_type,
    'posts_per_page' => $per_page,
    'paged' => $current_page,
    'orderby' => 'date',
    'order' => 'DESC'
);

if (!empty($search)) {
    $args['s'] = $search;
}

// دریافت نوشته‌ها
$posts_query = new WP_Query($args);
$posts = $posts_query->posts;
$total_posts = $posts_query->found_posts;
$total_pages = $posts_query->max_num_pages;

// آمار کلی
$all_posts_count = wp_count_posts($post_type);
$published_posts = $all_posts_count->publish ?? 0;

// نوع‌های پست قابل ارسال
$post_types = telegram_sender_get_sendable_post_types();
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-admin-post"></span>
        مدیریت نوشته‌ها
    </h1>

    <!-- آمار کلی -->
    <div class="telegram-sender-stats">
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($published_posts); ?></div>
                <div class="stat-label">نوشته‌های منتشر شده</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo number_format($total_posts); ?></div>
                <div class="stat-label">نتایج فیلتر شده</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count($post_types); ?></div>
                <div class="stat-label">نوع‌های پست</div>
            </div>
        </div>
    </div>

    <!-- فیلترها و ابزارها -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" style="display: inline;">
                <input type="hidden" name="page" value="telegram-sender-posts">
                <select name="post_type" id="post-type-filter">
                    <?php foreach ($post_types as $type_slug => $type_label): ?>
                    <option value="<?php echo esc_attr($type_slug); ?>" <?php selected($post_type, $type_slug); ?>>
                        <?php echo esc_html($type_label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="فیلتر">
            </form>

            <button type="button" class="button button-primary" id="send-all-posts">
                ارسال همه نوشته‌ها
            </button>

            <button type="button" class="button button-secondary" id="send-all-posts-scheduled">
                ارسال برنامه‌ریزی شده
            </button>
        </div>

        <div class="alignright">
            <form method="get" style="display: inline;">
                <input type="hidden" name="page" value="telegram-sender-posts">
                <input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجوی نوشته‌ها...">
                <input type="submit" class="button" value="جستجو">
                <?php if ($search): ?>
                <a href="<?php echo admin_url('admin.php?page=telegram-sender-posts&post_type=' . $post_type); ?>"
                    class="button">پاک کردن</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- جدول نوشته‌ها -->
    <?php if (!empty($posts)): ?>
    <table class="wp-list-table widefat fixed striped posts-table">
        <thead>
            <tr>
                <th class="column-image">تصویر شاخص</th>
                <th class="column-title">عنوان نوشته</th>
                <th class="column-author">نویسنده</th>
                <th class="column-date">تاریخ انتشار</th>
                <th class="column-status">وضعیت</th>
                <th class="column-comments">نظرات</th>
                <th class="column-actions">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($posts as $post):
                    $post_id = $post->ID;
                    $post_title = $post->post_title;
                    $post_author = get_the_author_meta('display_name', $post->post_author);
                    $post_date = get_the_date('Y/m/d H:i', $post_id);
                    $post_status = get_post_status($post_id);
                    $comments_count = wp_count_comments($post_id);
                    $featured_image = get_the_post_thumbnail_url($post_id, 'thumbnail');
                    $edit_link = get_edit_post_link($post_id);
                    $view_link = get_permalink($post_id);
                ?>
            <tr data-post-id="<?php echo esc_attr($post_id); ?>">
                <td class="column-image">
                    <?php if ($featured_image): ?>
                    <img src="<?php echo esc_url($featured_image); ?>" alt="<?php echo esc_attr($post_title); ?>"
                        class="post-thumb">
                    <?php else: ?>
                    <div class="no-image">📄</div>
                    <?php endif; ?>
                </td>

                <td class="column-title">
                    <strong class="post-title"><?php echo esc_html($post_title); ?></strong>
                    <div class="post-excerpt">
                        <?php
                                $excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words($post->post_content, 20);
                                echo esc_html($excerpt);
                                ?>
                    </div>
                    <div class="row-actions">
                        <span class="edit">
                            <a href="<?php echo esc_url($edit_link); ?>" target="_blank">ویرایش</a> |
                        </span>
                        <span class="view">
                            <a href="<?php echo esc_url($view_link); ?>" target="_blank">مشاهده</a> |
                        </span>
                        <span class="preview">
                            <a href="#" class="preview-message" data-type="post"
                                data-post-id="<?php echo esc_attr($post_id); ?>">پیش‌نمایش پیام</a>
                        </span>
                    </div>
                </td>

                <td class="column-author">
                    <a href="<?php echo get_author_posts_url($post->post_author); ?>" target="_blank">
                        <?php echo esc_html($post_author); ?>
                    </a>
                </td>

                <td class="column-date">
                    <span class="post-date" title="<?php echo esc_attr(get_the_date('c', $post_id)); ?>">
                        <?php echo esc_html($post_date); ?>
                    </span>
                </td>

                <td class="column-status">
                    <?php
                            $status_labels = array(
                                'publish' => array('منتشر شده', 'success'),
                                'draft' => array('پیش‌نویس', 'warning'),
                                'pending' => array('در انتظار بررسی', 'info'),
                                'private' => array('خصوصی', 'secondary')
                            );

                            $status_info = $status_labels[$post_status] ?? array($post_status, 'secondary');
                            ?>
                    <span class="post-status status-<?php echo esc_attr($status_info[1]); ?>">
                        <?php echo esc_html($status_info[0]); ?>
                    </span>
                </td>

                <td class="column-comments">
                    <?php if ($comments_count->approved > 0): ?>
                    <a href="<?php echo admin_url('edit-comments.php?p=' . $post_id); ?>" class="comments-count">
                        <?php echo number_format($comments_count->approved); ?>
                    </a>
                    <?php else: ?>
                    <span class="no-comments">0</span>
                    <?php endif; ?>
                </td>

                <td class="column-actions">
                    <div class="actions-container">
                        <button type="button" class="button button-small send-post"
                            data-post-id="<?php echo esc_attr($post_id); ?>">
                            <span class="dashicons dashicons-share"></span>
                            ارسال
                        </button>

                        <a href="<?php echo esc_url($edit_link); ?>" class="button button-small" target="_blank">
                            <span class="dashicons dashicons-edit"></span>
                            ویرایش
                        </a>

                        <a href="<?php echo esc_url($view_link); ?>" class="button button-small" target="_blank">
                            <span class="dashicons dashicons-visibility"></span>
                            مشاهده
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
                        $pagination_args['base'] = add_query_arg(array('s' => $search, 'post_type' => $post_type, 'paged' => '%#%'));
                    } elseif ($post_type !== 'post') {
                        $pagination_args['base'] = add_query_arg(array('post_type' => $post_type, 'paged' => '%#%'));
                    }

                    echo paginate_links($pagination_args);
                    ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="no-items">
        <div class="dashicons dashicons-admin-post"></div>
        <p>
            <?php if ($search): ?>
            هیچ نوشته‌ای با عبارت "<?php echo esc_html($search); ?>" پیدا نشد.
            <?php else: ?>
            هیچ نوشته‌ای برای نمایش وجود ندارد.
            <?php endif; ?>
        </p>

        <?php if (!$search): ?>
        <a href="<?php echo admin_url('post-new.php?post_type=' . $post_type); ?>"
            class="button button-primary button-large">
            ایجاد نوشته جدید
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- مودال تنظیمات ارسال برنامه‌ریزی شده نوشته‌ها -->
<div id="scheduled-send-posts-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>ارسال برنامه‌ریزی شده نوشته‌ها</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="scheduled-send-posts-form">
                <table class="form-table">
                    <tr>
                        <th><label for="send-posts-interval">فاصله زمانی (دقیقه):</label></th>
                        <td>
                            <input type="number" id="send-posts-interval" name="interval" value="5" min="1" max="60"
                                class="small-text">
                            <p class="description">فاصله زمانی بین ارسال هر نوشته</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="send-posts-start-time">شروع بازه (ساعت):</label></th>
                        <td>
                            <input type="time" id="send-posts-start-time" name="start_time" value="08:00" class="regular-text">
                            <p class="description">ارسال‌ها از این ساعت به بعد شروع می‌شوند</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="send-posts-end-time">پایان بازه (ساعت):</label></th>
                        <td>
                            <input type="time" id="send-posts-end-time" name="end_time" value="22:00" class="regular-text">
                            <p class="description">ارسال‌ها تا قبل از این ساعت انجام می‌شوند</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="posts-limit">حداکثر تعداد:</label></th>
                        <td>
                            <input type="number" id="posts-limit" name="limit" value="<?php echo count($posts); ?>"
                                min="1" max="100" class="small-text">
                            <p class="description">حداکثر تعداد نوشته‌هایی که ارسال شوند</p>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-primary" id="confirm-scheduled-send-posts">شروع ارسال</button>
            <button type="button" class="button" id="cancel-scheduled-send-posts">انصراف</button>
        </div>
    </div>
</div>

<style>
/* استایل‌های اضافی برای صفحه نوشته‌ها */
.posts-table .column-image {
    width: 60px;
}

.posts-table .column-title {
    width: 30%;
}

.posts-table .column-author {
    width: 120px;
}

.posts-table .column-date {
    width: 120px;
}

.posts-table .column-status {
    width: 100px;
}

.posts-table .column-comments {
    width: 80px;
}

.posts-table .column-actions {
    width: 180px;
}

.post-thumb {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid #e0e0e0;
}

.post-title {
    font-size: 14px;
    color: #333;
    margin-bottom: 5px;
    display: block;
}

.post-excerpt {
    font-size: 12px;
    color: #666;
    line-height: 1.4;
    margin-bottom: 8px;
}

.row-actions {
    font-size: 12px;
}

.row-actions span {
    display: inline;
}

.row-actions a {
    color: #666;
    text-decoration: none;
    transition: color 0.3s ease;
}

.row-actions a:hover {
    color: #4CAF50;
}

.post-date {
    font-size: 12px;
    color: #666;
}

.post-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.post-status.status-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.post-status.status-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    color: #856404;
}

.post-status.status-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
}

.post-status.status-secondary {
    background: linear-gradient(135deg, #e2e3e5 0%, #d6d8db 100%);
    color: #383d41;
}

.comments-count {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: bold;
    text-decoration: none;
    transition: all 0.3s ease;
}

.comments-count:hover {
    background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
    transform: scale(1.05);
    color: white;
}

.no-comments {
    color: #999;
    font-size: 11px;
}

#post-type-filter {
    padding: 6px 10px;
    border: 2px solid #e0e0e0;
    border-radius: 4px;
    background: white;
    font-size: 13px;
    margin-left: 10px;
}

/* مودال نوشته‌ها */
#scheduled-send-posts-modal .form-table th {
    width: 150px;
    padding: 15px 10px;
}

#scheduled-send-posts-modal .form-table td {
    padding: 15px 10px;
}

#scheduled-send-posts-modal input[type="number"] {
    border: 2px solid #e0e0e0;
    border-radius: 4px;
    padding: 6px 10px;
}

#scheduled-send-posts-modal input[type="number"]:focus {
    border-color: #4CAF50;
    outline: none;
}

/* Responsive برای نوشته‌ها */
@media (max-width: 768px) {

    .posts-table .column-image,
    .posts-table .column-author,
    .posts-table .column-date,
    .posts-table .column-comments {
        display: none;
    }

    .posts-table .column-title {
        width: 60%;
    }

    .posts-table .column-actions {
        width: 40%;
    }

    .actions-container .button {
        padding: 3px 6px;
        font-size: 10px;
    }

    .post-excerpt {
        display: none;
    }

    .row-actions {
        margin-top: 5px;
    }
}

@media (max-width: 480px) {
    .posts-table .column-status {
        display: none;
    }

    .posts-table .column-title {
        width: 70%;
    }

    .posts-table .column-actions {
        width: 30%;
    }

    .actions-container {
        flex-direction: column;
        gap: 2px;
    }

    .actions-container .button {
        width: 100%;
        justify-content: center;
        font-size: 9px;
        padding: 2px 4px;
    }

    .actions-container .dashicons {
        font-size: 10px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // تغییر خودکار فرم با تغییر نوع پست
    $('#post-type-filter').on('change', function() {
        $(this).closest('form').submit();
    });

    // بستن مودال نوشته‌ها
    $('#cancel-scheduled-send-posts, #scheduled-send-posts-modal .close').on('click', function() {
        $('#scheduled-send-posts-modal').hide();
    });
});
</script>