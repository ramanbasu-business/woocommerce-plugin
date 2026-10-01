<?php

/*
 * integration SCME

NOTICE OF LICENSE

This source file is subject to the Open Software License (OSL 3.0)

DISCLAIMER

Do not edit or add to this file if you wish to upgrade to newer
versions in the future. If you wish to customize this extension for your
needs please refer to http://www.integration.com for more information.

@author      Raman Basu <support@integration.com>
@package     Scme_Imports
@copyright   Copyright (C) 2016 integration <support@integration.com>
@license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

header('HTTP/1.1 200 OK');
header("Pragma: no-cache");
header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
header("Content-type: text/html");

require('../include.php');

$util = new scoc_utility();
$scoc_lib=new scoc_lib();
$scoc_encoder=new scoc_encoder();

$qtrStrArray = $scoc_encoder->authenticate();
if ($qtrStrArray == null) {
    return;
}
        
//$util->printHeader();
$newId = !empty($_GET['id']) ? intval($_GET['id']) : 0;

$job_row = $scoc_lib->getJob($newId);
if ($job_row!== null) :
    echo $job_row->stack_trace;
endif;


unset($job_row);
