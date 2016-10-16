<?php
/**
 *          Db(Frame\Db.class.php)
 *
 *    功　　能：数据库操作基础类，提供数据库操作公共接口，数据库访问适配器
 *
 *    作　　者：李康
 *    完成时间：2015/08/14
 *
 */
namespace Core;

abstract  class Db{
    // 数据库操作对象资源池
    static private    $instance   = array();
    // 当前数据库操作对象
    static private    $_instance  = null;

    /**
     * 获取一个数据库操作实例 工厂方法创建一个数据库操作对象
     * @param array $config
     * @return null
     */
    static public function getInstance($config=array()){
        // 将配置信息作为与之对应的数据库操作对象唯一标识，进行md5混淆操作，配置信息发生改变时，自动创建一个新的数据库操作对象
        $id = md5(serialize($config));
        // 如果资源池中没有与之对应的实例，则进行创建
        if (!isset(self::$instance[$id])) {
            // 解析配置数组
            $option = self::parseConfig($config);
            // 得到类全名
            $class = 'Core\\Driver\\DB\\' . ucwords(strtolower($option['type']));
            // 类文件是否存在，系统自动调用auto_load函数去加载类文件
            if (class_exists($class)) {
                // 实例化一个对象
                self::$instance[$id] = new $class($option);
            } else {
                E('该类未定义');
            }
        }
        // 设置当前数据库操作对象
        self::$_instance = self::$instance[$id];
        // 返回当前数据库操作对象
        return self::$_instance;
    }
    
