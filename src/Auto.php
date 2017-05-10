<?php

require_once "Where.php";
require_once "DB.php";

class Auto
{
    // instance for chain methods
    public $_instance = null;

    public $_class;
    public $_db;

    /**
     * Constructor
     */
    public function __construct(){
        $this->_db = DB::getConnection();
        if ($this->_instance === null) $this->_instance = $this;
    }

    /**
     * Generate chain methods
     *
     * @return Auto
     */
    public function getInstance(){
        if ($this->_instance === null) $this->_instance = $this;
        return $this->_instance;
    }


    public function __call($method,$arguments) {
        if($this->_class) {
            if (method_exists($this->_class, $method)) {
                return call_user_func_array(array($this->_class, $method), $arguments);
            }else return $this;
        }else{
            return call_user_func_array(array($this, $method), $arguments);
        }
    }

    /**
     * Initialize Where module
     *
     * @return Auto
     */
    public static function where(){
        $auto = new self();
        $auto->_class = new Where($auto);
        return $auto->getInstance();
    }

}
?>