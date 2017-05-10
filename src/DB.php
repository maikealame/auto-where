<?php
namespace PhpAutoWhere;

class DB
{
    /**
     * @var \PDO
     */
    private $db;

    /**
     * @param $dbInfo
     */
    private function __construct($dbconfig)
    {
        try {
            if($dbconfig['type'] == "mysql") {
                $this->db = new \PDO(
                    'mysql:host=' . $dbconfig['host'] . ';dbname=' . $dbconfig['database'] . ';charset=utf8',
                    $dbconfig['username'],
                    $dbconfig['pass']
                );
            }

            if($dbconfig['type'] == "pgsql" || $dbconfig['type'] == "postgres" || $dbconfig['type'] == "postgresql"){
                $this->db = new \PDO(
                    'pgsql:dbname=' . $dbconfig['database'] . ';host=' . $dbconfig['host'] . ';',
                    $dbconfig['username'],
                    $dbconfig['pass']
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
    public static function getConnection($dbconfig)
    {
        return new self($dbconfig);
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
