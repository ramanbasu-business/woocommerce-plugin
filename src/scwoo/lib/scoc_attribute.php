<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of scoc_attribute
 *
 * @author raman
 */
class scoc_attribute
{

    private $scoc_lib;
    private $scoc_utility;

    public function __construct()
    {
        $this->scoc_lib = new scoc_lib();
        $this->scoc_utility = new scoc_utility();
    }

    public function product_condition_options_fields(){
        global $wp_version;
        global $woocommerce;
    
        // 7.10.0.0
        $output = '<plugin>';
        $output .= '<name>product-condition-for-woocommerce</name>';
        $output .= '<code>product-condition-for-woocommerce</code>';
        $output .= '<attributes>';
        
        if (function_exists('product_condition_options')) :
            
            $output .= '<attribute>';
            $output .= '<id>10000</id>';
            $output .= '<code>product_condition_condition_option</code>';
            $output .= '<name>Product Condition</name>';
            $output .= '<group_id>' . "" . '</group_id>';
            $output .= '<group_label>' . "" . '</group_label>';
            $output .= '<type>select</type>';

            // Get product condition options.
            $options = product_condition_options();
            //var_dump($terms);

            $output .= '<optionvalues>';
            if (is_array($options) && !empty($options)) :
                foreach ($options as $key=>$value) :
                    $output .= '<optionvalue>';
                    $output .= '<value>' . $this->cData($key) . '</value>';
                    $output .= '<label>' . $this->cData($value) . '</label>';
                    $output .= '</optionvalue>';
                endforeach;
            endif;
            $output .= '</optionvalues>';
            
            $output .= '</attribute>';

        endif;

        $output .= '</attributes>';
        $output .= '</plugin>';
        echo $output;
    }

    public function acf_fields()
    {
        global $wp_version;
        global $woocommerce;
    
        $output = '<plugin>';
        $output .= '<name>advanced custom fields</name>';
        $output .= '<code>acf</code>';
        $output .= '<attributes>';
        echo $output;
        
        if (function_exists('acf_get_field_groups')) :
            $groups = acf_get_field_groups();

            foreach ($groups as $group) :
                $output = '';
                //$output .= '<group>';
                //$output .= '<id>' . $group["ID"] . '</id>';
                //$output .= '<title>' . $group["title"] . '</title>';

                $fields = acf_get_fields($group);
                foreach ($fields as $field) :
                    //var_dump($field);
                    $output .= '<attribute>';
                    $output .= '<id>' . $field["ID"] . '</id>';
                    $output .= '<code>' . $this->cData($field["name"]) . '</code>';
                    $output .= '<name>' . $this->cData($field["label"]) . '</name>';
                    $output .= '<group_id>' . $group["ID"] . '</group_id>';
                    $output .= '<group_label>' . $this->cData($group["title"]) . '</group_label>';
                    $output .= '<type>' . $field["type"] . '</type>';

                    $output .= '<optionvalues>';
                    if (isset($field["choices"]) && !empty($field["choices"])) :
                        foreach ($field["choices"] as $key => $value) :
                            $output .= '<optionvalue>';
                            $output .= '<value>' . $this->cData($key) . '</value>';
                            $output .= '<label>' . $this->cData($value) . '</label>';
                            $output .= '</optionvalue>';
                        endforeach;
                    endif;
                    $output .= '</optionvalues>';

                    $output .= '</attribute>';
                endforeach;


                //$output .= '</group>';
                echo $output;
            endforeach;
        endif;
        $output = '</attributes>';
        $output .= '</plugin>';
        echo $output;
    }

    public function wpat_fields()
    {
        global $wp_version;
        global $woocommerce;
        global $wc_product_attributes;
        
        $output = '<plugin>';
        $output .= '<name>woocommerce product attribute taxonomy</name>';
        $output .= '<code>wpat</code>';
        $output .= '<attributes>';
        echo $output;
        
        // Array of defined attribute taxonomies.
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        
        foreach ($attribute_taxonomies as $tax) :
            $attribute_taxonomy_name = wc_attribute_taxonomy_name($tax->attribute_name);
            $label                   = $tax->attribute_label ? $tax->attribute_label : $tax->attribute_name;
            //var_dump($tax);
            $output = '<attribute>';
            $output .= '<id>' . $tax->attribute_id . '</id>';
            $output .= '<code>' . $this->cData($attribute_taxonomy_name) . '</code>';
            $output .= '<name>' . $this->cData($label) . '</name>';
            $output .= '<group_id>' . "" . '</group_id>';
            $output .= '<group_label>' . "" . '</group_label>';
            $output .= '<type>' . $tax->attribute_type . '</type>';

            $terms = get_terms($attribute_taxonomy_name, array('hide_empty' => 0));
            //var_dump($terms);
            $output .= '<optionvalues>';
            if (is_array($terms) && !empty($terms)) :
                foreach ($terms as $term) :
                    $output .= '<optionvalue>';
                    $output .= '<value>' . $this->cData($term->slug) . '</value>';
                    $output .= '<label>' . $this->cData($term->name) . '</label>';
                    $output .= '</optionvalue>';
                endforeach;
            endif;
            $output .= '</optionvalues>';
            
            $output .= '</attribute>';
            echo $output;
        endforeach;
        
        $output = '</attributes>';
        $output .= '</plugin>';
        echo $output;
    }