    static private function parseConfig($config){
        if(!empty($config)){
            if(is_string($config)){
                return self::parseDsn($config);
            }
            $config = array_change_key_case($config);
            $config = array(
                'type'          =>  $config['db_type'],
                'username'      =>  $config['db_user'],
                'password'      =>  $config['db_pwd'],
                'hostname'      =>  $config['db_host'],
                'hostport'      =>  $config['db_port'],
                'database'      =>  $config['db_name'],
                'dsn'           =>  isset($config['db_dsn'])?$config['db_dsn']:null,
                'params'        =>  isset($config['db_params'])?$config['db_params']:null,
                'charset'       =>  isset($config['db_charset'])?$config['db_charset']:'utf8',
            );
        }else{
            $config = array(
                'type'          =>  C('DB_TYPE'),
                'hostname'      =>  C('DB_HOST'),
                'username'      =>  C('DB_USER'),
                'password'      =>  C('DB_PWD'),
                'hostport'      =>  C('DB_PORT'),
                'database'      =>  C('DB_NAME'),
                'dsn'           =>  C('DB_DSN'),
                'params'        =>  C('DB_PARAMS'),
                'charset'       =>  C('DB_CHARSET',null,'utf8'),
            );
        }
        return $config;
    }
    static private function parseDsn($config){
        return $config;
    }
    /**
     * 绑定参数值至bind[]数组中
     * @param unknown $name
     * @param unknown $value
     */
    protected function bindParam($name,$value){
        $this->bind[':'.$name] = $value;
    }
    /**
     * 参数绑定分析
     * @param unknown $bind
     * @return multitype:
     */
    protected function parseBind($bind){
        $bind = array_merge($this->bind,$bind);
        $this->bind = array();
        return $bind;
    }
    /**
     * 设置锁
     * @param string $lock
     * @return string
     */
    protected function parseLock($lock=false){
        if(!$lock) return '';
        if('ORACLE' == $this->dbType){
            return ' FOR UPDATE NOWAIT ';
        }
        return ' FOR UPDATE ';
    }
    /**
     * 解析SQL语句中的字段值
     * 合法性处理
     * @param unknown $value
     * @return Ambigous <string, multitype:>
     */
    protected function parseValue($value){
        if(is_string($value)){
            $value = '\''.$this->escapeString($value).'\'';
        }elseif(isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp'){
            $value = $this->escapeString($value[1]);
        }elseif(is_array($value)){
            $value = array_map(array($this,'parseValue'), $value);
        }elseif(is_bool($value)){
            $value = $value?'1':'0';
        }elseif(is_null($value)){
            $value = 'null';
        }
        return $value;
    }
    /**
     * 解析SQL关键字 DINSTINCT
     * @param  string   $str
     * @return string
     */
    protected function parseDinstinct($TorF){
        return !empty($TorF)? ' DINSTINCT ' : '';
    }
    /**
     * 解析SQL语句中字段，定义别名时自动处理
     * @param unknown $fields
     * @return string
     */
    protected function parseField($fields){
        $fieldsStr = '';
        if(is_string($fields) && strpos($fields, ',')){
            $fields = explode(',', $fields);
        }
        if(is_array($fields)){
            $arr = array();
            foreach ($fields as $key=>$field){
                if(!is_numeric($key)){//字段别名
                    $arr[] = $this->parseKey($key).' AS '.$this->parseKey($field);
                }else{
                    $arr[] = $this->parseKey($field);
                }
            }
            $fieldsStr = implode(',', $arr);
        }elseif(is_string($fields) && !empty($fields)){
            $fieldsStr = $this->parseKey($fields);
        }else{
            $fieldsStr = '*';
        }
        return $fieldsStr;
    }
    /**
     * 解析SQL语句中关键字Join
     * @param unknown $join
     * @return string
     */
    protected function parseJoin($join){
        $joinStr = '';
        if(!empty($join)){
            $joinStr = ' '.implode(' ',$join).' ';
        }
        return $joinStr;
    }
    /**
     * 解析SQL语句关键字WHERE
     * @param unknown $where
     * @return string
     */
    protected function parseWhere($where){
        $whereStr = '';
        if(is_string($where)){
            $whereStr = $where;
        }else{
            $operate = isset($where['_logic'])?strtoupper($where['_logic']):'';
            if(in_array($operate, array('AND','OR','XOR'))){
                $operate = ' '.$operate.' ';
                unset($where['_logic']);
            }else{
                $operate = ' AND ';
            }
            foreach ($where as $key=>$val){
                $whereStr .= '( ';
                if(is_numeric($key)){
                    $key = '_complex';
                }
                if(0===strpos($key, '_')){
                    switch ($key){
                        case '_string':
                            $whereStr .= $val;
                            break;
                        case '_complex':
                            $whereStr .=is_string($val)? $val : substr($this->parseWhere($val), 6);
                            break;
                        case '_query':
                            parse_str($val,$where);
                            if(isset($where['_logic'])){
                                $op = ' '.strtoupper($where['_logic']).' ';
                                unset($where['_logic']);
                            }else{
                                $op = ' AND ';
                            }
                            $arr = array();
                            foreach($where as $field=>$data){
                                $arr[] = $this->parseKey($field).' = '.$this->parseValue($data);
                            }
                            $whereStr .= implode($op, $arr);
                    }
                }else{
                    if(!preg_match('/^[A-Z_\|\&\-.a-z0-9\(\)\,]+$/', trim($key))){
                        E('表达式错误：'.$key);
                    }
                    $multi = is_array($val) && isset($val['_multi']);//多条件
                    $key   = trim($key);
                    if(strpos($key, '|')){
                        $arr   = explode('|', $key);
                        $str   = array();
                        foreach($arr as $m=>$k){
                            $v = $multi?$val[$m]:$val;
                            $str[] = '('.$this->parseWhereItem($this->parseKey($k), $v).')';
                        }
                        $whereStr .= implode(' OR ', $str);
                    }elseif(strpos($key, '&')){
                        $arr = explode('&', $key);
                        $str = array();
                        foreach($arr as $m=>$k){
                            $v = $multi?$val[$m]:$val;
                            $str[] = '('.$this->parseWhereItem($this->parseKey($k), $v).')';
                        }
                        $whereStr .= implode(' AND ',$str);
                    }else{
                        $whereStr .= $this->parseWhereItem($this->parseKey($k), $val);
                    }
                }
                $whereStr .= ' )'.$operate;
            }//end foreach
            $whereStr = substr($whereStr,0,-strlen($operate));//去掉最后一个多余的$operate
        }
        return empty($whereStr)?'':' WHERE '.$whereStr;
    }
    protected function parseWhereItem($key,$val){
        $whereStr = '';
        if(is_array($val)){
            if(is_string($val[0])){
                if(preg_match('/^(EQ|NEQ|GT|EGT|LT|ELT)$/i', $val[0])){
                    $whereStr .= $key.' '.$this->comparison[strtolower($val[0])].' '.$this->parseValue($val[1]);
                }elseif(preg_match('/^(NOTLIKE|LIKE)$/i', $val[0])){
                    if(is_array($val[1])){
                        $likeLogic = isset($val[2])?strtoupper($val[2]):'OR';
                        if(in_array($likeLogic, array('AND','OR','XOR'))){
                            $likeStr = $this->comparison[strtolower($val[0])];
                            $like    = array();
                            foreach ($val[1] as $item){
                                $like[] = $key.' '.$likeLogic.' '.$this->parseValue($item);
                            }
                            $whereStr .= '('.implode(' ', $likeLogic.' ',$like).')';
                        }
                    }else{
                        $whereStr .= $key.' '.$this->comparison[strtolower($val[0])].$this->parseValue($val[1]);
                    }
                }elseif('exp'==strtolower($val[0])){
                    $whereStr .= ' ('.$key.' '.$val[1].') ';
                }elseif(preg_match('/IN/i', $val[0])){
                    if(isset($val[2]) && 'exp'==$val[2]){
                        $whereStr .= $key.' '.strtoupper($val[0]).' '.$val[1];
                    }else{
                        if(is_string($val[1])){
                            $val[1] = explode(',', $val[1]);
                        }
                        $area = implode(',', $this->parseValue($val[1]));
                        $whereStr .= $key.' '.strtoupper($val[0]).' ('.$area.')';
                    }
                }elseif(preg_match('/BETWEEN/i', $val[0])){
                    $data = is_string($val[1])?explode(',', $val[1]):$val[1];
                    $whereStr .= ' ('.$key.' '.strtoupper($val[0]).' '.$this->parseValue($data[0]).' AND '.$this->parseValue($data[1]).') ';
                }else{
                    E('表达式错误：'.$val[0]);
                }
            }else{
                $count = count($val);
                $rule  = isset($val[$count-1])?(is_array($val[$count-1])?strtoupper($val[$count-1][0]):strtoupper($val[$count-1])):'';
                if(in_array($rule, array('AND','OR','XOR'))){
                    $count = $count-1;
                }else{
                    $rule = 'AND';
                }
                for($i=0;$i<$count;$i++){
                    $data = is_array($val[$i])?$val[$i][1]:$val[$i];
                    if('exp'==strtolower($val[$i][0])){
                        $whereStr .= '('.$key.' '.$data.')'.$rule.' ';
                    }else{
                        $whereStr .= '('.$this->parseWhereItem($key, $val[$i]).') '.$rule.' ';
                    }
                }
                $whereStr = substr($whereStr,0,-4);
            }
        }else{
            $whereStr .= $key.' = '.$this->parseValue($val);
        }
        return $whereStr;
    }
    /**
     * 解析SQL语句关键字GROUP
     * @param unknown $group
     * @return string
     */
    protected function parseGroup($group){
        return !empty($group)? ' GROUP BY '.$group:'';
    }
    /**
     * 解析SQL语句关键字HAVING
     * @param unknown $having
     * @return string
     */
    protected function parseHaving($having){
        return !empty($having)? ' HAVING '.$having:'';
    }
    protected function parseOrder($order){
        if(is_array($order)){
            $arr = array();
            foreach($order as $key=>$val){
                if(is_numeric($key)){
                    $array[] = $this->parseKey($val);
                }else{
                    $array[] = $this->parseKey($key).' '.$val;
                }
            }
            $order = implode(',', $arr);
        }
        return !empty($order)? ' ORDER BY '.$order:'';
    }
    /**
     * 解析SQL语句LIMIT关键字
     * @param unknown $limit
     * @return string
     */
    protected function parseLimit($limit){
        return !empty($limit)?' LIMIT '.$limit.' ':'';
    }
    /**
     * 解析SQL语句关键字UNION
     * @param unknown $union
     * @return string
     */
    protected function parseUnion($union){
        if(empty($union)) return'';
        if(isset($union['_all'])){
            $str = 'UNION ALL ';
            unset($union['_all']);
        }else{
            $str = 'UNION ';
        }
        $sql = array();
        foreach($union as $u){
            $sql[] = $str.(is_array($u)) ? $this->buildSelectSql($u) : $u;
        }
        return implode(' ', $sql);
    }
    /**
     * 解析SQL语句中的注释
     * @param unknown $comment
     * @return string
     */
    protected function parseComment($comment){
        return !empty($comment)?' /*'.$comment.' */':'';
    }
    /**
     *  表名分析
     * @param string|array $tables
     * @return string
     */
    protected function parseTable($tables){
        if(is_array($tables)){
            $arr = array();
            foreach ($tables as $table=>$alias){
                if(is_numeric($table)){
                    $arr[] = $this->parseKey($table).' '.$this->parseKey($alias);//别名表解析
                }else{
                    $arr[] = $this->parseKey($table);
                }
                $tables = $arr;
            }
        }elseif(is_string($tables)){
            $tables = explode(',', $tables);
            array_walk($tables, array(&$this,'parseKey'));
        }
        $tables = implode(',', $tables);
        return $tables;
    }
    /**
     * 解析SQL语句
     * @param string $sql
     * @param array $option
     * @return mixed
     */
    protected function parseSql($sql,$option){
        $sql = str_replace(
            array('%TABLE%','%DISTINCT%','%FIELD%','%JOIN%','%WHERE%','%GROUP%','%HAVING%','%ORDER%','%LIMIT%','%UNION%','%COMMENT%'),
            array(
                $this->parseTable($option['table']),
                $this->parseDinstinct(isset($option['distinct'])?$option['distinct']:false),
                $this->parseField(!empty($option['field'])?$option['field']:'*'),
                $this->parseJoin(!empty($option['join'])?$option['join']:''),
                $this->parseWhere(!empty($option['where'])?$option['where']:''),
                $this->parseGroup(!empty($option['group'])?$option['group']:''),
                $this->parseHaving(!empty($option['having'])?$option['having']:''),
                $this->parseOrder(!empty($option['order'])?$option['order']:''),
                $this->parseLimit(!empty($option['limit'])?$option['limit']:''),
                $this->parseUnion(!empty($option['union'])?$option['union']:''),
                $this->parseComment(!empty($option['comment'])?$option['comment']:''),
            ),$sql);
        return $sql;
    }

