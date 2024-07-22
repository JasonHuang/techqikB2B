<?php

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
    // Check if an update is already in progress
    if (get_transient('techqik_price_update_in_progress')) {
        error_log("WooCommerce Product Cost: Price update already in progress. Skipping this request.");
        return;
    }

    // Set the transient to indicate an update is in progress
    set_transient('techqik_price_update_in_progress', true, 5 * MINUTE_IN_SECONDS);

    error_log("WooCommerce Product Cost: Price update process started");
    $options = get_option('techqik_general_options');
    $profit = isset($options['general_profit']) ? floatval($options['general_profit']) : 8;
    $general_exchange_rate = isset($options['general_exchange_rate']) ? floatval($options['general_exchange_rate']) : 7.1;

    error_log("WooCommerce Product Cost: Profit: $profit USD, Exchange Rate: $general_exchange_rate CNY/USD");

    $products = wc_get_products(array('limit' => -1));
    $updated_count = 0;
    $error_count = 0;

    foreach ($products as $product) {
        try {
            $sku = $product->get_sku();

            if ($product->is_type('variable')) {
                $variations = $product->get_children();
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    techqik_update_product_prices_helper($variation, $profit, $general_exchange_rate);
                    $updated_count++;
                }
            } 
            techqik_update_product_prices_helper($product, $profit, $general_exchange_rate);
            $updated_count++;

        } catch (Exception $e) {
            error_log("WooCommerce Product Cost: Error updating product ID: {$product->get_id()}, SKU: $sku. Error: " . $e->getMessage());
            $error_count++;
        } finally {
            // Always delete the transient, even if an error occurred
            delete_transient('techqik_price_update_in_progress');
        }
    }

    error_log("WooCommerce Product Cost: Price update process completed. Updated: $updated_count, Errors: $error_count");

    update_option('techqik_price_update_notice', "Successfully updated prices for $updated_count products. Errors: $error_count.");
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


// Display the notice
add_action('admin_notices', 'techqik_display_price_update_notice');

function techqik_display_price_update_notice() {
    if ($notice = get_option('techqik_price_update_notice')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($notice); ?></p>
        </div>
        <?php
        delete_option('techqik_price_update_notice');
    }

    if (techqik_is_price_update_in_progress()) {
        ?>
        <div class="notice notice-warning">
            <p><?php _e('Product price update is currently in progress. This may take a few minutes.', 'woo-product-cost'); ?></p>
        </div>
        <?php
    }
}

// Function to check if price update is in progress
function techqik_is_price_update_in_progress() {
    return get_transient('techqik_price_update_in_progress');
}