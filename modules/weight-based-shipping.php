<?php
/*
Plugin Name: Weight Based Shipping Method
Plugin URI: https://techqik.com
Description: Calculates shipping costs based on total weight including packaging.
Version: 1.0
Author: Techqik
Author URI: https://techqik.com
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// // Add menu item for plugin settings
// add_action('admin_menu', 'wbs_menu');

// function wbs_menu() {
//     add_menu_page(
//         'Weight Based Shipping Settings',
//         'Weight Based Shipping',
//         'manage_options',
//         'wbs-settings',
//         'wbs_settings_page',
//         null,
//         99
//     );
// }

// Create settings page
function wbs_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.'));
    }

    ?>
    <div class="wrap">
        <h1>Weight Based Shipping Settings</h1>
        <div style="display: flex;">
            <div style="flex: 3; padding-right: 20px;">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wbs_options_group');
                    do_settings_sections('wbs-settings');
                    submit_button();
                    ?>
                </form>
            </div>
            <div style="flex: 2; padding-left: 20px; border-left: 1px solid #ccc;">
                <h2>Setting Explanations</h2>
                <!-- <p><strong>Enable Weight Based Shipping: </strong> Enable Weight Based Shipping</p> -->
                <p><strong>Per Kg Rate:</strong> The cost per kilogram for shipping. This rate is multiplied by the total weight of the packaged order. For example, if the rate is $14.5 per kg, and the total weight is 1.5 kg, the shipping cost will be $21.75.</p>
                <p><strong>Registration Fee:</strong> This is a fixed fee added to every shipment, regardless of weight. Useful for covering base operational costs.</p>
                <p><strong>First Weight:</strong> This setting can be used to specify a threshold weight, where different rates might apply below or above this weight.</p>
                <p><strong>Packaging Weight:</strong> Additional weight added for packaging. This weight is added to the product weight to calculate total shipping weight.</p>
                <p><strong>Surcharge:</strong> An additional surcharge to cover extra costs or to ensure profitability. This is added on top of the calculated shipping cost.</p>
                <p><strong>USD to RMB Exchange Rate:</strong> This rate is used to convert costs from USD to RMB. For example, if the rate is 7.5, then 1 USD equals 7.5 RMB.</p>

                <h2>Calculation Examples</h2>
                <p><strong>Example for a 58g package:</strong> If the per Kg rate is $14.5, and the registration fee is $3, with no additional surcharge, the shipping cost will be (58g/1000g) * $14.5 + $3 = $3.85.</p>
                <p><strong>Example for a 168g package:</strong> Similarly, for 168g, the shipping cost will be (168g/1000g) * $14.5 + $3 = $5.45.</p>
                <p><strong>Example for an 1128g package:</strong> For 1128g, the calculation changes slightly due to crossing 1 kg mark: (1128g/1000g) * $14.5 + $3 = $19.36.</p>

                <p><strong>You can treat RMB as your currency corresponding to the setting parameters.</strong></p>
            </div>
        </div>
    </div>
    <?php
}


// Register settings
add_action('admin_init', 'wbs_settings_init');

function wbs_settings_init() {
    register_setting('wbs_options_group', 'wbs_options', 'wbs_options_validate');
    add_settings_section('wbs_setting_section', 'Settings', 'wbs_section_text', 'wbs-settings');

    /*add_settings_field(
        'wbs_enable',
        'Enable Weight Based Shipping',
        'wbs_enable_field',
        'wbs-settings',
        'wbs_setting_section'
    );*/

    add_settings_field(
        'wbs_per_kg_rate',
        'Per Kg Rate (RMB)',
        'wbs_per_kg_rate_field',
        'wbs-settings',
        'wbs_setting_section'
    );

    add_settings_field(
        'wbs_registration_fee',
        'Registration Fee (RMB)',
        'wbs_registration_fee_field',
        'wbs-settings',
        'wbs_setting_section'
    );

    add_settings_field(
        'wbs_first_weight',
        'First Weight (grams)',
        'wbs_first_weight_field',
        'wbs-settings',
        'wbs_setting_section'
    );

    add_settings_field(
        'wbs_packaging_weight',
        'Packaging Weight (grams)',
        'wbs_packaging_weight_field',
        'wbs-settings',
        'wbs_setting_section'
    );

    add_settings_field(
        'wbs_surcharge',
        'Surcharge (RMB)',
        'wbs_surcharge_field',
        'wbs-settings',
        'wbs_setting_section'
    );

    add_settings_field(
        'wbs_exchange_rate',
        'USD to RMB Exchange Rate',
        'wbs_exchange_rate_field',
        'wbs-settings',
        'wbs_setting_section'
    );    
}

function wbs_section_text() {
    echo '<p>Set up the parameters for calculating shipping costs based on weight.</p>';
}

