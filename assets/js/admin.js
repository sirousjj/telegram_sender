/**
 * اسکریپت ادمین افزونه ارسال به تلگرام
 * 
 * @package TelegramSender
 * @author اصغر معینی <as.moini@gmail.com>
 */
// تست اولیه - این باید در کنسول نمایش داده شود
// console.log('=== TELEGRAM SENDER DEBUG START ===');
// console.log('jQuery loaded:', typeof jQuery !== 'undefined');
// console.log('$ loaded:', typeof $ !== 'undefined');
// console.log('Current URL:', window.location.href);

jQuery(document).ready(function($) {
    'use strict';
    
    // متغیرهای سراسری
    var ajaxUrl = telegram_sender_ajax.ajax_url;
    var nonce = telegram_sender_ajax.nonce;
    var strings = telegram_sender_ajax.strings; // اضافه شده
    var originalValues = {};
    
    // console.log('Telegram Sender Admin JS loaded');
    // console.log('AJAX URL:', ajaxUrl);
    // console.log('Nonce:', nonce);
    
    // توابع کمکی
    function showNotice(message, type) {
        var noticeClass = 'notice-' + type;
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // حذف خودکار پس از 5 ثانیه
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // اسکرول به بالا
        $('html, body').animate({scrollTop: 0}, 300);
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
    
    function updateSendCount(productId) {
        var $row = $('tr[data-product-id="' + productId + '"]');
        var $sendCount = $row.find('.send-count');
        var currentCount = parseInt($sendCount.text()) || 0;
        
        $sendCount.text(currentCount + 1);
        
        // بروزرسانی آخرین ارسال
        var $lastSent = $row.find('.last-sent');
        var now = new Date();
        var persianDate = formatPersianDate(now);
        
        if ($lastSent.length) {
            $lastSent.text(persianDate).attr('title', now.toISOString());
        } else {
            $row.find('.never-sent').replaceWith('<span class="last-sent" title="' + now.toISOString() + '">' + persianDate + '</span>');
        }
    }
    
    function updatePostSendCount(postId) {
        var $row = $('tr[data-post-id="' + postId + '"]');
        var $sendCount = $row.find('.send-count');
        var currentCount = parseInt($sendCount.text()) || 0;
        
        $sendCount.text(currentCount + 1);
        
        // بروزرسانی آخرین ارسال
        var $lastSent = $row.find('.last-sent');
        var now = new Date();
        var persianDate = formatPersianDate(now);
        
        if ($lastSent.length) {
            $lastSent.text(persianDate).attr('title', now.toISOString());
        } else {
            $row.find('.never-sent').replaceWith('<span class="last-sent" title="' + now.toISOString() + '">' + persianDate + '</span>');
        }
    }
    
    function formatPersianDate(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        var hours = String(date.getHours()).padStart(2, '0');
        var minutes = String(date.getMinutes()).padStart(2, '0');
        
        return year + '/' + month + '/' + day + ' ' + hours + ':' + minutes;
    }
    
    function checkForChanges($row) {
        var hasChanges = $row.find('[contenteditable="true"].changed').length > 0;
        
        if (!hasChanges) {
            $row.removeClass('editing');
            $row.find('.save-changes').hide();
        }
    }
    
    // بررسی وجود دکمه‌ها
    // console.log('Number of .send-product buttons:', $('.send-product').length);
    // console.log('Number of .send-post buttons:', $('.send-post').length);
    // console.log('Number of #test-connection buttons:', $('#test-connection').length);
    
    // تست کلیک روی هر دکمه
    $(document).on('click', '*', function(e) {
        var $target = $(e.target);
        if ($target.hasClass('send-product') || $target.hasClass('send-post') || $target.attr('id') === 'test-connection') {
            // console.log('Button clicked:', $target.get(0));
            // console.log('Button classes:', $target.attr('class'));
            // console.log('Button data:', $target.data());
        }
    });
    
    // ارسال تک محصول - با debug کامل
    $(document).on('click', '.send-product', function(e) {
        // console.log('=== SEND PRODUCT CLICKED ===');
        e.preventDefault();
        
        var $button = $(this);
        // console.log('Button element:', $button.get(0));
        
        var productId = $button.data('product-id');
        // console.log('Product ID:', productId);
        // console.log('Product ID type:', typeof productId);
        
        if (!productId) {
            console.error('Product ID is empty or undefined');
            alert('شناسه محصول نامعتبر است');
            return;
        }
        
        var originalText = $button.html();
        // console.log('Original button text:', originalText);
        
        $button.html('<span class="spinner"></span> در حال ارسال...').prop('disabled', true);
        // console.log('Button state changed to loading');
        
        var ajaxData = {
            action: 'telegram_send_product',
            product_id: productId,
            nonce: nonce
        };
        // console.log('AJAX data to send:', ajaxData);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            beforeSend: function() {
                // console.log('AJAX request starting...');
            },
            success: function(response) {
                // console.log('=== AJAX SUCCESS ===');
                console.log('Response:', response);
                // console.log('Response type:', typeof response);
                
                if (response && response.success) {
                    // console.log('Success message:', response.data);
                    showNotice(strings.product_sent_success, 'success');
                    updateSendCount(productId);
                } else {
                    // console.log('Error message:', response ? response.data : 'No response data');
                    showNotice(response.data || strings.unknown_error, 'error');
                }
            },
            error: function(xhr, status, error) {
                // console.log('=== AJAX ERROR ===');
                // console.log('XHR:', xhr);
                // console.log('Status:', status);
                // console.log('Error:', error);
                console.log('Response Text:', xhr.responseText);
                
                showNotice(strings.request_error + ': ' + error, 'error');
            },
            complete: function() {
                // console.log('AJAX request completed');
                $button.html(originalText).prop('disabled', false);
                // console.log('Button restored to original state');
            }
        });
    });
    
    // ارسال تک نوشته - با debug کامل
    $(document).on('click', '.send-post', function(e) {
        // console.log('=== SEND POST CLICKED ===');
        e.preventDefault();
        
        var $button = $(this);
        // console.log('Button element:', $button.get(0));
        
        var postId = $button.data('post-id');
        // console.log('Post ID:', postId);
        // console.log('Post ID type:', typeof postId);
        
        if (!postId) {
            console.error('Post ID is empty or undefined');
            alert('شناسه نوشته نامعتبر است');
            return;
        }
        
        var originalText = $button.html();
        // console.log('Original button text:', originalText);
        
        $button.html('<span class="spinner"></span> در حال ارسال...').prop('disabled', true);
        // console.log('Button state changed to loading');
        
        var ajaxData = {
            action: 'telegram_send_post',
            post_id: postId,
            nonce: nonce
        };
        // console.log('AJAX data to send:', ajaxData);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            beforeSend: function() {
                // console.log('AJAX request starting...');
            },
            success: function(response) {
                // console.log('=== AJAX SUCCESS ===');
                console.log('Response:', response);
                // console.log('Response type:', typeof response);
                
                if (response && response.success) {
                    // console.log('Success message:', response.data);
                    showNotice(strings.post_sent_success, 'success'); // Need to add post_sent_success string
                    updatePostSendCount(postId);
                } else {
                    // console.log('Error message:', response ? response.data : 'No response data');
                    showNotice(response.data || strings.unknown_error, 'error');
                }
            },
            error: function(xhr, status, error) {
                // console.log('=== AJAX ERROR ===');
                // console.log('XHR:', xhr);
                // console.log('Status:', status);
                // console.log('Error:', error);
                console.log('Response Text:', xhr.responseText);
                
                showNotice(strings.request_error + ': ' + error, 'error');
            },
            complete: function() {
                // console.log('AJAX request completed');
                $button.html(originalText).prop('disabled', false);
                // console.log('Button restored to original state');
            }
        });
    });
    
    // ارسال همه محصولات (فوری)
    $('#send-all-products').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(strings.confirm_send_all_products)) {
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('در حال ارسال...').prop('disabled', true);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'telegram_send_all_products',
                send_now: 'true',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                    location.reload();
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                showNotice(strings.request_error, 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // ارسال برنامه‌ریزی شده محصولات
    $('#send-all-scheduled').on('click', function(e) {
        e.preventDefault();
        $('#scheduled-send-modal').show();
    });
    
    // تایید ارسال برنامه‌ریزی شده محصولات
    $('#confirm-scheduled-send').on('click', function() {
        var interval = $('#send-interval').val();
        var startTime = $('#send-start-time').val();
        var endTime = $('#send-end-time').val();
        var excludeOutOfStock = $('#exclude-out-of-stock').is(':checked') ? '1' : '0';
        var onlyUnsent = $('#only-unsent').is(':checked') ? '1' : '0';
        var onlyPriceUpdated = $('#only-price-updated').is(':checked') ? '1' : '0';
        
        if (!interval || interval < 1) {
            showNotice(strings.invalid_time_interval, 'warning');
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('در حال ارسال...').prop('disabled', true);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'telegram_send_all_products',
                interval: interval,
                start_time: startTime,
                end_time: endTime,
                exclude_out_of_stock: excludeOutOfStock,
                only_unsent: onlyUnsent,
                only_price_updated: onlyPriceUpdated,
                send_now: 'false',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                    $('#scheduled-send-modal').hide();
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                showNotice(strings.request_error, 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // ارسال تک نوشته
    $(document).on('click', '.send-post', function(e) {
        e.preventDefault();
        
        // console.log('Send post button clicked');
        
        var $button = $(this);
        var postId = $button.data('post-id');
        
        // console.log('Post ID:', postId);
        
        if (!postId) {
            alert('شناسه نوشته نامعتبر است');
            return;
        }
        
        var originalText = $button.html();
        $button.html('<span class="spinner"></span> در حال ارسال...').prop('disabled', true);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'telegram_send_post',
                post_id: postId,
                nonce: nonce
            },
            success: function(response) {
                // console.log('Response:', response);
                
                if (response.success) {
                    showNotice(strings.post_sent_success, 'success'); // Need to add post_sent_success string
                    updatePostSendCount(postId);
                } else {
                    showNotice(response.data || strings.unknown_error, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', error);
                showNotice(strings.request_error + ': ' + error, 'error');
            },
            complete: function() {
                $button.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // ارسال همه نوشته‌ها
    $('#send-all-posts').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(strings.confirm_send_all_posts)) {
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('در حال ارسال...').prop('disabled', true);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'telegram_send_all_posts',
                send_now: 'true',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                    location.reload();
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                showNotice(strings.request_error, 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // ارسال برنامه‌ریزی شده نوشته‌ها
    $('#send-all-posts-scheduled').on('click', function(e) {
        e.preventDefault();
        $('#scheduled-send-posts-modal').show();
    });
    
    // تایید ارسال برنامه‌ریزی شده نوشته‌ها
    $('#confirm-scheduled-send-posts').on('click', function() {
        var interval = $('#send-posts-interval').val();
        var startTime = $('#send-posts-start-time').val();
        var endTime = $('#send-posts-end-time').val();
        
        if (!interval || interval < 1) {
            showNotice(strings.invalid_time_interval, 'warning');
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('در حال ارسال...').prop('disabled', true);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'telegram_send_all_posts',
                interval: interval,
                start_time: startTime,
                end_time: endTime,
                send_now: 'false',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                    $('#scheduled-send-posts-modal').hide();
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                showNotice(strings.request_error, 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // همگام‌سازی محصولات
    $('#sync-all-products').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('در حال همگام‌سازی...').prop('disabled', true);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'telegram_sync_products',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data, 'success');
                    location.reload();
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                showNotice(strings.request_error, 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // تست اتصال
    $('#test-connection').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('در حال تست...').prop('disabled', true);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'telegram_test_connection',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(strings.test_connection_success, 'success'); // Need to add test_connection_success string
                } else {
                    showNotice(strings.test_connection_error + ': ' + response.data, 'error'); // Need to add test_connection_error string
                }
            },
            error: function() {
                showNotice(strings.request_error, 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // ویرایش درجا - شروع ویرایش
    $(document).on('focus', '[contenteditable="true"]', function() {
        var $element = $(this);
        var field = $element.data('field');
        var productId = $element.closest('tr').data('product-id');
        var key = productId + '_' + field;
        
        originalValues[key] = $element.text().trim();
        $element.closest('tr').addClass('editing');
        $element.closest('tr').find('.save-changes').show();
    });
    
    // ویرایش درجا - پایان ویرایش
    $(document).on('blur', '[contenteditable="true"]', function() {
        var $element = $(this);
        var field = $element.data('field');
        var productId = $element.closest('tr').data('product-id');
        var key = productId + '_' + field;
        var newValue = $element.text().trim();
        
        if (originalValues[key] !== newValue) {
            $element.addClass('changed');
        } else {
            $element.removeClass('changed');
            checkForChanges($element.closest('tr'));
        }
    });
    
    // کلیدهای میانبر در ویرایش
    $(document).on('keydown', '[contenteditable="true"]', function(e) {
        if (e.keyCode === 13) { // Enter
            e.preventDefault();
            $(this).blur();
        }
        
        if (e.keyCode === 27) { // Escape
            var $element = $(this);
            var field = $element.data('field');
            var productId = $element.closest('tr').data('product-id');
            var key = productId + '_' + field;
            
            $element.text(originalValues[key]);
            $element.blur();
        }
    });
    
    // ذخیره تغییرات
    $(document).on('click', '.save-changes', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $row = $button.closest('tr');
        var productId = $row.data('product-id');
        var changes = {};
        
        $row.find('[contenteditable="true"].changed').each(function() {
            var $element = $(this);
            var field = $element.data('field');
            changes[field] = $element.text().trim();
        });
        
        if (Object.keys(changes).length === 0) {
            showNotice(strings.no_changes_to_save, 'info');
            return;
        }
        
        var originalText = $button.html();
        $button.html('<span class="spinner"></span> در حال ذخیره...').prop('disabled', true);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'telegram_update_product',
                product_id: productId,
                changes: changes,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(strings.changes_saved, 'success');
                    $row.find('[contenteditable="true"]').removeClass('changed');
                    $row.removeClass('editing');
                    $button.hide();
                    
                    // بروزرسانی originalValues
                    $row.find('[contenteditable="true"]').each(function() {
                        var $element = $(this);
                        var field = $element.data('field');
                        var key = productId + '_' + field;
                        originalValues[key] = $element.text().trim();
                    });
                } else {
                    showNotice(response.data, 'error');
                }
            },
            error: function() {
                showNotice(strings.save_error, 'error');
            },
            complete: function() {
                $button.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // بستن مودال‌ها
    $('.close, #cancel-scheduled-send, #cancel-scheduled-send-posts').on('click', function() {
        $('.modal').hide();
    });
    
    // بستن مودال با کلیک خارج از آن
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('modal')) {
            $('.modal').hide();
        }
    });
    
    // جلوگیری از بستن مودال با کلیک داخل آن
    $('.modal-content').on('click', function(e) {
        e.stopPropagation();
    });
    
    // کلیدهای میانبر سراسری
    $(document).on('keydown', function(e) {
        // Ctrl+S برای ذخیره تغییرات
        if (e.ctrlKey && e.keyCode === 83) {
            e.preventDefault();
            var $saveButton = $('.save-changes:visible').first();
            if ($saveButton.length) {
                $saveButton.click();
            }
        }
        
        // Escape برای بستن مودال‌ها و لغو تغییرات
        if (e.keyCode === 27) {
            $('.modal:visible').hide();
            
            $('[contenteditable="true"]:focus').each(function() {
                var $element = $(this);
                var field = $element.data('field');
                var productId = $element.closest('tr').data('product-id');
                var key = productId + '_' + field;
                
                if (originalValues[key] !== undefined) {
                    $element.text(originalValues[key]);
                }
                $element.blur();
            });
        }
    });
    
});

console.log('=== TELEGRAM SENDER DEBUG END ===');