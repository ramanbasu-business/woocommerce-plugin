<?php


require('../include.php');
define('WP_DEBUG', true);
install();

function install()
{
    //include_once( 'admin/woocommerce-admin-install.php' );
    //set_transient( '_wc_activation_redirect', 1, 60 * 60 );
    //global $wpdb;
    //require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    //$wpdb->show_errors();

    /*$q = 'CREATE TABLE IF NOT EXISTS job (
            id BIGINT NOT NULL  AUTO_INCREMENT,
            submittedon DATETIME NOT NULL ,
            processedon DATETIME NULL ,
            localfilename VARCHAR( 100 ) NOT NULL ,
            status TINYINT NOT NULL ,
            logfilename VARCHAR( 100 ) NOT NULL ,
            module varchar(50) NULL,
        PRIMARY KEY (  id )
            )';
    dbDelta($q);
    */
    
    $globalhelper=new scoc_lib();
    $globalhelper->createJobTableIfNotExist();
    echo '<html><head><style type="text/css">body{font:12px trebuche;}</style> </head><body><h2>SCWOO plugin for Wordpress has been installed successfully</h2></body></html>';
    
    //$admin_path = str_replace( get_bloginfo( 'url' ) . '/', ABSPATH, get_admin_url() );
    //echo $admin_path;
    
    exit();
}
