<?php

/* * **********************************************************
 *
 * *********************************************************** */

//require_once "KLogger.php";
/* * **********************************************************************
  class to handle product import from given XML file stored in Web server.
 * **********************************************************************
 * global $var_dirs;
 * $var_dirs['log']
 */

class scoc_importer
{
    private $verbose = 0;
    private $xmllog = null;
    private $globalHelper;
    private $variationHelper;
    private $currentJobId = 0;
    private $isDeleteTransient = 0;


    /**
     * arrImageUrlWithId holds Key:ImageUrl and Saved ImageId as value
     * Purpose: Avoid duplicate image saving in variants.
     * **/
    private $arrImageUrlWithId = array();


    public function __construct($jobId, $_verbose = 0, $isDeleteTransient = 0)
    {
        foreach ($GLOBALS as $key => $val) {
            global $$key;
        }
        $this->globalHelper = new scoc_lib();
        $this->verbose = $_verbose;
        $this->variationHelper = new scoc_variation($this->verbose);
        
        if ($jobId != null) :
            $this->currentJobId = $jobId;
            $this->xmllog = new scoc_xmllog($jobId, $_verbose);
        endif;

        $this->isDeleteTransient = $isDeleteTransient;
    }

    public function __destruct()
    {
        unset($this->xmllog);
    }

    public function getModule($feed_xml)
    {
        if (isset($feed_xml['module'])) {
            return $feed_xml['module'];
        }
        return '';
    }

