<?php
namespace PhpAutoWhere;

class Where
{
    private $_auto;

    // global config
    private $table;

    // options
    private $columns = [];
    private $or = [];

    // result query
    private $q = "";


    /**
     * Constructor
     *
     * @param mixed $core
     */
    public function __construct($core){
        $this->_auto = $core;
    }



    /**
     * Generate chain methods
     *
     * @return mixed
     */
    private function getInstance(){
        return $this->_auto->getInstance();
    }



    /**
     * @param string $table
     *
     * @return mixed
     */
    public function table($table){
        $this->table = $table;
        return $this->getInstance();
    }
    /**
     * @param array $columns
     *
     * @return mixed
     */
    public function columns($columns){
        if(is_array($columns)) $this->columns = $columns;
        return self::getInstance();
    }
    /**
     * @param array $or
     *
     * @return mixed
     */
    public function _or($or){
        if(is_array($or)) $this->or = $or;
        return self::getInstance();
    }



    /**
     * @param array $where
     *
     * @return string
     */
    public function render($where){
        if(is_array($this->table)){
            $result = [];
            foreach($this->table as $a => $m){
                $query = $this->whereComplete($where, $a);
                if(is_array($query)) $result = array_merge($result, $query);
            }
        }else {
            $result = $this->whereComplete($where);
        }
        if($result == null) return " true ";

        $this->q = $this->whereCompleteParser($result);

        if(empty($this->q)) return null;
        else return $this->q;
    }


    private function whereComplete( $where, $alias = null ){
        $q = null;
        if( !empty($where) ){
            if( is_array($where) ){

                // get table
                if( $alias ){
                    $table = $this->table[$alias];
                }else{
                    $table = $this->table;
                }

                // get table description by DB
                if( $table ){
                    $dbcolumns = [];

                    // get dbColumns by querying
                    if(in_array($this->_auto->_dbtype, ["mysql"])){
                        $dbcolumns = $this->_auto->_db->select("describe ".$table);
                        $dbcolumns = json_decode(json_encode($dbcolumns), true);
                    }
                    if(in_array($this->_auto->_dbtype, ["pgsql"])) {
                        $dbcolumns = $this->_auto->_db->select("SELECT column_name as \"Field\", data_type as \"Type\" FROM information_schema.COLUMNS WHERE TABLE_NAME = '".$table."'");
                        $dbcolumns = json_decode(json_encode($dbcolumns), true);
                    }

                    // Clean dbcolumns
                    foreach ($dbcolumns as $k=>$v){
                        if(strpos($v['Type'],'unsigned') >= 0){
                            $dbcolumns[$k]['Type'] = str_replace('unsigned','',$dbcolumns[$k]['Type']);
                            $dbcolumns[$k]['Type'] = rtrim($dbcolumns[$k]['Type']);
                        }
                    }

                }
                else $dbcolumns = [];


                // Loop where columns
                $result = [];
                foreach ($where as $key => $value) {

                    // Has alias ?
                    $keyo = null;
                    if( $alias ){
                        $keyo = $key;
                        $key = explode(".",$key);

                        // if not alias in this loop continue to next where column
                        if($key[0] != $alias) continue;
                        // has alias but not provided correctly where param ?
                        if(count($key) < 2) continue;

                        $key = $key[1];
                    }




                    // find column of loop in table description
                    $type = false;
                    if(!empty($this->columns)) if(array_key_exists($key, $this->columns)) $type = $this->columns[$key];
                    if($alias) if(!empty($this->columns)) if(array_key_exists($keyo, $this->columns)) $type = $this->columns[$keyo];
                    $dbtype = array_search($key, array_column($dbcolumns, 'Field'));
                    if($type === false && $dbtype === false) continue;
                    if($type === false)
                        $type = $dbcolumns[$dbtype]["Type"];

                    $type = strtolower(preg_replace('/[0-9]/', "", $type));


                    // revert $key with alias
                    if($alias) {
                        $key = $keyo;
                        $col = $key;
                    }else{
                        $col = explode("|",$key);
                        foreach ($col as $k=>$v) {
                            $col[$k] = $table.".".$v;
                        }
                        $col = implode("|",$col);
                    }

                    if( is_array($value) ){ // array values parse: ( [0] or [1] or [2] ... )

                        $valueArr = [];
                        foreach ($value as $v) {
                            $valueArr[] = self::whereCompleteProcess($v, $col, $type);
                        }
                        $result[$key] = " (".implode(" or ",$valueArr).") ";

                    }else {

                        $result[$key] = self::whereCompleteProcess($value, $col, $type);

                    }

                }//foreach

                $q = $result;

            }//is array
            else {
                // not array
            }
        } //exists / empty
        else{
            // not exists where param
        }


        return $q;
    }

