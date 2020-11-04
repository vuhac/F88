<?php
// ----------------------------------------------------------------------------
// Features:	GTOKEN 代理商後台，人工提款GTOKEN ajax 動作的處理
// File Name:	member_withdrawalgtoken_action.php
// Author:		Barkley
// Related:   member_withdrawalgtoken.php
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
// require_once dirname(__FILE__) ."/lib.php";

// gtoken lib 現金轉帳函式庫
require_once dirname(__FILE__) ."/gtoken_lib.php";

if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的測試');
}

if (!isset($_SESSION['agent']) || $_SESSION['agent'] == 'R') {
  die('(x)請登入正確管理帳號再行嘗試');
}
// var_export($_SESSION);die();
//var_dump($_POST);
//var_dump($_GET);

// ----------------------------------
// 本程式使用的 function
// ----------------------------------


// GTOKEN 提出的轉入帳戶。 define in config.php
// $gtoken_cashier_account = 'gtoken_cashier';



// 檢查函式，檢查帳號是否存在，且合法可以使用。且不是登入者本身的帳號
// return: 1 --> valid   , 0 --> no valid , 2-n --> other
// 這個函式給提款使用，預設目的是 $gtoken_cashier_account 帳號
// -----------------------------------------------------------
function check_withdrawalgtoken_transferaccount($source_transferaccount_input,$destination_transferaccount_input) {

  // 現金提出的轉入帳戶。
  global $gtoken_cashier_account;

  if($source_transferaccount_input == '' OR $destination_transferaccount_input == ''){
    $check_return['messages'] = '尚無資料';
    $check_return['code'] = 3;
  }else{
    //var_dump($destination_transferaccount_input);
    // $sql = "SELECT * FROM root_member WHERE status = '1' AND account = '".$source_transferaccount_input."';";
    $sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$source_transferaccount_input."';";

    $r = runSQLall($sql);
    //var_dump($r);

    // 如果登入者身份，存在系統內的話。
    if($r[0] == 1) {
      $source_transferaccount_input = $r[1]->account;

      // 身分不可以是管理員
      if ($r[1]->therole != 'R') {
        // 不是登入者本身的帳號
        if($destination_transferaccount_input != $source_transferaccount_input) {
          $check_return['messages'] =  '取款帳號 '.$source_transferaccount_input.' 存在可以使用，餘額還有：'.$r[1]->gcash_balance;
          $check_return['code'] = 1;
        }else{
          // cash_cashier
          $check_return['messages'] =  '取款帳號不可以是 '.$gcash_cashier_account.' 帳號 '.$destination_transferaccount_input;
          $check_return['code'] = 2;
        }

      } else {
        $check_return['messages'] =  '來源帳號身分不可以是管理員';
        $check_return['code'] = 3;
      }

    }else{
      $check_return['messages'] =  '無此帳號 '.$source_transferaccount_input;
      $check_return['code'] = 0;
    }
  }

  return($check_return);
}


// member grade 會員等級的名稱，取得會員等級的詳細資訊。預計用來限制取款金額。(轉帳金額)
// -------------------------------------
$grade_sql = "SELECT * FROM root_member_grade WHERE status = 1 AND id = '".$_SESSION['agent']->grade."';";
$graderesult = runSQLALL($grade_sql);
//var_dump($graderesult);
if($graderesult[0] == 1) {
	$member_grade = $graderesult[1];
}else{
	$member_grade = NULL;
}
// var_dump($member_grade);