    public function startImport($feed_xml)
    {
        foreach ($GLOBALS as $key => $val) {
            global $$key;
        }

        $this->_print("-------- import process starts --------");

        $scoc_lib = new scoc_lib();
        $languages_id = 1;

        foreach ($feed_xml->product as $productXML) {
            //\admin\post-types\writepanels\writepanel-product_data.php

            if (isset($sql_data_array)) {
                unset($sql_data_array);
            }

            $sku = isset($productXML->sku) ? (string) $productXML->sku : null;
            $product_id = $this->globalHelper->getProductIdBySku($sku);
            $post_id = $product_id;

            $name = isset($productXML->name) ? (string) $productXML->name : null;
            $short_desc = isset($productXML->short_description) ? (string) $productXML->short_description : null;
            $description = isset($productXML->description) ? (string) $productXML->description : null;

            //$_POST['post_type'] = 'product';
            $is_virtual = false;
            $backorders = 'no';

            $this->_print("======== importing SKU '$sku' starts ========");


            if ($product_id == 0) {
                $action = 'insert';
                
                /* Get and Set new product type */
                if (isset($productXML->product_type)) {
                    $product_type = (string) $productXML->product_type;
                } else {
                    $product_type = 'simple';
                }

                $product_type = sanitize_title(stripslashes($product_type));


                $product_id = $this->globalHelper->addProduct($sku, $product_type, $name, $description, $short_desc);
                $post_id = $product_id;
                
                
                $this->_print("New product id: '$product_id'");
                $woo_product = wc_get_product($product_id);
                
                /* @EOF Get and Set new product type */
            } else {
                $action = 'update';
                $this->_print($sku . ' updating product.');

                $woo_product = wc_get_product($product_id);
                //$woo_product = new WC_Product( $product_id );
                
                /*
                if ($_post) {
                    $_post['post_title'] = $name;
                    $_post['post_excerpt'] = $short_desc;
                    $_post['post_content'] = $description;

                    $_post['post_status'] = 'publish';

                    wp_update_post($_post);
                }
                */
                $woo_product->set_name($name);
                $woo_product->set_description($description);
                $woo_product->set_short_description($short_desc);

                /* Get and Set new product type */
                $product_type = $this->globalHelper->getProductType($product_id);
                if (isset($productXML->product_type) && (string) $productXML->product_type != $product_type) {
                    $product_type = sanitize_title(stripslashes((string) $productXML->product_type));
                    //wp_set_object_terms($product_id, $product_type, 'product_type');
                    
                    if (method_exists($woo_product, "set_product_type")) :
                        $woo_product->set_product_type($product_type);
                    endif;
                }

                /* @EOF Get and Set new product type */
                $is_virtual = $woo_product->get_virtual(); //get_post_meta($product_id, '_virtual', true);
                $backorders = $woo_product->get_backorders(); ////get_post_meta($product_id, '_backorders', true);

                $this->_print($sku . ' backorders: ' . $backorders);
            }

            if (isset($productXML->virtual)) {
                $is_virtual = ((int) $productXML->virtual == 1) ? true : false;
                //update_post_meta($product_id, '_virtual', $is_virtual);
                $woo_product->set_virtual($is_virtual);
            }


            // Update other fields from
            // F:\xampp\htdocs\wordpress38\wp-content\plugins\woocommerce\admin\post-types\writepanels\writepanel-product_data.php
            $date_from = (isset($productXML->sale_price_dates_from)) ? (string) $productXML->sale_price_dates_from : '';
            $date_to = (isset($productXML->sale_price_dates_to)) ? (string) $productXML->sale_price_dates_to : '';
            $sale_price = (isset($productXML->sale_price)) ? (float) $productXML->sale_price : '';
            $regular_price = (isset($productXML->regular_price)) ? (float) $productXML->regular_price : '';
            $msrp_price = (isset($productXML->msrp_price)) ? (float) $productXML->msrp_price : '';

            if ($regular_price) {
                //update_post_meta($product_id, '_regular_price', $regular_price);
                $woo_product->set_regular_price($regular_price);
            }
            if ($sale_price) {
                //update_post_meta($product_id, '_sale_price', $sale_price);
                $woo_product->set_regular_price($sale_price);
            }
            
            
            // Dates
            if (in_array($product_type, array('variable', 'grouped'))) {
                // Variable and grouped products have no prices
                /*update_post_meta($product_id, '_regular_price', '');
                update_post_meta($product_id, '_sale_price', '');
                update_post_meta($product_id, '_sale_price_dates_from', '');
                update_post_meta($product_id, '_sale_price_dates_to', '');
                update_post_meta($product_id, '_price', '');
                */

                $woo_product->set_regular_price('');
                $woo_product->set_sale_price('');
                $woo_product->set_date_on_sale_from('');
                $woo_product->set_date_on_sale_to('');
                $woo_product->set_price('');
            } else {
                $this->updatePrices($woo_product, $regular_price, $sale_price, $date_from, $date_to, $msrp_price);
            }

            //if (isset($productXML->price)) {
            //update_post_meta($product_id, '_price', (double) $productXML->price);
            //update_post_meta($product_id, '_sale_price', (double) $productXML->price);
            //}

            if (isset($productXML->regular_price)) {
                //update_post_meta($product_id, '_regular_price', (double) $productXML->regular_price);
                $woo_product->set_regular_price((double) $productXML->regular_price);
            }

            if (isset($productXML->visibility)) {
                //update_post_meta($product_id, '_visibility', (string) $productXML->visibility);
                $woo_product->set_catalog_visibility((string) $productXML->visibility);
            }

            if (isset($productXML->featured)) {
                if ((int) $productXML->featured == 1) {
                    //update_post_meta($product_id, '_featured', 'yes');
                    $woo_product->set_featured(true);
                } else {
                    //update_post_meta($product_id, '_featured', 'no');
                    $woo_product->set_featured(false);
                }
            }

            if (isset($productXML->downloadable)) {
                if ((int) $productXML->downloadable == 1) {
                    //update_post_meta($product_id, '_downloadable', 'yes');
                    $woo_product->set_downloadable(true);
                } else {
                    //update_post_meta($product_id, '_downloadable', 'no');
                    $woo_product->set_downloadable(false);
                }
            }

            if (isset($productXML->tax_status)) {
                //update_post_meta($product_id, '_tax_status', (string) $productXML->tax_status);
                $woo_product->set_tax_status((string) $productXML->tax_status);
            }

            if (isset($productXML->tax_class)) {
                //update_post_meta($product_id, '_tax_class', (string) $productXML->tax_class);
                $woo_product->set_tax_class((string) $productXML->tax_class);
            }

            if ($is_virtual == false) {
                if (wc_product_weight_enabled() && isset($productXML->weight)) :
                    // Weight comes in as LBS(decimal)
                    $_weight = (float) $productXML->weight;
                    $_default_weight_unit = get_option('woocommerce_weight_unit');
                    
                    if ($this->verbose == 1) {
                        $this->_print("weight unit " . $_default_weight_unit);
                    }
                    if ($_default_weight_unit === 'kg') {
                        $_weight = 0.453592 * $_weight;
                    }
                    if ($_default_weight_unit === 'g') {
                        $_weight = 453.592 * $_weight;
                    }
                    if ($_default_weight_unit === 'oz') {
                        $_weight = 16 * $_weight;
                    }

                    //update_post_meta($product_id, '_weight', $_weight);
                    $woo_product->set_weight($_weight);
                endif;

                if (isset($productXML->length)) {
                    //update_post_meta($product_id, '_length', (float) $productXML->length);
                    $woo_product->set_length((float) $productXML->length);
                }

                if (isset($productXML->width)) {
                    //update_post_meta($product_id, '_width', (float) $productXML->width);
                    $woo_product->set_width((float) $productXML->width);
                }

                if (isset($productXML->height)) {
                    //update_post_meta($product_id, '_height', (float) $productXML->height);
                    $woo_product->set_height((float) $productXML->height);
                }
            } else {
                //update_post_meta($product_id, '_weight', '');
                //update_post_meta($product_id, '_length', '');
                //update_post_meta($product_id, '_width', '');
                //update_post_meta($product_id, '_height', '');
                $woo_product->set_weight('');
                $woo_product->set_length('');
                $woo_product->set_width('');
                $woo_product->set_height('');
            }

            if (isset($productXML->purchase_note)) {
                //update_post_meta($product_id, '_purchase_note', (string) $productXML->purchase_note);
                $woo_product->set_purchase_note((string) $productXML->purchase_note);
            }

            if (isset($productXML->backorders)) {
                if ((int) $productXML->backorders == '1' || (string) $productXML->backorders == "yes") {
                    $backorders = 'yes';
                } else {
                    $backorders = 'no';
                }
                
                $this->_print("backorders set as " . $backorders);
                //update_post_meta($product_id, '_backorders', $backorders);
                $woo_product->set_backorders($backorders);
                $this->_print("backorders from product  " . $woo_product->get_backorders());
            }


            $stock_status = isset($productXML->stock_status) ? (string) $productXML->stock_status : 'instock';
            $stock = (isset($productXML->stock)) ? (int) $productXML->stock : null;

            $this->_print("[startImport]stock set as " . $stock);

            //if (isset($productXML->stock)) {
            //update_post_meta($product_id, '_stock', (float) $productXML->stock);
            //}

            if (isset($productXML->stock_status)) {
                //update_post_meta($product_id, '_stock_status', (string) $productXML->stock_status);
                $woo_product->set_stock_status((string) $productXML->stock_status);
            }

            $manage_stock = (isset($productXML->manage_stock)) ? (string) $productXML->manage_stock : '';
            $this->_print("[startImport]manage_stock = " . $manage_stock);
            
            // 7.12.0.0
            // if manage_stock is set to 0, use ELSE condition

            if ($manage_stock == '1') {
                /**
                 *  7.8.2.0
                 * Default value setup section
                 **/
                $woo_product->set_manage_stock(true);
                $woo_product->set_backorders($backorders);
                $woo_product->set_stock_status(stripslashes($stock_status));

                if (!empty($stock)) {
                    $this->_print("[startImport]manage_stock=1, manage_stock not empty, stock=" . $stock);
                    $woo_product->set_stock_quantity($stock);
                }
                /** End Default value setup section **/

                if ($product_type == 'grouped') {
                    
                    $this->_print("[startImport]manage_stock=1, product_type = grouped");

                    $woo_product->set_stock_status(stripslashes($stock_status));
                    $woo_product->set_stock_quantity('');
                    $woo_product->set_manage_stock(false);
                    $woo_product->set_backorders('no');

                } elseif ($product_type == 'external') {
                    $this->_print("[startImport]manage_stock=1, product_type = external");

                    $woo_product->set_stock_status('instock');
                    $woo_product->set_stock_quantity('');
                    $woo_product->set_manage_stock(false);
                    $woo_product->set_backorders('no');
                }

                // Check stock level
                if ($product_type !== 'variable' && $backorders == 'no' && $stock < 1) {
                    $woo_product->set_stock_status('outofstock');
                }

                $this->_print("[stock_status] set to " . $woo_product->get_stock_status());
            
            } elseif($manage_stock == '0') {
                $woo_product->set_stock_quantity(0);
                $woo_product->set_stock_status(stripslashes($stock_status));
                $woo_product->set_backorders($backorders);
                $woo_product->set_manage_stock(false);
                $this->_print("[stock_status] set to " . $woo_product->get_stock_status());
            }


            //_thumbnail_id
            if (isset($productXML->sold_individually)) {
                if ((int) $productXML->sold_individually == 1) {
                    //update_post_meta($product_id, '_sold_individually', true);
                    $woo_product->set_sold_individually(true);
                } else {
                    //update_post_meta($product_id, '_sold_individually', '');
                    $woo_product->set_sold_individually('');
                }
            }


            if (isset($productXML->shipping_class_id) && (string) $productXML->shipping_class_id != '') {
                //wp_set_object_terms($product_id, (int) $productXML->shipping_class_id, 'product_shipping_class');
                $woo_product->set_shipping_class_id((int) $productXML->shipping_class_id);
            }


            // product tags, comma delimited
            if (isset($productXML->tags) && (string) $productXML->tags != '') {
                $tags = (string) $productXML->tags;
                $tags = explode(',', $tags);
                wp_set_object_terms($product_id, $tags, 'product_tag'); // this should work in CRUD
            }

            if (isset($productXML->categories)) {
                $categories = array();
                $root_category_id = $this->globalHelper->getRootCategoryId();
                if ($root_category_id !== null) {
                    $categories[$root_category_id] = $root_category_id;
                }

                $i = 1;
                foreach ($productXML->categories->categoryid as $category_id) {
                    $categories[$i] = (int) $category_id;
                    $i++;
                }
                //var_dump($categories);
                //wp_set_post_terms($product_id, $categories, 'product_cat');
                $woo_product->set_category_ids($categories);
            }

            // raman --------------------------------------------------------------------
            if (isset($productXML->images)) {

                $this->delete_product_images( $product_id ); // 7.17.0.0

                foreach ($productXML->images->image as $image) {
                    if (isset($image['type']) && (string) $image['type'] == 'featured') {
                        $this->saveFeaturedImage($woo_product, (string) $image);
                    } else {
                        //echo 'ssssssssssss 2';
                    }
                }
                //var_dump($categories);
                $this->saveProductGallery($woo_product, $productXML);
            }

            // Save the product data.
            $this->_print("stock_status set to = " . $woo_product->get_stock_status());
            $this->_print("stock set as " . $woo_product->get_stock_quantity());
            $this->_print("Final backorders from product  " . $woo_product->get_backorders());

            //$woo_product->set_stock_quantity(); // testing
            $woo_product->save();

            // for testing 7.10.1.0
            $woo_product = wc_get_product($product_id);
            $this->_print("1***After saving: backorders: " . $woo_product->get_backorders() 
                . " stock_status:" . $woo_product->get_stock_status()
            );
            

            // 7.10.3.0. For simple products, following should not run.
            if($product_type == 'variable') :
                $this->_print('child_is_on_backorder=' . $woo_product->child_is_on_backorder());
            endif;
            
            // ======================================================


            //$woocommerce->clear_product_transients( $post_id );
            if (isset($productXML->attributes)) {
                //if ($this->_saveTaxonomyAttributes($post_id, $sku, $productXML))
                //    $this->_print("$sku : Attributes are saved");
                $pos = 0;
                //$attr_data_variation = array();
                foreach ($productXML->attributes->attribute as $attribute) :
                    $code = (string) $attribute->code;
                    $value = (string) $attribute->value;
                    $is_variation = isset($attribute->is_variation) ? (int) $attribute->is_variation : 0;
                    $attribute_label = isset($attribute->label) ? (string) $attribute->label : $code;

                    $this->_print('saving attribute: ' . $code . ' value:' . $value);
                    //$this->updateProductAttribute($woo_product, $code, $attr_data, $value);
                    $this->updateProductAttribute_Crud($woo_product, $code, $value, $pos, $is_variation);

                    $pos++;
                endforeach;
                
                // 2.5.0.0 raman May 24, 2018
                // 5.0.0.0  No need to create attributes anymore.
                //if (!empty($attr_data_variation)) :
                    //var_dump($attr_data_variation);
                    //$this->variationHelper->updateVariationProperties($product_id, $attr_data_variation);
                //endif;
            }

            if (isset($productXML->variants)) {
                foreach ($productXML->variants->variant as $variant) :
                    //$this->_insertVariant($post_id, $variant); // raman
                    // 2.5.0.0 raman May 24, 2018
                    $this->updateSingleVariation($product_id, $variant); //testing
                endforeach;
            }

            // V1.2 : brand
            if (isset($productXML->brand) && (string) $productXML->brand != '') {
                $brand_id = $scoc_lib->getBrandIdByName((string) $productXML->brand, $this->verbose);
                
                if ($this->verbose == 1) :
                    echo "Brand Id found: ";
                    echo $brand_id;
                endif;

                if ($brand_id) {
                    $scoc_lib->setBrand($product_id, $brand_id);
                }
            }
            //$scoc_lib->setBrand($product_id, '150');

            //var_dump("additional_info");
            if (isset($productXML->additional_info)) :
                $plugin = (string) $productXML->additional_info->plugin;
                $target_field = (string) $productXML->additional_info->target_field;
                $value = (string) $productXML->additional_info->value;

                if ($plugin === "advanced custom fields" && $target_field === "additional_info") :
                    try {
                        $scoc_lib->updateField_AdvancedCustomFields($product_id, $target_field, $value);
                    } catch (Exception $ex) {
                        $this->_print($sku . ' [updateField_AdvancedCustomFields] target_field: ' . $target_field . ' ' . $ex->getMessage());
                    }
                endif;
            endif;

            if (isset($productXML->specs)) :
                $plugin = (string) $productXML->specs->plugin;
                $target_field = (string) $productXML->specs->target_field;
                $value = (string) $productXML->specs->value;
                if ($plugin === "advanced custom fields" && $target_field === "specs") :
                    try {
                        $scoc_lib->updateField_AdvancedCustomFields($product_id, $target_field, $value);
                    } catch (Exception $ex) {
                        $this->_print($sku . ' [updateField_AdvancedCustomFields] target_field: ' . $target_field . ' ' . $ex->getMessage());
                    }
                endif;
            endif;

            
            // 3.3.2.0 Import Custom Columns
            if (isset($productXML->custom_columns)) :
                foreach ($productXML->custom_columns->custom_column as $ccXml) :
                    $plugin = (string) $ccXml->plugin;
                    $target_field = (string) $ccXml->target_field;
                    $value = (string) $ccXml->value;
                    
                    $this->_print($sku . ' Updating custom_column plugin=' . $plugin . ' target_field: '
                        . $target_field . ' value=' . $value);

                    if ($plugin === "advanced custom fields") :
                        try {
                            $updated = $scoc_lib->updateField_AdvancedCustomFields($product_id, $target_field, $value);
                            $this->_print(esc_html($value));
                            $this->_print($sku . ' Result: ' . $updated);
                        } catch (Exception $ex) {
                            $this->_print($sku . ' custom_columns [updateField_AdvancedCustomFields] '
                                    . 'target_field: ' . $target_field . ' ' . $ex->getMessage());
                        }
                    
                    elseif ($plugin === "woocommerce product attribute taxonomy") :
                        //$this->updateProductAttribute($woo_product, $target_field, $attr_data, $value);
                        $this->updateProductAttribute_Crud($woo_product, $target_field, $value, 0, 0);
                        unset($attr_data);
                    else:
                        /**
                         * For plugin "product-condition-for-woocommerce"
                         * For plugin "other_meta_fields"
                         * For plugin "extendons_product_condition"
                         * We use update_post_meta() only
                         * */
                        $result_update = update_post_meta(
                            $post_id,
                            $target_field,
                            esc_attr((string)$value)
                        );

                        $this->_print($sku . 'post_id=' . $post_id . ' result=' . $result_update);
                    endif;
                endforeach;
            endif;
            
            // mpn, upc, ean are from custom product field.
            // Plugin: Cart Product Feed Additional Product Fields
            // By ShoppingCartProductFeed; Remi Corson & Harsha Venkatesh
            if (isset($productXML->mpn)) :
                update_post_meta($post_id, '_cpf_mpn', esc_attr((string)$productXML->mpn));
            endif;
            if (isset($productXML->upc)) :
                update_post_meta($post_id, '_cpf_upc', esc_attr((string)$productXML->upc));
            endif;
            if (isset($productXML->ean)) :
                update_post_meta($post_id, '_cpf_ean', esc_attr((string)$productXML->ean));
            endif;
            
            // Warranty plugin update
            // Product Warranty: WooCommerce Warranty Requests
            if (isset($productXML->warranty)) :
                $product_warranty = array();
                $product_warranty_type = (string)$productXML->warranty->warranty_type;

                if ($product_warranty_type  == 'no_warranty') :
                    $product_warranty = array('type' => 'no_warranty');
                    update_post_meta($post_id, '_warranty', $product_warranty);
                elseif ($product_warranty_type == 'included_warranty') :
                        $product_warranty = array(
                        'type'      => 'included_warranty',
                        'length'    => (string) $productXML->warranty->included_warranty_length, // "lifetime" or "limited"
                        'value'     => (string) $productXML->warranty->limited_warranty_length_value, // numeric 1, 2 etc
                        'duration'  => (string) $productXML->warranty->limited_warranty_length_duration //"days","weeks","months","years"
                        );
                        update_post_meta($post_id, '_warranty', $product_warranty);
                endif;

                if (isset($productXML->warranty->label)) :
                    update_post_meta($post_id, '_warranty_label', stripslashes($productXML->warranty->label));
                endif;
            endif;
            
            if (isset($productXML->tabs)) :
                foreach ($productXML->tabs->tab as $tabXml) :
                    $tab_plugin = (string)$tabXml->tab_plugin;
                    $tab_title = (string)$tabXml->tab_title; //"What's In The Box";
                    $tab_content = (string)$tabXml->tab_content;

                    if ($tab_plugin === 'yikes_woocommerce_custom_product_tabs') :
                        $scoc_lib->updateField_YikesWooProductsTab($product_id, $tab_title, $tab_content);
                    endif;
                endforeach;
            endif;
            

            if (isset($productXML->related_products)) :
                $this->_print($sku . " Updating related_products");
                $upsell_ids = array();
                foreach ($productXML->related_products->sku as $skuXml) :
                    $related_sku = (string) $skuXml;
                    $related_product_id = $this->globalHelper->getProductIdBySku($related_sku);
                    
                    if ($related_product_id > 0) :
                        $upsell_ids[] = $related_product_id;
                    else :
                        $this->_print($sku . " product id against related sku " . $related_sku . " is not found");
                    endif;
                endforeach;
                
                //var_dump($upsell_ids);
                //update_post_meta($post_id, 'upsell_ids', serialize($upsell_ids));
                $woo_product->set_upsell_ids($upsell_ids);
                $this->_print($sku . " Updated related_products");
            endif;


            // Save the product data.
            /**
            * The moment product is saved, WC calls validate_props() ->sync_stock_status()
            * sync_stock_status() checks if $product->child_is_on_backorder() is true. If "true", 
            * parent stock_status is set to 'onbackorder'.
            * 
            * validate_props()
            * D:\xampp7.1.13\htdocs\wordpress532\wp-content\plugins\woocommerce\includes\class-wc-product-variable.php
            * 
            * sync_stock_status()
            * D:\xampp7.1.13\htdocs\wordpress532\wp-content\plugins\woocommerce\includes\data-stores
            * SELECT * FROM wp_wc_product_meta_lookup WHERE product_id IN (430,438)
            * */
            //$woo_product->save(); //commtented 7.10.1.0
            
            // for testing 7.10.1.0
            //update_post_meta($post_id, '_stock_status', $stock_status); // ok
            $woo_product = wc_get_product($product_id);
            

            $this->_print("2***After saving: backorders: " . $woo_product->get_backorders() 
                . " stock_status:" . $woo_product->get_stock_status());

            if ($action == 'insert') {
            } else {
                $this->_print($sku . " updated.");
                $this->_print("Existing product id '$product_id'");
            }

            

            //******************** @@ Productwise PROCESS IS COMPLETE ********************
            if ($product_type == 'variable') {
                $this->update_children($post_id);
            }


            //$woocommerce->clear_product_transients( $post_id );
            $this->xmllog->log($sku, "success", "");
            

            // And finally (optionally if needed)
            wc_delete_product_transients($product_id); // Clear/refresh the variation cache
        } //@ Each product in Xml

        $this->_print("-------- import process ends --------");
    }