    private function whereCompleteProcess($value, $key, $type){
        $q = "";

        switch($type){
            case "int":
            case "int()":
            case "integer":
            case "integer()":
            case "decimal":
            case "decimal()":
            case "number":
            case "bigint":
            case "money":
            case "numeric":
            case "real":
            case "double":
            case "double precision":

                // numeric parser
                $value = $this::numericToDB($value);

                if (strpos($value, ":") !== false){ // number range [0] e [1]
                    $valueArray = explode(":",$value);
                    if($valueArray[0] <= $valueArray[1])
                        $q .="(" .$key ." BETWEEN ".$this::getOnlyNumber($valueArray[0])." AND ".$this::getOnlyNumber($valueArray[1]).")";
                }else{
                    if (strpos($value, ">") !== false && strpos($value, "<") !== false){
                        $q .=$key ." <>".$this::getOnlyNumber($value)."";
                    }elseif (strpos($value, ">") !== false){ // number [0] bigger than [1]
                        $q .=$key ." >".$this::getOnlyNumber($value)."";
                    }else if (strpos($value, "<") !== false){ // number [1] bigger than [0]
                        $q .=$key ." <".$this::getOnlyNumber($value)."";
                    }else{ // number only
                        $q .=$key ." = ".$value."";
                    }
                }
                break;
            case "text":
            case "mediumtext":
            case "varchar":
            case "varchar()":
            case "char":
            case "char()":
            case "character varying":
            case "character":
                $q .= "(UPPER(".$key .") LIKE '%".mb_strtoupper($value)."%')";
                break;
            case "equal":
                $q .= "(".$key ." = '".$value."')";
                break;
            case "string_equal":
            case "text_equal":
                $q .= "(UPPER(".$key .") = '".mb_strtoupper($value)."')";
                break;
            case "select":
                if(is_numeric($value))
                    $q .= "(".$key ."=".$value.")";
                else
                    $q .= "(".$key ."='".$value."')";
                break;
            case "date":
            case "datetime":
            case "time":
            case "timestamp":
            case "timestamp without time zone":
            case "timestamp with time zone":
                if (strpos($value, "|") !== false){
                    $valueArray = explode("|",$value);
                    if($valueArray[0] =="*" && $valueArray[1] =="*"){
                        $q .= "".$key." is not Null";
                    }elseif($valueArray[0] =="*"){
                        $q .="(" .$key ." <= '".self::parseDatetime($valueArray[1], $this->_auto->_config->db_date_format)."')";
                    }elseif($valueArray[1] =="*"){
                        $q .="(" .$key ." >= '".self::parseDatetime($valueArray[0], $this->_auto->_config->db_date_format)."')";
                    }else{
                        $q .="(" .$key ." BETWEEN '".self::parseDatetime($valueArray[0], $this->_auto->_config->db_date_format)."' AND '".self::parseDatetime($valueArray[1], $this->_auto->_config->db_date_format)."')";
                    }
                }else{
                    $oper = null;
                    if($type == "time"){
                        $oper = "=";
                    }
                    if(strpos($value, ">=")!== false){
                        $oper = ">=";
                        $value = str_replace(">=","",$value);
                    }
                    if(strpos($value, ">=")!== false){
                        $oper = ">=";
                        $value = str_replace(">=","",$value);
                    }
                    if(strpos($value, "<")!== false){
                        $oper = "<";
                        $value = str_replace("<","",$value);
                    }
                    if(strpos($value, ">")!== false){
                        $oper = ">";
                        $value = str_replace(">","",$value);
                    }
                    if($oper) {
                        $q .= $key . " " . $oper ." '".self::parseDatetime($value, $this->_auto->_config->db_date_format)."'";
                    }else{
                        $tomorrow = strtotime(self::parseDatetime($value, $this->_auto->_config->db_date_format) . " +1 day");
                        if(count(explode(" ",$value))>1){ // datetime
                            $tomorrow = date('Y-m-d H:i:s',$tomorrow);
                        }else{ // date
                            $tomorrow = date('Y-m-d',$tomorrow);
                        }
                        $q .= $key ." >= '".self::parseDatetime($value, $this->_auto->_config->db_date_format)."' and ".$key." <= '".$tomorrow."'";
                    }
                }
                break;
            case "between_columns":
                // todo: separate of "between_columns_date" without cast
            case "between_columns_date":
                $keyArray = explode("|",$key);

                $q .= "('" . self::parseDatetime($value, $this->_auto->_config->db_date_format) . "' BETWEEN cast(" . $keyArray[0] . " as date) 
                            AND cast(" . $keyArray[1] . " as date) )";

                break;
            case "tinyint":
            case "tinyint()":
            case "bool":
            case "boolean":
                $q .=" (";
                if ($value == false || $value === 'false' || $value == 0 ){
                    $q .=  '('.$key.' = false) OR ('.$key.' IS NULL)';
                }else{
                    $q .= $key.' = true';
                }
                $q .= ")";
                break;
            case "null":
                if(boolval($value) && !empty($value) && !is_null($value))
                    $q .= " ".$key." IS NULL";
                else
                    $q .= " ".$key." IS NOT NULL";
                break;
            case "custom":
                $q .= $value;
                break;
            default:
                break;
        } //switch

