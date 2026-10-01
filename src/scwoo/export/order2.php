<?php
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);
define('WP_CACHE', false);
ini_set("display_errors", true);

header('HTTP/1.1 200 OK');
header("Pragma: no-cache");
header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
header("Content-type: text/xml");

echo '<?xml version="1.0" encoding="UTF-8" ?>';

ob_start();
/** ORDER EXPORT PLUGIN */
require('../include.php');

getOrders();


function test_order()
{
    global $start_date, $end_date, $woocommerce, $wpdb, $wp_locale;
    $scoc_lib = new scoc_lib();
    ob_end_clean();

    $delimOrderStatus = null; // "'wc-processing','processing'";
    $orders = $scoc_lib->getOrders(
        $delimOrderStatus,
        0,
        10,
        null,
        null,
        null,
        0
    );
    //var_dump($orders);

    foreach ($orders as $k => $order) :
        $order_id = $order->ID;
        $order = new WC_Order($order_id);
        echo 'order id: ' . $order_id . ' status: ' . $order->status . '<br/>';
    endforeach;

    $sql = "SELECT COUNT(posts.ID)";
    $sql .= " FROM {$wpdb->posts} AS posts ";
    $sql .= " LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id "
            . "LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID = rel.object_ID "
            . "LEFT JOIN {$wpdb->term_taxonomy} AS tax USING( term_taxonomy_id ) "
            . "LEFT JOIN {$wpdb->terms} AS term USING( term_id ) "
            . " WHERE 1=1 "
            . " AND meta.meta_key = '_order_total' "
            . " AND posts.post_type = 'shop_order' ";

    $sql .=" AND posts.post_status IN(" . $delimOrderStatus . ") ";
    $records = $wpdb->get_results($sql);
    //var_dump($sql);
    var_dump($records);

    $recordCount = $scoc_lib->getOrderCount(
        $delimOrderStatus,
        null,
        null,
        null,
        1
    );
    echo $recordCount;
}

function getStateName($countryCode, $stateCode)
{
    if (empty($stateCode)) {
        return '';
    }
    $c = new WC_Countries();
    if (empty($c->states[$countryCode])) {
        return '';
    }

    if (empty($c->states[$countryCode][$stateCode])) {
        return '';
    }
    return $c->states[$countryCode][$stateCode];
}