    // raman
    // New method(CRUD)
    public function updateProductAttribute_Crud($product, $attr_name, $term_names = null, $position = 0, $is_variation = 0)
    {

        global $wpdb, $woocommerce;
        global $wp_taxonomies;

        if (strpos($attr_name, 'pa_') !== false) :
            $attr_name = substr($attr_name, 3);
        endif;
        
        // taxonomy = "pa_color"
        $attr_name_with_prefix = 'pa_' . $attr_name; // wc_attribute_taxonomy_name()
        $taxonomy = $attr_name_with_prefix;

        $product_id = $product->get_id();

        $existing_attributes = $product->get_attributes();
        //$this->_print("-------- existing attr. product data --------");
        //var_dump($existing_attributes);
        //var_dump($wp_taxonomies);

        //$existing_attributes = maybe_unserialize(get_post_meta($product_id, '_product_attributes', true));
        //$this->_print("-------- existing attr. meta data --------");
        //var_dump($existing_attributes);
        if (! taxonomy_exists($taxonomy)) :
            $this->_print("Taxonomy '" . $taxonomy . "' does not exist. Skipping attribute creation. ");
            
            // To create the taxonomy or global attribute dynamically
            //$this->create_taxonomy($taxonomy);
            return false;
        endif;

        $this->_print("Taxonomy '" . $taxonomy . "' attribute creation via updateProductAttribute_Crud(). ");

        
        if (!$existing_attributes) :
            $existing_attributes = array();
        endif;

        
        // Get an instance of the WC_Product_Attribute Object
        $attribute = new WC_Product_Attribute();

        $term_ids = array();

        if ($term_names) {
            $attribute_type = $wpdb->get_var(
                $wpdb->prepare("SELECT attribute_type FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s;", $attr_name)
            );

            $this->_print("attr_name= " . $attr_name .  " type= " . ($attribute_type==false? "" : $attribute_type));

            // Update post terms if taxonomy exists
            if (!$attribute_type) {
                return false;
            }

            $values_array = array();

            if ($attribute_type == 'text') {
                $values_array = array_map('stripslashes', array_map('strip_tags', explode('|', $term_names)));
            } elseif ($attribute_type == 'select') {
                $values_array = explode('|', $term_names);
            } else {
            }

            // Remove empty items in the array
            $values_array = array_filter($values_array, 'strlen');

            foreach ($values_array as $term_name) :
                if (term_exists($term_name, $taxonomy)) :
                    // Get and set the term ID in the array from the term name
                    $term_id = get_term_by('name', $term_name, $taxonomy)->term_id;
                    $this->_print("Term '" . $term_name . "' exists. ID: " . $term_id);
                    $term_ids[] = $term_id;
                else :
                    $term_slug = sanitize_title($term_name);

                    // Insert new term ie red, blue, small, large etc.
                    $this->_print("Term '" . $term_name . "' does not exist. Creating new.");
                    wp_insert_term($term_name, $taxonomy, array('slug' => $term_slug)); // Create the term
                    
                    $term_id = get_term_by('name', $term_name, $taxonomy)->term_id;
                    $this->_print("Term '" . $term_name . "' created. ID: " . $term_id);
                    $term_ids[] = $term_id;
                endif;
            endforeach;

            unset($values_array, $attribute_type);
        }

        
        $taxonomy_id = wc_attribute_taxonomy_id_by_name($taxonomy); // Get taxonomy ID

        $attribute->set_id($taxonomy_id);
        $attribute->set_name($taxonomy);
        $attribute->set_options($term_ids);
        $attribute->set_position($position);
        $attribute->set_visible(1);
        $attribute->set_variation($is_variation);

        $existing_attributes[$taxonomy] = $attribute; // Set in an array
        $product->set_attributes($existing_attributes);
        $product->save();
    }


    // This function may come handy when we need to create global attributes
    // dynamically.
    private function create_taxonomy($taxonomy)
    {
        // If taxonomy doesn't exists we create it (Thanks to Carl F. Corneil)
        if (!taxonomy_exists($taxonomy)) {
            register_taxonomy(
                $taxonomy,
                'product_variation',
                array(
                    'hierarchical' => false,
                    'label' => ucfirst($attribute),
                    'query_var' => true,
                    'rewrite' => array( 'slug' => sanitize_title($attribute) ), // The base slug
                )
            );
        }
    }


