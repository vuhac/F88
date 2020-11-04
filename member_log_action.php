<?php
// ----------------------------------------------------------------------------
// Features:	後台--會員操作記錄
// File Name:	member_log_action.php
// Author:		snowiant@gmail.com
// Related:
//    member_log.php member_log_lib.php
//    DB table: root_memberlog
//    member_log_action：有收到 member_log.php 透過ajax 傳來的  _GET 時會將 _GET
//        取得的值進行驗證，並檢查是否為可查詢對象，如果是就直接丟入 $query_sql_array 中再
//        引用 member_log_lib.php 中的涵式 show_member_logininfo() 並將返回
//        的資料放入 table 中給 datatable 處理，再以 ajax 丟給 member_log.php 來顯示。
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

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
// 本程式使用的 function
// -------------------------------------------------------------------------

// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s'){
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

// 過往時間計算function
// ref:http://qiita.com/wgkoro@github/items/eee4e6854535d62ca55b
function convert_to_fuzzy_time($times){
    global $tr;
    date_default_timezone_set('America/St_Thomas');
    $unix   = strtotime($times);
    $now    = time();
    $diff_sec   = $now - $unix;

    if($diff_sec < 60){
        $time   = $diff_sec;
        $unit   = $tr['Seconds ago'];
    }
    elseif($diff_sec < 3600){
        $time   = $diff_sec/60;
        $unit   = $tr['minutes ago'];
    }
    elseif($diff_sec < 86400){
        $time   = $diff_sec/3600;
        $unit   = $tr['hours ago'];
    }
    elseif($diff_sec < 2764800){
        $time   = $diff_sec/86400;
        $unit   = $tr['days ago'];
    }
    elseif($diff_sec < 31536000){
        $time   = $diff_sec/2592000;
        $unit   = $tr['months ago'];
    }
    else{
        $time   = $diff_sec/31536000;
        $unit   = $tr['years ago'];
    }

    return (int)$time .$unit;
}



// 查詢帳戶名稱，對映的id，方便點選帳號，可連至會員詳細資訊頁面
$account_chk = 0;
function query_accountid($account){
    // 原版
    // $query_sql = 'SELECT id FROM root_member WHERE account = \''.$account.'\';';
    $query_sql = <<<SQL
      SELECT id,therole FROM root_member WHERE account = '{$account}'
SQL;
    $query_result = runSQLall($query_sql);

    if($query_result[0] == 1){
        // 管理員不顯示會員詳細頁面的連結
        if($query_result[1]->therole == 'R'){
          $return = $account;
        }else{
          $return = '<a href="member_account.php?a='.$query_result[1]->id.'" target="_BLANK" data-role=\" button\" title="连至会员详细页面">'.$account.'</a>';

        }
        // $return = $account;
    }
    else{
      //如果查不到資料，就不連結至會員詳細資訊頁面，只顯示沒有詳細資訊
      $return ='<a title="没有详细资讯" data-role=\"button\"  >'.$account.'</a>';
    }

    return $return;
}



