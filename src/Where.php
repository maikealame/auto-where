<?php

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
     * @param Auto $core
     */
    public function __construct(Auto $core){
        $this->_auto = $core;
    }



    /**
     * Generate chain methods
     *
     * @return Auto
     */
    private function getInstance(){
        return $this->_auto->getInstance();
    }



    /**
     * @param string $table
     *
     * @return Auto
     */
    public function table($table){
        $this->table = (string) $table;
        return $this->getInstance();
    }
    /**
     * @param array $columns
     *
     * @return Auto
     */
    public function columns($columns){
        if(is_array($columns)) $this->columns = $columns;
        return self::getInstance();
    }
    /**
     * @param array $alias
     *
     * @return Auto
     */
    public function or($or){
        if(is_array($or)) $this->or = $or;
        return self::getInstance();
    }




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

        $this->q = $this->whereCompleteParser($result);

        if(empty($q)) return null;
        else return $q;
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
                    if(in_array($this->_auto->_db->type, ["mysql"])){
                        $dbcolumns = $this->_auto->_db->select("describe ".$this->table);
                        $dbcolumns = json_decode(json_encode($dbcolumns), true);
                    }
                    if(in_array($this->_auto->_db->type, ["pgsql"])) {
                        $dbcolumns = $this->_auto->_db->select("SELECT column_name as \"Field\", data_type as \"Type\" FROM information_schema.COLUMNS WHERE TABLE_NAME = '".$this->table."'");
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
                    if(!empty($this::$columns)) if(array_key_exists($key, $this::$columns)) $type = $this::$columns[$key];
                    if($alias) if(!empty($this::$columns)) if(array_key_exists($keyo, $this::$columns)) $type = $this::$columns[$keyo];
                    $dbtype = array_search($key, array_column($dbcolumns, 'Field'));
                    if($type === false && $dbtype === false) continue;
                    if($type === false)
                        $type = $dbcolumns[$dbtype]["Type"];

                    $type = strtolower(preg_replace('/[0-9]/', "", $type));


                    // revert $key with alias
                    if($alias) {
                        $key = $keyo;
                    }

                    $result[$key] = self::whereCompleteProcess($value, $key, $type);

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

                //echo $value[0] . "aaaa".$value;
                //echo strpos($value, "a")."----".$value;

                if (strpos($value, ",") || strpos($value, ".")) $value = str_replace(',', '.', str_replace('.', '', $value));

                if (strpos($value, ":") !== false){ // number range [0] e [1]
                    $valueArray = explode(":",$value);
                    if($valueArray[0] <= $valueArray[1])
                        $q .="(" .$key ." BETWEEN ".$valueArray[0]." AND ".$valueArray[1].")";
                }else{
                    if (strpos($value, ">") !== false){ // number [0] bigger than [1]
                        $value = str_replace(">", "", $value);
                        $q .=$key ." >".$value."";
                    }else if (strpos($value, "<") !== false){ // number [1] bigger than [0]
                        $value = str_replace("<", "", $value);
                        $q .=$key ." <".$value."";
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
                        $q .="(" .$key ." <= '".self::parseDatetimeDb($valueArray[1])."')";
                    }elseif($valueArray[1] =="*"){
                        $q .="(" .$key ." >= '".self::parseDatetimeDb($valueArray[0])."')";
                    }else{
                        $q .="(" .$key ." BETWEEN '".self::parseDatetimeDb($valueArray[0])."' AND '".self::parseDatetimeDb($valueArray[1])."')";
                    }
                    //echo $q;
                }else{
                    $oper = null;
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
                        $q .= $key . $oper ." '".self::parseDatetimeDb($value)."'";
                    }else{
                        $tomorrow = strtotime(self::parseDatetimeDb($value) . " +1 day");
                        if(count(explode(" ",$value))>1){ // datetime
                            $tomorrow = date('Y-m-d H:i:s',$tomorrow);
                        }else{ // date
                            $tomorrow = date('Y-m-d',$tomorrow);
                        }
                        $q .= $key ." >= '".self::parseDatetimeDb($value)."' and ".$key." <= '".$tomorrow."'";
                    }
                }
                break;
            case "between_columns":
                $keyArray = explode("|",$key);

                $q .= "('" . self::parseDatetimeDb($value) . "' BETWEEN cast(" . $keyArray[0] . " as date) 
                            AND cast(" . $keyArray[1] . " as date) )";

                break;
            case "tinyint":
            case "tinyint()":
            case "bool":
            case "boolean":
                $q .=" (";
                //echo $value = boolval($value);
                if ($value == false || $value === 'false' || $value == 0 ){
                    $q .=  '('.$key.' = false) OR ('.$key.' IS NULL)';
                }else{
                    $q .= $key.' = true';
                }
                $q .= ")";
                break;
            case "null":
                if($value)
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

//        dd($this->or);
//        dd($result);
//        dd($sql);

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

//            dd($or);
//            dd($result);

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

//            dd($sql);

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

    public static function parseDate($date){
        if(!$date) return $date;
        if( strpos($date,"/") ) return $date; // format correct
        $date = explode('-',$date);
        $retorno = $date[2]."/".$date[1]."/".$date[0];
        return $retorno;
    }
    public static function parseDateDb($date){
        if(!$date) return $date;
        if( strpos($date,"-") ) return $date; // format correct
        $retorno = "";
        $date = explode('/',$date);
        if(count($date) > 2) {
            $retorno = $date[2] . "-" . $date[1] . "-" . $date[0];
        }else{
            $retorno = $date[0];
        }
        return $retorno;
    }

    public static function parseDatetime($datetime){
        if(!$datetime) return $datetime;
        if(!isset(explode(' ',$datetime)[1])) return self::parseDate($datetime);
        if( strpos(explode(' ',$datetime)[0],"/") ) return $datetime; // format correct
        $time = explode(':',explode(' ',$datetime)[1] );
        $date = explode('-', explode(' ',$datetime)[0] );
        $retorno = $date[2]."/".$date[1]."/".$date[0]." ".$time[0].":".$time[1];
        if($time[2] != "00") $retorno .= ":".$time[2];
        return $retorno;
    }
    public static function parseDatetimeDb($datetime){
        if(!$datetime) return $datetime;
        if(!isset(explode(' ',$datetime)[1])) return self::parseDateDb($datetime);
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
        $value = preg_replace("/[^0-9.,-]/", '',  $value);
        if(substr_count($value, '.') > 1) $value = str_replace('.', '', $value);
        $value = floatval(str_replace(',', '.', $value));
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

}
?>