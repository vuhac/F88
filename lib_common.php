<?php
// ----------------------------------------
// Features:	前後台共用設定檔
// File Name:	lib_common.php
// Author:		Barkley
// Related:
// Log:
// 2017.2.3 update
// -----------------------------------------------------------------------------


// ------------------------------------------------------------------------------------------------
// PostGreSQL 常用的 Function
// ------------------------------------------------------------------------------------------------

function get_pdo_object($sqlact='w')
{
    // ref:http://php.net/manual/en/book.pdo.php
    // db 帳號密碼變數 global
    global  $pdo;

    // 当读写分离的主机设定都存在的时候, 才使用读写分离的设定
    if(isset($pdo['host4write']) AND isset($pdo['host'])) {
        // DB主機讀寫分離
        if(strtolower($sqlact) == 'w'){
            $pdo_host = $pdo['host4write'];
        }else{
            $pdo_host = $pdo['host'];
        }
    }else{
        // 没有设定档的时候, 就停止强迫使用者更新 DB config , 以后可以加入多台读取的主机
        die('Lost DB PDO config.');
        // $pdo_host = $pdo['host'];
    }

    // 建立 DB 連線
    try {
        $dbh_string = $pdo['db'].':dbname='.$pdo['dbname'].';host='.$pdo_host;
        $dbh = new PDO("$dbh_string", $pdo['user'], $pdo['password'] );
    } catch (PDOException $e) {
        print "DB connect Error!: " . $e->getMessage() . "<br/>";
        die();
    }

    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $dbh;
}

// ---------------------------------------------------------------------
// run SQL command with prepare then return $result
// $result[0] ~ $result[n] --> 資料內容，從第 [0] 開始
// 使用方式 example:
// $sql = 'SELECT * FROM root_statisticsdailyreport WHERE date = :date';
// $result = runSQLall($sql, [':date' => '2017-12-20']);
// var_dump($result);
//
// $fetch_classname --> for PDO::FETCH_CLASS
// ref: https://stackoverflow.com/questions/5137051/pdo-php-fetch-class
//
// $debug --> 除錯資訊顯示 1 , 不顯示 0
// ---------------------------------------------------------------------
function runSQLall_prepared($sql="SET NAMES 'utf8';", $prepare_array = [], $fetch_classname="", $debug="0", $sqlact='w')
{
  // 取得PDO物件
  $dbh = get_pdo_object($sqlact);

  if(strtolower($sqlact) == 'w'){
      $stime = 10000;
  }else{
      $stime = 0;
  }

  try{
    $dbstatus = $dbh->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
    $info = $dbh->getAttribute(\PDO::ATTR_SERVER_INFO);
    if (is_null($info)) {
        echo "---DB server has gone away\n";
    }
    // sql 執行
    $sth = $dbh->prepare("$sql");
    $db_dump_result_all = NULL;
    // 如果執行成功, 就把資料以 FETCH_OBJ 方式拿出來
    if($sth->execute($prepare_array)) {
        // 所有資料取出, 會花費時間儲存變數
        if(empty($fetch_classname))
            $db_dump_result_all = $sth->fetchAll(PDO::FETCH_OBJ);
        else
            $db_dump_result_all = $sth->fetchAll(PDO::FETCH_CLASS, $fetch_classname);

    }else{
        // 請參考 postgresql error code 對應表 https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
        $debug_message = "runSQLall_prepared ERROR: ["
            . "\nerrorCode:".$sth->errorCode()
            . "\ninfo:".$sth->errorInfo()[2]
            . "\nDB status:".$dbstatus."]\n";

        if($debug == 1) {
            var_dump($sql);
        }
        error_log( date("Y-m-d H:i:s").' '.$debug_message.' SQL:'.$sql);
        $db_dump_result_all = FALSE;
        echo "$debug_message";
        die();
    }

    // 顯示除錯資訊
    if($debug == 1) {
        var_dump($sql);
    }
    // 設定讀寫分離，需於寫入時等待約 10 亳秒的時間讓MASTER DB資料寫入SLAVE DB
    // usleep($stime);
  } catch(\Exception $e) {
      echo "ErrorCode:" . $e->getCode() . ",ErrorMsg:" . $e->getMessage();
  }
  $dbh = null;

  return($db_dump_result_all);
}
// ---------------------------------------------------------------------



