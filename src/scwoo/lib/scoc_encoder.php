<?php

class scoc_encoder
{

    // http://stackoverflow.com/questions/24709331/encrypt-in-vb-net-and-decrypt-in-php
    public $_encryptionKey;
    private $aes;
    private $verbose = 0;

    private $consumer_key = '';
    private $consumer_secret = '';

    private $_authorizationHeader = "";

    public function __construct()
    {
        $this->aes = new scoc_AES();

        if (isset($_GET['verbose']) && $_GET['verbose'] == "1") :
            $this->verbose = 1;
            echo "verbose mode enabled\n\r<br>";
        endif;
    }

    private function isSecureRequest()
    {
        if (is_ssl()) {
            return true;
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        return false;
    }

    /** Select a credential validation path and return request values only after authentication. */
    public function authenticate($enabled = true, $encryption = false)
    {
        global $wp_version, $woocommerce;
        $qryStrArray = array();
        $q = $_SERVER["QUERY_STRING"];

        if ($this->verbose == 1) :
            echo ("-------------- INITIAL QUERY STRING BEFORE PARSING -----------\n\r");
            echo "Query string is present and redacted for safety\n\r";
        endif;

        parse_str($q, $qryStrArray);

        $qryStrArray["is_authenticated"] = 0;
        $qryStrArray["message"] = "";

        $apacheHeaders = array();
        try {
            $apacheHeaders = apache_request_headers();

        } catch (Exception $ex) {
            $apacheHeaders = array();
        }


        if ($this->verbose == 1) :
            echo ("-------------- INITIAL ARRAY AFTER PARSING -----------\n\r");
            echo "Request values received: redacted for safety\n\r";

            //echo ("-------------- APACHE HEADERS -----------\n\r");
            //var_dump($apacheHeaders);
            //echo ("-------------- _SERVER -----------\n\r");
            //var_dump($_SERVER);
        endif;

        // Retrieve http header "origin"
        $this->_encryptionKey = "";
        if(isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] != "")
        {
            $this->_encryptionKey = $_SERVER['HTTP_ORIGIN'];
        }
        else if(isset($_SERVER['Origin']) && $_SERVER['Origin'] != "")
        {
            $this->_encryptionKey = $_SERVER['Origin'];
        }
        else if(isset($apacheHeaders['Origin']) && $apacheHeaders['Origin'] != "")
        {
            $this->_encryptionKey = $apacheHeaders['Origin'];
        }

        if($this->verbose==1) echo "<br>\r\nHTTP_ORIGIN present\r\n";

        // Retrieve http header "origin"
        $this->_authorizationHeader = "";
        if(isset($_SERVER['HTTP_AUTHORIZATION']) && $_SERVER['HTTP_AUTHORIZATION'] != "")
        {
            $this->_authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        else if(isset($_SERVER['Authorization']) && $_SERVER['Authorization'] != "")
        {
            $this->_authorizationHeader = $_SERVER['Authorization'];
        }
        else if (isset($apacheHeaders['Authorization']) && $apacheHeaders['Authorization'] != "")
        {
            $this->_authorizationHeader = $apacheHeaders['Authorization'];
        }

        if($this->verbose==1) echo "<br>\r\nHTTP_AUTHORIZATION present\r\n";

        if ($this->verbose == 1) :
            echo ("-------------- AFTER RECEIVING HTTP HEADERS -----------\n\r");
        endif;

        if (isset($qryStrArray["sctoken"])) :
            $finalArray = $this->validateByScToken($qryStrArray);

        elseif (!empty($this->_authorizationHeader) && !empty($this->_encryptionKey)) :

            $finalArray = $this->validateByHeader($qryStrArray);
        else:
            if (!empty($qryStrArray["consumer_key"]) || !empty($qryStrArray["consumer_secret"])) {
                if (!$this->isSecureRequest()) {
                    $qryStrArray["is_authenticated"] = 0;
                    $qryStrArray["message"] = 'Insecure request: consumer keys must not be sent over plain HTTP.';
                    $finalArray = $qryStrArray;
                } else {
                    $finalArray = $this->validateByQuery($qryStrArray);
                }
            } else {
                $finalArray = $this->validateByQuery($qryStrArray);
            }
        endif;


        if ($this->verbose == 1) :
            echo ("-------------- FINAL ARRAY -----------\n\r");
            echo "Authentication result: redacted for safety\n\r";
        endif;

        if ($finalArray["is_authenticated"] == 0):
            $scoc_lib = new scoc_lib();
            $msg = "<response>"
                    . $scoc_lib->getVersionXml()
                    . "<status>failure</status><message>%s</message></response>";

            echo sprintf($msg, $finalArray['message']);
            return null;
        else:
            return $finalArray;
        endif;
    }

    public function validateByScToken($qryStrArray)
    {
        foreach ($GLOBALS as $key => $val) {
            global $$key;
        }

        if($this->verbose ==1 ) echo "\r\nAuth by ScToken";

        // Decrypt the token
        $scToken = trim((string) $qryStrArray["sctoken"]);
        $scToken = str_replace(" ", "+", $scToken);
        $scTokenDec = $this->aes->Decrypt($scToken, $this->_encryptionKey);
        $scTokenDec = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $scTokenDec);

        if ($this->verbose == 1) :
            echo "scToken decrypted successfully\r\n";
        endif;

        // Split the token by ||||
        $arrUserPass = explode("||||", $scTokenDec);
        if (!is_array($arrUserPass) || count($arrUserPass) != 2) :
            $qryStrArray["is_authenticated"] = 0;
            $qryStrArray["message"] = 'ScToken: Invalid parameters';

            return $qryStrArray;
        endif;

        $adminUsername = $arrUserPass[0];
        $adminPassword = $arrUserPass[1];

        $result = $this->dbLogin($adminUsername, $adminPassword);

        $qryStrArray = array_map(function ($val) {
                return urldecode($val);
            }, $qryStrArray);

        $qryStrArray = $this->makeParamLower($qryStrArray);

        if($result == true):
            $qryStrArray["is_authenticated"] = 1;
        else:
            $qryStrArray["is_authenticated"] = 0;
            $qryStrArray["message"] = 'ScToken: Authentication failed';
        endif;

        return $qryStrArray;
    }

