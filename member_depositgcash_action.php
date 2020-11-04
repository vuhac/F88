<?php
// ----------------------------------------------------------------------------
// Features:	人工存入現金後台處理
// File Name:	member_depositgcash_action.php
// Author:		Barkley
// Modifier:    Damocles
// Related:   member_depositgcash.php , root_member_account_setting
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

if( isset($_GET['a']) ){
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}
else{
    die('(x)不合法的測試');
}

if( !isset($_SESSION['agent']) || ($_SESSION['agent'] == 'R') ){
    die('(x)請登入正確管理帳號再行嘗試');
}

// ----------------------------------
// 本程式使用的 function
// ----------------------------------
// 檢查函式，檢查帳號是否存在，且合法可以使用。且不是登入者本身的帳號
// return: 1 --> valid   , 0 --> no valid , 2-n --> other
// -----------------------------------------------------------
function check_destination_transferaccount($source_transferaccount_input, $destination_transferaccount_input) {
    if( empty($source_transferaccount_input) || empty($destination_transferaccount_input) ){
        $check_return['messages'] = '尚無資料';
        $check_return['code'] = 3;
    }
    else{
        $sql = <<<SQL
            SELECT *
            FROM root_member
            WHERE (status = '1') AND
                  (account = '{$destination_transferaccount_input}');
        SQL;
        $r = runSQLall($sql);

        // 如果登入者身份，存在系統內的話。
        if( $r[0] == 1 ){
            $destination_transferaccount_input = $r[1]->account;
            // 身分不可以是管理員
            if ($r[1]->therole != 'R') {
                // 不是登入者本身的帳號
                if($destination_transferaccount_input != $source_transferaccount_input) {
                    $check_return['messages'] =  '轉入帳號 '.$destination_transferaccount_input.' 存在可以使用';
                    $check_return['code'] = 1;
                }
                else{
                    $check_return['messages'] =  '轉入帳號不可以是來源帳號 '.$source_transferaccount_input;
                    $check_return['code'] = 2;
                }

            } else {
                $check_return['messages'] =  '轉入帳號身分不可以是管理員';
                $check_return['code'] = 3;
            }

        }
        else{
            $check_return['messages'] =  '無此帳號 '.$destination_transferaccount_input;
            $check_return['code'] = 0;
        }
    }

    return($check_return);
} // end check_destination_transferaccount

