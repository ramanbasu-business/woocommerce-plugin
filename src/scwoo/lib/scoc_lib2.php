<?php

final class scoc_lib
{
    private $log;
    protected $xmllog;

    public function __construct()
    {
    }


    public function switchToBlog()
    {
        if (is_multisite()) :
            $new_blog_id = 0;
            if (isset($_GET["blog_id"]) &&  is_numeric($_GET["blog_id"])) :
                $new_blog_id = $_GET["blog_id"];
                
                //echo "Current blog id = " . get_current_blog_id() . " New = " . $new_blog_id;

                if (get_current_blog_id() != $new_blog_id) :
                    switch_to_blog($new_blog_id);
                    //echo "switched to new blog id " . $new_blog_id;
                else :
                    //echo "Not switched to new blog id " . $new_blog_id;
                endif;
            endif;
        endif;
    }


    public function restoreCurrentBlog()
    {
        // If there was a previous request with blog_id
        // we should reset it.
        if (is_multisite()) :
            if (isset($_GET["blog_id"]) &&  is_numeric($_GET["blog_id"])) :
                restore_current_blog();
            endif;
        endif;
    }
    
    public function getPluginVersion()
    {
        return '7.19.1.0';
    }

    public function getVersionXml()
    {
        global $wp_version, $woocommerce;

        $blog_id = get_current_blog_id(); // since WP 3.1.0

        return "<channel>"
                . "<plugin_version>" . $this->getPluginVersion() . "</plugin_version>"
                . "<blog_id>" . $blog_id . '</blog_id>'
                . "<is_multisite>" . (is_multisite() == true? "1" : "0") . '</is_multisite>'
                . "<wp_version>" . $wp_version . '</wp_version>'
                . '<wc_version>' . $woocommerce->version . '</wc_version>'
                . "<php_version>" . PHP_VERSION . "</php_version>"
                . "</channel>";
    }

    public function getVersionArray()
    {
        global $wp_version, $woocommerce;

        $blog_id = get_current_blog_id(); // since WP 3.1.0

        return array(
            'plugin_version' => $this->getPluginVersion(),
            'blog_id' => $blog_id,
            'is_multisite' => (is_multisite() == true? "1" : "0"),
            'wp_version' => $wp_version,
            'wc_version' => $woocommerce->version,
            'php_version' => PHP_VERSION,
        );
    }

    public function getOrderNumber($orderId)
    {
        $order_number_formatted = $orderId;
        if (is_numeric($orderId)) :
            $orderId = absint($orderId);
            $order_number_formatted = get_post_meta($orderId, '_order_number_formatted', true);
        endif;

        return $order_number_formatted;
    }

