<?php

ob_start();

// we are ont sure which has ABSPATH defined
include_once "../../wp-config.php";
include_once '../../wp-load.php';



ini_set('max_execution_time', 10000);
set_time_limit(10000);

ob_end_clean();

global $woo_options;

if (!defined('SCWOO_PATH')) {
    define('SCWOO_PATH', str_replace("\\", "/", dirname(__FILE__)));
    
}
if (!defined('SCWOO_FOLDER')) {
    define('SCWOO_FOLDER', basename(SCWOO_PATH));
}
if (!defined('SCWOO_LOG')) {
    define('SCWOO_LOG', str_replace("\\", "/", dirname(__FILE__).'/log/'));
}

require_once dirname(__FILE__) . '/lib/scoc_encoder.php';
require_once dirname(__FILE__) . '/lib/scoc_lib2.php';
require_once dirname(__FILE__) . '/lib/scoc_importer.php';
require_once dirname(__FILE__) . '/lib/scoc_shipping.php';
require_once dirname(__FILE__) . '/lib/scoc_xmllog.php';
require_once dirname(__FILE__) . '/lib/scoc_variation.php';
require_once dirname(__FILE__) . '/lib/scoc_attribute.php';
require_once dirname(__FILE__) . '/lib/scoc_AES.php';

@ini_set('log_errors', 1);
@ini_set('error_log', dirname(__FILE__) . '/php-errors.log'); /* path to server-writable log file */

if (isset($_GET['verbose']) && $_GET['verbose']=="1") :
    @ini_set('display_errors', 1); /* enable or disable public display of errors (use 'On' or 'Off') */
    @ini_set('error_reporting', E_ALL); /* the php parser to  all errors, excreportept notices.  */

    if (!defined("WP_DEBUG")) :
        define('WP_DEBUG', true);
    endif;
    
    if (!defined("WP_DEBUG_DISPLAY")) :
        define('WP_DEBUG_DISPLAY', true);
    endif;
else :
    @ini_set('display_errors', false);
    @ini_set('error_reporting', 0);
    
    if (!defined("WP_DEBUG")) :
        define('WP_DEBUG', false);
    endif;
endif;

if (function_exists("add_action")) :
    add_action('wp_head', 'wp_no_robots');
endif;