// 計算使用者當日gcash存款總額(member_depositgcash.php、member_depositgcash_action.php)
function calculate_deposit_total( $account ){
    $current_date = gmdate('Y-m-d',time()+ -4*3600).' 12:00 +08';
    $manual_deposit_sql=<<<SQL
        SELECT coalesce(sum(withdrawal),0) as sum
        FROM root_member_gcashpassbook
        WHERE (operator = '{$account}') AND
              (transaction_category = 'cashdeposit') AND
              (source_transferaccount = 'gcashcashier') AND
              (transaction_time >= '{$current_date}')
    SQL;
        $manual_deposit_sql_result = runSQLall($manual_deposit_sql);
        return round($manual_deposit_sql_result[1]->sum,0);
} // end calculate_deposit_total
// ----------------------------------
// 動作為會員登入檢查 MAIN
// ----------------------------------
if( ($action == 'member_depositgcash_check') && isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ) {
    // ----------------------------------------------------------------------------
    // 人工存入 GTOKEN 功能：檢查並提示用戶帳號是否正確。
    // ----------------------------------------------------------------------------

    // 只有管理員身份，才可以使用來自 post 的來源 data
    if($_SESSION['agent']->therole == 'R') {
        $source_transferaccount_input = filter_var($_POST['source_transferaccount_input'], FILTER_SANITIZE_STRING);
    }
    else{
        $source_transferaccount_input = $_SESSION['agent']->account;
    }
    // 目的帳號
    $destination_transferaccount_input = filter_var($_POST['destination_transferaccount_input'], FILTER_SANITIZE_STRING);

    // 執行檢查
    $check_acc = check_destination_transferaccount($source_transferaccount_input,$destination_transferaccount_input);
    echo $check_acc['messages'];
}
else if( ($action == 'member_depositgcash') && isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ) {
    // ----------------------------------------------------------------------------
    // 人工存入功能， 人工存入 GCASH 功能：此為管理員或允許的客服人員進行人工存入現金的工作。管理員可以給與 GCASH 給任何帳戶。
    // ----------------------------------------------------------------------------

    // 引用現金處理函式庫
    require_once dirname(__FILE__) ."/gcash_lib.php";

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
        $error_msg = '存入帳號身分不可以是管理員';
        echo '<script>alert("'.$error_msg.'");location.reload();</script>';
        die();
    }

    if($m_data->result->account == $gcash_cashier_account) {
        $error_msg = '存入帳號不可以是出納帳號';
        echo '<script>alert("'.$error_msg.'");location.reload();</script>';
        die();
    }

    $gcash_cashier_data = (object)check_memberdata($gcash_cashier_account);
    if (!$gcash_cashier_data->status) {
        echo '<script>alert("'.$gcash_cashier_data->result.'");location.reload();</script>';
        die();
    }

    if ($gcash_cashier_data->result->gcash_balance < $post_v->result->transaction_money) {
        $error_msg = '出納帳號 : '.$gcash_cashier_data->result->account.' 現金餘額不足';
        echo '<script>alert("'.$error_msg.'");location.reload();</script>';
        die();
    }

    if( in_array($_SESSION['agent']->account, $su['superuser']) ) {
        //如果是superuser，不做權限判斷
    }
    else{
        $gcash_input_max = 0; // 人工存入現金單次限額
        $gcash_input_daily_max = 0; // 人工存入現金單日限額

        // 從root_member_account_setting找出該帳號的設定值，如果沒有找到的話，自動新增一筆並讀取預設值(功能相依於lib.php)
        $account_setting = query_account_setting('account', $_SESSION['agent']->account);

        if($account_setting[0] == 1){ // 有找到該帳號的設定值，取出設定值
            $gcash_input_max = $account_setting[1]->gcash_input_max;
            $gcash_input_daily_max = $account_setting[1]->gcash_input_daily_max;
        }
        else{ // 沒有找到該帳號的設定值，新增一筆並且取出設定值
            if( insert_account_setting( $_SESSION['agent']->account ) == 1 ){ // 新增帳號設定值成功
                $account_setting = query_account_setting('account', $_SESSION['agent']->account);
                $gcash_input_max = $account_setting[1]->gcash_input_max;
                $gcash_input_daily_max = $account_setting[1]->gcash_input_daily_max;
            }
            else{ // 新增帳號設定值失敗，須作錯誤訊息處理
                $error_msg = '读取帐号设定值失败！如持续发生请联络网站管理员。';
                die('<script>alert("'.$error_msg.'"); $("#submit_to_memberdeposit").attr("disabled", false);</script>');
            }
        }

        // 判斷存入金額是否超出單筆限額
        if( round($post_v->result->transaction_money, 2) > $gcash_input_max ){
            $error_msg = "存款金额超出单笔限额：{$gcash_input_max} 元";
            die('<script>alert("'.$error_msg.'"); $("#submit_to_memberdeposit").attr("disabled", false);</script>');
        }

        // 判斷當日存入總金額是否超出總限額
        $manual_deposit_total = calculate_deposit_total( $_SESSION['agent']->account );
        // 如果超過總額限制，傳出錯誤
        $transaction_money = round($post_v->result->transaction_money, 0);
        if( ($transaction_money + $manual_deposit_total) > $gcash_input_daily_max){
            $error_msg = "存入金额超出今日总限额(欲存+今日已存入>总限额)：{$transaction_money} + {$manual_deposit_total} > {$gcash_input_daily_max}。";
            die("<script>alert('{$error_msg}'); $('#submit_to_memberdeposit').attr('disabled', false);</script>");
        }
    }

    $check_front_note = empty($post_v->result->front_note)?'':'，'.$post_v->result->front_note;


    // 人工存入現金-交易單號，預設以 (w/d)20180515_useraccount_亂數3碼 為單號，其中 w:代表提款/d:代表存款/md:後台人工存款/mw:後台人工提款
    // 原版
    // $d_transaction_id='md'.date("YmdHis").$_SESSION['agent']->account.str_pad(mt_rand(1,999),3,'0',STR_PAD_LEFT);

    // 2019.7.12
    $d_transaction_id='md'.date("YmdHis").$m_data->result->account.str_pad(mt_rand(1,999),3,'0',STR_PAD_LEFT);


    $data = (object)[
        'member_id'                   => $_SESSION['agent']->id,
        'source_transferaccid'        => $gcash_cashier_data->result->id,
        'source_transferaccount'      => $gcash_cashier_account,
        'destination_transferaccid'   => $m_data->result->id,
        'destination_transferaccount' => $post_v->result->acc,
        'transaction_money'           => round($post_v->result->transaction_money, 2),
        'summary'                     => $transaction_category[$post_v->result->transaction_category].$check_front_note,
        'transaction_category'        => $post_v->result->transaction_category,
        'realcash'                    => $post_v->result->realcash,
        'system_note'                 => $post_v->result->note,
        'debug'                       => 0,
        'transaction_id'              => $d_transaction_id,
        'operator'                    => $_SESSION['agent']->account
    ];

    $sql = member_gcash_transfer_sql($data);

    $transaction_sql = 'BEGIN;'.$sql.'COMMIT;';
    $transaction_result = runSQLtransactions($transaction_sql);

    if (!$transaction_result) {
        $error_msg = '转帐失败从'.$data->source_transferaccount.'到'.$data->destination_transferaccount.'，金额 : '.$data->transaction_money;
        echo '<script>alert("'.$error_msg.'");location.reload();</script>';
        // echo '<script>alert("'.$error_msg.'");</script>';
        die();
    }

    update_gcash_log_exist($data->destination_transferaccount);

    $error_msg = '成功转帐从'.$data->source_transferaccount.'到'.$data->destination_transferaccount.'，金额 : '.$data->transaction_money;
    echo '<script>alert("'.$error_msg.'");history.back();</script>';


    // ----------------------------------------------------------------------------
}
elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);

}

function validate_post($post)
{
  $input = array();

  $d_acc = filter_var($post['destination_transferaccount_input'], FILTER_SANITIZE_STRING);

  if (empty($d_acc)) {
    $error_msg = '不合法的帳號';
    return array('status' => false, 'result' => $error_msg);
  }

  $input['acc'] = $d_acc;

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
  // 如果交易類型不等於"現金存款" or "电子支付存款"，則顯示錯誤
  if (empty($transaction_category) || ($transaction_category != 'cashdeposit' AND $transaction_category != 'payonlinedeposit')) {
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