    // 2019-10-09
    // This method is replaced by updateProductAttribute_Crud()
    public function updateProductAttribute($product, $attr_name, $attr_data = null, $values = null)
    {
        global $wpdb, $woocommerce;

        $product_id = $product->get_id();
        /*
         * $attr_name must be pre-existing in woocommerce system/db
         */
        $this->_print('product_id id:' . $product_id . ' updateProductAttribute method');
        $res = get_post_meta($product_id);
        if (!$res) {
            $this->_print('post meta or attribute does not exist');
            //return FALSE;
        }
        
        //var_dump($res);
        //$existing_attributes= unserialize($res['_product_attributes'][0]);
        $existing_attributes = maybe_unserialize(get_post_meta($product_id, '_product_attributes', true));
        

        if (strpos($attr_name, 'pa_') !== false) :
            $attr_name = substr($attr_name, 3);
        endif;
        
        $attr_name_with_prefix = 'pa_' . $attr_name;
        $attr_data['name'] = $attr_name_with_prefix;
        
        
        if ($existing_attributes) {
            $this->_print('product has existing attributes');

            $found = array_key_exists($attr_name_with_prefix, $existing_attributes);

            if ($found == true) {
                $existing_attributes[$attr_name_with_prefix] = $attr_data;
            } else {
                $new = array($attr_name_with_prefix => $attr_data);
                $existing_attributes = array_merge($existing_attributes, $new);
                
                if ($this->verbose == 1) {
                    $this->_print('----------- Existing attributes merged -----------');
                    var_dump($existing_attributes);
                    
                    $this->_print('----------- New attribute -----------');
                    var_dump($new);
                }
            }
        } else {
            $new = array($attr_name_with_prefix => ($attr_data));
            $existing_attributes = $new; //array_merge($existing_attributes, $new);

            if ($this->verbose == 1) {
                var_dump($new);
            }
        }
        if ($this->verbose == 1) {
            var_dump($existing_attributes);
        }

        update_post_meta($post_id, '_product_attributes', $existing_attributes);

        if ($values) {
            $attribute_type = $wpdb->get_var(
                $wpdb->prepare("SELECT attribute_type FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s;", $attr_name)
            );

            // Update post terms if taxonomy exists
            if (!$attribute_type) {
                return false;
            }
            if ($attribute_type == 'text') {
                $values_array = array_map('stripslashes', array_map('strip_tags', explode('|', $values)));
            } elseif ($attribute_type == 'select') {
                $values_array = explode('|', $values);
            }

            // Remove empty items in the array
            $values_array = array_filter($values_array, 'strlen');
            wp_set_object_terms($post_id, $values_array, $attr_name_with_prefix, false);
            unset($values_array, $attribute_type);
        }



        //var_dump($existing_attributes);
        unset($existing_attributes);
        return true;
    }

    
    private function updateSingleVariation($parent_id, $variantXml)
    {
        $variation_data = array();
        $variation_data["attributes"] = array();
        
        foreach ($variantXml->option as $option) {
            $matrix_attribute = (string) $option['attribute'];
            $matrix_value = (string) $option['value'];
            $variation_data["attributes"][$matrix_attribute] = $matrix_value;
        }
        
        foreach ($variantXml->children() as $meta_key => $meta_data) {
            if ((string) $meta_key != 'option') {
                $variation_data[$meta_key] = (string) $meta_data;
            }
        }
        
        $variation_data["regular_price"] = isset($variantXml->regular_price)
            ? (float) $variantXml->regular_price : null;

        $variation_data["sale_price"] = isset($variantXml->sale_price)
        ? (string) $variantXml->sale_price : null;

        $variation_data["sale_price_dates_from"] = (isset($variantXml->sale_price_dates_from))
        ? (string) $variantXml->sale_price_dates_from : '';

        $variation_data["sale_price_dates_to"] = (isset($variantXml->sale_price_dates_to))
        ? (string) $variantXml->sale_price_dates_to : '';

        $variation_data["shipping_class_id"] = isset($variantXml->shipping_class_id)
        ? (int) $variantXml->shipping_class_id : 0;

        $variation_data["tax_class"] = (isset($variantXml->tax_class))
        ? (string) $variantXml->tax_class : '';

        $variation_data["weight"] = (isset($variantXml->weight))
            ? (string) $variantXml->weight : '';
        //var_dump($variation_data);
        
        /**
         * 7.9.0.0
        **/
        $variation_data["length"] = (isset($variantXml->length))
            ? (string) $variantXml->length : '';
        $variation_data["width"] = (isset($variantXml->width))
            ? (string) $variantXml->width : '';
        $variation_data["height"] = (isset($variantXml->height))
            ? (string) $variantXml->height : '';

        $this->_print("-------- importing VARIATION SKU " . $variation_data['sku'] . " starts --------");

        if (isset($variantXml->image)) :
            $variant_image_url = (string)$variantXml->image;
            $new_image_id =  $this->useExistingImageIdForVariant($variant_image_url);

            $variation_data["image_id"] = $new_image_id;
        endif;

       
        $this->variationHelper->updateSingleVariation($parent_id, $variation_data);
        $this->_print("---- Variation Done ----");
    }
    
    private function useExistingImageIdForVariant($variant_image_url)
    {
        /**
         * 7.14.0.0 Check $arrImageUrlWithId already holds the image url. Use the existing image Id
         * based on image url
         **/
        if(trim($variant_image_url) == "") :
            if ($this->verbose == 1) :
                $this->_print('Blank variation image ');
            endif;

            return '';
        endif;

        if (array_key_exists($variant_image_url, $this->arrImageUrlWithId)) :
            $new_image_id =  $this->arrImageUrlWithId[$variant_image_url];

            if ($this->verbose == 1) :
                $this->_print('Picking up variation image from existing. Image Id: ' . $new_image_id . '\r\n');
            endif;
        else :
            $new_image_id = $this->saveVariantImage($variant_image_url);

            if ($new_image_id > 0) :
                $this->arrImageUrlWithId[$variant_image_url] = $new_image_id;
            endif;

            if ($this->verbose == 1) :
                $this->_print('Downloaded variation image. New Image Id: ' . $new_image_id . '\r\n');
            endif;
        endif;

        return $new_image_id;
    }
    
    private function saveVariantImage($featuredImage)
    {
        $imageID = false;
        $this->_print("Importing variant image: " . $featuredImage);

        if (scoc_utility::isValidUrl($featuredImage)) :
            $imageID = $this->saveImageWithUrl($featuredImage);
            $this->_print("Generated image Id: " . (string) $imageID);

            if (!$imageID) :
                $this->_print("Variant image Id could not be generated");
            endif;
        else :
            $this->_print("Variant image url is invalid");
        endif;

        return $imageID;
    }

    private function update_children($post_id)
    {
        // Update parent if variable so price sorting works and stays in sync with the cheapest child
        $post_parent = $post_id;

        $children = get_posts(array(
            'post_parent' => $post_parent,
            'posts_per_page' => -1,
            'post_type' => 'product_variation',
            'fields' => 'ids',
            'post_status' => 'publish'
                ));

        $lowest_price = $lowest_regular_price = $lowest_sale_price = $highest_price = $highest_regular_price = $highest_sale_price = '';

        if ($children) {
            foreach ($children as $child) {
                $child_price = get_post_meta($child, '_price', true);
                $child_regular_price = get_post_meta($child, '_regular_price', true);
                $child_sale_price = get_post_meta($child, '_sale_price', true);

                if ($child_price === '' && $child_regular_price === '') {
                    continue;
                }

                // Regular prices
                if (!is_numeric($lowest_regular_price) || $child_regular_price < $lowest_regular_price) {
                    $lowest_regular_price = $child_regular_price;
                }

                if (!is_numeric($highest_regular_price) || $child_regular_price > $highest_regular_price) {
                    $highest_regular_price = $child_regular_price;
                }

                // Sale prices
                if ($child_price == $child_sale_price) {
                    if ($child_sale_price !== '' && (!is_numeric($lowest_sale_price) || $child_sale_price < $lowest_sale_price)) {
                        $lowest_sale_price = $child_sale_price;
                    }

                    if ($child_sale_price !== '' && (!is_numeric($highest_sale_price) || $child_sale_price > $highest_sale_price)) {
                        $highest_sale_price = $child_sale_price;
                    }
                }
            }

            $lowest_price = $lowest_sale_price === '' || $lowest_regular_price < $lowest_sale_price ? $lowest_regular_price : $lowest_sale_price;
            $highest_price = $highest_sale_price === '' || $highest_regular_price > $highest_sale_price ? $highest_regular_price : $highest_sale_price;
        }

        update_post_meta($post_parent, '_price', $lowest_price);
        update_post_meta($post_parent, '_min_variation_price', $lowest_price);
        update_post_meta($post_parent, '_max_variation_price', $highest_price);
        update_post_meta($post_parent, '_min_variation_regular_price', $lowest_regular_price);
        update_post_meta($post_parent, '_max_variation_regular_price', $highest_regular_price);
        update_post_meta($post_parent, '_min_variation_sale_price', $lowest_sale_price);
        update_post_meta($post_parent, '_max_variation_sale_price', $highest_sale_price);
    }

    public function importMini($feed_xml)
    {
        $parentProductIds = array();
        foreach ($GLOBALS as $key => $val) {
            global $$key;
            //echo $key;
        }

        $this->_print("-------- import process starts --------");

        foreach ($feed_xml->product as $productXML) :
            $sku = isset($productXML->sku) ? (string) $productXML->sku : null;
            $parent_sku = isset($productXML->parent_sku) ? (string) $productXML->parent_sku : '';
            $product_id = null;
            $variation_id = null;

            // 26 Dec 2017 - NO need to check parent sku/child sku.
            /*
            // If $parent_sku is empty, it is a normal product OR matrix parent.
            // Otherwise, this is a matrix child
            if ($parent_sku === ''):
            $product_id = $this->globalHelper->getProductIdBySku($sku);
            else:
            $parent_product_id = $this->globalHelper->getProductIdBySku($parent_sku);
            $this->_print("Parent product " . $parent_sku . " exists. id=" . $parent_product_id);

            $parentProductIds[] = $parent_product_id;

            if (!is_null($parent_product_id) && $parent_product_id > 0):
                // parent exists. Now get child id
                $product_id = $this->getChildId($parent_product_id, $sku);
                $this->_print("Child product " . $sku . " exists. id=" . $product_id);
                //var_dump("wc_get_product " . wc_get_product_id_by_sku($sku));
            endif;
            endif;
            //var_dump("----- PRODUCT ID-------- " . $product_id);
            //$this->xmllog->log( $sku, "error", 'Product id for SKU: ' . $product_id . ' not found' );
            */
            
            $product_id = wc_get_product_id_by_sku($sku);

            if ($product_id == null || $product_id === 0) {
                $this->xmllog->log($sku, "error", 'Product id for SKU: ' . $sku . ' not found');
                $this->_print('SKU: ' . $sku . ' Product id not found');
                continue;
            } else {
                $this->_print('SKU: ' . $sku . ' Product id: ' . $product_id);
            }

            $woo_product = wc_get_product($product_id);
            if (!$woo_product) :
                $this->xmllog->log($sku, "error", "product could not be loaded");
                continue;
            endif;

            if ($parent_sku !== '') :
                $variation_id = $product_id; //wc_get_product_id_by_sku($sku);
                
                if ($variation_id === null || $variation_id == 0) :
                    $this->xmllog->log($sku, "error", "Variation product could not be loaded");
                    continue;
                endif;
            endif;

            $product_modified = false;

            try {
                $product_type = $this->globalHelper->getProductType($product_id);
                $regular_price = (isset($productXML->price)) ? (float) $productXML->price : '';
                $date_from = (isset($productXML->sale_price_dates_from)) ? (string) $productXML->sale_price_dates_from : '';
                $date_to = (isset($productXML->sale_price_dates_to)) ? (string) $productXML->sale_price_dates_to : '';
                $sale_price = (isset($productXML->sale_price)) ? (float) $productXML->sale_price : '';
                $msrp_price = (isset($productXML->msrp_price)) ? (float) $productXML->msrp_price : '';

                /*if ($regular_price !== '') {
                    update_post_meta($product_id, '_regular_price', $regular_price);
                    update_post_meta($product_id, '_price', $regular_price);

                    $woo_product->set_price($regular_price);
                }

                if ($sale_price !== '') {
                    update_post_meta($product_id, '_sale_price', $sale_price);
                }*/

                $this->_print("Product type: " . $product_type);
                
                if ($regular_price !== '') :
                    $this->updatePrices($woo_product, $regular_price, $sale_price, $date_from, $date_to, $msrp_price);
                    $this->_print("Sku: " . $sku . " ProductId:" . $product_id . " pricing data updated.");
                endif;

                $stock = (isset($productXML->qty)) ? (int) $productXML->qty : null;
                ///$variation_id = wc_get_product_id_by_sku($variation_data['sku']);
                
                if ($stock !== null) :
                    if ($variation_id == null) :
                        $this->_print("Regular product stock update: " . $stock);
                        //update_post_meta( $product_id, '_stock_status', $stock > 0 ? 'instock' : 'outofstock'  );
                        //update_post_meta( $product_id, '_stock', $stock );
                        $woo_product->set_stock_quantity($stock);
                        $woo_product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
                        
                        $product_modified = true;
                        $this->_print("Product stock is being updated.");
                    else :
                        $this->_print("Product variant stock is being updated.");

                        $variation_obj = new WC_Product_variation($variation_id);
                        $variation_obj->set_stock_quantity($stock);
                        $variation_obj->save();

                        $this->_print("Variation stock updated to " . $stock);
                    endif;
                endif;

                
                if ($product_modified == true) :
                    // Save the product data.
                    $woo_product->save();
                    $this->_print("Sku: " . $sku . " ProductId:" . $product_id . " updated.");

                    // 7.8.0.0 ---------------------------------------------------------
                    // Delete transient data
                    if ($this->isDeleteTransient == 1) :
                        $this->_print("Deleting transient data");
                        wc_delete_product_transients($product_id);
                        if ($woo_product->get_parent_id('edit')) :
                            wc_delete_product_transients($woo_product->get_parent_id('edit'));
                        endif;
                    endif;
                    // ================================================================
                endif;

                
                $this->_print("============================================");

                $this->xmllog->log($sku, "success", "");
            } catch (Exception $ex) {
                $this->xmllog->log($sku, "error", $ex->getMessage());
                continue;
            }
        endforeach;

        // For parent products, we should verify children stock status
        $this->checkStockForParentProducts($parentProductIds);
        $this->_print("-------- import process ends --------");
    }

