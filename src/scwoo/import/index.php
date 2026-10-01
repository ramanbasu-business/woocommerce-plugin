<?php
ini_set("max_execution_time", 300);
session_start();
require('../include.php');

$scoc_encoder = new scoc_encoder();
$scoc_utility = new scoc_utility();


if (isset($_GET['verbose']) && $_GET['verbose'] === "1") :
else :
    $scoc_utility->printHeader();
    echo "<?xml version=\"1.0\"?>\n";
endif;



$qtrStrArray = $scoc_encoder->authenticate();

if ($qtrStrArray == null) {
    unset($scoc_utility, $scoc_encoder);
    return;
}

//echo file_get_contents('php://input');
if ($_SERVER['REQUEST_METHOD'] === 'POST') :
    process_import();
endif;

function process_import()
{
    $queue = 0;
    $verbose = 0;
    $isDeleteTransient = 1; // Making default ON

    if (isset($_GET['queue']) && $_GET['queue'] != "") {
        $queue = $_GET['queue'] == "1" ? 1 : 0;
    }

    if (isset($_GET['verbose']) && $_GET['verbose'] != "") {
        $verbose = $_GET['verbose'] == "1" ? 1 : 0;
    }

    if (isset($_GET['del_transient']) && $_GET['del_transient'] != "") {
        $isDeleteTransient = $_GET['del_transient'] == "1" ? 1 : 0;
    }

    $xml = file_get_contents('php://input');
    _processPostedData($queue, $verbose, $xml, $isDeleteTransient);
}

function _processPostedData($queue, $verbose, $local_file_source, $isDeleteTransient = 0)
{
    $globalHelper = new scoc_lib();
    $globalHelper->switchToBlog();
    //$globalHelper->CleanData();
    
    $output = '';
    if ($verbose == 0) {
        //header("Content-type: text/xml");
    }

    $feed_xml = simplexml_load_string($local_file_source);
    $module = '';
    if (isset($feed_xml['module'])) {
        $module = $feed_xml['module'];
    } else {
        $module = '';
    }

    /*
     * create xml file with posted data to extension front-end
     * "/files/scxe_log/xxx.log"
     */
    /* try {
      $filePath = $globalHelper->getLogFolderPath() . $localfilename;
      $FileHandle = fopen( $filePath, 'w' ); // or die("can't open file");
      fwrite( $FileHandle, $local_file_source );
      fclose( $FileHandle );
      } catch (Exception $ex) {
      $error_output = htmlentities( $ex->getMessage() );
      $globalHelper->printJobXmlError( $error_output );
      return;
      } */

    /*
     * Create new job
     */
    //$globalHelper = new scxe_job();
    //$today = date("Y-m-d H:i:s");

    $log_file_source = "<?xml version=\"1.0\"?>\n";
    $log_file_source .= "<products></products>";

    $today = date("Y-m-d H:i:s", strtotime(date("d.m.Y H:i:s")));
    $newId = $globalHelper->insertJob(
        $today,
        null,
        0,
        $module,
        $local_file_source,
        $log_file_source
    );
    
    if ($verbose == 1) {
        echo '[New ID# ' . $newId . ']';
    }

    if ($queue == 0) {
        $recordCount = 0;
        
        switch ($module) :
            case "":
                $output = $globalHelper->importProducts($newId, $verbose);
                break;
            
            case "invprice":
                $output = $globalHelper->importProducts($newId, $verbose, $isDeleteTransient);
                break;
            
            case "shipping":
                $output = $globalHelper->importProducts($newId, $verbose);
                break;
            
            case "order_cancel":
                $output = $globalHelper->importProducts($newId, $verbose);
                break;
            
            // incomplete
            case "order_refund":
                $output = $globalHelper->importProducts($newId, $verbose);
                break;
            
            case "images":
                $output = $globalHelper->importProducts($newId, $verbose);
                break;
            
            case "descriptions":
                $output = $globalHelper->importProducts($newId, $verbose);
                break;
            
            case "titles":
                $output = $globalHelper->importProducts($newId, $verbose);
                break;
            
            case "categories":
                $output = $globalHelper->importProducts($newId, $verbose);
                break;

            case "metas":
                $output = $globalHelper->importProducts($newId, $verbose);
                break;
        endswitch;
        
        /*
        if ($module == '') {
        }
        else if ($module == 'invprice') {
            foreach ($feed_xml->xpath( "/products/product" ) as $child) {
                $recordCount++;
            }

            if ($recordCount > 0 && $recordCount <= 500):
                $output = $globalHelper->importProducts( $newId, $verbose );
            endif;
        } else if ($module == 'shipping') {
            foreach ($feed_xml->xpath( "/orders/order" ) as $child) {
                $recordCount++;
            }

            if ($recordCount > 0 && $recordCount <= 1000):
                $output = $globalHelper->importProducts( $newId, $verbose );
            endif;
        }
         else if ($module == 'order_cancel'){
             $output = $globalHelper->importProducts( $newId, $verbose );
         }

        if ($verbose == 1) {
            echo 'record count = ' . $recordCount;
        }*/
    }

    // print job xml now
    $job_row = $globalHelper->getJob($newId);
    
    $globalHelper->printXml($job_row);
    //$globalHelper->CleanData();
    $globalHelper->restoreCurrentBlog();
}
