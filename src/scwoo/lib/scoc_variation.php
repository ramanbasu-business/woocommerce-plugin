<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of scoc_variation
 *
 * @author raman
 */
class scoc_variation
{
    private $verbose = 0;
    
    public function __construct($verbose = 0)
    {
        $this->verbose = $verbose;
    }
    
    private function printLog($s)
    {
        if ($this->verbose==1) :
            echo $s . "\r\n<br>";
        endif;
    }


    /**
     * Save a new product attribute from his name (slug).
     *
     * @since 3.0.0
     * @param string $name  | The product attribute name (slug).
     * @param string $label | The product attribute label (name).
     */
    private function save_product_attribute_from_name($name, $label = '', $set = true)
    {
        global $wpdb;

        $label = $label == '' ? ucfirst($name) : $label;
        $attribute_id = $this->get_attribute_id_from_name($name);

        if (empty($attribute_id)) {
            $attribute_id = null;
        } else {
            $set = false;
        }
        $args = array(
            'attribute_id' => $attribute_id,
            'attribute_name' => $name,
            'attribute_label' => $label,
            'attribute_type' => 'select',
            'attribute_orderby' => 'menu_order',
            'attribute_public' => 0,
        );

        if (empty($attribute_id)) {
            $wpdb->insert("{$wpdb->prefix}woocommerce_attribute_taxonomies", $args);
        }

        if ($set) {
            $attributes = wc_get_attribute_taxonomies();
            $args['attribute_id'] = $this->get_attribute_id_from_name($name);
            $attributes[] = (object) $args;
            //print_pr($attributes);
            set_transient('wc_attribute_taxonomies', $attributes);
        } else {
            return;
        }
    }

    
    /**
     * Get the product attribute ID from the name.
     *
     * @since 3.0.0
     * @param string $name | The name (slug).
     */
    private function get_attribute_id_from_name($name)
    {
        global $wpdb;
        $attribute_id = $wpdb->get_col("SELECT attribute_id
    FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
    WHERE attribute_name LIKE '$name'");
        return reset($attribute_id);
    }

    
    /**
     * Create a new variable product (with new attributes if they are).
     * (Needed functions:
     *
     * @since 3.0.0
     * @param array $data | The data to insert in the product.
     */
    public function updateVariationProperties($product_id, $data)
    {
        // sample data
        // array(
        //    'Figurine Color'   =>  array( 'Black and Tan', 'Bronze', 'Tan' ),
        //    'Pose'   =>  array( 'Sitting' ),
        //)
        
        // Get an instance of the WC_Product_Variable object and save it
        //$product = new WC_Product_Variable( $product_id );
        $product = wc_get_product($product_id);

        ## ---------------------- Other optional data  ---------------------- ##
        ##     (see WC_Product and WC_Product_Variable setters methods)
        // THE PRICES

        $product->validate_props(); // Check validation
        ## ---------------------- VARIATION ATTRIBUTES ---------------------- ##

        $product_attributes = array();
        $pos = 0;
        foreach ($data as $key => $terms) {
            $taxonomy = wc_attribute_taxonomy_name($key); // The taxonomy slug
            $attr_label = ucfirst($key); // attribute label name
            $attr_name = (wc_sanitize_taxonomy_name($key)); // attribute slug
            
            $this->printLog(
                "updateVariationProperties"
                . " key: '" . $key . "'"
                . " taxonomy: '" . $taxonomy . "'"
                . " attr_label: '" . $attr_label . "'"
                . " attr_name: '" . $attr_name . "'"
            );
            
            // NEW Attributes: Register and save them
            if (!taxonomy_exists($taxonomy)) {
                $this->save_product_attribute_from_name($attr_name, $attr_label);
            }

            $product_attributes[$taxonomy] = array(
                'name' => $taxonomy,
                'value' => '',
                'position' => $pos,
                'is_visible' => 1,
                'is_variation' => 1,
                'is_taxonomy' => 1
            );

            foreach ($terms as $value) {
                $term_name = ucfirst($value);
                $term_slug = sanitize_title($value);

                // Check if the Term name exist and if not we create it.
                if (!term_exists($value, $taxonomy)) {
                    wp_insert_term($term_name, $taxonomy, array('slug' => $term_slug)); // Create the term
                }
                // Set attribute values
                wp_set_post_terms($product_id, $term_name, $taxonomy, true);
            }
            
            $pos++;
        }
        
        update_post_meta($product_id, '_product_attributes', $product_attributes);
        $product->save(); // Save the data
        return true;
    }

    
    /**
     * Create a product variation for a defined variable product ID.
     *
     * @since 3.0.0
     * @param int   $product_id | Post ID of the product parent variable product.
     * @param array $variation_data | The data to insert in the product.
     */
    public function updateSingleVariation($product_id, $variation_data)
    {
        // Get the Variable product object (parent)
        $product = wc_get_product($product_id);
        
        $variation_post = array(
            'post_title' => $product->get_title(),
            'post_name' => 'product-' . $product_id . '-variation',
            'post_status' => 'publish',
            'post_parent' => $product_id,
            'post_type' => 'product_variation',
            'guid' => $product->get_permalink()
        );

        $variation_id = wc_get_product_id_by_sku($variation_data['sku']);
        if (empty($variation_id)) :
            // Creating the product variation
            $variation_id = wp_insert_post($variation_post);
            $this->printLog("updateSingleVariation variation_id created: " . $variation_id);
        else :
            $this->printLog("updateSingleVariation variation_id exists: " . $variation_id);
        endif;

        // Get an instance of the WC_Product_Variation object
        $variation = new WC_Product_Variation($variation_id);
        $variation->set_backorders('no');   // 7.10.2.0

        // Iterating through the variations attributes
        foreach ($variation_data['attributes'] as $attribute => $term_name) {
            $taxonomy = 'pa_' . $attribute; // The attribute taxonomy
            $this->printLog("updateSingleVariation taxonomy : " . $taxonomy);
            
            // Check if the Term name exist and if not we create it.
            if (!term_exists($term_name, $taxonomy)) {
                $result = wp_insert_term($term_name, $taxonomy);
                $this->printLog("term_name creation result: " . $term_name);
                if ($this->verbose==1) :
                    var_dump($result);
                endif;
            } // Create the term

            $term_object = get_term_by('name', $term_name, $taxonomy);
            if ($term_object==false) :
                $this->printLog("updateSingleVariation term_name does not exist: " . $term_name);
                continue;
            endif;
            
            $term_slug = $term_object->slug; // Get the term slug
            // Get the post Terms names from the parent variable product.
            $post_term_names = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'names'));

            // Check if the post term exist and if not we set it in the parent variable product.
            if (!in_array($term_name, $post_term_names)) {
                wp_set_post_terms($product_id, $term_name, $taxonomy, true);
            }

            // Set/save the attribute data in the product variation
            update_post_meta($variation_id, 'attribute_' . $taxonomy, $term_slug);
        }

