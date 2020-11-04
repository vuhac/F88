<?php
// ----------------------------------------------------------------------------
// Features:  審查用的同意或是轉帳動作SQL操作 - 接收來自 withdrawapgtoken_company_audit_review.php 的動作處理
// File Name:	withdrawapgtoken_company_audit_review_action.php
// Author:		Barkley
// Log:
// ----------------------------------------------------------------------------
/*
主要操作的DB表格：
root_withdraw_review 代幣申請審查表
root_member_gtokenpassbook 代幣存款紀錄
前台
wallets.php 錢包顯示連結--取款、存簿都由這裡進入。
transcactiongtoken.php 前台代幣的存簿
withdrawapplication.php 代币(GTOKEN)线上取款前台程式, 操作界面
withdrawapplication_action.php 代币(GTOKEN)线上取款前台動作, 會先預扣提款款項
後台
member_transactiongtoken.php 後台的會員GTOKEN轉帳紀錄,預扣款項及回復款項會寫入此紀錄表格
withdrawalgtoken_company_audit.php  後台GTOKEN提款審查列表頁面
withdrawalgtoken_company_audit_review.php  後台GTOKEN提款單筆紀錄審查
withdrawapgtoken_company_audit_review_action.php 後台GTOKEN提款審查用的同意或是轉帳動作SQL操作
*/
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
// require_once dirname(__FILE__) ."/lib.php";

// gtoken lib 現金轉帳函式庫
require_once dirname(__FILE__) ."/gtoken_lib.php";
// gcash lib 現金轉帳函式庫
require_once dirname(__FILE__) ."/gcash_lib.php";

require_once dirname(__FILE__) ."/token_auditorial_lib.php";

require_once dirname(__FILE__) ."/deposit_withdrawal_company_audit_lib.php";

if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    // $tr['Illegal test'] = '(x)不合法的測試。';
    die($tr['Illegal test']);
}

// var_dump($_SESSION);
// var_dump($_REQUEST);
// die();
//var_dump($_GET);
// var_dump($action);

