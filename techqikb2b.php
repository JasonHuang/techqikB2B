<?php
/**
 * Plugin Name: TechqikB2B
 * Description: A WooCommerce plugin to transform your site to B2B site.
 * Version: 1.0.0
 * Author: Techqik
 * Text Domain: techqik-b2b
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants
define('TECHQIKB2B_PATH', plugin_dir_path(__FILE__));
define('TECHQIKB2B_URL', plugin_dir_url(__FILE__));

// Load modules
require_once TECHQIKB2B_PATH . 'modules/wc-order-amount.php';
require_once TECHQIKB2B_PATH . 'modules/weight-based-shipping.php';
require_once TECHQIKB2B_PATH . 'modules/woo-product-cost.php';
require_once TECHQIKB2B_PATH . 'modules/products-bulk-update.php';

// Add admin menu
function techqikb2b_add_admin_menu() {
    // Add main menu
    add_menu_page('TechqikB2B', 'TechqikB2B', 'manage_options', 'techqikb2b_main_menu', 'techqikb2b_main_page', 'dashicons-businessman', 6);

    // Add submenu for Product Bulk Update
    $hook = add_submenu_page('techqikb2b_main_menu', 'Product Bulk Update', 'Product Bulk Update', 'manage_options', 'techqikb2b_product_bulk_update', 'bulk_update_products_page');
    add_action("load-$hook", 'set_bulk_update_products_screen_options');
}
add_action('admin_menu', 'techqikb2b_add_admin_menu');

// Main admin page content
function techqikb2b_main_page() {
    ?>
    <div class="wrap">
        <h1>Settings</h1>
        <?php settings_errors(); ?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=techqikb2b_main_menu&tab=general" class="nav-tab <?php echo wbs_active_tab('general'); ?>">General</a>
            <a href="?page=techqikb2b_main_menu&tab=shipping" class="nav-tab <?php echo wbs_active_tab('shipping'); ?>">Shipping</a>
            <a href="?page=techqikb2b_main_menu&tab=amount" class="nav-tab <?php echo wbs_active_tab('amount'); ?>">Order Total</a>
        </h2>

        <form method="post" action="options.php">
            <?php
            $tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
            if ($tab == 'shipping') {
                wbs_settings_page();
            } elseif ($tab == 'amount') {
                wc_order_amount_settings_page();
            } else {
                techqik_general_setting_page();
            }
            ?>
        </form>
    </div>
    <?php
}

// Active tab helper function
function wbs_active_tab($tab_name) {
    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
    return $current_tab == $tab_name ? 'nav-tab-active' : '';
}

// General settings page content
function techqik_general_setting_page(){
    settings_fields('techqik_general_options_group');
    do_settings_sections('techqik-general-settings');
    submit_button();
}

// Initialize general settings
function techqik_general_setting_init(){
    register_setting('techqik_general_options_group', 'techqik_general_options', 'techqik_general_options_validate');
    add_settings_section('techqik_general_setting_section', 'Settings', 'techqik_general_section_text', 'techqik-general-settings');
    
    add_settings_field(
        'techqik_general_profit',
        'Profit (USD)',
        'techqik_general_profit_field',
        'techqik-general-settings',
        'techqik_general_setting_section'
    );

    add_settings_field(
        'techqik_use_cost_profit',
        'Use Cost + Profit for Price',
        'techqik_use_cost_profit_field',
        'techqik-general-settings',
        'techqik_general_setting_section'
    );
}
add_action('admin_init', 'techqik_general_setting_init');

// General profit field
function techqik_general_profit_field(){
    $options = get_option('techqik_general_options');
    $fee = isset($options['general_profit']) ? $options['general_profit'] : '8';
    echo "<input id='techqik_general_profit' name='techqik_general_options[general_profit]' type='number' step='1' value='" . esc_attr($fee) . "' style='width: 80px;' />";
}

function techqik_use_cost_profit_field() {
    $options = get_option('techqik_general_options');
    $use_cost_profit = isset($options['use_cost_profit']) ? $options['use_cost_profit'] : 0;
    echo "<input id='techqik_use_cost_profit' name='techqik_general_options[use_cost_profit]' type='checkbox' value='1'" . checked(1, $use_cost_profit, false) . " />";
}

// Validate general options
function techqik_general_options_validate($input) {
    $new_input = array();
    if (isset($input['general_profit']))
        $new_input['general_profit'] = floatval($input['general_profit']);

    $new_input['use_cost_profit'] = isset($input['use_cost_profit']) ? 1 : 0;

    return $new_input;
}

// General section text
function techqik_general_section_text() {
    echo '<p>Unify the wholesale profit of each product.</p>';
}


function get_custom_product_price($price, $product) {
    $options = get_option('techqik_general_options');
    if (isset($options['use_cost_profit']) && $options['use_cost_profit']) {
        $cost = get_post_meta($product->get_id(), '_cost', true);
        $profit = isset($options['general_profit']) ? floatval($options['general_profit']) : 0;
        $options_wbs = get_option('wbs_options');
        $exchange_rate = isset($options_wbs['exchange_rate']) ? floatval($options_wbs['exchange_rate']) : 1;
        if ($cost !== '') {
            $cost_in_usd = $cost / $exchange_rate;
            $price = $cost_in_usd + $profit;
        }
    }
    return $price;
}
add_filter('woocommerce_product_get_price', 'get_custom_product_price', 10, 2);
add_filter('woocommerce_product_get_sale_price', 'get_custom_product_price', 10, 2);
add_filter('woocommerce_product_get_regular_price', 'get_custom_product_price', 10, 2);