        return $q;
    }

    private function whereCompleteParser($result){

        $sql = "";
        // remove null values of sql
        foreach($result as $k => $v){
            if($v == null){
                unset( $result[$k] );
                continue;
            }
//            if( is_array($v) ){
//                foreach ($v as $k2 => $v2){
//                    $sql[] = $v2;
//                }
//            }else{
//                $sql[] = $v;
//            }
        }

        // transform array to sql
        if( count($this->or) ){
            $or = [];
            foreach($result as $k => $v){

                foreach ($this->or as $index => $orValue){
                    if(is_array($orValue)){

                        foreach ($orValue as $index2 => $orValue2) {

                            if (in_array($k, $orValue2)) {
                                isset($or[$index2]) ?
                                    $or[$index2] .= " OR " . $v :
                                    $or[$index2] = $v;
                                unset($result[$k]);
                            }

                        }

                    }else{

                        if( $k == $orValue ) {
                            array_push($or, $v);
                            unset($result[$k]);
                        }

                    }
                }

            }



            if(count($result)){
                $sql .= implode(" AND ", $result);
            }
            if(count($or)){
                if(count($result)) $sql .= " AND ";
                $sql .= "(";
                $count = 0;
                foreach ($or as $o){
                    if( is_array($o) ){
                        if($count) $sql .= " AND ";
                        $sql .= "(";
                        $sql .= implode(" OR ", $o);
                        $sql .= ")";
                    }else{
                        if($count) $sql .= " OR ";
                        $sql .= $o;
                    }
                    $count++;
                }
                $sql .= ")";
            }


        }else{
            $sql = implode(" AND ", $result);
        }

        if(empty($sql)) $sql = "true";
        return $sql;
    }


    // not in use, needs improves and tests
    public static function havingComplete($array){
        $c = 0;
        $r = "";
        if( is_array( $array ) )
            foreach($array as $k => $v){
                if($c) $r .= " AND ";

                if (strpos($v, ":") !== false){ // number range [0] e [1]
                    $vArray = explode(":",$v);
                    if($vArray[0] <= $vArray[1])
                        $r .="(" .$k ." BETWEEN ".$vArray[0]." AND ".$vArray[1].")";
                }else{
                    if (strpos($v, ">") !== false){ // number [0] bigger than [1]
                        $v = str_replace(">", "", $v);
                        $r .= $k ." >".$v."";
                    }else if (strpos($v, "<") !== false){ // number [1] bigger than [0]
                        $v = str_replace("<", "", $v);
                        $r .= $k ." <".$v."";
                    }else{ // number only
                        $r .= $k ." = ".$v."";
                    }
                }

                $c++;
            }
        return empty($r) ? "1=1" : $r;
    }





    // Helps for data manipulation

    public static function parseDate($date, $format){
        if( $format == "d/m/Y" ) return self::parseDate1($date, $format);
        if( $format == "Y-m-d" ) return self::parseDate2($date, $format);
        return null;
    }
    public static function parseDate1($date, $format){
        if(!$date) return $date;
        if( strpos($date,"/") ) return $date; // format correct
        $date = explode('-',$date);
        $retorno = $date[2]."/".$date[1]."/".$date[0];
        return $retorno;
    }
    public static function parseDate2($date, $format){
        if(!$date) return $date;
        if( strpos($date,"-") ) return $date; // format correct
        $date = explode('/',$date);
        if(count($date) > 2) {
            $retorno = $date[2] . "-" . $date[1] . "-" . $date[0];
        }else{
            $retorno = $date[0];
        }
        return $retorno;
    }

    public static function parseDatetime($datetime, $format){
        if(!isset(explode(' ',$datetime)[1])){
            if(strpos($datetime,":") !== false) return $datetime; // is hours
            return self::parseDate($datetime, $format);
        }
        if( $format == "d/m/Y" ) return self::parseDatetime1($datetime, $format);
        if( $format == "Y-m-d" ) return self::parseDatetime2($datetime, $format);
        return null;
    }
    public static function parseDatetime1($datetime, $format){
        if(!$datetime) return $datetime;
        if( strpos(explode(' ',$datetime)[0],"/") ) return $datetime; // format correct
        $time = explode(':',explode(' ',$datetime)[1] );
        $date = explode('-', explode(' ',$datetime)[0] );
        $retorno = $date[2]."/".$date[1]."/".$date[0]." ".$time[0].":".$time[1];
        if($time[2] != "00") $retorno .= ":".$time[2];
        return $retorno;
    }
    public static function parseDatetime2($datetime, $format){
        if(!$datetime) return $datetime;
        if( strpos(explode(' ',$datetime)[0],"-") ) return $datetime; // format correct
        $time = explode(' ',$datetime)[1];
        $date = explode('/', explode(' ',$datetime)[0] );
        if(count($date) > 2) {
            $retorno = $date[2] . "-" . $date[1] . "-" . $date[0];
        }else{
            $retorno = $date[0];
        }
        if(count(explode(':',$time)) < 3){
            $time .= ":00";
        }
        $retorno .= " ".$time;
        return $retorno;
    }


    public static function numericToDB($value){
        // numeric float parser
        if (strpos($value, ":")) {
            $value = explode(":",$value);
            $value[0] = self::numericToDB($value[0]);
            $value[1] = self::numericToDB($value[1]);
            return implode(":",$value);
        }
        $value = preg_replace("/[^0-9.,-<>=]/", '',  $value);
        if(substr_count($value, '.') > 1) $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return $value;
    }
    public static function formatNumeric($value){
        $value = number_format($value, 2, ',', '.');
        return $value;
    }

    public static function boolean($value, $key = null){
        if($key && is_array($value)){
            if(isset($value[$key])){
                $value = $value[$key];
            }else $value = null;
        }


        if ($value == null || $value == "false") return false;
        if ($value == true || $value == "true" || strtolower($value) == "yes" || strtolower($value) == "y" ||
            strtolower($value) == "sim" || $value == 1 || $value == "1" || $value == 'on') return true;
        else return false;

    }

    public static function getOnlyNumber($value){
        $value = preg_replace("/[^0-9.]/", "",$value);
        return $value;
    }

}
?>