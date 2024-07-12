<?php

function wbs_add_shipping_method() {
    if ( ! class_exists( 'WBS_Custom_Shipping_Method' ) ) {
        class WBS_Custom_Shipping_Method extends WC_Shipping_Method {
            public function __construct( $instance_id = 0 ) {
                $this->id                 = 'wbs_custom_shipping'; // 运费方法ID
                $this->instance_id        = absint( $instance_id );
                $this->method_title       = __( 'Weight Based Shipping' );
                $this->method_description = __( 'A custom weight based shipping method.' );
                $this->supports           = array(
                    'shipping-zones',
                    'instance-settings',
                    'instance-settings-modal',
                );
                $this->init();

                $this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
                $this->title   = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Weight Based Shipping', 'wbs' );
            }

            public function init() {
                // Load the settings API
                $this->init_form_fields();
                $this->init_settings();

                // Save settings in admin
                add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
            }

            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => __( 'Enable/Disable', 'wbs' ),
                        'type'    => 'checkbox',
                        'label'   => __( 'Enable this shipping method', 'wbs' ),
                        'default' => 'yes',
                    ),
                    'title' => array(
                        'title'       => __( 'Title', 'wbs' ),
                        'type'        => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'wbs' ),
                        'default'     => __( 'Weight Based Shipping', 'wbs' ),
                        'desc_tip'    => true,
                    ),
                );
            }

            public function calculate_shipping( $package = array() ) {
                $options = get_option('wbs_options');
                $per_kg_rate = isset($options['per_kg_rate']) ? $options['per_kg_rate'] : 0;
                $registration_fee = isset($options['registration_fee']) ? $options['registration_fee'] : 0;
                $exchange_rate = isset($options['exchange_rate']) ? $options['exchange_rate'] : 1;
                $surcharge = isset($options['surcharge']) ? $options['surcharge'] : 1;
                $packaging_weight = isset($options['packaging_weight']) ? $options['packaging_weight'] : 0;

                $weight = 0;
                foreach ( $package['contents'] as $item_id => $values ) {
                    $_product = $values['data'];
                    $product_weight = $_product->get_weight();
                    error_log("product_weight:$product_weight");
                    error_log("packaging_weight:$packaging_weight");

                    if ($product_weight) {
                        $weight += $_product->get_weight() * $values['quantity'] + $packaging_weight/1000;
                    }else{
                        $weight += 0.05 * $values['quantity'] + $packaging_weight/1000;
                    }
                }
                error_log("weight:$weight");

                $cost = (($weight * $per_kg_rate) + $registration_fee + $surcharge) / $exchange_rate;
                error_log("cost:$cost");

                $rate = array(
                    'id'    => $this->id,
                    'label' => $this->title,
                    'cost'  => $cost,
                );

                $this->add_rate( $rate );
            }
        }
    }
}

add_action( 'woocommerce_shipping_init', 'wbs_add_shipping_method' );


function add_wbs_custom_shipping_method( $methods ) {
    $methods['wbs_custom_shipping'] = 'WBS_Custom_Shipping_Method';
    return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'add_wbs_custom_shipping_method' );