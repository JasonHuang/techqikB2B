jQuery(document).ready(function ($) {
    $('.editable-cost').on('blur', function () {
        var costElement = $(this);
        var newCost = costElement.text();
        var productId = costElement.data('product-id');

        $.ajax({
            url: editPriceData.ajaxurl,  // 使用从PHP传递的ajaxurl
            type: 'POST',
            data: {
                action: 'update_product_cost',
                product_id: productId,
                new_cost: newCost,
                security: editPriceData.nonce  // 使用从PHP传递的nonce
            },
            success: function (response) {
                showMessage('Cost updated successfully!');
            },
            error: function () {
                showMessage('Failed to update cost.');
            }
        });
    });
});

function showMessage(message) {
    var overlay = document.querySelector('.overlay');
    var messageBox = document.querySelector('.message-box');

    messageBox.textContent = message; 
    overlay.style.display = 'block'; 

    setTimeout(function () {
        overlay.style.display = 'none';
    }, 2000);
}


document.addEventListener('DOMContentLoaded', function () {
    var editableCosts = document.querySelectorAll('.editable-cost');

    editableCosts.forEach(function (element) {
        element.addEventListener('focus', function () {
            this.contentEditable = true;
            var range = document.createRange();
            var selection = window.getSelection();

            selection.removeAllRanges();
            range.selectNodeContents(this);
            selection.addRange(range);
        });

        element.addEventListener('blur', function () {
            this.contentEditable = false;
            window.getSelection().removeAllRanges();
        });
    });
});


