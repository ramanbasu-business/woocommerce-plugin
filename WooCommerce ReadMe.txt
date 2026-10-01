PHP CBF :::
CD D:\xampp7.1.13\htdocs\wordpress_multisite\scwoo\lib
phpcbf  --standard=PSR2 .
--------------------------

-----------------------------------------------------------------
SITE: http://www.localhost.com/wordpress532
http://www.localhost.com/wordpress532/scwoo/export/auth.php?consumer_key=ck_ed634fe2d895a1aee72c8eb9bb1038fc6ba76739&consumer_secret=cs_55f04e769c6af322f1841589df55391ec4e68030

consumer_key: 	ck_ed634fe2d895a1aee72c8eb9bb1038fc6ba76739
consumer_secret: cs_55f04e769c6af322f1841589df55391ec4e68030
=================================================================


---------------------------------------------------------------
SITE: 
D:\xampp820\htdocs\wordpress63
http://www.localhost.com/wordpress63/
<wp_version>6.3</wp_version>
<wc_version>7.9.0</wc_version>
<php_version>8.2.4</php_version>

http://www.localhost.com/wordpress63/scwoo/export/order.php?u=admin&p=dalal123
&consumer_key=ck_798e1a0e48ee2c216192e86272b60a248702ef61&consumer_secret=cs_fc973e8cb9ae42a738e9e6a1d25475e2179fd48d
ck_798e1a0e48ee2c216192e86272b60a248702ef61
cs_fc973e8cb9ae42a738e9e6a1d25475e2179fd48d
===============================================================


WOOCOMMERCE API
===============
www.localhost.com/wordpress_multisite/wp-json/wc/v2

USERS:
admin	dalal123
user2	U%B&4*S$b#		U%25B%264%2AS%24b%23

eyeadmin
pB1&uU5T#R@k*K32kc#xrL5%

TEST SERVER
===========
https://10.13.4.116/wordpress/scwoo/export/order.php?u=admin&p=P@ssw0rd1234@



3.3.3.0		Jan 29, 2019	Added end-point to download attributes from advance custom field plugin
				http://www.localhost.com/wordpress532/scwoo/export/attributelist.php?u=admin&p=dalal123


4.2.0.0		Feb 08, 2019	Product Import: Feature to import custom columns for product dynamically.
5.0.1.0		Nov 01, 2019	CRUD method compatible coding for plugin

=========================================================================================================



$new_post_id = method_exists( $duplicate_product, 'get_id' ) === true ? $duplicate_product->get_id() : $duplicate_product->ID;

Assumption: example website is http://www.localhost.com/wordpress532

Plugin Dependencies:
a) Tracking: WooCommerce Shipment Tracking
https://woocommerce.com/products/shipment-tracking/

b) Brands: Perfect WooCommerce Brands
https://wordpress.org/plugins/perfect-woocommerce-brands/

c) Product custom tabs: Custom Product Tabs Pro Version 1.0.3 
By YIKES, Inc.  https://yikesplugins.com/plugin/custom-product-tabs-pro/
https://wordpress.org/plugins/yikes-inc-easy-custom-woocommerce-product-tabs/

d) Product mpn, upc, ean: are from custom product field.
Plugin: Cart Product Feed Additional Product Fields
By ShoppingCartProductFeed; Remi Corson & Harsha Venkatesh

e) Product Warranty: WooCommerce Warranty Requests
https://woocommerce.com/products/warranty-requests/

MULTI SITE FEATURE:
[blog_id] : Site ID
www.localhost.com/wordpress_multisite/scwoo/export/auth.php?u=admin2&p=dalal1234&blog_id=2


INSTALL
========
http://www.localhost.com/wordpress532/scwoo2/install/
http://www.localhost.com/wordpress_multisite/wp-content/plugins/sellercloud-woocommerce/install/index.php?u=admin&p=dalal123

MORE Features(payment gateways, shipping methods etc.) are available at http://www.woothemes.com/product-category/woocommerce-extensions/?prod_cat%5B%5D=1026&s=&post_type=product&prod_country=0

*For Variation (variable product), make sure that variation attributes (color, size) are already defined as Global attribute. 

ATTRIBUTELIST:
==============
http://www.localhost.com/wordpress532/scwoo2/export/attributelist.php?u=admin&p=dalal123
http://www.localhost.com/wordpress_multisite/wp-content/plugins/sellercloud-woocommerce/export/attributelist.php?u=admin&p=dalal123


