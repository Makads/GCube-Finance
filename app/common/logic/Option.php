<?php

namespace app\common\logic;

class Option
{
    private $allowSystem = NULL;
    private $system = NULL;
    private $osIco = NULL;
    private $ext = "jpg";
    public function initialize()
    {
        $this->allowSystem = config("allow_system");
        $this->system = config("system_list");
        $this->imageaddress = config("servers");
        $this->osIco = config("system");
    }
}

?>