<?php

/*
 * Features: Export list of normal and variable parent sku products via csv or json
 * Ver: 7.19.0.0
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

// Fetch product data
//$products_data = generate_product_data($query_args);


// Export as JSON or CSV based on user preference
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    $products_data = generate_product_variation_json($limit, $page);
    export_to_json($products_data);    
} else {
    $products_data = generate_product_variation_csv($limit, $page);
    export_to_csv($products_data);
}


function generate_product_variation_json($limit, $page) 
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

    $offset = ($page - 1) * $limit;

    // Query to fetch variation product IDs
    $variation_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID 
        FROM {$wpdb->posts} 
        WHERE post_type = 'product_variation' 
        AND post_status = 'publish'
        LIMIT %d OFFSET %d",
        $limit,
        $offset
    ));

    if(empty($variation_ids) || $variation_ids == null):
        return $data_array;
    endif;

    $variations = array();

    foreach ($variation_ids as $variation_id) {
        $variation = wc_get_product($variation_id);
        if ($variation) {
            $parent_id = $variation->get_parent_id();
            $parent_product = $parent_id ? wc_get_product($parent_id) : null;

            $variations[] = array(
                'id'         => $variation->get_id(),
                'sku'        => $variation->get_sku(),
                'parent_sku' => $parent_product ? $parent_product->get_sku() : null,
                'price'      => (float)$variation->get_price(),
                'stock_qty'  => $variation->get_stock_quantity(),
                'url'        => $variation->get_permalink(),
            );
        }
    }

    // Get the total count of all variations in the store
    $total_variations = $wpdb->get_var("
        SELECT COUNT(ID)
        FROM {$wpdb->posts}
        WHERE post_type = 'product_variation'
        AND post_status = 'publish'
    ");

    $data_array["channel"] = $scoc_lib->getVersionArray();
    $data_array['total_items'] = (int)$total_variations;
    $data_array['current_page'] = $page;
    $data_array['per_page'] = $limit;
    $data_array['data'] = $variations;

    return $data_array;
}

function generate_product_variation_csv($limit, $page) 
{
    global $wpdb;
    
    $scoc_lib = new scoc_lib();

    
    $offset = ($page - 1) * $limit;

    // Query to fetch variation product IDs
    $variation_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID 
        FROM {$wpdb->posts} 
        WHERE post_type = 'product_variation' 
        AND post_status = 'publish'
        LIMIT %d OFFSET %d",
        $limit,
        $offset
    ));

    if(empty($variation_ids) || $variation_ids == null):
        return array();
    endif;

    $variations = array();

    foreach ($variation_ids as $variation_id) {
        $variation = wc_get_product($variation_id);
        if ($variation) {
            $parent_id = $variation->get_parent_id();
            $parent_product = $parent_id ? wc_get_product($parent_id) : null;

            $variations[] = array(
                'id'         => $variation->get_id(),
                'sku'        => $variation->get_sku(),
                'parent_sku' => $parent_product ? $parent_product->get_sku() : null,
                'price'      => (float)$variation->get_price(),
                'stock_qty'  => $variation->get_stock_quantity(),
                'url'        => $variation->get_permalink(),
            );
        }
    }

    return $variations;
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