    // V5 ... done
    public function getOrderIDFromOrderNumber($order_number)
    {
        //var_dump($orderNumber);
        if (!class_exists("WC_Seq_Order_Number_Pro")) :
            //return $order_number;
            return 0;
        endif;

        try {
            // search for the order by custom order number
            $query_args = array(
                'numberposts' => 1,
                'meta_key' => '_order_number_formatted',
                'meta_value' => $order_number,
                'post_type' => 'shop_order',
                'post_status' => 'any',
                'return' => 'ids',
            );

            //$posts = wc_get_orders($query_args);
            $query = new WC_Order_Query($query_args);
            $posts = $query->get_orders();

            $order_id = !empty($posts) ? $posts[0] : null;

            // order was found
            if (null !== $order_id) {
                return $order_id;
            }

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

    /** Dispatch a stored XML job by module and update its processing status. */
    public function importProducts($jobId, $verbose, $isDeleteTransient = 0)
    {
        $job_row = $this->getJob($jobId);
        if ($job_row == null) {
            //$this->log->write('SCOC :  ' . "job# " . $jobId . " not found");
            return "job# " . $jobId . " not found";
        }

        $local_file_source = (string) $job_row->local_file_source;

        try {
            $feed_xml = simplexml_load_string($local_file_source);
            /* simplexml_load_string */
            if ($feed_xml == null) {
                $this->updateJobErrored($jobId);
                if ($verbose == 1) {
                    echo "job# " . $jobId . ", can not load local file source. Job status:error, rejected";
                }
                return "job# " . $jobId . ", can not load local file source. Job status:error, rejected";
            }

            $module = '';
            if (isset($feed_xml['module'])) {
                $module = (string) $feed_xml['module'];
            }
            $this->updateJobSubmitted($jobId);

            //$o = new scoc_importer($this, $this->log, $logfilename, $verbose);
            $o = new scoc_importer($jobId, $verbose, $isDeleteTransient);
            //var_dump($o);

            switch ($module) :
                case "":
                    $o->startImport($feed_xml);
                    break;
                case "invprice":
                    $o->importMini($feed_xml);
                    break;
                case "shipping":
                    $o->importShipping($feed_xml);
                    break;

                case "order_cancel":
                    $o->importCancelOrders($feed_xml);
                    break;

                // incomplete
                case "order_refund":
                    $o->importRefundOrders($feed_xml);
                    break;
                case "images":
                    $o->importImages($feed_xml);
                    break;
                case "descriptions":
                    $output = $o->importDescriptions($feed_xml);
                    break;
                case "titles":
                    $output = $o->importTitles($feed_xml);
                    break;
                case "categories":
                    $output = $o->importCategories($feed_xml);
                    break;
                    
                case "metas":
                    $output = $o->importMetas($feed_xml);
                    break;
            endswitch;


            $o = null;

            $this->updateJobCompleted($jobId);
            if ($verbose == 1) {
                echo "job# " . $jobId . " updated as completed";
            }
            return ""; // executed successfully
        } catch (Exception $ex) {
            $this->updateJobErrored($jobId);
            if ($verbose == 1) {
                echo "job# " . $jobId . ", Job status:error " . $ex->getMessage();
            }
            return $ex->getMessage();
        }
    }

    
    public function getOrders(
        $delimOrderStatus,
        $currentPageIndex = 0,
        $rowsPerPage = 20,
        $fromDate = null,
        $toDate = null,
        $order_id = null,
        $verbose = 0
    ) {
        global $start_date, $end_date, $woocommerce, $wpdb, $wp_locale;
        global $wp_version;

        $statusArray = [];
        
        $start = $currentPageIndex * $rowsPerPage;
        $limit = $rowsPerPage;
                
            
        $query_args = array(
            'return' => 'ids',
            'type'  => 'shop_order',
            'orderby' => 'date',
            'order' => 'DESC',
            'paginate' => true,
            'limit' => $rowsPerPage,
            'page'  => $currentPageIndex
        );

        if (!empty($delimOrderStatus)) :
            //$delimOrderStatus = "'" . str_replace(",", "','", $delimOrderStatus) . "'";
            $statusArray = explode(",", $delimOrderStatus);
            $query_args['status'] = $statusArray;
        endif;

        
        if (!empty($fromDate) && !empty($toDate)) :
            $query_args['date_created'] = $fromDate . '...' . $toDate;
        endif;

        if ($order_id !== null) :
            //return array($order_id);
            $tempOrder = wc_get_order($order_id);
            if ($tempOrder && $tempOrder != null) :
                $query_args['order_key'] = $tempOrder->get_order_key();
            else :
                $query_args['order_key'] = 'XXXXXXXX';
            endif;
            unset($tempOrder);
        endif;


        if ($verbose ==1) {
            var_dump($query_args);
        }
        $query = new WC_Order_Query($query_args);
        $orders = $query->get_orders();

        if ($verbose ==1) {
            var_dump($orders);
        }
        return $orders;
    }

    

    // done
    public function getProducts(
        $currentPageIndex = null,
        $rowsPerPage = null,
        $product_id = null
    ) {
        //global $HTTP_GET_VARS;
        //var_dump($HTTP_GET_VARS);
        //$HTTP_GET_VARS['currentpageindex']=$currentPageIndex;
        if ($currentPageIndex == null) {
            $currentPageIndex = 1;
        }
        if ($rowsPerPage == null) {
            $rowsPerPage = 10;
        }
        //$data["filter_name"]="A";

        $products_new_split = array();

        $products_new_query_raw = "select p.*, pd.products_name, m.manufacturers_name from " . TABLE_PRODUCTS . " p 
	    left join " . TABLE_MANUFACTURERS . " m on (p.manufacturers_id = m.manufacturers_id), " . TABLE_PRODUCTS_DESCRIPTION . " pd 
	    where p.products_status = '1' and p.products_id = pd.products_id 
	    and pd.language_id = '1'";
        if ($product_id != null) {
            $products_new_query_raw.=" AND p.products_id='" . $product_id . "'";
        }

        $products_new_query_raw.=" order by p.products_id, pd.products_name";

        $products_new_split = new PageResults(
            $products_new_query_raw,
            $rowsPerPage
        );
        return $products_new_split;
    }


    // 5.0.0.0
    public function addProduct($sku, $product_type, $name, $desc, $short_desc)
    {
        global $woocommerce;

        // Get an empty instance of the product object (defining it's type)
        $product = $this->wc_get_product_object_type($product_type);
        if (!$product) :
            return false;
        endif;

        $product->set_sku($sku);
        $product->set_name($name);
        $product->set_description($desc);
        $product->set_short_description($short_desc);

        ## --- SAVE PRODUCT --- ##
        $product->save();
        $product_id = $product->get_id();
        return $product_id;
    }



    /// 5.0.0.0
    // Utility function that returns the correct product object instance
    private function wc_get_product_object_type($type)
    {
        // Get an instance of the WC_Product object (depending on his type)
        if ($type === 'variable') {
            $product = new WC_Product_Variable();
        } elseif ($type === 'grouped') {
            $product = new WC_Product_Grouped();
        } elseif ($type === 'external') {
            $product = new WC_Product_External();
        } else {
            $product = new WC_Product_Simple(); // "simple" By default
        }

        if (! is_a($product, 'WC_Product')) {
            return false;
        } else {
            return $product;
        }
    }

    public function getProductType($product_id)
    {
        $taxonomy = wp_get_object_terms($product_id, 'product_type');
        if ($taxonomy && !empty($taxonomy[0])) {
            return $taxonomy[0]->name;
        } else {
            return '';
        }
    }

    public function getBrandIdByName($brandName, $verbose = 0)
    {
        $brandId = null;
        $brands = [];
        
        // product's brand
        //if(class_exists("Perfect_Woocommerce_Brands")):
        if ($verbose == 1) :
            //echo "PWB_PLUGIN is defined: " . defined("PWB_PLUGIN");
        endif;

        if (!defined("PWB_PLUGIN_NAME")) :
            $brands = get_terms('product_brand', array('hide_empty' => 0));

            if ($verbose == 1) :
                var_dump($brands);
            endif;

            if ($brands && is_array($brands) && count($brands)>0) :
                foreach ($brands as $key => $brandObject) :
                    if ($brandObject->name === $brandName) :
                        return (int) $brandObject->term_id;
                    endif;
                endforeach;
            endif;

            return null;
        else :
            $brands = get_terms('pwb-brand', array('hide_empty' => 0));

            if ($verbose == 1) :
                var_dump($brands);
            endif;

            foreach ($brands as $brandObject) {
                if ($brandObject->name == $brandName) :
                    $brandId = (int) $brandObject->term_id;
                    break;
                endif;
            }
            return $brandId;
        endif;
    }

    public function setBrand($product_id, $brand_id)
    {
        global $wpdb;
        //echo $product_id . ' ' . $brand_id;
        //if(class_exists("Perfect_Woocommerce_Brands")):
        if (defined("PWB_PLUGIN_NAME")) :
            wp_set_object_terms($product_id, array($brand_id), 'pwb-brand', false);
        else :
            wp_set_object_terms($product_id, array($brand_id), 'product_brand', false);
        endif;
    }

    public function getProductIdBySku($sku)
    {
        /*
        global $wpdb;
        $query = $wpdb->get_col('SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key="_sku" AND meta_value ="' . $sku . '";');
        if ($query == null) {
            return 0;
        } else {
            return (int) $query[0];
        }
        */
        return wc_get_product_id_by_sku($sku);
    }

    public function getRootCategoryId()
    {
        $rootcat = false;
        $args = array('parent' => 0, 'taxonomy' => 'product_cat');
        $root_categories = get_categories(($args));
        
        if (count($root_categories) == 1) {
            $rootcat = $root_categories[0]->term_id;
        } else {
            return null;
        }
        
        unset($root_categories);
        return $rootcat;
    }

    public function getRootCategoryIdOf($catId)
    {
        // gets an array of all parent category levels
        $product_parent_categories_all_hierachy = get_ancestors($catId, 'product_cat');

        if ($product_parent_categories_all_hierachy == null) :
            return null;
        endif;
        
        // This cuts the array and extracts the last set in the array
        $last_parent_cat = array_slice($product_parent_categories_all_hierachy, -1, 1, true);
        foreach ($last_parent_cat as $last_parent_cat_value) {
            // $last_parent_cat_value is the id of the most top level category, can be use whichever one like
            return $last_parent_cat_value;
        }
    }
    
    public function getPostIDFromWooOrderID($woo_order_id)
    {
        global $wpdb;
        $order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_order_number' AND meta_value = %s",
                $woo_order_id
            )
        );
        return $order_id;
    }

