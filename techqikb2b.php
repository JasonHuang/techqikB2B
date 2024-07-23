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
    // error_log("hook:$hook");
    add_action("load-$hook", 'set_bulk_update_products_screen_options');

    add_submenu_page(
        'techqikb2b_main_menu',
        'Unlock Price Update',
        'Unlock Price Update',
        'manage_options',
        'techqikb2b_unlock_price_update',
        'techqik_unlock_price_update_page'
    );
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

function techqik_unlock_price_update_page() {
    if (isset($_POST['unlock_price_update']) && check_admin_referer('unlock_price_update')) {
        techqik_release_update_lock();
        echo '<div class="notice notice-success"><p>Price update has been unlocked.</p></div>';
    }

    $is_locked = techqik_is_update_locked();
    ?>
    <div class="wrap">
        <h1>Unlock Price Update</h1>
        <?php if ($is_locked): ?>
            <p>The price update process is currently locked. This may be due to an ongoing update or an update that did not complete properly.</p>
            <form method="post">
                <?php wp_nonce_field('unlock_price_update'); ?>
                <input type="submit" name="unlock_price_update" class="button button-primary" value="Unlock Price Update">
            </form>
        <?php else: ?>
            <p>The price update process is not currently locked.</p>
        <?php endif; ?>
    </div>
    <?php
}