// ---------------------------------------------------------------------
// run SQL command then return $result
// $result[0] --> 資料數量, 如果為 0 表示沒有變動的列
// $result[1] ~ $result[n] --> 資料內容，從第 [1] 開始
// 使用方式 example:
// $result = runSQLall($sql);
// var_dump($result);
//
// $debug --> 除錯資訊顯示 1 , 不顯示 0
// $cache --> 使用 memcache = 1, 不使用 memcache = 0 --> todo
// $cache_timeout --> 時間 timeout = 600 sec  --> todo
// ---------------------------------------------------------------------
function runSQLall($sql="SET NAMES 'utf8';", $debug="0", $sqlact='w')
{
  // 取得PDO物件
  $dbh = get_pdo_object($sqlact);

  if(strtolower($sqlact) == 'w'){
      $stime = 10000;
  }else{
      $stime = 0;
  }

  try{
    $dbstatus = $dbh->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
    $info = $dbh->getAttribute(\PDO::ATTR_SERVER_INFO);
    if (is_null($info)) {
        echo "---DB server has gone away\n";
    }

    // sql 執行
    $sth = $dbh->prepare("$sql");
    $db_dump_result_all = NULL;
    // 如果執行成功, 就把資料以 FETCH_OBJ 方式拿出來
    if($sth->execute()) {
        // 放置紀錄數量
        $db_dump_result_all[0] = $sth->rowCount();

        if($debug == 1) {
                var_dump($sql);
        }
        // 所有資料取出, 會花費時間儲存變數
        $i=1;
        while($db_dump_result = $sth->fetch(PDO::FETCH_OBJ)) {
            if($debug >= 2) {
                var_dump($db_dump_result);
            }
            $db_dump_result_all[$i] = $db_dump_result;
            $i++;
        }
    }else{
        // 請參考 postgresql error code 對應表 https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
        $debug_message = "runSQLall ERROR: ["
            . "\nerrorCode:".$sth->errorCode()
            . "\ninfo:".$sth->errorInfo()[2]
            . "\nDB status:".$dbstatus."]\n";

        if($debug == 1) {
            var_dump($sql);
        }
        error_log( date("Y-m-d H:i:s").' '.$debug_message.' SQL:'.$sql);
        $db_dump_result_all = FALSE;
        echo "$debug_message";
        die();
    }

    // 顯示除錯資訊
    if($debug == 1) {
        var_dump($sql);
    }
    // 設定讀寫分離，需於寫入時等待約 10 亳秒的時間讓MASTER DB資料寫入SLAVE DB
    usleep($stime);
  } catch(\Exception $e) {
      echo "ErrorCode:" . $e->getCode() . ",ErrorMsg:" . $e->getMessage();
  }
  $dbh = null;

  return($db_dump_result_all);
}
// ---------------------------------------------------------------------

// ---------------------------------------------------------------------
// run SQL command then return $result
// $result --> 資料數量, 如果為 0 表示沒有變動的列
//
// 使用方式 example:
// $result = runSQL($sql);
// var_dump($result);
//
// $debug --> 除錯資訊顯示 1 , 不顯示 0
// $cache --> 使用 memcache = 1, 不使用 memcache = 0 --> todo
// $cache_timeout --> 時間 timeout = 600 sec  --> todo
// ---------------------------------------------------------------------
function runSQL($sql="SET NAMES 'utf8';", $debug="0", $sqlact='w',$error_callback = null)
{
  // 取得PDO物件
  $dbh = get_pdo_object($sqlact);

  if(strtolower($sqlact) == 'w'){
      $stime = 10000;
  }else{
      $stime = 0;
  }

  try{
    $dbstatus = $dbh->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
    $info = $dbh->getAttribute(\PDO::ATTR_SERVER_INFO);
    if (is_null($info)) {
        echo "---DB server has gone away\n";
    }

    $sth = $dbh->prepare("$sql");
    $db_dump_result_num = NULL;
    // 如果執行成功, 就把資料以 FETCH_OBJ 方式拿出來
    if($sth->execute()) {
        // 放置紀錄數量
        $db_dump_result_num = $sth->rowCount();
    }else{

        // 請參考 postgresql error code 對應表 https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
        // $debug_message = "[runSQL ERROR:".$sth->errorCode()."]".$sql;
        $debug_message = "runSQL ERROR: ["
            . "\nerrorCode:".$sth->errorCode()
            . "\ninfo:".$sth->errorInfo()[2]
            . "\nDB status:".$dbstatus."]\n";

        if($debug == 1) {
            var_dump($sql);
            print_r($sql);
        }
        if(!empty($error_callback)) {
            $error_callback($sql);
	       }
        error_log( date("Y-m-d H:i:s").' '.$debug_message.' SQL:'.$sql);
        $db_dump_result_num = FALSE;
        echo $debug_message;
        die();
    }

    // 顯示除錯資訊
    if($debug == 1) {
        var_dump($sql);
        var_dump($db_dump_result_num);
    }
    // 設定讀寫分離，需於寫入時等待約 10 亳秒的時間讓MASTER DB資料寫入SLAVE DB
    usleep($stime);
  } catch(\Exception $e) {
      echo "ErrorCode:" . $e->getCode() . ",ErrorMsg:" . $e->getMessage();
  }
  $dbh = null;

    // 回傳受影響的列
    return($db_dump_result_num);
}
// ---------------------------------------------------------------------
// $sql = 'select * from "dot1dTpFdbTable" limit 5;';
// var_dump(runSQLall($sql));


