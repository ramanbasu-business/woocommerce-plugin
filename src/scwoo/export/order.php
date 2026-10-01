<?php

/** ORDER EXPORT PLUGIN */
require('../include.php');

require_once '../lib/scoc_BaseExport.php';
require_once '../lib/scoc_authorizeNetResponse.php';
require_once '../lib/scoc_authorizeNetAIM_Response.php';
require_once '../lib/scoc_CreditCardInfo.php';


class scoc_OrderExport extends scoc_BaseExport
{
    public function __construct()
    {
        parent::__construct();
    }

    private function getStateName($countryCode, $stateCode)
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

    // 5.0.0.0
    /** Authenticate and serialize the requested WooCommerce orders as XML. */
    public function getOrders()
    {
        $this->scoc_lib->switchToBlog();

        ob_start();

        if (!$this->authenticate()) :
            return;
        endif;

        /*
         * currentpageindex starts from 1
         */
        global $start_date, $end_date, $woocommerce, $wpdb, $wp_locale;
        global $wp_version;
        global $woocommerce;

        //ob_end_clean();
        $verbose = isset($_GET['verbose']) ? $_GET['verbose'] : 0;
        
        $order_id = isset($_GET['orderid']) ? $_GET['orderid'] : null;
        $order_key = isset($_GET['order_key']) ? $_GET['order_key'] : null;
        $post_id = isset($_GET['post_id']) ? $_GET['post_id'] : null;
        
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
        if ($post_id !== null) {
            $start_date = null;
            $end_date = null;
            $order_id = $post_id;
            
            if ($verbose == 1) :
                echo "Search by post_id " . $post_id . "<br>";
            endif;
        } elseif ($order_id !== null) {
            $start_date = null;
            $end_date = null;
            
            if ($verbose == 1) :
                echo "Search by order_id " . $order_id . "<br>";
            endif;
            
            // AlphaNumeric order id to post id
            $order_id = $this->scoc_lib->getOrderIDFromOrderNumber($order_id);
            if ($order_id == null || $order_id == 0) {
                $order_id = 0;
            }
            
            if ($verbose == 1) :
                echo "Order ID: " . $order_id . "<br>";
            endif;
        } elseif ($order_key !== null) {
            $start_date = null;
            $end_date = null;
            $order_id =  wc_get_order_id_by_order_key($order_key);
            //var_dump($order_id); exit;
            
            if ($verbose == 1) :
                echo "Search by order_key " . $order_key . "<br>";
                echo "Order ID: " . $order_id . "<br>";
            endif;
            
            if ($order_id == null || $order_id == 0) {
                $order_id = 0;
            }
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
        //$currentPageIndex--;
        $rowsPerPage = isset($_GET['rowsperpage']) ? $_GET['rowsperpage'] : 10;

        //$recordCount = $this->scoc_lib->getOrderCount($allowed_status, $start_date, $end_date, $order_id, $verbose);
        $orderCollection = $this->scoc_lib->getOrders($allowed_status, $currentPageIndex, $rowsPerPage, $start_date, $end_date, $order_id, $verbose);

        if ($verbose == 1) {
            echo "Order Count: " . $orderCollection->total . "<br>";
            echo "Order Statuses: " . $allowed_status . "<br>";
            echo "Current Page Index : " . $currentPageIndex . "<br>";
            echo "Rows Per Page: " . $rowsPerPage . "<br>";
            echo "Start Date: " . $start_date . "<br>";
            echo "End Date: " . $end_date . "<br>";
        }

        echo '<orders>';
        echo $this->scoc_lib->getVersionXml();

        //$totalPages = (int) ($recordCount / $rowsPerPage) + ((int) ($recordCount % $rowsPerPage) > 0 ? 1 : 0);
        echo '<recordcount>' . $orderCollection->total . '</recordcount>';
        echo '<currentpageindex>' . ($currentPageIndex) . '</currentpageindex>';
        echo '<totalpages>' . $orderCollection->max_num_pages . '</totalpages>';


        $tax_display_cart = get_option('woocommerce_tax_display_cart');
        
        //if ($verbose == 1) var_dump($orderCollection->orders);

        if ($orderCollection->orders) {
            foreach ($orderCollection->orders as $order_id) :
                
                //7.15.1.0
                if ($order_id == 0):
                    if ($verbose == 1) :
                        echo("\n\r<br />ORDER ID: " . $order_id . " is ZERO n\r<br>");
                    endif;
                    
                    continue;
                endif;

                $order = wc_get_order($order_id);
                
                $output = '<order>';

                if ($verbose == 1) :
                    $order_data = $order->get_data();
                    var_dump($order_data);
                    echo("\n\r<br />ORDER ID: " . $order_id . " n\r<br>");
                    echo("\n\r<br />get_type: " . $order->get_type() . " n\r<br>");
                    echo("\n\r<br />get_order_number ID: " . $order->get_order_number() . " n\r<br>");
                    
                    $shipping_methods = $order->get_shipping_method();
                    echo("\n\r<br>Shipping method ----------------\n\r<br>");
                    var_dump($shipping_methods);
                    
                    echo("\n\r<br>ORDER DATA ----------------\n\r<br>");
                   //var_dump($order_data);
                endif;

                // https://stackoverflow.com/questions/55685582/limit-order-type-to-avoid-errors-when-completing-an-order-in-woocommerce

                if (true) {
                    $this_order_id = trim(str_replace('#', '', $order->get_order_number()));

                    $output .= '<order_id>' . $this_order_id . '</order_id>';
                    $output .= '<post_id>' . $order_id . '</post_id>';

                    $output .= '<status>' . $order->get_status() . '</status>';
                    $output .= '<order_date>' . $order->get_date_created() . '</order_date>';
                    $output .= '<modified_date>' . $order->get_date_modified() . '</modified_date>';
                    
                    // 2.6.0.0 Allow store credit for order
                    $storecredit_amount = 0.0;
                    $storecredit_version = '';

                    if (class_exists('WC_Smart_Coupons')) :
                        $wC_Smart_Coupons = WC_Smart_Coupons::get_instance();
                        $order_contains_storecredit = $wC_Smart_Coupons->is_order_contains_store_credit($order);
                        
                        if ($verbose == 1) :
                            echo "<br />is_order_contains_store_credit: " . (string)$order_contains_storecredit;
                        endif;
                        
                        if (class_exists('WC_SC_Order_Fields')) :
                            $wC_SC_Order_Fields = WC_SC_Order_Fields::get_instance();
                            $storecredit_amount += floatval($wC_SC_Order_Fields->get_total_credit_used_in_order($order));
                        else :
                            if ($verbose == 1) :
                                echo "<br />WC_SC_Order_Fields class does not exist";
                            endif;
                        endif;
                    else :
                        if ($verbose == 1) :
                            echo "<br />WC_Smart_Coupons class does not exist";
                        endif;
                    endif;
                    
                    

                    if (function_exists('wc_get_store_credit_used_for_order')) :
                        $this->_print("WC_Store_Credit plugin is available");

                        $storecredit_amount += floatval(wc_get_store_credit_used_for_order($order));
                        $storecredit_version = wc_get_store_credit_version_for_order($order);
                    else :
                        $this->_print("WC_Store_Credit class does not exist");
                    endif;
                    
                    $discount_total_amount = (float)$order->get_discount_total();

                    if ($storecredit_amount > 0 && $discount_total_amount > 0) :
                        //$discount_total_amount -= $storecredit_amount; // To fix with issue with 2.4.6 store credit plugin.
                    endif;

                    $coupons = $order->get_items('coupon');
                    
                    if ($verbose == 1) :
                        echo("\n\r<br>COUPONS ----------------\n\r<br>");
                    endif;

                    $xml_coupon_discount = '';
                    $coupon_discount_total = 0.0;
                    $xml_coupon_discount = '<coupons>';
                    if ($coupons) :
                        foreach ($coupons as $coupon) :
                            $c = new WC_Coupon($coupon->get_code());
                            if ($verbose == 1) :
                                var_dump($c);
                            endif;
                            

                            $xml_coupon_discount .= '<coupon>';
                            $xml_coupon_discount .= '<code><![CDATA[' . $coupon->get_code() . ']]></code>';
                            $xml_coupon_discount .= '<discount>' . number_format($coupon->get_discount(), 2) . '</discount>';
                            $xml_coupon_discount .= '<discount_type>' . $c->get_discount_type() . '</discount_type>';
                            $xml_coupon_discount .= '</coupon>';

                            if ($c->get_discount_type() =="fixed_cart" || $c->get_discount_type() != "store_credit") :
                                $coupon_discount_total += (float)$coupon->get_discount();
                            endif;
                        endforeach;
                    endif;
                    $xml_coupon_discount .= '</coupons>';


                    // Since $discount_total_amount is inclusive of $coupon_discount_total
                    // we deduct $coupon_discount_total from it and show.
                    // 7.15.1.0 floatval() method applied
                    $output .= '<order_discount>' 
                        . number_format( floatval($discount_total_amount) - floatval($coupon_discount_total), 2) 
                        . '</order_discount>';

                    $output .= '<cart_discount>' . 0.00 . '</cart_discount>';
                    $output .= '<order_storecredit version="' . $storecredit_version . '">' 
                        . number_format($storecredit_amount, 2) . '</order_storecredit>';
                    
                    // 6.1.0.0
                    $output .= '<coupon_discount>' . number_format($coupon_discount_total, 2) . '</coupon_discount>';
                    
                    // Add coupon xml nodes to the output.
                    $output .= $xml_coupon_discount;

                    if ($verbose == 1) :
                        echo("\n\r<br>order total:" . $order->get_total());
                        echo("\n\r<br>order total:" . floatval($order->get_total()));
                        echo("\n\r<br>storecredit_amount:" . $storecredit_amount);
                        //echo("\n\r<br>Final order total:" . floatval($order->get_total()) + $storecredit_amount);
                    endif;


                    $output .= '<order_tax>' . $order->get_total_tax() . '</order_tax>';
                    $output .= '<order_shipping>' . $order->get_shipping_total() . '</order_shipping>';
                    $output .= '<order_shipping_tax>' . $order->get_shipping_tax() . '</order_shipping_tax>';
                    $output .= '<order_total>' . (floatval($order->get_total()) + $storecredit_amount) . '</order_total>';
                    //$output .= '<order_custom_fields>' . $order->order_custom_fields . '</order_custom_fields>';
                    $output .= '<order_key><![CDATA[' . $order->get_order_key() . ']]></order_key>';

                    // $order->get_billing_first_name()
                    $output .= '<billing_first_name><![CDATA['.$order->get_billing_first_name().']]></billing_first_name>';
                    $output .= '<billing_last_name><![CDATA[' . $order->get_billing_last_name() . ']]></billing_last_name>';
                    $output .= '<billing_company><![CDATA[' . $order->get_billing_company() . ']]></billing_company>';
                    $output .= '<billing_address_1><![CDATA[' . $order->get_billing_address_1() . ']]></billing_address_1>';
                    $output .= '<billing_address_2><![CDATA[' . $order->get_billing_address_2() . ']]></billing_address_2>';
                    $output .= '<billing_city><![CDATA[' . $order->get_billing_city() . ']]></billing_city>';
                    $output .= '<billing_postcode>' . $order->get_billing_postcode() . '</billing_postcode>';
                    $output .= '<billing_country>' . $order->get_billing_country() . '</billing_country>';
                    $output .= '<billing_state><![CDATA[' . $order->get_billing_state() . ']]></billing_state>';
                    $output .= '<billing_statename><![CDATA['
                            . $this->getStateName($order->get_billing_country(), $order->get_billing_state()) . ']]></billing_statename>';

                    $output .= '<billing_email><![CDATA[' . $order->get_billing_email() . ']]></billing_email>';
                    $output .= '<billing_phone><![CDATA[' . $order->get_billing_phone() . ']]></billing_phone>';

                    $output .= '<shipping_first_name><![CDATA[' . $order->get_shipping_first_name() . ']]></shipping_first_name>';
                    $output .= '<shipping_last_name><![CDATA[' . $order->get_shipping_last_name() . ']]></shipping_last_name>';
                    $output .= '<shipping_company><![CDATA[' . $order->get_shipping_company() . ']]></shipping_company>';
                    $output .= '<shipping_address_1><![CDATA[' . $order->get_shipping_address_1() . ']]></shipping_address_1>';
                    $output .= '<shipping_address_2><![CDATA[' . $order->get_shipping_address_2() . ']]></shipping_address_2>';
                    $output .= '<shipping_city><![CDATA[' . $order->get_shipping_city() . ']]></shipping_city>';
                    $output .= '<shipping_postcode>' . $order->get_shipping_postcode() . '</shipping_postcode>';
                    $output .= '<shipping_country>' . $order->get_shipping_country() . '</shipping_country>';
                    $output .= '<shipping_state><![CDATA[' . $order->get_shipping_state() . ']]></shipping_state>';
                    $output .= '<shipping_statename><![CDATA['
                            . $this->getStateName(
                                $order->get_shipping_country(),
                                $order->get_shipping_state()
                            ) . ']]></shipping_statename>';

                    $shipping_method_title = $order->get_shipping_method();
                    if ($shipping_method_title === "") :
                    //$shipping_method_title=$order->shipping_method_title;
                    endif;

                    $output .= '<shipping_method><![CDATA[' . $shipping_method_title . ']]></shipping_method>';
                    $output .= '<shipping_method_title><![CDATA[' . $shipping_method_title . ']]></shipping_method_title>';
                    $output .= '<payment_method><![CDATA[' . $order->get_payment_method() . ']]></payment_method>';
                    $output .= '<payment_method_title><![CDATA[' . $order->get_payment_method_title() . ']]></payment_method_title>';
                    $output .= '<payment_transaction_id><![CDATA[' . $order->get_transaction_id() . ']]></payment_transaction_id>';

                    //$output .= '<taxes>' . $order->taxes . '</taxes>';
                    $output .= '<customer_user>' . $order->get_customer_id() . '</customer_user>';
                    $output .= '<user_id>' . $order->get_user_id() . '</user_id>';
                    $output .= '<completed_date>' . $order->get_date_completed() . '</completed_date>';
                    //$output .= '<billing_address>' . $order->billing_address . '</billing_address>';
                    //$output .= '<formatted_billing_address>' . $order->formatted_billing_address . '</formatted_billing_address>';
                    //$output .= '<shipping_address>' . $order->shipping_address . '</shipping_address>';
                    //$output .= '<formatted_shipping_address>' . $order->formatted_shipping_address . '</formatted_shipping_address>';
                    //$output .= '<post_status>' . $order->post_status . '</post_status>';
                    $output .= '<post_status></post_status>';

                    $output .= '<prices_include_tax>' . $order->get_prices_include_tax() . '</prices_include_tax>';


                    $output .= '<tax_display_cart>' . $tax_display_cart . '</tax_display_cart>';
                    $output .= '<display_totals_ex_tax>' . ($tax_display_cart == 'excl') . '</display_totals_ex_tax>';
                    $output .= '<display_cart_ex_tax>' . ($tax_display_cart == 'excl') . '</display_cart_ex_tax>';
                }

                

                $output .= '<order_meta>';
                $order_meta = $order->get_meta_data();
                if (count($order_meta)) {
                    foreach ($order_meta as $meta) {
                        if ($verbose==1) :
                            echo("\n\r<br>META key: " . $meta->key . "\n\r<br>");
                            if (is_array($meta->value)) :
                                echo("\n\r<br>META value: \n\r<br>");
                                var_dump($meta->value);
                            else :
                                echo("\n\r<br>META value: " . $meta->value . "\n\r<br>");
                            endif;
                        endif;
                        
                        $meta_value = $meta->value;
                        
                        if (is_scalar($meta_value)) :
                            $output .= '<meta>';
                            $output .= '<key>' . $meta->key . '</key>';
                            $output .= '<value><![CDATA[' . preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $meta_value) . ']]></value>';
                            $output .= '</meta>';
                        else :
                            if ($meta->key=="_eh_authorize_net_charge_response") :
                                $output .= '<meta>';
                                $output .= '<key>' . $meta->key . '</key>';
                                $output .= '<value>';
                                
                                $authResponse = new scoc_authorizeNetAIM_Response($meta_value);
                                if ($authResponse !== null) :
                                    $output .= '<error>' . ($authResponse->error==true? "1" : "0") . '</error>';
                                    $output .= '<error_message><![CDATA[' . $authResponse->error_message . ']]></error_message>';
                                    $output .= '<response_code>' . $authResponse->response_code . '</response_code>';
                                    $output .= '<response_code_text>' . $authResponse->response_code_text . '</response_code_text>';
                                    
                                    $output .= '<response_subcode>' . $authResponse->response_subcode . '</response_subcode>';
                                    $output .= '<response_reason_code>' . $authResponse->response_reason_code . '</response_reason_code>';
                                    $output .= '<response_reason_text><![CDATA[' . $authResponse->response_reason_text . ']]></response_reason_text>';
                                    $output .= '<authorization_code>' . $authResponse->authorization_code . '</authorization_code>';
                                    $output .= '<avs_response>' . $authResponse->avs_response . '</avs_response>';
                                    $output .= '<transaction_id>' . $authResponse->transaction_id . '</transaction_id>';
                                    $output .= '<invoice_number>' . $authResponse->invoice_number . '</invoice_number>';
                                    $output .= '<description><![CDATA[' . $authResponse->description . ']]></description>';
                                    $output .= '<amount>' . $authResponse->amount . '</amount>';
                                    $output .= '<method>' . $authResponse->method . '</method>';
                                    $output .= '<transaction_type>' . $authResponse->transaction_type . '</transaction_type>';
                                    $output .= '<card_code_response>' . $authResponse->card_code_response . '</card_code_response>';
                                    $output .= '<cavv_response>' . $authResponse->cavv_response . '</cavv_response>';
                                    $output .= '<card_type>' . $authResponse->card_type . '</card_type>';
                                endif;
                                
                                $output .= '</value>';
                                $output .= '</meta>';
                            elseif ($meta->key=="_eh_authorize_net_charge_request") :
                                $authRequest = new scoc_CreditCardInfo($meta_value);
                                
                                $output .= '<meta>';
                                $output .= '<key>' . $meta->key . '</key>';
                                $output .= '<value>';
                                
                                if ($authRequest != null) :
                                    $output .= '<x_card_num>' . $authRequest->x_card_num . '</x_card_num>';
                                    $output .= '<x_exp_month>' . $authRequest->x_exp_month . '</x_exp_month>';
                                    $output .= '<x_exp_year>' . $authRequest->x_exp_year . '</x_exp_year>';
                                    $output .= '<x_card_code>' . $authRequest->x_card_code . '</x_card_code>';
                                endif;
                                                            
                                $output .= '</value>';
                                $output .= '</meta>';
                            endif;
                        endif;
                    }
                }

                $output .= '</order_meta>';


                $output .= '<customer_notes>';
                $orderNotes = $order->get_customer_order_notes();
                if ($orderNotes && count($orderNotes) > 0) :
                    foreach ($orderNotes as $orderNote) :
                        //var_dump($orderNote->comment_content);
                        $output .= '<customer_note><![CDATA[' . $orderNote->comment_content . ']]></customer_note>';
                    endforeach;
                endif;

                // customer_message is no longer available.......
                if ($order->get_customer_note() !== null && $order->get_customer_note() !== "") :
                    //$output .= '<customer_note><![CDATA[' . $order->gcustomer_message . ']]></customer_note>';
                    $output .= '<customer_note><![CDATA[' . $order->get_customer_note() . ']]></customer_note>';
                endif;
                $output .= '</customer_notes>';

                $output .= $this->getLineItemXml2($order, $verbose);
                //$order->get_line_tax($item);
                
                
                ## Refunds
                $output .= '<refunds>';
                $refunds = $order->get_refunds();
                if ($refunds) :
                    //var_dump($refunds);
                    foreach ($refunds as $refund) :
                        //var_dump($refund);
                        $date = new DateTime($refund->get_date_created());
                        $date->setTimezone(new DateTimeZone('America/New_York'));

                        $output .= '<refund>';
                        $output .= '<id>' . $refund->get_id() . '</id>';
                        $output .= '<date>' . $date->format("Y-m-d H:i:s") . '</date>';
                        $output .= '<amount>' . $refund->get_amount() . '</amount>';
                        $output .= '<reason><![CDATA[' . $refund->get_reason() . ']]></reason>';
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
                            $output .= '<number><![CDATA[' . $item['tracking_number'] . ']]></number>';
                            $output .= '</tracking>';
                        endforeach;
                    endif;
                endif;

                $output .= '</trackings>';
                $output .= '</order>';
                
                //echo trim(scoc_utility::cleanString($output));
                echo $output;
            endforeach;
        }


        echo '</orders>';

        $output = ob_get_contents();
        ob_end_clean();
        echo ltrim($output);

        $this->scoc_lib->restoreCurrentBlog();
    }