    /**
     * 增加记录
     * @access public
     * @param mixed $data 数据
     * @param array $options 参数表达式
     * @param boolean $replace 是否replace
     * @return false | integer
     */
    public function insert($data,$options=array(),$replace=false){
        $values = $fields = array();
        $this->modelName = $options['model'];
        foreach($data as $key=>$val){
            if(is_array($val) && 'exp' == $val[0]){
                $fields[] = $this->parseKey($key);
                $values[] = $val[1];
            }elseif(is_scalar($val || is_null($val))){
                $fields[] = $this->parseKey($key);//字段名处理
                if(C('DB_BIND_PARAM') &&　0 !==strpos($val, ':')){//绑定参数
                    $name = md5($key);
                    $values[] = ':'.$name;
                    $this->bindParam($name,$val);
                }else{//未绑定参数
                    $values[] = $this->parseValue($val);
                }
            }
        }
        $sql  = ($replace?'REPLACE':'INSERT').' INTO '
            .$this->parseTable($options['table'])
            .' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')'
            .$this->parseLock(isset($options['lock'])?$options['lock']:false)
            .$this->parseComment(!empty($options['commnet'])?$options['commnet']:'');
        return $this->execute($sql,$this->parseBind(!empty($options['bind'])?$options['bind']:array()));
    }
    /**
     * 删除记录
     * @param unknown $options
     */
    public function delete($options=array()){
        $this->modelName = $options['model'];
        $sql = 'DELETE FROM '
            .$this->parseTable($options['table'])
            .$this->parseWhere(!empty($options['where'])?$options['where']:'')
            .$this->parseOrder(!empty($options['oeder'])?$options['order']:'')
            .$this->parseLimit(!empty($options['limit'])?$options['limit']:'')
            .$this->parseLock(isset($options['lock'])?$options['lock']:false)
            .$this->parseComment(!empty($options['comment'])?$options['comment']:'');
        return $this->execute($sql,$this->parseBind(!empty($options['bind'])?$options['bind']:array()));
    }
    /**
     * 查询记录
     * @param unknown $options
     * @return unknown
     */
    public function select($options=array()){
        $this->modelName = $options['model'];
        $sql             = $this->buildSelectSql($options);
        $result          = $this->query($sql,$this->parseBind(!empty($options['bind'])?$options['bind']:array()));
        return $result;
    }
    /**
     * 选择插入数据
     */
    public function selectInsert($fields,$table,$options=array()){
        $this->modelName = $options['model'];
        if(is_string($fields)) $fields = explode(',', $fields);
        array_walk($fields, array($this,'parseKey'));
        $sql  = 'INSERT INTO '.$this->parseTable($table).' ('.implode(',', $fields).') ';
        $sql .= $this->buildSelectSql($options);
        return $this->execute($sql,$this->parseBind(!empty($options['bind'])? $options['bind'] : array()));
    }
    /**
     * 更新记录
     * @param unknown $data
     * @param unknown $options
     */
    public function update($data,$options){
        $this->modelName = $options['model'];
        foreach ($data as $key=>$val){
            if(is_array($val) && 'exp' == $val[0]){
                $set[] = $this->parseKey($key).'='.$val[1];
            }elseif (is_scalar($val) || is_null($val)){
                if(C('DB_BIND_PARAM') && 0 !== strpos($val, ':')){
                    $name = md5($key);
                    $set[] = $this->parseKey($key).'=:'.$name;
                    $this->bindParam($name, $val);
                }else{
                    $set[] = $this->parseKey($key).'='.$this->parseValue($val);
                }
            }
        }
        $setStr = ' SET '.implode(',', $set);
        $sql = 'UPDATE '
            .$this->parseTable($options['table'])
            .$setStr
            .$this->parseWhere(!empty($options['where'])?$options['where']:'')
            .$this->parseOrder(!empty($options['order'])?$options['order']:'')
            .$this->parseLimit(!empty($options['limit'])?$options['limit']:'')
            .$this->parseLock(!empty($options['lock'])?$options['lock']:false)
            .$this->parseComment(!empty($options['comment'])?$options['comment']:'');
        
        return $this->execute($sql,$this->parseBind(!empty($options['bind'])?$options['bind']:array()));    
    }
    /**
     * 生成SQLselect字符串
     * @param unknown $options
     * @return string
     */
    public function buildSelectSql($options=array()){
        //如果设置了分页，处理分页
        if(isset($options['page'])){
            if(strpos($options['page'], ',')){
                list($page,$listRows) = explode(',', $options['page']);
            }else{
                $page = $options['page'];
            }
            $page     = $page?$page:1;
            $listRows = isset($listRows)?$listRows:(is_numeric($options['limit'])?$options['limit']:20);
            $offset   = $listRows*((int)$page-1);
            $options['limit'] = $offset.','.$listRows;
        }
        $sql  = $this->parseSql($this->selectSql, $options);
        $sql .= $this->parseLock(isset($options['lock'])?$options['lock']:false);
        return $sql;
    }
    /**
     * 返回模型的错误信息
     * @access private
     * @return string
     */
    public function getError(){
        return $this->error;
    }
    /**
     * 返回最后插入的ID
     * @access private
     * @return string
     */
    public function getLastInsertID(){
        return $this->lastInsertID;
    }
    /**
     * 返回最后执行的sql语句
     * @access private
     * @return string
     */
    public function getLastSql(){
        return $this->queryStr;
    }
    /**
     * 设置模型名
     * @access private
     * @param  string $name
     */
    public function setModel($name){
        $this->modelName = $name;
    }
    /////////////////////////////// 接口约束 ////////////////////////////////////////
    //字段约束
    //数据库类型
    protected  $dbType       = null;
    //数据库连接句柄数组 
    protected  $linkID       = array();
    //当前数据库连接句柄
    protected  $_linkID      = null;
    //数据库连接状态
    protected  $connected    = false;
    //数据库连接参数配置数组
    protected  $config       =array();
    protected  $numRows      = '';
    protected  $numCols      = '';
    //当前执行的SQL语句
    protected  $queryStr     = '';
    //最后插入记录ID
    protected  $lastInsertID = null;
    //当前操作的模型名
    protected  $modelName    = '';
    //当前查询结果集
    protected  $queryResult  = null;
    //受影响的行数
    protected  $affectedRows = 0;
    //事务指令数
    protected  $transTimes   = 0;
    //数据库表达式
    protected  $comparison   = array('eq'=>'=','neq'=>'<>','gt'=>'>','egt'=>'>=','lt'=>'<','elt'=>'<=','notlike'=>'NOT LIKE','like'=>'LIKE','in'=>'IN','notin'=>'NOT IN');
    //查询SQL语句格式串
    protected  $selectSql    = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%COMMENT%';
    //绑定的参数数组
    protected  $bind         = array();
    //执行sql语句的错误信息
    protected  $error        = '';


    //方法约束，对子类的实现进行规约,这部分是与特定数据库关联比较紧密的部分，不同数据库实现方式不尽相同
    //abstract protected function initConnect();
    abstract protected function escapeString($str);

    /**
     * 解析SQL语句中的关键字
     * @param $key
     * @return mixed
     */
    abstract protected function parseKey(&$key);

    /**
     * 释放当前的结果集
     */
    abstract protected function free();

    /**
     * 关闭当前数据库连接
     */
    abstract protected function close();

    /**
     * 执行一条SQL语句
     * @param $sql
     * @return mixed 执行失败返回false，执行成功返回结果集（数组）
     */
    abstract protected function query($sql);

    /**
     * 执行一条SQL语句，返回执行情况
     * @param $sql
     * @return mixed 如果执行成功返回受影响的行数，如果执行失败返回False
     */
    abstract protected function execute($sql);

    /**
     *
     * @param $dataList
     * @param $options
     * @param $replace
     * @return mixed
     */
    abstract public    function insertAll($dataList,$options,$replace);

    /**
     * 提交事务
     * @return mixed
     */
    abstract public    function commit();

    /**
     * 回滚事务
     * @return mixed
     */
    abstract public    function rollback();

    /**
     * 启动事务传输
     * @return mixed
     */
    abstract public    function startTrans();

    /**
     * 获取表字段信息数组
     * @param $tableName
     * @return array
     */
    abstract public    function getFields($tableName);
    
    /**
     * 析构方法
     */
    public function __destruct(){
        //检查并释放结果集
        if($this->queryResult){
            $this->free();
        }
        //关闭数据库连接
        $this->close();
    }
}
