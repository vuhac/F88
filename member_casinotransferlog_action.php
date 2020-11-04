<?php
// ----------------------------------------------------------------------------
// Features:  後台--會員娛樂城轉換記錄
// File Name: member_casinotransferlog_action.php
// Author:    snowiant@gmail.com
// Related:
//    member_casinotransferlog.php member_casinotransferlog_lib.php
//    DB table: root_member_casino_transferrecords
//    member_casinotransferlog_action：有收到 member_casinotransferlog.php 透過ajax 傳來的  _GET 時會將 _GET
//        取得的值進行驗證，並檢查是否為可查詢對象，如果是就直接丟入 $query_sql_array 中再
//        引用 member_casinotransferlog_lib.php 中的涵式 show_member_casinotransferloginfo() 並將返回
//        的資料放入 table 中給 datatable 處理，再以 ajax 丟給 member_casinotransferlog.php 來顯示。
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 日結報表函式庫，判斷可以一次寫幾筆函式庫(仁博大師)
require_once dirname(__FILE__) ."/statistics_daily_report_lib.php";
// 報表匯出函式庫
require_once dirname(__FILE__) ."/lib_file.php";
// 娛樂城函式庫
require_once dirname(__FILE__) ."/casino_switch_process_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

$casinoLib = new casino_switch_process_lib();
$debug = 0;

// -------------------------------------------------------------------------
// 本程式使用的 function
// -------------------------------------------------------------------------
// $tr['Seconds ago'] = '秒前';
// $tr['minutes ago'] = '分前';
// $tr['hours ago'] = '小時前';
// $tr['days ago'] = '天前';
// $tr['months ago'] = '個月前';
// $tr['years ago'] = '年前';
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

// 產生查詢條件
function query_str($query_sql_array){
  global $tr;
  $query_top = 0;
  $show_member_log_sql = '';

  /*
  //檢查query的值
  if(isset($query_sql_array['account_query']) AND $query_sql_array['account_query'] != NULL ) {

    $member_id_sql = 'SELECT id FROM root_member WHERE account=\''.$query_sql_array['account_query'].'\';';
    $member_id_result = runSQLall($member_id_sql,0,'r');
    $show_member_log_sql .= 'memberid = \''.$member_id_result[1]->id.'\'';
    $query_top = 1;

  }
  */

  //檢查query的值
  if(isset($query_sql_array['account_query']) AND $query_sql_array['account_query'] != NULL ) {

    $member_id_sql = 'SELECT id FROM root_member WHERE account=\''.$query_sql_array['account_query'].'\';';
    $member_id_result = runSQLall($member_id_sql);

    if(isset($member_id_result) AND $member_id_result[0] == 1){
      $show_member_log_sql .= 'memberid = \''.$member_id_result[1]->id.'\'';
      $query_top = 1;
      //var_dump($show_member_log_sql);
      //die();
    } else{
      $logger =' \"'.$query_sql_array['account_query'].'\" '.$tr['No account'];
      // var_dump($logger);die();
      //echo '<script>alert("'.$logger.'");</script>';
    }
  }

  // 娛樂城
  if(isset($query_sql_array['check_casino']) AND $query_sql_array['check_casino'] != NULL ) {
    // array組成字串
    $query_casino_sql='IN(\''.implode("','",$query_sql_array['check_casino']).'\')';

    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
      // $show_member_log_sql = $show_member_log_sql.' WHERE ';
    }

    $show_member_log_sql .= '(source '.$query_casino_sql;
    $show_member_log_sql .= ' OR destination '.$query_casino_sql.')';
    //var_dump($show_member_log_sql);die();
    $query_top = 1;
  }

  if(isset($query_sql_array['query_date_start_datepicker']) AND $query_sql_array['query_date_start_datepicker'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql .= 'occurtime >= \''.$query_sql_array['query_date_start_datepicker_gmt'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['query_date_end_datepicker']) AND $query_sql_array['query_date_end_datepicker'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql .= 'occurtime <= \''.$query_sql_array['query_date_end_datepicker_gmt'].'\'';
    $query_top = 1;
  }

  if($query_top == 1 AND !isset($logger)){
    $return_sql = ' WHERE '.$show_member_log_sql;
  } elseif(isset($logger)){
    $return_sql['logger'] = $logger;
  } else{
    $return_sql = '';
  }

  return $return_sql;
}





// -------------------------------------------------------------------------
// END function lib
// -------------------------------------------------------------------------


// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------

// var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);

if(isset($_GET['get'])){
    $action = filter_var($_GET['get'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的測試');
}

if(isset($_GET['a']) AND $_GET['a'] != NULL ) {
  $query_sql_array['account_query'] = filter_var($_GET['a'], FILTER_SANITIZE_STRING);

}

if(isset($_GET['csv'])){
  $CSVquery_sql_array = get_object_vars(jwtdec('casinotransferlog',$_GET['csv']));
  $csvfilename = sha1($_GET['csv']);
}

if(isset($_GET['k'])) {
    $logfile_sha = $_GET['k'];
}

// 娛樂城
//var_dump($_GET);die();
if(isset($_GET['casino_query']) AND $_GET['casino_query'] != NULL) {

  $query_sql_array['check_casino'] = filter_var_array($_GET['casino_query'],FILTER_SANITIZE_STRING);
}
//var_dump($_GET['casino_query']);
//die();

if(isset($_GET['sdate']) AND $_GET['sdate'] != NULL ) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['sdate'], 'Y-m-d H:i')) {
    $query_sql_array['query_date_start_datepicker'] = $_GET['sdate'].':00';
    $query_sql_array['query_date_start_datepicker_gmt'] = gmdate('Y-m-d H:i:s.u',strtotime($query_sql_array['query_date_start_datepicker'].'-04')+8*3600).'+08:00';

  }
}

