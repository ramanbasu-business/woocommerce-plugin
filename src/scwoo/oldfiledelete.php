<?php


clean_files();
clean_logs();

function clean_logs()
{
    $curr_path= dirname(__FILE__);
    foreach (glob( $curr_path. "/log/*.xml" ) as $file) {
        $filelastmodified = filemtime( $file );

        if ((time() - $filelastmodified) > 0 * 24 * 60 * 60) {
            unlink( $file );
            echo $file . "<br />";
        }
    }
    
    foreach (glob($curr_path . "/log/*.txt" ) as $file) {
        $filelastmodified = filemtime( $file );

        if ((time() - $filelastmodified) > 0 * 24 * 60 * 60) {
            unlink( $file );
            echo $file . "<br />";
        }
    }
}

function clean_files()
{
    $path = dirname(__FILE__) . '/log';
    if ($handle = opendir( $path )) {

        while (false !== ($file = readdir( $handle ))) {
            $filelastmodified = filemtime( $path . $file );
            //24 hours in a day * 3600 seconds per hour
            if ((time() - $filelastmodified) > 24 * 3600) {
                unlink( $path . $file );
            }
        }

        closedir( $handle );
    }
    foreach (glob( "log/*xml" ) as $file) {
        unlink( $file );
        echo $file . "<br />";
    }
    foreach (glob( "log/*txt" ) as $file) {
        unlink( $file );
        echo $file . "<br />";
    }
}