// ---------------------------------------------------------------------
// run SQL command使用交易方式確保成功 ACID then return $result
// 使用方式 example:
// $result = runSQLtransactions($sql);
// var_dump($result);
// success = 1 , false = 0
// ref: http://php.net/manual/en/pdo.transactions.php
// ---------------------------------------------------------------------
function runSQLtransactions($sql="SET NAMES 'utf8';", $sqlact='w')
{
  // 取得PDO物件
  $dbh = get_pdo_object($sqlact);

  if(strtolower($sqlact) == 'w'){
      $stime = 10000;
  }else{
      $stime = 0;
  }

  try{
    $dbstatus = $dbh->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
    $info = $dbh->getAttribute(\PDO::ATTR_SERVER_INFO);
    if (is_null($info)) {
        echo "---DB server has gone away\n";
    }

    // var_dump($sql);

    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbh->beginTransaction();
    $dbh->exec($sql);
    $dbh->commit();
  } catch (Exception $e) {
    // 請參考 postgresql error code 對應表 https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
    $debug_message = "[runSQLtransactions ERROR:".$dbh->errorCode(). $dbstatus."]";
    error_log( date("Y-m-d H:i:s").' '.$debug_message.' SQL:'.$sql);
    echo $debug_message;

    $dbh->rollBack();
    echo "Failed: " . $e->getMessage();
    return(0);
  }
    // 設定讀寫分離，需於寫入時等待約 10 亳秒的時間讓MASTER DB資料寫入SLAVE DB
  usleep($stime);
  $dbh = null;

  return(1);
}
// ---------------------------------------------------------------------


// ---------------------------------------------------------------------
// 紀錄程式
// example: memberlog2db('mtchang','login','info');
// example: memberlog2db('使用者','服務','訊息等級','訊息內容');
// 預設會紀錄 client ip , client browser 指紋碼,
// ---------------------------------------------------------------------
function memberlog2db($who = 'guest', $service, $message_level, $message = NULL) {
    global $config;

        //$who = 'guest';
        //$service = 'login';
        //$message_level = 'info';

    // 定義log level所包含要記錄的訊息層級
    $log_level_list = [
    	 'debug' => [ 'notice', 'info',	'error', 'warning' ],
    	 'warning' => [ 'info', 'error', 'warning' ],
    	 'error' => [ 'info', 'error' ]
    ];

    $s = '';

    if(in_array($message_level,$log_level_list[$config['log_level']])){
      // 應用程式的訊息資訊
      $message = filter_var($message, FILTER_SANITIZE_MAGIC_QUOTES);

      // 操作人員的 web http remote ip
      if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
          $agent_ip = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'];
      }else{
          $agent_ip = 'no_remote_addr';
      }

      // 操作人員使用的 browser 指紋碼, 有可能會沒有指紋碼. JS close 的時候會發生
      if(isset($_SESSION['fingertracker'])) {
          $fingertracker = $_SESSION['fingertracker'];
      }else{
          $fingertracker = 'no_fingerprinting';
      }

      // 執行的程式檔名 - client
      if(isset($_SERVER['SCRIPT_NAME'])){
          $script_name = filter_var($_SERVER['SCRIPT_NAME'], FILTER_SANITIZE_MAGIC_QUOTES);
      }else{
          $script_name = 'no_script_name';
      }

      // 瀏覽器資訊 - client
      if(isset($_SERVER['HTTP_USER_AGENT'])) {
          $http_user_agent = filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_MAGIC_QUOTES);
      }else{
          $http_user_agent = 'no_http_user_agent';
      }

      // 使用者的 cookie 資訊
      if(isset($_SERVER['HTTP_COOKIE'])) {
          $http_cookie = filter_var($_SERVER['HTTP_COOKIE'], FILTER_SANITIZE_MAGIC_QUOTES);
      }else{
          $http_cookie ='no_cookie';
      }

      // 使用 $_GET 的傳入網址
      if(isset($_SERVER['QUERY_STRING'])) {
          $query_string = filter_var($_SERVER['QUERY_STRING'], FILTER_SANITIZE_MAGIC_QUOTES);
      }else{
          $query_string = 'no_query_string';
      }

      $sql = 'INSERT INTO "root_memberlog" ("who", "service", "message_level" , "agent_ip", "message", "fingerprinting_id", "script_name", "http_user_agent", "http_cookie", "query_string")'.
      " VALUES ('$who', '$service', '$message_level' , '$agent_ip', '$message', '$fingertracker', '$script_name', '$http_user_agent', '$http_cookie', '$query_string');";
      //var_dump($sql);

      $s = runSQL($sql, 0,'w');
      // var_dump($s);
    }
return($s);
}
// syslog2db('mtchang','login','info');
// ---------------------------------------------------------------------

