<?php
require('../include.php');

$scoc_encoder=new scoc_encoder();
$qtrStrArray = $scoc_encoder->authenticate();
if ($qtrStrArray == null) {
    return;
}
?>
<html>
    <head>
        <style type="text/css">
            body,table{font-family:arial, serif; font-size:12px;}
        </style>
        <script type="text/javascript">
            function submit_error(id)
            {
                document.getElementById("hidid").value = id;
                document.getElementById("form1").submit();
            }
        </script>
    </head>
    <?php
    ini_set("display_errors", true);
    umask(0);
    $dbObject = new scoc_lib();
    
    if ($_POST["hidid"] != "") {
        $id = $_POST["hidid"];
        //$dbObject->updateInvalid();
        $jobs = $dbObject->getJobs(100);
        foreach ($jobs as $job) {
            try {
                $newStatus = $_POST['cbo'][$job->id];
                //echo 'id=' .$job->id .  ' new status= ' . $newStatus . '<br/>';
                if ($job->id != '' && $newStatus != '') {
                    $dbObject->updateJobStatus($job->id, $newStatus);
                }
            } catch (Exception $e) {
                //echo $e->getMessage();
            }
        }
    }
    if ($_POST["btnCleanUp"] != "") :
        $numRows= $dbObject->CleanData();
        echo "Data clean up.. done " . $numRows;
    endif;
    ?>
    <body>
        <form id="form1" name="form1" method="post" action="<?php echo $_SERVER["PATH_INFO"] ?>" >
            <input id="hidid" name="hidid" type="hidden" value=""/>
            <h3>Jobs# <?php echo $dbObject->getJobCount() ?></h3>
            <table border="1" style="border-collapse:collapse;" width="100%">
                <thead>
                <tr>
                    <td>id</td><td>Submitted On</td><td>Processed On</td><td>Xml</td><td>Log</td><td>Status</td><td>Module</td>
                </tr>
                </thead>
                <tbody>
<?php
//var_dump($_SERVER);

$jobs = $dbObject->getJobs(100);
//var_dump( $jobs );
foreach ($jobs as $job) {
    $status = "";
    $color = "";
    if ($job->status == -1) {
        $status = "error";
        $color = "#FFAF9B";
    } elseif ($job->status == 0) {
        $status = "submitted";
        $color = "#FFFF99";
    } elseif ($job->status == 1) {
        $status = "processing";
        $color = "#A8FFA8";
    } elseif ($job->status == 2) {
        $status = "completed";
        $color = "#CAE4FF";
    }
    $selected_text = 'selected="selected"';
    
    $fullpath = site_url() . '/scwoo/'
    //echo $fullpath . "<hr>";
    ?>
        <tr style="background-color:<?php echo $color ?>">
            <td><a href="<?php echo $fullpath . "job/getjob.php?id=" . $job->id ?>" target="_blank"><?php echo $job->id ?><a/></td>
            <td><?php echo $job->submittedon ?></td>
            <td><?php echo $job->processedon ?></td>
            <td><a href="<?php echo $fullpath . "job/jobsource.php?id=" . $job->id ?>" target="_blank">SOURCE</a></td>
            <td><a href="<?php echo $fullpath . "job/joblog.php?id=" . $job->id ?>" target="_blank">LOG<a/></td>
            <!--<td><?php echo $job->status ?></td>-->
            <td>
                <select name="cbo[<?php echo $job->id ?>]">
                    <option value="0" <?php echo $job->status == 0 ? $selected_text : "" ?>>submitted</option>
                    <option value="1" <?php echo $job->status == 1 ? $selected_text : "" ?>>processing</option>
                    <option value="2" <?php echo $job->status == 2 ? $selected_text : "" ?>>completed</option>
                    <option value="-1" <?php echo $job->status == -1 ? $selected_text : "" ?>>error</option>
                </select>
            </td>
            <td><?php echo $job->module ?></td>
        </tr>
    <?php
}
unset($dbObject);

?>
        </tbody>
        </table>
            <input id="btnCleanUp" name="btnCleanUp" type="submit" value="Clean Up" />
            <input type="button" value="Update Status" onclick="submit_error(1);" />
        </form>
    </body>
</html>