// 產生查詢條件
function query_str($query_sql_array){
  $query_top = 0;
  $show_member_log_sql = '';

  //檢查query的值
  if(isset($query_sql_array['recent_once']) AND $query_sql_array['recent_once']==1){
    $show_member_log_sql .=<<<SQL
      id in( SELECT max (id) as max_id
                    FROM root_memberlog
                    group by who )
SQL;
    $query_top = 1;
  }

  if(isset($query_sql_array['account_ip_statistics']) AND $query_sql_array['account_ip_statistics']==1){
    $show_member_log_sql .=<<<SQL
      id in( SELECT max (id) as max_id
              FROM root_memberlog
              WHERE who='{$query_sql_array["account_query"]}'
              group by agent_ip )
      SQL;
    $query_top = 1;
  }else if(isset($query_sql_array['account_ip_statistics']) AND $query_sql_array['account_ip_statistics']==2){
    if(isset($query_sql_array["ip_query"])){
      $show_member_log_sql .=<<<SQL
        id in( SELECT max (id) as max_id
                FROM root_memberlog
                WHERE agent_ip='{$query_sql_array["ip_query"]}'
                group by who )
      SQL;
      $query_top = 1;
    }
  }

  if(isset($query_sql_array['account_query']) AND $query_sql_array['account_query'] != NULL) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'who = \''.$query_sql_array['account_query'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['query_date_start_datepicker']) AND $query_sql_array['query_date_start_datepicker'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'occurtime >= \''.$query_sql_array['query_date_start_datepicker_gmt'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['query_date_end_datepicker']) AND $query_sql_array['query_date_end_datepicker'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'occurtime <= \''.$query_sql_array['query_date_end_datepicker_gmt'].'\'';
    $query_top = 1;

  }

  if(isset($query_sql_array['ip_query']) AND $query_sql_array['ip_query'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'agent_ip = \''.$query_sql_array['ip_query'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['fingerprint_query']) AND $query_sql_array['fingerprint_query'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'fingerprinting_id = \''.$query_sql_array['fingerprint_query'].'\'';
    $query_top = 1;
  }

  // 過濾維運帳號之log，不是維運帳號，組成過濾sql條件
  $judge_su_account=judge_su_account();
  if(!$judge_su_account['result']){
      if ($query_top == 1) {
          $show_member_log_sql .= ' AND ';
      }
      $show_member_log_sql.="who NOT IN('".$judge_su_account['sql']."')";
      $query_top = 1;
  }

  if($query_top == 1){
    $show_member_log_sql = ' WHERE '.$show_member_log_sql;
  }

  return $show_member_log_sql;
}

// 維運要可以看全部，站長及其它帳號不能看維運
function judge_su_account(){
    global $su;
    $return=[];
    if(in_array($_SESSION['agent']->account,$su['ops'])){
        $return['result']=true;
        $return['sql']='';
    }else{
        // 組成過濾$su['ops']之sql字串
        $return['result'] = false;
        $return['sql']= implode("','", $su['ops']);
    }

    return $return;
}

//判斷$string是否為json
function scopeIsJson($string){
    
    return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;

}

// 半形逗號轉成全形，方便CSV 匯出欄位對映正確
function comma_chgfullwidth($realcashvalue){
  $comma_chang=str_replace(",","，",$realcashvalue);
  return $comma_chang;
}

function sql_query(){
  return <<<SQL
      SELECT to_char((occurtime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as log_time,
              who as log_account,
              service,
              message,
              agent_ip as log_ip,
              fingerprinting_id as log_fingerprinting,
              id,
              target_users,
              sub_service,
              site as platform,
              ip_region
      FROM root_memberlog
SQL;
}

// 生成casion2casion服務的陣列
function casion2casion(){
    $sql=<<<SQL
				SELECT LOWER(casinoid) AS lname , UPPER(casinoid) as uname FROM casino_list 
SQL;
    $sql_result = runSQLall($sql);
    if($sql_result[0]>=1){unset($sql_result[0]);}
    
    foreach ($sql_result as $key => $val){
        $return_ary['ctoc'][]='gpk2'.$val->lname;
        $return_ary['ctoc'][]=$val->lname.'2gpk';

        $return_ary['logingame'][]='login'.$val->uname.'Game';
        
        $return_ary['casino_game'][]=$val->lname.'game';

      
    }

    return $return_ary;

}

// print("<pre>" . print_r($msg_tra, true) . "</pre>");die();

// 轉成服務中文
function service_convert($srvc='',$casino=[],$msg_tra){
    $msg='';
    global $tr;
    if(array_key_exists($srvc,$msg_tra)){
        $msg= $msg_tra[$srvc];
    } elseif(in_array($srvc,$casino['ctoc'])){
        $msg=$tr['casino news'];
    } elseif(in_array($srvc,$casino['logingame'])) {
        $msg1=str_replace('login','',$srvc);
        $msg2=str_replace('Game','',$msg1);
        $msg=$tr['Login'].$msg2.$tr['Game'];
    } elseif(preg_match("/API$/i", $srvc)) {
        $msg=str_replace("API",$tr['casino news'],$srvc);
    } elseif(in_array($srvc,$casino['casino_game'])){
        $msg=str_replace('game',$tr['Game'],$srvc);
    } elseif($srvc=='') {
        $msg='---';
    } else {
        $msg=$srvc;
    }
    return $msg;
}

// ip所在區域函數
function find_ip_region($ip){
    $to_array = json_decode(json_encode($ip), true);
    $curl_ip_data = curl_ip_region($to_array);
    foreach($curl_ip_data as $v){
      if(isset($v['country_en']) AND $v['country_en'] != ''){
        $ip_location = $v['country_en']." ".$v['city_en'];
      }else{
        $ip_location = '暂无地区资料';
      }
    }
    return $ip_location;
}

// 撈出篩選條件個別資料筆數
function count_unit_statistic_total($account_ip_statistics,$account,$ip,$sdate,$edate){
  if(isset($account) && $account != ''){
    switch($account_ip_statistics){
      case '1':
        $sql = <<<SQL
          SELECT count(id) as total
            FROM root_memberlog
            WHERE who='{$account}'
            AND agent_ip='{$ip}'
            AND occurtime>='{$sdate}'
            AND occurtime<='{$edate}'
        SQL;
        $result = runSQLall($sql);
        $total = $result[1]->total;
        return $total;
        break;
      case '2':
        $sql = <<<SQL
          SELECT count(id) as total
          FROM root_memberlog
          WHERE who='{$account}'
          AND agent_ip='{$ip}'
          AND occurtime>='{$sdate}'
          AND occurtime<='{$edate}'
        SQL;
        $result = runSQLall($sql);
        $total = $result[1]->total;
        return $total;
        break;
      default:
        return;
        break;
    }
  }else{
    return;
  }
}

// -------------------------------------------------------------------------
// END function lib
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------


if(isset($_GET['get'])){
    $action = filter_var($_GET['get'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的測試');
}

if(isset($_GET['a']) AND $_GET['a'] != NULL) {
  $query_sql_array['account_query'] = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
  $account_chk = 1;
  $accountid = query_accountid($query_sql_array['account_query']);

  if($query_sql_array['account_query'] == ''){
    $output = array(
      "sEcho" => 0,
      "iTotalRecords" => 0,
      "iTotalDisplayRecords" => 0,
      "data" => [
          "download_url" => '#',
          "list" => [],
      ] 
    );
    echo json_encode($output);die();
  }

}

//檢查下載csv檔時，csv帶的長編碼經過解密之後的值
if(isset($_GET['csv'])){
  $CSVquery_sql_array = get_object_vars(jwtdec('log_download',$_GET['csv']));
  $csvfilename = sha1($_GET['csv']);
}

if(isset($_GET['sdate']) AND $_GET['sdate'] != NULL ) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['sdate'], 'Y-m-d H:i:s')) {
    $query_sql_array['query_date_start_datepicker'] = $_GET['sdate'];
    $query_sql_array['query_date_start_datepicker_gmt'] = gmdate('Y-m-d H:i:s',strtotime($query_sql_array['query_date_start_datepicker'].'-04')+8*3600).' +08:00';
  }
}


if(isset($_GET['edate']) AND $_GET['edate'] != NULL ) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['edate'], 'Y-m-d H:i:s')) {
    // $query_sql_array['query_date_end_datepicker'] = $_GET['edate'];
    $query_sql_array['query_date_end_datepicker'] = date("Y-m-d H:i:s",strtotime($_GET['edate']."+ 5 seconds")); // 因為毫秒的關係，所以結束時間無法搜尋到同一秒的資料，加5秒

    $query_sql_array['query_date_end_datepicker_gmt'] = gmdate('Y-m-d H:i:s',strtotime($query_sql_array['query_date_end_datepicker'].'-04')+8*3600).' +08:00';
  }
}

if(isset($_GET['ip']) AND $_GET['ip'] != NULL ) {
  if(filter_var($_GET['ip'], FILTER_VALIDATE_IP)){
    $query_sql_array['ip_query'] = $_GET['ip'];
  }
}

if(isset($_GET['recent_one']) AND (filter_var($_GET['recent_one'], FILTER_SANITIZE_NUMBER_INT) =='1' )) {
    $query_sql_array['recent_once']=true;
}
// var_dump($recent_once);die();

if(isset($_GET['account_ip_statistics']) AND ((filter_var($_GET['account_ip_statistics'], FILTER_SANITIZE_NUMBER_INT) =='1' ) || (filter_var($_GET['account_ip_statistics'], FILTER_SANITIZE_NUMBER_INT) =='2' )) ){
  $query_sql_array['account_ip_statistics']=$_GET['account_ip_statistics'];
}else{
  $query_sql_array['account_ip_statistics']=false;
}

if(isset($_GET['fp']) AND $_GET['fp'] != NULL ) {
  $query_sql_array['fingerprint_query'] = filter_var($_GET['fp'], FILTER_SANITIZE_STRING);
}
// var_dump($_GET);//die();

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

// -----------------------------------------------------------------------
// datatable server process 用資料讀取
// -----------------------------------------------------------------------

  // 定義前後台名稱
  $platform_name=['f'=>'前台','b'=>'后台'];
  // 服務及子服務轉中文
  $msg_tra=[
      'accounting'                   => $tr['Account Management'],                       //  '帐务管理'
      'admin'                        => $tr['administrator'],                            // '管理员'
      'agent login'                  => $tr['agent logout'],                             //'代理商登入',
      'agent logout'                 => $tr['agent login'],                              //'代理商登出',
      'authority'                    => $tr['Certification'],                            //'认证',
      'agent_profitloss_calculation' => $tr['Commission calculation'],                   //'佣金計算',
      'behavior'                     => $tr['Operational behavior'],                     //'操作行为',
      'bonus'                        => $tr['dividend'],                                 //'红利',
      'cashtransfer'                 => $tr['cash transfer'],                            // '现金转帐'
      'deposit'                      => $tr['deposits'],                                 // '存款'
      'daily_report'                 => $tr['daily_report'],                             // '日報表'
      'information'                  => $tr['information'],                              //'资讯',
      'login'                        => $tr['Login'],                                    // 登入'
      'logout'                       => $tr['Logout'],                                   // '登出'
      'manual_gcashtogtoken'         => $tr['Manual cash deposit into game currency'],   //'手动现金存入游戏币',
      'marketing'                    => $tr['profit and promotion'],                     //'营销管理'
      'member'                       => $tr['member'],                                   // '会员'

      // 
      'administrator'                => $tr['administrator'].$tr['Login'],               // '管理員'
      // 
      'member create'                => $tr['Member establishment'],                     //'会员建立',
      'member_agentdepositgcash'     => $tr['User cash deposit'],                        //'用户现金入款',
      'member_edit'                  => $tr['Member editor'],                            //'会员编辑',
      'onlinepayment'                => $tr['onlinepay'],                                //'线上支付',
      'partner'                      => $tr['Partner'],                                  //'合作伙伴'
      'preferential_calculation'     => $tr['Preferential calculation'],                 //'反水計算'
      'receivemoney'                 => $tr['payout'],                                   //'彩金',
      'payout'                       => $tr['payout'],                                   //'彩金',
      'register_agent'               => $tr['Registered agent'],                         //'注册代理商',
      'registration'                 => $tr['register'],                                 //'注册',
      'realtime_reward'              => $tr['reward'],                                   //'反水',
      'wallet'                       => $tr['wallet'],                                   //'钱包',
      'withdrawal'                   => $tr['withdrawals']                               //'提款',
  ];

if($action == 'query_log' AND isset($query_sql_array) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  // -----------------------------------------------------------------------
  // 列出所有的會員資料及人數 SQL
  // -----------------------------------------------------------------------

  // 處理 datatables 傳來的排序需求
  if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
    if($_GET['order'][0]['dir'] == 'asc'){ $sql_order_dir = 'ASC';
    }else{ $sql_order_dir = 'DESC';}
    if($_GET['order'][0]['column'] == 0){ $sql_order = 'ORDER BY id '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 1){ $sql_order = 'ORDER BY who '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 2){ $sql_order = 'ORDER BY occurtime '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 3){ $sql_order = 'ORDER BY agent_ip '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 4){ $sql_order = 'ORDER BY ip_region '.$sql_order_dir;
    // }elseif($_GET['order'][0]['column'] == 5){ $sql_order = 'ORDER BY fingerprinting_id '.$sql_order_dir;
    }else{ $sql_order = 'ORDER BY id ASC';}
  }else{ $sql_order = 'ORDER BY id ASC';}

  // 取得查詢條件
  $query_str = query_str($query_sql_array);
  $sql_tmp   = sql_query().$query_str." ".$sql_order;
  // echo $query_str;die();


  // 算資料總筆數
  $userlist_sql   = $sql_tmp.';';
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


  // 取出資料
  $userlist_sql   = $sql_tmp.' OFFSET '.$page['no'].' LIMIT '.$page['per_size'].';';
  // echo '<pre>', var_dump($userlist_sql), '</pre>';
  // die();
  $userlist = runSQLall($userlist_sql);

  // 存放列表的 html -- 表格 row -- tables DATA
  $show_listrow_html = '';
  // 判斷 root_member count 數量大於 1
  if($userlist[0] >= 1) {
    // 以會員為主要 key 依序列出每個會員的貢獻金額
    for($i = 1 ; $i <= $userlist[0]; $i++){
      $count = $page['no'] + $i;

      // 查詢條件是否有帶帳號，如果沒有，則每筆都會查詢會員id，如果有，則不需查詢會員id
      if($account_chk == 0 OR empty($query_sql_array['account_query'])){
          $account_data = query_accountid($userlist[$i]->log_account);
      }else{
        // 有輸入會員帳號，連至會員詳細資料
        $account_data = $accountid;

      }

      // 生成判斷服務組成之字串陣列
      $casion_name = casion2casion();

      $platform_show    = isset($platform_name[$userlist[$i]->platform])?$platform_name[$userlist[$i]->platform]:'----';
      
      $service_show     = service_convert($userlist[$i]->service,$casion_name,$msg_tra);

      $sub_service_show = service_convert($userlist[$i]->sub_service,$casion_name,$msg_tra);

      // ip區域
      $ip_location='';
      if(is_null($userlist[$i]->ip_region) || empty($userlist[$i]->ip_region)){
        $ip_location = find_ip_region($userlist[$i]->log_ip);
      }else{
        $ip_location = $userlist[$i]->ip_region;
      }
  

      $deltail_map = '';
      $deltail_map .=<<<HTML
          <button type="button" class="btn btn-info btn-xs pull-right modal-btn" data-toggle="modal" data-target="#{$userlist[$i]->id}">{$tr['detail']}</button>
          <div class="modal fade" id="{$userlist[$i]->id}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <h2 class="modal-title" id="myModalLabel">{$tr['Member service detailed record']}</h2>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>

                <div class="modal-body">
                <table class="table table-striped">
                  <tbody>
                    <tr>
                      <th scope="row" style="width:30%;">{$tr['seq']}</th>
                      <td>{$userlist[$i]->id}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['event time']}</th>
                      <td>{$userlist[$i]->log_time}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['operator']}</th>
                      <td>{$userlist[$i]->log_account}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['Main service type']}</th>
                      <td>{$service_show}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['Secondary service type']}</th>
                      <td>{$sub_service_show}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['ip address']}</th>
                      <td>{$userlist[$i]->log_ip}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['ip location']}</th>
                      <td>{$ip_location}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['Process message']}</th>
                      <td><textarea disabled style="width:420px;background-color:transparent;border:none;height:100%;">{$userlist[$i]->message}</textarea></td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['Browser fingerprint'] }</th>
                      <td>{$userlist[$i]->log_fingerprinting}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['target user']}</th>
                      <td>{$userlist[$i]->target_users}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['platform']}</th>
                      <td>{$platform_show}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{$tr['off']}</button>
              </div>
            </div>
          </div>
        </div>