    public function other_fields(){
        global $wp_version;
        global $woocommerce;
    
        // 7.10.0.0
        $output = '<plugin>';
        $output .= '<name>other_meta_fields</name>';
        $output .= '<code>other_meta_fields</code>';
        $output .= '<attributes>';
        
        //if (function_exists('product_condition_options')) :
            
            $output .= '<attribute>';

            $output .= '<id>20001</id>';
            $output .= '<code>_woosea_upc</code>';
            $output .= '<name>_woosea_upc</name>';
            $output .= '<group_id>' . "" . '</group_id>';
            $output .= '<group_label>' . "" . '</group_label>';
            $output .= '<type>text</type>';
            
            $output .= '</attribute>';

        //endif;

        $output .= '</attributes>';
        $output .= '</plugin>';
        echo $output;
    }

    /*
    * 7.12.2.0
    */
    public function extendons_product_condition_fields(){
        global $wp_version;
        global $woocommerce;
    
        // 7.10.0.0
        $output = '<plugin>';
        $output .= '<name>extendons_product_condition</name>';
        $output .= '<code>extendons_product_condition</code>';
        $output .= '<attributes>';
        
        if (class_exists('Extendons_Product_Condition')) :
            
            $output .= '<attribute>';
            $output .= '<id>30001</id>';
            $output .= '<code>extendons_pc_enable_product_condition</code>';
            $output .= '<name>extendons_pc_enable_product_condition</name>';
            $output .= '<group_id>' . "" . '</group_id>';
            $output .= '<group_label>' . "" . '</group_label>';
            $output .= '<type>select</type>';
            
            $output .= '<optionvalues>';
            $terms = array("yes", "no");
            foreach ($terms as $term) :
                $output .= '<optionvalue>';
                $output .= '<value>' . $this->cData($term) . '</value>';
                $output .= '<label>' . $this->cData($term) . '</label>';
                $output .= '</optionvalue>';
            endforeach;
            
            $output .= '</optionvalues>';
            $output .= '</attribute>';

            $output .= '<attribute>';
            $output .= '<id>30002</id>';
            $output .= '<code>extendons_pc_type</code>';
            $output .= '<name>extendons_pc_type</name>';
            $output .= '<group_id>' . "" . '</group_id>';
            $output .= '<group_label>' . "" . '</group_label>';
            $output .= '<type>select</type>';
            
            $output .= '<optionvalues>';
            $terms = array("", "New_Condition", "Used_Condition", "Refurbished_Condition", "Damaged_Condition");
            foreach ($terms as $term) :
                $output .= '<optionvalue>';
                $output .= '<value>' . $this->cData($term) . '</value>';
                $output .= '<label>' . $this->cData($term) . '</label>';
                $output .= '</optionvalue>';
            endforeach;
            
            $output .= '</optionvalues>';
            $output .= '</attribute>';

            $output .= '<attribute>';
            $output .= '<id>30003</id>';
            $output .= '<code>extendons_pc_product_condition</code>';
            $output .= '<name>extendons_pc_product_condition</name>';
            $output .= '<group_id>' . "" . '</group_id>';
            $output .= '<group_label>' . "" . '</group_label>';
            $output .= '<type>select</type>';
            
            $output .= '<optionvalues>';
            $terms = array("", "Brand_New", "New", "New_with_box", "New_With_Defects", "New_Without_Box",
                "New_with_tags", "New_without_tags", 
                "Acceptable", "Certified_pre_owned", "Good", "Like_new", "Pre_owned", "Used", "Very_good",
                "Manufacturer_Refurbished", "Remanufactured", "Retread", "Seller_refurbished",
                "For_parts", "Damaged");

            foreach ($terms as $term) :
                $output .= '<optionvalue>';
                $output .= '<value>' . $this->cData($term) . '</value>';
                $output .= '<label>' . $this->cData($term) . '</label>';
                $output .= '</optionvalue>';
            endforeach;
            
            $output .= '</optionvalues>';
            $output .= '</attribute>';

        endif;

        $output .= '</attributes>';
        $output .= '</plugin>';
        echo $output;
    }

    private function cData($str)
    {
        return '<![CDATA[' . $str . ']]>';
    }
}
