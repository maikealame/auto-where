<?php
namespace PhpAutoWhere;

include('DB.php');
include('Where.php');

use PhpAutoWhere\Where;
use PhpAutoWhere\DB;

class Auto
{
    public $_core = "laravel";
    public $_class;
    public $_config = __auto_config;
    public $_db;
    public $_dbtype;

    // instance for chain methods
    public $_instance = null;


    /**
     * Constructor
     */
    public function __construct(){
        $this->_config = (object) $this->_config;
        $this->_db = DB::getConnection($this->_config->db);
        $this->_dbtype = $this->_config->db['type'];

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
            }elseif( $method == "or"){ // Keyword reserved of php

                return call_user_func_array(array($this->_class, "_or"), $arguments);

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