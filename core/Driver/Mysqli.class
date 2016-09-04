<?php
/**
 *          Mysqli(Frame\Driver\DB\Mysqli.class.php)
 *
 *    功　　能：MySQL数据库访问接口
 *
 *    作　　者：李康
 *    完成时间：2015/08/14
 *    修改时间：2016/08/26
 *
 */
namespace Frame\Driver\DB;
use Frame\Db;

class Mysqli extends Db{
 
    public function __construct($config=''){
        if(!empty($config)){
            $this->config = $config;
            if(empty($this->config['params'])){
                $this->config['params'] = '';
            }
        }
    }

    /**
     * 创建并返回数据库连接
     * @param string $config          数据库配置参数
     * @param int    $linkNum         数据库连接资源号
     * @return mixed                  返回一个数据库连接资源
     */
    private function connect($config='',$linkNum=0){
        // 根据资源号检查数据库连接资源池中对应资源是否存在
        if(!isset($this->linkID[$linkNum])){
            // 如果数据库配置资源为空，则使用默认配置参数
            if(empty($config)) $config = $this->config;
            /**
             * 实例化：\mysqli()  前面的'\'不可去掉(表示根命名空间)，不然默认是当前命名空间的类冲突
             * 此处的错误查找了半天时间才找出来，犯这样的低级错误还是基本功不扎实
             */
            $this->linkID[$linkNum] = new \mysqli($config['hostname'],$config['username'],$config['password'],$config['database'],$config['hostport']?$config['hostport']:3306);
            // 检测并显示数据库错误信息
            if(mysqli_connect_error()) E('[数据库连接错误]'.mysqli_connect_errno().':'.mysqli_connect_error());
            // 设置数据库编码
            $this->linkID[$linkNum]->query("SET NAMES '".C('DB_CHARSET')."'");
            // 当前连接状态为正常
            $this->connected = true;
        }
        // 返回资源号对应的数据库连接资源
        return $this->linkID[$linkNum];
    }

    /**
     * 以关联数组的格式从结果集中获取全部的数据
     * @return array 结果数组
     */
    private function getAll(){
        $result = array();
        if($this->numRows>=0){
            // 循环读取数据并存入数组中
            for($i=0;$i<$this->numRows;$i++){
                $result[$i] = $this->queryResult->fetch_assoc();//关联数组 【K：字段名】<-->【V：数据】
            }
            // 复位数据指针
            $this->queryResult->data_seek(0);
        }
        // 返回结果数组
        return $result;
    }

    /**
     * 返回执行SQL错误信息
     * @return string
     */
    private function error(){
        $this->error = $this->_linkID->errno.':'.$this->_linkID->error;
        if('' != $this->queryStr){
            $this->error .= "\n [SQL语句]:".$this->queryStr;
        }
        return $this->error;
    }
    protected function escapeString($str){
        if($this->_linkID){
            return $this->_linkID->real_escape_string($str);
        }else{
            return addslashes($str);
        }
    }
    /**
     * 释放当前暂存的结果集
     */
    protected function free(){
        $this->queryResult->free_result();
        $this->queryResult = null;
    }
    /**
     * 关闭当前数据库连接
     * @see \Frame\Db::close()
     */
    protected function close(){
        if($this->_linkID){
            $this->_linkID->close();
        }
        $this->_linkID = null;
    }
    //接口方法
    /**
     * 执行一条SQL语句并返回结果
     * @param $sql
     * @return array|bool 如果执行失败返回false，执行成功返回结果集
     */
    public function query($sql){
        // 创建数据库连接
        $this->_linkID =$this->Connect();
        // 当前数据库连接资源不存在，返回False
        if(!$this->_linkID) return false;
        // 记录当前正在执行的SQL
        $this->queryStr = $sql;
        // 销毁之前的结果集
        if($this->queryResult) $this->free();
        // 执行SQL，保存最新结果集
        $this->queryResult = $this->_linkID->query($sql);
        // 释放其余结果集
        if($this->_linkID->more_results()){
            while(($results = $this->_linkID->next_result()) != NULL){
                $results->free_result();
            }
        }
        // 如果当前结果集为false，输出错误信息
        if(false === $this->queryResult){
            E($this->error());
            return false;
        }else{// 返回全部结果
            $this->numRows = $this->queryResult->num_rows;    // 暂存记录数量
            $this->numCols = $this->queryResult->field_count; // 暂存字段数量
            return $this->getAll();
        }
    }
    /**
     * 执行一条sql语句
     * @param  string $sql    sql语句字符串
     * @return int            返回受影响的行数
     * @see                   \Frame\Db::execute()
     */
    public function execute($sql){
        $this->_linkID = $this->Connect();
        if(!$this->_linkID) return false;
        $this->queryStr = $sql;
        if($this->queryResult) $this->free();
        $result = $this->_linkID->query($sql);
        if(false === $result){
            $this->error();
            return false;
        }else{
            $this->affectedRows = $this->_linkID->affected_rows;
            $this->lastInsertID = $this->_linkID->insert_id;//记录id，每次插入后都会更新
            return $this->affectedRows;
        }
    }
    public function insertAll($dataList,$options,$replace){
        
    }
    /**
     * 提交事务
     * @access public
     * @return boolean 提交成功返回true，失败返回false
     */
    public function commit(){
        //有事务时进行提交
        if($this->transTimes > 0){
            $result = $this->_linkID->commit();
            $this->_linkID->autocommit(true);
            $this->transTimes = 0;//提交后清空标识
            if(!$result){
                $this->error();
                return false;
            }
        }
        return true;
    }
    /**
     * 事务回滚
     * @access public
     * @return boolean
     * @see \Frame\Db::rollback()
     */
    public function rollback(){
        if($this->transTimes > 0){//有事务时进行回滚
            $result = $this->_linkID->rollback();
            $this->_linkID->autocommit(true);//开启自动提交
            $this->transTimes = 0;
            if(!result){
                $this->error();
                return false;
            }
        }
        return true;
    }
    /**
     * 启动事务
     * @access public
     * @return void
     */
    public function startTrans(){
        $this->_linkID = $this->connect();//连接数据库
        if($this->transTimes == 0){
            $this->_linkID->autocommit(false);//关闭自动提交
        }
        $this->transTimes++;
        return;
    }
    /**
     * 获取字段信息数组
     * @param string $tableName
     * @return array
     */
    public function getFields($tableName){
        $result = $this->query('SHOW COLUMNS FROM '.$this->parseKey($tableName));
        $info   = array();
        if($result){
            foreach ($result as $key => $val){
                $info[$val['Field']] = array(
                    'name'    => $val['Field'],
                    'type'    => $val['Type'],
                    'notnull' => (bool)($val['Null'] === ''),
                    'primary' => (strtolower($val['Key']) == 'pri'),
                    'autoinc' => (strtolower($val['Extra']) == 'auto_increment')
                );
            }
        }
        return $info;
    }

    /**
     * 解析SQL中的关键字
     * @param $key
     * @return string
     */
    protected function parseKey(&$key){
        $key = trim($key);// 过滤空白字符

        if(!preg_match('/[,\'\"\*\(\)`.\s]/',$key)){
            $key = '`'.$key.'`'; // MYSQL数据库中的字段需要使用`[字段名]`括起来
        }
        return $key;
    }

}