//20180802 yaoyuan紀錄程式
function memberlogtodb($who = 'guest', $service, $message_level, $message = NULL,$target_user=NULL,$message_log=NULL,$site='b',$sub_service=NULL) {
    global $config;

        //$who = 'guest';
        //$service = 'login';
        //$message_level = 'info';

    // 定義log level所包含要記錄的訊息層級
    $log_level_list = [
    	 'debug' => [ 'notice', 'info',	'error', 'warning' ],
    	 'warning' => [ 'info', 'error', 'warning' ],
    	 'error' => [ 'info', 'error' ]
    ];

    $s = '';

    if(in_array($message_level,$log_level_list[$config['log_level']])){
        // 應用程式的訊息資訊
        $message = filter_var($message, FILTER_SANITIZE_STRING,FILTER_FLAG_NO_ENCODE_QUOTES) ;

        // 操作人員的 web http remote ip
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $agent_ip = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'];
        }else{
            // $agent_ip = 'no_remote_addr';
            $agent_ip = '0.0.0.0';
        }

        // IP所在區域
        $curl_ip_data = curl_ip_region($agent_ip);
        foreach($curl_ip_data as $v){
            if(isset($v['country_en']) AND $v['country_en'] != ''){
                $ip_location = $v['country_en']." ".$v['city_en']; // 國家 城市
            }else{
                $ip_location = 'no_ip_region';
            }
        }

        // 操作人員使用的 browser 指紋碼, 有可能會沒有指紋碼. JS close 的時候會發生
        if(isset($_SESSION['fingertracker'])) {
            $fingertracker = $_SESSION['fingertracker'];
        }else{
            $fingertracker = 'no_fingerprinting';
        }

        // 執行的程式檔名 - client
        if(isset($_SERVER['SCRIPT_NAME'])){
            $script_name = filter_var($_SERVER['SCRIPT_NAME'], FILTER_SANITIZE_MAGIC_QUOTES);
        }else{
            $script_name = 'no_script_name';
        }

        // 瀏覽器資訊 - client
        if(isset($_SERVER['HTTP_USER_AGENT'])) {
            $http_user_agent = filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_MAGIC_QUOTES);
        }else{
            $http_user_agent = 'no_http_user_agent';
        }

        // 使用者的 cookie 資訊
        if(isset($_SERVER['HTTP_COOKIE'])) {
            $http_cookie = filter_var($_SERVER['HTTP_COOKIE'], FILTER_SANITIZE_MAGIC_QUOTES);
        }else{
            $http_cookie = 'no_cookie';
        }

        // 使用 $_GET 的傳入網址
        if(isset($_SERVER['QUERY_STRING'])) {
            $query_string = filter_var($_SERVER['QUERY_STRING'], FILTER_SANITIZE_MAGIC_QUOTES);
        }else{
            $query_string = 'no_query_string';
        }

        $sql = 'INSERT INTO "root_memberlog" ("who", "service", "message_level" , "agent_ip", "message", "fingerprinting_id", "script_name", "http_user_agent", "http_cookie", "query_string",
            "target_users","message_log","site","sub_service","ip_region")'.
        " VALUES ('$who', '$service', '$message_level' , '$agent_ip', '$message', '$fingertracker', '$script_name', '$http_user_agent', '$http_cookie', '$query_string',
            '$target_user', '$message_log', '$site','$sub_service','$ip_location');";
        // echo($sql);die();

        $s = runSQL($sql, 0,'w');

        // 2019.06.11 改為runsql執行
        // $s = runSQLall_prepared($sql, [':message_log' => $message_log]);

        // var_dump($s);
    }

    return($s);
}
// ---------------------------------------------------------------------

// curl IP到IP來源API，透過MAXMIND做查詢IP所在地
// 送到IP來源API的IP須包成陣列
function curl_ip_region($user_ip_data){
    global $config;

	$ip_to_array = array($user_ip_data);
    $header = ['Content-type: application/x-www-form-urlencoded;charset=utf-8'];

    $ch = curl_init();
    $options = array(
        CURLOPT_URL				=> $config['ip_region_url'], // 設定欲抓取的網址
        CURLOPT_HTTPHEADER      => $header, // 設置一個header中傳輸內容的數組
        CURLOPT_CUSTOMREQUEST   => 'POST',  // post
        CURLOPT_POSTFIELDS		=> http_build_query($ip_to_array), // post參數
        CURLOPT_SSL_VERIFYPEER  => false, // 規避ssl的檢查
        CURLOPT_RETURNTRANSFER  => true, // 只傳回結果，不輸出在畫面上
        CURLOPT_TIMEOUT         => 30 // 允許執行的最長秒數
    );

    curl_setopt_array($ch,$options);
    $curl_result = curl_exec($ch);

    if($curl_result == false){
        echo curl_error($ch);
        exit();
    };
    curl_close($ch);

    return json_decode($curl_result,true);
};

// ---------------------------------------------------------------------
// 紀錄程式
// example: memberlog2db('mtchang','login','info');
// example: memberlog2db('使用者','服務','訊息等級','訊息內容');
// ---------------------------------------------------------------------
/*
function memberlog2db($who = 'guest', $service, $message_level, $message = NULL) {

    //$who = 'guest';
    //$service = 'login';
    //$message_level = 'info';

    // 取得儲存於 session 中的 name and id , 當使用者登入成功時. 紀錄這些 id.
    //session_name();
    //session_id();

    // default 的資訊
    $app_message = session_name().','.session_id().','.$_SERVER["SCRIPT_NAME"].','.$_SERVER["HTTP_USER_AGENT"];

    // 如果 message 沒有資料的時候, 以上面這些資訊為主
    if($message == NULL) {
        $message = filter_var($app_message, FILTER_SANITIZE_MAGIC_QUOTES);
    }else{
        $message = $message.','.$app_message;
        $message = filter_var($message, FILTER_SANITIZE_MAGIC_QUOTES);
    }

    $agent_ip = $_SERVER["REMOTE_ADDR"];

    $sql = 'INSERT INTO "root_memberlog" ("who", "service", "message_level" , "agent_ip", "message")'." VALUES ('$who', '$service', '$message_level' , '$agent_ip', '$message');";
    //var_dump($sql);

    $s = runSQL($sql);
    // var_dump($s);

return($s);
}
// syslog2db('mtchang','login','info');
*/


