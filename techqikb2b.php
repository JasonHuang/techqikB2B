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

 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

 // Define constants
define('TECHQIKB2B_PATH', plugin_dir_path(__FILE__));
define('TECHQIKB2B_URL', plugin_dir_url(__FILE__));

// Load common functions
// require_once(TECHQIKB2B_PATH . 'includes/functions/module-one-functions.php');


// Load modules
require_once(TECHQIKB2B_PATH . 'modules/wc-order-amount.php');
require_once(TECHQIKB2B_PATH . 'modules/weight-based-shipping.php');
require_once(TECHQIKB2B_PATH . 'modules/woo-product-cost.php');
require_once(TECHQIKB2B_PATH . 'modules/products-bulk-update.php');


// require_once(TECHQIKB2B_PATH . 'modules/module-two/module-two.php');

// Load common functions
// require_once(TECHQIKB2B_PATH . 'includes/functions/module-one-functions.php');
// require_once(TECHQIKB2B_PATH . 'includes/functions/module-two-functions.php');

// Load admin settings
// if (is_admin()) {
//     require_once(TECHQIKB2B_PATH . 'includes/admin/admin-settings.php');
// }


function techqikb2b_add_admin_menu() {
    // 添加主菜单
    add_menu_page('TechqikB2B', 'TechqikB2B', 'manage_options', 'techqikb2b_main_menu', 'techqikb2b_main_page', 'dashicons-businessman', 6);

    // 添加子菜单项
    add_submenu_page('techqikb2b_main_menu', 'Weight-based Shipping', 'Weight-based Shipping', 'manage_options', 'techqikb2b_weight_shipping', 'wbs_settings_page');

    add_submenu_page('techqikb2b_main_menu', 'Order Amount Limit', 'Order Amount Limit', 'manage_options', 'techqikb2b_order_limit', 'wc_order_amount_settings_page');

    $hook = add_submenu_page('techqikb2b_main_menu', 'Product Bulk Update', 'Product Bulk Update', 'manage_options', 'techqikb2b_product_bulk_update', 'bulk_update_products_page');

    add_action("load-$hook", 'set_bulk_update_products_screen_options');

}
add_action('admin_menu', 'techqikb2b_add_admin_menu');


function techqikb2b_main_page() {
    ?>
    <div class="wrap">
        <h1>Welcome to TechqikB2B</h1>
        <p>Here's a brief overview of the features:</p>

        <ul>
            <li><strong>Weight-based Shipping:</strong> Configure shipping costs based on weight.</li>
            <li><strong>Order Amount Limit:</strong> Set minimum and maximum order amounts.</li>
            <li><strong>Product Bulk Update:</strong> Quickly update products in bulk.</li>
            <li><strong>Settings:</strong> Adjust plugin settings to fit your needs.</li>
        </ul>

        <p>For detailed documentation, please visit our <a href="#">documentation page</a>.</p>
        <p>Need help? Contact our support <a href="#">here</a>.</p>
    </div>
    <?php
}

function techqikb2b_settings_page() {
    echo '<div class="wrap"><h1>Settings</h1></div>';
}
