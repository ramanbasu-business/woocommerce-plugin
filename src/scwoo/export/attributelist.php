<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require('../include.php');
@ini_set('log_errors', 1);
@ini_set('display_errors', 0); /* enable or disable public display of errors (use 'On' or 'Off') */
@ini_set('error_log', dirname(__FILE__) . '/php-errors.log'); /* path to server-writable log file */
@ini_set('error_reporting', E_ALL); /* the php parser to  all errors, excreportept notices.  */

$scoc_lib = new scoc_lib();
$scoc_encoder=new scoc_encoder();
$scoc_utility = new scoc_utility();

$scoc_lib->switchToBlog();
$scoc_utility->printHeader();

$qtrStrArray = $scoc_encoder->authenticate();
if ($qtrStrArray == null) {
    unset($scoc_encoder);
    return;
}

$output = '<?xml version="1.0" encoding="UTF-8" ?>';
$output .= '<attributelist>';
$output .= $scoc_lib->getVersionXml();
echo $output;

$scoc_attribute = new scoc_attribute();

$scoc_attribute->product_condition_options_fields();
$scoc_attribute->acf_fields();
$scoc_attribute->wpat_fields();
$scoc_attribute->extendons_product_condition_fields();
$scoc_attribute->other_fields();

$output = '</attributelist>';
echo $output;
//ob_end_clean();

$scoc_lib->restoreCurrentBlog();

exit;
