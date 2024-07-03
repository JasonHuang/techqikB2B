(function ($) {
    $(document).ready(function () {
        $('.editable-field').on('focus', function () {
            if (typeof $(this).data('original-value') === 'undefined') {
                $(this).data('original-value', $(this).text());
            }
            var range = document.createRange();
            var selection = window.getSelection();
            selection.removeAllRanges();
            range.selectNodeContents(this);
            selection.addRange(range);
            // $(this).attr('contentEditable', true);
        });

        $('.editable-field').on('blur', function () {
            // $(this).attr('contentEditable', false);
            window.getSelection().removeAllRanges();

            var costElement = $(this);
            var field = costElement.data('field');
            var originalValue = costElement.data('original-value').toString();
            var newValue = costElement.text().trim();

            if (originalValue === newValue) {
                return;
            }

            $.ajax({
                url: editPriceData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_product_cost',
                    product_id: costElement.data('product-id'),
                    field:field,
                    new_value: newValue,
                    security: editPriceData.nonce
                },
                success: function (response) {
                    // showMessage('Cost updated successfully!');
                },
                error: function () {
                    showMessage('Failed to update cost.');
                }
            });
        });
    });

    // 确保showMessage函数也在IIFE内
    function showMessage(message) {
        var overlay = $('.overlay');
        var messageBox = $('.message-box');

        messageBox.text(message);
        overlay.show();

        setTimeout(function () {
            overlay.hide();
        }, 1000);
    }
})(jQuery);
