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
    $search_query = isset($_GET['s']) ? esc_attr($_GET['s']) : '';
    ?>
    <div class="wrap">
        <h2>Product List</h2>
        <div class="overlay">
            <div class="message-box">操作成功!</div>
        </div>
        <div class="search-box">
            <form method="get">
                <input type="hidden" name="page" value="techqikb2b_product_bulk_update">
                <input type="text" name="s" placeholder="Search products" value="<?php echo $search_query; ?>">
                <input type="submit" value="Search" class="button">
                <input type="button" id="clear-search" value="Clear" class="button">
            </form>
        </div>
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
    display_products_list_table();
}

function enqueue_admin_scripts_and_styles() {
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_style('wp-jquery-ui-dialog');

    wp_enqueue_script('edit-price-script', TECHQIKB2B_URL . 'assets/js/edit-price.js', array('jquery'));
    wp_localize_script('edit-price-script', 'editPriceData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('update-cost-nonce')

    ));

    wp_enqueue_style('plugin-style', TECHQIKB2B_URL . 'assets/css/style.css');

}
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts_and_styles');

add_action('wp_ajax_update_product_cost', 'handle_update_product_cost');

function handle_update_product_cost() {
    if (!isset($_POST['product_id'], $_POST['new_value'], $_POST['security']) ||
        !wp_verify_nonce($_POST['security'], 'update-cost-nonce')) {
        wp_send_json_error('Invalid request or failed nonce verification.');
        return;
    }
    $product_id = intval($_POST['product_id']);
    $new_value = floatval($_POST['new_value']);
    $field = sanitize_text_field($_POST['field']);

    $meta_key = '';

    switch ($field) {
        case 'cost':
            $meta_key = '_cost';
            break;
        case 'weight':
            $meta_key = '_weight';
            break;
        case 'length':
            $meta_key = '_length';
            break;
        case 'width':
            $meta_key = '_width';
            break;
        case 'height':
            $meta_key = '_height';
            break;
        default:
            wp_send_json_error('Invalid field.');
            return;
    }

    if (update_post_meta($product_id, $meta_key, $new_value)) {
        wp_send_json_success('Cost updated or added successfully.');
    } else {
        wp_send_json_error('No changes made to cost, or update failed.');
    }
}
