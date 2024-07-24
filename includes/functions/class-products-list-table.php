<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

require_once TECHQIKB2B_PATH . 'includes/functions/product-update.php';

class Products_List_Table extends WP_List_Table {
    private $per_page;

    public function __construct($args = array()) {
        parent::__construct($args);

        $per_page = isset($_REQUEST['products_per_page']) ? intval($_REQUEST['products_per_page']) : get_user_meta(get_current_user_id(), 'products_per_page', true);
        $this->per_page = $per_page ? $per_page : 10;

        if (isset($_REQUEST['products_per_page'])) {
            update_user_meta(get_current_user_id(), 'products_per_page', $this->per_page);
        }
    }

    public function prepare_items() {
        $current_page = $this->get_pagenum();
        $total_items = $this->get_total_products();

        $this->process_bulk_action();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $this->per_page
        ));

        $this->items = $this->get_products($current_page, $this->per_page);
        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());
    }

    private function get_total_products() {
        global $wpdb;
        
        $where_clause = "WHERE (p.post_type = 'product' AND p.post_status = 'publish')
            OR (p.post_type = 'product_variation' AND p.post_status = 'publish')
            OR (p.post_type = 'product_variation' AND p.post_status = 'inherit' AND p2.post_status = 'publish')";
        
        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';
            $where_clause .= $wpdb->prepare(
                " AND (p.post_title LIKE %s OR pm.meta_value LIKE %s OR p2.post_title LIKE %s OR pm2.meta_value LIKE %s)",
                $search, $search, $search, $search
            );
        }
        
        $query = "SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p 
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            LEFT JOIN {$wpdb->posts} p2 ON p.post_parent = p2.ID
            LEFT JOIN {$wpdb->postmeta} pm2 ON p2.ID = pm2.post_id
            $where_clause";
        // error_log("total query:$query");

        return $wpdb->get_var($query);
    }

    private function get_products($current_page, $per_page) {
        global $wpdb;
        $offset = ($current_page - 1) * $per_page;
        $search_query = '';
        $search_params = array();
        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';
            $search_query = "AND (p.post_title LIKE %s OR pm.meta_value LIKE %s OR p2.post_title LIKE %s OR pm2.meta_value like %s)";
            $search_params = array($search, $search, $search, $search);
        }

        // 1. 获取基本产品信息
        $products_query = $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_parent, p2.post_title as parent_title,
                    CASE 
                        WHEN p.post_parent = 0 THEN p.ID 
                        ELSE p.post_parent 
                    END as group_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->posts} p2 ON p.post_parent = p2.ID
            LEFT JOIN {$wpdb->postmeta} pm2 ON p2.ID = pm2.post_id
            WHERE (
                (p.post_type = 'product' AND p.post_status = 'publish')
                OR (p.post_type = 'product_variation' AND p.post_status = 'publish')
                OR (p.post_type = 'product_variation' AND p.post_status = 'inherit' AND p2.post_status = 'publish')
            ) $search_query
            GROUP BY p.ID
            ORDER BY group_id desc, p.post_parent, p.menu_order, p.ID
            LIMIT %d, %d",
            array_merge($search_params, array($offset, $per_page))
        );
        
        // error_log("Products query: " . $products_query);
        $products = $wpdb->get_results($products_query, ARRAY_A);

        if (empty($products)) {
            return array();
        }

        $product_ids = wp_list_pluck($products, 'ID');
        $placeholders = array_fill(0, count($product_ids), '%d');
        $product_ids_format = implode(',', $placeholders);

        // error_log('products:'. print_r($products, true));
        // error_log("product_ids_format:$product_ids_format");

        // 2. 获取产品元数据
        $meta_query = $wpdb->prepare(
            "SELECT post_id, meta_key, meta_value
            FROM {$wpdb->postmeta}
            WHERE post_id IN ($product_ids_format)
            AND meta_key IN ('_sku', '_price', '_weight', '_length', '_width', '_height', '_cost')",
            $product_ids
        );
        $metas = $wpdb->get_results($meta_query, ARRAY_A);
        // error_log('metas:'. print_r($metas, true));

        // 3. 获取品牌信息
        $brand_query = $wpdb->prepare(
            "SELECT tr.object_id, t.name
            FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE tr.object_id IN ($product_ids_format)
            AND tt.taxonomy = %s",
            array_merge($product_ids, array('ts_product_brand'))
        );
        $brands = $wpdb->get_results($brand_query, ARRAY_A);
        // error_log('brands:'. print_r($brands, true));

        // 4. 整合数据
        $result = array();
        foreach ($products as $product) {
            // error_log('product:'. print_r($product, true));

            $product_data = array(
                'ID' => $product['ID'],
                'post_title' => $product['post_title'],
                'post_parent' => $product['post_parent'],
                'parent_title' => $product['parent_title']
            );

            // 添加元数据
            foreach ($metas as $meta) {
                if ($meta['post_id'] == $product['ID']) {
                    $key = ltrim($meta['meta_key'], '_');
                    $product_data[$key] = $meta['meta_value'];
                }
            }

            // 添加品牌
            foreach ($brands as $brand) {
                if ($brand['object_id'] == $product['ID']) {
                    $product_data['brand'] = $brand['name'];
                    break;
                }
            }

            $result[] = $product_data;
        }
        // error_log("Total products retrieved: " . count($result));
        return $result;
    }
    
    protected function get_bulk_actions() {
        $actions = array(
            'update_selected_prices' => 'Update Selected Prices',
            'update_all_prices' => 'Update All Prices'
        );
        return $actions;
    }

    public function process_bulk_action() {
        $action = $this->current_action();
        error_log("Current action: " . $action);  // 调试日志

        if ($action === 'update_all_prices'){
            if (techqik_is_update_locked()) {
                add_settings_error('techqik_price_update', 'update_in_progress', 'A price update is already in progress. Please wait for it to complete.', 'error');
                error_log("update_in_progress");  // 调试日志
                return;
            }
            error_log("update all event is scheduled");  // 调试日志

            // 设置更新进行中的标志
            techqik_set_update_lock();

            // 调度更新事件a
            wp_schedule_single_event(time() + 3, 'techqik_update_product_prices_action');

            // add_settings_error('techqik_price_update', 'update_scheduled', 'Product price update has been scheduled. You can check the progress in the admin dashboard.', 'info');
        }else if($action === 'update_selected_prices'){

            $options = get_option('techqik_general_options');
            $profit = isset($options['general_profit']) ? floatval($options['general_profit']) : 8;
            $general_exchange_rate = isset($options['general_exchange_rate']) ? floatval($options['general_exchange_rate']) : 7.1;
            // error_log("WooCommerce Product Cost: Profit: $profit USD, Exchange Rate: $general_exchange_rate CNY/USD");

            $post_ids = isset($_POST['product']) ? $_POST['product'] : array();
            $total = count($post_ids);
            $updated = 0;

            foreach ($post_ids as $post_id) {
                // error_log("post_id:$post_id");
                techqikb2b_update_product_price($post_id, $profit, $general_exchange_rate);
                $updated++;
            }
            add_settings_error('techqik_price_update', 'update_scheduled', 'Selected products updated.', 'info');

        }
    }

    public function get_columns() {
        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'sku'       => 'SKU',
            'brand'     => 'Brand',
            'post_title'=> 'Post Title',
            'cost'      => 'Cost (CNY)',
            'price'     => 'Price (USD)',
            'weight'    => 'Weight (KG)',
            'length'    => 'Length(cm)',
            'width'     => 'Width(cm)',
            'height'    => 'Height(cm)',
            'actions'   => 'Actions'
        );
        return $columns;
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'sku':
                $product_url = get_permalink($item['ID']);
                $sku = isset($item[$column_name]) ? esc_html($item[$column_name]) : 'N/A';
                return sprintf(
                    '<a href="%s" target="_blank" class="product-link" data-product-id="%s">%s</a>',
                    esc_url($product_url),
                    esc_attr($item['ID']),
                    $sku
                );

            case 'brand':
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : 'N/A';
            case 'post_title':
                $title = isset($item[$column_name]) ? esc_html($item[$column_name]) : 'N/A';
                if (!empty($item['parent_title'])) {
                    $title = ' (Variation) ' . esc_html($item[$column_name]);
                }
                return $title;

            case 'price':
                return isset($item[$column_name]) && $item[$column_name] !== '' ? esc_html($item[$column_name]) : '0';
            case 'cost':
            case 'weight':
            case 'length':
            case 'width':
            case 'height':
                $value = isset($item[$column_name]) && $item[$column_name] !== '' ? esc_html($item[$column_name]) : '0';
                return sprintf(
                    '<span class="editable-field" contenteditable="true" data-product-id="%s" data-field="%s" data-original-value="%s">%s</span>',
                    esc_attr($item['ID']),
                    esc_attr($column_name),
                    esc_attr($value),
                    esc_html($value)
                );

            case 'actions':
                if (empty($item['post_parent'])) { // 仅为主产品显示编辑按钮
                    $edit_url = admin_url('post.php?post=' . $item['ID'] . '&action=edit');
                    return sprintf(
                        '<a target="_blank" href="%s" class="button">Edit</a>',
                        esc_url($edit_url)
                    );
                }
                return ''; // 变体不显示编辑按钮

            default:
                return print_r($item, true); 
        }
    }

    protected function get_hidden_columns() {
        return array(); 
    }

    protected function get_sortable_columns() {
        return array(
            'sku'        => array('sku', true),
            'brand'      => array('brand', false),
            'post_title' => array('post_title', true),
            'cost'       => array('cost', false),
            'price'      => array('price', false),
            'weight'     => array('weight', false),
            'length'     => array('length', false),
            'width'      => array('width', false),
            'height'     => array('height', false)
        );
    }

    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="product[]" value="%s" />', esc_attr($item['ID'])
        );
    }

    public function extra_tablenav($which) {
        if ($which == "top") {
            $per_page = $this->get_items_per_page('products_per_page', 10);
            $current_url = remove_query_arg('products_per_page');

            echo '<div class="alignleft actions">';
            echo '<label for="products_per_page" class="screen-reader-text">' . __('Products per page', 'wc-product-import-export') . '</label>';
            echo '<select name="products_per_page" id="products_per_page" onchange="location.href=this.value;">';
            foreach (array(10, 20, 50, 100) as $value) {
                $url = add_query_arg('products_per_page', $value, $current_url);
                $selected = ($per_page == $value) ? ' selected="selected"' : '';
                echo '<option value="' . esc_url($url) . '"' . $selected . '>' . $value . '</option>';
            }
            echo '</select>';
            echo '</div>';
        }
    }

    public function display_rows() {
        $records = $this->items;
        // error_log('records:'. print_r($records, true));
        foreach ($records as $rec) {
            // Parent product row
            if (empty($rec['post_parent'])) {
                echo '<tr>';
                $this->single_row_columns($rec);
                echo '</tr>';

                // Find and display variations
                foreach ($records as $var) {
                    if ($var['post_parent'] == $rec['ID']) {
                        echo '<tr class="variation-row">';
                        $this->single_row_columns($var);
                        echo '</tr>';
                    }
                }
            }
        }
    }
}