    private function updatePrices($product, $regular_price, $sale_price, $date_from, $date_to, $msrp_price)
    {
        $product_id = $product->get_id();

        // 24 march, 2017
        if ($msrp_price != '') :
            update_post_meta($product_id, '_msrp_price', $msrp_price);
        endif;

        $temp_date_from = '';
        $temp_date_to = '';
        $temp_price = $regular_price;
        
        if ($date_from !== '') {
            //update_post_meta($product_id, '_sale_price_dates_from', strtotime($date_from));
            $temp_date_from = strtotime($date_from);
        } else {
            //update_post_meta($product_id, '_sale_price_dates_from', '');
        }

        if ($date_to !== '') {
            //update_post_meta($product_id, '_sale_price_dates_to', strtotime($date_to));
            $temp_date_to = strtotime($date_to);
        } else {
            //update_post_meta($product_id, '_sale_price_dates_to', '');
        }

        if ($date_to !== '' && $date_from === '') {
            //update_post_meta($product_id, '_sale_price_dates_from', strtotime('NOW', current_time('timestamp')));
            $temp_date_from = strtotime('NOW', current_time('timestamp'));
        }

        // Update final price if on sale
        if ($sale_price != '' && $date_to == '' && $date_from == '') {
            //update_post_meta($product_id, '_price', stripslashes($sale_price));
            $temp_price = stripslashes($sale_price);
        } else {
            //update_post_meta($product_id, '_price', $regular_price);
            $temp_price = $regular_price;
        }

        if ($sale_price != '' && $date_from !== '' && strtotime($date_from) < strtotime('NOW', current_time('timestamp'))) {
            $this->_print("date_from is lower than NOW");
            //update_post_meta($product_id, '_price', stripslashes($sale_price));
            $temp_price = stripslashes($sale_price);
        }

        if ($date_to !== '' && strtotime($date_to) < strtotime('NOW', current_time('timestamp'))) {
            //update_post_meta($product_id, '_price', stripslashes($regular_price));
            //update_post_meta($product_id, '_sale_price_dates_from', '');
            //update_post_meta($product_id, '_sale_price_dates_to', '');
            $this->_print("date_to is lower than NOW");
            $temp_price = stripslashes($regular_price);
            $temp_date_from = '';
            $temp_date_to = '';
        } //@
        
        //if ($product = wc_get_product($product_id)) :
        if ($product) :
            $this->_print("regular price : " . $regular_price);
            $this->_print("sale price : " . $sale_price);
            $this->_print("*final price : " . $temp_price);
            $this->_print("date from : " . ($temp_date_from === ''? '' : date('Y-m-d', $temp_date_from)));
            $this->_print("date to : " . ($temp_date_to === ''? '' : date('Y-m-d', $temp_date_to)));
                        
            $product->set_regular_price($regular_price);
            $product->set_sale_price($sale_price);
            
            $product->set_price($temp_price); // Final Price
            $product->set_date_on_sale_from($temp_date_from);
            $product->set_date_on_sale_to($temp_date_to);
            
            $product->save();
        endif;
    }

    public function checkStockForParentProducts($parentProductIds)
    {
        $total_stock = 0;
        if (count($parentProductIds) == 0) {
            return;
        }

        $parentProductIds = array_unique($parentProductIds);
        //var_dump($parentProductIds); exit;
        
        foreach ($parentProductIds as $parentProductId) :
            // Process each parent product Id
            $woo_product = new WC_Product($parentProductId);
            $total_stock = max(0, $woo_product->get_stock_quantity());

            $this->_print("total stock for matrix parent " . $total_stock);

            $children = get_posts(
                array(
                    'post_parent' => $parentProductId,
                    'posts_per_page' => -1,
                    'post_type' => 'product_variation',
                    'fields' => 'ids',
                    'post_status' => 'publish'
                )
            );
        
            //var_dump($children); exit;
            if ($children) {
                foreach ($children as $key => $child_id) :
                    $stock = get_post_meta($child_id, '_stock', true);

                    if ($this->verbose == 1) :
                        $child_sku = get_post_meta($child_id, '_sku', true);
                        $this->_print("Child SKU " . $child_sku . " stock: " . $stock);
                    endif;
                    //$woo_product = new WC_Product( $child_id );
                    //$childStock += $woo_product->get_stock_quantity();
                    if ('yes' === get_post_meta($child_id, '_manage_stock', true)) {
                        $total_stock += max(0, $stock);
                    }
                endforeach;
            }

            if ($this->verbose == 1) {
                var_dump($children);
                var_dump($total_stock);
            }

            //$woo_product->set_stock($total_stock, 'set');
            $woo_product->set_stock_quantity($total_stock);
            $woo_product->set_stock_status($total_stock > 0 ? 'instock' : 'outofstock');
            unset($woo_product);
        endforeach;
    }

    /// V1.1
    // 5.0.0.0
    public function importShipping($feed_xml)
    {
        foreach ($GLOBALS as $key => $val) {
            global $$key;
        }

        $this->_print("-------- Shipping Import process starts --------");

        foreach ($feed_xml->order as $shippingXML) :
            $woo_order_id = isset($shippingXML->orderid) ? (string) $shippingXML->orderid : null; // Mandatory field

            $post_id =(isset($shippingXML->post_id) && strlen((string) $shippingXML->post_id) > 0)
                    ? (string) $shippingXML->post_id : null; // Optional
            $order_key =(isset($shippingXML->order_key) && strlen((string) $shippingXML->order_key) > 0)
                    ? (string) $shippingXML->order_key : null; // Optional

            $carrier_code = isset($shippingXML->carrier_code) 
                ? (string) $shippingXML->carrier_code : null;
            $tracking_number = isset($shippingXML->tracking_number) 
                ? (string) $shippingXML->tracking_number : null;

            $shipment_date = isset($shippingXML->shipment_date) 
                ? (string) $shippingXML->shipment_date : null;

            $new_order_status = isset($shippingXML->new_order_status) 
                ? (string) $shippingXML->new_order_status : "";

            $status_note = isset($shippingXML->status_note) 
                ? (string) $shippingXML->status_note : "";

            $unconditional_update = isset($shippingXML->unconditional_update) 
                ? (int) $shippingXML->unconditional_update : 0;


            $order_id = 0;
            
            if ($post_id !== null) :
                $this->_print("Retrieve order from post_id " . $post_id);
                $order_id = $post_id;
            elseif ($order_key !== null) :
                $this->_print("Retrieve order from key " . $order_key);
                $order_id = wc_get_order_id_by_order_key($order_key);
                if ($order_id == null || $order_id == 0) :
                    $order_id = 0;
                endif;

                if ($order_id == 0 || $order_id == null) :
                    $this->_print($woo_order_id . " could not be loaded by key: " . $order_key);
                    $this->xmllog->log($woo_order_id, "error", $woo_order_id . " could not be loaded by key: " . $order_key);
                    continue;
                endif;
            elseif ($woo_order_id !== null) :
                $this->_print("Retrieve order from order number");
                // Convert order number to order id, if possible
                $order_id = $this->globalHelper->getOrderIDFromOrderNumber($woo_order_id);

                if ($order_id == 0 || $order_id == null) :
                    $this->_print("order " . $woo_order_id . " not found");
                    $this->xmllog->log($woo_order_id, "error", "order " . $woo_order_id . " not found");
                    continue;
                endif;
            endif;
            
            $this->_print("Processing Order ID " . $order_id);

            if (empty($order_id) || $order_id == 0) {
                $this->_print("ORDER " . $woo_order_id . " is missing in XML");
                continue;
            }
            
            if (empty($tracking_number)) {
                $this->_print("Tracking Number is missing in XML");
                continue;
            }
            
            if (!class_exists('WC_Shipment_Tracking')) :
                $this->_print($woo_order_id . " failed. WC_Shipment_Tracking does not exist. ");
                $this->xmllog->log($woo_order_id, "error", "WC_Shipment_Tracking does not exist. ");
                continue;
            endif;

            if ($order_id == 0) :
                $this->_print($woo_order_id . " failed.");
                $this->xmllog->log($woo_order_id, "error", "order " . $woo_order_id . " does not exist");
                continue;
            endif;

            $order = wc_get_order($order_id);
            if ($order == null) :
                $this->_print($woo_order_id . " does not exist");
                $this->xmllog->log($woo_order_id, "error", $woo_order_id . " does not exist");
                continue;
            endif;

            $current_status = strtolower($order->get_status());
            
            // 7.12.3.0 for <unconditional_update> = 1, no need to verify order status
            if($unconditional_update == 0):
                if (!($current_status == 'processing' || $current_status == 'wc-processing')) :
                    // 7.5.0.0
                    $this->_print($woo_order_id . " Cannot update tracking. Status=" . $current_status);
                    $this->xmllog->log(
                        $woo_order_id,
                        "error",
                        "order " . $woo_order_id . " Cannot update tracking. Status=" . $current_status
                    );
                    continue;

                endif;
            endif;

            $scocShipping = new scoc_shipping($this->verbose);

            $custom_url = "";
            
            if ($scocShipping->addTrackingToOrder(
                $order_id,
                $tracking_number,
                $carrier_code,
                $shipment_date,
                $custom_url,
                $new_order_status,
                $status_note
            )) :
                $this->_print($woo_order_id . " updated.");
                $this->xmllog->log($woo_order_id, "success", "");
            else :
                $this->_print($woo_order_id . " failed.");
                $this->xmllog->log($woo_order_id, "error", "");
            endif;
            unset($scocShipping);
        endforeach;

        $this->_print("-- shiping import process ends --");
    }