// --------------------------------
// 20200414
function validateDate($date, $format = 'Y-m-d H:i:s')
{
  $d = DateTime::createFromFormat($format, $date);
  return $d && $d->format($format) == $date;
}
// 交易代號
if(isset($_GET['transaction_id']) AND $_GET['transaction_id'] != null){
  $query_sql_array['transaction_id'] = filter_var($_GET['transaction_id'],FILTER_SANITIZE_STRING);
}
// 帳號
if(isset($_GET['account']) AND $_GET['account'] != null){
  $query_sql_array['account'] = filter_var($_GET['account'],FILTER_SANITIZE_STRING);
}
// 代理商
if(isset($_GET['agent']) AND $_GET['agent'] != null){
  $query_sql_array['agent'] = filter_var($_GET['agent'],FILTER_SANITIZE_STRING);
}
// 申請開始日期
if(isset($_GET['sdate']) AND $_GET['sdate'] != NULL ) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['sdate'], 'Y-m-d H:i')) {
    $query_sql_array['query_date_start_datepicker'] = $_GET['sdate'];//.' 00:00:00';
    $query_sql_array['query_date_start_datepicker_gmt'] = gmdate('Y-m-d H:i:s.u',strtotime($query_sql_array['query_date_start_datepicker'].'-04')+8*3600).'+08:00';
  }
}
// 申請結束日期
if(isset($_GET['edate']) AND $_GET['edate'] != NULL ) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['edate'], 'Y-m-d H:i')) {
    $query_sql_array['query_date_end_datepicker'] = $_GET['edate'];//.' 23:59:59';
    $query_sql_array['query_date_end_datepicker_gmt'] = gmdate('Y-m-d H:i:s.u',strtotime($query_sql_array['query_date_end_datepicker'].'-04')+8*3600).'+08:00';
  }
}
// 金額
if(isset($_GET['amount_lower']) AND $_GET['amount_lower'] != null){
  $query_sql_array['amount_lower'] = filter_var($_GET['amount_lower'],FILTER_SANITIZE_STRING);
}
if(isset($_GET['amount_upper']) AND $_GET['amount_upper'] != null){
  $query_sql_array['amount_upper'] = filter_var($_GET['amount_upper'],FILTER_SANITIZE_STRING);
}
// 審核狀態
if(isset($_GET['status_qy']) AND $_GET['status_qy'] != NULL ) {
  $query_sql_array['status_query'] = filter_var_array($_GET['status_qy'], FILTER_SANITIZE_STRING);
}
// IP
if(isset($_GET['ip']) AND $_GET['ip'] != NULL ) {
  if(filter_var($_GET['ip'], FILTER_VALIDATE_IP)){
    $query_sql_array['ip_query'] = $_GET['ip'];
  }
}
// 初始化
function all_data($default_min_date){
  $list_sql=<<<SQL
	SELECT * FROM
		(SELECT *, to_char((applicationtime AT TIME ZONE 'posix/Etc/GMT-8'), 'YYYY-MM-DD HH24:MI:SS' ) as applicationtime_tz
		FROM root_withdraw_review ORDER BY id DESC LIMIT 500) as withdraw_review
		LEFT OUTER JOIN (SELECT id as member_id,account as member_account,parent_id as p_id FROM root_member) as member
		ON withdraw_review.account = member.member_account

    WHERE applicationtime > '{$default_min_date}'
SQL;
  return $list_sql;
}
// 搜尋
function sql_query(){
  // posix/Etc/GMT-8
  $sql=<<<SQL
  SELECT * FROM
  (SELECT *, to_char((applicationtime AT TIME ZONE 'AST'), 'YYYY-MM-DD HH24:MI:SS' ) as applicationtime_tz
  FROM root_withdraw_review ORDER BY id DESC LIMIT 500) as withdraw_review
  LEFT OUTER JOIN (SELECT id as member_id,account as member_account,parent_id as p_id FROM root_member) as member
  ON withdraw_review.account = member.member_account
SQL;
  return $sql;
}
// 組sql
function query_str($query_sql_array){
  $query_top = 0;
  $show_sql = '';

  // 今天
  $current_date_s = gmdate('Y-m-d',time() + -4*3600).' 00:00';
  $current_date_e = gmdate('Y-m-d',time() + -4*3600).' 23:59';
  $default_min_date = gmdate('Y-m-d',strtotime('- 7 days'));

  // 代理商
  if(isset($query_sql_array['agent']) AND $query_sql_array['agent'] != null){
    // if($query_top ==1){
      // $show_sql = $show_sql.' AND ';

      $agent_id = <<<SQL
      SELECT id FROM root_member WHERE (therole ='A' OR therole ='R') AND account = '{$query_sql_array['agent']}'
      -- SELECT id FROM root_member WHERE account = '{$query_sql_array['agent']}' AND therole = 'A'
SQL;
      $agent_id_result = runSQLALL($agent_id);

      if ($agent_id_result[0] == 1) {
        // $show_sql = $show_sql.'b.parent_id = \''.$agent_id_result[1]->id.'\'';

        $show_sql = $show_sql.' p_id = \''.$agent_id_result[1]->id.'\'';
        $query_top = 1;

        // if(isset($query_sql_array['account']) AND $query_sql_array['account'] != null){
        //   // 沒有填搜尋帳號
        //   $show_sql = $show_sql.'p_id = \''.$agent_id_result[1]->id.'\'';
        //   $query_top = 1;

        // }elseif(isset($query_sql_array['transaction_id']) AND $query_sql_array['transaction_id'] != null){
        //   $show_sql = $show_sql.' AND p_id = \''.$agent_id_result[1]->id.'\'';
        //   $query_top = 1;

        // }else{
        //   $show_sql = $show_sql.'p_id = \''.$agent_id_result[1]->id.'\'';
        //   $query_top = 1;
        // }
      }else{
        $logger = '无此帐号，错误代码：1907221501754。';
      }
    // }
    // $query_top = 1;
  }

   // 帳號
   if(isset($query_sql_array['account']) AND $query_sql_array['account'] != null){
    if($query_top ==1){
      $show_sql = $show_sql.' AND ';
    }
    $show_sql = $show_sql.'withdraw_review.account = \''.$query_sql_array['account'].'\'';
    $query_top = 1;
  }

  // 交易單號
  if(isset($query_sql_array['transaction_id']) AND $query_sql_array['transaction_id'] != null){
    if($query_top == 1){
      $show_sql = $show_sql.' AND ';
    }
    $show_sql =$show_sql.'transaction_id = \''.$query_sql_array['transaction_id'].'\'';
    $query_top = 1;
  }

  // 開始時間
  if(isset($query_sql_array['query_date_start_datepicker']) AND $query_sql_array['query_date_start_datepicker'] != null){
    if($query_top == 1){
      $show_sql = $show_sql.' AND ';
    }
    $show_sql = $show_sql.'applicationtime_tz >= \''.$query_sql_array['query_date_start_datepicker'].'\'';
    $query_top = 1;

  }else{
    // 沒填，預設7天前
    if($query_top == 1){
      $show_sql = $show_sql.' AND ';
    }
    $show_sql = $show_sql.'applicationtime_tz >= \''.$default_min_date.'\'';
    $query_top = 1;
  }

  // 結束時間
  if(isset($query_sql_array['query_date_end_datepicker']) AND $query_sql_array['query_date_end_datepicker'] != null){
    if($query_top == 1){
      $show_sql = $show_sql.' AND ';
    }
    $show_sql = $show_sql.'applicationtime_tz <= \''.$query_sql_array['query_date_end_datepicker'].'\'';
    $query_top = 1;

  }else{
    // 沒填，預設今日
    if($query_top == 1){
      $show_sql = $show_sql.' AND ';
    }
    $show_sql = $show_sql.'applicationtime_tz <= \''.$current_date_e.'\'';
    $query_top = 1;
  }
  // 金額
  if(isset($query_sql_array['amount_lower']) AND $query_sql_array['amount_lower'] != null){
    if($query_top == 1){
      $show_sql = $show_sql. ' AND ';
    }
    $show_sql = $show_sql.'amount >= \''.$query_sql_array['amount_lower'].'\'';
  }
  if(isset($query_sql_array['amount_upper']) AND $query_sql_array['amount_upper'] != null){
    if($query_top == 1){
      $show_sql = $show_sql. ' AND ';
    }
    $show_sql = $show_sql.'amount <= \''.$query_sql_array['amount_upper'].'\'';
  }
  // ip
  if(isset($query_sql_array['ip_query']) AND $query_sql_array['ip_query'] != NULL ) {
    if($query_top == 1){
      $show_sql = $show_sql.' AND ';
    }
    $show_sql = $show_sql.'applicationip = \''.$query_sql_array['ip_query'].'\'';
    $query_top = 1;
  }

  // 審核狀態
  if(isset($query_sql_array['status_query']) AND $query_sql_array['status_query'] != NULL) {
    if($query_top == 1){
      $show_sql = $show_sql.' AND ';
    }
    $show_sql = $show_sql.'status IN ( '.implode("," ,$query_sql_array['status_query']).')';
    $query_top = 1;
  }

  if($query_top == 1 AND !isset($logger)){
    $return_sql = ' WHERE '.$show_sql;
  }elseif(isset($logger)){
    $return_sql['logger'] = $logger;
  }else{
    $return_sql = '';
  }
  return $return_sql;
}
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

// ---------------------------------
// ----------------------------------
// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(300);
// ----------------------------------
// $banknotegcash = filter_var($_POST['banknotegcash'], FILTER_SANITIZE_NUMBER_INT);
// 交易類別 , 定義遊戲幣轉銀行或遊戲幣轉現鈔--yaoyuan
$withdraw_method_name        = ['0' => 'tokengcash',        '1' => 'tokentogcashpoint'];
$withdraw_method_name_reject = ['0' => 'reject_tokengcash', '1' => 'reject_tokentogcashpoint'];

if(isset($_SESSION['agent'])) $processing_note = '客服'.$_SESSION['agent']->account.'审核操作中...';

// ----------------------------------
// 動作檢查
// ----------------------------------

