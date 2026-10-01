<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of scoc_shipping
 *
 * @author Raman Basu <support@integration.com>
 */
class scoc_shipping
{
    private $verbose;

    public function __construct($verbose)
    {
        $this->verbose = $verbose;
    }

    /*
     * Previous function
     */

    public function _update_shipping(
        $order_id,
        $tracking_provider,
        $tracking_number,
        $shipment_date
    ) {
        update_post_meta($order_id, '_tracking_provider', $tracking_provider);
        //update_post_meta( $post_id, '_custom_tracking_provider', $custom_tracking_provider );
        update_post_meta($order_id, '_tracking_number', $tracking_number);
        update_post_meta(
            $order_id,
            '_date_shipped',
            strtotime($shipment_date)
        );
        return true;
    }

    public function addTrackingToOrder(
        $order_id,
        $tracking_number,
        $provider,
        $date_shipped = null,
        $custom_url = false,
        $new_order_status = '',
        $status_note = ''
    ) {
        // Delete existing tracking with the same number the supplied one here.
        if (function_exists('wc_st_delete_tracking_number')) :
            wc_st_delete_tracking_number($order_id, $tracking_number);
        endif;
        
        if ($date_shipped !== null) :
            $date_shipped = strtotime($date_shipped);
        endif;
        //var_dump(date( 'Y-m-d', strtotime($date_shipped) ));
        //var_dump(current_time( 'timestamp' ));
        
        if (function_exists('wc_st_add_tracking_number')) :
            // create new tracking record.
            wc_st_add_tracking_number(
                $order_id,
                $tracking_number,
                $provider,
                $date_shipped,
                $custom_url
            );
            //echo $new_order_status;
            if ($new_order_status != "") :
                //$order = new WC_Order( $order_id );
                $order = wc_get_order($order_id);
                if ($order) :
                    // Not using now because it wants to redirect to paypal
                    //$this->pay_action($order);

                    $order->update_status($new_order_status, $status_note, true);
                endif;
                unset($order);
            endif;
            return true;
        else :
            return false;
        endif;
    }

    /**
     * If order needs payment, process here
     * D:\xampp7.1.13\htdocs\wordpress532\wp-content\plugins\woocommerce\includes\class-wc-form-handler.php
     **/
    private function pay_action($order)
    {
        global $wp;

        $order_id = $order->get_id();

        if(!$order->needs_payment()):
            WC()->customer->set_props(
                array(
                    'billing_country'  => $order->get_billing_country() ? $order->get_billing_country() : null,
                    'billing_state'    => $order->get_billing_state() ? $order->get_billing_state() : null,
                    'billing_postcode' => $order->get_billing_postcode() ? $order->get_billing_postcode() : null,
                    'billing_city'     => $order->get_billing_city() ? $order->get_billing_city() : null,
                )
            );
            WC()->customer->save();

            try {
                $payment_method_id = !empty( $order->get_payment_method() ) ? 
                    wc_clean( wp_unslash( $order->get_payment_method() ) ) : false;
                
                if ( ! $payment_method_id ):
                    if($this->verbose == 1):
                        echo "payment_method_id not found";
                    endif;

                    return false;
                endif;

                $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                
                $payment_method     = isset( $available_gateways[ $payment_method_id ] ) ?
                    $available_gateways[ $payment_method_id ] : false;
                if ( ! $payment_method ):
                    
                    if($this->verbose == 1):
                        echo "payment_method not found";
                    endif;

                    return false;
                endif;

                $payment_method->validate_fields();
                if($this->verbose == 1):
                    echo "wc_notice_count = " . wc_notice_count( 'error' );
                endif;

                if ( 0 === wc_notice_count( 'error' ) ) {
                    $result = $payment_method->process_payment( $order_id );

                    if($this->verbose == 1):
                        var_dump($result);
                    endif;

                    // Redirect to success/confirmation/payment page.
                    if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
                        return true;
                    }
                }
            } catch ( Exception $e ) {
                wc_add_notice( $e->getMessage(), 'error' );
                if($this->verbose == 1):
                    echo $e->getMessage();
                endif;
            }
        else:
            if($this->verbose == 1):
                echo "Order does not need payment";
            endif;

            $order->payment_complete();
        endif;
    }
}
