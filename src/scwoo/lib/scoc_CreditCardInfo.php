<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of scoc_CreditCardInfo
 *
 * @author raman
 */
class scoc_CreditCardInfo
{

    public $x_card_num = "";
    public $x_card_code = "";
    public $x_exp_month = 0;
    public $x_exp_year = 0;

    public function __construct($meta_value)
    {
        if (isset($meta_value["x_card_num"])) :
            $this->x_card_num = $meta_value["x_card_num"];
            $this->x_card_num = '************' . substr($this->x_card_num, -4);
        endif;

        if (isset($meta_value["x_exp_date"]) && strlen($meta_value["x_exp_date"]) >= 4) :
            $x_exp_date = $meta_value["x_exp_date"];
            //var_dump($x_exp_date);
            $this->x_exp_month = intval(substr($x_exp_date, 0, 2));
            $this->x_exp_year = 2000+ intval(substr($x_exp_date, 2));
        endif;

        if (isset($meta_value["x_card_code"])) :
            $this->x_card_code = (string)$meta_value["x_card_code"];
        endif;
    }
}