// 20200414 edit by vism23
if($action == 'get_init' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){

  $review_agent_status[NULL] 	= $tr['delete'];
  $review_agent_status[0] 	= $tr['Cancel'];
  $review_agent_status[1] 	= $tr['agree'];
  // 单号审核中
  // $tr['select function'] = '選擇功能';
  $review_agent_status[2] 	= $tr['select function'];

  $review_agent_status[3] 	= 'lock';

  // 處理 datatables 傳來的排序需求
  if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
    $sql_order_dir = ($_GET['order'][0]['dir'] == 'asc')? 'ASC':'DESC';
    $sql_order = 'ORDER BY '.$_GET['columns'][$_GET['order'][0]['column']]['data'].' '.$sql_order_dir;
  }else{ $sql_order = 'ORDER BY id ASC';}

  $default_min_date = gmdate('Y-m-d',strtotime('- 7 days')); // 7天

  // 取所有資料 *預設審核狀態為未審核
  $list_sql = all_data($default_min_date)." AND status IN ( 2) ".$sql_order;

  // 算資料總筆數
  $userlist_sql   = $list_sql.';';
  $userlist_count = runSQL($userlist_sql);
  // echo $userlist_count;die();
  if($userlist_count != 0){

    // -----------------------------------------------------------------------
    // 分頁處理機制
    // -----------------------------------------------------------------------
    // 所有紀錄數量
    $page['all_records']     = $userlist_count;
    // 每頁顯示多少
    $page['per_size']        = $current_per_size;
    // 目前所在頁數
    $page['no']              = $current_page_no;

    $userlist_sql   = $list_sql.' OFFSET '.$page['no'].' LIMIT '.$page['per_size'].';';
    $userlist = runSQLall($userlist_sql);
    // var_dump($userlist_sql);die();

    // 從all_data()內找出parent_id，帶入parent account
    $to_get_data = only_get_parent($userlist);

    foreach($to_get_data as $k1 => $v1){
      // var_dump($v1);die();

      // 提款 - 申請單號
      $withdrawalgtoken_id = '<a class="p-3 text-muted" href="./withdrawalgtoken_company_audit_review.php?id='.$v1['id'].'" role="button" target="_SELF" title="'.$tr['go to number'].' '.$v1['id'].' '.$tr['detail'].'"><i class="fas fa-angle-right"></i></a>';

      // 會員帳號查驗連結,$tr['Check membership details'] = '檢查會員的詳細資料';
      $member_check_html = '<a href="member_account.php?a='.$v1['member_id'].'" target="_BLANK" title="'.$tr['Check membership details'].'">'.$v1['account'].'</a>';

      // 審查的選項 -- for confirm
      $confirm_status_html = '';

      // $tr['select function'] = '選擇功能',$tr['seq applying'] = '單號審核中';
      if($review_agent_status[$v1['status']] == $tr['select function']){
        $confirm_status_html = '
        <button id="agreen_ok'.$v1['id'].'" class="btn btn-primary  agreen_ok mr-1 mb-1 btn-sm" role="button" value="'.$v1['id'].'">'.$tr['agree'].'</button>
        <button id="agreen_cancel'.$v1['id'].'" class="btn btn-danger  agreen_cancel btn-sm" role="button" value="'.$v1['id'].'">'.$tr['disagree'].'</button>
        ';
        // $tr['agree']= '同意';
      }else if ($review_agent_status[$v1['status']] == $tr['agree']){
        // $tr['Approved'] = '已审核通过';
        $confirm_status_html = '
        <a href="./withdrawalgtoken_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-success btn-xs">'.$tr['Approved'].'</a>
        ';
      }else if ($review_agent_status[$v1['status']] == 'lock'){
        $confirm_status_html = '
        <a href="./withdrawalgtoken_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-lock"><span></a>
        ';
      }else{
        // $tr['application reject'] = '审核退回';
        $confirm_status_html = '
        <a href="./withdrawalgtoken_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-danger btn-xs">'.$tr['application reject'].'</a>
        ';
      }

      // 檢查使用者的代幣存簿,$tr['Check the users token deposit book'] = '檢查使用者的代幣存簿';
      // $check_amount_html = '<a href="member_transactiongtoken.php?a='.$list[$i]->member_id.'" title="'.$tr['Check the users token deposit book'].'" target="_SELF">'.$list[$i]->amount.'</a>';
      // 提款金額 加上  ＄ 格式, 小數點 2 位 number_format
      $amount_money = '$'.number_format(round($v1['amount'], 2),2);
      //手續費  加上  ＄ 格式, 小數點 2 位 number_format
      $fee_amount_money = '$'.number_format(round($v1['fee_amount'], 2),2);
      // 行政稽核
      $administrative_amoun_money = '$'.number_format(round($v1['administrative_amount'], 2),2);

      // ---------------------------------------------------
      // 出款方式
      $withdraw_method_html = ($v1['togcash'] == 0) ? '<span class="label label-info">'.$tr['Bank withdrawal'].'</span>' : '<span class="label label-info">'.$tr['Convert to cash'].'</span>';
      // IP
      $to_get_ip_log= <<<HTML
      <form target="_blank" data-id ="link_member_log" action="member_log.php" method="post">
          <button class="btn btn-default btn-xs" data-id="check_{$v1['account']}" title="{$tr['check member login status']}">{$v1['applicationip']}</button>
          <input type="hidden" data-id="ip_source" name="ip_query" value="{$v1['applicationip']}">
      </form>
HTML;
      // 出款方式HTML
      $withdraw_type=<<<HTML
        <td id="{$v1['id']}">
          {$withdraw_method_html}
          <p class="my-1">{$v1['companyname']}</p>
          <p class="mb-1">{$v1['accountnumber']}</p>
        </td>
HTML;
      // gmdate('Y-m-d H:i:s', strtotime($v1['applicationtime_tz']) + -4 * 3600)
      $show_list_array[] = array(
        'agent'=> $v1['parent'],
        'account'=> $member_check_html,
        'transaction_id'=> $v1['transaction_id'],
        'amount'=> $amount_money,
        'fee_amount'=> $fee_amount_money,
        'administrative_amount'=> $administrative_amoun_money,
        'applicationtime_tz'=> gmdate('Y-m-d H:i:s', strtotime($v1['applicationtime_tz']) + -4 * 3600),
        'type'=> $withdraw_type,
        'applicationip'=> $to_get_ip_log,
        'status'=> $confirm_status_html,
        'detail'=> $withdrawalgtoken_id
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
      "sEcho" 									=> 0,
      "iTotalRecords" 					=> 0,
      "iTotalDisplayRecords" 		=> 0,
      "data" 										=> ''
    );
  }

  echo json_encode($output);
}elseif($action == 'withdrawalgtoken_submit' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // 遊戲幣取款審核同意-----------------------------------------------------------------------------
// -----------------------------------------------------------------------------
// 管理員同意此筆提款申請，寫入訊息紀錄到GTOKEN存簿, 並且將 root_withdraw_review 表格的紀錄設定為「同意」
// -----------------------------------------------------------------------------
  global $config;

  // 取得 root_deposit_review 的 ID
  $withdrawapplgtoken_id = filter_var($_POST['withdrawapplgtoken_id'], FILTER_SANITIZE_NUMBER_INT);

  // 查詢 root_member_wallets 資料表
  $withdrawapplgtoken_sql = "SELECT * FROM root_withdraw_review WHERE id = '".$withdrawapplgtoken_id."' AND status = '2'; ";
  // var_dump($withdrawapplgtoken_sql);

  $withdrawapplgtoken_result = runSQLALL($withdrawapplgtoken_sql);
  // var_dump($withdrawapplgtoken_result);

  // 判斷單號審核資料
  if($withdrawapplgtoken_result[0] == 1) {
    // 锁住審核單號
    $withdrawapplgcash_lock = runSQL("UPDATE root_withdraw_review SET status = '3' WHERE id = '".$withdrawapplgtoken_id."' AND status = '2'; ");

    // 交易的類別分類：應用在所有的錢包相關程式內 , 定義再 system_config.php 內.
    // global $transaction_category;
    // 轉帳摘要 -- 代幣轉現鈔或銀行帳戶(tokengcash)
    $transaction_category_index = $withdraw_method_name[$withdrawapplgtoken_result[1]->togcash];
    // 交易類別 , 定義再 system_config.php 內, 不可以隨意變更.
    $summary = $transaction_category[$transaction_category_index].'同意';
    // 操作者 ID
    $member_id = $_SESSION['agent']->id;
    // 轉帳來源帳號 -- 代幣出納帳號 $gtoken_cashier_account
    // $source_transferaccount = $gtoken_cashier_account;
    $source_transferaccount = $withdrawapplgtoken_result[1]->account;
    // 轉帳目標帳號
    // $destination_transferaccount = $withdrawapplgtoken_result[1]->account;
    $destination_transferaccount = $gtoken_cashier_account;
    // 來源帳號提款密碼 or 管理員登入的密碼
    // $pwd_verify_sha1 = $_SESSION['agent']->passwd;
    // $pwd_verify_sha1 = 'tran5566'; // 移到config

    // 轉帳金額
    $transaction_money = '0';
    // 實際存提
    $realcash = 1;
    // 稽核方式
    $auditmode_select = 'freeaudit';
    // 稽核金額
    $auditmode_amount = '0';
    // 系統轉帳文字資訊(補充)
    $system_note = NULL;
    // $debug = 1 --> 進入除錯模式 , debug = 0 --> 關閉除錯
    $debug = 0;
    // 交易單號
    $transaction_id=$withdrawapplgtoken_result[1]->transaction_id;
    // 操作人員
    $operator = $_SESSION['agent']->account;

    // 原本
    // $error = member_gtoken_notice($member_id, $source_transferaccount, $destination_transferaccount, $transaction_money, $pwd_verify_sha1, $summary, $transaction_category_index, $realcash, $auditmode_select, $auditmode_amount, $system_note, $debug,$transaction_id,$operator);

    // 20191017
    $error = member_gtoken_notice($member_id, $source_transferaccount, $destination_transferaccount, $transaction_money, $config['withdrawal_pwd'], $summary, $transaction_category_index, $realcash, $auditmode_select, $auditmode_amount, $system_note, $debug,$transaction_id,$operator);

    $error1['code'] = 1;
    if ($withdrawapplgtoken_result[1]->togcash == 1) {
      $source_transferaccount = $gcash_cashier_account;
      $destination_transferaccount = $withdrawapplgtoken_result[1]->account;
      $transaction_money = $withdrawapplgtoken_result[1]->amount;

      // 原本
      // $error1 = member_gcash_transfer($transaction_category_index, $summary, $member_id, $source_transferaccount, $destination_transferaccount, $pwd_verify_sha1, $transaction_money, $realcash, $system_note, $debug,$transaction_id,$operator);

      // 20191017
      $error1 = member_gcash_transfer($transaction_category_index, $summary, $member_id, $source_transferaccount, $destination_transferaccount, $config['withdrawal_pwd'], $transaction_money, $realcash, $system_note, $debug,$transaction_id,$operator);

    }

    if($error['code'] == 1 AND $error1['code'] == 1) {
      $error['messages'] = $tr['agree gtoken withdrawal request ID'].$tr['Success.'];
      // 更新 root_withdraw_review 變數
      $update_sql = "UPDATE root_withdraw_review SET status = 1, notes='".$error['messages']."', processingaccount = '".$_SESSION['agent']->account."', processingtime = now() WHERE id = ".$withdrawapplgtoken_result[1]->id." AND status = '3';";
      $update_result = runSQLall($update_sql);

      if($update_result[0] == 1) {
        update_auditreport_transactionid($withdrawapplgtoken_result[1]->account, $transaction_id);

        $error['code'] = '1';
        // $error['messages'] = '更新代幣取款單號'.$withdrawapplgtoken_result[1]->id.'成功。'$tr['Success.'] = '成功。';$tr['agree gtoken withdrawal request ID'] = '同意代幣取款申請單號';
        $error['messages'] = $tr['agree gtoken withdrawal request ID'].$withdrawapplgtoken_result[1]->transaction_id.$tr['Success.'];
        // echo '<p align="center"><button type="button" class="btn btn-success" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
      } else {
        $error['code'] = 'wgs505';
        // $error['messages'] = '更新代幣取款單號'.$withdrawapplgtoken_result[1]->id.'錯誤, 請聯絡開發人員處理。';$tr['error, please contact the developer for processing.'] = '錯誤，請聯絡開發人員處理。';
        $error['messages'] = $tr['agree gtoken withdrawal request ID'].$withdrawapplgtoken_result[1]->transaction_id.$tr['error, please contact the developer for processing.'];
        // echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
      }
    } else {
      $error['code'] = 'wgs506-'.$error1['code'];
      $error['messages'] =  ($error1['code'] == 1) ? $error['messages'] : $error1['messages'];
      // echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$messages.'</button></p>';
      runSQL("UPDATE root_withdraw_review SET status = '2',notes='".$error['messages']."' WHERE id = '".$withdrawapplgtoken_id."' AND status = '3'; ");
    }
  } else {
    // $logger = '(x)目前此取款單號'.$withdrawapplgtoken_id.'已處理過，請勿重新操作處理。';
    // memberlog2db($_SESSION['agent']->account,'withdrawal','notice', "$logger");

    $error['code'] = 'wgs507';
    $error['messages'] = '(x)'.$tr['Currently this withdrawal order number'].$withdrawapplgtoken_result[1]->id.$tr['has been dealt with, do not re-operation.'];
    memberlog2db($_SESSION['agent']->account,'withdrawal','notice', $error['messages']);
    // echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$logger.'</button></p>';

  }

  // 結果
  // if($debug == 1) {
  //   var_dump($error);
  // }

  echo <<<HTML
        <script>
            alert("{$error['messages']}");
            location.reload();
        </script>
    HTML;

// -----------------------------------------------------------------------------
} elseif($action == 'withdrawalgtoken_cancel' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
// -----------------------------------------------------------------------------
// 取消使用者的申請, 把提款 + 手續費加總後，一次退款給使用者。
// -----------------------------------------------------------------------------
  global $config;

  // 取得 root_deposit_review 的 ID
  $withdrawapplgtoken_id = filter_var($_POST['withdrawapplgtoken_id'], FILTER_SANITIZE_NUMBER_INT);

  // 查詢 root_member_wallets 資料表
  $withdrawapplgtoken_sql = " SELECT * FROM root_withdraw_review WHERE id = '".$withdrawapplgtoken_id."' AND status = '2'; ";
  // var_dump($withdrawapplgtoken_sql);

  $withdrawapplgtoken_result = runSQLALL($withdrawapplgtoken_sql);
  // var_dump($withdrawapplgtoken_result);

  // 判斷單號審核資料
  if($withdrawapplgtoken_result[0] == 1){
    // 锁住審核單號
    $withdrawapplgcash_lock = runSQL("UPDATE root_withdraw_review SET status = '3' WHERE id = '".$withdrawapplgtoken_id."' AND status = '2'; ");

    // 轉帳摘要 -- 代幣轉現金(tokengcash)
    $transaction_category_index = $withdraw_method_name_reject[$withdrawapplgtoken_result[1]->togcash];
    // 交易類別 , 定義再 system_config.php 內, 不可以隨意變更.
    $summary = $transaction_category[$transaction_category_index];
    // 轉帳摘要 -- 代幣提款行政手續費(tokenadministrationfees)
    $fee_transaction_category_index = 'tokenadministrationfees';
    // 交易類別 , 定義再 system_config.php 內, 不可以隨意變更.
    $fee_summary = $transaction_category[$fee_transaction_category_index].'退回';

    // 操作者 ID
    $member_id = $_SESSION['agent']->id;
    // 轉帳來源帳號 -- 代幣出納帳號 $gtoken_cashier_account
    $source_transferaccount = $gtoken_cashier_account;
    // $source_transferaccount = $withdrawapplgtoken_result[1]->account;
    // 轉帳目標帳號
    $destination_transferaccount = $withdrawapplgtoken_result[1]->account;
    // $destination_transferaccount = $gtoken_cashier_account;
    // 來源帳號提款密碼 or 管理員登入的密碼
    // $pwd_verify_sha1 = $_SESSION['agent']->passwd;
    // $pwd_verify_sha1 = 'tran5566'; // 移到config

    // 轉帳金額
    $transaction_money = $withdrawapplgtoken_result[1]->amount;
    // 行政手續費
    $fee_amount = $withdrawapplgtoken_result[1]->fee_amount;

    // 稽核費用
    $administrative_amount = $withdrawapplgtoken_result[1]->administrative_amount;
    // 優惠扣除
    $offer_deduction = $withdrawapplgtoken_result[1]->offer_deduction;

    // 總行政費(手續費 + 稽核不通過費用)
    $fee_transaction_money_sum  = round((float)($fee_amount + $administrative_amount),2);

    // 實際存提
    $realcash = 1;
    // 稽核方式
    $auditmode_select = 'freeaudit';
    // 稽核金額
    $auditmode_amount = '0';
    // 系統轉帳文字資訊(補充)
    $system_note = NULL;
    // $debug = 1 --> 進入除錯模式 , debug = 0 --> 關閉除錯
    $debug = 0;
    // 取款單號
    $transaction_id=$withdrawapplgtoken_result[1]->transaction_id;
    // 操作人員
    $operator = $_SESSION['agent']->account;
    // var_dump($transaction_id);die();

    // 檢查cacher餘額
    $cacher_chk = runSQLall('SELECT gtoken_balance FROM "root_member"  JOIN "root_member_wallets" ON "root_member"."id" = "root_member_wallets"."id"
      WHERE "root_member"."account" = \''.$gtoken_cashier_account.'\'  AND ("root_member"."status" = \'1\');');
    $cacher_balance_after_payout = $cacher_chk['1']->gtoken_balance - ($transaction_money + $fee_amount + $administrative_amount + $offer_deduction );
    if($cacher_balance_after_payout < 0) {
      $error['code'] = 'wgc504';
      $error['messages'] = $tr['Insufficient cashier balance'];

      runSQL("UPDATE root_withdraw_review SET status = '2',notes='(".$error['code'] .')'.$error['messages']."' WHERE id = '".$withdrawapplgtoken_id."' AND status = '3'; ");
    }else{
      // 退還轉帳金額
      // 原版
      // $error1 = member_gtoken_transfer($member_id, $source_transferaccount, $destination_transferaccount, $transaction_money, $pwd_verify_sha1, $summary, $transaction_category_index, $realcash, $auditmode_select, $auditmode_amount, $system_note, $debug,$transaction_id,$operator);

      // 20191017
      $error1 = member_gtoken_transfer($member_id, $source_transferaccount, $destination_transferaccount, $transaction_money, $config['withdrawal_pwd'], $summary, $transaction_category_index, $realcash, $auditmode_select, $auditmode_amount, $system_note, $debug,$transaction_id,$operator);


      // 退還取款手續費
      if ($fee_amount != 0) {
        // 原本:
        // $error2 = member_gtoken_transfer($member_id, $source_transferaccount, $destination_transferaccount, $fee_amount, $pwd_verify_sha1, $fee_summary, $fee_transaction_category_index, $realcash, $auditmode_select, $auditmode_amount, $system_note, $debug,$transaction_id,$operator);

        // 20191017
        $error2 = member_gtoken_transfer($member_id, $source_transferaccount, $destination_transferaccount, $fee_amount, $config['withdrawal_pwd'], $fee_summary, $fee_transaction_category_index, $realcash, $auditmode_select, $auditmode_amount, $system_note, $debug,$transaction_id,$operator);

      } else {
        $error2['code'] = 1;
        $error2['messages'] ='';
      }

      // 退還稽核費
      if ($administrative_amount != 0) {
        // 原本
        // $error3 = member_gtoken_transfer($member_id, $source_transferaccount, $destination_transferaccount, $administrative_amount, $pwd_verify_sha1, $fee_summary, $fee_transaction_category_index, $realcash, $auditmode_select, $auditmode_amount, $system_note, $debug,$transaction_id,$operator);

        // 20191017
        $error3 = member_gtoken_transfer($member_id, $source_transferaccount, $destination_transferaccount, $administrative_amount, $config['withdrawal_pwd'], $fee_summary, $fee_transaction_category_index, $realcash, $auditmode_select, $auditmode_amount, $system_note, $debug,$transaction_id,$operator);
      } else {
        $error3['code'] = 1;
        $error3['messages'] ='';
      }

      // 退還優惠扣除
      if ($offer_deduction != 0) {
        // 原本
        // $error4 = member_gtoken_transfer($member_id, $source_transferaccount, $destination_transferaccount, $offer_deduction, $pwd_verify_sha1, $summary, $transaction_category_index, $realcash, $auditmode_select, $auditmode_amount, $system_note, $debug,$transaction_id,$operator);

        // 20191017
        $error4 = member_gtoken_transfer($member_id, $source_transferaccount, $destination_transferaccount, $offer_deduction, $config['withdrawal_pwd'], $summary, $transaction_category_index, $realcash, $auditmode_select, $auditmode_amount, $system_note, $debug,$transaction_id,$operator);
      } else {
        $error4['code'] = 1;
        $error4['messages'] ='';
      }



      // if($error['code'] == 1){
      if($error1['code'] == 1 AND $error2['code'] == 1 AND $error3['code'] == 1 AND $error4['code'] == 1){
        $error['messages'] = $tr['Cancel token withdrawal request number'].$tr['Success.'];
        // 更新 root_withdraw_review 變數
        $update_sql = "UPDATE root_withdraw_review SET status = 0, notes='".$error['messages']."', processingaccount = '".$_SESSION['agent']->account."', processingtime = now() WHERE id = ".$withdrawapplgtoken_result[1]->id." AND status = '3';";
        $update_result = runSQLall($update_sql);

        if($update_result[0] == 1) {
          $error['code'] = '1';
          // $error['messages'] = '更新代幣取款單號'.$withdrawapplgtoken_result[1]->id.'成功。';$tr['Success.'] = '成功。';$tr['Cancel token withdrawal request number'] = '取消代幣取款申請單號';
          $error['messages'] = $tr['Cancel token withdrawal request number'].$withdrawapplgtoken_result[1]->transaction_id.$tr['Success.'];
          // echo '<p align="center"><button type="button" class="btn btn-success" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
        } else {
          $error['code'] = 'wgc505';
          // $error['messages'] = '更新代幣取款單號'.$withdrawapplgtoken_result[1]->id.'錯誤, 請聯絡開發人員處理。';$tr['error, please contact the developer for processing.'] = '錯誤，請聯絡開發人員處理。';
          $error['messages'] = $tr['Cancel token withdrawal request number'].$withdrawapplgtoken_result[1]->transaction_id.$tr['error, please contact the developer for processing.'];
          // echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
        }
      } else {
        $m_sql_result = runSQLall(<<<SQL
        SELECT *
        FROM "root_member"
        WHERE ("account" = '{$destination_transferaccount}')
        AND ("status" = '2');
    SQL);
        if ($m_sql_result[0] == 1) {
          $error['code'] = 'wgc506-'.$error1['code'].'-'.$error2['code'].'-'.$error3['code'].'-'.$error4['code'];
          $error['messages'] = $tr['This account has been frozen, you need to unlock the wallet first to operate deposits and withdrawals'];
        } else {
          $error['code'] = 'wgc506-'.$error1['code'].'-'.$error2['code'].'-'.$error3['code'].'-'.$error4['code'];
          $tr['Member information query failed'] = '會員資訊查詢失敗。';
          $error['messages'] = $tr['this feature is currently unavailable for member status'];
        }        
        // echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$error1['messages'].'</button></p>';
        runSQL("UPDATE root_withdraw_review SET status = '2',notes='".$error['messages']."' WHERE id = '".$withdrawapplgtoken_id."' AND status = '3'; ");
      }
    }
  } else {
    $error['code'] = 'wgc507';
    $error['messages'] =  '(x)'. $tr['This order number has been processed so far, do not re-operate.'].'';
    memberlog2db($_SESSION['agent']->account,'withdrawal','notice', $error['messages']);
    // echo '<script>alert("'.$logger.'");location.reload();</script>';
  }

  echo <<<HTML
  <script>
      alert("{$error['messages']}");
      location.reload();
  </script>
HTML;

// -----------------------------------------------------------------------------
} elseif($action == 'withdrawalgtoken_administrative_amount_update' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
// -----------------------------------------------------------------------------
// 更新行政稽核費用欄位
// -----------------------------------------------------------------------------

  $withdrawapplgtoken_id = filter_var($_POST['withdrawapplgtoken_id'], FILTER_SANITIZE_NUMBER_INT);
  // 檢查傳入的值，是否為浮點數。
  $administrative_amount = filter_var($_POST['administrative_amount'], FILTER_VALIDATE_FLOAT);
  // var_dump($administrative_amount);
  if($administrative_amount == false) {
    //$tr['Administrative audit costs wrong, please confirm that the input is a floating number.'] ='行政稽核費用錯誤，請確認輸入的為浮點數。';
    $logger = $tr['Administrative audit costs wrong, please confirm that the input is a floating number.'];
    echo '<div class="alert alert-danger" role="alert">'.$logger.'</div>';
  } else {
    // 取小數點第二位
    $administrative_amount = round($administrative_amount,2);
    $sql = "UPDATE root_withdraw_review SET administrative_amount = '$administrative_amount' WHERE id = '$withdrawapplgtoken_id' ;";
    //var_dump($sql);
    $result = runSQLall($sql);
    if($result[0] == 1) {
      // $tr['Administrative audit fee updated successfully.'] = '行政稽核費用更新成功。';
      $logger = $tr['Administrative audit fee updated successfully.'];
      echo '<div class="alert alert-success" role="alert">'.$logger.'</div>';
      //echo '<script>window.location.reload();</script>';
    } else {
      // $tr['Administrative audit fee update failed'] = '行政稽核費用更新失敗。';
      $logger = $tr['Administrative audit fee update failed'] ;
      echo '<div class="alert alert-danger" role="alert">'.$logger.'</div>';
    }
  }


// -----------------------------------------------------------------------------
} elseif($action == 'withdrawalgtoken_notes_common_update' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
// -----------------------------------------------------------------------------
// 更新註解欄位資訊。
// -----------------------------------------------------------------------------
  $withdrawapplgtoken_id = filter_var($_POST['withdrawapplgtoken_id'], FILTER_SANITIZE_NUMBER_INT);
  // 檢查傳入的值，是否為字串且合法
  $notes_common = filter_var($_POST['notes_common'], FILTER_SANITIZE_STRING);
  //var_dump($notes_common);
  if($notes_common == false) {
    // $tr['The content of the comment is incorrect. Please confirm it as a string and valid.'] = '備註內容錯誤，請確認為字串且合法。';
    $logger = $tr['The content of the comment is incorrect. Please confirm it as a string and valid.'];
    echo <<<HTML
      <script>
          alert("{$logger}");
          location.reload();
      </script>
  HTML;
  } else {
    // var_dump($_POST);
    $sql = "UPDATE root_withdraw_review SET notes = '$notes_common', processingtime = now(), processingaccount = '".$_SESSION['agent']->account."' WHERE id = '$withdrawapplgtoken_id' ;";
    // var_dump($sql);
    $result = runSQLall($sql);
    if($result[0] == 1) {
      // $tr['Remarks Content update completed.'] = '備註內容更新完成。';
      $logger = $tr['Remarks Content update completed.'];
      echo <<<HTML
      <script>
          alert("{$logger}");
          location.reload();
      </script>
  HTML;
      //echo '<script>window.location.reload();</script>';
    } else {
      // $tr['Remark Content update failed.'] = '備註內容更新失敗。';
      $logger = $tr['Remark Content update failed.'];
      echo <<<HTML
      <script>
          alert("{$logger}");
          location.reload();
      </script>
  HTML;
    }
  }

// -----------------------------------------------------------------------------
// } elseif($action == 'edit_withdraw_method' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
//   // var_dump($_POST);

//   $id = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
//   $withdraw_method = filter_var($_POST['withdraw_method'], FILTER_SANITIZE_NUMBER_INT);

//   if ($id != '' AND $withdraw_method != '') {
//     if ($withdraw_method > 1 OR $withdraw_method < 0) {
//       $logger = '請選擇正確的出款方式。';
//       echo '<script>alert("'.$logger.'");location.reload();</script>';
//       return;
//     }

//     $update_sql = "UPDATE root_withdraw_review SET togcash = '".$withdraw_method."' WHERE id = '".$id."';";
//     $update_sql_result = runSQL($update_sql);

//     $logger = $update_sql_result ? '出款方式更新成功。' : '出款方式更新失敗。';
//     echo '<script>alert("'.$logger.'");location.reload();</script>';
//   } else {
//     $logger = '請選擇正確的出款方式。';
//     echo '<script>alert("'.$logger.'");location.reload();</script>';
//   }


} elseif($action == 'get_result' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  // 搜尋

  $review_agent_status[NULL] 	= $tr['delete'];
  $review_agent_status[0] 	= $tr['Cancel'];
  $review_agent_status[1] 	= $tr['agree'];
  // 单号审核中
  // $tr['select function'] = '選擇功能';
  $review_agent_status[2] 	= $tr['select function'];

  $review_agent_status[3] 	= 'lock';

  if(isset($query_sql_array)){
      // 處理 datatables 傳來的排序需求
      if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
        $sql_order_dir = ($_GET['order'][0]['dir'] == 'asc')? 'ASC':'DESC';
        $sql_order = 'ORDER BY '.$_GET['columns'][$_GET['order'][0]['column']]['data'].' '.$sql_order_dir;
      }else{ $sql_order = 'ORDER BY id ASC';}

      // 取得查詢條件
      $query_str = query_str($query_sql_array);
      // echo $query_str;die();
      if(isset($query_str['logger'])){
        // $output = array('logger' => $query_str['logger']);
        $output = array(
          "sEcho" 									=> 0,
          "iTotalRecords" 					=> 0,
          "iTotalDisplayRecords" 		=> 0,
          "data" 										=> ''
        );
        echo json_encode($output);die();
        // echo '<script>alert("'.$query_str['logger'].'");</script>';die();
      }
      $sql_tmp   = sql_query().$query_str." "." ".$sql_order;//.$sql_order;
      // echo($sql_tmp);die();

      // 算資料總筆數
      $userlist_sql   = $sql_tmp.';';
      $userlist_count = runSQL($userlist_sql);
      // echo $userlist_count;die();

      // -----------------------------------------------------------------------
      // 分頁處理機制
      // -----------------------------------------------------------------------
      // 所有紀錄數量
      $page['all_records']     = $userlist_count;
      // 每頁顯示多少
      $page['per_size']        = $current_per_size;
      // 目前所在頁數
      $page['no']              = $current_page_no;

      if($userlist_count != 0){
        $review_agent_status[NULL] 	= $tr['delete'];
        $review_agent_status[0] 	= $tr['Cancel'];
        $review_agent_status[1] 	= $tr['agree'];
        // 单号审核中
        // $tr['select function'] = '選擇功能';
        $review_agent_status[2] 	= $tr['select function'];

        // 取出資料
        $userlist_sql   = $sql_tmp.' OFFSET '.$page['no'].' LIMIT '.$page['per_size'].';';
        $userlist = runSQLall($userlist_sql);
        // var_dump($userlist_sql);die();

        // 從list內找出parent_id
        $to_get_data = only_get_parent($userlist);
        // var_dump($to_get_data);die();

          foreach($to_get_data as $a1 => $v1){

            // 提款 - 申請單號
            $withdrawalgtoken_id = '<a class="p-3 text-muted" href="./withdrawalgtoken_company_audit_review.php?id='.$v1['id'].'" role="button" target="_SELF" title="'.$tr['go to number'].' '.$v1['id'].' '.$tr['detail'].'"><i class="fas fa-angle-right"></i></a>';

            // 會員帳號查驗連結,$tr['Check membership details'] = '檢查會員的詳細資料';
            $member_check_html = '<a href="member_account.php?a='.$v1['member_id'].'" target="_BLANK" title="'.$tr['Check membership details'].'">'.$v1['account'].'</a>';

            // 審查的選項 -- for confirm
            // $confirm_status_html = '<button type="button" class="btn btn-warning btn-xs"><a href="#" class="status" id="status" data-type="select" data-pk="'.$list[$i]->id.','.$list[$i]->account.', '.$list[$i]->status.'" data-title="請確認是否同意使用者申請成為代理商？">'.$review_agent_status[$list[$i]->status].'</a></button>';
            $confirm_status_html = '';

            // $tr['select function'] = '選擇功能',$tr['seq applying'] = '單號審核中';
            if($review_agent_status[$v1['status']] == $tr['select function']){
              $confirm_status_html = '
              <button id="agreen_ok'.$v1['id'].'" class="btn btn-primary  agreen_ok mr-1 mb-1 btn-sm" role="button" value="'.$v1['id'].'">'.$tr['agree'].'</button>
              <button id="agreen_cancel'.$v1['id'].'" class="btn btn-danger  agreen_cancel btn-sm" role="button" value="'.$v1['id'].'">'.$tr['disagree'].'</button>
              ';
              // $tr['agree']= '同意';
            }else if ($review_agent_status[$v1['status']] == $tr['agree']){
              // $tr['Approved'] = '已审核通过';
              $confirm_status_html = '
              <a href="./withdrawalgtoken_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-success btn-xs">'.$tr['Approved'].'</a>
              ';
            }else if ($review_agent_status[$v1['status']] == 'lock'){
              $confirm_status_html = '
              <a href="./withdrawalgtoken_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-lock"><span></a>
              ';
            }else{
              // $tr['application reject'] = '审核退回';
              $confirm_status_html = '
              <a href="./withdrawalgtoken_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-danger btn-xs">'.$tr['application reject'].'</a>
              ';
            }

            // 檢查使用者的代幣存簿,$tr['Check the users token deposit book'] = '檢查使用者的代幣存簿';
            // $check_amount_html = '<a href="member_transactiongtoken.php?a='.$list[$i]->member_id.'" title="'.$tr['Check the users token deposit book'].'" target="_SELF">'.$list[$i]->amount.'</a>';
            // 提款金額 加上  ＄ 格式, 小數點 2 位 number_format
            $amount_money = '$'.number_format(round($v1['amount'], 2),2);
            //手續費  加上  ＄ 格式, 小數點 2 位 number_format
            $fee_amount_money = '$'.number_format(round($v1['fee_amount'], 2),2);
            // 行政稽核
            $administrative_amoun_money = '$'.number_format(round($v1['administrative_amount'], 2),2);

            // ---------------------------------------------------
            // 出款方式
            $withdraw_method_html = ($v1['togcash'] == 0) ? '<span class="label label-info">'.$tr['Bank withdrawal'].'</span>' : '<span class="label label-info">'.$tr['Convert to cash'].'</span>';
            // IP
            $to_get_ip_log= <<<HTML
            <form target="_blank" data-id ="link_member_log" action="member_log.php" method="post">
                <button class="btn btn-default btn-xs" data-id="check_{$v1['account']}" title="{$tr['check member login status']}">{$v1['applicationip']}</button>
                <input type="hidden" data-id="ip_source" name="ip_query" value="{$v1['applicationip']}">
            </form>
HTML;
            // ---------------------------------------------------

            // 出款方式HTML
            $withdraw_type=<<<HTML
              <td id="{$v1['id']}">
                {$withdraw_method_html}
                <p class="my-1">{$v1['companyname']}</p>
                <p class="mb-1">{$v1['accountnumber']}</p>
              </td>
HTML;
            // gmdate('Y-m-d H:i:s', strtotime($v1['applicationtime_tz']) + -4 * 3600)
            $show_list_array[] = array(
              'agent'=> $v1['parent'],
              'account'=> $member_check_html,
              'transaction_id'=> $v1['transaction_id'],
              'amount'=> $amount_money,
              'fee_amount'=> $fee_amount_money,
              'administrative_amount'=> $administrative_amoun_money,
              'applicationtime_tz'=> gmdate('Y-m-d H:i:s', strtotime($v1['applicationtime_tz']) + -4 * 3600),
              'type'=> $withdraw_type,
              'applicationip'=> $to_get_ip_log,
              'status'=> $confirm_status_html,
              'detail'=> $withdrawalgtoken_id
            );
            // var_dump( $show_list_array);die();
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
          "sEcho" 									=> 0,
          "iTotalRecords" 					=> 0,
          "iTotalDisplayRecords" 		=> 0,
          "data" 										=> ''
        );
      }
  }else{
     // 搜尋區間沒資料
     $output = array(
      "sEcho" 									=> 0,
      "iTotalRecords" 					=> 0,
      "iTotalDisplayRecords" 		=> 0,
      "data" 										=> ''
    );
  }
  echo json_encode($output);

}else {
  // $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
  $logger = $tr['only management and login mamber'] ;
  memberlog2db('guest','withdrawal','notice', "$logger");
  echo '<script type="text/javascript">alert("'.$logger.'");location.href="./depositing_company_audit.php"</script>';die();
}
?>
