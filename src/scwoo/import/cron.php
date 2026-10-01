<?php

require('../include.php');

$scoc_utility = new scoc_utility();
$scoc_encoder = new scoc_encoder();
$scoc_utility->printHeader();

$qtrStrArray = $scoc_encoder->authenticate(true, false);
//var_dump($qtrStrArray); exit;
if ($qtrStrArray == null) {
    unset($scoc_utility, $scoc_encoder);
    return;
}

index();

function index()
{
    //$this->_processProduct();
    $id = 0; // default value for $id
    $verbose = 1; // default mode

    if (isset($_GET['id']) && $_GET['id'] != "") {
        //cron.php?id=XXX
        $id = (int) $_GET["id"];
    }
    //var_dump($id); return false;
    $globalHelper = new scoc_lib();

    //$this->log->write('SCOC :  ' . "job# " . $id . " not found");
    if ($id != 0) {
        $job_row = $globalHelper->getJob($id);
    } else {
        $job_row = $globalHelper->getOneSubmittedJob(null);
    }

    //var_dump($job_row);
    if ($job_row) {
        $id = $job_row->id;
        $module = $job_row->module;

        if ($id > 0) {
            echo "<h2>cron job $id started ........</h2>";

            $output = $globalHelper->importProducts($id, $verbose);

            if ($verbose) {
                echo $output;
            }
            echo "<h2>cron job finished............</h2>";
        }
    } else {
        echo "<h2>No submitted job is found</h2>";
    }
    exit;
}
