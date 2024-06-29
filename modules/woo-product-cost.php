<?php
/**
 * Plugin Name: WooCommerce Product Cost
 * Description: Add a cost field to WooCommerce products.
 * Version: 1.0.0
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
            'label' => __('Cost', 'woocommerce'),
            'placeholder' => __('Enter the cost of the product', 'woocommerce'),
            'desc_tip' => 'true',
            'description' => __('Enter the cost of the product here.', 'woocommerce'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min' => '0',
            ),
        )
    );
    echo '</div>';
}
add_action('woocommerce_product_options_pricing', 'add_cost_field');

// Save cost field value
function save_cost_field($product_id) {
    $cost = isset($_POST['_cost']) ? sanitize_text_field($_POST['_cost']) : '';
    update_post_meta($product_id, '_cost', $cost);
}
add_action('woocommerce_process_product_meta', 'save_cost_field');
