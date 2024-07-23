<?php

require_once TECHQIKB2B_PATH . 'includes/functions/techqik-lock.php';


function techqikb2b_update_product_price($product_id, $profit, $general_exchange_rate) {
    $product = wc_get_product($product_id);
    if ($product) {
        try {
            $sku = $product->get_sku();

            if ($product->is_type('variable')) {
                $variations = $product->get_children();
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    techqik_update_product_prices_helper($variation, $profit, $general_exchange_rate);
                }
            } 
            techqik_update_product_prices_helper($product, $profit, $general_exchange_rate);

        } catch (Exception $e) {
            error_log("WooCommerce Product Cost: Error updating product ID: {$product->get_id()}, SKU: $sku. Error: " . $e->getMessage());
        }
    }
}

add_action('techqik_update_product_prices_action', 'techqik_update_product_prices');

// The function to update product prices
function techqik_update_product_prices() {

    error_log("WooCommerce Product Cost: Price update process started");
    $options = get_option('techqik_general_options');
    $profit = isset($options['general_profit']) ? floatval($options['general_profit']) : 8;
    $general_exchange_rate = isset($options['general_exchange_rate']) ? floatval($options['general_exchange_rate']) : 7.1;

    $products = wc_get_products(array('limit' => -1));
    $total = count($products);
    $updated = 0;
    $error_count = 0;

    foreach ($products as $product) {
        try {
            $sku = $product->get_sku();

            if ($product->is_type('variable')) {
                $variations = $product->get_children();
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    techqik_update_product_prices_helper($variation, $profit, $general_exchange_rate);
                }
            } 
            techqik_update_product_prices_helper($product, $profit, $general_exchange_rate);
            $updated++;

            update_option('techqik_price_update_progress', array(
                'total' => $total,
                'updated' => $updated,
                'percentage' => round(($updated / $total) * 100, 2)
            ));
        } catch (Exception $e) {
            error_log("WooCommerce Product Cost: Error updating product ID: {$product->get_id()}, SKU: $sku. Error: " . $e->getMessage());
            $error_count++;
        }
    }
    error_log("WooCommerce Product Cost: Price update process completed. Updated: $updated, Errors: $error_count");

    update_option('techqik_price_update_progress', "Successfully updated prices for $updated products. Errors: $error_count.");
    techqik_release_update_lock();
    delete_option('techqik_price_update_progress');
}

function techqik_update_product_prices_helper($product, $profit, $exchange_rate) {
    $cost_cny = get_post_meta($product->get_id(), '_cost', true);
    if (empty($cost_cny)){
        return;
    }

    $new_sale_price = round(($cost_cny / $exchange_rate) + $profit, 2);

    $product_id = $product->get_id();
    $sku = $product->get_sku();
    $product_type = $product->get_type();

    $old_regular_price = $product->get_regular_price();
    $old_sale_price = $product->get_sale_price();

    // Get the regular price ratio from settings (default to 1.5 if not set)
    $options = get_option('techqik_general_options');
    $regular_price_ratio = isset($options['regular_price_ratio']) ? floatval($options['regular_price_ratio']) : 1.5;

    // Calculate new regular price
    $new_regular_price = round($new_sale_price * $regular_price_ratio, 2);

    // Set new prices
    $product->set_regular_price($new_regular_price);
    $product->set_sale_price($new_sale_price);

    // Set the product's actual price to the sale price
    $product->set_price($new_sale_price);

    $product->save();

    error_log(sprintf(
        "WooCommerce Product Cost: Updated product: ID: %d, SKU: %s, Type: %s, Old Regular: %f, New Regular: %f, Old Sale: %s, New Sale: %f",
        $product_id,
        $sku,
        $product_type,
        $old_regular_price,
        $new_regular_price,
        $old_sale_price ?: 'N/A',
        $new_sale_price
    ));
}

add_action('wp_ajax_get_price_update_progress', 'techqik_get_price_update_progress');

function techqik_get_price_update_progress() {
    $progress = get_option('techqik_price_update_progress', array('percentage' => 0));
    wp_send_json($progress);
}

function techqik_display_update_progress() {
    if ($notice = get_option('techqik_price_update_notice')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($notice); ?></p>
        </div>
        <?php
        delete_option('techqik_price_update_notice');
    }

    if (techqik_is_update_locked()) {
        $progress = get_option('techqik_price_update_progress', array('percentage' => 0));
        ?>
        <div id="techqik-update-progress" class="notice notice-warning">
            <p><?php _e('Product price update is currently in progress. Progress: ', 'woo-product-cost'); ?><span id="price-update-progress"><?php echo $progress['percentage']; ?></span>%</p>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var lastProgress = -1;
            var noProgressCount = 0;
            var maxNoProgressAttempts = 5;

            function updateProgress() {
                $.ajax({
                    url: ajaxurl,
                    data: {
                        'action': 'get_price_update_progress'
                    },
                    success: function(response) {
                        var $notice = $('#techqik-update-progress');
                        var $progressSpan = $('#price-update-progress');
                        
                        if (response && typeof response.percentage !== 'undefined') {
                            var currentProgress = parseFloat(response.percentage);
                            
                            if (currentProgress === 100 || (currentProgress === 0 && lastProgress > 0)) {
                                // 进度到达100%或突然回到0%（可能表示更新完成）
                                $notice.fadeOut('slow', function() {
                                    $(this).remove();
                                });
                                return;
                            }

                            if (currentProgress === lastProgress) {
                                noProgressCount++;
                            } else {
                                noProgressCount = 0;
                            }

                            if (noProgressCount >= maxNoProgressAttempts) {
                                // 多次没有进度变化，可能更新已完成
                                $notice.fadeOut('slow', function() {
                                    $(this).remove();
                                });
                                return;
                            }

                            $progressSpan.text(currentProgress);
                            lastProgress = currentProgress;
                            
                            setTimeout(updateProgress, 1000);
                        } else {
                            // 响应中没有百分比数据，可能更新已完成
                            $notice.fadeOut('slow', function() {
                                $(this).remove();
                            });
                        }
                    },
                    error: function() {
                        // AJAX请求失败，可能是因为后端数据已被删除
                        $('#techqik-update-progress').fadeOut('slow', function() {
                            $(this).remove();
                        });
                    }
                });
            }
            
            updateProgress();
        });
        </script>
        <?php
    }
}

// 在适当的管理页面中调用显示函数
add_action('admin_notices', 'techqik_maybe_display_update_progress');
function techqik_maybe_display_update_progress() {
    // 确保只在相关的管理页面显示
    $screen = get_current_screen();
    error_log("screen id:$screen->id");
    if ($screen->id === 'techqikb2b_page_techqikb2b_product_bulk_update') {
        techqik_display_update_progress();
    }
}