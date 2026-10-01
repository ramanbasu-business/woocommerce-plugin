<?php
require('../include.php');
//switch_to_blog(1);

$scoc_lib = new scoc_lib();
$scoc_utility = new scoc_utility();
$scoc_encoder = new scoc_encoder();

$scoc_lib->switchToBlog();
$scoc_utility->printHeader();
echo '<?xml version="1.0" encoding="UTF-8" ?>';


//$scoc_utility->printHeader();

$qtrStrArray = $scoc_encoder->authenticate();

//echo "auth.php result \r\n";
//var_dump($qtrStrArray);
//exit;

if ($qtrStrArray == null) {
    unset($scoc_utility, $scoc_encoder);
    return;
} else {
    $msg = "<response>"
    . $scoc_lib->getVersionXml()
    . "<status>success</status>"
    . "<message>User has been authenticated successfully</message>"
    . "</response>";
    echo $msg;
}


$scoc_lib->restoreCurrentBlog();
unset($scoc_utility, $encoder);
