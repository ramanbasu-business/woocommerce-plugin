<?php

/*
 * SellerCloud SCME

  NOTICE OF LICENSE

  This source file is subject to the Open Software License (OSL 3.0)

  DISCLAIMER

  Do not edit or add to this file if you wish to upgrade to newer
  versions in the future. If you wish to customize this extension for your
  needs please refer to http://www.sellercloud.com for more information.

  @author      Raman Basu <support@sellercloud.com>
  @package     Scme_Imports
  @copyright   Copyright (C) 2016 SellerCloud <support@sellercloud.com>
  @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Description of scoc_xmllog
 *
 * @author Raman Basu <support@sellercloud.com>
 */
class scoc_xmllog
{

    private $log_file_source = null;
    private $verbose = 0;
    private $globalhelper;
    private $JobId = 0;

    function __construct($p_job_id, $p_verbose = 0)
    {
        $this->verbose = $p_verbose;
        $this->JobId = $p_job_id;
        $this->globalhelper = new scoc_lib();
        $this->loadXmlIntoVariable();
    }

    private function loadXmlIntoVariable()
    {
        $this->log_file_source = $this->globalhelper->getJobLogFileSource($this->JobId);
        //var_dump($this->log_file_source);
    }

    function __destruct()
    {
    }

    private function get($sku)
    {
        $products = simplexml_load_string($this->log_file_source);
        if (!$products) {
            return false; //version 10.7
        }

        $p = $products->xpath('/products/product[sku="' . $sku . '"]');
        //var_dump($p);
        if ($p) {
            return true;
        }
        return false;
    }

    public function append($p_sku, $p_status, $p_error)
    {
        if ($this->get($p_sku)) {
            return false;
        }
        //return true;
        //$products = new SimpleXMLElement($this->_filename, null, true);
        $products = simplexml_load_string($this->log_file_source, null, LIBXML_NOCDATA);

        $p = $products->addChild('product');
        $p->addChild('sku', $p_sku);
        $p->addChild('status', $p_status);

        $errors = $p->addChild('errors');
        if ($p_error != "") {
            /* $p_error = cleanString($p_error);
              $p_error = preg_replace("/[<]/u", " ", $p_error);
              $p_error = preg_replace("/[>]/u", " ", $p_error);
              $error = $errors->addChild("error", $p_error);
             */
            $errors->error = null;
            $this->addCData($errors->error, $p_error);
        }
        //echo $products->asXML($this->_filename);
        return true;
    }

    public function appendError($p_sku, $p_error)
    {
        if ($this->get($p_sku) == false) {
            return false;
        }

        $dom = new DOMDocument();
        $dom->validateOnParse = true;
        
        $this->loadXmlIntoVariable();
        $dom->loadXML($this->log_file_source);
        //var_dump($dom);
        
        foreach ($dom->getElementsByTagName('product') as $p) {
            //$p->setIdAttribute('id', true);
            //var_dump($p);
        }
        $elements = $dom->getElementsByTagName('product');
        foreach ($elements as $node) {
            foreach ($node->childNodes as $child) {
                //$data[] = array($child->nodeName => $child->nodeValue);
                if ($child->nodeName == 'sku' && $child->nodeValue == $p_sku) {
                    //echo $child->nodeValue;
                    $p = $node;
                    break;
                }
            }
        }

        /*
          $xpath = new DOMXPath($dom);
          $ps = $xpath->query('//product[sku="'. $p_sku .'"]');

          foreach ($ps as $p) {
          echo ($p->getElementsByTagName('sku')->item(0));
          echo $p->find('sku',0);
          echo $p->childNodes[0];
          }
          return;
         */

        foreach ($p->getElementsByTagName('errors') as $errors) {
            if ($p_error != "") {
                $cdata = $dom->createCDATASection($p_error);
                //$errors->getElementsByTagName('error')->item(0)->appendChild($cdata);
                $error = $dom->createElement("error");
                $errors->appendChild($error)->appendChild($cdata);
            }
        }
        //var_dump( $p[0]->sku );
        //return;
        //$dom->save( $this->_filename );
        $this->globalhelper->updateJobLogFileSource($this->JobId, $dom->saveXML());
        return true;
    }

    public function log($p_sku, $p_status, $p_error = "", $order_status = "", $json_response = "")
    {
        $this->loadXmlIntoVariable();
        
        if ($this->get($p_sku)) {
            //if($this->verbose==1) {echo 'log: product sku found';}
            return $this->appendError($p_sku, $p_error);
        } else {
            //if($this->verbose==1) {echo 'log: product sku ' . $p_sku .' not found'; }
            //return false;
        }
        //return false;
        $dom = new DOMDocument();
        $dom->validateOnParse = true;
        
        //$dom->load( $this->_filename );
        //var_dump($this->_filename);
        
        $dom->loadXML($this->log_file_source);
        //var_dump($this->log_file_source);
        
        $p = $dom->createElement("product");
        $sku = $dom->createElement('sku', $p_sku);
        $p->appendChild($sku);

        $status = $dom->createElement('status', $p_status);
        $p->appendChild($status);

        if ($order_status != null && $order_status != "") :
            $order_status_node = $dom->createElement('order_status', $order_status);
            $p->appendChild($order_status_node);
        endif;

        if ($json_response != null && $json_response !== "") :
            $json_node = $dom->createElement('json_response', $json_response);
            $p->appendChild($json_node);
        endif;
        
        
        $errors = $dom->createElement('errors');

        if ($p_error != "") {
            $cdata = $dom->createCDATASection($p_error);
            $error = $dom->createElement("error");
            $errors->appendChild($error)->appendChild($cdata);
        }
        $p->appendChild($errors);

        // v 18.3
        if ($dom->documentElement != null) {
            $dom->documentElement->appendChild($p);
        } else {
            $dom->appendChild($p);
        }

        //var_dump($dom->saveXML());
        $this->globalhelper->updateJobLogFileSource($this->JobId, $dom->saveXML());
        return true;
    }

    public function addCData($e, $cdata_text)
    {
        $node = dom_import_simplexml($e);
        $no = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($cdata_text));
    }
}