/**
 * SQL 分頁器
 * example: see statistics_daily_output_cmd.php
 */
class Paginator {

  private $limit;
  private $page;
  private $query;
  public $total;
    public $runSQLCallback;

  public function __construct($query, $limit = 20, $runSQLCallback = null)
  {

        $this->query = $query;
    $this->runSQLCallback = $runSQLCallback;
    $this->limit = $limit;
    $this->page = 1;

    $rs = $this->runSQL("SELECT COUNT(*) AS count FROM( $query ) AS subquery");
    $this->total = (array_pop($rs))->count;
  }

    private function runSQL($sql)
    {
        return empty($this->runSQLCallback) ? runSQLall_prepared($sql) : ($this->runSQLCallback)($sql);
    }

  public function hasNextPage()
  {
    return $this->page * $this->limit < $this->total;
  }

  public function getCurrentPage()
  {
    return $this->getData($this->page);
  }

  public function getNextPage()
  {
    return $this->getData($this->page + 1);
  }

  public function getData($page = 1)
  {
    $this->page  = $page;

    if ( $this->limit == 'all' ) {
        $query = $this->query;
    } else {
        $query = $this->query . " LIMIT " . $this->limit . " OFFSET " .  ( ( $this->page - 1 ) * $this->limit );
    }
    $results = $this->runSQL($query);

    $result = new stdClass();
    $result->page = $this->page;
    $result->limit = $this->limit;
    $result->total = $this->total;
    $result->data = $results;

    return $result;
  }

}


/**
 * 批次SQL執行器
 * example: see statistics_daily_output_cmd.php
 */
Class BatchedSqlExecutor {

  private $sql_buffer = [];
  private $batched_execute_threshold = 100;

  function __construct($batched_execute_threshold = 100)
  {
    $this->batched_execute_threshold = $batched_execute_threshold;
  }

  public function isBufferFull()
  {
    return count($this->sql_buffer) >= $this->batched_execute_threshold;
  }

  public function push($sql, $prepared_parameter = [])
  {
    if(empty($sql)) return;

    ($this->sql_buffer)[] = $sql;

    // batched execute when buffer is full
    if($this->isBufferFull()) {
      $this->execute();
    }
  }

  public function execute()
  {
    if(empty($this->sql_buffer)) return;

    $sql = implode($this->sql_buffer, ';');
    runSQLtransactions($sql);
    $this->sql_buffer = [];
  }

}

/**
 * 自定義型別轉換
 *
 * 使用方法
 * $converter = new TypeConverter
 * $converter->add($input)->{ n 個要轉換的型別，可能先轉浮點再轉字串，之間用箭頭串聯 }
 * $converver->commit 取得轉換後的變數
 *
 * 註: $variable 可以在 heredoc 中使用
 */
class TypeConverter
{
    protected $before = null;
    protected $buffer = null;
    protected $after = null;

    public function add($param)
    {
        $this->before = $param;
        $this->buffer = $param;
        $this->after = $param;
        return $this;
    }

    public function toFloat()
    {
        $this->buffer = floatval($this->buffer);
        return $this;
    }

    public function toString()
    {
        $this->buffer = (string) ($this->buffer);
        return $this;
    }

    public function sprintf($format = '%.2f')
    {
        $this->buffer = sprintf($format, $this->buffer);
        return $this;
    }

    public function numberFormat($decimals=2, $decimalpoint='.', $separator=',')
    {
        $this->buffer = number_format($this->buffer, $decimals, $decimalpoint, $separator);
        return $this;
    }

    public function commit()
    {
        $this->after = $this->buffer;
        $this->buffer = null;
        return $this->after;
    }
}

/**
 * 簡單的資料庫讀取抽象類
 */
abstract class ADataAccess
{
  protected $table;
  public $stmtOptions = [
    'table' => '',
    'where' => '',
    'orderBy' => '',
    'limit' => 'all',
    'offset' => 0
  ];
  protected $exists = false;
  protected $primary = 'id';
  protected $debugMode = 0;
  protected $sqlAct = 'r';
  protected $tmpl = 'SELECT * FROM %s WHERE %s ORDER BY %s LIMIT %s OFFSET %s';
  protected $sql = '';

  public function __construct()
  {
    $this->stmtOptions['table'] = $this->table;
    $this->stmtOptions['where'] = 'TRUE';
    $this->stmtOptions['orderBy'] = $this->primary;
    $this->stmtOptions['orderSeq'] = 'ASC';
  }

  public function save()
  {

  }

  public function delete()
  {

  }

  public function find($value)
  {
    $condition = is_array($value) ? implode("','", $value) : $value;
    $sql = <<<SQL
      SELECT * from $this->table WHERE "$this->primary" IN ('$condition')
SQL;
    echo $sql;
    return runSQLall_prepared($sql, [], get_class($this), $this->debugMode, 'r');
  }

  public function get($column=[])
  {
    $this->sql = vsprintf($this->tmpl, $this->stmtOptions);
    return runSQLall_prepared($this->sql, [], get_class($this), $this->debugMode, 'r');
  }
}

