<?php
/* ***************************************************************************
* cleanjobs
* March 16, 2021
* To be setup as CRON to remove 7 days old historical job data.
* ************************************************************************* */

$rootPath = dirname(dirname(dirname(__FILE__)));
$rootPath = str_replace("\\", "/", $rootPath);
include($rootPath . '/wp-load.php');

$scwooPath = (dirname(dirname(__FILE__)));
$scwooPath = str_replace("\\", "/", $scwooPath);


global $woo_options;

require_once $scwooPath . '/lib/scoc_encoder.php';
require_once $scwooPath . '/lib/scoc_lib2.php';
require_once $scwooPath . '/lib/scoc_importer.php';
require_once $scwooPath . '/lib/scoc_shipping.php';
require_once $scwooPath . '/lib/scoc_xmllog.php';
require_once $scwooPath . '/lib/scoc_variation.php';
require_once $scwooPath . '/lib/scoc_attribute.php';
require_once $scwooPath . '/lib/scoc_AES.php';

$scoc_lib = new scoc_lib();
$scoc_encoder=new scoc_encoder();
$scoc_utility = new scoc_utility();

$scoc_lib->switchToBlog();
$scoc_utility->printHeader();

if ($_SERVER && isset($_SERVER['REQUEST_METHOD'])) :
    $qtrStrArray = $scoc_encoder->authenticate();

	if (!$qtrStrArray) {
	    unset($scoc_utility, $scoc_encoder);
	    return;
	}
endif;

$cleaningMessage = '';
try {
	$scoc_lib->CleanData();
	$cleaningMessage = 'Data cleaning completed';
} catch (\Exception $e) {
    $cleaningMessage = $e->getMessage();
}
?>
<?xml version="1.0" ?>
<task>
	<name>data cleanup</name>
	<status><?php echo ($cleaningMessage) ?></status>
</task>