    public function ibenic_wc_refund_order(
        $order_id, 
        $refund_reason, 
        $is_full_refund, 
        $other_amount = 0.0, 
        $my_items = array()
    )
    {
        $order_id = absint($order_id);
        $online_refund = true;

        if (empty($order_id)) {
            throw new Exception('Order ID is invalid');
        }

        $order = wc_get_order($order_id);
        // IF it's something else such as a WC_Order_Refund, we don't want that.
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        // Determine if automatic refund is supported by payment gateway
        $gateway_controller = WC_Payment_Gateways::instance();
        $all_gateways = $gateway_controller->payment_gateways();
        $payment_method = $order->get_payment_method();

        //var_dump("payment method on order: ");
        //var_dump($payment_method);
        //var_dump($all_gateways);
        $gateway = isset($all_gateways[$payment_method]) ? $all_gateways[$payment_method] : false;
        if (!$gateway->supports('refunds')) {
            $online_refund = false;
        }

        $max_refund = wc_format_decimal($order->get_total() - $order->get_total_refunded(), wc_get_price_decimals());
        $order_items = $order->get_items(array('line_item'));

        // Refund Amount
        $ultimate_amount = 0;

        // Prepare line items which we are refunding
        $line_items = array();

        if ($is_full_refund === true) :
            // process full refund .....
            $ultimate_amount = $max_refund;
            foreach ($order_items as $item_id => $item) :
                $item_meta = $order->get_item_meta($item_id);
                //$product_data = wc_get_product($item_meta["_product_id"][0]);
                //$class = get_class($item);
                //$refunded_item = new $class($item);
                //$refunded_qty = $order->get_qty_refunded_for_item( $item_id );
                //var_dump($refunded_qty);

                $actual_qty = $item->get_quantity(); //$item_meta['_qty'][0];
                $actual_refund_total = wc_format_decimal($item_meta['_line_total'][0]);
                $actual_refund_tax = wc_format_decimal($item_meta['_line_tax'][0]);

                $my_qty = $actual_qty - absint($order->get_qty_refunded_for_item($item_id));
                if ($my_qty <= 0) {
                    $my_qty = 0;
                }

                $my_refund_total = $actual_refund_total - abs($order->get_total_refunded_for_item($item_id));
                $my_refund_tax = 0;

                //$already_refunded_tax=0;
                $array_tax = array();
                if (($tax_data = $item->get_taxes()) && wc_tax_enabled()) {
                    /* foreach ($order->get_taxes() as $tax_item) {
                      $tax_item_id = $tax_item->get_rate_id();
                      //$already_refunded_tax += $order->get_tax_refunded_for_item( $item_id, $tax_item_id );
                      //var_dump($tax_item);
                      $array_tax[$tax_item_id] = $my_qty * ($tax_item->get_tax_total() / $actual_qty);
                      } */
                    foreach ($tax_data["total"] as $tax_rate_id => $total_paid) :
                        $array_tax[$tax_rate_id] = $my_qty * ($total_paid / $actual_qty);
                    endforeach;
                }
                ////$my_refund_tax = $actual_refund_tax - abs( $already_refunded_tax );

                $line_items[$item_id] = array(
                    'qty' => $my_qty,
                    'refund_total' => $my_refund_total,
                    'refund_tax' => $array_tax
                    );
            endforeach;

            // RETURN ALL PENDING SHIPPING
            foreach ($order->get_items(array('shipping')) as $item_id => $item) {
                $actual_refund_total = floatval($item->get_total());
                $actual_refund_tax = $item->get_total_tax();

                $my_refund_total = $actual_refund_total - abs($order->get_total_refunded_for_item($item_id, 'shipping'));
                //$my_refund_tax = $actual_refund_tax - abs( $already_refunded_tax );
                //var_dump($order->get_total_refunded_for_item($item_id, 'shipping'));
                //var_dump($actual_refund_total);

                $array_tax = array();
                if (($tax_data = $item->get_taxes()) && wc_tax_enabled()) {
                    foreach ($order->get_taxes() as $tax_item) {
                        $tax_item_id = $tax_item->get_rate_id();
                        $already_refunded_tax = $order->get_tax_refunded_for_item($item_id, $tax_item_id, 'shipping');
                        //var_dump($tax_item);
                        $array_tax[$tax_item_id] = ($tax_item->get_shipping_tax_total() - $already_refunded_tax);
                    }
                }

                $line_items[$item_id] = array(
                    'qty' => 0,
                    'refund_total' => $my_refund_total,
                    'refund_tax' => $array_tax
                    );
            }
        else :
            // process partial refund...
            $ultimate_amount = $other_amount;

            // add item-wise amount below
            foreach ($order_items as $item_id => $item) :
                if ($item->get_variation_id() !== null && $item->get_variation_id() > 0) :
                    $product_id = $item->get_variation_id();
                    //var_dump("variation_id=" . $product_id);
                else :
                        $product_id = $item->get_product_id();
                endif;

                if (array_key_exists($product_id, $my_items)) :
                        $item_meta = $order->get_item_meta($item_id);
                    $product_data = wc_get_product($item_meta["_product_id"][0]);

                    //var_dump($item);
                        $actual_qty = $item->get_quantity(); //$item_meta['_qty'][0];
                        $actual_refund_total = wc_format_decimal($item_meta['_line_total'][0]);
                    $actual_refund_tax = wc_format_decimal($item_meta['_line_tax'][0]);

                    $my_qty = $my_items[$product_id];
                    $my_refund_total = ($actual_refund_total / $actual_qty) * $my_qty;
                    $my_refund_tax = ($actual_refund_tax / $actual_qty) * $my_qty;

                    $ultimate_amount += $my_refund_total + $my_refund_tax;

                    // per tate-id, build array of tax refund amount
                    $array_tax = array();
                    if (($tax_data = $item->get_taxes()) && wc_tax_enabled()) {
                        //var_dump($tax_data);
                        /* foreach ($order->get_taxes() as $tax_item) {
                          //var_dump($item);
                          $tax_item_id = $tax_item->get_rate_id();
                          $already_refunded_tax = $order->get_tax_refunded_for_item($item_id, $tax_item_id, 'line_item');
                          $array_tax[$tax_item_id] = $my_qty * ($tax_item->get_tax_total() / $actual_qty);
                          } */
                        foreach ($tax_data["total"] as $tax_rate_id => $total_paid) :
                                $array_tax[$tax_rate_id] = $my_qty * ($total_paid / $actual_qty);
                        endforeach;
                    }


                    $line_items[$item_id] = array(
                    'qty' => $my_qty,
                    'refund_total' => $my_refund_total,
                    'refund_tax' => $array_tax
                        );
                else :
                            // optional: items which are not my items
                        $line_items[$item_id] = array(
                        'refund_total' => 0,
                        'refund_tax' => array(1 => "0.0")
                                );
                endif;
            endforeach;
        endif;

        //$remaining_refund_amount = $order->get_remaining_refund_amount();
        //var_dump($refund_amount . "==");

        if (!$ultimate_amount || $max_refund < $ultimate_amount || 0 >= $ultimate_amount) {
            throw new exception(__('Invalid refund amount of ' . $ultimate_amount . ' while max refundable is ' . $max_refund, 'woocommerce'));
        }

        /* $line_items[154] = array(
          'refund_total' => 0 ,
          'refund_tax' => array(0)
          );
         */
            $refundRequest = array(
            'amount' => $ultimate_amount,
            'reason' => $refund_reason,
            'order_id' => $order_id,
            'line_items' => $line_items,
            'refund_payment' => $online_refund,
            'restock_items' => true
            );

            //var_dump($refundRequest);
            $refund = wc_create_refund($refundRequest);
            //var_dump($refund->get_amount());
            //var_dump($refund);

            if (is_wp_error($refund)) {
                throw new Exception("Exception from ibenic_wc_refund_order = " . $refund->get_error_message());
            }
            return $refund;
    }