    public function importCancelOrders($feed_xml)
    {
        foreach ($GLOBALS as $key => $val) {
            global $$key;
        }

        $this->_print("-------- Order Cancel process starts --------");
        $woo_order_id = isset($feed_xml->orderid) ? (string) $feed_xml->orderid : null; // Mandatory field
        $post_id =( isset($feed_xml->post_id) && strlen((string) $feed_xml->post_id) > 0) ? (string) $feed_xml->post_id : null; // Optional
        $order_key =( isset($feed_xml->order_key) && strlen((string) $feed_xml->order_key) > 0) ? (string) $feed_xml->order_key : null; // Optional

        $status_note = isset($feed_xml->status_note) ? (string) $feed_xml->status_note : "";

        $order_id = 0;
        
        // Priority given to key to load proper order
        if ($post_id !== null) :
            $this->_print("Retrieve order from post_id " . $post_id);
            $order_id = $post_id;
        elseif ($order_key !== null) :
            $this->_print("Retrieve order from key " . $order_key);
            $order_id = wc_get_order_id_by_order_key($order_key);
            if ($order_id == null || $order_id == 0) :
                $order_id = 0;
            endif;
            
            if ($order_id == 0 || $order_id == null) :
                $this->_print($woo_order_id . " could not be loaded by key: " . $order_key);
                $this->xmllog->log($woo_order_id, "error", $woo_order_id . " could not be loaded by key: " . $order_key);
                return false;
            endif;
        elseif ($woo_order_id !== null) :
            $this->_print("Retrieve order from order number");
            // Convert order number to order id, if possible
            $order_id = $this->globalHelper->getOrderIDFromOrderNumber($woo_order_id);
        
            if ($order_id == 0 || $order_id == null) :
                $this->_print("order " . $woo_order_id . " not found");
                $this->xmllog->log($woo_order_id, "error", "order " . $woo_order_id . " not found");
                return false;
            endif;
        endif;
        
        
        $order = wc_get_order($order_id);
        if (!$order) :
            $this->xmllog->log($woo_order_id, "error", $woo_order_id . " could not be loaded");
            $this->_print($woo_order_id . " could not be loaded");
            return false;
        endif;

        $result = $order->cancel_order($status_note);
        $this->_print($woo_order_id . " updated.");
        $this->xmllog->log($woo_order_id, "success", "");
        unset($order);
    }

    public function importRefundOrders($feed_xml)
    {
        // \wp-content\plugins\woocommerce\includes\wc-order-functions.php
        // wc_create_refund( $args = array() )
        //wc_order_fully_refunded();

        foreach ($GLOBALS as $key => $val) {
            global $$key;
        }

        $this->_print("-------- Order refund process starts --------");
        $woo_order_id = isset($feed_xml->orderid) ? (string) $feed_xml->orderid : null;
        $post_id =( isset($feed_xml->post_id) && strlen((string) $feed_xml->post_id) > 0) ? (string) $feed_xml->post_id : null; // Optional
        $order_key =( isset($feed_xml->order_key) && strlen((string) $feed_xml->order_key) > 0) ? (string) $feed_xml->order_key : null; // Optional

        $is_full_refund = isset($feed_xml->is_full_refund) ? (int) $feed_xml->is_full_refund : null;
        $refund_reason = isset($feed_xml->reason) ? (string) $feed_xml->reason : null;
        
        $order_id = 0;
        
        if ($post_id !== null) :
            $this->_print("Retrieve order from post_id " . $post_id);
            $order_id = $post_id;
        elseif ($order_key !== null) :
            $this->_print("Retrieve order from key " . $order_key);
            $order_id = wc_get_order_id_by_order_key($order_key);
            if ($order_id == null || $order_id == 0) :
                $order_id = 0;
            endif;
        elseif ($woo_order_id !== null) :
            $this->_print("Retrieve order from order number");
            // Convert order number to order id, if possible
            $order_id = $this->globalHelper->getOrderIDFromOrderNumber($woo_order_id);
        
            if ($order_id == 0 || $order_id == null) :
                $this->_print("order " . $woo_order_id . " not found");
                $this->xmllog->log($woo_order_id, "error", "order " . $woo_order_id . " not found");
                return false;
            endif;
        endif;
        
        if ($order_id == 0) :
            $this->_print($woo_order_id . " failed.");
            $this->xmllog->log($woo_order_id, "error", "order " . $woo_order_id . " does not exist");
            return false;
        endif;


        if ($order_id > 0) :
            try {
                $refund = null;
                // process refund for order
                if ($is_full_refund === 1) :
                    $this->_print($woo_order_id . " processing full refund.");
                    $refund = $this->globalHelper->ibenic_wc_refund_order(
                        $order_id, $refund_reason, true
                    );
                else :
                    // PARTIAL REFUND
                    $this->_print($woo_order_id . " processing partial refund.");
                    $other_amount = isset($feed_xml->other_amount) ? floatval($feed_xml->other_amount) : 0.0;
                    
                    $my_items = array();
                    if (isset($feed_xml->items)) :
                        foreach ($feed_xml->items->item as $itemXml) :
                            $sku = (string)$itemXml->sku;
                            $qty = (int)$itemXml->qty;
                            $product_id = wc_get_product_id_by_sku($sku);
                            $my_items[$product_id] = $qty;
                        endforeach;
                    endif;

                    //var_dump($my_items);
                    $this->_print($woo_order_id . " Item count = " . count($my_items));
                    $this->_print($woo_order_id . " other_amount = " . $other_amount);

                    $refund = $this->globalHelper->ibenic_wc_refund_order(
                        $order_id, $refund_reason, false, $other_amount, $my_items
                    );

                    unset($my_items);
                endif;
                
                
                if ($refund !== null) :
                    // if refund is successful, we create json string on amount refunded etc.
                    $refund_response_array = array(
                        'id'=> $refund->get_id(), 
                        'amount' => floatval($refund->get_amount())
                    );

                    $refund_response_json = json_encode($refund_response_array);

                    $this->_print($woo_order_id . " updated.");
                    $this->xmllog->log($woo_order_id, "success", "", "", $refund_response_json);
                endif;
            } catch (Exception $ex) {
                $this->xmllog->log($woo_order_id, "error", $ex->getMessage());
                return false;
            }
        endif;
        
        return true;
    }

    
    public function importImages($feed_xml)
    {
        foreach ($GLOBALS as $key => $val) :
            global $$key;
        endforeach;

        $this->_print("---------------------------------------------");
        $this->_print("-------- Image import process starts --------");

        foreach ($feed_xml->product as $productXML) :
            $sku = isset($productXML->sku) ? (string) $productXML->sku : null;
            $parent_sku = isset($productXML->parent_sku) ? (string) $productXML->parent_sku : '';
            $product_id = null;
            $variation_id = null;


            // If $parent_sku is empty, it is a normal product OR matrix parent.
            // Otherwise, this is a matrix child
            $product_id = $this->globalHelper->getProductIdBySku($sku);
            //var_dump($product_id);
            //$this->xmllog->log( $sku, "error", 'Product id for SKU: ' . $product_id . ' not found' );

            if ($product_id == null || $product_id === 0) :
                $this->xmllog->log($sku, "error", 'Product id for SKU: ' . $sku . ' not found');
                $this->_print('SKU: ' . $sku . ' Product id not found');
                continue;
            endif;

            $woo_product = wc_get_product($product_id);
            
            if (!$woo_product) :
                $this->xmllog->log($sku, "error", "product could not be loaded");
                continue;
            endif;


            if ($parent_sku !== '') :
                $variation_id = $product_id; //wc_get_product_id_by_sku($sku);
                
                if ($variation_id === null || $variation_id == 0) :
                    $this->xmllog->log($sku, "error", "Variation product could not be loaded");
                    continue;
                endif;
            endif;


            try {
                //$woo_product = new WC_Product( $product_id );
                if ($variation_id == null || $variation_id == 0) :
                    // NORMAL OR PARENT PRODUCT
                    if (isset($productXML->images)) :
                        
                        $this->delete_product_images( $product_id ); // 7.17.0.0

                        foreach ($productXML->images->image as $image) :
                            if (isset($image['type']) && (string) $image['type'] == 'featured') :
                                $this->saveFeaturedImage($woo_product, (string) $image);
                            endif;
                        endforeach;

                        $this->saveProductGallery($woo_product, $productXML);

                        $woo_product->save(); // 7.1.1.0

                        $this->_print("SKU: " . $sku . " ProductId:" . $product_id . " updated.");
                        $this->xmllog->log($sku, "success", "");
                    endif;
                else :
                    // VARIATION CHILD IMAGE - ONE IMAGE per product
                    $variation = new WC_Product_Variation($variation_id);
                    if (isset($productXML->images)) :
                        foreach ($productXML->images->image as $image) :
                            $variant_image_url = (string)$image;
                            $new_image_id =  $this->useExistingImageIdForVariant($variant_image_url);
                            
                            
                            $variation->set_image_id($new_image_id);
                            $variation->save(); // Save the data

                            $this->_print("Variation sku: " . $sku . " ProductId:" . $variation_id . " updated.");
                            $this->xmllog->log($sku, "success", "");
                        
                        endforeach;
                    endif;
                endif;
            } catch (Exception $ex) {
                $this->xmllog->log($sku, "error", $ex->getMessage());
                continue;
            }
        endforeach;

        $this->_print("-------- import process ends --------");
    }