if(isset($_GET['edate']) AND $_GET['edate'] != NULL ) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['edate'], 'Y-m-d H:i')) {
    $query_sql_array['query_date_end_datepicker'] = $_GET['edate'].':59';
    $query_sql_array['query_date_end_datepicker_gmt'] = gmdate('Y-m-d H:i:s.u',strtotime($query_sql_array['query_date_end_datepicker'].'-04')+8*3600).'+08:00';
  }
}

//var_dump($_GET);die();
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

if($action == 'query_log' AND isset($query_sql_array) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  // -----------------------------------------------------------------------
  // 列出所有的會員資料及人數 SQL
  // -----------------------------------------------------------------------

  // 處理 datatables 傳來的排序需求
  if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
    if($_GET['order'][0]['dir'] == 'asc'){
      $sql_order_dir = 'ASC';
    }else{
      $sql_order_dir = 'DESC';
    }
    if($_GET['order'][0]['column'] == 0){
      $sql_order = 'ORDER BY a.id '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 1){
      $sql_order = 'ORDER BY b.account '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 2){
      $sql_order = 'ORDER BY a.token '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 3){
      $sql_order = 'ORDER BY a.source '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 4){
      $sql_order = 'ORDER BY a.status '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 5){
      $sql_order = 'ORDER BY a.transaction_id '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 6){
      $sql_order = 'ORDER BY a.occurtime '.$sql_order_dir;
    }else{
      $sql_order = 'ORDER BY a.id ASC';
    }
  }else{
    $sql_order = 'ORDER BY a.id ASC';
  }

  // 取得查詢條件
    $query_str = query_str($query_sql_array);
    if(isset($query_str['logger'])){
      // NO member
      $output = array(
        "sEcho" => 0,
        "iTotalRecords" => 0,
        "iTotalDisplayRecords" => 0,
        "data" => ''
      );
    }else{
      $sql_tmp   = <<<SQL
        SELECT to_char((a.occurtime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS occurtime_string,
          a.id,
          a.memberid,
          a.token,
          a.source,
          a.destination,
          a.status,
          a.transaction_id,
          a.occurtime,
          a.agent_ip,
          a.fingerprint,
          a.note,
          a.casino_transfer_status,
          b.account
        FROM root_member_casino_transferrecords AS a
          LEFT JOIN root_member AS b
            ON a.memberid=b.id {$query_str} {$sql_order}
SQL;

      // 算資料總筆數
      $userlist_sql = $sql_tmp.';';
      $userlist_count = runSQL($userlist_sql, 0, 'r');

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
      $userlist = runSQLall($userlist_sql, $debug, 'r');

      // 存放列表的 html -- 表格 row -- tables DATA
      $show_listrow_html = '';
      // 判斷 root_member count 數量大於 1
      if($userlist[0] >= 1) {
        // 以會員為主要 key 依序列出每個會員的貢獻金額
        for($i = 1 ; $i <= $userlist[0]; $i++){
          $count = $page['no'] + $i;
          $member_account = '<a href="member_account.php?a='.$userlist[$i]->memberid.'" target="_BLANK" data-role=\" button\" title="连至会员详细页面">'.$userlist[$i]->account.'</a>';

          $arrow_left=<<<HTML
            <i class="fas fa-arrow-left" style="color: red;"></i>
HTML;
          $arrow_right=<<<HTML
            <i class="fas fa-arrow-right" style="color: green;"></i>
HTML;

            // 娛樂城
          if($userlist[$i]->destination == 'lobby'){
            // 如果destination = lobby
            // 就是memberid從source把錢拿到lobby，取source值
            $casino_name = $arrow_left .' '. $casinoLib->getCasinoDefaultName($userlist[$i]->source);
          } else{
            // 如果destination不是lobby
            // 就是memberid從lobby把錢拿到source，取destination值
            $casino_name = $arrow_right .' '. $casinoLib->getCasinoDefaultName($userlist[$i]->destination);
          }
          // 交易狀態
          // db success,success = 跟站台資料庫存取有關
          // info = 指純記錄的success是成功操作的
          // error,fail = 操作失敗
          if($userlist[$i]->status == 'success' or $userlist[$i]->status == 'db success' or $userlist[$i]->status == 'info'){
            $status_name =<<<HTML
              <span class="label label-success">{$tr['Success']}</span>
HTML;
          } elseif($userlist[$i]->status == 'error' or $userlist[$i]->status == 'fail') {
            $status_name = <<<HTML
              <span class="label label-danger">{$tr['fail']}</span>
HTML;
          }

          // 交易單號
          if($userlist[$i]->transaction_id == NULL) {
            // 如果交易單號是空，出現 -
            $show_transaction_id =<<<HTML
              <i class="fas fa-minus"></i>
HTML;
          } else{
            // 如果有交易單號，就會出現
            $show_transaction_id = $userlist[$i]->transaction_id;
          }

          $b['id']         = $count;
          $b['account']    = $member_account;
          $b['token']    = '$'.$userlist[$i]->token;
          $b['casino']      = $casino_name;
          $b['status']    =  $status_name;
          $b['agent_ip'] = $userlist[$i]->agent_ip;
          $b['fingerprint'] = $userlist[$i]->fingerprint;
          $b['note'] = $userlist[$i]->note;
          $b['transaction_id']    = $show_transaction_id;
          $b['occurtime'] = $userlist[$i]->occurtime_string;
          $b['transfer_status'] = is_null($userlist[$i]->casino_transfer_status) ? 0 : $userlist[$i]->casino_transfer_status;

          $deltail_map = '';

          // status
          if($b['status'] == 'error' OR $b['status'] == 'fail'){
            $btn_status = 'btn-warning';
          }else{
            $btn_status = 'btn-info';
          }

          $deltail_map .=<<<HTML
          <button type="button" class="btn {$btn_status} btn-xs pull-right modal-btn" data-toggle="modal" data-target="#{$userlist[$i]->id}">{$tr['detail']}</button>
          <div class="modal fade" id="{$userlist[$i]->id}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <h2 class="modal-title" id="myModalLabel">娱乐城转帐纪录明细</h2>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>

                <div class="modal-body">
                <table class="table table-striped">
                  <tbody>
                    <tr>
                      <th scope="row">{$tr['seq']}</th>
                      <td style="border:transparent;">{$userlist[$i]->id}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['Account']}</th>
                      <td style="border:transparent;">{$member_account}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['amount']}</th>
                      <td style="border:transparent;">{$b['token']}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['Casino']}</th>
                      <td style="border:transparent;">{$b['casino']}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['State']}</th>
                      <td style="border:transparent;">{$b['status']}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['ip address']}</th>
                      <td style="border:transparent;">{$b['agent_ip']}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['FingerPrint']}</th>
                      <td style="border:transparent;">{$b['fingerprint']}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['Note']}</th>
                      <td style="border:transparent;"><textarea class="form-control" disabled style="width:490px;background:white;border:none; ">{$b['note']}</textarea></td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['Transaction order number']}</th>
                      <td style="border:transparent;">{$b['transaction_id']}</td>
                    </tr>
                    <tr>
                      <th scope="row">{$tr['Transaction time']}</th>
                      <td style="border:transparent;">{$b['occurtime']}</td>
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
          // 顯示的表格資料內容
          $show_listrow_array[] = array(
            'id'      => $b['id'],
            'seq'     => $userlist[$i]->id,
            'account' => $b['account'],
            'token'   => $b['token'],
            'casino'  => $b['casino'],
            'status'  => $b['status'],
            'transaction_id' => $b['transaction_id'],
            'occurtime' => $b['occurtime'],
            'detail' => $deltail_map,
            'transfer' => $b['transfer_status']
          );
        }
        $output = array(
          "sEcho" => intval($secho),
          "iTotalRecords" => intval($page['per_size']),
          "iTotalDisplayRecords" => intval($page['all_records']),
          "data" => $show_listrow_array
        );
        // --------------------------------------------------------------------
        // 表格資料 row list , end for loop
        // --------------------------------------------------------------------
      }else{
        $output = array(
          "sEcho" => 0,
          "iTotalRecords" => 0,
          "iTotalDisplayRecords" => 0,
          "data" => ''
        );
      }
  }
  echo json_encode($output);
} elseif($action == 'query_csv' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($query_sql_array)) {
    // 取得查詢條件
    $query_str = query_str($query_sql_array);
    if(isset($query_str['logger'])){
      $json_arr = [
          'error_log' => '<script>alert("'.$query_str['logger'].'");</script>',
      ];
      echo json_encode($json_arr);
      die();//假如帳號亂打，錯誤訊息存在，則不做查詢csv動作。
    }

    $sql_tmp   = "SELECT to_char((occurtime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as log_time,
                                      id,
                                      memberid,
                                      source,
                                      destination,
                                      token,
                                      occurtime,
                                      agent_ip,
                                      fingerprint,
                                      note,
                                      status,
                                      transaction_id
                              FROM root_member_casino_transferrecords ".$query_str;
    // 算資料總筆數
    $userlist_sql   = $sql_tmp.';';
    // echo $userlist_sql;die();
    $userlist_count = runSQL($userlist_sql);

    // var_dump($userlist_sql);//查到最初組成查詢字串
    $dl_csv_code = jwtenc('casinotransferlog', $query_sql_array);
    $json_arr = [
      'member_casinotransferlog_count' => $userlist_count,
      'download_url' => 'member_casinotransferlog_action.php?get=dl_csv&csv='.$dl_csv_code,
    ];
    echo json_encode($json_arr);
} elseif($action == 'dl_csv' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($CSVquery_sql_array)) {
    $query_str = query_str($CSVquery_sql_array);
    if(isset($query_str['logger'])){
      $output = array('logger' => $query_str['logger']);
      echo json_encode($output);
    }else{

        $member_list_sql= "SELECT
                to_char((a.occurtime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as log_time,
                a.id,
                a.memberid,
                a.source,
                a.destination,
                a.token,
                a.occurtime,
                a.agent_ip,
                a.fingerprint,
                a.note,
                a.status,
                a.transaction_id,
                b.account
                FROM root_member_casino_transferrecords a
                    LEFT JOIN root_member b on a.memberid=b.id
                ".$query_str." ORDER BY a.occurtime DESC ";

        // 寫入 CSV 檔案前, 先產生一組 key 來處理
        $csv_key = 'logcasinotransfer';
        $csv_key_sha1 = sha1($csv_key);


        //引入一次寫幾筆物件，仁博大師
        $current_per_size = 10000;
        $casinotransferlog_paginator = new Paginator($member_list_sql, $current_per_size);
        $k=0;

        // 2019/12/31 csv轉excel
        $v = 1;
        $csv_key_title[$csv_key_sha1][$v++] = $tr['seq']; // 单号
        $csv_key_title[$csv_key_sha1][$v++] = $tr['Account']; // 会员
        $csv_key_title[$csv_key_sha1][$v++] = $tr['Trading Hours']; // 交易时间(EDT)
        $csv_key_title[$csv_key_sha1][$v++] = $tr['Conversion purpose']; // 转换来源
        $csv_key_title[$csv_key_sha1][$v++] = $tr['Conversion source']; // 转换目的
        $csv_key_title[$csv_key_sha1][$v++] = $tr['amount']; // 金额
        $csv_key_title[$csv_key_sha1][$v++] = $tr['Payout results']; // 派彩结果
        $csv_key_title[$csv_key_sha1][$v++] = $tr['State']; // 状态
        $csv_key_title[$csv_key_sha1][$v++] = $tr['ip address']; // 会员操作IP
        $csv_key_title[$csv_key_sha1][$v++] = $tr['FingerPrint']; // fingerprint
        $csv_key_title[$csv_key_sha1][$v++] = $tr['Note']; // 转换记录说明
        $csv_key_title[$csv_key_sha1][$v++] = $tr['Transaction order number']; // 交易单号

        // 在分頁迴圈裡面，將資料寫出
        for(
            $casinotransfer_log_result = $casinotransferlog_paginator->getCurrentPage()->data;
            count($casinotransfer_log_result) > 0;
            $casinotransfer_log_result = $casinotransferlog_paginator->getNextPage()->data)
        {

            // start of one page loop
            foreach($casinotransfer_log_result as $transferdata) {
                // var_dump($transferdata);die();
                $payoff_arr = preg_split("/=/", $transferdata->note);
                if(count($payoff_arr) > 2){
                  $payoff =$payoff_arr[count($payoff_arr)-1];
                  if(!is_numeric($payoff)) {$payoff = '0';}//強制轉型，過濾字串，剩下數字留下來即可
                }else{
                  $payoff = '0';
                }
                $v = 1;
                $k++;

                $csv_array[$csv_key_sha1][$k][$v++] = $transferdata->id;
                $csv_array[$csv_key_sha1][$k][$v++] = $transferdata->account;
                $csv_array[$csv_key_sha1][$k][$v++] = $transferdata->log_time;
                $csv_array[$csv_key_sha1][$k][$v++] = $transferdata->source;
                $csv_array[$csv_key_sha1][$k][$v++] = $transferdata->destination;
                $csv_array[$csv_key_sha1][$k][$v++] = $transferdata->token;
                $csv_array[$csv_key_sha1][$k][$v++] = $payoff;
                $csv_array[$csv_key_sha1][$k][$v++] = $transferdata->status;
                $csv_array[$csv_key_sha1][$k][$v++] = $transferdata->agent_ip;
                $csv_array[$csv_key_sha1][$k][$v++] = $transferdata->fingerprint;
                $csv_array[$csv_key_sha1][$k][$v++] = $transferdata->note;
                $csv_array[$csv_key_sha1][$k][$v++] = $transferdata->transaction_id;
            }

            // 檔名
            $filename  = 'casinotransfer_'.date("Y-m-d_His").'.csv';
            $file_path = dirname(__FILE__) .'/tmp_dl/'.$filename;
            $csv_stream  = new CSVWriter($file_path);

            $csv_stream->begin();

            // 欄位標題
            foreach ($csv_key_title as $wline) {
              // fputcsv($filehandle, $wline);
              $csv_stream->writeRow($wline);
            }

            foreach ($csv_array as $wline) {
              foreach ($wline as $line) {
                // fputcsv($filehandle, $line);
                $csv_stream->writeRow($line);
              }
            }

            $excel_stream = new csvtoexcel($filename,$file_path);
            $excel_stream->begin();

        }
    }
} elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);

} elseif ($action == 'checkTransactionId') {
    // 確認娛樂城交易狀態
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT) : 0;
    // 取得 Transaction Id
    $prefixTransactionId = isset($_POST['tid']) ? filter_var($_POST['tid'], FILTER_SANITIZE_STRING) : '_empty';
    // 擷取無前綴 Transaction Id
    $transactionId = explode('_', $prefixTransactionId)[1];
    $apiData = [
        'transaction_id' => $transactionId
    ];

    $apiResult = $casinoLib->getDataByAPI('CheckTransaction', $debug, $apiData);
    // API 錯誤碼
    $result['api'] = $apiResult['errorcode'];
    if ($apiResult['errorcode'] == 0) {
        // 查的到表示交易完成
        // 判斷存錢或取錢
        $isWithdraw = $apiResult['Result']->balance_after == 0;
        // 放入單號
        $apiResult['Result']->id = $id;
        if ($isWithdraw) {
            // 取得會員 ID
            $memberId = $casinoLib->getMemberIdByCasinoAccount(strtoupper($apiResult['Result']->casino), $apiResult['Result']->source_account, $debug);
            // 從娛樂城取款
            $result['code'] = $casinoLib->withdrawDB($apiResult, $memberId, $debug);
        } else {
            // 取得會員 ID
            $memberId = $casinoLib->getMemberIdByCasinoAccount(strtoupper($apiResult['Result']->casino), $apiResult['Result']->destination_account, $debug);
            // 存款至娛樂城
            $result['code'] = $casinoLib->depositDB($apiResult, $memberId, $debug);
        }
    } elseif ($apiResult['errorcode'] == 303) {
        // 查不到交易
        $result['code'] = $casinoLib->updateCasinoTransferRecord($id, $prefixTransactionId, 2, 'fail', '', $debug);
    } else {
        // 其他錯誤
        $result['code'] = $casinoLib->updateCasinoTransferRecord($id, $prefixTransactionId, 2,'fail', '', $debug);
    }

    echo json_encode($result);
} elseif ($action == 'transferLog') {
    // 取得單筆紀錄
    // 取得單號
    $seq = isset($_GET['seq']) ? filter_var($_GET['seq'], FILTER_SANITIZE_NUMBER_INT) : 0;
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : 0;

    // SQL
    $sql = <<<SQL
        SELECT to_char((a.occurtime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS occurtime_string,
            a.id,
            a.memberid,
            a.token,
            a.source,
            a.destination,
            a.status,
            a.transaction_id,
            a.occurtime,
            a.agent_ip,
            a.fingerprint,
            a.note,
            a.casino_transfer_status,
            b.account
        FROM root_member_casino_transferrecords AS a
            LEFT JOIN root_member AS b
                ON a.memberid=b.id
                    WHERE a.id = {$seq}; 
SQL;

    $result = runSQLall($sql, $debug);
    if ($result[0] > 0) {
        // 會員帳號
        $member_account = '<a href="member_account.php?a='. $result[1]->memberid .'" target="_BLANK" data-role=\" button\" title="连至会员详细页面">' .$result[1]->account. '</a>';

        // 箭頭及娛樂城
        $arrow_left=<<<HTML
            <i class="fas fa-arrow-left" style="color: red;"></i>
HTML;
        $arrow_right=<<<HTML
            <i class="fas fa-arrow-right" style="color: green;"></i>
HTML;

        // 娛樂城
        if($result[1]->destination == 'lobby'){
            // 如果destination = lobby
            // 就是memberid從source把錢拿到lobby，取source值
            $casino_name = $arrow_left .' '. $casinoLib->getCasinoDefaultName($result[1]->source, $debug);
        } else{
            // 如果destination不是lobby
            // 就是memberid從lobby把錢拿到source，取destination值
            $casino_name = $arrow_right .' '. $casinoLib->getCasinoDefaultName($result[1]->destination, $debug);
        }

        // 交易狀態
        // db success = 跟站台資料庫存取有關
        // info = 指純記錄的
        // success = 成功操作
        // error,fail = 操作失敗
        if($result[1]->status == 'success' or $result[1]->status == 'db success' or $result[1]->status == 'info'){
            $status_name =<<<HTML
              <span class="label label-success">{$tr['Success']}</span>
HTML;
        } elseif($result[1]->status == 'error' or $result[1]->status == 'fail') {
            $status_name = <<<HTML
              <span class="label label-danger">{$tr['fail']}</span>
HTML;
        }

        // 交易單號
        if($result[1]->transaction_id == NULL) {
            // 如果交易單號是空，出現 -
            $show_transaction_id =<<<HTML
              <i class="fas fa-minus"></i>
HTML;
        } else{
            // 如果有交易單號，就會出現
            $show_transaction_id = $result[1]->transaction_id;
        }

        // 取得欄位資料
        $b['id']         = $id;
        $b['account']    = $member_account;
        $b['token']    = '$'. $result[1]->token;
        $b['casino']      = $casino_name;
        $b['status']    =  $status_name;
        $b['agent_ip'] = $result[1]->agent_ip;
        $b['fingerprint'] = $result[1]->fingerprint;
        $b['note'] = $result[1]->note;
        $b['transaction_id']    = $show_transaction_id;
        $b['occurtime'] = $result[1]->occurtime_string;
        $b['transfer_status'] = is_null($result[1]->casino_transfer_status) ? 0 : $result[1]->casino_transfer_status;

        $detailMap = '';

        // status
        if($b['status'] == 'error' OR $b['status'] == 'fail'){
            $btn_status = 'btn-warning';
        }else{
            $btn_status = 'btn-info';
        }

        $detailMap .= <<<HTML
            <button type="button" class="btn {$btn_status} btn-xs pull-right modal-btn" data-toggle="modal" data-target="#{$result[1]->id}">{$tr['detail']}</button>
            <div class="modal fade" id="{$result[1]->id}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title" id="myModalLabel">娱乐城转帐纪录明细</h2>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <table class="table table-striped">
                                <tbody>
                                    <tr>
                                        <th scope="row">{$tr['seq']}</th>
                                        <td style="border:transparent;">{$result[1]->id}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{$tr['Account']}</th>
                                        <td style="border:transparent;">{$member_account}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{$tr['amount']}</th>
                                        <td style="border:transparent;">{$b['token']}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{$tr['Casino']}</th>
                                        <td style="border:transparent;">{$b['casino']}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{$tr['State']}</th>
                                        <td style="border:transparent;">{$b['status']}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{$tr['ip address']}</th>
                                        <td style="border:transparent;">{$b['agent_ip']}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{$tr['FingerPrint']}</th>
                                        <td style="border:transparent;">{$b['fingerprint']}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{$tr['Note']}</th>
                                        <td style="border:transparent;"><textarea class="form-control" disabled style="width:490px;background:white;border:none; ">{$b['note']}</textarea></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{$tr['Transaction order number']}</th>
                                        <td style="border:transparent;">{$b['transaction_id']}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{$tr['Transaction time']}</th>
                                        <td style="border:transparent;">{$b['occurtime']}</td>
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

        $transferLog = array(
            'id'      => $b['id'],
            'seq'     => $result[1]->id,
            'account' => $b['account'],
            'token'   => $b['token'],
            'casino'  => $b['casino'],
            'status'  => $b['status'],
            'transaction_id' => $b['transaction_id'],
            'occurtime' => $b['occurtime'],
            'detail' => $detailMap,
            'transfer' => $b['transfer_status']
        );

        echo json_encode($transferLog);
    }
} else {
  // NO member
  $output = array(
    "sEcho" => 0,
    "iTotalRecords" => 0,
    "iTotalDisplayRecords" => 0,
    "data" => ''
  );
echo json_encode($output);
}
// -----------------------------------------------------------------------
// MAIN END
// -----------------------------------------------------------------------
?>
