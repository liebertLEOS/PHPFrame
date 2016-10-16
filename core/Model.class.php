<?php
/**
 *           Model(Frame\Model.class.php)
 *
 *    功　　能：模型类，提供了数据CURD操作接口
 *
 *    作　　者：李康
 *    完成时间：2015/08/14
 *
 */
namespace Core;
use Core\Db;

class Model{
    const MODEL_INSERT         = 1;
    const MODEL_UPDATE         = 2;
    const MODEL_BOTH           = 3;
    //模型名称
    protected $name            = '';
    //数据库名
    protected $dbName          = '';
    //数据库连接配置
    protected $connection      = '';
    //当前操作的数据库对象句柄
    protected $dbHandle        = null;
    //表名前缀
    protected $tablePrefix     = '';
    //表名
    protected $tableName       = '';
    //当前模型主键
    protected $pk              = 'id';
    //实际的表名 包括表名前缀
    protected $realTableName   = '';
    //当前错误信息
    protected $error           = '';
    //主键是否自动增长
    protected $autoinc         = false;
    //字段信息数组
    protected $fields          = array();
    //字段名-字段值  K-V 数组 
    protected $data            = array();
    //SQL语句暂存数组
    protected $options         = array();
    //字段名-字段别名 K-V 数组，用于存储用户自定义字段名和实际表名之间的映射关系
    protected $map             = array();
    //动态调用方法名数组
    protected $methods         = array('order','alias','having','group','lock','distinct','auto','filter','validate','result','token');
    