    public function validateByHeader($qryStrArray)
    {
        global $wp_version, $woocommerce;
        foreach ($GLOBALS as $key => $val) {
            global $$key;
        }

        if($this->verbose ==1 ) echo "\r\nAuth by Header";


        $qryStrArray = array_map(
            function ($val) {
                return urldecode($val);
            },
            $qryStrArray
        );

        $qryStrArray = $this->makeParamLower($qryStrArray);

        $this->_authorizationHeader = str_replace("BASIC ", "", $this->_authorizationHeader);
        $arrAuthValues = explode(":", $this->_authorizationHeader);

        if(is_array($arrAuthValues) && count($arrAuthValues) == 2) {
            $enc_user = $arrAuthValues[0];
            $enc_password = $arrAuthValues[1];

            $consumer_key = $this->aes->Decrypt($enc_user, $this->_encryptionKey);
            $consumer_secret = $this->aes->Decrypt($enc_password, $this->_encryptionKey);

            $consumer_key = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', trim($consumer_key));
            $consumer_secret = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', trim($consumer_secret));

            if($this->verbose==1) echo "<br>\r\n User credentials decoded successfully\r\n";
        }
        else
        {
            $qryStrArray["is_authenticated"] = 0;
            $qryStrArray["message"] = 'Header: Wrong encoded string passed';

            return $qryStrArray;
        }


        // finally consumer_key and secret are received.
        // Stop if don't have any key.
        if ( ! $consumer_key || ! $consumer_secret ) {
            $qryStrArray["message"] = 'Invalid header keys in request';
            return $qryStrArray;
        }

        $result = $this->validate_keys($consumer_key, $consumer_secret);

        if($result == true):
            $qryStrArray["is_authenticated"] = 1;
        else:
            $qryStrArray["is_authenticated"] = 0;
            $qryStrArray["message"] = 'Header key mismatch';
        endif;

        return $qryStrArray;
    }

