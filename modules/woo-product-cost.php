<?php
/**
 * Plugin Name: WooCommerce Product Cost
 * Description: Add a cost field to WooCommerce products and manage pricing.
 * Version: 1.1.0
 * Author: Techqik
 * Text Domain: woo-product-cost
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add cost field to product
function add_cost_field() {
    global $woocommerce, $post;
    echo '<div class="options_group">';
    woocommerce_wp_text_input(
        array(
            'id' => '_cost',
            'label' => __('Cost(CNY)', 'woocommerce'),
            'placeholder' => __('Enter the cost of the product in CNY', 'woocommerce'),
            'desc_tip' => 'true',
            'description' => __('Enter the cost of the product in Chinese Yuan (CNY).', 'woocommerce'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min' => '0',
            ),
        )
    );
    echo '</div>';
}
add_action('woocommerce_product_options_general_product_data', 'add_cost_field');

// Save cost field value
function save_cost_field($product_id) {
    $cost = isset($_POST['_cost']) ? sanitize_text_field($_POST['_cost']) : '';
    update_post_meta($product_id, '_cost', $cost);
}
add_action('woocommerce_process_product_meta', 'save_cost_field');

function add_cost_field_to_variations($loop, $variation_data, $variation) {
    woocommerce_wp_text_input(
        array(
            'id' => 'variable_cost[' . $variation->ID . ']', // 注意这里的变化
            'name' => 'variable_cost[' . $variation->ID . ']',
            'label' => __('Cost(CNY)', 'woocommerce'),
            'desc_tip' => 'true',
            'description' => __('Enter the cost of the variation in Chinese Yuan (CNY).', 'woocommerce'),
            'value' => get_post_meta($variation->ID, '_cost', true), // 使用 '_cost' 作为 meta key
            'type' => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min' => '0',
            ),
        )
    );
}
add_action('woocommerce_variation_options_pricing', 'add_cost_field_to_variations', 10, 3);

// 保存变体成本
function save_variation_cost_field($variation_id, $i) {
    $variable_cost = $_POST['variable_cost'][$variation_id] ?? '';
    if ('' !== $variable_cost) {
        update_post_meta($variation_id, '_cost', wc_clean($variable_cost));
    }
}
add_action('woocommerce_save_product_variation', 'save_variation_cost_field', 10, 2);


// General settings page content
function techqik_general_setting_page(){
    ?>
    <div class="wrap">
        <h1>WooCommerce Product Cost Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('techqik_general_options_group');
            do_settings_sections('techqik-general-settings');
            submit_button('Save Settings and Update Prices');
            ?>
        </form>
    </div>
    <?php
}

// Initialize general settings
function techqik_general_setting_init(){
    register_setting('techqik_general_options_group', 'techqik_general_options', 'techqik_general_options_validate');
    add_settings_section('techqik_general_setting_section', 'Pricing Settings', 'techqik_general_section_text', 'techqik-general-settings');
    
    add_settings_field(
        'techqik_general_profit',
        'Profit (USD)',
        'techqik_general_profit_field',
        'techqik-general-settings',
        'techqik_general_setting_section'
    );

    add_settings_field(
        'techqik_general_exchange_rate',
        'USD to CNY Exchange Rate',
        'techqik_general_exchange_rate_field',
        'techqik-general-settings',
        'techqik_general_setting_section'
    );

    add_settings_field(
        'techqik_regular_price_ratio',
        'Regular Price Ratio',
        'techqik_regular_price_ratio_field',
        'techqik-general-settings',
        'techqik_general_setting_section'
    );
}
add_action('admin_init', 'techqik_general_setting_init');

// Add this function to create the new setting field
function techqik_regular_price_ratio_field() {
    $options = get_option('techqik_general_options');
    $ratio = isset($options['regular_price_ratio']) ? $options['regular_price_ratio'] : 1.5;
    echo "<input id='techqik_regular_price_ratio' name='techqik_general_options[regular_price_ratio]' type='number' step='0.01' value='" . esc_attr($ratio) . "' style='width: 80px;' />";
    echo "<p class='description'>The ratio to calculate regular price from sale price. E.g., 1.5 means regular price will be 50% higher than sale price.</p>";
}

// General profit field
function techqik_general_profit_field(){
    $options = get_option('techqik_general_options');
    $profit = isset($options['general_profit']) ? $options['general_profit'] : '8';
    echo "<input id='techqik_general_profit' name='techqik_general_options[general_profit]' type='number' step='0.01' value='" . esc_attr($profit) . "' style='width: 80px;' />";
    echo "<p class='description'>The profit amount in USD to be added to the cost price.</p>";
}

function techqik_general_exchange_rate_field() {
    $options = get_option('techqik_general_options');
    $exchange_rate = isset($options['general_exchange_rate']) ? $options['general_exchange_rate'] : 7.1;
    echo "<input id='techqik_general_exchange_rate' name='techqik_general_options[general_exchange_rate]' type='number' step='0.0001' value='" . esc_attr($exchange_rate) . "' style='width: 80px;' />";
    echo "<p class='description'>The current USD to CNY exchange rate. You will benefit when this rate is less than the current market rate.</p>";
}

// Validate general options
function techqik_general_options_validate($input) {
    $new_input = array();
    $new_input['general_profit'] = floatval($input['general_profit']);
    $new_input['general_exchange_rate'] = floatval($input['general_exchange_rate']);
    $new_input['regular_price_ratio'] = floatval($input['regular_price_ratio']);

    // Schedule the price update
    wp_schedule_single_event(time() + 3, 'techqik_update_product_prices_action');
    
    return $new_input;
}

add_action('techqik_update_product_prices_action', 'techqik_update_product_prices');

// General section text
function techqik_general_section_text() {
    echo '<p>Set the profit and exchange rate to automatically update product prices. The new price will be calculated as: (Cost in CNY / Exchange Rate) + Profit in USD.</p>';
}

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
            // $cost_cny = get_post_meta($product->get_id(), '_cost', true);
            $sku = $product->get_sku();

            // if (!empty($cost_cny)) {
                // $new_sale_price = round(($cost_cny / $general_exchange_rate) + $profit, 2);

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
                
            // } else {
            //     error_log("WooCommerce Product Cost: Skipped product (no cost): ID: {$product->get_id()}, SKU: $sku");
            //     $skipped_count++;
            // }
        } catch (Exception $e) {
            error_log("WooCommerce Product Cost: Error updating product ID: {$product->get_id()}, SKU: $sku. Error: " . $e->getMessage());
            $error_count++;
        } finally {
        // Always delete the transient, even if an error occurred
        delete_transient('techqik_price_update_in_progress');
    }
    }

    error_log("WooCommerce Product Cost: Price update process completed. Updated: $updated_count, Errors: $error_count");
    
    // Delete the transient to indicate the update is complete
    delete_transient('techqik_price_update_in_progress');
    
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

// Add settings page to menu
// function techqik_add_admin_menu() {
//     add_options_page('WooCommerce Product Cost', 'Product Cost', 'manage_options', 'woo-product-cost', 'techqik_general_setting_page');
// }
// add_action('admin_menu', 'techqik_add_admin_menu');