<?php

ob_start();
/** Make sure that the WordPress bootstrap has run before continuing. */
require('../include.php');

getCategoriesForSellerCloud();

function getCategoriesForSellerCloud()
{
    $scoc_lib = new scoc_lib();
    $scoc_utility = new scoc_utility();
    $scoc_encoder=new scoc_encoder();
    
    $scoc_lib->switchToBlog();
    $scoc_utility->printHeader();
    
    $qtrStrArray = $scoc_encoder->authenticate();
    if ($qtrStrArray == null) {
        unset($scoc_encoder);
        return;
    }
    
    $output = '<?xml version="1.0" encoding="UTF-8" ?>';
    $output .= '<categories>';
    $output .= $scoc_lib->getVersionXml();

    
    $args = array(
    'child_of' => 0,
    'menu_order' => 'ASC',
    'hide_empty' => 0,
    'hierarchical' => 1,
    'taxonomy' => 'product_cat',
    'pad_counts' => 1
    );
    $product_categories = get_categories(apply_filters('woocommerce_product_subcategories_args', $args));
    //var_dump($product_categories);
    if ($product_categories) {
        foreach ($product_categories as $category) :
            if (true) :
                $output .= '<category>';
                $output .= '<id>' . $category->cat_ID . '</id>';
                $output .= '<slug>' . $category->slug . '</slug>';
                $output .= '<name><![CDATA[' . $category->cat_name . ']]></name>';
                $output .= '<description><![CDATA[' . $category->category_description . ']]></description>';
                $output .= '<parent_id>' . $category->category_parent . '</parent_id>';

                $output .= '</category>';
            endif;
        endforeach;
    }
    $output .= '</categories>';

    wp_reset_query();
    $scoc_utility->printHeader();
    
    ob_end_clean();
    echo $output;
    
    $scoc_lib->restoreCurrentBlog();
    
    unset($scoc_utility);
    unset($product_categories);
}

exit;
