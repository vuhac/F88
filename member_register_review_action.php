<?php
// ----------------------------------------------------------------------------
// Features:	代理商後台 - register_review.php 的處理
// File Name:	register_review_action.php
// Author:		侑駿
// Related:		對應後台 register_review.php
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// function
function queryStr($query_sql_array){
    Global $tr;
    $query_top= 0;
    $show_member_log_sql = '';
    if(isset($query_sql_array['query_date_start_datepicker']) or isset($query_sql_array['query_date_end_datepicker'])){
        if (isset($query_sql_array['query_date_start_datepicker']) and $query_sql_array['query_date_start_datepicker'] != null) {
            if ($query_top == 1) {
                $show_member_log_sql = $show_member_log_sql . ' AND ';
            	}
            $show_member_log_sql = $show_member_log_sql . 'applicationtime >= \'' . $query_sql_array['query_date_start_datepicker_gmt'] . '\'';
            $query_top = 1;
     	   }

        if (isset($query_sql_array['query_date_end_datepicker']) and $query_sql_array['query_date_end_datepicker'] != null) {
            if ($query_top == 1) {
                $show_member_log_sql = $show_member_log_sql . ' AND ';
      	      }
            $show_member_log_sql = $show_member_log_sql . 'applicationtime <= \'' . $query_sql_array['query_date_end_datepicker_gmt'] . '\'';
            $query_top           = 1;
    	    }

    	   if($query_top == 1 AND !isset($logger)){
          $return_sql = ' '.$show_member_log_sql;
        }elseif(isset($logger)){
          $return_sql['logger'] = $logger;
        }else{
          $return_sql = '';
        }
    }else{
      switch ($query_sql_array['t']) {
        case '1':
          $return_sql 	= " (applicationtime >= (current_timestamp - interval '24 hours')) ";
          break;
        case '7':
          $return_sql 	= " (applicationtime >= (current_timestamp - interval '7 days')) ";
          break;
        case '30':
          $return_sql 	= " (applicationtime >= (current_timestamp - interval '30 days')) ";
          break;
        case '90':
          $return_sql 	= "  (applicationtime >= (current_timestamp - interval '90 days')) ";
          break;
        default:
          $return_sql 	= " (applicationtime >= (current_timestamp - interval '24 hours')) ";
          break;
      }
     }
    return $return_sql;
}

