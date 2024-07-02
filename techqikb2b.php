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
    // add_submenu_page('techqikb2b_main_menu', 'Weight-based Shipping', 'Weight-based Shipping', 'manage_options', 'techqikb2b_weight_shipping', 'wbs_settings_page');

    // add_submenu_page('techqikb2b_main_menu', 'Order Amount Limit', 'Order Amount Limit', 'manage_options', 'techqikb2b_order_limit', 'wc_order_amount_settings_page');

    $hook = add_submenu_page('techqikb2b_main_menu', 'Product Bulk Update', 'Product Bulk Update', 'manage_options', 'techqikb2b_product_bulk_update', 'bulk_update_products_page');

    add_action("load-$hook", 'set_bulk_update_products_screen_options');

}
add_action('admin_menu', 'techqikb2b_add_admin_menu');


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
                // settings_fields('tqb_shipping_settings');
                // do_settings_sections('tqb-settings-shipping');
                wbs_settings_page();
            } elseif ($tab == 'amount') {
                // settings_fields('tqb_profit_settings');
                // do_settings_sections('tqb-settings-profit');
                wc_order_amount_settings_page();
            } else {
                // settings_fields('tqb_general_settings');
                // do_settings_sections('tqb-settings-general');
                techqik_general_setting_page();
            }
            // submit_button();
            ?>
        </form>
    </div>
    <?php
}

function wbs_active_tab($tab_name) {
    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
    return $current_tab == $tab_name ? 'nav-tab-active' : '';
}

function techqik_general_setting_page(){
    settings_fields('techqik_general_options_group');
    do_settings_sections('techqik-general-settings');
    submit_button();
}

add_action('admin_init', 'techqik_general_setting_init');

function techqik_general_setting_init(){
    register_setting('techqik_general_options_group', 'techqik_general_options', 'techqik_general_options_validate');
    add_settings_section('techqik_general_setting_section', 'Settings', 'techqik_general_section_text', 'techqik-general-settings');
    add_settings_field(
        'techqik_general_profit',
        'Profit(USD)',
        'techqik_general_profit_field',
        'techqik-general-settings',
        'techqik_general_setting_section'
    );
}

function techqik_general_profit_field(){
    $options = get_option('techqik_general_options');

    // error_log(json_encode($options));

    $fee = isset($options['general_profit']) ? $options['general_profit'] : '8';

    echo "<input id='techqik_general_profit' name='techqik_general_options[general_profit]' type='number' step='1' value='" . esc_attr($fee) . "' style='width: 80px;' />";
}

function techqik_general_options_validate($input) {
    $new_input = array();

    if (isset($input['general_profit']))
        $new_input['general_profit'] = floatval($input['general_profit']);

    return $new_input;
}

function techqik_general_section_text() {
    echo '<p>Set up the parameters for calculating shipping costs based on weight.</p>';
}