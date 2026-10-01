WooCommerce Plugin
==================

This repository contains a legacy WordPress/WooCommerce plugin for synchronising store data with an external integration-like integration layer.

Security note
-------------
This file intentionally contains no real credentials, client names, store URLs, or production account details. All examples below use public placeholders only.

Examples of live endpoints in a real store should be stored in the WordPress admin configuration or environment-specific deployment settings, not committed to source control.

Example endpoints
-----------------
- https://shop.example.com/wp-content/plugins/woocommerce-plugin/export/auth.php?consumer_key=CHANGE_ME&consumer_secret=CHANGE_ME
- https://shop.example.com/wp-content/plugins/woocommerce-plugin/export/product.php?rowsperpage=2&currentpageindex=1&sku=EXAMPLE
- https://shop.example.com/wp-content/plugins/woocommerce-plugin/export/order.php?u=admin&p=CHANGE_ME&rowsperpage=4&currentpageindex=1
- https://shop.example.com/wp-content/plugins/woocommerce-plugin/import
- https://shop.example.com/wp-content/plugins/woocommerce-plugin/import/cron.php?id=N

Example config values
---------------------
- consumer_key: CHANGE_ME
- consumer_secret: CHANGE_ME
- admin username: CHANGE_ME
- admin password: CHANGE_ME

Important
---------
- Real credentials must never be committed.
- Client-host names and local development URLs were removed from the public repo.
- The plugin expects secure transport for credential-bearing requests.
- The actual runtime values belong in the WordPress store configuration or a secure environment-specific store.

