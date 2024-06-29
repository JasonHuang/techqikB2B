<?php
/*
Plugin Name: WC Order Amount
Plugin URI: http://techqik.com
Description: Set minimum and maximum amount limits for WooCommerce orders.
Version: 1.1
Author: Techqik
Author URI: http://techqik.com
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Add menu item for plugin settings
// add_action('admin_menu', 'wc_order_amount_menu');

// function wc_order_amount_menu() {
//     add_submenu_page(
//         'Order Amount Settings',
//         'OrderAmt',
//         'manage_options',
//         'wc-order-amount-settings',
//         'wc_order_amount_settings_page',
//         null,
//         99
//     );
// }

// Create settings page
function wc_order_amount_settings_page() {
    ?>
    <div class="wrap">
        <h1>Order Amount Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wc_order_amount_options_group');
            do_settings_sections('wc-order-amount-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'wc_order_amount_settings_init');

function wc_order_amount_settings_init() {
    register_setting('wc_order_amount_options_group', 'wc_order_amount_options', 'wc_order_amount_options_validate');
    add_settings_section('setting_section_id', 'Settings', 'wc_order_amount_section_text', 'wc-order-amount-settings');

    // Checkbox to enable/disable order amount restriction
    add_settings_field(
        'wc_enable_order_amount_limit',
        'Enable Order Amount Limit',
        'wc_enable_order_amount_limit_field',
        'wc-order-amount-settings',
        'setting_section_id'
    );

    add_settings_field(
        'wc_min_order_amount',
        'Minimum Order Amount',
        'wc_min_order_amount_field',
        'wc-order-amount-settings',
        'setting_section_id'
    );
    add_settings_field(
        'wc_max_order_amount',
        'Maximum Order Amount',
        'wc_max_order_amount_field',
        'wc-order-amount-settings',
        'setting_section_id'
    );
    // Checkbox to include/exclude shipping amount
    add_settings_field(
        'wc_include_shipping_amount',
        'Include Shipping Amount',
        'wc_include_shipping_amount_field',
        'wc-order-amount-settings',
        'setting_section_id'
    );
}

function wc_include_shipping_amount_field() {
    $options = get_option('wc_order_amount_options');
    $checked = isset($options['include_shipping']) && $options['include_shipping'] ? 'checked="checked"' : '';
    echo "<input id='wc_include_shipping_amount' name='wc_order_amount_options[include_shipping]' type='checkbox' value='1' $checked />";
}

function wc_enable_order_amount_limit_field() {
    $options = get_option('wc_order_amount_options');
    $checked = isset($options['enable_limit']) ? checked(1, $options['enable_limit'], false) : '';
    echo "<input id='wc_enable_order_amount_limit' name='wc_order_amount_options[enable_limit]' type='checkbox' value='1' $checked />";
}

function wc_order_amount_section_text() {
    echo '<p>Enter the minimum and maximum order amounts.</p>';
}

function wc_min_order_amount_field() {
    $options = get_option('wc_order_amount_options', array('min_order' => 100, 'max_order' => 800));
    $min_order = isset($options['min_order']) ? $options['min_order'] : 100;
    echo "<input id='wc_min_order_amount' name='wc_order_amount_options[min_order]' type='number' value='" . esc_attr($min_order) . "' style='width: 80px;' />";
}

function wc_max_order_amount_field() {
    $options = get_option('wc_order_amount_options', array('min_order' => 100, 'max_order' => 800));
    $max_order = isset($options['max_order']) ? $options['max_order'] : 800;
    echo "<input id='wc_max_order_amount' name='wc_order_amount_options[max_order]' type='number' value='" . esc_attr($max_order) . "' style='width: 80px;' />";
}

function wc_order_amount_options_validate($input) {
    $new_input = array();

    $new_input['enable_limit'] = isset($input['enable_limit']) ? 1 : 0;
    $new_input['include_shipping'] = isset($input['include_shipping']) ? 1 : 0;

    if (isset($input['min_order']))
        $new_input['min_order'] = absint($input['min_order']);

    if (isset($input['max_order']))
        $new_input['max_order'] = absint($input['max_order']);

    return $new_input;
}

// Implement minimum and maximum order amount restriction in the cart page
add_action( 'woocommerce_before_cart', 'wc_order_amount_cart_validation' );

function wc_order_amount_cart_validation() {
    $options = get_option('wc_order_amount_options', array('min_order' => 100, 'max_order' => 800, 'enable_limit' => 1));
    if (empty($options['enable_limit'])) {
        return; // If disabled, do nothing
    }

    $minimum = absint($options['min_order'] ?? 100);
    $maximum = absint($options['max_order'] ?? 800);
    $include_shipping = isset($options['include_shipping']) ? $options['include_shipping'] : 1; // Default to include shipping
    $cart_total = $include_shipping ? WC()->cart->total : WC()->cart->subtotal; // Consider shipping if selected by user

    if ($cart_total < $minimum || $cart_total > $maximum) {
        wc_print_notice(
            sprintf( 'Your order subtotal must be between %s and %s to proceed to checkout. Your current order total is %s.',
                wc_price( $minimum ),
                wc_price($maximum),
                wc_price( $cart_total )
            ), 'error'
        );
    }
}


// Prevent proceeding to checkout if the cart total is not within the allowed range
add_action( 'woocommerce_before_cart', 'wc_disable_proceed_to_checkout', 20 );
add_action( 'woocommerce_after_cart_table', 'wc_disable_proceed_to_checkout', 20 );

function wc_disable_proceed_to_checkout() {
    $options = get_option('wc_order_amount_options', array('min_order' => 100, 'max_order' => 800, 'enable_limit' => 0));
    if (empty($options['enable_limit'])) {
        // If the limits are not enabled, ensure the checkout button is fully functional
        echo "<style>.checkout-button { pointer-events: auto; opacity: 1; cursor: pointer; }</style>";
        return; // Exit function early if the limit is not enabled
    }

    $minimum = absint($options['min_order'] ?? 100);
    $maximum = absint($options['max_order'] ?? 800);
    $include_shipping = isset($options['include_shipping']) ? $options['include_shipping'] : 1; // Default to include shipping
    $cart_total = $include_shipping ? WC()->cart->total : WC()->cart->subtotal; // Consider shipping if selected by user

    // Apply styles conditionally based on cart total
    $disable_checkout = ($cart_total < $minimum || $cart_total > $maximum);
    ?>
    <style>
        .checkout-button {
            pointer-events: <?php echo $disable_checkout ? 'none' : 'auto'; ?>;
            opacity: <?php echo $disable_checkout ? '0.5' : '1'; ?>;
            cursor: <?php echo $disable_checkout ? 'not-allowed' : 'pointer'; ?>;
        }
    </style>
    <?php
}

// Add error messages and prevent checkout if conditions are not met
add_action( 'woocommerce_checkout_process', 'wc_order_amount_checkout_validation' );

function wc_order_amount_checkout_validation() {
    $options = get_option('wc_order_amount_options', array('min_order' => 100, 'max_order' => 800, 'enable_limit' => 1));
    if (empty($options['enable_limit'])) {
        return; // If disabled, do nothing
    }

    $minimum = absint($options['min_order'] ?? 100);
    $maximum = absint($options['max_order'] ?? 800);
    $include_shipping = isset($options['include_shipping']) ? $options['include_shipping'] : 1; // Default to include shipping
    $cart_total = $include_shipping ? WC()->cart->total : WC()->cart->subtotal; // Consider shipping if selected by user

    if ($cart_total < $minimum || $cart_total > $maximum) {
        wc_add_notice(
            sprintf( 'Your order subtotal must be between %s and %s to proceed to checkout. Your current order total is %s.',
                wc_price( $minimum ),
                wc_price($maximum),
                wc_price( $cart_total )
            ), 'error'
        );
    }
}

register_activation_hook(__FILE__, 'wc_order_amount_set_default_options');

function wc_order_amount_set_default_options() {
    $default_options = array(
        'min_order' => 100,
        'max_order' => 800,
        'enable_limit' => 1, // Enable by default
        'include_shipping' => 0 // Exclude shipping by default

    );
    add_option('wc_order_amount_options', $default_options);
}
