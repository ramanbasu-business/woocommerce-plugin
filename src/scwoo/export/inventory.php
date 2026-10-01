<?php

/*
 * integration SCWOO

NOTICE OF LICENSE

This source file is subject to the Open Software License (OSL 3.0)

DISCLAIMER

Do not edit or add to this file if you wish to upgrade to newer
versions in the future. If you wish to customize this extension for your
needs please refer to http://www.integration.com for more information.

@author      Raman Basu <support@integration.com>
@package     SCWOO
@copyright   Copyright (C) 2016 integration <support@integration.com>
@license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

require('../include.php');
ini_set("max_execution_time", 600);

getProducts();

function getProducts()
{
    
    global $wp_version;
    global $woocommerce;
    
    $scoc_lib = new scoc_lib();
    $scoc_utility = new scoc_utility();
    $scoc_encoder = new scoc_encoder();
    
    $scoc_lib->switchToBlog();
    $scoc_utility->printHeader();

    $qtrStrArray = $scoc_encoder->authenticate();
    //var_dump($qtrStrArray); exit;
    if ($qtrStrArray == null) {
        unset($scoc_utility, $scoc_encoder);
        return;
    }
    
    $verbose = isset($_GET['verbose']) ? $_GET['verbose'] : 0;
    //echo $verbose;exit;
    $sku = isset($_GET['sku']) ? $_GET['sku'] : null;
    
    $currentPageIndex = isset($_GET['currentpageindex']) ? (int) $_GET['currentpageindex'] : 1;
    //$currentPageIndex--;
    if ($currentPageIndex < 1) :
        $currentPageIndex = 1;
    endif;
    $rowsPerPage = isset($_GET['rowsperpage']) ? $_GET['rowsperpage'] : 10;

    $output = '<?xml version="1.0" encoding="UTF-8" ?>';
    $output .= '<products>';
    $output .= $scoc_lib->getVersionXml();

    //wc_delete_product_transients();

    $query_args = array(
        'return' => 'ids',
        'status' => 'publish',
        'orderby' => 'id',
        'order' => 'DESC',
        'paginate' => true,
        'limit' => $rowsPerPage,
        'page'  => $currentPageIndex
    );
    
    if (!empty($sku)) :
        $query_args["sku"] = $sku;
    endif;

    //$query = new WC_Product_Query($query_args);
    //$productCollection = $query->get_products();
    $productCollection = wc_get_products($query_args);
    
    if ($verbose == 1) :
        var_dump($query_args);
        var_dump($productCollection);
        exit;
    endif;

    // calculate record count manually
    //$productCount = wc_get_product('return' => 'ids','limit' => -1);
    //echo '<p>Number of products: ' . sizeof($productCount) . '</p>';

    $recordCount = (int) $productCollection->total;
    $totalPages = $productCollection->max_num_pages;
    
    $output .= '<recordcount>' . $recordCount . '</recordcount>';
    if (isset($_GET['currentpageindex']) && isset($_GET['rowsperpage'])) {
        //$output .= '<currentpageindex>' . ($currentPageIndex + 1) . '</currentpageindex>';
        $output .= '<currentpageindex>' . ($currentPageIndex) . '</currentpageindex>';
        $output .= '<totalpages>' . $totalPages . '</totalpages>';
    }

    $serialNumber = (($currentPageIndex-1) * $rowsPerPage) + 1;
    
    foreach ($productCollection->products as $product_id) :
        $product = wc_get_product($product_id);

        $output .= '<product>';
        $output .= '<serial>' . $serialNumber++ . '</serial>';
        $output .= '<id>' . $product->get_id() . '</id>';
        $output .= '<sku><![CDATA[' . $product->get_sku() . ']]></sku>';
        $output .= '<price>' . $product->get_price() . '</price>';
        $output .= '<is_in_stock>' . $product->is_in_stock() . '</is_in_stock>';
        $output .= '<stock>' . $product->get_stock_quantity() . '</stock>'; //$product->get_total_stock() deprecated
        $output .= '<url><![CDATA[' . get_permalink($product->get_id()) . ']]></url>';
        
        $output .= '<children>';
        
        // Total stock calculation
        $children = $product->get_children();
        if (sizeof($children) > 0) :
            $total_stock = max(0, $product->get_stock_quantity());

            foreach ($children as $child_id) :
                $_childProduct = wc_get_product($child_id);

                if ($_childProduct == false || $_childProduct == null) :
                    continue;
                endif;

                $output .= '<child>';
                $output .= '<id>' .  $_childProduct->get_id() . '</id>';

                $stock = 0;
                if ($_childProduct->get_manage_stock() == 1) :
                    $stock = $_childProduct->get_stock_quantity();
                    $total_stock += max(0, wc_stock_amount($stock));
                endif;

                $output .= '<manage_stock>' . $_childProduct->get_manage_stock() . '</manage_stock>';
                $output .= '<sku><![CDATA[' . $_childProduct->get_sku() . ']]></sku>';
                $output .= '<price>' . $_childProduct->get_price() . '</price>';
                $output .= '<stock>' . $stock . '</stock>';
                $output .= '<is_in_stock>' . $_childProduct->is_in_stock() . '</is_in_stock>';
                $output .= '</child>';
            endforeach;
        else :
                    $total_stock = $product->get_stock_quantity();
        endif;
            unset($children);
            $output .= '</children>';

            $output .= '</product>';
    endforeach;

    $output .= '</products>';

    wp_reset_query();


    ob_end_clean();
    echo $output;

    $scoc_lib->restoreCurrentBlog();
    unset($scoc_utility);
}

exit;