CSV ** deprecated in 7.19.0.0
  http://www.localhost.com/wordpress532/scwoo/export/csv.php

  ## normal + parent product list
  # outputs in json
  http://www.localhost.com/wordpress63/scwoo/export/product-list.php?u=admin&p=dalal123&consumer_key=ck_798e1a0e48ee2c216192e86272b60a248702ef61&consumer_secret=cs_fc973e8cb9ae42a738e9e6a1d25475e2179fd48d&currentpageindex=1&rowsperpage=1&format=json

  # outputs in csv
  http://www.localhost.com/wordpress63/scwoo/export/product-list.php?u=admin&p=dalal123&consumer_key=ck_798e1a0e48ee2c216192e86272b60a248702ef61&consumer_secret=cs_fc973e8cb9ae42a738e9e6a1d25475e2179fd48d&currentpageindex=1&rowsperpage=1&format=csv

  ---------------------------
  ## variant product list ##
  # outputs  in json
  http://www.localhost.com/wordpress63/scwoo/export/variation-list.php?u=admin&p=dalal123&consumer_key=ck_798e1a0e48ee2c216192e86272b60a248702ef61&consumer_secret=cs_fc973e8cb9ae42a738e9e6a1d25475e2179fd48d&currentpageindex=1&rowsperpage=10&format=json

  # outputs in csv
  http://www.localhost.com/wordpress63/scwoo/export/variation-list.php?u=admin&p=dalal123&consumer_key=ck_798e1a0e48ee2c216192e86272b60a248702ef61&consumer_secret=cs_fc973e8cb9ae42a738e9e6a1d25475e2179fd48d&currentpageindex=1&rowsperpage=10&format=csv

PRODUCTS XML
============
	http://www.localhost.com/wordpress532/scwoo2/export/product.php?&u=admin&p=dalal123&rowsperpage=2&currentpageindex=1

	http://www.localhost.com/wordpress532/scwoo/export/product.php?rowsperpage=2&currentpageindex=1&sku=TE
	
	http://woocommerce.cellphonemate.net/scwoo/export/product.php?rowsperpage=2&currentpageindex=1

	http://www.localhost.com/wordpress_multisite/wp-content/plugins/sellercloud-woocommerce/export/product.php?u=admin&p=dalal123

INVENTORY
=========
	http://www.localhost.com/wordpress532/scwoo2/export/inventory.php?u=admin&p=dalal123
	http://www.localhost.com/wordpress_multisite/wp-content/plugins/sellercloud-woocommerce/export/inventory.php?u=admin&p=dalal123

ORDERS XML
==========
	http://www.localhost.com/wordpress532/scwoo2/export/order.php?&u=admin&p=dalal123&rowsperpage=4&currentpageindex=1
	
	http://www.localhost.com/wordpress532/scwoo2/export/order.php?rowsperpage=3&currentpageindex=3&from=2014-01-09&to=2022-01-10&u=admin&p=dalal123

	http://www.localhost.com/wordpress532/scwoo2/export/order.php?u=admin&p=dalal123&rowsperpage=4&currentpageindex=1&orderid=46

	3.1.0.0 By order key
	http://www.localhost.com/wordpress532/scwoo/export/order.php?u=admin&p=dalal123&order_key=wc_order_5a4245f1452a3

	3.1.0.0 By post_id
	http://www.localhost.com/wordpress532/scwoo2/export/order.php?u=admin&p=dalal123&post_id=300

	8.0.0.0
	http://www.localhost.com/wordpress_multisite/wp-content/plugins/sellercloud-woocommerce/export/order.php?u=admin&p=dalal123


CATEGORY XML
============
	http://www.localhost.com/wordpress532/scwoo2/export/category.php?u=admin&p=dalal123
	http://www.localhost.com/wordpress_multisite/wp-content/plugins/sellercloud-woocommerce/export/category.php?u=admin&p=dalal123

PRODUCT & INVPRICE IMPORT
==============
http://www.localhost.com/wordpress532/scwoo/import

(7.8.0.0) "del_transient" query string should be appended to delete transients in mini update(for now).


SHIPPING TRACKING IMPORT
==============
http://www.localhost.com/wordpress532/scwoo/import

CRON
====
	http://www.localhost.com/wordpress532/scwoo/import/cron.php?id=N
	http://www.localhost.com/wordpress_multisite/wp-content/plugins/sellercloud-woocommerce/import/cron.php?u=admin&p=dalal123


JOB
====
	http://www.localhost.com/wordpress532/scwoo2/job/getjob.php?u=admin&p=dalal123&id=75

	http://www.localhost.com/wordpress_multisite/wp-content/plugins/sellercloud-woocommerce/job/getjob.php?u=admin&p=dalal123&id=268

JOBS
========
	http://www.localhost.com/wordpress532/scwoo/job/jobs.php?u=admin&p=dalal123

	http://www.localhost.com/wordpress_multisite/wp-content/plugins/sellercloud-woocommerce/job/jobs.php?u=admin&p=dalal123&id=268

// 5.0.0.0 
http://www.localhost.com/wordpress500/scwoo2/export/order.php?u=admin&p=dalal123&from=2019-10-01&to=2019-10-04&status=on-hold,completed,processing

http://www.localhost.com/wordpress500/scwoo/export/order.php?u=admin&p=dalal123&orderid=OD2

CRON for delete old jobs
=============================
	http://www.localhost.com/wordpress532/scwoo2/import/cleanjobs.php
	http://www.localhost.com/wordpress_multisite/scwoo/import/cleanjobs.php

	http://www.localhost.com/wordpress_multisite/wp-content/plugins/sellercloud-woocommerce/import/cleanjobs.php?u=admin&p=dalal123&id=268

 OR 
	D:\xampp7.1.13\htdocs\wordpress_multisite> d:\xampp7.1.13\php\php scwoo/import/cleanjobs.php



