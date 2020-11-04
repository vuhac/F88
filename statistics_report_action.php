<?php
// ----------------------------------------------------------------------------
// Features:	後台 - 查詢統計報表
// File Name:	statistics_report_action.php
// Author:		yaoyuan
// Related:   2019.08.06
//    statistics_report.php
//    statistics_report_lib.php
// Log:
// ----------------------------------------------------------------------------

session_start();
require_once dirname(__FILE__) ."/statistics_report_lib.php";

require_once dirname(__FILE__) . "/config_betlog.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------

// var_dump($_SESSION);
// var_dump($_POST);
// var_dump($_GET);
// die();

global $page_config;
global $tr;

// 預設可查區間限制，2個月
$current_date = gmdate('Y-m-d H:i:s',time() + -4*3600);
$default_min_date = gmdate('Y-m-d H:i',strtotime('-2 month') + -4*3600);//.'-01 00:00';

$query_chk = 0;
if(isset($_GET['a'])){
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的測試');
}

if(isset($_GET['csv'])){
  $CSVquery_sql_array = get_object_vars(jwtdec('statistics_report', $_GET['csv']));
}

// 檢查查詢條件是否有提供帳號
$account_chk = 0;
if(isset($_GET['ag']) AND $_GET['ag'] != NULL  AND $_GET['ag'] != '' ) {
  $query_sql_array['account_query'] = filter_var($_GET['ag'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
  $account_chk = 1;
}

// 20200420
if(isset($_GET['sdate']) AND $_GET['sdate'] != NULL ) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['sdate'], 'Y-m-d H:i')) {
    $query_sql_array['query_date_start_datepicker'] = $_GET['sdate'];//.':00';

    // $query_sql_array['query_date_start_datepicker'] = $_GET['sdate'].' -04';
    $query_chk = 1;
  }else{
    $output = array('logger' => '开始时间错误，查询区间超过2个月，请修改查询区间！');
    die(json_encode($output));
  }
}else{
  // 預設datetimepicker 開始時間:現在-7天
  $min_date = gmdate('Y-m-d',strtotime('- 7 days'));
  $query_sql_array['query_date_start_datepicker'] = $min_date;
}

if(isset($_GET['edate']) AND $_GET['edate'] != NULL ) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['edate'], 'Y-m-d H:i')) {
    $query_sql_array['query_date_end_datepicker'] = $_GET['edate'];//.':59';

    // $query_sql_array['query_date_end_datepicker'] = $_GET['edate'].' -04';
    $query_chk = 1;
  }else{
    $output = array('logger' => '结束时间错误,请修改查询区间!结束时间:'.$current_date);
    die(json_encode($output));
  }
}else{
  $query_sql_array['query_date_end_datepicker'] = $current_date;
}
//-----------------------------------
if(isset($_GET['gc']) AND $_GET['gc'] != NULL ) {
  $query_sql_array['gc_query'] = filter_var_array($_GET['gc'], FILTER_SANITIZE_STRING);
  if (in_array("game", $query_sql_array['gc_query'])) {
    $query_sql_array['gc_query'][] = 'html5';
  }
  if (in_array("lottosum", $query_sql_array['gc_query'])) {
    $query_sql_array['gc_query'][] = 'lotto';
    $query_sql_array['gc_query'][] = 'lottery';
    unset($query_sql_array['gc_query'][array_search('lottosum', $query_sql_array['gc_query'])]);
  }
  $query_chk = 1;
}else{
  $query_sql_array['gc_query'] = qcselall();
}

$query_sql_array['casino_query'] = 'all';
if(isset($_GET['casino']) AND $_GET['casino'] != NULL AND $_GET['casino'] != '') {
    $query_sql_array['casino_query'] = filter_var_array($_GET['casino'], FILTER_SANITIZE_STRING);
}


// 娛樂城對應娛樂城帳號 MG->mg_account
$casino2gameaccount=casino2gameaccount($query_sql_array['casino_query']);
$query_sql_array['casino_query']=$casino2gameaccount['casino_query'];
unset($casino2gameaccount);


// -------------------------------------------------------------------------
// datatable server process 分頁處理及驗證參數
// -------------------------------------------------------------------------
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
// -------------------------------------------------------------------------
// datatable server process 分頁處理及驗證參數  END
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// GET / POST 傳值處理 END
// -------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------