    public function importDescriptions($feed_xml)
    {
        foreach ($GLOBALS as $key => $val) :
            global $$key;
        endforeach;

        $this->_print("---------------------------------------------");
        $this->_print("-------- Description import process starts --------");

        foreach ($feed_xml->product as $productXML) :
            $sku = isset($productXML->sku) ? (string) $productXML->sku : null;
            $parent_sku = isset($productXML->parent_sku) ? (string) $productXML->parent_sku : '';
            $product_id = null;

            // If $parent_sku is empty, it is a normal product OR matrix parent.
            // Otherwise, this is a matrix child
            $product_id = $this->globalHelper->getProductIdBySku($sku);
            //var_dump($product_id);
            //$this->xmllog->log( $sku, "error", 'Product id for SKU: ' . $product_id . ' not found' );

            if ($product_id == null || $product_id === 0) :
                $this->xmllog->log($sku, "error", 'Product id for SKU: ' . $sku . ' not found');
                $this->_print('SKU: ' . $sku . ' Product id not found');
                continue;
            endif;

            $woo_product = wc_get_product($product_id);

            try {
                //$woo_product = new WC_Product( $product_id );
                $short_desc = isset($productXML->short_description) ? (string) $productXML->short_description : null;
                $description = isset($productXML->description) ? (string) $productXML->description : null;

                //$_post = get_post($product_id, ARRAY_A);
                if ($woo_product) :
                    //$_post['post_title'] = $name;
                    if ($short_desc !== null) :
                        //$_post['post_excerpt'] = $short_desc;
                        $woo_product->set_short_description($short_desc);
                    endif;
                    
                    if ($description !== null) :
                        //$_post['post_content'] = $description;
                        $woo_product->set_description($description);
                    endif;

                    //wp_update_post($_post);
                    $woo_product->save();

                    $this->_print("SKU: " . $sku . " ProductId:" . $product_id . " updated.");
                    $this->xmllog->log($sku, "success", "");
                endif;
            } catch (Exception $ex) {
                $this->xmllog->log($sku, "error", $ex->getMessage());
                continue;
            }
        endforeach;

        $this->_print("-------- import process ends --------");
    }


    // 5.0.0.0
    public function importTitles($feed_xml)
    {
        foreach ($GLOBALS as $key => $val) :
            global $$key;
        endforeach;

        $this->_print("---------------------------------------------");
        $this->_print("-------- Title import process starts --------");

        foreach ($feed_xml->product as $productXML) :
            $sku = isset($productXML->sku) ? (string) $productXML->sku : null;
            $parent_sku = isset($productXML->parent_sku) ? (string) $productXML->parent_sku : '';
            $product_id = null;

            // If $parent_sku is empty, it is a normal product OR matrix parent.
            // Otherwise, this is a matrix child
            $product_id = $this->globalHelper->getProductIdBySku($sku);
            //var_dump($product_id);
            //$this->xmllog->log( $sku, "error", 'Product id for SKU: ' . $product_id . ' not found' );

            if ($product_id == null || $product_id === 0) :
                $this->xmllog->log($sku, "error", 'Product id for SKU: ' . $sku . ' not found');
                $this->_print('SKU: ' . $sku . ' Product id not found');
                continue;
            endif;

            $woo_product = wc_get_product($product_id);

            try {
                //$woo_product = new WC_Product( $product_id );
                $name = isset($productXML->name) ? (string) $productXML->name : null;

                //$_post = get_post($product_id, ARRAY_A);
                if ($woo_product) :
                    if ($name !== null) :
                        //$_post['post_title'] = $name;
                        $woo_product->set_name($name);
                    endif;

                    //wp_update_post($_post);
                    $woo_product->save();

                    $this->_print("Sku: " . $sku . " ProductId:" . $product_id . " updated.");
                    $this->xmllog->log($sku, "success", "");
                endif;
            } catch (Exception $ex) {
                $this->xmllog->log($sku, "error", $ex->getMessage());
                continue;
            }
        endforeach;

        $this->_print("-------- import process ends --------");
    }

    // 5.0.0.0
    public function importCategories($feed_xml)
    {
        foreach ($GLOBALS as $key => $val) :
            global $$key;
        endforeach;

        $this->_print("---------------------------------------------");
        $this->_print("-------- Category import process starts --------");

        foreach ($feed_xml->product as $productXML) :
            $sku = isset($productXML->sku) ? (string) $productXML->sku : null;
            $parent_sku = isset($productXML->parent_sku) ? (string) $productXML->parent_sku : '';
            $product_id = null;

            // If $parent_sku is empty, it is a normal product OR matrix parent.
            // Otherwise, this is a matrix child
            $product_id = $this->globalHelper->getProductIdBySku($sku);
            //var_dump($product_id);
            //$this->xmllog->log( $sku, "error", 'Product id for SKU: ' . $product_id . ' not found' );

            if ($product_id == null || $product_id === 0) :
                $this->xmllog->log($sku, "error", 'Product id for SKU: ' . $sku . ' not found');
                $this->_print('SKU: ' . $sku . ' Product id not found');
                continue;
            endif;

            $woo_product = wc_get_product($product_id);

            try {
                if (isset($productXML->categories)) :
                    $categories = array();
                    $root_category_id = $this->globalHelper->getRootCategoryId();
                    if ($root_category_id !== null) {
                        $categories[$root_category_id] = $root_category_id;
                    }

                    $i = 1;
                    foreach ($productXML->categories->categoryid as $category_id) {
                        $categories[$i] = (int) $category_id;
                        $i++;
                    }
                    
                    //var_dump($categories);
                    //wp_set_post_terms($product_id, $categories, 'product_cat');
                    $woo_product->set_category_ids($categories);
                    $woo_product->save();

                    $this->_print("SKU: " . $sku . " ProductId:" . $product_id . " updated.");
                    $this->xmllog->log($sku, "success", "");
                
                    unset($categories);
                endif;
            } catch (Exception $ex) {
                $this->xmllog->log($sku, "error", $ex->getMessage());
                continue;
            }
        endforeach;

        $this->_print("-------- import process ends --------");
    }

    public function importMetas($feed_xml)
    {
        foreach ($GLOBALS as $key => $val) :
            global $$key;
        endforeach;

        $this->_print("---------------------------------------------");
        $this->_print("-------- Title import process starts --------");

        foreach ($feed_xml->product as $productXML) :
            $sku = isset($productXML->sku) ? (string) $productXML->sku : null;
            $parent_sku = isset($productXML->parent_sku) ? (string) $productXML->parent_sku : '';
            $product_id = null;

            // If $parent_sku is empty, it is a normal product OR matrix parent.
            // Otherwise, this is a matrix child
            $product_id = $this->globalHelper->getProductIdBySku($sku);

            if ($product_id == null || $product_id === 0) :
                $this->xmllog->log($sku, "error", 'Product id for SKU: ' . $sku . ' not found');
                $this->_print('SKU: ' . $sku . ' Product id not found');
                continue;
            endif;

            $woo_product = wc_get_product($product_id);

            $tags = null;
            try {
                // product tags, comma delimited
                if (isset($productXML->tags) && (string) $productXML->tags != '') {
                    $tags = (string) $productXML->tags;
                    $tags = explode(',', $tags);
                }

                //$_post = get_post($product_id, ARRAY_A);
                if ($woo_product) :
                    if ($tags !== null) :
                        wp_set_object_terms($product_id, $tags, 'product_tag'); // this should work in CRUD
                    endif;

                    //$woo_product->save();
                    $this->_print("Sku: " . $sku . " ProductId:" . $product_id . " updated.");
                    $this->xmllog->log($sku, "success", "");
                endif;
            } catch (Exception $ex) {
                $this->xmllog->log($sku, "error", $ex->getMessage());
                continue;
            }
        endforeach;

        $this->_print("-------- import process ends --------");
    }

    private function _print($s)
    {
        if ($this->verbose == 1) {
            echo '<li>' . $s . '</li>';
        }
        //$this->log->write($s);
        if ($this->currentJobId != null && $this->currentJobId !== 0) :
            $this->globalHelper->updateJobStackTraceConcatenated($this->currentJobId, $s);
        endif;
    }

    private function _printError($s)
    {
        if ($this->verbose == 1) {
            echo '<li style="color:red;font-family:serif,tahoma,verdana,arial;font-size:0.85em">' . $s . '</li>';
        }
        $this->log->write($s);
    }

    protected function userCSVDataAsArray($data)
    {
        //return explode( ',', str_replace( " ", "", $data ) );
        return explode(',', trim($data));
    }

    private function _saveTaxonomyAttributes($post_id, $sku, $productXML)
    {
        global $wpdb;
        if (!isset($productXML->attributes)) {
            return false;
        }

        $attributes = array();
        $position = 0;

        foreach ($productXML->attributes->attribute as $attribute) :
            $code = (string) $attribute->code;
            $value = (string) $attribute->value;

            $attribute_type = $wpdb->get_var(
                $wpdb->prepare("SELECT attribute_type FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s;", $code)
            );

        // Update post terms if taxonomy exists
            if (!$attribute_type) {
                return false;
            }

            if ($attribute_type == 'text') {
                $values_array = array_map('stripslashes', array_map('strip_tags', explode('|', $value)));
            } elseif ($attribute_type == 'select') {
                $values_array = explode('|', $value);
            }

        // Remove empty items in the array
            $values_array = array_filter($values_array, 'strlen');
            wp_set_object_terms($post_id, $values_array, 'pa_' . $code, false);

            if ($values_array) {
                // Add attribute to array, but don't set values
                $attributes[sanitize_title('pa_' . $code)] = array(
                    'name' => wc_clean('pa_' . $code),
                    'value' => '',
                    'position' => $position,
                    'is_visible' => 1,
                    'is_variation' => 0,
                    'is_taxonomy' => 1
                );
            }

            $this->_print("$sku : Attribute: $code VALUE: $value");
        endforeach;

        //var_dump($attributes);
        update_post_meta($post_id, '_product_attributes', $attributes);

        return true;
    }

