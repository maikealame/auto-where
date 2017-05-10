<?php

class DB
{
    /**
     * @var \PDO
     */
    private $db;
    public $type;

    /**
     * @param $dbInfo
     */
    private function __construct($dbInfo)
    {
        try {
            $this->db = $dbInfo['type'];
            if($dbInfo['type'] == "mysql") {
                $this->db = new \PDO(
                    'mysql:host=' . $dbInfo['host'] . ';dbname=' . $dbInfo['database'] . ';charset=utf8',
                    $dbInfo['username'],
                    $dbInfo['pass']
                );
            }

            if($dbInfo['type'] == "pgsql" || $dbInfo['type'] == "postgres" || $dbInfo['type'] == "postgresql"){
                $this->db = new \PDO(
                    'pgsql:dbname=' . $dbInfo['database'] . ';host=' . $dbInfo['host'] . ';',
                    $dbInfo['username'],
                    $dbInfo['pass']
                );
            }

            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            // To prevent PDO sql injection
            // According to http://stackoverflow.com/a/12202218/2045041
            $this->db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        } catch (\PDOException $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @param  $dbInfo
     * @return DB
     */
    public static function getConnection()
    {
        if(isset($__auto_config))
            return new self($__auto_config);
        return null;
    }

    public function select($sql){
        $rs = $this->db->prepare($sql);
            
        if($rs->execute()){
            if($rs->rowCount() > 0){
                return $rs->fetchAll();
            }       
        }
        return null;
    }
}