// ----------------------------------
// 動作為會員登入檢查 MAIN
// ----------------------------------
if($action == 'member_withdrawalgtoken_check' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // ----------------------------------------------------------------------------
  // 人工取款 GCASH 功能：檢查並提示用戶帳號是否正確。 現金取款為轉帳到 cash_cashier  帳戶。
  // ----------------------------------------------------------------------------

  // var_dump($_POST);
  // 只有管理員身份，才可以使用來自 post 的來源 data
  if($_SESSION['agent']->therole == 'R') {
    $source_transferaccount_input = filter_var($_POST['source_transferaccount_input'], FILTER_SANITIZE_STRING);
  }else{
    $source_transferaccount_input = $_SESSION['agent']->account;
  }
  // 目的帳號 cash_cashier , 系統提款到現金預設寫入到 cash_cashier  帳號內。
  // $destination_transferaccount_input = filter_var($_POST['destination_transferaccount_input'], FILTER_SANITIZE_STRING);
  $destination_transferaccount_input = $gtoken_cashier_account;

  // 執行檢查
  $check_acc = check_withdrawalgtoken_transferaccount($source_transferaccount_input,$destination_transferaccount_input);
  echo $check_acc['messages'];


// 後台按下--人工游戏币提款(GTOKEN)
}elseif($action == 'member_withdrawalgtoken' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // ----------------------------------------------------------------------------
  // 人工取回 gtoken 功能
  // ----------------------------------------------------------------------------

  // var_dump($_POST);die();

  $post_v = (object)validate_post($_POST);

  if (!$post_v->status) {
    echo '<script>alert("'.$post_v->result.'");location.reload();</script>';
    die();
  }

  $m_data = (object)check_memberdata($post_v->result->acc);
  if (!$m_data->status) {
    echo '<script>alert("'.$m_data->result.'");location.reload();</script>';
    die();
  }

  if ($m_data->result->therole == 'R') {
    $error_msg = '來源帳號身分不可以是管理員';
    echo '<script>alert("'.$error_msg.'");location.reload();</script>';
    die();
  }

  if($m_data->result->account == $gtoken_cashier_account) {
    $error_msg = '來源帳號不可以是出納帳號';
    echo '<script>alert("'.$error_msg.'");location.reload();</script>';
    die();
  }

  if ($m_data->result->gtoken_balance < $post_v->result->transaction_money) {
    $error_msg = '帳號 : '.$m_data->result->account.' 遊戲幣錢包餘額不足';
    echo '<script>alert("'.$error_msg.'");location.reload();</script>';
    die();
  }

  $gtoken_cashier_data = (object)check_memberdata($gtoken_cashier_account);
  if (!$gtoken_cashier_data->status) {
    echo '<script>alert("'.$gtoken_cashier_data->result.'");location.reload();</script>';
    die();
  }

  $check_front_note=empty($post_v->result->front_note)?'':'，'.$post_v->result->front_note;

  // 後台人工出遊戲幣，但也要寫入root_withdraw_review 取款交易單號
  // 人工提出遊戲幣-交易單號，預設以 (w/d)20180515_useraccount_亂數3碼 為單號，其中 w:代表提款/d:代表存款/md:後台人工存款/mw:後台人工提款
  // 原版
  // $w_transaction_id='mw'.date("YmdHis").$_SESSION['agent']->account.str_pad(mt_rand(1,999),3,'0',STR_PAD_LEFT);

  // 2019-7-12
  $w_transaction_id='mw'.date("YmdHis").$m_data->result->account.str_pad(mt_rand(1,999),3,'0',STR_PAD_LEFT);

  // $data為寫入gtokenpassbook之參數
  $data = (object)[
    'member_id' => $_SESSION['agent']->id,
    'source_transferaccid' => $m_data->result->id,
    'source_transferaccount' => $post_v->result->acc,
    'destination_transferaccid' => $gtoken_cashier_data->result->id,
    'destination_transferaccount' => $gtoken_cashier_account,
    'transaction_money' => round($post_v->result->transaction_money, 2),
    'summary' => $transaction_category[$post_v->result->transaction_category].$check_front_note,
    'auditmode_select' => 'freeaudit',
    'auditmode_amount' => 0,
    'transaction_category' => $post_v->result->transaction_category,
    'realcash' => $post_v->result->realcash,
    'system_note' => $post_v->result->note,
    'debug' => 0,
    'transaction_id'=>$w_transaction_id
  ];

  $sql = member_gtoken_transfer_sql($data);

  // echo($sql);die();

  // 後台人工提出遊戲幣，寫入root_withdraw_review
  $withdrawgtoken_data=[
      'account'               =>$post_v->result->acc,
      // changetime
      'status'                =>'1',
      'amount'                =>round($post_v->result->transaction_money, 2),
      'companyname'           =>null,
      'accountname'           =>$post_v->result->acc,
      'accountnumber'         =>null,
      'accountprovince'       =>null,
      'notes'                 =>$post_v->result->note,
      // applicationtime
      'processingaccount'     =>$_SESSION['agent']->account,
      // processingtime
      'accountcounty'         =>null,
      'applicationip'         =>$_SESSION['fingertracker_remote_addr'],
      'mobilenumber'          =>null,
      'wechat'                =>null,
      'email'                 =>null,
      'qq'                    =>null,
      'togcash'               =>'0',
      'fingerprinting'        =>$_SESSION['fingertracker'],
      'administrative_amount' =>'0',
      'fee_amount'            =>'0',
      'offer_deduction'       =>'0',
      'transaction_id'        =>$w_transaction_id,
  ];
  $withdrawgtoken_sql = <<<SQL
  INSERT INTO root_withdraw_review
  (
    "account", "changetime", "status",
    "amount", "companyname","accountname",
    "accountnumber","accountprovince", "notes",
    "applicationtime", "processingaccount", "processingtime",
    "accountcounty", "applicationip",  "mobilenumber",
    "wechat", "email", "qq",
    "togcash", "fingerprinting", "administrative_amount",
    "fee_amount","offer_deduction","transaction_id"
  ) VALUES (
    '{$withdrawgtoken_data['account']}', now(), '{$withdrawgtoken_data['status']}',
    '{$withdrawgtoken_data['amount']}', '{$withdrawgtoken_data['companyname']}', '{$withdrawgtoken_data['accountname']}',
    '{$withdrawgtoken_data['accountnumber']}', '{$withdrawgtoken_data['accountprovince']}', '{$withdrawgtoken_data['notes']}',
     now() , '{$withdrawgtoken_data['processingaccount']}', now(),
    '{$withdrawgtoken_data['accountcounty']}', '{$withdrawgtoken_data['applicationip']}', '{$withdrawgtoken_data['mobilenumber']}',
    '{$withdrawgtoken_data['wechat']}', '{$withdrawgtoken_data['email']}', '{$withdrawgtoken_data['qq']}',
    '{$withdrawgtoken_data['togcash']}', '{$withdrawgtoken_data['fingerprinting']}', '{$withdrawgtoken_data['administrative_amount']}',
    '{$withdrawgtoken_data['fee_amount']}', '{$withdrawgtoken_data['offer_deduction']}', '{$withdrawgtoken_data['transaction_id']}'
  );
SQL;
// echo($withdrawgtoken_sql);die();
  $transaction_sql = 'BEGIN;'
  .$sql
  .$withdrawgtoken_sql
  .'COMMIT;';
  // echo $transaction_sql;die();
  $transaction_result = runSQLtransactions($transaction_sql);

  if (!$transaction_result) {
    $error_msg = '转帐失败从'.$data->source_transferaccount.'到'.$data->destination_transferaccount.'，金额 : '.$data->transaction_money;
    echo '<script>alert("'.$error_msg.'");</script>';
    // echo '<script>alert("'.$error_msg.'");location.reload();</script>';
    die();
  }

  $error_msg = '成功转帐从'.$data->source_transferaccount.'到'.$data->destination_transferaccount.'，金额 : '.$data->transaction_money;
  // echo '<script>alert("'.$error_msg.'");</script>';
  echo '<script>alert("'.$error_msg.'");history.back();</script>';

// ----------------------------------------------------------------------------
}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);

}