    protected function saveImageWithUrl($image)
    {
        $attach_id = false;
        $upload_dir = wp_upload_dir();

        if (isset($upload_dir) && isset($upload_dir["path"])) :
            $this->_print("saveImageWithUrl[] wp_upload_dir: " . $upload_dir['path']);
        else :
            if ($this->verbose == 1) :
                echo "<br />upload_dir array<br />";
                var_dump($upload_dir);
            endif;
        endif;

        /* !version1.2.1 */
        /*
          use curl to get image instead of
          $image_data = file_get_contents($image);
         */

        $ch = curl_init();
        $timeout = 120; // old 0
        // curl set options
        curl_setopt($ch, CURLOPT_URL, $image);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);


        // Getting binary data
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

        // Added 2017-10-13
        //Timeout if the file doesn't download after 20 seconds.
        curl_setopt($ch, CURLOPT_TIMEOUT, 420);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // added for testing
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.1 Safari/537.11'
        );


        $image_data = curl_exec($ch);
        $rescode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        //get the filename
        $filename = basename($image);

        // 5.0.0.5
        $upload_dir_path = str_replace("\\", "/", $upload_dir['path']);

        //create the dir or take the current one
        if (wp_mkdir_p($upload_dir_path)) {
            $file = $upload_dir_path . '/' . $filename;
        } else {
            //$file = $upload_dir['basedir'] . '/' . $filename;
            $file = str_replace("\\", "/", $upload_dir['basedir']) . '/' . $filename;
        }

        $this->_print("saveImageWithUrl[] Store file to disk: " . $file);

        $result_put_content = file_put_contents($file, $image_data);
        $this->_print("result_put_content = " . $result_put_content);

        if ($result_put_content !== false && $result_put_content > 0) {
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            if ($this->verbose == 1) :
                echo "<br />attachment array<br />";
                var_dump($attachment);
            endif;
            //var_dump($file);
            //var_dump($image_data);

            $attach_id = wp_insert_attachment($attachment, $file); //,$this->body['ID'] );
            $this->_print("saveImageWithUrl[] New attachment ID: " . $attach_id);

            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attach_data = wp_generate_attachment_metadata($attach_id, $file);
            wp_update_attachment_metadata($attach_id, $attach_data);
        }
        return $attach_id;
    }

    public function saveFeaturedImage($product, $featuredImage)
    {
        $imageID = false;
        $this->_print("Importing featured image: " . $featuredImage);

        if (scoc_utility::isValidUrl($featuredImage)) :
            $imageID = $this->saveImageWithUrl($featuredImage);
            $this->_print("Generated image Id: " . (string) $imageID);

            if ($imageID) :
                //set_post_thumbnail($post_id, $imageID);
                $product->set_image_id($imageID);
            else :
                $this->_print("Featured image ID could not be generated");
            endif;
        else :
            $this->_print("Featured image url is invalid");
        endif;

        return $imageID;
    }

    public function saveProductGallery($product, $productXml)
    {
        $gallery = [];

        $this->_print("Importing gallery image(s):::::::::::::::::::::::::::::::");

        foreach ($productXml->images->image as $image) {
            if (isset($image['type']) && (string) $image['type'] == 'featured') {
                // do nothing
            } else {
                $image = (string) $image;
                $this->_print("Processing gallery image: " . $image);

                if (scoc_utility::isValidUrl($image)) {
                    $imageID = $this->saveImageWithUrl($image);
                    $this->_print("Generated image Id: " . (string) $imageID);
                    
                    if ($imageID) {
                        $gallery[] = $imageID;
                    }
                } else {
                    $this->_print("Image url is invalid");
                }
            }
        }

        if (count($gallery) > 0) {
            $gallery_ids = implode(',', $gallery);
            //update_post_meta($post_id, '_product_image_gallery', $meta_value);
            $this->_print("Finally image ids" . (string) $gallery_ids);

            /** $product->set_gallery_image_ids($gallery_ids); **/
            $product->set_gallery_image_ids($gallery);
        }

        $this->_print("Importing gallery image(s)... Done:::::::::::::::::::::::::");
    }
    

    function delete_product_images( $product_id ) {
        
        $this->_print("delete_product_images product_id=" . (string)$product_id );

        // Check if user has the capability to delete products
        if ( !current_user_can( 'delete_products' ) ) {
            $this->_print("current user cannot delete this product");
            //return;
        }

        $product = wc_get_product( $product_id );
     
        if ( !$product ) {
            return;
        }
     
        $featured_image_id = $product->get_image_id();
        $image_galleries_id = $product->get_gallery_image_ids();
     
        if( !empty( $featured_image_id ) ) {
            $this->_print("deleting featured_image_id " . (string)$featured_image_id);

            $is_featured_image_used = $this->is_image_used( $featured_image_id, $product_id );
            if ( !$is_featured_image_used ) {
                if(wp_delete_attachment( $featured_image_id, true ))
                    $this->_print("deleted featured image");
            }
            else {
                $this->_print("featured image is already used by other products");
            }
        }
     
        if( !empty( $image_galleries_id ) ) {
            foreach( $image_galleries_id as $single_image_id ) {
                
                $this->_print("deleting gallery image single_image_id=" . $single_image_id);

                $is_image_used = $this->is_image_used( $single_image_id, $product_id );
                
                if ( !$is_image_used ) {
                    if(wp_delete_attachment( $single_image_id, true ))
                        $this->_print("deleted gallery image");
                }
                else {
                    $this->_print("gallery image is already used by other products");
                }
            }
        }

    }


    function is_image_used( $image_id, $current_product_id ) {
        $query = new WP_Query( array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_thumbnail_id',
                    'value' => $image_id,
                    'compare' => '='
                ),
                array(
                    'key' => '_product_image_gallery',
                    'value' => '"'.$image_id.'"',
                    'compare' => 'LIKE'
                )
            ),
            'post__not_in' => array( $current_product_id ),
            'fields' => 'ids',
            'posts_per_page' => -1
        ) );
     
        return ( $query->have_posts() );
    }

    private function _linkAllVariations($post_id, $variations_input_array, $meta_data)
    {
        global $wpdb, $woocommerce;

        
        $variations = array();
        foreach ($variations_input_array as $attribute_slug_lowercased => $matrix_value) {
            $variations['attribute_pa_' . str_replace(" ", "-", $attribute_slug_lowercased)] = $matrix_value;
        }
        
        if ($this->verbose) {
            //var_dump($variations);
        }
        
        // Quit out if none were found
        if (sizeof($variations) == 0) {
            return false;
        }

        //$_product = get_product($post_id, array('product_type' => 'variable'));
        $_product = wc_get_product($post_id);
        
        
        if (empty($_product)) :
            return false;
        endif;
        
        if ($this->verbose) {
            var_dump("--------- before available_variations -----------");
        }
        
        
        // Get existing variations so we don't create duplicates
        $available_variations = array();
        foreach ($_product->get_children() as $child_id) {
            //$child = $_product->get_child($child_id);
            $child = wc_get_product($child_id);
            
            //if (!empty($child->variation_id)) {
            $childVariationId = $child->get_id();
            if (!empty($childVariationId)) {
                $available_variations[$childVariationId] = $child->get_variation_attributes();
            }
        }

        if ($this->verbose) {
            var_dump("--------- after available_variations -----------");
            var_dump($available_variations);
        }
        
        // Created posts will all have the following data
        $variation_post_data = array(
            'post_title' => 'Product #' . $post_id . ' Variation',
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
            'post_parent' => $post_id,
            'post_type' => 'product_variation'
        );

        // Now find all combinations and create posts
        if (!function_exists('array_cartesian')) {
            function array_cartesian($input)
            {
                $result = array();

                while (list($key, $values) = each($input)) {
                    // If a sub-array is empty, it doesn't affect the cartesian product
                    if (empty($values)) {
                        continue;
                    }

                    // Special case: seeding the product array with the values from the first sub-array
                    if (empty($result)) {
                        foreach ($values as $value) {
                            $result[] = array($key => $value);
                        }
                    } else {
                        // Second and subsequent input sub-arrays work like this:
                        //   1. In each existing array inside $product, add an item with
                        //      key == $key and value == first item in input sub-array
                        //   2. Then, for each remaining item in current input sub-array,
                        //      add a copy of each existing array inside $product with
                        //      key == $key and value == first item in current input sub-array
                        // Store all items to be added to $product here; adding them on the spot
                        // inside the foreach will result in an infinite loop
                        $append = array();
                        foreach ($result as &$product) {
                            // Do step 1 above. array_shift is not the most efficient, but it
                            // allows us to iterate over the rest of the items with a simple
                            // foreach, making the code short and familiar.
                            $product[$key] = array_shift($values);

                            // $product is by reference (that's why the key we added above
                            // will appear in the end result), so make a copy of it here
                            $copy = $product;

                            // Do step 2 above.
                            foreach ($values as $item) {
                                $copy[$key] = $item;
                                $append[] = $copy;
                            }

                            // Undo the side effecst of array_shift
                            array_unshift($values, $product[$key]);
                        }

                        // Out of the foreach, we can add to $results now
                        $result = array_merge($result, $append);
                    }
                }

                return $result;
            }
        }

        $added = 0;
        $possible_variations = array_cartesian($variations);
        //if($this->verbose) var_dump($possible_variations);
        $variation_id = false;
        foreach ($possible_variations as $variation) {
            // Check if variation already exists
            if (in_array($variation, $available_variations)) {
                // existing variation
                foreach ($available_variations as $vkey => $vvalue) {
                    if ($vvalue == $variation) {
                        $variation_id = absint($vkey);
                    }
                }
            } else {
                //Create new variation
                $variation_id = wp_insert_post($variation_post_data);
                foreach ($variation as $key => $value) {
                    update_post_meta($variation_id, $key, $value);
                }

                $added++;
            }
        }
        //if($this->verbose) $this->_print ("matrix saved.");
        //$woocommerce->clear_product_transients( $post_id );
        return $variation_id;
    }
}
