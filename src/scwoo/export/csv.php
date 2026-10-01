<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require('../include.php');
ini_set("max_execution_time", 600);

$scoc_utility = new scoc_utility();
$scoc_encoder = new scoc_encoder();
    
$qtrStrArray = $scoc_encoder->authenticate(true, false);

if ($qtrStrArray == null) {
    $scoc_utility->printHeader();
    unset($scoc_utility, $scoc_encoder);
    return;
}
    
// output headers so that the file is downloaded rather than displayed
header('HTTP/1.1 200 OK');
header("Pragma: public");
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header("Last-Modified: " . date('r'));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=data.csv');

// create a file pointer connected to the output stream
$fp = fopen('php://output', 'w');

// output the column headings
fputcsv($fp, _getCsvHeaders());

GenerateCsvDataArray($fp);

fclose($fp);

function GenerateCsvDataArray($output)
{
    global $wpdb;

    $currentPageIndex = isset($_GET['currentpageindex']) ? (int) $_GET['currentpageindex'] : 1;
    $rowsPerPage = isset($_GET['rowsperpage']) ? $_GET['rowsperpage'] : 100000;

    $query_args = array(
        'return' => 'ids',
        'orderby' => 'id',
        'order' => 'DESC',
        'paginate' => true,
        'limit' => $rowsPerPage,
        'page'  => $currentPageIndex
    );

    $query = new WC_Product_Query($query_args);
    $productCollection = $query->get_products();

    // Extract product IDs
    $product_ids = $productCollection->products; // Array of product IDs for the current page

    // Debugging information
    $total_products = $productCollection->total; // Total number of products
    $total_pages = $productCollection->max_num_pages; // Total number of pages

    //var_dump($product_ids, $total_products, $total_pages);

    //echo $wpdb->last_query;

    foreach ($product_ids as $product_id) :
        $_product = wc_get_product($product_id);

        
        if ($_product->get_sku() != "" && $_product->price!="") :
            $arr = array();
            $arr[] = $_product->get_id();
            $arr[] = $_product->get_sku();
            $arr[] = "";
            $arr[] = $_product->get_price();
            $arr[] = $_product->get_stock_quantity(); //get_total_stock()
            $arr[] = get_permalink($_product->get_id()); // 2.4.1.0
            
            //var_dump($arr);
            fputcsv($output, $arr);
        endif;
        
        
        // children if any...
        //$child_product_variations = get_children('post_parent=' . $post_id . '&post_type=product_variation');
        $child_product_variations = $_product->get_children();
        
        if ($child_product_variations) :
            foreach ($child_product_variations as $child_id) :
                $_childProduct = wc_get_product($child_id);

                if ($_childProduct->get_sku() != "" && $_childProduct->price!="") :
                    $arr = array();
                    
                    $arr[] = $_childProduct->get_id();
                    $arr[] = $_childProduct->get_sku();
                    $arr[] = $_product->get_sku();
                    $arr[] = $_childProduct->get_price();
                    $arr[] = $_childProduct->get_stock_quantity();
                    $arr[] = "";

                    fputcsv($output, $arr);
                endif;
            endforeach;
        endif;
    endforeach;
}

function _getCsvHeaders()
{

    $headers = array("id", "sku", "parent_sku", "price", "qty", "url");
    return $headers;
}