/**
 * 對應 10 分鐘報表、日報的 transaction_detail 模型
 *
 * 使用方法 $val->transaction_detail = new TransactionDetail($val->transaction_detail, $transaction_category);
 * 其中 $val 為每一條 10 分鐘/日報的資料
 *
 * 會將 json 型態的 transaction_detail 轉換成 TransactionDetail 物件
 */
class TransactionDetail
{
    protected $jsonRaw = '';
    protected $data;
    protected $subtotalDeposit = [
        'gcash' => 0,
        'gtoken' => 0
    ];
    protected $subtotalWithdrawal = [
        'gcash' => 0,
        'gtoken' => 0
    ];
    protected $siteDeposit = [
        'gcash' => 0,
        'gtoken' => 0
    ];
    protected $siteWithdrawal = [
        'gcash' => 0,
        'gtoken' => 0
    ];
    protected $systemTranscationCategories = [];
    const DEPOSIT_CATS = [
        // gcash
        'cashdeposit',
        'apicashdeposit',
        'payonlinedeposit',
        'company_deposits',
        // gtoken
        'tokendeposit',
        'apitokendeposit',
    ];
    const WITHDRAWAL_CATS = [
        'cashwithdrawal',
        'apicashwithdrawal',
        'apitokenwithdrawal',
    ];
    private $point = 2;

    public function __construct($jsonRaw, $systemTranscationCategories)
    {
        $this->jsonRaw = $jsonRaw;
        $this->systemTranscationCategories = $systemTranscationCategories;
        foreach ($this->systemTranscationCategories as $systemTransactionCategory => $systemTransactionCategoryDescription) {
            $this->$systemTransactionCategory = 0;
        }
        $this->data = json_decode($this->jsonRaw);

        if (!empty($this->data)) {
            $this->setSubtotalDeposit();
            $this->setSubtotalWithdrawal();
            $this->setNormal();
            $this->setSiteData();
        }
    }

    /**
     * 這裡定義了一般的交易類別統計值；不分實際存提、不分貨幣類別
     */
    private function setNormal()
    {
        foreach ($this->data as $transactionCategory => $transactionCategoryInfo) {
            foreach ($transactionCategoryInfo as $currency => $realcashInfo) {
                foreach ($realcashInfo as $key => $value) {
                    $this->$transactionCategory += $value;
                }
            }
        }
    }

    // 實際存提為 1 的入款總計, >0 (不分貨幣、不分類別)
    public function getTotalDeposit(): float
    {
        return array_sum($this->subtotalDeposit);
    }

    // 實際存提為 1 的出款總計, <0 (不分貨幣、不分類別)
    public function getTotalWithdrawal(): float
    {
        return array_sum($this->subtotalWithdrawal);
    }

    /**
     *  存提差額(總)
     * @return float
     */
    public function getTotalBalance(): float
    {
        $d_sum = intval($this->getTotalDeposit() * 10 ** $this->point);
        $w_sum = intval($this->getTotalWithdrawal() * 10 ** $this->point);

        return ($d_sum + $w_sum) / 10 ** $this->point;
    }

    /**
     * 計算後台顯示用的出入款(現金|遊戲幣)
     * 不區分實際存提
     *
     * @return void
     */
    public function setSiteData()
    {
        foreach ($this->data as $transactionCategory => $transactionCategoryInfo) {
            foreach ($transactionCategoryInfo as $currency => $realcashInfo) {
                in_array($transactionCategory, self::DEPOSIT_CATS) and $this->siteDeposit[$currency] += array_sum((array) $realcashInfo);
                in_array($transactionCategory, self::WITHDRAWAL_CATS) and $this->siteWithdrawal[$currency] += array_sum((array) $realcashInfo);
                in_array($transactionCategory, ['reject_company_deposits']) and $this->siteDeposit[$currency] += array_sum((array) $realcashInfo);
                in_array($transactionCategory, ['reject_cashwithdrawal', 'reject_tokengcash']) and $this->siteDeposit[$currency] += array_sum((array) $realcashInfo);
            }
        }
    }

    /**
     *  現金|遊戲幣 入款小計，實際存提為 1
     */
    private function setSubtotalDeposit()
    {
        foreach ($this->data as $transactionCategory => $transactionCategoryInfo) {
            foreach ($transactionCategoryInfo as $currency => $realcashInfo) {
                // if (!in_array($transactionCategory, self::DEPOSIT_CATS)) continue;
                if (isset($transactionCategoryInfo->{$currency}->{'realcash'}) && $transactionCategoryInfo->{$currency}->{'realcash'} > 0) {
                    $this->subtotalDeposit[$currency] += $transactionCategoryInfo->{$currency}->{'realcash'} ?? 0;
                    continue;
                }
            }
        }
    }

    /**
     *  現金|遊戲幣 出款小計，實際存提為 1
     */
    private function setSubtotalWithdrawal()
    {
        foreach ($this->data as $transactionCategory => $transactionCategoryInfo) {
            foreach ($transactionCategoryInfo as $currency => $realcashInfo) {
                // if (!in_array($transactionCategory, self::WITHDRAWAL_CATS)) continue;
                if (isset($transactionCategoryInfo->{$currency}->{'realcash'}) && $transactionCategoryInfo->{$currency}->{'realcash'} < 0) {
                    $this->subtotalWithdrawal[$currency] += $transactionCategoryInfo->{$currency}->{'realcash'} ?? 0;
                    continue;
                }
            }
        }
    }

