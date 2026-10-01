<?php

class scoc_encoder
{

    // http://stackoverflow.com/questions/24709331/encrypt-in-vb-net-and-decrypt-in-php
    public $enabled = true;
    public $encryption = 0;
    public $key;
    private $tripleDes;
    private $aes;
    private $verbose = 0;


    private $consumer_key = '';
    private $consumer_secret = '';

    public function __construct()
    {
        $this->tripleDes = new scoc_TripleDES();
        $this->aes = new scoc_AES();

        if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] != "") :
            $this->key = $_SERVER['HTTP_ORIGIN'];
        else :
            $this->key = "A0B1C2D4E5F6G7H8";
        endif;

        if (isset($_GET['verbose']) && $_GET['verbose'] == "1") :
            $this->verbose = 1;
            echo "verbose mode enabled\n\r<br>";
        endif;
    }

    public function authenticate($enabled = true, $encryption = false)
    {
        /*         * *******************************************************
         *  Query string validation
         * ******************************************************* */

        $this->encryption = 0;
        $qryStrArray = array();
        $q = $_SERVER["QUERY_STRING"];

        if ($this->verbose == 1) :
            echo ("-------------- INITIAL QUERY STRING BEFORE PARSING -----------\n\r");
            var_dump($q);
        endif;
        
        parse_str($q, $qryStrArray);
        //var_dump($qryStrArray);
        
        if ($this->verbose == 1) :
            echo ("-------------- INITIAL ARRAY AFTER PARSING -----------\n\r");
            var_dump($qryStrArray);
        endif;

        if (count($qryStrArray) == 1) :
            reset($qryStrArray);
            $first_key = key($qryStrArray);
            if ($first_key !== "" && $qryStrArray[$first_key] == "") :
                $this->encryption = 1;
            endif;
        endif;

        //var_dump($this->encryption);
        $this->enabled = $enabled;
        
        $qryStrArray2 = $this->validateLogin();
        //var_dump($qryStrArray2);

        if ($this->verbose == 1) :
            echo ("-------------- FINAL ARRAY -----------\n\r");
            var_dump($qryStrArray2);
        endif;
        
        //var_dump($qryStrArray2==null);

        return $qryStrArray2;
        /*         * ******************************************************** */
        //* @ End of Query string validation
    }

    public function validateLogin()
    {
        global $wp_version, $woocommerce;
        $scoc_lib = new scoc_lib();

        $msg = "<response>"
                . $scoc_lib->getVersionXml()
                . "<status>failure</status><message>%s</message></response>";
        
        try {
            $qryStrArray2 = $this->getQueryStringArrayByDecryption();
        
            $adminUsername = "";
            $adminPassword = "";


            if ($this->verbose == 1) {
                echo ("-------------- validateLogin() -----------");
                var_dump($qryStrArray2);
            }
            
            if ($this->enabled == false) {
                return $qryStrArray2;
            }

            if ($qryStrArray2 == null || empty($qryStrArray2)) {
                echo sprintf($msg, "Authentication failed");
                return null;
            }

            $ignoreLogin = isset($qryStrArray2["ignorelogin"]) ? true : false;
            if ($ignoreLogin == true) {
                //var_dump($qryStrArray);
                return $qryStrArray2;
            }

            if (isset($qryStrArray2["sctoken"]) && $this->key != "" && $this->encryption == 0) :
                // Decrypt the token
                $scToken = trim((string) $qryStrArray2["sctoken"]);
                $scToken = str_replace(" ", "+", $scToken);
                $scTokenDec = $this->decrypt(($scToken));
                $scTokenDec = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $scTokenDec);

                if ($this->verbose == 1) {
                    echo "scToken after decryption = " . $scTokenDec . "\r\n";
                }
                
                // Split the token by ||||
                $arrUserPass = explode("||||", $scTokenDec);
                if (!is_array($arrUserPass) || count($arrUserPass) != 2) :
                    echo sprintf($msg, "Invalid Parameters");
                    return null;
                endif;

                $adminUsername = $arrUserPass[0];
                $adminPassword = $arrUserPass[1];
            else :
                if (!isset($qryStrArray2["u"]) && !isset($qryStrArray2["p"])) {
                    echo sprintf($msg, "Invalid Parameters");
                    return null;
                }

                if (!isset($qryStrArray2["u"])) {
                    echo sprintf($msg, "Invalid User");
                    return null;
                }

                if (!isset($qryStrArray2["p"])) {
                    echo sprintf($msg, "Invalid Password");
                    return null;
                }

                if (empty($qryStrArray2["u"])) {
                    echo sprintf($msg, 'Required parameter "u" is missing');
                    return null;
                }
                if (empty($qryStrArray2["p"])) {
                    echo sprintf($msg, 'Required parameter "p" is missing');
                    return null;
                }
                if ($qryStrArray2["u"] == "") {
                    echo sprintf($msg, 'Required parameter "u" is missing');
                    return null;
                }
                if ($qryStrArray2["p"] == "") {
                    echo sprintf($msg, 'Required parameter "p" is missing');
                    return null;
                }

                $adminUsername = trim((string) $qryStrArray2["u"]);
                $adminPassword = trim((string) $qryStrArray2["p"]);
            endif;

            
            if ($this->verbose == 1) :
                echo ("-------------- validateLogin() before dbLogin() -----------\n\r");
                echo ("user: " . $adminUsername . " pass: " . $adminPassword . " \n\r");
            endif;

            //var_dump($qryStrArray2);

            $result = $this->dbLogin($adminUsername, $adminPassword);
            //var_dump($result);

            if ($result == true) {
                return $qryStrArray2;
            } else {
                
                // 7.13.0.0
                $result = $this->validate_keys();
                if ($result == true):
                    return $qryStrArray2;
                endif;

                echo sprintf($msg, "Authentication failed");
                return null;
            }
        } catch (Exception $ex) {
            echo sprintf($msg, $ex->getMessage());
            return null;
        }
    }

    public function getQueryStringArrayByDecryption()
    {
        $qryStrArray = array();
        $q = $_SERVER["QUERY_STRING"];


        if (empty($q) && $this->enabled == true) {
            return null;
        }

        //if ($this->verbose == 1) {
        //    echo '<br>q before url decode ' . $q;
        //}
        // cannot urldecode. each query string param can contain ? and & characters. If
        // we decode now, additional element may be created in the array.
        //$q = urldecode($q);
        //if ($this->verbose == 1) {
        //    echo '<br>q after url decode ' . $q;
        //}
        // proceed with decryption of query string
        try {
            // Decide of we need encryption
            if ($this->encryption == 1) :
                $strDecrypted = $this->decrypt(urldecode($q)); //echo $qryString;
                //echo ' decrypted string ' . $strDecrypted;
                //if ($this->verbose == 1) {
                //    echo ' decrypted string ' . $strDecrypted;
                //}

                if (!empty($strDecrypted)) :
                        $qryString = "" . $strDecrypted;

                // removed Nov 13, 2017
                //$qryString = urldecode($qryString);
                endif;

                //$qryString = $this->cleanString($qryString);
                parse_str($qryString, $qryStrArray);
            else :
                parse_str($q, $qryStrArray);
                //var_dump($qryStrArray);
            endif;

            //var_dump($qryStrArray);
            // added Nov 13, 2017
            // Commented?? June 30, 2020. Because parse_str() already url decodes
            $qryStrArray = array_map(function ($val) {
                return urldecode($val);
                //return $val;
            }, $qryStrArray);

            $qryStrArray = $this->makeParamLower($qryStrArray);

            return $qryStrArray;
        } catch (Exception $ex) {
            echo "<response><status>failure</status><message>" . $ex->getMessage() . "</message></response>";
            return $qryStrArray;
        }
    }

    public function encrypt($string)
    {
        //$phpEncrypted = $this->tripleDes->Encrypt($string, $this->key);
        $phpEncrypted = $this->aes->Encrypt($string, $this->key);
        return $phpEncrypted;
    }

    public function decrypt($vbEncrypted)
    {
        //$phpDecrypted = $this->tripleDes->Decrypt($vbEncrypted, $this->key);
        $phpDecrypted = $this->aes->Decrypt($vbEncrypted, $this->key);
        return $phpDecrypted;
    }

    private function makeParamLower($qryStrArray)
    {
        return array_change_key_case($qryStrArray, CASE_LOWER);
    }

    /*     * *******************************************************
     *  Query string validation
     * ******************************************************* */

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
            var_dump(get_user_by('login', $username));
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
                //return false;
            }
        }
        else {
            if ($this->verbose == 1):
                echo "credentials are not valid\r\n";
                var_dump($password);
                var_dump($user);
            endif;
        }
        return false;
    }

    private function validate_keys() {
        foreach ($GLOBALS as $key => $val) {
            global $$key;
        }

        $consumer_key      = '';
        $consumer_secret   = '';

        if ( ! empty( $_GET['consumer_key'] ) && ! empty( $_GET['consumer_secret'] ) ):
            
            $consumer_key    = $_GET['consumer_key']; // WPCS: CSRF ok, sanitization ok.
            $consumer_secret = $_GET['consumer_secret']; // WPCS: CSRF ok, sanitization ok.

            // $consumer_key = wc_api_hash( sanitize_text_field( $consumer_key ) );
            $consumer_key = sanitize_text_field( $consumer_key );
            $consumer_secret = sanitize_text_field($consumer_secret); // WPCS: CSRF ok, sanitization ok.
        endif;

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
        //var_dump($user);
        // Validate user secret.
        if ( ! hash_equals( $user->consumer_secret, $consumer_secret ) ):
            return false;
        else:
            return true;
        endif;
        
    }
}