function validate_post($post)
{
  $input = array();

  $s_acc = filter_var($post['source_transferaccount_input'], FILTER_SANITIZE_STRING);

  if (empty($s_acc)) {
    $error_msg = '不合法的帳號';
    return array('status' => false, 'result' => $error_msg);
  }

  $input['acc'] = $s_acc;


  $pw = filter_var($post['password_input'], FILTER_SANITIZE_STRING);

  if (empty($pw)) {
    $error_msg = '不合法的密碼';
    return array('status' => false, 'result' => $error_msg);
  }

  if ($pw != $_SESSION['agent']->passwd) {
    $error_msg = '密碼不正確';
    return array('status' => false, 'result' => $error_msg);
  }

  $input['pw'] = $pw;


  $money = filter_var($post['balance_input'], FILTER_VALIDATE_FLOAT);

  if ($money === false) {
    $error_msg = '不合法的轉帳金額';
    return array('status' => false, 'result' => $error_msg);
  }

  if ($money < 0) {
    $error_msg = '轉帳金額不可小於0';
    return array('status' => false, 'result' => $error_msg);
  }

  $input['transaction_money'] = $money;


  $transaction_category = filter_var($_POST['transaction_category_input'], FILTER_SANITIZE_STRING);

  if (empty($transaction_category)) {
    $error_msg = '請選擇正確交易類型';
    return array('status' => false, 'result' => $error_msg);
  }

  if ($transaction_category != 'tokenrecycling' && $transaction_category != 'tokenadministrationfees') {
    $error_msg = '不合法的交易類型';
    return array('status' => false, 'result' => $error_msg);
  }

  $input['transaction_category'] = $transaction_category;


  $realcash = filter_var($_POST['realcash_input'], FILTER_SANITIZE_NUMBER_INT);

  if ($realcash != 1 && $realcash != 0) {
    $error_msg = '不合法的實際提存';
    return array('status' => false, 'result' => $error_msg);
  }

  $input['realcash'] = $realcash;


  $note = filter_var($_POST['system_note_input'], FILTER_SANITIZE_STRING);
  $input['note'] = $note;

  //前台摘要
  $front_note = filter_var($_POST['front_system_note'], FILTER_SANITIZE_STRING);
  $input['front_note'] = $front_note;

  return array('status' => true, 'result' => (object)$input);
}

function check_memberdata($acc)
{
  // 2020/01/30 修正 By Damocles
  // 加上 (root_member.status = 3) 判斷，因為 gcashcashier 是系統帳號
  // 然後 demo站上的 gcashcashier.status 是1
  // 而 VIP站上的 gcashcashier.status 是3
  $sql = <<<SQL
    SELECT *
    FROM root_member
    JOIN root_member_wallets
    ON (root_member.id = root_member_wallets.id)
    WHERE (root_member.account = '{$acc}') AND
          (
            (root_member.status = '1') OR (root_member.status = '3')
          )
  SQL;
  $result = runSQLall($sql);

  if( !isset($result[0]) || ($result[0] == 0) ){
    $error_msg = '帳號 : '.$acc.' 無效，請確認後重新輸入';
    return array('status' => false, 'result' => $error_msg);
  }

  unset($result[0]);
  return array('status' => true, 'result' => $result[1]);
}
?>