    public  function __construct($name='', $tablePrefix='', $connection=''){
        //初始化分离出去，继承此类时只需要用户对初始化方法重写即可
        $this->_initialize();

        if(!empty($name)){
            //分析 数据库名.表名
            if(strpos($name, '.')){
                list($this->dbName, $this->name) = explode('.', $name);
            }else{
                $this->name = $name;
            }
        }elseif(empty($this->name)){
            $this->getModelName();
        }

        //表名前缀设置
        if(is_null($tablePrefix)){
            $this->tablePrefix = '';
        }elseif('' != $tablePrefix){
            $this->tablePrefix = $tablePrefix;
        }elseif(!isset($this->$tablePrefix)){
            $this->tablePrefix = C('DB_PREFIX');
        }
        //初始化数据库
        $this->getDB(0,empty($this->connection)?$connection:$this->connection,true);
    }
    protected function _initialize(){ }
    /**
     * 重写set方法
     */
    public function __set($name, $value){
        $this->data[$name] = $value;
    }
    /**
     * 重写get方法
     */
    public function __get($name){
        return isset($this->data[$name])? $this->data[$name]:null;
    }
    /**
     * 检测对象值的情况
     */
    public function __isset($name){
        return isset($this->data[$name]);
    }
    /**
     * 销毁对象值
     */
    public function __unset($name){
        unset($this->data[$name]);
    }
    /**
     * 重写__call()方法，实现动态方法的调用
     */
    public function __call($method, $args){
        if(in_array(strtolower($method), $this->methods,true)){
            //连贯操作的实现
            $this->options[strtolower($method)] = $args[0];
            return $this;
        }elseif(in_array(strtolower($method),array('count','sum','min','max','avg'),true)){
            //统计查询
            $field = isset($args[0])?$args[0]:'*';
            return $this->getField(strtolower($method).'('.$field.') AS leos_'.$method);
        }elseif(strtolower(substr($method,0,5)) == 'getby'){
            //根据某个字段获取记录
            $field = parse_name(substr($method, 5));
            $where[$field] = $args[0];
            return $this->where($where)->find();
        }elseif(strtolower(substr($method,0,5)) == 'getfieldby'){
            //根据某个字段获取记录的某个值
            $name = parse_name(substr($method,10));
            $where[$name] = $args[0];
            return $this->where($where)->getField($args[1]);
        }elseif(isset($this->_scope[$method])){
            return $this->scope($method,$args[0]);
        }else{
            E(__CLASS__.':'.$method.'方法不存在');
            return;
        }
    }
    /*
     * 获取并设置当前模型名
     */
    public function getModelName(){
        if(empty($this->name)){
            $name = substr(get_class($this),0,-5);
            $pos = strrpos($name,'\\');
            if($pos){
                $this->name = substr($name, $pos+1);
            }else{
                $this->name = $name;
            }
        }
        return $this->name;
    }
    /**
     * 获取模型对应的表全名：[数据库名].[表前缀].[实际表名]
     * @return string
     */
    public function getTableName(){
        if(empty($this->realTableName)){
            $tableName = empty($this->tablePrefix) ? '' : $this->tablePrefix;
            if(!empty($this->tableName)){
                $tableName .= $this->tableName;
            }else{
                $tableName .= parse_name($this->name);//默认使用模型名称
            }
            $this->realTableName = strtolower($tableName);
        }
        return (!empty($this->dbName)?$this->dbName.'.':'').$this->realTableName;
    }
    /**
     * 获取数据表字段信息
     * @return boolean|Ambigous <boolean, multitype:>|multitype:
     */
    public function getDbFields(){
        if(isset($this->options['table'])){
            if(is_array($this->options['table'])){
                $table = key($this->options['table']);
            }else{
                $table = $this->options['table'];
                if(strpos($table, ')')){
                    return false;
                }
            }
            $fields = $this->dbHandle->getFields($table);
            return $fields ? array_keys($fields) : false;
        }

        if($this->fields){
            $fields = $this->fields;
            unset($fields['_type'],$fields['_pk']);
            return $fields;
        }
        return false;
    }
    /**
     * 获取一条记录的指定的字段值
     * @param string  $field      要查询的字段名
     * @param string  $separate   字段之间的分隔符
     */
    public function getField($field,$separate=null){
        $options['field'] = $field;
        $options          = $this->_parseOptions($options);
        //缓存字段信息，此处未实现
        $field = trim($field);
        if(strpos($field, ',')){//多字段处理
            if(!isset($options['limit'])){
                $options['limit'] = is_numeric($separate)?$separate:'';
            }
            $resultSet = $this->dbHandle->select($options);
            if(!empty($resultSet)){
                $_field = explode(',',$field);
                $field  = array_keys($resultSet[0]);//第一条记录的全部字段名
                $key    = array_shift($field);//第一个字段名
                $key2   = array_shift($field);//第二个字段名
                $cols   = array();
                $count  = count($_field);//要查询的字段数量
                foreach($resultSet as $result){
                    $name = $result[$key];//每条记录的第一个字段值，为主键值
                    if(2==$count){
                        $cols[$name] = $result[$key2];//如果只有两个字段，那么第一个字段值作为键第二个字段值为键值
                    }else{
                        $cols[$name] = is_string($separate)?implode($separate,$result):$result;
                    }
                }
                return $cols;
            }
        }else{//一个字段
            if(true != $separate){
                $options['limit'] = is_numeric($separate)?$separate:1;
            }
            $result = $this->dbHandle->select($options);
            if(!empty($result)){
                if(true !== $separate && 1==$options['limit']){
                    $data = reset($result[0]);
                    return $data;
                }
                foreach($result as $val){
                    $arr[] = $val[$field];
                }
                return $arr;
            }
        }
        return null;
    }
    /**
     * 获取主键名
     * @return string
     */
    public function getPk(){
        return $this->pk;
    }
    /**
     * 获取最后一条插入数据的id
     */
    public function getLastInsertID(){
        return $this->dbHandle->getLastInsertID();
    }
    /**
     * 对写入数据的数据进行合法性校验、安全过滤
     * @param unknown $data
     * @return multitype:
     */
    protected function _facade($data){
        //字段合法性校验
        if(!empty($this->fields)){
            if(!empty($this->options['field'])){
                $fields = $this->options['field'];
                unset($this->options['field']);
                if(is_string($fields)){
                    $fields = explode(',', $fields);
                }
            }else{
                $fields = $this->fields;
            }
            foreach ($data as $key=>$val){
                if(in_array($key, $fields,true)){
                    unset($data[$key]);
                }elseif(is_scalar($val)){
                    $this->_parseType($data, $key);
                }
            }
        }
        //安全过滤
        if(!empty($this->options['filter'])){
            $data = array_map($this->options['filter'],$data);
            unset($this->options['filter']);
        }
        return $data;
    }
    /**
     * 从映射数组中读取对应字段信息，赋值数组中，删除原字段信息
     * @param unknown $data
     * @return unknown
     */
    protected function _read_data($data){
        if(!empty($this->map) && C('READ_DATA_MAP')){
            foreach($this->map as $key=>$val){
                if(isset($data[$val])){
                    $data[$key] = $data[$val];
                    unset($data[$val]);
                }
            }
        }
        return $data;
    }
    //SQL语句暂存数组过滤
    protected function _options_filter(&$options){
    
    }
    /**
     * 数据类型校验
     * @param mixed   $data   待校验数组
     * @param string  $key    字段名
     */
    protected function _parseType(&$data,$key){
        if(!isset($this->options['bind'][':'.$key]) && isset($this->fields['_type'][$key])){
            $fieldType = strtolower($this->fields['_type'][$key]);
            if(false !== strpos($fieldType,'enum')){
                
            }elseif(false === strpos($fieldType,'bigint') && false !== strpos($fieldType,'int')){
                $data[$key] = intval($data[$key]);                
            }elseif(false !== strpos($fieldType,'float') || false !== strpos($fieldType,'double')){
                $data[$key] = floatval($data[$key]);
            }elseif(false !== strpos($fieldType,'bool')){
                $data[$key] = (bool)($data[$key]);
            }
        }
    }
    /**
     * 解析SQL语句暂存数组
     * @param  array  $options
     * @return array  $options
     */
    protected function _parseOptions($options=array()){
        //获取参数
        if(is_array($options)){
            $options = array_merge($this->options,$options);
        }
        //表名
        if(!isset($options['table'])){
            $options['table'] = $this->getTableName();
            $fields           = $this->fields;
        }else{
            $fields = $this->getDbFields();
        }
        //表别名
        if(!empty($options['alias'])){
            $options['table'] .= ' '.$options['alias'];
        }
        //模型名
        $options['model'] = $this->name;
        //字段验证
        if(isset($options['where']) && is_array($options['where']) && !empty($fields) && !isset($options['join'])){
            //检查查询语句条件中字段的类型合法性
            foreach($options['where'] as $key=>$val){
                $key = trim($key);
                if(in_array($key, $fields,true)){
                    if(is_scalar($val)){
                        $this->_parseType($options['where'],$key);
                    }
                }elseif(!is_numeric($key) && '_' != substr($key,0,1) && false === strpos($key, '.')){
                    if(!empty($this->options['strict'])){
                        E('查询表达式错误，表中不存在此字段'.':['.$key.']');
                    }
                    unset($options['where'][$key]);//销毁非法字段
                }
            }
        }
        //清空数组，避免影响下次sql组装数组
        $this->options = array();
        $this->_options_filter($options);
        return $options;
    }
    /**
     * 解析SQL语句
     * @param string   $sql    待解析的SQL语句
     * @param mixed    $parse  解析选项
     * @return string  $sql    解析后的SQL语句
     */
    protected function _parseSql($sql,$parse){
        if(true === $parse){
            $options = $this->_parseOptions();
            $sql     = $this->dbHandle->parseSql($sql,$options); 
        }elseif(is_array($parse)){//sql预处理
            $parse = array_map(array($this->dbHandle,'escapeString'), $parse);
            $sql   = vsprintf($sql,$parse);
        }else{
            $sql = strtr($sql, array('__TABLE__'=>$this->getTableName(),'__PREFIX__'=>C('DB_PREFIX')));
        }
        $this->db->setModel($this->name);
        return $sql;
    }
    /**
     * 返回查询的结果集
     * @param array   $data  查询返回的结果数组
     * @param string  $type
     * @return mixed
     */
    protected function returnResult($data,$type=''){
        if($type){
            if(is_callable($type)){
                return call_user_func($type,$data);
            }
            switch (strtolower($type)){
                case 'json':
                    return json_encode($data);
                case 'xml':
                    return xml_encode($data);
            }
        }
    }
    /*
     * 实例化一个数据对象并获取其句柄
     * @param int     $linkNum           数据库对象句柄号
     * @param string  $connectionParam   数据库连接参数
     * */
    protected function getDB($linkNum='',$connection='',$force=''){
        //必须指定链接号

        /*
        if('' === $linkNum) return false;

        static $dbArray = array();
        if(isset($dbArray[$linkNum])){
            if($force){
                $dbArray[$linkNum]->close();
                unset($dbArray[$linkNum]);
                $dbArray[$linkNum] = Db::getInstance($connection);
            }
        }else{
            $dbArray[$linkNum] = Db::getInstance($connection);
        }
         */
        if(is_null($this->dbHandle)){
            $this->dbHandle = Db::getInstance($connection);
        }
        return $this->dbHandle;
    }
    /**
     * 增加记录
     * @param  array   $data      要增加的记录值数组，如果该参数为空，则使用对象data数组
     * @param  unknown $options
     * @param  string  $replace
     * @return boolean|unknown
     */
    public function add($data='',$options=array(),$replace=false){
        if(empty($data)){
            if(!empty($this->data)){
                $data = $this->data;
                $this->data = array();
            }else{
                $this->error = '请输入要添加的数据';
                return false;
            }
        }
        $options = $this->_parseOptions($options);
        $data    = $this->_facade($data);//合法性校验
        //写入至数据库
        $result = $this->dbHandle->insert($data,$options,$replace);
        if(false !== $result){
            $insertId = $this->getLastInsertID();
            if($insertId){
                $data[$this->getPk()] = $insertId;
                return $insertId;
            }
        }
        return $result;
    }
    public function selectAdd($fields='',$table='',$options=array()){
        $options = $this->_parseOptions($options);
        if(false === $result=$this->dbHandle->selectInsert($fields?$fields:$options['fields'],$table?$table:$this->getTableName(),$options)){
            $this->error = 'selectAdd操作失败';
            return false;
        }else{
            return $result;
        }
    }
    /**
     * 删除记录
     * @param array $options SQL数组
     * @return Ambigous <boolean, unknown>|boolean|unknown
     */
    public function delete($options=array()){
        if(empty($options) && empty($this->options['where'])){
            if(!empty($this->data) && isset($this->data[$this->getPk()])){
                return $this->delete($this->data[$this->getPk()]);
            }else{
                return false;
            }
        }
        $pk = $this->getPk();
        if(is_numeric($options) || is_string($options)){
            if(strpos($options, ',')){
                $where[$pk] = array('IN',$options);
            }else{
                $where[$pk] = $options;
            }
            $options          = array();
            $options['where'] = $where;
        }
        $options = $this->_parseOptions($options);
        if(is_array($options['where']) && isset($options['where'][$pk])){
            $pkValue = $options['where'][$pk];
        }
        $result = $this->dbHandle->delete($options);
        if(false !== $result){
            $data = array();
            if(isset($pkValue)) $data[$pk] = $pkValue;
        }
        return $result;
    }
    /**
     * 查询记录
     * @param unknown $options
     * @return string|boolean|NULL|multitype:
     */
    public function select($options=array()){
        $pk = $this->getPk();
        if(is_string($options) || is_numeric($options)){
            if(strpos($options,',')){
                $where[$pk]  = array('IN',$options);
            }else{
                $where[$pk]  = $options;
            }
            $options = array();
            $options['where'] = $where;
        }elseif(false === $options){
            $options = array();
            $options = $this->_parseOptions($options);
            return '( '.$this->dbHandle->buildSelectSql($options).' )';
        }
        $options    = $this->_parseOptions($options);
        $resultSet  = $this->dbHandle->select($options);
        if(false === $resultSet){
            return false;
        }
        if(empty($resultSet)){
            return null;
        }
        $resultSet = array_map(array($this,'_read_data'), $resultSet);
        return $resultSet;
    }
    /**
     * 查询一条记录
     * @param  array $options SQL语句暂存数组
     * @return boolean|NULL|unknown|Ambigous <multitype:, unknown>
     */
    public function find($options=array()){
        if(is_numeric($options) || is_string($options)){
            $where[$this->getPk()] = $options;
            $options               = array();
            $options['where']      = $where;
        }
        $pk = $this->getPk();//获取主键
        //复合主键查询
        if(is_array($options) && (count($options) > 0) && is_array($pk)){
            
        }
        $options['limit'] = 1;
        $options          = $this->_parseOptions($options);
        $resultSet        = $this->dbHandle->select($options);
        if(false === $resultSet){
            return false;
        }
        if(empty($resultSet)){
            return null;
        }
        if(is_string($resultSet)){
            return $resultSet;
        }
        //结果处理
        $data = $this->_read_data($resultSet[0]);
        if(!empty($this->options['result'])){
            return $this->returnResult($data,$this->options['result']);
        }
        $this->data = $data;
        return $this->data;
    }
    public function save($data='',$options=array()){
        if(empty($data)){
            if(empty($this->data)){
                $this->error = '请输入用于跟新的数据';
                return false;
            }else{
                $data = $this->data;
                $this->data = array();
            }  
        }
        $data    = $this->_facade($data);
        $options = $this->_parseOptions($options);
        $pk      = $this->getPk();//主键名
        if(!isset($options['where'])){
            if(isset($data[$pk])){
                $where[$pk] = $data[$pk];
                $options['where'] = $where;
                unset($data[$pk]);//主键不能参加更新，此处必须销毁
            }else{
                $this->error = '未输入记录的唯一性标识，系统无法获知要更新哪些记录';
                return false;
            }
        }
        if(is_array($options['where']) && isset($options['where'][$pk])){
            $pkValue = $options['where'][$pk];
        }
        $result = $this->dbHandle->update($data,$options);
        if(false !== $result){
            if(isset($pkValue)) $data[$pk] = $pkValue;
        }
        return $result;
    }
    public function join($join,$type='INNER'){
        $prefix = $this->tablePrefix;
        if(is_array($join)){
            foreach ($join as $key=>&$_join){
                $_join = preg_replace_callback('/__([A-Z_-]+)__/sU', function($matches) use($prefix){return $prefix.strtolower($matches[1]);}, $_join);
                $_join = false !== stripos($_join, 'JOIN')? $_join : $type.' JOIN '.$_join;
            }
            $this->options['join'] = $join;
        }elseif(!empty($join)){
            $join = preg_replace_callback('/__([A-Z_-]+)__/sU', function($matches)use($prefix){return $prefix.strtolower($matches[1]);}, $join);
            $this->options['join'][] = false !== stripos($join, 'JOIN')? $join : $type.' JOIN '.$join;
        }
        return $this;
    }
    public function union($union,$all=false){
        if(!empty($union)){
            if($all){
                $this->options['union']['_all'] = true;
            }
            if(is_object($union)){
                $union = get_object_vars($union);
            }
            if(is_string($union)){
                $prefix = $this->tablePrefix;
                $options = preg_replace('/__([A-Z_-]+)/sU', function($matches)use($prefix){return $prefix.strtolower($matches[1]);}, $union);
            }
            if(is_array($union)){
                if(isset($union[0])){
                    $this->options['union'] = array_merge($this->options['union'],$union);
                }else{
                    $options = $union;
                }
            }
            $this->options['union'][] = $options;
        }
        return $this;
    }
    /**
     * 赋值查询条件
     * @param mixed $where   条件表达式
     * @param mixed $param   参数
     * @return \Core\Model
     */
    public function where($where,$param=null){
        if(!is_null($param) && is_string($where)){
            if(!is_array($param)){
                $param = func_get_arg();
                array_shift($param);
            }
            $param = array_map(array($this->dbHandle,'escapeString'),$param);
            $where = vsprintf($where, $param);
        }elseif (is_object($where)){
            $where = get_object_vars($where);
        }
        if(is_string($where) && '' != $where){
            $map = array();
            $map['_string'] = $where;
            $where = $map;
        }
        if(isset($this->options['where'])){
            $this->options['where'] = array_merge($this->options['where'],$where);
        }else{
            $this->options['where'] = $where;
        }
        return $this;
    }
    /**
     * 指定SQL语句 字段名
     * @param unknown $field
     * @param string $except
     * @return \Core\Model
     */
    public function field($field, $except=false){
        if(true === $field){
            $fields = $this->getDbFields();
            $field  = $fields?$fields:'*';
        }elseif($except){
            if(is_string($field)){//先转换成数组
                $field = explode(',',$field);
            }
            $fields = $this->getDbFields();
            $field  = $fields?array_diff($fields, $field):$field;
        }
        $this->options['field'] = $field;
        return $this;
    }
    