    public function __get($property)
    {
        if ($this->$property) {
            return $this->$property;
        }
    }
}

// ----------------------------------------------------------------------
// 檢查 CSRF token 是否正確 , 對應的 function 為 csrf_token_make()
// 有兩個對應的 function
// csrf_token_make() 使用在傳送端 client
// csrf_action_check() 使用在接收端 server
/*
// 檢查產生的 CSRF token 是否存在 , 錯誤就停止使用
$csrftoken_ret = csrf_action_check();
if($csrftoken_ret['code'] != 1) {
  //var_dump($csrftoken_ret);
  die($csrftoken_ret['messages']);
}
*/
// ----------------------------------------------------------------------
function csrf_action_check($form_array=array()) {

    // 只接收指定來源的 script name, 預設為空陣列, 不限制來源網頁!!
    // $form_array = array("a.php", "b.php", "c.php", "d.php");
    // $form_array = array('/gpk2/home2.php');
    //$form_array = array();

  // 檢查所帶入的 CSRF token 是否存在 ,且不是空值, 需要有登入才可以
  if(isset($_POST['csrftoken']) AND !empty($_POST['csrftoken']) ) {
    // 從 $_POST['csrftoken'] 解出正確的 $csrftoken_valid
    $csrftoken = $_POST['csrftoken'];
        //$csrftoken = "eyJSRU1PVEVfQUREUiI6IjExNC4zMy4yMDEuMjQyIiwiUEhQX1NFTEYiOiJcL2dwazJcL2hvbWUucGhwIiwiZGF0YSI6bnVsbCwiZmluZ2VydHJhY2tlciI6IjY2NjA4NzQ2OCJ9_1883f43e672ed87db87b027f0a0d98e5187e370e";
        //var_dump($csrftoken);

        // 加上特殊 key, 避免 jwt sha1 编码被识破, $jwt_csrftoken_key = date('Y-m-d H:m:s');
        // $jwt_csrftoken_key = gmdate('Y-m-d_H');
        // 以每日改變一次,避免一直產生錯誤但同時風險就是一天
        $jwt_csrftoken_key = gmdate('Y-m-d');
        // var_dump($jwt_csrftoken_key);

        $jwt = explode("_", $csrftoken);
        // var_dump($jwt);
        // SHA1 驗證
        $csrftoken_check_sha1 = sha1($jwt[0].$jwt_csrftoken_key);
        // var_dump($csrftoken_check_sha1);
        // jwt[1] --> 傳來的 hash code


        // 檢查 JWT hash 是否相同, 相同才繼續
        if(isset($jwt[1]) AND $csrftoken_check_sha1 == $jwt[1]) {

            $client_data = json_decode(base64_decode($jwt[0]));
            // var_dump($client_data);
            $ret['code']      = 1;
            $ret['messages'] = 'token correct.';
            $ret['debug']  = 'CSRF token correct. jwtkey='.$jwt_csrftoken_key.' token='.$csrftoken;

            if($form_array == array() OR in_array($client_data->PHP_SELF , $form_array )) {
                $ret['code']      = 1;
                $ret['messages'] = 'token correct, data correct.';
                $ret['debug']  = 'CSRF token correc, data correct jwtkey='.$jwt_csrftoken_key.' token='.$csrftoken;
            }else{
                // 只接收指定來源的 script name , 空 ARRAY
                $ret['code']      = 404;
                // $ret['messages'] = 'token correct, data error.';
                $ret['messages'] = '<a href="#" onClick="window.location.reload()">你好像输入了错误的资料。</a>';
                $ret['debug']  = 'CSRF token correct,  data error '.$client_data->PHP_SELF.' jwtkey='.$jwt_csrftoken_key.' token='.$csrftoken;
            }

        }else{
            $ret['code']      = 0;
            $ret['messages'] = '<a href="#" onClick="window.location.reload()">你休息太久没动作，请重新整理页面。</a>';
            //$ret['messages'] = '<a href="#" onClick="window.location.reload()">You rest too long, please refresh the page.</a>';
            $ret['debug']  = 'CSRF token hashcode error!! jwtkey='.$jwt_csrftoken_key.' token='.$csrftoken;
        }

  }else{
    // 請傳入 $_POST['csrftoken'] 變數, 以及 $_SESSION['csrftoken_valid'] 的 session 值
    $ret['code']      = 500;
    $ret['messages'] = 'token does not exist.';
        $ret['debug']  = 'CSRF token does not exist!!';
  }

  return($ret);
}
// ----------------------------------------------------------------------


