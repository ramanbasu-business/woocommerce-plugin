<?php

$verbose = 0;
if (isset($_GET['verbose']) && $_GET['verbose'] != "") {
    $verbose = $_GET['verbose'] == "1" ? 1 : 0;
}

ob_start();
/** Make sure that the WordPress bootstrap has run before continuing. */
require('../include.php');
//$_woocommerce = $GLOBALS['woocommerce'];
//if (is_woocommerce_activated() == FALSE) {
//    exit;
//}

//switch_to_blog(2);
getProducts($verbose);
//restore_current_blog();

function getProducts($verbose)
{
    global $wp_version;
    global $woocommerce;

    $scoc_lib = new scoc_lib();
    $scoc_utility = new scoc_utility();
    $scoc_encoder = new scoc_encoder();

    $scoc_lib->switchToBlog();
    $scoc_utility->printHeader();

    $qtrStrArray = $scoc_encoder->authenticate(true, false);
    //var_dump($qtrStrArray); exit;
    if ($qtrStrArray == null) {
        unset($scoc_utility, $scoc_encoder);
        return;
    }



    $sku = isset($_GET['sku']) ? $_GET['sku'] : null;
    $currentPageIndex = isset($_GET['currentpageindex']) ? (int) $_GET['currentpageindex'] : 1;
    //$currentPageIndex--;
    if ($currentPageIndex < 1) :
        $currentPageIndex = 1;
    endif;
    
    $rowsPerPage = isset($_GET['rowsperpage']) ? $_GET['rowsperpage'] : 4;

    echo '<?xml version="1.0" encoding="UTF-8" ?>';
    echo '<products>';
    echo $scoc_lib->getVersionXml();

    $query_args = array(
        'return' => 'ids',
        'orderby' => 'id',
        'order' => 'DESC',
        'paginate' => true,
        'limit' => $rowsPerPage,
        'page'  => $currentPageIndex
    );
    
    if (!empty($sku)) :
        $query_args["sku"] = $sku;
    endif;

    $query = new WC_Product_Query($query_args);
    $productCollection = $query->get_products();


    //$loop = new WP_Query($args);
    if ($verbose == 1) :
        var_dump($query_args);
        //echo $query->request;
    endif;

    $recordCount = (int) $productCollection->total;
    $totalPages = $productCollection->max_num_pages;

    echo '<recordcount>' . $recordCount . '</recordcount>';
    if (isset($_GET['currentpageindex']) && isset($_GET['rowsperpage'])) {
        echo '<currentpageindex>' . ($currentPageIndex) . '</currentpageindex>';
        echo '<totalpages>' . $totalPages . '</totalpages>';
    }

    //echo '<products>';
    if ($productCollection->products) :
        foreach ($productCollection->products as $product_id) :
            $product = wc_get_product($product_id);

            if ($verbose == 1) :
                //var_dump($product);//return;
                var_dump($product->get_children());
            endif;
            
            $product_type = $scoc_lib->getProductType($product_id);

            echo '<product>';
            echo '<id>' . $product->get_id() . '</id>';
            echo '<sku>' . $product->get_sku() . '</sku>';
            echo '<name><![CDATA[' . $product->get_name() . ']]></name>';

            
            echo '<image>';
            $featured_image_id = $product->get_image_id();
            if (!empty($featured_image_id)) :
                $image = wp_get_attachment_url($featured_image_id);
                echo '<![CDATA[' . $image . ']]>';
            endif;
            echo '</image>';


            $image_url = '';
            $attachment_ids = $product->get_gallery_image_ids();
            foreach ($attachment_ids as $attachment_id) :
                //$image = wp_get_attachment_image_src($attachment_id);
                $image = wp_get_attachment_url($attachment_id);
                echo '<thumbnail><![CDATA[' . $image . ']]></thumbnail>';
            endforeach;

            
            
            echo '<post_name><![CDATA[' . $product->get_title() . ']]></post_name>';
            
            //echo '<product_type><![CDATA[' . $product_type . ']]></product_type>';
            echo '<product_type><![CDATA[' . $product->get_type() . ']]></product_type>';


            echo '<description><![CDATA[' . $product->get_description() . ']]></description>';
            echo '<short_description><![CDATA[' . $product->get_short_description() . ']]></short_description>';
            echo '<price>' . $product->get_price() . '</price>';

            echo '<is_in_stock>' . $product->is_in_stock() . '</is_in_stock>';

            // 1.6.0
            echo '<children>';
            /*$children = $product->get_children();

            if (sizeof($children) > 0) :
                $total_stock = max(0, $product->get_stock_quantity());

                foreach ($children as $child_id) :
                    echo '<child>';
                    echo '<variation_id>' . $child_id . '</variation_id>';

                    $stock = 0;
                    if ('yes' === get_post_meta($child_id, '_manage_stock', true)) :
                        $stock = get_post_meta($child_id, '_stock', true);
                        $total_stock += max(0, wc_stock_amount($stock));
                    endif;
                    echo '<sku><![CDATA[' . get_post_meta($child_id, '_sku', true) . ']]></sku>';
                    echo '<price>' . get_post_meta($child_id, '_price', true) . '</price>';
                    echo '<stock>' . $stock . '</stock>';
                    echo '</child>';
                endforeach;
            else :
                $total_stock = $product->get_stock_quantity();
            endif;
            unset($children);*/

            $children = $product->get_children();
            
            if (sizeof($children) > 0) :
                $total_stock = max(0, $product->get_stock_quantity());
                $variation_ids = $children;

                foreach ($variation_ids as $variation_id) :
                    $variation = wc_get_product($variation_id);

                    if ($variation && $variation->exists()) :
                        //$variation_id = $variation['variation_id'];
                        $variation_obj = new WC_Product_variation($variation_id);
                        //var_dump($variation_obj);

                        echo '<child>';
                        echo '<variation_id>' . $variation_id . '</variation_id>';

                        $stock = 0;
                        if (true === $variation_obj->get_manage_stock()) :
                            $stock = $variation_obj->get_stock_quantity();
                            $total_stock += max(0, wc_stock_amount($stock));
                        endif;

                        echo '<sku><![CDATA[' . $variation_obj->get_sku() . ']]></sku>';
                        echo '<price>' . $variation_obj->get_price() . '</price>';
                        echo '<stock>' . $stock . '</stock>';
                        //echo '<image>' . $variation['image']['url'] . '</image>';

                        /*
                        echo '<image>';
                        $featured_image_id = $variation_obj->get_image_id();
                        if (!empty($featured_image_id)) :
                            $image = wp_get_attachment_url($featured_image_id);
                            echo '<![CDATA[' . $image . ']]>';
                        endif;
                        echo '</image>';
                        */

                        echo '</child>';
                    endif;
                endforeach;
            else :
                    $total_stock = $product->get_stock_quantity();
            endif;

            /*
            if (sizeof($children) > 0) :
                $variations = $product->get_available_variations();
                //var_dump($variations);
                if ($variations) :
                    $total_stock = max(0, $product->get_stock_quantity());

                    foreach ($variations as $variation) :
                        //var_dump($variation);
                        $variation_id = $variation['variation_id'];
                        $variation_obj = new WC_Product_variation($variation_id);
                        //var_dump($variation_obj);

                        echo '<child>';
                        echo '<variation_id>' . $variation_id . '</variation_id>';

                        $stock = 0;
                        if (true === $variation_obj->get_manage_stock()) :
                            $stock = $variation_obj->get_stock_quantity();
                            $total_stock += max(0, wc_stock_amount($stock));
                        endif;

                        echo '<sku><![CDATA[' . $variation_obj->get_sku() . ']]></sku>';
                        echo '<price>' . $variation_obj->get_price() . '</price>';
                        echo '<stock>' . $stock . '</stock>';

                        echo '</child>';
                    endforeach;
                endif;
            else :
                    $total_stock = $product->get_stock_quantity();
            endif;
            */
                
            echo '</children>';

                echo '<stock>' . $total_stock . '</stock>';
             //echo  '<stock>' . $product->get_stock_quantity() . '</stock>';


             //echo '<backorders_allowed>' . $product->backorders_allowed() . '</backorders_allowed>';
                echo '<backorders>' . $product->get_backorders() . '</backorders>';
             //echo  '<available>' . $product->get_availability( ) . '</available>';
                echo '<featured>' . $product->get_featured() . '</featured>';

                echo '<is_downloadable>' . $product->get_downloadable() . '</is_downloadable>';
                echo '<virtual>' . $product->get_virtual() . '</virtual>';
                echo '<needs_shipping>' . $product->needs_shipping() . '</needs_shipping>';
                echo '<is_shipping_taxable>' . $product->is_shipping_taxable() . '</is_shipping_taxable>';
                echo '<shipping_class_id>' . $product->get_shipping_class_id() . '</shipping_class_id>';

                echo '<is_sold_individually>' . $product->is_sold_individually() . '</is_sold_individually>';
                echo '<tax_status>' . $product->get_tax_status() . '</tax_status>';
                echo '<tax_class>' . $product->get_tax_class() . '</tax_class>';
                echo '<is_visible>' . $product->get_catalog_visibility() . '</is_visible>';

                echo '<weight>' . $product->get_weight() . '</weight>';
                echo '<height>' . ($product->has_weight() ? $product->get_weight() : 0) . '</height>';

             // Get tags
                $tags_array = [];
                $terms = get_the_terms($product->get_id(), "product_tag");
            if (!empty($terms)) :
                foreach ($terms as $term) :
                    $tags_array[] =$term->name;
                endforeach;
            endif;

                echo '<tags><![CDATA[' . implode(', ', $tags_array) . ']]></tags>';


                $atts = $product->get_attributes();
            if ($atts) {
                echo '<attributes>';
                foreach ($atts as $_attrName => $_arrData) {
                    echo '<attribute>';
                    echo '<code>' . $_attrName . '</code>';
                    echo '<name>' . $_arrData['name'] . '</name>';
                    echo '<value>' . $_arrData['value'] . '</value>';
                    echo '</attribute>';
                }
                echo '</attributes>';
            }

             //global $post;
                $terms = get_the_terms($product->get_id(), "product_cat");
            if ($terms) {
                echo '<categories>';
                foreach ($terms as $term) {
                    $product_cat_id = $term->term_id;
                    echo '<category>' . $product_cat_id . '</category>';
                    //break;
                }
                echo '</categories>';
            }
             //echo '<li><a href="' . get_permalink() . '">' . get_the_post_thumbnail($loop->post->ID, 'thumbnail') . '</a></li>';
                echo '</product>';
        endforeach;
    endif;
    echo '</products>';

    wp_reset_query();
    //ob_end_clean();
    //echo $output;

    $scoc_lib->restoreCurrentBlog();
    unset($scoc_utility);
}

exit;
