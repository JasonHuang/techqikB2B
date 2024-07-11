<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Products_List_Table extends WP_List_Table {
    private $per_page;
    private $use_cost_profit;
    private $general_profit;
    private $exchange_rate;

    public function __construct($args = array()) {
        parent::__construct($args);

        $per_page = isset($_REQUEST['products_per_page']) ? intval($_REQUEST['products_per_page']) : get_user_meta(get_current_user_id(), 'products_per_page', true);
        $this->per_page = $per_page ? $per_page : 10;

        if (isset($_REQUEST['products_per_page'])) {
            update_user_meta(get_current_user_id(), 'products_per_page', $this->per_page);
        }

        $options = get_option('techqik_general_options');
        $this->use_cost_profit = isset($options['use_cost_profit']) ? $options['use_cost_profit'] : 0;
        $this->general_profit = isset($options['general_profit']) ? floatval($options['general_profit']) : 0;

        $options_wbs = get_option('wbs_options');
        $this->exchange_rate = isset($options_wbs['exchange_rate']) ? floatval($options_wbs['exchange_rate']) : 1;

        error_log("use_cost_profit:$this->use_cost_profit");
        error_log("general_profit:$this->general_profit");
        error_log("exchange_rate:$this->exchange_rate");
    }

    public function prepare_items() {
        $current_page = $this->get_pagenum();
        $total_items = $this->get_total_products();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $this->per_page
        ));

        $this->items = $this->get_products($current_page, $this->per_page);
        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());
    }

    private function get_total_products() {
        global $wpdb;
        $search_query = '';
        if (!empty($_REQUEST['s'])) {
            $search = esc_sql($_REQUEST['s']);
            $search_query = "AND (p.post_title LIKE '%$search%' OR pm.meta_value LIKE '%$search%')";
        }
        return $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->prefix}posts p 
            LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id 
            WHERE p.post_type IN ('product', 'product_variation') $search_query");
    }

    private function get_products($current_page, $per_page) {
        global $wpdb;
        $offset = ($current_page - 1) * $per_page;
        $search_query = '';
        if (!empty($_REQUEST['s'])) {
            $search = esc_sql($_REQUEST['s']);
            $search_query = "AND (p.post_title LIKE '%$search%' OR pm.meta_value LIKE '%$search%' OR pm_attr.meta_value LIKE '%$search%')";
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM (
                SELECT 
                    p.ID, 
                    p.post_title,
                    p.post_parent,
                    COALESCE(MAX(pm_sku.meta_value), MAX(p2_sku.meta_value)) AS sku,
                    MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value ELSE NULL END) AS price,
                    MAX(CASE WHEN pm.meta_key = '_weight' THEN pm.meta_value ELSE NULL END) AS weight,
                    MAX(CASE WHEN pm.meta_key = '_length' THEN pm.meta_value ELSE NULL END) AS length,
                    MAX(CASE WHEN pm.meta_key = '_width' THEN pm.meta_value ELSE NULL END) AS width,
                    MAX(CASE WHEN pm.meta_key = '_height' THEN pm.meta_value ELSE NULL END) AS height,
                    MAX(CASE WHEN pm.meta_key = '_cost' THEN pm.meta_value ELSE NULL END) AS cost,
                    GROUP_CONCAT(DISTINCT CASE WHEN pm_attr.meta_key LIKE '_attribute_%' THEN CONCAT(pm_attr.meta_key, ': ', pm_attr.meta_value) ELSE NULL END SEPARATOR ', ') AS attributes,
                    MAX(p2.post_title) AS parent_title,
                    GROUP_CONCAT(DISTINCT CASE WHEN tt.taxonomy = 'ts_product_brand' THEN t.name ELSE NULL END) AS brand
                FROM 
                    {$wpdb->prefix}posts p
                LEFT JOIN 
                    {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
                LEFT JOIN 
                    {$wpdb->prefix}postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
                LEFT JOIN 
                    {$wpdb->prefix}postmeta pm_attr ON p.ID = pm_attr.post_id AND pm_attr.meta_key LIKE '_attribute_%'
                LEFT JOIN 
                    {$wpdb->prefix}posts p2 ON p.post_parent = p2.ID
                LEFT JOIN 
                    {$wpdb->prefix}postmeta p2_sku ON p2.ID = p2_sku.post_id AND p2_sku.meta_key = '_sku'
                LEFT JOIN 
                    {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id
                LEFT JOIN 
                    {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                LEFT JOIN 
                    {$wpdb->prefix}terms t ON tt.term_id = t.term_id
                WHERE 
                    p.post_type IN ('product', 'product_variation')
                    $search_query
                GROUP BY 
                    p.ID
                ORDER BY 
                    p.post_parent, p.ID
            ) pp
            LIMIT %d, %d",
            $offset, $per_page
        );
        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function get_columns() {
        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'sku'       => 'SKU',
            'brand'     => 'Brand',
            'post_title'=> 'Post Title',
            'attributes'=> 'Attributes',
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
                    $title = ' (Variation of: ' . esc_html($item['parent_title']) . ')';
                }
                return $title;

            case 'attributes':
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : 'N/A';

            case 'price':
                if ($this->use_cost_profit) {
                    $cost = isset($item['cost']) ? floatval($item['cost']) : 0;
                    $price = $cost / $this->exchange_rate + $this->general_profit;
                    return esc_html(number_format($price, 2, '.', ''));
                } else {
                    return isset($item[$column_name]) && $item[$column_name] !== '' ? esc_html($item[$column_name]) : '0';
                }

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
                $edit_url = admin_url('post.php?post=' . $item['ID'] . '&action=edit');
                return sprintf(
                    '<a target="_blank" href="%s" class="button">Edit</a>',
                    esc_url($edit_url)
                );

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
