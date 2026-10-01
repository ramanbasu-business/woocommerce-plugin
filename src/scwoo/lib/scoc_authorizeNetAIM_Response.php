<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of scoc_authorizeNetAIM_Response
 *
 * @author raman
 */
class scoc_authorizeNetAIM_Response extends scoc_authorizeNetResponse
{
    private $_response_array = array(); // An array with the split response.

    public function __construct($response_array)
    {
        $this->_response_array = $response_array;

        /**
         * If AuthorizeNet doesn't return a delimited response.
         */
        if (count($this->_response_array) < 10) {
            $this->approved = false;
            $this->error = true;
            $this->error_message = "Unrecognized response from AuthorizeNet: " . implode("+", $response_array);
            return;
        }
        
        // If there are more than one "product name" element in array, it adds extra
        // element to array. Thus, wrong position occurs.
        $temp_description = $this->_response_array[8];
        $arr = $this->_response_array;
        for ($i=9; $i< count($arr); $i++) :
            if (strpos($arr[$i], 'Product name:') !== false) :
                $temp_description .= $arr[$i];
                unset($arr[$i]);
            endif;
        endfor;
        $this->_response_array = array_values($arr);
        //echo "\r\n========== NEW ARRAY =========== ";
        //var_dump($this->_response_array);
        
        // Set all fields
        $this->response_code        = $this->_response_array[0];
        $this->response_subcode     = $this->_response_array[1];
        $this->response_reason_code = $this->_response_array[2];
        $this->response_reason_text = $this->_response_array[3];
        $this->authorization_code   = $this->_response_array[4];
        $this->avs_response         = $this->_response_array[5];
        $this->transaction_id       = $this->_response_array[6];
        $this->invoice_number       = $this->_response_array[7];
        $this->description          = $temp_description;
        $this->amount               = $this->_response_array[9];
        $this->method               = $this->_response_array[10];
        $this->transaction_type     = $this->_response_array[11];
        
        $this->customer_id          = $this->_response_array[12];
        $this->first_name           = $this->_response_array[13];
        $this->last_name            = $this->_response_array[14];
        $this->company              = $this->_response_array[15];
        $this->address              = $this->_response_array[16];
        $this->city                 = $this->_response_array[17];
        $this->state                = $this->_response_array[18];
        $this->zip_code             = $this->_response_array[19];
        $this->country              = $this->_response_array[20];
        $this->phone                = $this->_response_array[21];
        $this->fax                  = $this->_response_array[22];
        $this->email_address        = $this->_response_array[23];
        $this->ship_to_first_name   = $this->_response_array[24];
        $this->ship_to_last_name    = $this->_response_array[25];
        $this->ship_to_company      = $this->_response_array[26];
        $this->ship_to_address      = $this->_response_array[27];
        $this->ship_to_city         = $this->_response_array[28];
        $this->ship_to_state        = $this->_response_array[29];
        $this->ship_to_zip_code     = $this->_response_array[30];
        $this->ship_to_country      = $this->_response_array[31];
        $this->tax                  = $this->_response_array[32];
        $this->duty                 = $this->_response_array[33];
        $this->freight              = $this->_response_array[34];
        $this->tax_exempt           = $this->_response_array[35];
        $this->purchase_order_number= $this->_response_array[36];
        $this->md5_hash             = $this->_response_array[37];
        $this->card_code_response   = $this->_response_array[38];
        
        // Cardholder Authentication Verification Response
        $this->cavv_response        = $this->_response_array[39];
        $this->account_number       = $this->_response_array[50];
        $this->card_type            = $this->_response_array[51];
        $this->split_tender_id      = $this->_response_array[52];
        $this->requested_amount     = $this->_response_array[53];
        $this->balance_on_card      = $this->_response_array[54];
        
        $this->approved = ($this->response_code == self::APPROVED);
        $this->declined = ($this->response_code == self::DECLINED);
        $this->error    = ($this->response_code == self::ERROR);
        $this->held     = ($this->response_code == self::HELD);
        
        if ($this->_response_array[52]=="Visa" || $this->_response_array[52]=="MasterCard") :
            $this->card_type            = $this->_response_array[52];
        endif;
        
        if ($this->response_code == 1) :
            $this->response_code_text = "Approved";
        elseif ($this->response_code == 2) :
            $this->response_code_text = "Declined";
        elseif ($this->response_code == 3) :
            $this->response_code_text = "Error";
        elseif ($this->response_code == 4) :
            $this->response_code_text = "Held for review";
        endif;
        
        if ($this->error) {
            $this->error_message = "AuthorizeNet Error:
                Response Code: ".$this->response_code."
                Response Subcode: ".$this->response_subcode."
                Response Reason Code: ".$this->response_reason_code."
                Response Reason Text: ".$this->response_reason_text . "";
        }
    }
}