    private function getLineItemXml2(WC_Order $order, $verbose)
    {
        $output= '';
        $items = $order->get_items();
        
        if ($items) {
            $output .= '<orderitems>';
            //foreach ($items as $item_id => $orderItem) {
            foreach ($items as $item_id => $orderItem) {
                //$orderItem = $item->get_data();
                
                // Get an instance of corresponding the WC_Product object
                // Get an instance of the WC_Product object (can be a product variation  too)
                //$product = $orderItem->wc_get_product();
                
                if (method_exists($orderItem, "wc_get_product")) :
                    $product = $orderItem->wc_get_product();
                else :
                    $product = $orderItem->get_product();
                endif;

                                
                // get order item meta data (in an unprotected array)
                $item_meta_data = $orderItem->get_meta_data();

                // get only additional meta data (formatted in an unprotected array)
                $formatted_meta_data = $orderItem->get_formatted_meta_data();
                
                
                if ($verbose==1) :
                    //var_dump($orderItem);
                    echo("\n\r<br>item_meta_data ----------------\n\r<br>");
                    var_dump($item_meta_data);
                    //var_dump($formatted_meta_data);
                endif;
                
                //wc_get_order_item_meta($item_id, $output);
                $output .= '<orderitem>';
                $output .= '<id>' . $orderItem->get_id() . '</id>';
                
                if ($product == null || $product == false || empty($product)) :
                    $output .= '<name></name>';
                else :
                    $output .= '<name><![CDATA[' . $product->get_name() . ']]></name>';
                endif;
                
                $output .= '<type><![CDATA[' . $orderItem->get_type() . ']]></type>';
                //$output .= '<item_meta>' . $orderItem['item_meta'] . '</item_meta>';
                $output .= '<qty>' . $orderItem->get_quantity() . '</qty>';
                $output .= '<tax_class>' . $orderItem->get_tax_class() . '</tax_class>';
                

                $order_item_sku = trim(get_post_meta($orderItem->get_product_id(), '_sku', true));
                //$variation_id = !empty($orderItem['variation_id']) ? $orderItem['variation_id'] : 0;
                $variation_id = !empty($orderItem->get_variation_id()) ? $orderItem->get_variation_id() : 0;

                $variation_item_sku = '';
                if ($variation_id > 0) :
                    $variation_item_sku = trim(get_post_meta($variation_id, '_sku', true));
                endif;
                
                // 3.3.1.0
                if ($order_item_sku=== "") :
                    $order_item_sku = $orderItem->get_meta("ItemSKU", true);
                endif;
                
                $output .= '<product_id>' . $orderItem->get_product_id() . '</product_id>';
                $output .= '<product_sku><![CDATA[' . $order_item_sku . ']]></product_sku>'; //7.16.3.0
                $output .= '<variation_id>' . $variation_id . '</variation_id>';
                $output .= '<variation_sku><![CDATA[' . $variation_item_sku . ']]></variation_sku>';

                $output .= '<line_subtotal>' . $order->get_line_subtotal($orderItem, false, false) . '</line_subtotal>';
                $output .= '<line_total>' . $order->get_line_total($orderItem) . '</line_total>';
                $output .= '<line_tax>' . $order->get_line_tax($orderItem) . '</line_tax>';
                $output .= '<line_subtotal_tax caption="line subtotal incl tax">'
                        . $order->get_line_subtotal($orderItem, true, true) . '</line_subtotal_tax>';
                
                // 7.8.1.0 cheking if product object exists
                if (!($product == null || $product == false || empty($product))) :
                    // 5.0.2.0      Only for product variation
                    if ($product->is_type('variation')) :
                        // Get the variation attributes
                        $variation_attributes = $product->get_variation_attributes();
                        
                        // Loop through each selected attributes
                        foreach ($variation_attributes as $attribute_taxonomy => $term_slug) :
                            $taxonomy_name = str_replace('attribute_', '', $attribute_taxonomy);
                            
                            // 7.18.0.0
                            $taxonomy = get_taxonomy($taxonomy_name);

                            if (!is_null($taxonomy) && $taxonomy != false):
                                // The name of the attribute
                                $attribute_name = $taxonomy->labels->singular_name;
                            
                                // The term name (or value) for this attribute
                                $attribute_value = get_term_by('slug', $term_slug, $taxonomy_name)->name;

                                $output .= '<variation_attribute>';
                                $output .= '<name><![CDATA[' . $attribute_name . ']]></name>';
                                $output .= '<value><![CDATA[' . $attribute_value . ']]></value>';
                                $output .= '</variation_attribute>';
                            endif;

                        endforeach;
                    endif;
                endif;

                $output .= '</orderitem>';
            }

            $output .= '</orderitems>';
        }
        
        return $output;
    }
}

$integration_order_export = new scoc_OrderExport();
$integration_order_export->getOrders();
unset($integration_order_export);

exit;
