<?php

require('../include.php');
@ini_set('log_errors', 1);
@ini_set('display_errors', 1); /* enable or disable public display of errors (use 'On' or 'Off') */
@ini_set('error_log', dirname(__FILE__) . '/php-errors.log'); /* path to server-writable log file */
@ini_set('error_reporting', E_ALL); /* the php parser to  all errors, excreportept notices.  */

$scoc_utility = new scoc_utility();
$scoc_lib = new scoc_lib();



$blog_id = get_current_blog_id();
echo "CURRENT BLOG ID: " . $blog_id . " <br />";

$sku = "UC-MYAQ-8CM6";
$product_id = wc_get_product_id_by_sku($sku);
echo "SKU " . $sku . " product id=" . $product_id . "\r\n<br>";

$root_category_id = $scoc_lib->getRootCategoryId();
echo "ROOT CATEGORY ID: <br>";
var_dump($root_category_id);
echo "<br>";

getBrands();
echo "<br><br>";

// gets an array of all parent category levels
$product_parent_categories_all_hierachy = get_ancestors(6, 'product_cat');

// This cuts the array and extracts the last set in the array
$last_parent_cat = array_slice($product_parent_categories_all_hierachy, -1, 1, true);
foreach ($last_parent_cat as $last_parent_cat_value) {
    // $last_parent_cat_value is the id of the most top level category, can be use whichever one like
    echo '<strong>' . $last_parent_cat_value . '</strong>';
}



$groups = acf_get_field_groups();
var_dump($groups);

exit;

echo "Response from file_get_contents(): <br />";
$remoteContent = file_get_contents("http://www.google.com");
echo $remoteContent;


foreach ($groups as $group) :
    $fields = acf_get_fields($group);
    var_dump($fields);
endforeach;
   
exit;

$metas = get_post_custom(355);
var_dump($metas);

$metas = get_post_meta(355);
var_dump($metas);


if (isset($_GET["orderid"])) :
    $actualOrderId = getOrderIDFromOrderNumber($_GET["orderid"]);
    var_dump($actualOrderId);
endif;

function getOrderIDFromOrderNumber($order_number)
{
    //var_dump($orderNumber);
    if (!class_exists("WC_Seq_Order_Number_Pro")) :
        echo "WC_Seq_Order_Number_Pro does not exist";
        //return $order_number;
    endif;

    try {
        //$wc_Seq_Order_Number_Pro=new WC_Seq_Order_Number_Pro();
        //$orderId = $wc_Seq_Order_Number_Pro->find_order_by_order_number($orderNumber);
        ////var_dump($orderId);
        // The above code has error. So copied function body from find_order_by_order_number
        // search for the order by custom order number
        $query_args = array(
            'numberposts' => 1,
            'meta_key' => '_order_number_formatted',
            'meta_value' => $order_number,
            'post_type' => 'shop_order',
            'post_status' => 'any',
            'fields' => 'ids',
        );

        $posts = get_posts($query_args);
        list($order_id) = !empty($posts) ? $posts : null;

        // order was found
        if (null !== $order_id) {
            return $order_id;
        }
        
        $order = new WC_Order($order_id);
        var_dump($order);
        
        // if we didn't find the order, then it may be that this plugin was disabled and an order was placed in the interim
        $order = wc_get_order($order_number);
        
        if ($order != null) :
            if ('' !== SV_WC_Order_Compatibility::get_meta($order, 'order_number_formatted')) {
                // _order_number was set, so this is not an old order,
                // it's a new one that just happened to have post_id that matched the searched-for order_number
                return 0;
            }

            return SV_WC_Order_Compatibility::get_prop($order, 'id');
        endif;

        return 0;
        //return $orderId==0? $orderNumber : $orderId;
    } catch (Exception $ex) {
        return 0;
    }
}

function getBrands()
{
    $scoc_utility = new scoc_utility();
    $scoc_lib = new scoc_lib();

    if (defined("PWB_PLUGIN")) :
        echo "yes";
    endif;

    echo "ALl BRANDS <br />";
    $brands = get_terms('pwb-brand', array('hide_empty' => 0));
    //var_dump($brands);

    foreach ($brands as $key => $brandObject) :
        echo "---------------------------------";
        echo "Name: " . $brandObject->name;
        if ($brandObject->name === "Vintage") :
            echo ($brandObject->term_id) . "<br>";
        endif;
    endforeach;

    echo "Brand ID: ";
    echo $scoc_lib->getBrandIdByName("Vintage", 0);
    //echo getBrandIdByName("Vintage", 1);
}



exit;