function wbs_exchange_rate_field() {
    $options = get_option('wbs_options');
    $rate = isset($options['exchange_rate']) ? $options['exchange_rate'] : '7.5'; // 默认值假设为 0.15
    echo "<input id='wbs_exchange_rate' name='wbs_options[exchange_rate]' type='number' step='0.01' value='" . esc_attr($rate) . "' style='width: 80px;' />";
    echo "<span style='margin-left: 10px;'> You will benefit when this rate is greater than current rate.</span>";

}

/*function wbs_enable_field() {
    $options = get_option('wbs_options');
    $enabled = isset($options['enable']) ? $options['enable'] : 1;
    echo "<input id='wbs_enable' name='wbs_options[enable]' type='checkbox' value='1' " . checked(1, $enabled, false) . " />";
}*/

function wbs_per_kg_rate_field() {
    $options = get_option('wbs_options');
    $rate_in_rmb = isset($options['per_kg_rate']) ? $options['per_kg_rate'] : '110'; // 默认值或从设置中获取
    $exchange_rate = isset($options['exchange_rate']) ? $options['exchange_rate'] : '7.5'; // 默认汇率或从设置中获取
    $rate_in_usd = $rate_in_rmb / $exchange_rate; // 计算汇率转换后的美元值

    echo "<input id='wbs_per_kg_rate' name='wbs_options[per_kg_rate]' type='number' step='0.01' value='" . esc_attr($rate_in_rmb) . "' style='width: 80px;' />";
    echo "<span style='margin-left: 10px;'>Equivalent in USD: $" . number_format($rate_in_usd, 2) . "</span>";
}


function wbs_registration_fee_field() {
    $options = get_option('wbs_options');
    $fee = isset($options['registration_fee']) ? $options['registration_fee'] : '19';
    $exchange_rate = isset($options['exchange_rate']) ? $options['exchange_rate'] : '7.5'; // 默认汇率或从设置中获取
    $rate_in_usd = $fee / $exchange_rate; // 计算汇率转换后的美元值

    echo "<input id='wbs_registration_fee' name='wbs_options[registration_fee]' type='number' step='0.01' value='" . esc_attr($fee) . "' style='width: 80px;' />";
    echo "<span style='margin-left: 10px;'>Equivalent in USD: $" . number_format($rate_in_usd, 2) . "</span>";
}

function wbs_first_weight_field() {
    $options = get_option('wbs_options');
    $first_weight = isset($options['first_weight']) ? $options['first_weight'] : '1000';
    echo "<input id='wbs_first_weight' name='wbs_options[first_weight]' type='number' value='" . esc_attr($first_weight) . "' style='width: 80px;' />";
}

function wbs_packaging_weight_field() {
    $options = get_option('wbs_options');
    $packaging_weight = isset($options['packaging_weight']) ? $options['packaging_weight'] : '50';
    echo "<input id='wbs_packaging_weight' name='wbs_options[packaging_weight]' type='number' value='" . esc_attr($packaging_weight) . "' style='width: 80px;' />";
}

function wbs_surcharge_field() {
    $options = get_option('wbs_options');
    $surcharge = isset($options['surcharge']) ? $options['surcharge'] : '10';
    $exchange_rate = isset($options['exchange_rate']) ? $options['exchange_rate'] : '7.5'; // 默认汇率或从设置中获取
    $rate_in_usd = $surcharge / $exchange_rate; // 计算汇率转换后的美元值

    echo "<input id='wbs_surcharge' name='wbs_options[surcharge]' type='number' step='0.01' value='" . esc_attr($surcharge) . "' style='width: 80px;' />";
    echo "<span style='margin-left: 10px;'>Equivalent in USD: $" . number_format($rate_in_usd, 2) . "</span>";
}

function wbs_options_validate($input) {
    $new_input = array();

    // $new_input['enable'] = isset($input['enable']) ? 1 : 0;

    if (isset($input['per_kg_rate']))
        $new_input['per_kg_rate'] = floatval($input['per_kg_rate']);

    if (isset($input['registration_fee']))
        $new_input['registration_fee'] = floatval($input['registration_fee']);

    if (isset($input['first_weight']))
        $new_input['first_weight'] = intval($input['first_weight']);

    if (isset($input['packaging_weight']))
        $new_input['packaging_weight'] = intval($input['packaging_weight']);

    if (isset($input['surcharge']))
        $new_input['surcharge'] = floatval($input['surcharge']);

    if (isset($input['exchange_rate']))
        $new_input['exchange_rate'] = floatval($input['exchange_rate']);

    return $new_input;
}

register_activation_hook(__FILE__, 'wbs_set_default_options');

function wbs_set_default_options() {
    $default_options = array(
        // 'enable' => 1, // Enable by default
        'per_kg_rate' => 110,
        'registration_fee' => 19,
        'first_weight' => 1000,
        'packaging_weight' => 50,
        'surcharge' => 10,
        'exchange_rate' => 7.5
    );
    add_option('wbs_options', $default_options);
}

register_deactivation_hook(__FILE__, 'wbs_clear_options');

function wbs_clear_options() {
    delete_option('wbs_options');
}

require_once(TECHQIKB2B_PATH . 'includes/functions/custom-shipping.php');