function validateDate($date, $format = 'Y-m-d'){
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

function json_response($code = 200, $message = null) {
  header_remove();
  http_response_code($code);
  // 快取控制
  // header('"Cache-Control: no-transform,public,max-age=300,s-maxage=900');
  header('Content-Type: application/json');
  $status = [
    200 => '200 OK',
    400 => '400 Bad Request',
    500 => '500 Internal Server Error'
  ];

  header('Status: '.$status[$code]);
  return json_encode([
        'status' => $code < 300, // success or not?
        'message' => $message
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function notify2mq($logger){
  require_once __DIR__ . '/Utils/RabbitMQ/Publish.php';
  require_once __DIR__ . '/Utils/MessageTransform.php';

  $mq = Publish::getInstance();
  $msg = MessageTransform::getInstance();

  $currentDate = date("Y-m-d H:i:s", strtotime('now'));
  $notifyMsg = $msg->notifyMsg('RegisterReview', $logger, $currentDate);
  $notifyResult = $mq->fanoutNotify('msg_notify', $notifyMsg);

  // 測試用
  // $notifytestResult = $mq->directNotify('direct_test', 'direct_test', $notifyMsg);
}

// -------------------------------------------------------------------------------


if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
}else{
    die($tr['Illegal test']);
}

// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if(isset($_GET['length']) AND $_GET['length'] != NULL ) {
  $current_per_size = filter_var($_GET['length'],FILTER_VALIDATE_INT);
}else{
  $current_per_size = $page_config['datatables_pagelength'];
  //$current_per_size = 10;
}

// 起始頁面, 搭配 current_per_size 決定起始點位置
if(isset($_GET['start']) AND $_GET['start'] != NULL ) {
  $current_page_no = filter_var($_GET['start'],FILTER_VALIDATE_INT);
}else{
  $current_page_no = 0;
}

// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if(isset($_GET['_'])){
  $secho = $_GET['_'];
}else{
  $secho = '1';
}

// var_dump($_SESSION);
// die();
// var_dump($_POST);
//var_dump($_GET);

// 本功能的 global 變數設定
$operator=$_SESSION['agent']->account;
// ----------------------------------
// 動作為會員登入檢查, 只有 Root 可以維護。
// ----------------------------------
if($action == 'register_review_list' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------
  $query_sql_array=[];
  // 使用者所在的時區，sql 依據所在時區顯示 time
  // -------------------------------------
  if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
    $tz = $_SESSION['agent']->timezone;
  }else{
    $tz = '+08';
  }
  // 轉換時區所要用的 sql timezone 參數
  $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
  $tzone = runSQLALL($tzsql);
  // var_dump($tzone);
  if($tzone[0]==1){
    $tzonename = $tzone[1]->name;
  }else{
    $tzonename = 'posix/Etc/GMT-8';
  }
  // to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD') as enrollmentdate_tz

  // 開始時間
  if (isset($_GET['s']) and $_GET['s'] != null and isset($_GET['tk']) and $_GET['tk'] != null) {
      // 判斷格式資料是否正確
      $salt = $_GET['s'];
        // 判斷格式資料是否正確
      $querytoken = $_GET['tk'];
      $query_sql_array = jwtdec($salt, $querytoken);

  }

  $query_str= '';
  $query_str_arr= array();
  // 取得查詢條件

  // var_dump($query_sql_array);
  // 2-2去query_str($query_sql_array)函數，產生查詢條件
  if (isset($query_sql_array) and $query_sql_array != null){
    $query_str = queryStr((array)$query_sql_array);
  }else{
    // 沒有資料的話, default 24 hr
     $query_str_arr[] = " (applicationtime >= (current_timestamp - interval '7 days')) ";
  }
  // 審核未審核filter
  switch ($_GET['status_filter'])
  {
  case 'unreviewed':
     $query_str_arr[] = " status = '4' ";
    break;
  case 'audited':
     $query_str_arr[] = " status != '4' ";
    break;
  default:
     $query_str_arr[] = " status = '4' ";
  }

  // 處理 datatables 傳來的search需求
  if(isset($_GET['search']['value']) AND $_GET['search']['value'] != ''){
    $query_str_arr[] = ' account = \''.$_GET['search']['value'].'\' ';
  }
  // var_dump($query_str_arr);
  // var_dump($query_str);

  if(count($query_str_arr) > 0){
    $query_str .= ($query_str != '') ? ' AND ' : '';
    $query_str .= (count($query_str_arr) > 1) ? implode(' AND ',$query_str_arr) : $query_str_arr['0'];
  }

  // -----------------------------------------------------------------------
  // 列出所有的會員資料及人數 SQL
  // -----------------------------------------------------------------------
  // 設定基本查詢條件
  $userlist_sql_tmp = "SELECT  account FROM root_member_register_review WHERE ".$query_str;

  // 算 root_member 人數
  $userlist_sql = $userlist_sql_tmp.';';
  // var_dump($userlist_sql);
  $userlist_count = runSQL($userlist_sql);

  // -----------------------------------------------------------------------
  // 分頁處理機制
  // -----------------------------------------------------------------------
  // 所有紀錄數量
  $page['all_records']     = $userlist_count;
  // 每頁顯示多少
  $page['per_size']        = $current_per_size;
  // 目前所在頁數
  $page['no']              = $current_page_no;
  // var_dump($page);

  // 處理 datatables 傳來的排序需求
  if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
    if($_GET['order'][0]['dir'] == 'asc'){ $sql_order_dir = 'ASC';
    }else{ $sql_order_dir = 'DESC';}
    if($_GET['order'][0]['column'] == 0){ $sql_order = 'ORDER BY id '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 1){ $sql_order = 'ORDER BY member_account '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 2){ $sql_order = 'ORDER BY root_member_realname '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 3){ $sql_order = 'ORDER BY applicationtime_tz '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 4){ $sql_order = 'ORDER BY root_member_register_review_applicationip '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 5){ $sql_order = 'ORDER BY root_member_register_review_fingerprinting '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 6){ $sql_order = 'ORDER BY root_member_register_review_status '.$sql_order_dir;
    }else{ $sql_order = 'ORDER BY root_member_register_review_status DESC';}
  }else{ $sql_order = 'ORDER BY root_member_register_review_status DESC';}
  // 取出 root_member 資料
  $list_sql = "
	SELECT *, to_char((applicationtime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as applicationtime_tz,
  to_char((processingtime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as processingtime_tz,
  to_char((changetime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as changetime_tz,
  member_id as root_member_id,
  realname as root_member_realname,
  account as member_account,
  id as root_member_register_review_id,
  status as root_member_register_review_status,
  applicationip as root_member_register_review_applicationip,
  fingerprinting as root_member_register_review_fingerprinting,
  notes as root_member_register_review_notes
  FROM root_member_register_review
  WHERE ".$query_str." ".$sql_order." OFFSET ".$page['no']." LIMIT ".$page['per_size']." ;";

  // var_dump($list_sql);
  $userlist = runSQLall($list_sql);

  // 存放列表的 html -- 表格 row -- tables DATA
  $show_listrow_html = '';
  // 判斷 root_member count 數量大於 1
  if($userlist[0] >= 1) {
    // 以會員為主要 key 依序列出每個會員的貢獻金額
    for($i = 1 ; $i <= $userlist[0]; $i++){
      // 資料庫內的 PK
  		$b['id']                     = $userlist[$i]->root_member_register_review_id;
  		// 會員的 member ID
  		$b['member_id']              = $userlist[$i]->root_member_id;
  		$b['member_account']         = $userlist[$i]->member_account;
      $b['name']       = $userlist[$i]->root_member_realname;
  		$b['applicationtime']        = gmdate('Y-m-d H:i:s', strtotime($userlist[$i]->applicationtime_tz)+-4 * 3600);
  		$b['processingtime']          = $userlist[$i]->processingtime_tz;
  		$b['changetime']           = $userlist[$i]->changetime_tz;
  		$b['status']        = $userlist[$i]->root_member_register_review_status;
  		$b['applicationip']        = $userlist[$i]->root_member_register_review_applicationip;
  		$b['fingerprinting']        = $userlist[$i]->root_member_register_review_fingerprinting;
  		$b['notes']        = $userlist[$i]->root_member_register_review_notes;

    // 顯示的表格資料內容
    $show_listrow_array[] = array(
      'id'=>$b['member_id'],
      'account'=>$b['member_account'],
      'member_id'=>$b['member_id'],
      'name'=>$b['name'],
      'applicationtime'=>$b['applicationtime'],
      'processingtime'=>$b['processingtime'],
      'changetime'=>$b['changetime'],
      'status'=>$b['status'],
      'applicationip'=>$b['applicationip'],
      'fingerprinting'=>$b['fingerprinting'],
      'notes'=>$b['notes']);
    }
    $output = array(
      "sEcho" => intval($secho),
      "iTotalRecords" => intval($page['per_size']),
      "iTotalDisplayRecords" => intval($userlist_count),
      "data" => $show_listrow_array
    );
    // --------------------------------------------------------------------
    // 表格資料 row list , end for loop
    // --------------------------------------------------------------------
  }else{
    // NO member
    $output = array(
      "sEcho" => 0,
      "iTotalRecords" => 0,
      "iTotalDisplayRecords" => 0,
      "data" => ''
    );
  }
  // end member sql
  echo json_encode($output);
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------

}elseif($action == 'register_review_update' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  // ----------------------------------
  // 動作為會員登入檢查, 只有 Root 可以維護。
  // ----------------------------------
  // 取得 register_review_info 的 ID
  $register_review_id = filter_var($_POST['uid'], FILTER_SANITIZE_NUMBER_INT);
  $register_review_status = filter_var($_POST['value'], FILTER_SANITIZE_NUMBER_INT);

  $member_status = array(
    '0' => $tr['application reject'],
    '1' =>$tr['enable'],
    '2' => $tr['freezing'],
    '3' => $tr['blocked'],
    '4' => $tr['auditing']
  );

  // 查詢 root_member_wallets 資料表
  $register_review_sql = "SELECT * FROM root_member_register_review WHERE member_id = '$register_review_id' AND status = '4';";

  $register_review_result = runSQLALL($register_review_sql);
  // var_dump($register_review_result);die();
  // 判斷是否有無單號
  if($register_review_result[0] == 1){
    $op_acc = $_SESSION['agent']->account;
    $review_notes = "(".$register_review_result[1]->account.$tr['process_by_admin'].$op_acc.$tr['chg_auditstatus_to'].$member_status[$register_review_status].")";
    $review_sql = "UPDATE root_member_register_review SET status = '$register_review_status',notes='$review_notes',processingtime=now(),changetime=now(),processingaccount='$op_acc' WHERE member_id = '$register_review_id';";
    if($register_review_status == 0){
      $member_sql = "DELETE FROM root_member_wallets WHERE id = '$register_review_id'; DELETE FROM root_member WHERE id = '$register_review_id';";
    }else{
      $member_sql = "UPDATE root_member SET status = '$register_review_status',changetime=now() WHERE id = '$register_review_id';";
    }

    $sql = 'BEGIN;'
      .$review_sql
      .$member_sql
      .'COMMIT;';

    $sql_result = runSQLtransactions($sql);
    // var_dump($sql_result);die();

    if( $sql_result == 1) {
      // 取消
      $logger = $tr['Data has been successfully updated'];
      $status = 200;
      memberlog2db($register_review_result[1]->account, 'member create', 'notice', "$logger");
      notify2mq($review_notes);
    }else{
      // 系统错误
      $logger = $tr['Data update failed'];
      $status = 400;
    }

    // var_dump($sql);exit;
  }else{
    $logger = $tr['duplicate_audit_case'];
    $status = 400;
  }

  // echo $logger;
  echo   json_response($status, $result = ['state' => true, 'description' => $logger]);
}else if($action == 'register_review_switch_update' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
//  var_dump($_POST);

  $register_review_switch_status = filter_var($_POST['value'], FILTER_SANITIZE_NUMBER_INT);

  $switch_status = array(
    '0' => array('db_value' => 'on','status_str' => $tr['disable']),
    '1' =>array('db_value' => 'off','status_str' => $tr['enable'])
  );

  // 更新 root_protalsetting
  $review_sql = "UPDATE root_protalsetting SET value = '".$switch_status[$register_review_switch_status]['db_value']."' WHERE name = 'member_register_review';";
  // var_dump($review_sql);
  $review_result = runSQLtransactions($review_sql);
  // var_dump($review_result);

  // 強制更新前後台memcache資料
  $update_result = memcache_forceupdate();

  if($review_result == 1){
    // 更新 notes
    $logger = $tr['Data has been successfully updated'];
    $status = 200;
  }else{
    // 系统错误
    $logger = $tr['Data update failed'];
    $status = 400;
  }
  echo   json_response($status, $result = ['state' => true, 'description' => $logger]);

}elseif(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  $output = array(
    "sEcho" => 0,
    "iTotalRecords" => 0,
    "iTotalDisplayRecords" => 0,
    "data" => ''
  );
  echo json_encode($output);

}else{
  $logger = '(x) 只有管理员或有权限的会员才可以登入观看。';
  echo '<script type="text/javascript">alert("'.$logger.'");</script>';die();
}



?>