        $this->printLog("updateSingleVariation[] setting up other data:");

        $regular_price = $variation_data['regular_price'];
        $sale_price = isset($variation_data['sale_price'])? $variation_data['sale_price'] : '';
        $date_from = isset($variation_data['sale_price_dates_from'])? $variation_data['sale_price_dates_from'] :  '';
        $date_to = isset($variation_data['sale_price_dates_to'])? $variation_data['sale_price_dates_to'] : '';

        if ($date_from != '') {
            $variation_data['sale_price_dates_from'] = strtotime($date_from);
        } else {
            $variation_data['sale_price_dates_from'] = '';
        }

        if ($date_to != '') {
            $variation_data['sale_price_dates_to'] = strtotime($date_to);
        } else {
            $variation_data['sale_price_dates_to'] = '';
        }

        if ($date_to != '' && $date_from == '') {
            $variation_data['sale_price_dates_from'] = strtotime('NOW', current_time('timestamp'));
        }

        if ($sale_price != '' && $date_to == '' && $date_from == '') {
            $variation_data['price'] = $sale_price;
        } else {
            $variation_data['price'] = $regular_price;
        }

        if ($sale_price != '' && $date_from != '' && strtotime($date_from) < strtotime('NOW', current_time('timestamp'))) {
            $variation_data['price'] = $sale_price;
        }

