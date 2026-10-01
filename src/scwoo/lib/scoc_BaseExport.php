<?php

class scoc_BaseExport
{
    public $scoc_lib;
    public $scoc_utility;
    public $scoc_encoder;

    public $verbose;

    public function __construct()
    {
        $this->scoc_lib = new scoc_lib();
        $this->scoc_utility = new scoc_utility();
        $this->scoc_encoder = new scoc_encoder();

        $this->scoc_utility->printHeader();
        echo '<?xml version="1.0" encoding="UTF-8" ?>'; // 7.11.0.0. Echo after header
    }

    public function authenticate()
    {
        $qtrStrArray = $this->scoc_encoder->authenticate();
        if ($qtrStrArray == null) {
            return false;
        }

        if (isset($qtrStrArray["verbose"]) && $qtrStrArray["verbose"]==1) :
            $this->verbose = 1;
        else :
            $this->verbose = 0;
        endif;

        return true;
    }

    protected function _print($s)
    {
        if ($this->verbose == 1) {
            echo '<li>' . $s . '</li>\n\r';
        }
        //$this->log->write($s);
    }
}