    //----------------------- HELPER METHODS -------------------------------//
    //=====================================================================//
    // done
    public function updateJobStatus($id, $status)
    {
        global $wpdb;
        $query = "UPDATE job SET status=" . $status . " WHERE id =" . $id;
        try {
            $wpdb->query($query);
            return true;
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }

    // done
    public function updateJobSubmitted($id)
    {
        global $wpdb;
        $query = "UPDATE job SET status=1 WHERE id =" . $id;
        try {
            $wpdb->query($query);
            return true;
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }

    // done
    public function updateJobCompleted($id)
    {
        global $wpdb;
        $today = date("Y-m-d H:i:s", strtotime(date("d.m.Y H:i:s")));
        $query = "UPDATE job SET processedon='" . $today . "', status=2 WHERE id = '$id'";
        try {
            $wpdb->query($query);
            return true;
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }

    // done
    public function updateJobErrored($id)
    {
        global $wpdb;
        $query = "UPDATE job SET processedon=NOW(), status=-1 WHERE id = '$id'";
        try {
            $wpdb->query($query);
            return true;
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }

    /** Delete job rows submitted more than six days ago. */
    public function CleanData()
    {
        global $wpdb;
        try {
            ////return $wpdb->delete( "job", "DATEDIFF(NOW(), submittedon) >7" );
            $wpdb->query("delete FROM `job` WHERE DATEDIFF(NOW(), submittedon) >6");
            return 1;
        } catch (Exception $e) {
            echo $e->getMessage();
            return 0;
        }
    }

    public function CleanData_BySession()
    {
        global $wpdb;
        try {
            if (isset($_SESSION)) :
                if (isset($_SESSION["LAST_DELETED"])  && (time() - $_SESSION['LAST_DELETED'] > 1)) :
                    // last request was more than 30 minutes ago
                    $wpdb->query("delete FROM `job` WHERE DATEDIFF(NOW(), submittedon) >7");

                    session_unset();     // unset $_SESSION variable for the run-time
                    session_destroy();   // destroy session data in storage
                    echo "Jobs deleted. Session destroyed";
                    return 1;
                else :
                    echo "Session started, but criteria for deleting jobs not met";
                endif;
            else :
                echo "Session not started";
            endif;

            $_SESSION['LAST_DELETED'] = time(); // update last activity time stamp

            ////return $wpdb->delete( "job", "DATEDIFF(NOW(), submittedon) >7" );
            //$wpdb->query("delete FROM `job` WHERE DATEDIFF(NOW(), submittedon) >7");
            return 1;
        } catch (Exception $e) {
            echo $e->getMessage();
            return 0;
        }
    }

    public function createJobTableIfNotExist()
    {
        try {
            global $wpdb;
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $wpdb->show_errors();

            $q = 'CREATE TABLE IF NOT EXISTS job (
                            id                  BIGINT NOT NULL  AUTO_INCREMENT,
                            submittedon         DATETIME NOT NULL ,
                            processedon         DATETIME NULL ,
                            localfilename       VARCHAR( 100 ) NOT NULL ,
                            status              TINYINT NOT NULL ,
                            logfilename         VARCHAR( 100 ) NOT NULL ,
                            module              varchar(50) NULL,
                            local_file_source   LONGTEXT NULL,
                            log_file_source     LONGTEXT NULL,
                            stack_trace         LONGTEXT NULL,
                            blog_id INT NULL,
                    PRIMARY KEY (  id )
            )';
            dbDelta($q);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        try {
            $wpdb->query("alter table `job` 
                    add column `stack_trace` LONGTEXT NULL;");
        } catch (Exception $ex) {
            throw $ex;
        }

        /*try {
            $wpdb->query("alter table `job`
                    add column `log_file_source` LONGTEXT NULL");
        } catch (Exception $ex) {
            throw $ex;
        }**/

        try {
            $wpdb->query("alter table `job` add column `blog_id` INT NULL");
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    // done
    /** Persist an import payload and initial log state with the current multisite blog ID. */
    public function insertJob(
        $submittedon,
        $processedon,
        $status,
        $module,
        $local_file_source,
        $log_file_source
    ) {
        global $wpdb;

        //$query = "INSERT INTO job (id, submittedon, processedon, local_file_source, status, log_file_source, module) ";
        //$query .= " values (NULL, '$submittedon', ";
        //$query .= $processedon == NULL ? "NULL," : "'" . $processedon . "',";
        //$query .= "'$localfilename', '$status', '$logfilename', '$module')";
        

        $binds = array(
            'submittedon' => $submittedon,
            'processedon' => $processedon,
            'local_file_source' => $local_file_source,
            'status' => $status,
            'log_file_source' => $log_file_source,
            'module' => $module,
            'blog_id'   => get_current_blog_id()
        );

        try {
            $wpdb->insert("job", $binds);
            return $wpdb->insert_id;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        return -1;
    }

    // done
    public function getOneSubmittedJob($module)
    {
        global $wpdb;
        
        $blog_id = get_current_blog_id();
        $blog_id_query = "";
        
        if ($blog_id == 0 || $blog_id == 1) :
            $blog_id_query = "AND blog_id=1 OR blog_id IS NULL ";
        else :
            $blog_id_query = "AND blog_id=" . $blog_id;
        endif;

        // 6.0.0.0  multi site
        if ($module == null || $module == '') {
            $jobqry = ("SELECT * FROM job WHERE status in (0,1) " . $blog_id_query . " ORDER BY submittedon ASC LIMIT 0,1");
        } elseif ($module == '') {
            $jobqry = ("SELECT * FROM job WHERE status in (0,1) and module in('', 'invprice') " . $blog_id_query . " ORDER BY submittedon ASC LIMIT 0,1");
        } else {
            $jobqry = ("SELECT * FROM job WHERE status in (0,1) and module='" . $module . "' " . $blog_id_query . " ORDER BY submittedon ASC LIMIT 0,1");
        }
        
        $job = $wpdb->get_row($jobqry);
        return $job;
    }

    // done
    public function getJob($id)
    {
        global $wpdb;
        $jobqry = ("SELECT * FROM job WHERE id=" . $id . "");
        $job = $wpdb->get_row($jobqry);
        return $job;
    }

    // done
    public function getJobs($count)
    {
        global $wpdb;
        $jobqry = ("SELECT * FROM  job order by id desc LIMIT 0," . $count . "");

        $jobs = $wpdb->get_results($jobqry);
        return $jobs;
    }

    public function getJobCount()
    {
        global $wpdb;
        $jobqry = ("SELECT COUNT(*) FROM job");
        $count = $wpdb->get_var($jobqry);
        return $count;
    }

    public function getJobCountForToday()
    {
        global $wpdb;

        try {
            $jobqry = ("SELECT COUNT(*) FROM job WHERE DATEDIFF(NOW(), submittedon) = 0");
            $count = $wpdb->get_var($jobqry);
            return $count;
        } catch (Exception $e) {
            echo $e->getMessage();
            return 0;
        }
    }

    // new
    public function getJobLocalFileSource($id)
    {
        global $wpdb;
        if ($id == 0) {
            return null;
        }
        $local_file_source = $wpdb->get_col("SELECT local_file_source FROM job WHERE id=" . $id);
        return $local_file_source;
    }

    // new
    public function getJobLogFileSource($id)
    {
        global $wpdb;
        if ($id == 0) {
            return null;
        }
        $log_file_source = $wpdb->get_col("SELECT log_file_source FROM job WHERE id=" . $id);
        return $log_file_source[0];
    }

    public function updateJobLogFileSource($id, $log_file_source)
    {
        global $wpdb;
        try {
            $wpdb->update(
                "job",
                array('log_file_source' => $log_file_source),
                array('id' => $id)
            );
            return true;
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }

    public function getJobStackTrace($id)
    {
        global $wpdb;
        if ($id == 0) {
            return null;
        }

        $stack_trace = $wpdb->get_col("SELECT stack_trace FROM job WHERE id=" . $id);
        return $stack_trace[0];
    }

    public function updateJobStackTraceConcatenated($id, $stack_trace)
    {
        global $wpdb;
        try {
            $existingStackTrace = $this->getJobStackTrace($id);

            $wpdb->update(
                "job",
                array('stack_trace' => $existingStackTrace . "\n\r<br />" .  $stack_trace),
                array('id' => $id)
            );
            return true;
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }

    public function printJobXmlError($err)
    {
        // 1.4.10
        $xml_output = "<jobresult>" . $this->getVersionXml() . "<id>0</id><status>error</status><submittedon/><processedon/><localfilename/><logfilename/><products/><errors><error>" . $err . "</error></errors></jobresult>";
        echo $xml_output;
    }

    public function getPluginPath()
    {
        return site_url() . '/' . SCWOO_FOLDER . '/';
    }
    
    public function printXml($jobObject)
    {
        $scmp_log_vpath = $this->getPluginPath() . "job/";
        $log_file_source = $jobObject->log_file_source;
        $xml_output = "";
        $error_output = "<errors>";

        if ($jobObject != null) {
            $xml_output .= "<jobresult>\n";
            $xml_output .= $this->getVersionXml();

            $status = "";
            if ($jobObject->status == -1) {
                $status = "error";
            } elseif ($jobObject->status == 0) {
                $status = "submitted";
            } elseif ($jobObject->status == 1) {
                $status = "processing";
            } elseif ($jobObject->status == 2) {
                $status = "completed";
            }

            $xml_output .= "\t<id>" . $jobObject->id . "</id>\n";
            $xml_output .= "\t<status>" . $status . "</status>\n";
            $xml_output .= "\t<submittedon>" . $jobObject->submittedon . "</submittedon>\n";
            $xml_output .= "\t<processedon>" . $jobObject->processedon . "</processedon>\n";
            $xml_output .= "<localfilename>" . $scmp_log_vpath . "jobsource.php?id=" . $jobObject->id . "</localfilename>\n";
            $xml_output .= "<logfilename>" . $scmp_log_vpath . "joblog.php?id=" . $jobObject->id . "</logfilename>\n";
            $xml_output .= "<stacktrace>" . $scmp_log_vpath . "jobstacktrace.php?id=" . $jobObject->id . "</stacktrace>\n";
            $xml_output .= "\t<module>" . $jobObject->module . "</module>\n";

            $dom = new DOMDocument();

            try {
                if (empty($log_file_source)) {
                    throw new Exception("LOG XML does not exist");
                }

                $dom->loadXML($log_file_source);
                if ($dom) {
                    $node = $dom->getElementsByTagName('products')->item(0);
                    if ($node) {
                        $nodeXml = $dom->saveXML($node);
                        $xml_output .="\n\t" . $nodeXml . "\n";
                    } else {
                        $xml_output .= "<products></products>";
                    }
                } else {
                    $error_output .= "<error>Log Xml could not be loaded into DOM</error>";
                }
            } catch (Exception $ex) {
                $error_output .= "<error>" . htmlentities($ex->getMessage()) . "</error>";
            }

            //$xmllog=null;

            $error_output .= "</errors>\n";
            $xml_output .= $error_output;
            $xml_output .= "</jobresult>";
        } else {
            $xml_output .= "<jobresult>" . $this->getVersionXml() . "<id>0</id><status></status><submittedon/><processedon/><localfilename/><logfilename/><products/><errors></errors></jobresult>";
        }

        echo $xml_output;
    }

    //done
    public function getLogFolderPath()
    {
        //global $xcart_dir;

        $_filePath = SCWOO_LOG;

        if (file_exists($_filePath . ".htaccess") == false) {
            $fp = fopen($_filePath . ".htaccess", "w");
            fwrite($fp, "Allow from all");
            fclose($fp);
        }
        $_filePath = str_replace("\\", "/", $_filePath);
        return $_filePath;
    }

    

    public function getJobXml2($job)
    {
        $error_output = '';
        $jobresult = new SimpleXMLElement("<jobresult></jobresult>");
        //var_dump($jobresult);
        $products = new SimpleXMLElement("<products/>");

        //var_dump($job);
        $id = 0;
        $status = '';
        $submittedon = '';
        $processedon = '';
        $localfilename = '';
        $logfilename = '';
        $module = '';

        if ($job && $job->id != null) {
            if ($job->status == -1) {
                $status = "error";
            } elseif ($job->status == 0) {
                $status = "submitted";
            } elseif ($job->status == 1) {
                $status = "processing";
            } elseif ($job->status == 2) {
                $status = "completed";
            }

            $id = $job->id;
            $submittedon = $job->submittedon;
            $processedon = $job->processedon;
            $localfilename = $job->localfilename;
            $logfilename = $job->logfilename;
            $module = $job->module;

            $logfilepath = str_replace("\\", "/", $this->getLogFolderPath()) . $job->logfilename;
        }


        try {
            //var_dump($jobarray);
            $localfilename = $this->getPluginPath() . 'log/' . $localfilename;
            $logfilename = $this->getPluginPath() . 'log/' . $logfilename;

            $jobresult->addChild("id", $id);
            $jobresult->addChild("status", $status);
            $jobresult->addChild('submittedon', $submittedon);
            $jobresult->addChild('processedon', $processedon);
            $jobresult->addChild('localfilename', $localfilename);
            $jobresult->addChild('logfilename', $logfilename);
            $jobresult->addChild('module', $module);
            //$jobresult->addChild('products', '<da/>');

            if ($status == 'submitted' || $status == "") {
            } else {
                if (file_exists($logfilepath) == false) {
                    $error_output = ("LOG XML: '$logfilepath' does not exist");
                } else {
                    $products_source = simplexml_load_string($logfilepath);
                    //var_dump($products);
                    $productsXml = $jobresult->addChild("products");
                    foreach ($products_source->product as $product_source) {
                        $productXml = $productsXml->addChild("product");
                        $_sku = (string) $product_source->sku;
                        $_status = (string) $product_source->status;

                        $productXml->addChild("sku", $_sku);
                        $productXml->addChild("status", $_status);
                        //echo $product_source->errors->count();

                        $productXml_error = $productXml->addChild("errors");
                        if (isset($product_source->errors) && $product_source->errors->children()->count() > 0) {
                            //foreach ($product_source->errors as $product_source_error) {
                            //  $productXml_error->addChild("error", (string) $product_source_error->error);
                            //}
                            // modified 06.01.2014
                            foreach ($product_source->errors->error as $product_source_error) {
                                if ((string) $product_source_error != '') {
                                    $productXml_error->addChild(
                                        "error",
                                        (string) $product_source_error
                                    );
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            $error_output = htmlentities($ex->getMessage());
        }

        $errors = $jobresult->addChild("errors");
        if ($error_output != "") {
            $errors->addChild("error", (string) $error_output);
        }

        return $jobresult;
    }

    public function getJobXml($jobarray)
    {
        $xml_output = "";
        $error_output = "<errors>";
        
        $job = $jobarray;
        if ($job == null) {
            $xml_output .= "<jobresult><id>0</id><status></status><submittedon/><processedon/><localfilename/><logfilename/><products/><errors></errors></jobresult>";
            return $xml_output;
        }

        if ($job["id"] != null) {
            $xml_output .= "<jobresult>\n";

            $status = "";
            if ($job->status == -1) {
                $status = "error";
            } elseif ($job->status == 0) {
                $status = "submitted";
            } elseif ($job->status == 1) {
                $status = "processing";
            } elseif ($job->status == 2) {
                $status = "completed";
            }

            $xml_output .= "\t<id>" . $job['id'] . "</id>\n";
            $xml_output .= "\t<status>" . $status . "</status>\n";
            $xml_output .= "\t<submittedon>" . $job['submittedon'] . "</submittedon>\n";
            $xml_output .= "\t<processedon>" . $job['processedon'] . "</processedon>\n";
            $xml_output .= "\t<localfilename>" . $job['localfilename'] . "</localfilename>\n";
            $xml_output .= "\t<logfilename>" . $job['logfilename'] . "</logfilename>\n";
            $xml_output .= "\t<module>" . $job['module'] . "</module>\n";
            //$xml_output .= "<errors>\n";
            //$xmllog = new xmllog(dirname(__FILE__) ."\" . $job['logfilename']);
            $logfilepath = str_replace("\\", "/", $this->getLogFolderPath()) . $job['logfilename'];

            $dom = new DOMDocument();

            try {
                if ($job->status == 0) {
                } else {
                    if (file_exists($logfilepath) == false) {
                        throw new Exception("LOG XML: '$logfilepath' does not exist");
                    } else {
                        $dom->load($logfilepath);
                        if ($dom) {
                            $node = $dom->getElementsByTagName('products')->item(0);
                            if ($node) {
                                $nodeXml = $dom->saveXML($node);
                                $xml_output .="\n\t" . $nodeXml . "\n";
                            } else {
                                $xml_output .= "<products></products>";
                            }
                        } else {
                            $error_output .= "<error>$logfilepath could not be loaded into DOM</error>";
                        }
                    }
                }
            } catch (Exception $ex) {
                $error_output .= "<error>" . htmlentities($ex->getMessage()) . "</error>";
            }

            //$xmllog=null;

            $error_output .= "</errors>\n";
            $xml_output .= $error_output;
            $xml_output .= "</jobresult>";
        } else {
            //echo "hjere";
            $xml_output .= "<jobresult><id>0</id><status></status><submittedon/><processedon/><localfilename/><logfilename/><products/><errors></errors></jobresult>";
        }

        return $xml_output;
    }

    public function saveImageFromUrl($imageUrl)
    {
        $info = pathinfo($imageUrl);
        $extension = isset($info['extension']) ? $info['extension'] : "jpg";
        $importDir = str_replace(
            "\\",
            "/",
            DIR_FS_CATALOG . DIR_WS_IMAGES . '/'
        );

        if ($info['basename'] != null) {
            $newFileName = $info['basename'];
        } else {
            $newFileName = uniqid(rand(), true) . "." . $extension;
        }

        $ch = curl_init($imageUrl);

        $fp = fopen($importDir . $newFileName, "w");
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

        $rawdata = curl_exec($ch);
        curl_close($ch);

        fwrite($fp, $rawdata);
        fclose($fp);
        return $newFileName;
    }

    public function updateField_AdvancedCustomFields(
        $product_id,
        $field_name,
        $value
    ) {
        global $wpdb;
        global $acf; // added 7.12.2.0
        /*
        if (!function_exists('get_field_reference')) :
            throw new Exception("get_field_reference method(wp-content/plugins/advanced-custom-fields/core/api.php) does not exist");
        endif;

        $field_key = get_field_reference($field_name, $product_id);
        if ($field_key) :
            update_field($field_key, $value, $product_id);
        endif;
        */
        $field = acf_get_field($field_name);
        //var_dump($field);
        if($field == null):
            return false;
        endif;

        // acf-form-functions.php
        // acf-value-functions.php
        //if ($field_name == "acf_related_products_with_other_conditions"):
        if ($field["type"] == "relationship"):
            $product_id_array = explode(',', $value);
            
            $data_array = array(
                $field["key"]   => $product_id_array
            );
            acf_update_values( $data_array, $product_id );
            return true;
        else:
            $updated = update_post_meta($product_id, $field_name, (string)$value);
            return $updated;
        endif;
    }

    public function updateField_YikesWooProductsTab($product_id, $tab_title, $tab_content)
    {
        $foundTheTab = false;

        $current_products_tabs = get_post_meta($product_id, 'yikes_woo_products_tabs', true);
        //var_dump($current_products_tabs);

        if (!empty($current_products_tabs) && is_array($current_products_tabs)) :
            for ($i = 0; $i < count($current_products_tabs); $i++) :
                if ($current_products_tabs[$i]["title"] === $tab_title) :
                    $foundTheTab = true;
                    $current_products_tabs[$i]["content"] = $tab_content;
                    break;
                endif;
            endfor;
        endif;

        if ($foundTheTab === true) :
            //var_dump($current_products_tabs);
            update_post_meta($product_id, 'yikes_woo_products_tabs', $current_products_tabs);
        endif;
    }
}

class scoc_utility
{
    public static function cleanName($text)
    {
        $text = str_replace("Â", "", $text);
        $text = str_replace("–", "-", $text);
        $text = str_replace(" ", " ", $text);
        $text = str_replace("ç", "c", $text);
        $text = str_replace("Ç", "C", $text);
        $text = str_replace("ñ", "n", $text);
        $text = str_replace("Ñ", "N", $text);

        //2) Translation CP1252. &ndash; => -
        $trans = array();
        $trans['&sbquo;'] = '&#x82;';    // Single Low-9 Quotation Mark
        $trans['&mdash;'] = '&#x97;';    // Latin Small Letter F With Hook
        $trans['&ndash;'] = '&#x97;';    // Double Low-9 Quotation Mark
        $trans['&hellip;'] = '&#8230;';    // Horizontal Ellipsis
        $trans['&circ;'] = '&#8853;';    // Modifier Letter Circumflex Accent
        $trans['&tilde;'] = '&#732;';    // Small Tilde
        $trans['&trade;'] = '&#174;';    // Trade Mark Sign
        $trans['&nbsp;'] = '&#xA0;';
        $trans['&ldquo;'] = '&#x93;'; // Left Double Quote
        $trans['&rdquo;'] = '&#x94;'; // right Double Quote
        $trans['&eacute;'] = '&#201;'; // right Double Quote
        $trans['&euro;'] = '&#8364;';    // euro currency symbol
        $trans['&rsquo;'] = '&#x92;';
        $trans['&lsquo;'] = '&#x91;';
        $trans['&agrave;'] = '&#192;';
        $trans['&reg;'] = '&#174;';
        $trans['&Atilde;'] = '&#195;';
        $trans['&frac14;'] = '&#188;';
        $trans['&frac12;'] = '&#189;';
        $trans['&frac34;'] = '&#190;';

        ksort($trans);

        foreach ($trans as $k => $v) {
            $text = str_replace($k, $v, $text);
        }

        $text = str_ireplace('&Acirc;', '', $text);
        $text = str_replace('"', "&#34;", $text);
        // 3) remove <p>, <br/> ...
        //$text = strip_tags($text);
        // 4) &amp; => & &quot; => '
        //$text = html_entity_decode($text);
        // 5) remove Windows-1252 symbols like "TradeMark", "Euro"...
        //$text = preg_replace('/[^(\x20-\x7F)]*/', '', $text);

        $targets = array('\r\n', '\n', '\r', '\t');
        $results = array(" ", " ", " ", "");
        $text = str_replace($targets, $results, $text);

        //XML compatible
        /*
          $text = str_replace("&", "and", $text);
          $text = str_replace("<", ".", $text);
          $text = str_replace(">", ".", $text);
          $text = str_replace("\\", "-", $text);
          $text = str_replace("/", "-", $text);
         */

        return ($text);
    }

    public static function cleanString($text)
    {
        // 1) convert á ô => a o
        /*
          $text = preg_replace("/[áàâãªä]/u", "a", $text);
          $text = preg_replace("/[ÁÀÂÃÄ]/u", "A", $text);
          $text = preg_replace("/[ÍÌÎÏ]/u", "I", $text);
          $text = preg_replace("/[íìîï]/u", "i", $text);
          $text = preg_replace("/[éèêë]/u", "e", $text);
          $text = preg_replace("/[ÉÈÊË]/u", "E", $text);
          $text = preg_replace("/[óòôõºö]/u", "o", $text);
          $text = preg_replace("/[ÓÒÔÕÖ]/u", "O", $text);
          $text = preg_replace("/[úùûü]/u", "u", $text);
          $text = preg_replace("/[ÚÙÛÜ]/u", "U", $text);
          $text = preg_replace("/[’‘‹›‚]/u", "'", $text);
          $text = preg_replace("/[“”«»„]/u", '"', $text);
         */
        $text = str_replace("–", "-", $text);
        $text = str_replace(" ", " ", $text);
        $text = str_replace("ç", "c", $text);
        $text = str_replace("Ç", "C", $text);
        $text = str_replace("ñ", "n", $text);
        $text = str_replace("Ñ", "N", $text);

        //2) Translation CP1252. &ndash; => -
        $trans = array();
        $trans['&sbquo;'] = '&#x82;';    // Single Low-9 Quotation Mark
        $trans['&mdash;'] = '&#x97;';    // Latin Small Letter F With Hook
        $trans['&ndash;'] = '&#x97;';    // Double Low-9 Quotation Mark
        $trans['&hellip;'] = '&#8230;';    // Horizontal Ellipsis
        $trans['&circ;'] = '&#8853;';    // Modifier Letter Circumflex Accent
        $trans['&tilde;'] = '&#732;';    // Small Tilde
        $trans['&trade;'] = '&#174;';    // Trade Mark Sign
        $trans['&nbsp;'] = '&#xA0;';
        $trans['&ldquo;'] = '&#x93;'; // Left Double Quote
        $trans['&rdquo;'] = '&#x94;'; // right Double Quote
        $trans['&eacute;'] = '&#201;'; // right Double Quote
        $trans['&euro;'] = '&#8364;';    // euro currency symbol
        $trans['&rsquo;'] = '&#x92;';
        $trans['&lsquo;'] = '&#x91;';
        $trans['&agrave;'] = '&#192;';
        $trans['&reg;'] = '&#174;';
        $trans['&Atilde;'] = '&#195;';
        $trans['&frac14;'] = '&#188;';
        $trans['&frac12;'] = '&#189;';
        $trans['&frac34;'] = '&#190;';

        ksort($trans);

        foreach ($trans as $k => $v) {
            $text = str_replace($k, $v, $text);
        }

        // 3) remove <p>, <br/> ...
        //$text = strip_tags($text);
        // 4) &amp; => & &quot; => '
        //$text = html_entity_decode($text);
        // 5) remove Windows-1252 symbols like "TradeMark", "Euro"...
        //$text = preg_replace('/[^(\x20-\x7F)]*/', '', $text);

        $targets = array('\r\n', '\n', '\r', '\t');
        $results = array(" ", " ", " ", "");
        $text = str_replace($targets, $results, $text);

        //XML compatible
        /*
          $text = str_replace("&", "and", $text);
          $text = str_replace("<", ".", $text);
          $text = str_replace(">", ".", $text);
          $text = str_replace("\\", "-", $text);
          $text = str_replace("/", "-", $text);
         */

        return ($text);
    }

    public static function printHeader()
    {
        header('HTTP/1.1 200 OK');
        header("Pragma: no-cache");
        header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
        header("Content-type: text/xml");
    }

    public static function convertToXmlAndPrint($simpleXml)
    {


        // FOR PROPER FORMATTING XML
        // Create a new DOMDocument object
        $doc = new DOMDocument('1.0', 'utf-8');
        // add spaces, new lines and make the XML more readable format
        $doc->formatOutput = false;
        // Get a DOMElement object from a SimpleXMLElement object
        $domnode = dom_import_simplexml($simpleXml);
        $domnode->preserveWhiteSpace = false;
        $domnode = $doc->importNode($domnode, true);
        // Add new child at the end of the children
        $domnode = $doc->appendChild($domnode);

        // Dump the internal XML tree back into a string
        if (true) { //$this->debug== false
            try {
                $saveXml = $doc->saveXML();
                //$text = preg_replace('/\b&amp;\b/', '&', $text);
                //$text = preg_replace('/\b&\b/', '&amp;', $text);
                echo scoc_utility::cleanString($saveXml);
                //echo ($saveXml);
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    public static function convertToXmlAndPrint_NoEncode($simpleXml)
    {

        // FOR PROPER FORMATTING XML
        // Create a new DOMDocument object
        $doc = new DOMDocument('1.0', 'utf-8');
        // add spaces, new lines and make the XML more readable format
        $doc->formatOutput = false;
        // Get a DOMElement object from a SimpleXMLElement object
        $domnode = dom_import_simplexml($simpleXml);
        $domnode->preserveWhiteSpace = false;
        $domnode = $doc->importNode($domnode, true);
        // Add new child at the end of the children
        $domnode = $doc->appendChild($domnode);

        // Dump the internal XML tree back into a string
        if (true) { //$this->debug== false
            try {
                $saveXml = $doc->saveXML();
                echo($saveXml);
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    public static function toLocalDateString($subvalue)
    {
        global $sql_tbl, $config, $active_modules;
        $subvalue = strftime($config['Appearance']['date_format'], $subvalue) . " " . strftime(
            $config['Appearance']['time_format'],
            $subvalue
        );
        return $subvalue;
    }

    public static function convertUnixToW3c($timestamp)
    {
        return date(DATE_W3C, $timestamp);
    }

    public static function convertW3cToUnix($timestamp)
    {
        return strtotime($timestamp);
    }

    public static function lastIndexOf($string, $item)
    {
        $index = strpos(strrev($string), strrev($item));
        if ($index) {
            $index = strlen($string) - strlen($item) - $index;
            return $index;
        } else {
            return -1;
        }
    }

    public static function isValidUrl($url)
    {
        return preg_match(
            '|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i',
            $url
        );
    }

    public function _print($verbose, $s)
    {
        if ($verbose == 1) {
            echo '<li>' . $s . '</li>';
        }
        //$this->log->write($s);
    }
}

class scoc_TripleDES
{
    private $bPassword;
    private $sPassword;

    public function __construct()
    {
        $Password = "";
        $this->bPassword = md5(utf8_encode($Password), true);
        $this->bPassword .= substr($this->bPassword, 0, 8);
        $this->sPassword = $Password;
    }

    public function setSalt($salt)
    {
        $this->bPassword = md5(utf8_encode($salt), true);
        $this->bPassword .= substr($this->bPassword, 0, 8);
        $this->sPassword = $salt;
    }

    public function PasswordHash()
    {
        return $this->bPassword;
    }

    public function Encrypt($Message, $salt)
    {
        if ($salt <> "") {
            $this->setSalt($salt);
        }
        $size = mcrypt_get_block_size('tripledes', 'ecb');
        $padding = $size - ((strlen($Message)) % $size);
        $Message .= str_repeat(chr($padding), $padding);
        $encrypt = mcrypt_encrypt('tripledes', $this->bPassword, $Message, 'ecb');
        return base64_encode($encrypt);
    }

    public function Decrypt($message, $salt)
    {
        if ($salt <> "") {
            $this->setSalt($salt);
        }

        try {
            return mcrypt_decrypt('tripledes', $this->bPassword, base64_decode($message), 'ecb');
        } catch (Exception $ex) {
            return false;
        }
    }
}



class PageResults
{
    public $sql_query;
    public $number_of_rows;
    public $current_page_number;
    public $number_of_pages;
    public $number_of_rows_per_page;
    public $page_name;

    /* class constructor */

    public function __construct($query, $max_rows, $count_key = '*', $page_holder = 'currentpageindex')
    {
        global $HTTP_GET_VARS, $HTTP_POST_VARS;
        //var_dump($query);
        $this->sql_query = $query;
        $this->page_name = $page_holder;

        if (isset($_GET[$page_holder])) {
            $page = $_GET[$page_holder];
        } elseif (isset($_POST[$page_holder])) {
            $page = $_POST[$page_holder];
        } else {
            $page = '';
        }
        //echo '-------------'.$page_holder.'-------------------'.var_dump($HTTP_GET_VARS['page']);
        if (empty($page) || !is_numeric($page)) {
            $page = 1;
        }
        $this->current_page_number = $page;

        $this->number_of_rows_per_page = $max_rows;

        $pos_to = strlen($this->sql_query);
        $pos_from = strpos($this->sql_query, ' from', 0);

        $pos_group_by = strpos($this->sql_query, ' group by', $pos_from);
        if (($pos_group_by < $pos_to) && ($pos_group_by != false)) {
            $pos_to = $pos_group_by;
        }

        $pos_having = strpos($this->sql_query, ' having', $pos_from);
        if (($pos_having < $pos_to) && ($pos_having != false)) {
            $pos_to = $pos_having;
        }

        $pos_order_by = strpos($this->sql_query, ' order by', $pos_from);
        if (($pos_order_by < $pos_to) && ($pos_order_by != false)) {
            $pos_to = $pos_order_by;
        }

        if (strpos($this->sql_query, 'distinct') || strpos(
            $this->sql_query,
            'group by'
        )) {
            $count_string = 'distinct ' . tep_db_input($count_key);
        } else {
            $count_string = tep_db_input($count_key);
        }

        $count_query = tep_db_query("select count(" . $count_string . ") as total " . substr(
            $this->sql_query,
            $pos_from,
            ($pos_to - $pos_from)
        ));
        $count = tep_db_fetch_array($count_query);

        $this->number_of_rows = $count['total'];

        $this->number_of_pages = ceil($this->number_of_rows / $this->number_of_rows_per_page);

        if ($this->current_page_number > $this->number_of_pages) {
            $this->current_page_number = $this->number_of_pages;
        }

        $offset = ($this->number_of_rows_per_page * ($this->current_page_number - 1));

        $this->sql_query .= " limit " . max($offset, 0) . ", " . $this->number_of_rows_per_page;
    }
}

class SimpleXMLExtended extends SimpleXMLElement
{
    public function addCData($cdata_text)
    {
        $node = dom_import_simplexml($this);
        $no = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($cdata_text));
    }
}