        if ($date_to != '' && strtotime($date_to) < strtotime('NOW', current_time('timestamp'))) {
            $variation_data['price'] = $regular_price;
            $variation_data['sale_price_dates_from'] = '';
            $variation_data['sale_price_dates_to'] = '';
        }
        
        ## Set/save all other data
        $variation_data["shipping_class_id"] = isset($variation_data["shipping_class_id"])? $variation_data["shipping_class_id"] : '';
        $variation_data["weight"] = isset($variation_data["weight"])? $variation_data["weight"] : '';
        $variation_data["tax_class"] = isset($variation_data["tax_class"])? $variation_data["tax_class"] : '';
        
        $variation->set_sku($variation_data['sku']);
        $variation->set_price($variation_data['regular_price']);
        $variation->set_sale_price($variation_data['sale_price']);
        $variation->set_regular_price($variation_data['regular_price']);
        
        
        $variation->set_shipping_class_id($variation_data["shipping_class_id"]);
        
        /**
         * 7.9.0.0
        **/
        if (isset($variation_data['length']) && !empty($variation_data['length'])) :
            $variation->set_length($variation_data['length']);
        endif;

        if (isset($variation_data['width']) && !empty($variation_data['width'])) :
            $variation->set_width($variation_data['width']);
        endif;

        if (isset($variation_data['height']) && !empty($variation_data['height'])) :
            $variation->set_height($variation_data['height']);
        endif;

        // Stock
        $stock_status = 'instock';
        $this->printLog("updateSingleVariation[] variation_data['stock_qty']=" . $variation_data['stock_qty']);
        if (isset($variation_data['stock_qty'])) {
            $variation->set_stock_quantity($variation_data['stock_qty']);
        
            if($variation_data['stock_qty'] <= 0):
                $stock_status = 'outofstock';
            else:
                $stock_status = 'instock';
            endif;

        } else {
            $variation->set_manage_stock(false);
        }

        $variation->set_stock_status($stock_status); // 7.10.1.0

        // 7.1.0.0, 7.8.2.0
        if (isset($variation_data['manage_stock'])
            && !empty($variation_data['manage_stock']
            && $variation_data['manage_stock'] == 1)) {
            $variation->set_manage_stock(true);
        } else {
            $variation->set_manage_stock(false);
        }

        $variation->set_weight($variation_data['weight']); // weight (reseting)
        
        //if (!isset($variation_data['tax_class'])) {
        //    delete_post_meta($variation_id, '_tax_class');
        //}
        $variation->set_tax_class($variation_data['tax_class']);
        
        // Variation image.
        //if (isset($variation_data["image_id"]) && $variation_data["image_id"] > 0) :
        if (isset($variation_data["image_id"])) :
            $variation->set_image_id($variation_data["image_id"]);
        endif;

        //var_dump("=======scoc_variation =========" .$variation->get_backorders() . $variation->get_stock_status());

        $variation->save(); // Save the data
        $this->printLog("updateSingleVariation variation saved SKU: " . $variation_data['sku']);
        $this->printLog("variation [manage_stock]=" . $variation->get_manage_stock());
        $this->printLog("variation [stock_status] input=" . $stock_status . " output=" . $variation->get_stock_status());
        $this->printLog("variation [backorders] input=" . "no" . " output=" . $variation->get_backorders());

        update_post_meta($variation_id, "_sale_price_dates_from", wc_clean($variation_data["sale_price_dates_from"]));
        update_post_meta($variation_id, "_sale_price_dates_to", wc_clean($variation_data["sale_price_dates_to"]));
        
        update_post_meta($variation_id, '_stock_status', $stock_status);

        //testing
        /**
        $variations = $product->get_available_variations(); // array
        foreach ($variations as $variation):
            var_dump("********************************".$variation->get_stock_status());
        endforeach;
        **/

        return true;
    }
}