// ----------------------------------
// 動作為會員 action
// ----------------------------------
if($action == 'get_init' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  // datatable 20200417

  // 處理 datatables 傳來的排序需求
  if(isset($_GET['order'][0]) && $_GET['order'][0]['column'] != ''){
    if($_GET['order'][0]['dir'] == 'asc'){ 
      $sql_order_dir = 'ASC';
    }else{ 
      $sql_order_dir = 'DESC';
    }
    if($_GET['order'][0]['column'] == 0){ 
      $sql_order = 'ORDER BY updatetime '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 1){ 
      $sql_order = 'ORDER BY member_account  '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 2){ 
      $sql_order = 'ORDER BY betvalid '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 3){ 
      $sql_order = 'ORDER BY betprofit '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 4){ 
      $sql_order = 'ORDER BY bet_count '.$sql_order_dir;
    }else{ 
      $sql_order = 'ORDER BY updatetime ASC';
    }
  }else{ 
    $sql_order = 'ORDER BY updatetime ASC';
  }

  // 產生使用者所選之娛樂城及對應分類
  $getcasinogamecate=getcasinogamecate($query_sql_array['casino_query'],$query_sql_array['gc_query']);
    
  // 產生日報、十分鐘所需日期格式
  $sqldate=dateconvertsqldate($query_sql_array);
  // var_dump($sqldate);die();

  // 娱乐城无法对映游戏分类
  if(count($getcasinogamecate)==0){
    // $output = array('logger' => '娱乐城无法对映游戏分类。错误代码：1907231054637。');
    $output = array('logger' => '查无资料，娱乐城无该分类项目。');

    echo json_encode($output);die();
  }
   
  if ($sqldate['have_ten_mins']=='1'){
    // 產生十分鐘報表，搜尋條件
    $tenmin_query_str = tenmin_query_str($query_sql_array,$sqldate);
    // 產生原始投注資料_十分鐘 sql 條件
    $bet_tenmin_query_str = bet_tenmin_query_str($query_sql_array,$sqldate);
    // 判斷搜尋條件是否有錯
    if(isset($tenmin_query_str['logger'])){
        $output = array('logger' => $tenmin_query_str['logger']);
        echo json_encode($output);die();
    }else{
      // 產生十分鐘匯總資料
      $ten_min_data=ten_min_data($tenmin_query_str);

      // 產生原始投注紀錄_十分鐘
      $sql=bet_ten_min_data($bet_tenmin_query_str);
    }
  }

  if ($sqldate['have_daily']=='1'){
    // 產生日報，搜尋條件
    $daily_query_str = daily_query_str($query_sql_array,$sqldate);
    // var_dump($daily_query_str);die();
    // 判斷搜尋條件是否有錯
    if(isset($daily_query_str['logger'])){
        $output = array('logger' => $daily_query_str['logger']);
        echo json_encode($output);die();
    }else{
      // 產生日報匯總資料
      $daily_data=daily_data($daily_query_str,$getcasinogamecate);
    }
  }

  if(is_array($daily_data)){
  
    // 搜尋區間沒資料
    $output = array(
      "sEcho"                                     => 0,
      "iTotalRecords"                     => 0,
      "iTotalDisplayRecords"         => 0,
      "data"                                         => ''
    );
    echo json_encode($output);die();
  }else{

    // 算資料數
    $count_sql = $daily_data.";";
    $count_list = runSQL($count_sql);

    if($count_list != 0){
      // -----------------------------------------------------------------------
      // 分頁處理機制
      // -----------------------------------------------------------------------
      // 所有紀錄數量
      $page['all_records']     = $count_list;
      // 每頁顯示多少
      $page['per_size']        = $current_per_size;
      // 目前所在頁數
      $page['no']              = $current_page_no;

      // 取出資料
      $userlist_sql   = $daily_data. $sql_order. ' OFFSET '.$page['no'].' LIMIT '.$page['per_size'].';';
      // var_dump($userlist_sql);die();
      $result = runSQLall($userlist_sql);

      for($i=1;$i<=$result[0];$i++){
        $item = $page['no'] + $i;
        $account = $result[$i]->member_account;
        $bet_slip = $result[$i]->bet_count;
        $bet_amount = $result[$i]->betvalid;
        $profit_and_loss = $result[$i]->betprofit;
        $update_time = gmdate('Y-m-d H:i:s',strtotime($result[$i]->updatetime) + -4*3600);
      
        $show_list_array[]= array(
          'item'=> $item,
          'account'=> $account,
          'bet_slip'=> $bet_slip,
          'bet_amount'=> '$'.$bet_amount,
          'profit_and_loss'=> '$'.$profit_and_loss,
          'update_time'=> $update_time
        );
      }
      $output = array(
        "sEcho" 								=> intval($secho),
        "iTotalRecords" 				=> intval($page['per_size']),
        "iTotalDisplayRecords" 	=> intval($page['all_records']),
        "data" 									=> $show_list_array
      );
    }else{
      // 搜尋區間沒資料
      $output = array(
        "sEcho"                                     => 0,
        "iTotalRecords"                     => 0,
        "iTotalDisplayRecords"         => 0,
        "data"                                         => ''
      );
    }
  }

  echo json_encode($output);


}elseif($action =='get_result' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  // datatable搜尋
  
  // 產生使用者所選之娛樂城及對映分類
  $getcasinogamecate=getcasinogamecate($query_sql_array['casino_query'],$query_sql_array['gc_query']);
    
  // 產生日報、十分鐘所需日期格式
  $sqldate=dateconvertsqldate($query_sql_array);
  
  if(isset($_GET['order'][0]) && $_GET['order'][0]['column'] != ''){
    if($_GET['order'][0]['dir'] == 'asc'){ 
      $sql_order_dir = 'ASC';
    }else{ 
      $sql_order_dir = 'DESC';
    }
    if($_GET['order'][0]['column'] == 0){ 
      $sql_order = 'ORDER BY updatetime '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 1){ 
      $sql_order = 'ORDER BY member_account  '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 2){ 
      $sql_order = 'ORDER BY betvalid '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 3){ 
      $sql_order = 'ORDER BY betprofit '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 4){ 
      $sql_order = 'ORDER BY bet_count '.$sql_order_dir;
    }else{ 
      $sql_order = 'ORDER BY updatetime ASC';
    }
  }else{ 
    $sql_order = 'ORDER BY updatetime ASC';
  }

  // 娱乐城无法对映游戏分类
  if(count($getcasinogamecate) == 0){
    // $output_msg = array('logger' => '娱乐城无法对映游戏分类。错误代码：1907231054637。');
    // echo json_encode($output_msg);die();

    $output_msg = array(
      "sEcho" 								=> 0,
      "iTotalRecords" 				=> 0,
      "iTotalDisplayRecords" 	=> 0,
      "data" 									=> ''
    );
    echo json_encode($output_msg);die();
  }

  if ($sqldate['have_ten_mins']=='1'){
    // 產生十分鐘報表，搜尋條件
    $tenmin_query_str = tenmin_query_str($query_sql_array,$sqldate);
    // 產生原始投注資料_十分鐘 sql 條件
    $bet_tenmin_query_str = bet_tenmin_query_str($query_sql_array,$sqldate);
    // 判斷搜尋條件是否有錯
    if(isset($tenmin_query_str['logger'])){
        $output = array('logger' => $tenmin_query_str['logger']);
        echo json_encode($output);die();
    }else{
      // 產生十分鐘匯總資料
      $ten_min_data=ten_min_data($tenmin_query_str);

      // 產生原始投注紀錄_十分鐘
      $sql=bet_ten_min_data($bet_tenmin_query_str);
    }
  }
  // var_dump($sqldate);die();

  if ($sqldate['have_daily']=='1'){
    // 產生日報，搜尋條件
    $daily_query_str = daily_query_str($query_sql_array,$sqldate);

    // 判斷搜尋條件是否有錯
    if(isset($daily_query_str['logger'])){
      $output = array('logger' => $daily_query_str['logger']);
      echo json_encode($output);die();
    }else{
      // 產生日報匯總資料
      $daily_data=daily_data($daily_query_str,$getcasinogamecate);
    }
  }
  if(is_array($daily_data)){
      // 搜尋區間沒資料
      $output = array(
      "sEcho"                                     => 0,
      "iTotalRecords"                     => 0,
      "iTotalDisplayRecords"         => 0,
      "data"                                         => ''
    );
    echo json_encode($output);die();
  }else{
    // 算資料數
    $count_sql = $daily_data.";";
    $count_list = runSQL($count_sql);

    if($count_list != 0){
      // -----------------------------------------------------------------------
      // 分頁處理機制
      // -----------------------------------------------------------------------
      // 所有紀錄數量
      $page['all_records']     = $count_list;
      // 每頁顯示多少
      $page['per_size']        = $current_per_size;
      // 目前所在頁數
      $page['no']              = $current_page_no;

      // 取出資料
      $userlist_sql   = $daily_data. $sql_order. ' OFFSET '.$page['no'].' LIMIT '.$page['per_size'].';';
      // echo '<pre>', var_dump($userlist_sql), '</pre>';
      // die();
      $result = runSQLall($userlist_sql);

      for($i=1;$i<=$result[0];$i++){
        $item = $page['no'] + $i;
        $account = $result[$i]->member_account;
        $bet_slip = $result[$i]->bet_count;
        $bet_amount = $result[$i]->betvalid;
        $profit_and_loss = $result[$i]->betprofit;
        $update_time = gmdate('Y-m-d H:i:s',strtotime($result[$i]->updatetime) + -4*3600);
      
        $show_list_array[]= array(
          'item'=> $item,
          'account'=> $account,
          'bet_slip'=> $bet_slip,
          'bet_amount'=> '$'.$bet_amount,
          'profit_and_loss'=> '$'.$profit_and_loss,
          'update_time'=> $update_time
        );
      }
      $output = array(
        "sEcho" 								=> intval($secho),
        "iTotalRecords" 				=> intval($page['per_size']),
        "iTotalDisplayRecords" 	=> intval($page['all_records']),
        "data" 									=> $show_list_array
      );
    }else{
      // 搜尋區間沒資料
      $output = array(
        "sEcho"                                     => 0,
        "iTotalRecords"                     => 0,
        "iTotalDisplayRecords"         => 0,
        "data"                                         => ''
      );
    }
  }

  echo json_encode($output);

}elseif($action == 'query_summary' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($query_sql_array)  AND $query_chk == 1 ) {

    // 產生使用者所選之娛樂城及對映分類
    $getcasinogamecate=getcasinogamecate($query_sql_array['casino_query'],$query_sql_array['gc_query']);

    // 產生日報、十分鐘所需日期格式
    $sqldate=dateconvertsqldate($query_sql_array);
    // var_dump($sqldate);die();
    // 娱乐城无法对映游戏分类
    if(count($getcasinogamecate)==0){
      // $output = array('logger' => '娱乐城无法对映游戏分类。错误代码：1907231054637。');
      // echo json_encode($output);die();

      $output_msg = array(
        'logger' => false
      );
      echo json_encode($output_msg);die();
    }
      

    if ($sqldate['have_ten_mins']=='1'){
      // 產生十分鐘報表，搜尋條件
      $tenmin_query_str = tenmin_query_str($query_sql_array,$sqldate);
      // 判斷搜尋條件是否有錯
      if(isset($tenmin_query_str['logger'])){
          $output = array('logger' => $tenmin_query_str['logger']);
          echo json_encode($output);die();
      }else{
        // 產生十分鐘匯總資料
        $ten_min_data=ten_min_data($tenmin_query_str);
      }
    }
    // var_dump($sqldate);die();


    if ($sqldate['have_daily']=='1'){
      // 產生日報，搜尋條件
      $daily_query_str = daily_query_str($query_sql_array,$sqldate);
      // 判斷搜尋條件是否有錯
      if(isset($daily_query_str['logger'])){
          $output = array('logger' => $daily_query_str['logger']);
          echo json_encode($output);die();
      }else{
        // 產生日報匯總資料
        $daily_data=daily_data($daily_query_str,$getcasinogamecate);

        $sql_result = runSQLall($daily_data,0);
      }
    }

    // 日報及十分鐘都有資料
    if ($sqldate['have_daily']=='1' AND $sqldate['have_ten_mins']=='1'){
      // 以帳號為索引
      // $daily_data_index=account2index($daily_data);
      $daily_data_index=account2index($sql_result);

      $ten_min_data_index=account2index($ten_min_data);
      
      // 合併陣列
      $ary_merge=array_merge_recursive($daily_data_index, $ten_min_data_index);
      // 加總使用者資料
      $users_data_merge=combine_single_user_data($ary_merge);
      
      // 轉成美東時間
      $users_data=date_convert_est($users_data_merge);
      
      // 加總為娛樂城資料
      $casino_data=combine_casino_data($users_data);
      
      // 十分鐘有，日報沒有 
    }elseif($sqldate['have_daily']=='0' AND $sqldate['have_ten_mins']=='1'){
      // 以帳號為索引
      $ten_min_data_index=account2index($ten_min_data);
      
      // 轉成美東時間
      $users_data=date_convert_est($ten_min_data_index);
      
      // 加總為娛樂城資料
      $casino_data=combine_casino_data($users_data);
      
      // 十分鐘沒有，日報有 
    }elseif($sqldate['have_daily']=='1' AND $sqldate['have_ten_mins']=='0'){
      
      // 以帳號為索引
      $daily_data_index=account2index($sql_result);

      // 轉成美東時間
      $users_data=date_convert_est($daily_data_index);

      // 加總為娛樂城資料
      $casino_data=combine_casino_data($users_data);
    }

    // echo '<pre>"'.var_dump($casino_data).'"</pre>';die();
    $dl_csv_code = jwtenc('statistics_report', $query_sql_array);

    if($casino_data['betprofit'] >= 0){
      $difference_payout_style = 'color: green;';
      $payout_style = '-';
    }else{
      $difference_payout_style = 'color: red;';
      $payout_style = '';
    }

    $json_arr=[
        'member_betlog_result_count'=>$casino_data['people'],
        'member_betlog_counter'=>number_format($casino_data['bet_count'],0),
        // 'member_betlog_counter'=>number_format($count_list),

        'num_member_betlog_betvalidsum'=>number_format($casino_data['betvalid'],2),
        'difference_payout_style'=>$difference_payout_style,
        'payout_style'=> $payout_style,
        'num_member_betlog_accumulated'=>number_format($casino_data['betprofit'],2),
        'lastupdate'=>$casino_data['updatetime'],
        'date_rang'=> $sqldate['title'],
        'sum_report_url'=>'statistics_report_action.php?a=sum_report&csv='.$dl_csv_code,
    ];
    echo json_encode($json_arr);

}elseif($action == 'sum_report' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($CSVquery_sql_array) ) {
  // excel 

    // 產生使用者所選之娛樂城及對映分類
	if (is_array($CSVquery_sql_array['gc_query'])) {
		$categories = $CSVquery_sql_array['gc_query'];
	} else {
		$categories = get_object_vars($CSVquery_sql_array['gc_query']);
	}
    $getcasinogamecate = getcasinogamecate($CSVquery_sql_array['casino_query'], $categories);
    // 產生日報、十分鐘所需日期格式
    $sqldate=dateconvertsqldate($CSVquery_sql_array);
	var_dump($CSVquery_sql_array);
    // 娱乐城无法对映游戏分类
    if(count($getcasinogamecate)==0){
	    echo '<script>alert("查无资料，娱乐城无该分类项目。");window.close();</script>';die();
    }
    
    if ($sqldate['have_ten_mins']=='1'){
      // 產生十分鐘報表，搜尋條件
      $tenmin_query_str = tenmin_query_str($CSVquery_sql_array,$sqldate);
      
      // 產生原始投注資料_十分鐘 sql 條件
      $bet_tenmin_query_str = bet_tenmin_query_str($CSVquery_sql_array,$sqldate);



      // 判斷搜尋條件是否有錯
      if(isset($tenmin_query_str['logger'])){
          echo '<script>alert("'.$tenmin_query_str['logger'].'");window.close();</script>';die();
      }else{
        // 產生十分鐘匯總資料
        $ten_min_data=ten_min_data($tenmin_query_str);
        
        // 產生原始投注紀錄_十分鐘
        $bet_ten_min_data=bet_ten_min_data($bet_tenmin_query_str);
      }
    }



    if ($sqldate['have_daily']=='1'){
      // 產生日報，搜尋條件
      $daily_query_str = daily_query_str($CSVquery_sql_array,$sqldate);

      // 產生日報原始資料，搜尋條件
      $bet_daily_query_str = bet_daily_query_str($CSVquery_sql_array,$sqldate);

      // 判斷搜尋條件是否有錯
      if(isset($daily_query_str['logger'])){
          echo '<script>alert("'.$daily_query_str['logger'].'");window.close();</script>';die();
      }else{
        // 產生日報匯總資料
        $daily_data=daily_data($daily_query_str,$getcasinogamecate);
        $sql_result = runSQLall($daily_data,0);
        // 產生日報原始資料
        $bet_daily_data=bet_daily_data($bet_daily_query_str,$getcasinogamecate);
        // print("<pre>" . print_r($bet_daily_data, true) . "</pre>");
        // print("<pre>" . print_r($bet_daily_query_str, true) . "</pre>");
        // print("<pre>" . print_r($getcasinogamecate, true) . "</pre>");
        // die();

      }
    }


    // 日報及十分鐘都有資料
    if ($sqldate['have_daily']=='1' AND $sqldate['have_ten_mins']=='1'){
        // 以帳號為索引
        // $daily_data_index=account2index($daily_data);
        $daily_data_index=account2index($sql_result);

        // echo '<pre>', var_dump($daily_data_index), '</pre>';
        // die();
        $ten_min_data_index=account2index($ten_min_data);
        // 合併陣列
        $ary_merge=array_merge_recursive($daily_data_index, $ten_min_data_index);
        
        // 加總使用者資料
        $users_data_merge=combine_single_user_data($ary_merge);
        // 轉成美東時間
        $users_data=date_convert_est($users_data_merge);
        
    // 十分鐘有，日報沒有 
    }elseif($sqldate['have_daily']=='0' AND $sqldate['have_ten_mins']=='1'){
        // 以帳號為索引
        $ten_min_data_index=account2index($ten_min_data);
        // 轉成美東時間
        $users_data=date_convert_est($ten_min_data_index);
      
    // 十分鐘沒有，日報有 
    }elseif($sqldate['have_daily']=='1' AND $sqldate['have_ten_mins']=='0'){
        // 以帳號為索引
        // $daily_data_index=account2index($daily_data);
        $daily_data_index=account2index($sql_result);

        
        // 轉成美東時間
        $users_data=date_convert_est($daily_data_index);
    }

    if (count($users_data) >= 1) {
        $j = $v = 1;
        // 總表欄位名稱
        $userdata_convert_record[0][$v++] = $tr['ID'];
        $userdata_convert_record[0][$v++] = $tr['Account'];
        $userdata_convert_record[0][$v++] = $tr['bet slip'];
        $userdata_convert_record[0][$v++] = $tr['bet amount'];
        $userdata_convert_record[0][$v++] = $tr['profit and loss'];
        $userdata_convert_record[0][$v++] = $tr['last update time'];

        // 將使用者資料轉成一筆筆陣列格式，方便匯出
        $userdata_convert_record=array_merge($userdata_convert_record,userdata_convert_record($users_data));
    } else {
      // $userdata_convert_record[] = '无统​​计资料!!';
      echo '<script>alert("(1908051738147) 无统​​计资料!!");window.close();</script>';
      die();

      // echo '<script>alert("(1908051738147) 无统​​计资料!!");history.go(-1);</script>';
    }

    // var_dump($userdata_convert_record);die();

    // 明細_十分鐘
    if (isset($ten_min_data_index))  {
        // 明細欄位名稱
        $userdata_detail[0][1] = '十分钟纪录';
        $v = 1;
        $userdata_detail[1][$v++] = $tr['ID'];
        $userdata_detail[1][$v++] = $tr['Account'];
        $userdata_detail[1][$v++] = $tr['bet slip'];
        $userdata_detail[1][$v++] = $tr['bet amount'];
        $userdata_detail[1][$v++] = $tr['profit and loss'];
        $userdata_detail[1][$v++] = $tr['last update time'];
      
        // 十分鐘轉成美東時間
        $xls_detail_ten_min=date_convert_est($ten_min_data_index);
        $userdata_detail=array_merge($userdata_detail,userdata_convert_record($xls_detail_ten_min));

        // 十分鐘-每筆投注明細
        $bet_ten_min_xlsx=bet_ten_min_xlsx($bet_ten_min_data);

    }else{
        $bet_ten_min_xlsx[]='無十分鐘投注資料！';
    }



    // 明細_日報
    if (isset($daily_data_index))  {
        $j=count($daily_data_index);
        // 明細欄位名稱
        $userdata_detail[$j+2][1] = '日报纪录';
        $v = 1;
        $userdata_detail[$j+3][$v++] = $tr['ID'];
        $userdata_detail[$j+3][$v++] = $tr['Account'];
        $userdata_detail[$j+3][$v++] = $tr['bet slip'];
        $userdata_detail[$j+3][$v++] = $tr['bet amount'];
        $userdata_detail[$j+3][$v++] = $tr['profit and loss'];
        $userdata_detail[$j+3][$v++] = $tr['last update time'];
      
        // 日報轉成美東時間
        $xls_detail_daily=date_convert_est($daily_data_index);
        $userdata_detail=array_merge($userdata_detail,userdata_convert_record($xls_detail_daily));

        // 日報-每筆投注明細
        $bet_daily_data_xlsx=bet_daily_data_xlsx($bet_daily_data);
    
    }else{
        $bet_daily_data_xlsx[]='無日報投注資料！';
    }

    // print("<pre>" . print_r($ten_min_data_index, true) . "</pre>");die();


    // 清除快取以防亂碼
    ob_end_clean();
  
    //---------------phpspreadsheet----------------------------
    $spreadsheet = new Spreadsheet();

    // 預設字型
    $spreadsheet->getDefaultStyle()->getFont()->setName('Microsoft YaHei');

    // Create a new worksheet called "My Data"
    $myWorkSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, '统计资料查询');
    // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
    $spreadsheet->addSheet($myWorkSheet, 0);

    // Create a new worksheet called "My Data"
    $myWorkSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, '十分鐘投注資料');
    // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
    $spreadsheet->addSheet($myWorkSheet, 2);

    // Create a new worksheet called "My Data"
    $myWorkSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, '日報投注資料');
    // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
    $spreadsheet->addSheet($myWorkSheet, 3);



    // 總表索引標籤開始寫入資料
    $sheet = $spreadsheet->setActiveSheetIndex(0);
    // 寫入總表資料陣列
    $sheet->fromArray($userdata_convert_record, null, 'A1');
    $worksheet = $spreadsheet->getActiveSheet();
    // 凍結行
    $worksheet->freezePane('A2');
    // 取得最高行號
    $highestRow = ($worksheet->getHighestRow()+1); 
    // 設定加總公式
    $worksheet->setCellValue('A'.$highestRow,'总合');
    $worksheet->setCellValue('C'.$highestRow,'=sum(C2:C'.($highestRow-1).')');
    $worksheet->setCellValue('D'.$highestRow,'=sum(D2:D'.($highestRow-1).')');
    $worksheet->setCellValue('E'.$highestRow,'=sum(E2:E'.($highestRow-1).')');
    // 自動欄寬
    foreach (range('A', $worksheet->getHighestColumn()) as $column) {
        $spreadsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
    }
    unset($userdata_convert_record);
    

    // 明細索引標籤開始寫入資料
    $sheet_detail = $spreadsheet->setActiveSheetIndex(1);
    // 寫入明細資料陣列
    $sheet_detail->fromArray($userdata_detail, null, 'A1');
    // Rename worksheet
    $spreadsheet->getActiveSheet()->setTitle('明细');
    // 自動欄寬
    foreach (range('A', $worksheet->getHighestColumn()) as $column) {
        $spreadsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
    }
    unset($userdata_detail);


    // 十分鐘投注明細索引標籤開始寫入資料
    $sheet_detail = $spreadsheet->setActiveSheetIndex(2);
    // 寫入十分鐘資料陣列
    $sheet_detail->fromArray($bet_ten_min_xlsx, null, 'A1');
    // 自動欄寬
    foreach (range('A', $worksheet->getHighestColumn()) as $column) {
        $spreadsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
    }
    unset($bet_ten_min_xlsx);


    // 日報投注明細索引標籤開始寫入資料
    $sheet_detail = $spreadsheet->setActiveSheetIndex(3);
    // 寫入日報鐘資料陣列
    $sheet_detail->fromArray($bet_daily_data_xlsx, null, 'A1');
    // 自動欄寬
    foreach (range('A', $worksheet->getHighestColumn()) as $column) {
        $spreadsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
    }
    unset($bet_daily_data_xlsx);


    $spreadsheet->setActiveSheetIndex(0);



    // xlsx
    $file_name = 'statistics_report' . date('ymd_His', time());
    // var_dump($file_name);die();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $file_name . '.xlsx"');
    header('Cache-Control: max-age=0');

    // 直接匯出，不存於disk
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');

      // print("<pre>" . print_r($userdata_convert_record, true) . "</pre>");
}elseif(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
}else{
  $logger = '(x) 只有管理員或有權限的會員才可以使用。';
  echo $logger;
}
// -----------------------------------------------------------------------
// MAIN END
// -----------------------------------------------------------------------