    /** Validate query-string keys, requiring HTTPS whenever either key is supplied. */
    public function validateByQuery($qryStrArray)
    {
        foreach ($GLOBALS as $key => $val) {
            global $$key;
        }

        if($this->verbose ==1 ) echo "\r\nAuth by query string (redacted)";

        if (!empty($qryStrArray["consumer_key"]) || !empty($qryStrArray["consumer_secret"])) {
            if (!$this->isSecureRequest()) {
                $qryStrArray["is_authenticated"] = 0;
                $qryStrArray["message"] = 'Insecure request: consumer keys must not be passed in the query string over HTTP.';
                return $qryStrArray;
            }
        }

        $qryStrArray = array_map(function ($val) {
                return urldecode($val);
            }, $qryStrArray);

        $qryStrArray = $this->makeParamLower($qryStrArray);

        // ---------- Validate by keys --------
        $consumer_key = "";
        $consumer_secret = "";

        if ( ! empty( $_GET['consumer_key'] ) && ! empty( $_GET['consumer_secret'] ) ):

            $consumer_key    = $_GET['consumer_key']; // WPCS: CSRF ok, sanitization ok.
            $consumer_secret = $_GET['consumer_secret']; // WPCS: CSRF ok, sanitization ok.

            // $consumer_key = wc_api_hash( sanitize_text_field( $consumer_key ) );
            $consumer_key = sanitize_text_field( $consumer_key );
            $consumer_secret = sanitize_text_field($consumer_secret); // WPCS: CSRF ok, sanitization ok.
        endif;

        $result = $this->validate_keys($consumer_key, $consumer_secret);

        if($result == true):
            $qryStrArray["is_authenticated"] = 1;
        else:
            $qryStrArray["is_authenticated"] = 0;
            $qryStrArray["message"] = 'Header key mismatch';
        endif;
        // =============================

        /**
        if (!isset($qryStrArray["u"]) || !isset($qryStrArray["p"])):
            $qryStrArray["is_authenticated"] = 0;
            $qryStrArray["message"] = 'Query: Missing Parameters';

            return $qryStrArray;
        endif;

        $adminUsername = trim((string) $qryStrArray["u"]);
        $adminPassword = trim((string) $qryStrArray["p"]);




        try {
            $result = $this->dbLogin($adminUsername, $adminPassword);

        } catch (Exception $ex) {
            $qryStrArray["is_authenticated"] = 0;
            $qryStrArray["message"] = "Query: Exception: " . $ex->getMessage();

            return $qryStrArray;
        }

        if($result == true):
            $qryStrArray["is_authenticated"] = 1;
        else:
            $qryStrArray["is_authenticated"] = 0;
            $qryStrArray["message"] = 'Query: Authentication failed';
        endif;
        **/

        return $qryStrArray;
    }


    private function makeParamLower($qryStrArray)
    {
        return array_change_key_case($qryStrArray, CASE_LOWER);
    }


    public function isValid($str)
    {
        return !preg_match('/[^A-Za-z0-9.#\\-$]/', $str);
    }

    public function dbLogin($username, $password)
    {
        if ($this->verbose == 1) {
            echo "admin pwd in dbLogin() = " . $password . "\r\n";
        }

        if ($this->verbose == 1) :
            echo ("-------------- dbLogin() get_user_by() -----------\n\r");
            //var_dump(get_user_by('login', $username));
        endif;


        // Normally username = admin
        $user = username_exists($username);

        if (!$user) {
            if ($this->verbose == 1):
                echo "user not found\r\n";
            endif;

            return false;
        }


        $user = wp_authenticate($username, $password);
        $message = "User does not belong to administrator, ceo or management role";

        if (!is_wp_error($user)) {
            //return TRUE;
            if (in_array('administrator', (array) $user->roles)) {
                return true;
            } elseif (in_array('ceo', (array) $user->roles)) {
                return true;
            } elseif (in_array('management', (array) $user->roles)) {
                return true;
            } else {
                throw new Exception($message);
            }
        }
        else {
            if ($this->verbose == 1):
                echo "credentials are not valid\r\n";
                //var_dump($password);
                //var_dump($user);
            endif;
        }
        return false;
    }

    private function validate_keys($consumer_key, $consumer_secret) {
        foreach ($GLOBALS as $key => $val) {
            global $$key;
        }

        // Stop if don't have any key.
        if ( ! $consumer_key || ! $consumer_secret ) {
            return false;
        }

        // Get user data.
        $query = $wpdb->prepare(
            "SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces
            FROM {$wpdb->prefix}woocommerce_api_keys
            WHERE consumer_key = %s",
            wc_api_hash($consumer_key)
        );

        $user         = $wpdb->get_row($query);

        if ( empty( $user ) ) {
            return false;
        }

        // Validate user secret.
        if ( ! hash_equals( $user->consumer_secret, $consumer_secret ) ):
            return false;
        else:
            return true;
        endif;

    }
}