function getOrders()
{
    /*
     * currentpageindex starts from 1
     */
    global $start_date, $end_date, $woocommerce, $wpdb, $wp_locale;
    global $wp_version;

    //ob_end_clean();
    
    
    $scoc_lib = new scoc_lib();
    $scoc_utility = new scoc_utility();
    $scoc_encoder = new scoc_encoder();
    //$scoc_utility->printHeader();

    $qtrStrArray = $scoc_encoder->authenticate();
    if ($qtrStrArray == null) {
        unset($scoc_utility, $scoc_lib, $scoc_encoder);
        return;
    }
    $verbose = isset($_GET['verbose']) ? $_GET['verbose'] : 0;
    $order_id = isset($_GET['orderid']) ? $_GET['orderid'] : null;
    $start_date = isset($_GET['from']) ? $_GET['from'] : null;
    $end_date = isset($_GET['to']) ? $_GET['to'] : null;
    if (!$start_date) {
        //$start_date = date( 'Ymd',strtotime( date( 'Ym', current_time( 'timestamp' ) ) . '01' ) );
    }
    if (!$end_date) {
        //$end_date = date( 'Ymd', current_time( 'timestamp' ) );
    }

    //var_dump($start_date);return;
    //$start_date = strtotime($start_date);
    //$end_date = strtotime($end_date);
    if ($order_id != null) {
        $start_date = null;
        $end_date = null;
    }

    // Status filter is no longer done. 14.Jun.2016
    //$allowed_status = "'wc-completed','completed'";
    //if (isset( $_GET['processing'] )):
    //    $allowed_status .= ",'wc-processing','processing'";
    //endif;
    //if (isset( $_GET['onhold'] )):
    //    $allowed_status .= ",'wc-on-hold','on-hold'";
    //endif;
    $allowed_status = "";
    if (isset($_GET['status'])) :
        $allowed_status = $_GET['status'];
    endif;
    
    $currentPageIndex = isset($_GET['currentpageindex']) ? (int) $_GET['currentpageindex'] : 1;
    $currentPageIndex--;
    $rowsPerPage = isset($_GET['rowsperpage']) ? $_GET['rowsperpage'] : 10;

    $recordCount = $scoc_lib->getOrderCount(
        $allowed_status,
        $start_date,
        $end_date,
        $order_id,
        $verbose
    );
    $orders = $scoc_lib->getOrders(
        $allowed_status,
        $currentPageIndex,
        $rowsPerPage,
        $start_date,
        $end_date,
        $order_id,
        $verbose
    );

    if ($verbose == 1) {
        echo "Order Count: " . $recordCount . "<br>";
        echo "Order Statuses: " . $allowed_status . "<br>";
        echo "Current Page Index : " . $currentPageIndex . "<br>";
        echo "Rows Per Page: " . $rowsPerPage . "<br>";
        echo "Start Date: " . $start_date . "<br>";
        echo "End Date: " . $end_date . "<br>";
    }
    
    //
    echo '<orders>';
    echo '<channel>';
    echo '<version>' . $wp_version . '</version>';
    echo '</channel>';



    $totalPages = (int) ($recordCount / $rowsPerPage) + ( (int) ($recordCount % $rowsPerPage) > 0 ? 1 : 0 );
    echo  '<recordcount>' . $recordCount . '</recordcount>';
    //if (isset( $_GET['currentpageindex'] )) {
    echo '<currentpageindex>' . ($currentPageIndex + 1) . '</currentpageindex>';
    echo '<totalpages>' . $totalPages . '</totalpages>';
    //}

    if ($verbose == 1) :
        var_dump($orders);
    endif;

    if ($orders) {
        foreach ($orders as $k => $order) :
            $order_id = $order->ID;
            $order = new WC_Order($order_id);
            $items = $order->get_items();

            $output = '<order>';
            
            if ($verbose == 1) :
                //var_dump($order);
                $shipping_methods = $order->get_shipping_methods();
                //echo ("shipping method ----------------");
                //var_dump($shipping_methods);
                /*foreach ($items[1] as $key => $v) {
                    echo "\$output .= '<" . $key . ">'" . "." . "\$orderItem['" . $key . "']" . ". '</" . $key . ">'" . ";";
                }*/
            endif;
            //if ($order_id==244) var_dump($items);exit;
            if ($verbose == 1) :
                var_dump($order->get_customer_order_notes());
            endif;
            
            if (true) {
                //$output .= '<order_id>' . $order->id . '</order_id>';
                $this_order_id = trim(str_replace('#', '', $order->get_order_number()));
                
                $output .= '<order_id>' . $this_order_id . '</order_id>';
                $output .= '<post_id>' . $order->id . '</post_id>';
                
                $output .= '<status>' . $order->status . '</status>';
                $output .= '<order_date>' . $order->order_date . '</order_date>';
                $output .= '<modified_date>' . $order->modified_date . '</modified_date>';
                $output .= '<order_discount>' . $order->order_discount . '</order_discount>';
                $output .= '<cart_discount>' . $order->cart_discount . '</cart_discount>';
                $output .= '<order_tax>' . $order->order_tax . '</order_tax>';
                $output .= '<order_shipping>' . $order->order_shipping . '</order_shipping>';
                $output .= '<order_shipping_tax>' . $order->order_shipping_tax . '</order_shipping_tax>';
                $output .= '<order_total>' . $order->order_total . '</order_total>';
                
                //$output .= '<order_custom_fields>' . $order->order_custom_fields . '</order_custom_fields>';
                $output .= '<order_key><![CDATA[' . $order->order_key . ']]></order_key>';
                $output .= '<billing_first_name><![CDATA[' . $order->billing_first_name . ']]></billing_first_name>';
                $output .= '<billing_last_name><![CDATA[' . $order->billing_last_name . ']]></billing_last_name>';
                $output .= '<billing_company><![CDATA[' . $order->billing_company . ']]></billing_company>';
                $output .= '<billing_address_1><![CDATA[' . $order->billing_address_1 . ']]></billing_address_1>';
                $output .= '<billing_address_2><![CDATA[' . $order->billing_address_2 . ']]></billing_address_2>';
                $output .= '<billing_city><![CDATA[' . $order->billing_city . ']]></billing_city>';
                $output .= '<billing_postcode>' . $order->billing_postcode . '</billing_postcode>';
                $output .= '<billing_country>' . $order->billing_country . '</billing_country>';
                $output .= '<billing_state><![CDATA[' . $order->billing_state . ']]></billing_state>';
                $output .= '<billing_statename><![CDATA[' . getStateName(
                    $order->billing_country,
                    $order->billing_state
                ) . ']]></billing_statename>';


                $output .= '<billing_email><![CDATA[' . $order->billing_email . ']]></billing_email>';
                $output .= '<billing_phone><![CDATA[' . $order->billing_phone . ']]></billing_phone>';
                $output .= '<shipping_first_name><![CDATA[' . $order->shipping_first_name . ']]></shipping_first_name>';
                $output .= '<shipping_last_name><![CDATA[' . $order->shipping_last_name . ']]></shipping_last_name>';
                $output .= '<shipping_company><![CDATA[' . $order->shipping_company . ']]></shipping_company>';
                $output .= '<shipping_address_1><![CDATA[' . $order->shipping_address_1 . ']]></shipping_address_1>';
                $output .= '<shipping_address_2><![CDATA[' . $order->shipping_address_2 . ']]></shipping_address_2>';
                $output .= '<shipping_city><![CDATA[' . $order->shipping_city . ']]></shipping_city>';
                $output .= '<shipping_postcode>' . $order->shipping_postcode . '</shipping_postcode>';
                $output .= '<shipping_country>' . $order->shipping_country . '</shipping_country>';
                $output .= '<shipping_state><![CDATA[' . $order->shipping_state . ']]></shipping_state>';
                $output .= '<shipping_statename><![CDATA[' . getStateName(
                    $order->shipping_country,
                    $order->shipping_state
                ) . ']]></shipping_statename>';

                $shipping_method_title = $order->get_shipping_method();
                if ($shipping_method_title==="") :
                    $shipping_method_title=$order->shipping_method_title;
                endif;
                
                $output .= '<shipping_method><![CDATA[' . $shipping_method_title . ']]></shipping_method>';
                $output .= '<shipping_method_title><![CDATA[' . $shipping_method_title . ']]></shipping_method_title>';
                $output .= '<payment_method><![CDATA[' . $order->payment_method . ']]></payment_method>';
                $output .= '<payment_method_title><![CDATA[' . $order->payment_method_title . ']]></payment_method_title>';

                //$output .= '<taxes>' . $order->taxes . '</taxes>';
                $output .= '<customer_user>' . $order->customer_user . '</customer_user>';
                $output .= '<user_id>' . $order->user_id . '</user_id>';
                $output .= '<completed_date>' . $order->completed_date . '</completed_date>';
                //$output .= '<billing_address>' . $order->billing_address . '</billing_address>';
                //$output .= '<formatted_billing_address>' . $order->formatted_billing_address . '</formatted_billing_address>';
                //$output .= '<shipping_address>' . $order->shipping_address . '</shipping_address>';
                //$output .= '<formatted_shipping_address>' . $order->formatted_shipping_address . '</formatted_shipping_address>';
                $output .= '<post_status>' . $order->post_status . '</post_status>';
                $output .= '<prices_include_tax>' . $order->prices_include_tax . '</prices_include_tax>';
                $output .= '<tax_display_cart>' . $order->tax_display_cart . '</tax_display_cart>';
                $output .= '<display_totals_ex_tax>' . $order->display_totals_ex_tax . '</display_totals_ex_tax>';
                $output .= '<display_cart_ex_tax>' . $order->display_cart_ex_tax . '</display_cart_ex_tax>';
            }

            $output .= '<customer_notes>';
            $orderNotes = $order->get_customer_order_notes();
            if ($orderNotes && count($orderNotes)>0) :
                foreach ($orderNotes as $orderNote) :
                    $output .= '<customer_note><![CDATA[' . $orderNote->comment_content . ']]></customer_note>';
                endforeach;
            endif;
            
            if (!empty($order->customer_message)) :
                $output .= '<customer_note><![CDATA[' . $order->customer_message . ']]></customer_note>';
            endif;
            $output .= '</customer_notes>';
            
            if ($items) {
                $output .= '<orderitems>';
                foreach ($items as $key => $orderItem) {
                    $output .= '<orderitem>';
                    $output .= '<name><![CDATA[' . $orderItem['name'] . ']]></name>';
                    $output .= '<type><![CDATA[' . $orderItem['type'] . ']]></type>';
                    //$output .= '<item_meta>' . $orderItem['item_meta'] . '</item_meta>';
                    $output .= '<qty>' . $orderItem['qty'] . '</qty>';
                    $output .= '<tax_class>' . $orderItem['tax_class'] . '</tax_class>';
                    $output .= '<product_id>' . $orderItem['product_id'] . '</product_id>';

                    $order_item_sku = trim(get_post_meta(
                        $orderItem['product_id'],
                        '_sku',
                        true
                    ));
                    $output .= '<product_sku>' . $order_item_sku . '</product_sku>';

                    $variation_id = !empty($orderItem['variation_id']) ? $orderItem['variation_id'] : 0;
                    $output .= '<variation_id>' . $variation_id . '</variation_id>';
                    $variation_item_sku = '';
                    if ($variation_id > 0) {
                        $variation_item_sku = trim(get_post_meta(
                            $variation_id,
                            '_sku',
                            true
                        ));
                    }
                    $output .= '<variation_sku>' . $variation_item_sku . '</variation_sku>';


                    $output .= '<line_subtotal>' . $orderItem['line_subtotal'] . '</line_subtotal>';
                    $output .= '<line_total>' . $orderItem['line_total'] . '</line_total>';
                    $output .= '<line_tax>' . $orderItem['line_tax'] . '</line_tax>';
                    $output .= '<line_subtotal_tax>' . $orderItem['line_subtotal_tax'] . '</line_subtotal_tax>';
                    $output .= '</orderitem>';
                }
                $output .= '</orderitems>';
            }
            
            ## Refunds
            $output .= '<refunds>';
            $refunds = $order->get_refunds();
            if ($refunds) :
                foreach ($refunds as $refund) :
                    //var_dump($refund);
                    $date = new DateTime($refund->date);
                    $date->setTimezone(new DateTimeZone('America/New_York'));
                    
                    $output .= '<refund>';
                    $output .= '<id>' . $refund->id . '</id>';
                    $output .= '<date>' . $date->format("Y-m-d H:i:s") . '</date>';
                    $output .= '<amount>' . $refund->get_refund_amount() . '</amount>';
                    $output .= '<reason><![CDATA[' . $refund->reason . ']]></reason>';
                    $output .= '</refund>';
                endforeach;
            endif;
            $output .= '</refunds>';
            
            $output .= '<trackings>';
            if (class_exists('WC_Shipment_Tracking_Actions')) :
                $st = WC_Shipment_Tracking_Actions::get_instance();
                $tracking_items = $st->get_tracking_items($order_id);
                if (count($tracking_items) > 0) :
                    foreach ($tracking_items as $item) :
                        $output .= '<tracking>';
                        $output .= '<number>'.  $item[ 'tracking_number' ] . '</number>';
                        $output .= '</tracking>';
                    endforeach;
                endif;
            endif;
            
            $output .= '</trackings>';
            $output .= '</order>';
            echo trim($output);
            //return;
        endforeach;
    }
    
    
    echo '</orders>';

    //wp_reset_query();
    //
    //echo trim($output);
    unset($scoc_utility, $scoc_lib, $scoc_encoder);
    
    $output = ob_get_contents();
    ob_end_clean();
    echo ltrim($output);
    //echo "X". substr($output, 0, 10) . "X";
}

exit;
