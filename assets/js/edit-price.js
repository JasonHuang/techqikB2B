jQuery(document).ready(function ($) {
    // 处理价格字段的失焦事件
    $('.editable-price').on('blur', function () {
        var priceElement = $(this);
        var newPrice = priceElement.text();
        var productId = priceElement.data('product-id');

        // AJAX请求更新价格
        $.ajax({
            url: ajaxurl,  // WordPress AJAX处理URL
            type: 'POST',
            data: {
                action: 'update_product_price',  // WordPress后端钩子标识
                product_id: productId,
                new_price: newPrice
            },
            success: function (response) {
                $('#price-update-message').text('Price updated successfully!').show().fadeOut(3000);
            },
            error: function () {
                $('#price-update-message').text('Failed to update price.').show().fadeOut(3000);
            }
        });
    });
});