    /**
     * 提取当前的数据表
     * @param unknown $table
     */
    public function table($table){
        $prefix = $this->tablePrefix;
        if(is_array($table)){
            $this->options['table'] = $table;
        }elseif(!empty($table)){
            // 完整表名：前缀+表名
            $table = preg_replace_callback('/__([A-Z0-9_-]+)__/sU', function($matches) use($prefix){return $prefix.strtolower($matches[1]);}, $table);
            $this->options['table'] = $table;
        }
        return $this;
    }
    /**
     * 指定查询数量
     * @param unknown $offset
     * @param string $length
     * @return \Core\Model
     */
    public function limit($offset,$length=null){
        $this->options['limit'] =   is_null($length)?$offset:$offset.','.$length;
        return $this;
    }
    /**
     * 指定分页
     * @param unknown $page
     * @param string $listRows
     * @return \Core\Model
     */
    public function page($page,$listRows=null){
        $this->options['page'] = is_null($listRows)? $page : $page.','.$listRows;
        return $this;
    }
    /**
     * 提交事务
     */
    public function commit(){
        return $this->dbHandle->commit();
    }
    /**
     * 回滚事务
     */
    public function rollback(){
        return $this->dbHandle->rollback();
    }
    /**
     * 启动事务
     */
    public function startTrans(){
        $this->dbHandle->commit();
        $this->dbHandle->startTrans();
        return ;
    }
    
    
    
    
    
    
    
    
    
}
