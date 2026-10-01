<?php

require('../include.php');
@ini_set('log_errors', 1);
@ini_set('display_errors', 1); /* enable or disable public display of errors (use 'On' or 'Off') */
@ini_set('error_log', dirname(__FILE__) . '/php-errors.log'); /* path to server-writable log file */
@ini_set('error_reporting', E_ALL); /* the php parser to  all errors, excreportept notices.  */

$variationService = new scoc_variation(1);

$parent_id = 324; // Or get the variable product id dynamically
$arr = array(
    'Figurine Color' => array('Black and Tan', 'Bronze', 'Tan'),
    'Pose' => array('Sitting'),
);
//var_dump($arr);
$variationService->updateVariationProperties($parent_id, $arr);


// The variation data
$variation_data = array(
    'attributes' => array(
        'figurine-color' => 'Black and Tan',
        'pose' => 'Sitting',
    ),
    'sku' => 'BTS',
    'regular_price' => '22.00',
    'sale_price' => '',
    'stock_qty' => 10,
);
var_dump($variation_data);
// The function to be run
$variationService->updateSingleVariation($parent_id, $variation_data);

// The variation data
$variation_data = array(
    'attributes' => array(
        'figurine-color' => 'Tan',
        'pose' => 'Sitting',
    ),
    'sku' => 'T001',
    'regular_price' => '11.00',
    'sale_price' => '9',
    'stock_qty' => 100,
);
// The function to be run
$variationService->updateSingleVariation($parent_id, $variation_data);

// The variation data
$variation_data = array(
    'attributes' => array(
        'figurine-color' => 'Bronze',
        'pose' => 'Sitting',
    ),
    'sku' => 'B001',
    'regular_price' => '11.00',
    'sale_price' => '9',
    'stock_qty' => 100,
);
// The function to be run
$variationService->updateSingleVariation($parent_id, $variation_data);

exit;