// ----------------------------------------------------------------------
// 產生 CSRF token 對應的 function 為 csrf_action_check()
// 有兩個對應的 function
// csrf_token_make() 使用在傳送端 client
// csrf_action_check() 使用在接收端 server
/*
// 產生 csrf token , $csrftoken 需要透過這個傳遞到對應的 action page post 內
$csrftoken = csrf_token_make();
// 可以放傳遞的變數 json ,做資料的驗證
$csrftoken = csrf_token_make($json_data);
*/
// ----------------------------------------------------------------------
function csrf_token_make($json_data=NULL){
    global $program_start_time;
    // 加上特殊 key, 避免 jwt sha1 编码被识破, $jwt_csrftoken_key = date('Y-m-d H:m:s');
    //$jwt_csrftoken_key = gmdate('Y-m-d_H');
    // 以每日改變一次,避免一直產生錯誤但同時風險就是一天
    $jwt_csrftoken_key = gmdate('Y-m-d');

    // DATA: 遠端IP + 浏览器 ID
    $client_data_array['REMOTE_ADDR']		= explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'];
    $client_data_array['PHP_SELF'] 			= $_SERVER['PHP_SELF'];
    $client_data_array['data'] 					= $json_data;
    if(isset($_SESSION['fingertracker'])) {
        $client_data_array['fingertracker'] = $_SESSION['fingertracker'];
    }else{
        $client_data_array['fingertracker'] = 'NoFingerID';
    }
    $client_data_encode = json_encode($client_data_array);

    // BASE64
    $csrftoken_orig = base64_encode($client_data_encode);
    // SHA1
    $csrftoken_orig_sha1 = sha1($csrftoken_orig.$jwt_csrftoken_key);
    // JWT String
    $csrftoken 			= $csrftoken_orig.'_'.$csrftoken_orig_sha1;
    //echo "<br><br><br><br><br><hr>";
    //var_dump($jwt_csrftoken_key);
    //var_dump($csrftoken_orig);
    //var_dump($client_data_encode);
    //var_dump($csrftoken);

    // 在 post 看到的 CSRF
    return($csrftoken);
}
// ----------------------------------------------------------------------


// ----------------------------------------------------------------------
// CSRF 全域变数, 预先载入了. 位置很重要. 紧接著产生之后
// lib_common.php 创造了 CSRF token function, 產生 csrf token , $csrftoken 需要透過這個傳遞到對應的 action page post 內
if (php_sapi_name() != "cli") {
    $csrftoken = csrf_token_make();
    //var_dump($csrftoken);
}
// ----------------------------------------------------------------------


/**
 * csv upload function
 */
function csv_upload($fileprefix){
  require_once dirname(__FILE__) . "/lib_file.php";
  header('Content-Type: application/json');

  $valid_exts = ['xlsx','xls','csv']; // valid extensions
  $max_size = 30000 * 1024; // max file size in bytes

  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
      http_response_code(406);
      echo json_encode([
          'message' => 'Bad request!',
      ]);
      return;
  }

  if (!isset($_FILES['csv'])) {
      http_response_code(406);
      echo json_encode([
          'message' => 'No file in form!',
      ]);
      return;
  }

  if (!is_uploaded_file($_FILES['csv']['tmp_name'])) {
      http_response_code(406);
      echo json_encode([
          'message' => 'Upload Fail: File not uploaded!',
          'file_error' => $_FILES['csv']['error'],
          'data' => $_FILES,
      ]);
      return;
  }

  // get uploaded file extension
  $ext = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));

  // looking for format and size validity
  if (!in_array($ext, $valid_exts) and $_FILES['csv']['size'] < $max_size) {
      http_response_code(406);
      echo json_encode([
          'message' => 'Upload Fail: Unsupported file format or It is too large to upload!',
      ]);
      return;
  }

  $tmp_file_path = $_FILES['csv']['tmp_name'];

  // remove BOM
  // $content = file_get_contents($tmp_file_path);
  // file_put_contents($tmp_file_path, str_replace("\xEF\xBB\xBF",'', $content));
  $file_name = $fileprefix. date("YmdHis");
  $destination_file = dirname(__FILE__) . '/tmp_dl/'. $file_name . '.csv';
  $tmp_file_path_final = ($ext == 'csv') ? $tmp_file_path : exceltocsv($tmp_file_path,$destination_file,$ext);

  // return var_dump($tmp_file_path_final);die();
  return $tmp_file_path_final;
}

// 強制更新前後台memcache資料
function memcache_forceupdate(){
  global $redisdb;

  try{
    $redis = new Redis();
    // 第一个参数为redis服务器的ip,第二个为端口
    $redis->connect($redisdb['host'], 6379);
    $redis->auth($redisdb['auth']);
    // test为发布的频道名称,hello,world为发布的消息
    $res = $redis->publish('memcache','update');
    $return['code'] = 1;
  }catch(RedisException $e){
    $return['code'] = 0;
    $return['msg'] = $e->getMessage();
  }
  return $return;
}

// get casino game categories
function get_casino_game_categories()
{
    $casino_game_sql = <<<SQL
        SELECT "casinoid", -- 娛樂城id
               "game_flatform_list" -- 娛樂城的返水分類
        FROM "casino_list"
        ORDER BY "id";
    SQL;
    $casino_game_category_result = runSQLall($casino_game_sql);
    $casino_game_categories = [];
    if ($casino_game_category_result[0] > 0) {
        $casino_game_category_result = array_slice($casino_game_category_result, 1); // 切除索引
        foreach ($casino_game_category_result as $val) {
            $casino_game_categories[strtolower($val->casinoid)] = json_decode($val->game_flatform_list, true);
        }
    }
    return $casino_game_categories;
}
