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

$page = isset($_GET['currentpageindex']) ? (int) $_GET['currentpageindex'] : 1;
$limit = isset($_GET['rowsperpage']) ? (int)$_GET['rowsperpage'] : 100000;


// Export as JSON or CSV based on user preference
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    
    // Fetch product data
    $products_data = generate_product_data_json($limit, $page);
    export_to_json($products_data);    
} else {
    // Fetch product data
    $products_data = generate_product_data_csv($limit, $page);
    export_to_csv($products_data);
}


function generate_product_data_json($limit, $page)
{
    global $wpdb;    
    $scoc_lib = new scoc_lib();

    $data_array = array(
        'channel' => $scoc_lib->getVersionArray(),
        'total_items' => 0,
        'current_page' => $page,
        'per_page' => $limit,
        'data' => null,
    );

    $query_args = array(
        'return' => 'ids',      // Return only IDs for efficiency
        'orderby' => 'id',
        'order' => 'DESC',
        'paginate' => true,
        'limit' => $limit,      // Number of products per page
        'page'  => $page,       // Number of products per page
    );

    $query = new WC_Product_Query($query_args);
    $productCollection = $query->get_products();

    if($productCollection == null):
        return $data_array;
    endif;
    
    // Extract product IDs
    $product_ids = $productCollection->products; // Array of product IDs for the current page

    // Debugging information
    $total_products = $productCollection->total; // Total number of products
    $total_pages = $productCollection->max_num_pages; // Total number of pages
    $total_items = $productCollection->total;          // Total number of products

    //var_dump($product_ids, $total_products, $total_pages);
    //echo $wpdb->last_query;

    $products_data = array();

    foreach ($product_ids as $product_id) :
        $product = wc_get_product($product_id);

        // Skip if the product is invalid
        if (!$product) continue;

        // Populate product data
        $products_data[] = array(
            'id' => $product->get_id(),
            'sku' => $product->get_sku(),
            'parent_sku' => null,
            'price' => (float)$product->get_price(),
            'qty' => $product->get_stock_quantity(),
            'url' => $product->get_permalink(),
        );
    endforeach;

    $data_array["channel"] = $scoc_lib->getVersionArray();
    $data_array['total_items'] = $total_items;
    $data_array['current_page'] = $page;
    $data_array['per_page'] = $limit;
    $data_array['data'] = $products_data;
    
    return $data_array;
}

function generate_product_data_csv($limit, $page)
{
    global $wpdb;    
    $scoc_lib = new scoc_lib();
    
    $products_data = array();

    $query_args = array(
        'return' => 'ids',      // Return only IDs for efficiency
        'orderby' => 'id',
        'order' => 'DESC',
        'paginate' => true,
        'limit' => $limit,      // Number of products per page
        'page'  => $page,       // Number of products per page
    );

    $query = new WC_Product_Query($query_args);
    $productCollection = $query->get_products();

    if($productCollection == null):
        return $products_data;
    endif;
    
    // Extract product IDs
    $product_ids = $productCollection->products; // Array of product IDs for the current page

    // Debugging information
    $total_products = $productCollection->total; // Total number of products
    $total_pages = $productCollection->max_num_pages; // Total number of pages
    $total_items = $productCollection->total;          // Total number of products

    //var_dump($product_ids, $total_products, $total_pages);
    //echo $wpdb->last_query;

    $products_data = array();

    foreach ($product_ids as $product_id) :
        $product = wc_get_product($product_id);

        // Skip if the product is invalid
        if (!$product) continue;

        // Populate product data
        $products_data[] = array(
            'id' => $product->get_id(),
            'sku' => $product->get_sku(),
            'parent_sku' => null,
            'price' => (float)$product->get_price(),
            'qty' => $product->get_stock_quantity(),
            'url' => $product->get_permalink(),
        );
    endforeach;

        
    return $products_data;
}


// Function to export data to JSON
function export_to_json($products_data) {
    header('Content-Type: application/json');
    header('HTTP/1.1 200 OK');
    header("Pragma: public");
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header("Last-Modified: " . date('r'));

    echo json_encode($products_data, JSON_PRETTY_PRINT);
    exit;
}

// Function to export data to CSV
function export_to_csv($products_data) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=data.csv');
    header('HTTP/1.1 200 OK');
    header("Pragma: public");
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header("Last-Modified: " . date('r'));


    $output = fopen('php://output', 'w');
    fputcsv($output, array("id", "sku", "parent_sku", "price", "qty", "url"));

    foreach ($products_data as $product) {
        fputcsv($output, $product);
    }

    fclose($output);
    exit;
}