HTML;

      $b['id']          = $count;
      $b['account']     = $account_data;
      $b['logintime']   = $userlist[$i]->log_time.$tr['about'].convert_to_fuzzy_time($userlist[$i]->log_time);
      $b['ip']          = $userlist[$i]->log_ip;
      $b['ip_location'] = $ip_location;
      $b['fp']          = $userlist[$i]->log_fingerprinting;
      $b['detail']      = $deltail_map;
      $b['time']        = count_unit_statistic_total($query_sql_array['account_ip_statistics'],$userlist[$i]->log_account,$userlist[$i]->log_ip,$query_sql_array['query_date_start_datepicker_gmt'],$query_sql_array['query_date_end_datepicker_gmt']);
      $b['unprocessed_account'] = $userlist[$i]->log_account;

      // 顯示的表格資料內容
      $show_listrow_array[] = array(
        'id'          => $b['id'],
        'account'     => $b['account'],
        'occurtime'   => $b['logintime'],
        'ip'          => $b['ip'],
        'ip_location' => $b['ip_location'],
        'fp'          => $b['fp'],
        'detail'      => $b['detail'],
        'time'        => $b['time'],
        'unprocessed_account' => $b['unprocessed_account']
      );
    }
    
    $dl_csv_code = jwtenc('log_download', $query_sql_array);
    
    $output = array(
      "sEcho" => intval($secho),
      "iTotalRecords" => intval($page['per_size']),
      "iTotalDisplayRecords" => intval($page['all_records']),
      "data" => [
                "download_url" => 'member_log_action.php?get=dl_csv&csv='. $dl_csv_code,
                "list" => $show_listrow_array,
      ]
    );

  }else{
    // NO member
    $output = array(
      "sEcho" => 0,
      "iTotalRecords" => 0,
      "iTotalDisplayRecords" => 0,
      "data" => [
        "download_url" => '#',
        "list" => [],
      ]
    );
  }
  // end member sql
  echo json_encode($output);

}elseif($action == 'query_csv' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($query_sql_array)) {
    // 取得查詢條件
    $query_str = query_str($query_sql_array);

    $sql_tmp   = sql_query().$query_str;
    // 算資料總筆數
    $userlist_sql   = $sql_tmp.';';
    //echo $userlist_sql;
    $userlist_count = runSQL($userlist_sql);

    // var_dump($userlist_sql);//查到最初組成查詢字串
    $dl_csv_code = jwtenc('log_download', $query_sql_array);
    $json_arr = [
      'member_log_result_count' => $userlist_count,
      'download_url' => 'member_log_action.php?get=dl_csv&csv='.$dl_csv_code,
    ];
    echo json_encode($json_arr);
}elseif($action == 'dl_csv' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($CSVquery_sql_array)){
    $query_str = query_str($CSVquery_sql_array);
    if(isset($query_str['logger'])){
      $output = array('logger' => $query_str['logger']);
      echo json_encode($output);
    }else{
        $sql_tmp   = sql_query().$query_str.' order by "occurtime" DESC';
        $userlist_sql   = $sql_tmp.';';
        $userlist = runSQLall($userlist_sql);
        // print("<pre>" . print_r($userlist, true) . "</pre>");die();


        $casion_name=casion2casion();

   
        if($userlist[0] >= 1) {
            $j=$v = 1;
            $xls_memberlog[0][$v++] = $tr['ID'];
            $xls_memberlog[0][$v++] = $tr['seq'];
            $xls_memberlog[0][$v++] = $tr['time'];
            $xls_memberlog[0][$v++] = $tr['operator'];
            $xls_memberlog[0][$v++] = $tr['Main service type'];
            $xls_memberlog[0][$v++] = $tr['Secondary service type'];
            $xls_memberlog[0][$v++] = $tr['ip address'];
            $xls_memberlog[0][$v++] = $tr['ip location'];
            $xls_memberlog[0][$v++] = $tr['Process message'];
            $xls_memberlog[0][$v++] = $tr['registerfingerprinting'];
            $xls_memberlog[0][$v++] = $tr['target user'];
            $xls_memberlog[0][$v++] = $tr['platform'];

            for ($i = 1; $i <= $userlist[0]; $i++) {
                $v= 1;

                // ip所在區域 
                $to_array = json_decode(json_encode($userlist[$i]->log_ip), true);
                $curl_ip_data = curl_ip_region($to_array);

                $ip_location='';
                if(is_null($userlist[$i]->ip_region) || empty($userlist[$i]->ip_region)){
                    $ip_location=find_ip_region($userlist[$i]->log_ip);
                }else{
                    $ip_location=$userlist[$i]->ip_region;
                }
                
                // 前後台
                $platform_show    = isset($platform_name[$userlist[$i]->platform])?$platform_name[$userlist[$i]->platform]:'----';
                $service_show     = service_convert($userlist[$i]->service,$casion_name,$msg_tra);
                $sub_service_show = service_convert($userlist[$i]->sub_service,$casion_name,$msg_tra);
 
                $xls_memberlog[$i][$v++] = $j;
                $xls_memberlog[$i][$v++] = $userlist[$i]->id;
                $xls_memberlog[$i][$v++] = $userlist[$i]->log_time;
                $xls_memberlog[$i][$v++] = $userlist[$i]->log_account;
                $xls_memberlog[$i][$v++] = $service_show;
                $xls_memberlog[$i][$v++] = $sub_service_show;
                $xls_memberlog[$i][$v++] = $userlist[$i]->log_ip;
                $xls_memberlog[$i][$v++] = $ip_location;
                $xls_memberlog[$i][$v++] = $userlist[$i]->message;
                $xls_memberlog[$i][$v++] = $userlist[$i]->log_fingerprinting;
                $xls_memberlog[$i][$v++] = $userlist[$i]->target_users;
                $xls_memberlog[$i][$v++] = $platform_show;
                $j++;
        }
        }else{echo '<script>alert("(1910181923) 无登入纪录资料!!");window.close();</script>';die();}
        
        // 清除快取以防亂碼
        ob_end_clean();

        //---------------phpspreadsheet----------------------------
        $spreadsheet = new Spreadsheet();
        
        // Create a new worksheet called "My Data"
        $myWorkSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, '登入纪录');

        // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
        $spreadsheet->addSheet($myWorkSheet, 0);

        // 總表索引標籤開始寫入資料
        $sheet = $spreadsheet->setActiveSheetIndex(0);
        // 寫入資料陣列
        $sheet->fromArray($xls_memberlog,NULL,'A1',true);

        // 自動欄寬
        $worksheet = $spreadsheet->getActiveSheet();

        foreach (range('A', $worksheet->getHighestColumn()) as $column) {
            $spreadsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
        }

        // xlsx
        $file_name='memberlog_'.date('ymd_His', time());
        // var_dump($file_name);die();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$file_name.'.xlsx"');
        header('Cache-Control: max-age=0');

        // 直接匯出，不存於disk
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

        die();
        
    }

  }else{
    // NO member
  $output = array(
    "sEcho" => 0,
    "iTotalRecords" => 0,
    "iTotalDisplayRecords" => 0,
    "data" => [
      "download_url" => '#',
      "list" => [],
    ]
  );
  echo json_encode($output);
}

// -----------------------------------------------------------------------
// datatable server process 用資料讀取 END
// -----------------------------------------------------------------------

// -----------------------------------------------------------------------
// MAIN END
// -----------------------------------------------------------------------
?>
