<?php
/**
 * Plugin Name: Products Bulk Update
 * Description: A WooCommerce plugin to bulk update products.
 * Version: 1.0.0
 * Author: Techqik
 * Text Domain: wc-product-import-export
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once TECHQIKB2B_PATH . 'includes/functions/class-products-list-table.php';

// function bulk_update_products_menu() {
//     $hook = add_submenu_page(
//         'Bulk Update Products',
//         'Bulk Update Products',
//         'manage_options',
//         'bulk_update_products-settings',
//         'bulk_update_products_page',
//         null,
//         99
//     );

//     add_action("load-$hook", 'set_bulk_update_products_screen_options');
// }
// add_action('admin_menu', 'bulk_update_products_menu');

function set_bulk_update_products_screen_options() {
    $option = 'per_page';
    $args = array(
        'label' => 'Products per page',
        'default' => 10,
        'option' => 'products_per_page'
    );
    add_screen_option($option, $args);
}

function set_screen_option($status, $option, $value) {
    if ($option == 'products_per_page') {
        return $value;
    }
    return $status;
}
add_filter('set-screen-option', 'set_screen_option', 10, 3);

function display_products_list_table() {
    $list_table = new Products_List_Table();
    $list_table->prepare_items();
    ?>
    <div class="wrap">
        <h2>Product List</h2>
        <div id="price-update-message" style="display: none; color: green;"></div>
        <form method="post">
            <?php
            $list_table->display();
            ?>
        </form>
    </div>
    <?php
}

function bulk_update_products_page() {
    ?>
    <h1>Bulk Update Products</h1>
    <button id="open-popup">Add Data</button>
    <div id="dialog" title="Product Attributes" style="display:none;">
        <form>
            <!-- 表单内容：选择产品属性 -->
            <input type="submit" value="Submit">
        </form>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $("#open-popup").on("click", function() {
                $("#dialog").dialog({
                    modal: true,
                    width: 500,
                    height: 400,
                });
            });
        });
    </script>
    <?php
    // 调用函数显示产品列表
    display_products_list_table();
}

function enqueue_admin_scripts_and_styles() {
    // 加载 jQuery UI Dialog 相关文件
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_style('wp-jquery-ui-dialog');

    // 加载位于 assets/js/ 目录下的自定义编辑价格脚本
    wp_enqueue_script('edit-price-script', plugins_url('assets/js/edit-price.js', __FILE__), array('jquery'));
    wp_localize_script('edit-price-script', 'plugin_data', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
    // 可以选择引入其他第三方库，如SweetAlert2
}
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts_and_styles');

function enqueue_plugin_styles() {
    wp_enqueue_style('plugin-style', plugins_url('assets/css/style.css', __FILE__));
}
add_action('admin_enqueue_scripts', 'enqueue_plugin_styles');


add_action('wp_ajax_update_product_price', 'handle_update_product_price');

function handle_update_product_price() {
    global $wpdb;
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $new_price = isset($_POST['new_price']) ? floatval($_POST['new_price']) : 0;

    if ($product_id > 0 && $new_price >= 0) {
        $wpdb->update(
            $wpdb->postmeta,
            array('meta_value' => $new_price),
            array(
                'post_id' => $product_id,
                'meta_key' => '_price'
            )
        );
        wp_send_json_success('Price updated.');
    } else {
        wp_send_json_error('Failed to update price.');
    }
}
