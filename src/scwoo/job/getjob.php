<?php
header('HTTP/1.1 200 OK');
header("Pragma: no-cache");
header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
header("Content-type: text/xml");

echo "<?xml version=\"1.0\"?>\n";

require('../include.php');

getjob();

function getjob()
{
    //$this->response->addHeader('Content-Type:xml');
    //$this->request->get['currentPageIndex']
    $scoc_lib = new scoc_lib();
    $scoc_encoder = new scoc_encoder();
    $util = new scoc_utility();

    //$util->printHeader();
    $qtrStrArray = $scoc_encoder->authenticate();
    if ($qtrStrArray == null) {
        return;
    }

    $newId = !empty($qtrStrArray['id']) ? intval($qtrStrArray['id']) : 0;

    $job_row = $scoc_lib->getJob($newId);

    $scoc_lib->printXml($job_row);
    unset($util);
}
