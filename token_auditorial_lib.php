<?php
// ----------------------------------------------------------------------------
// Features:	即時稽核計算 lib
// File Name:	token_auditorial_lib.php
// Author:		Yuan
// Related:   對應即時稽核功能
// Log:
// 2020.08.05 Bug #4383 VIP站後台，娛樂城反水計算 > 錢包凍結帳號 > 轉現金/轉遊戲幣選項 > 跳錯  Letter
//            1.不過濾會員狀態
//            2.給予變數預設值
//            3.方法回傳值統一為陣列
// ----------------------------------------------------------------------------

// 投注紀錄專用檔
require_once dirname(__FILE__) ."/config_betlog.php";


// 計算指定時間距離現在時間是多久以前
function get_howlongago($date)
{
  $time = strtotime($date);
//  $now = strtotime(gmdate('Y-m-d H:i:s',time() + -4*3600));
  $now = time();
  $ago = $now - $time;

  if($ago < 60) {
    $when = round($ago);
    $s = ($when == 1)?"second":"seconds";
    return "$when $s ago";
  } elseif($ago < 3600) {
    $when = round($ago / 60);
    $m = ($when == 1)?"minute":"minutes";
    return "$when $m ago";
  } elseif($ago >= 3600 && $ago < 86400) {
    $when = round($ago / 60 / 60);
    $h = ($when == 1)?"hour":"hours";
    return "$when $h ago";
  } elseif($ago >= 86400 && $ago < 2629743.83) {
    $when = round($ago / 60 / 60 / 24);
    $d = ($when == 1)?"day":"days";
    return "$when $d ago";
  } elseif($ago >= 2629743.83 && $ago < 31556926) {
    $when = round($ago / 60 / 60 / 24 / 30.4375);
    $m = ($when == 1)?"month":"months";
    return "$when $m ago";
  } else {
    $when = round($ago / 60 / 60 / 24 / 365);
    $y = ($when == 1)?"year":"years";
    return "$when $y ago";
  }
}

// 取得使用者所在時區
function get_tzonename($tz)
{
  // $tz = $timezone;

  // 根據使用者所再時區取得現在時間
  $today = gmdate('Y-m-d H:i:s',time() + $tz * 3600);

  // 轉換時區所要用的 sql timezone 參數
  $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."';";
  $tzone = runSQLALL($tzsql);

  if($tzone[0]==1) {
    $tzonename = $tzone[1]->name;
  } else {
    $tzonename = 'posix/Etc/GMT-8';
  }

  return $tzonename;
}

/**
 * 取得系統非root權限全部使用者資料
 *
 * @return array
 */
function get_all_member_data()
{
  $member_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.therole != 'R' AND root_member.status = '1' ORDER BY root_member.id;";
  $member_sql_result = runSQLall($member_sql);

  if ($member_sql_result[0] > 0) {
    $r = $member_sql_result;
  } else {
    $r = null;
  }

  return $r;
}

/**
 * 取得系統非root權限單一使用者資料
 *
 * @param [type] $who
 * @param [type] $select_by
 * @return void
 */
function get_one_member_data($who, $select_by)
{
  $where_col = $select_by == 'account' ? 'account' : 'id';
  $member_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.".$select_by." = '".$who."' AND root_member.therole != 'R';";
  $member_sql_result = runSQLall($member_sql);

  if ($member_sql_result[0] > 0) {
    $r = $member_sql_result;
  } else {
    $r = null;
  }

  return $r;
}

/**
 * 取得會員等級
 *
 * @param [type] $member_grade - 會員等級
 * @return array
 */
function get_member_grade($member_grade)
{
  // 取出會員等級設定
	$grade_sql = "SELECT * FROM root_member_grade WHERE status = 1 AND id = '".$member_grade."';";
	$grade_sql_result = runSQLALL($grade_sql);
  
  if ($grade_sql_result[0] > 0) {
    $r = $grade_sql_result[1];
  } else {
    echo "会员等级查询错误\n";
    die();
  }

  return $r;
}

/**
 * 取娛樂城列表
 *
 * @return array
 */
function get_casino_list()
{
  $sql = <<<SQL
  SELECT casinoid FROM casino_list ORDER BY casinoid;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    echo "娱乐城列表查询失败\n";
    die();
  }

  unset($result[0]);

  foreach ($result as $k => $v) {
    $casino_list[$k] = $v->casinoid;
  }

  return $casino_list;
}

/**
 * 查詢會員最後提款紀錄
 *
 * @param [type] $account - 會員帳號
 * @param [type] $tzonename - 會員所在時區的時間戳
 * @return array
 */
function get_last_withdraw_data($account, $tzonename)
{
  // 查詢會員最後提款紀錄
  $withdraw_sql = "SELECT *, to_char((processingtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as processing_time FROM root_withdraw_review WHERE account = '$account' AND processingtime IS NOT NULL AND status = '1' ORDER BY id DESC LIMIT 1";
  $withdraw_sql_result = runSQLall($withdraw_sql);

  if ($withdraw_sql_result[0] >= 1) {
    $withdraw_data = $withdraw_sql_result[1];
  } else {
    $withdraw_data = null;
  }

  return $withdraw_data;
}

/**
 * 取得最後提款後存款資訊
 *
 * @param [type] $account - 會員帳號
 * @param [type] $tz - 會員所在時區
 * @param [type] $tzonename - 會員所在時區的時間戳
 * @return array
 */
function get_withdraw_deposit_data($account, $tz, $tzonename)
{
  global $gtoken_cashier_account;

  $withdraw_result = get_last_withdraw_data($account, $tzonename);

  /*
  沒取得提款紀錄表該會員沒提過款
  則查詢至今為止的存款紀錄進行稽核
  */
  if ($withdraw_result != null) {
    $result['withdraw_data'] = $withdraw_result;
    $withdraw_after_deposit = "SELECT *, to_char((transaction_time AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as transactiontime, to_char((now() AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as nowtime FROM root_member_gtokenpassbook WHERE destination_transferaccount = '$gtoken_cashier_account' AND source_transferaccount = '$account' AND auditmode != 'freeaudit' AND transaction_time > '".$withdraw_result->processingtime."' ORDER BY id DESC;";
  } else {
    $result['withdraw_data'] = null;
    $today = gmdate('Y-m-d H:i:s',time() + $tz * 3600);
    $withdraw_after_deposit = "SELECT *, to_char((transaction_time AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as transactiontime, to_char((now() AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as nowtime FROM root_member_gtokenpassbook WHERE destination_transferaccount = '$gtoken_cashier_account' AND source_transferaccount = '$account' AND auditmode != 'freeaudit' AND transaction_time < '".$today."' ORDER BY id DESC;";
  }

  // 提款後存款資訊
  $withdraw_after_deposit_result = runSQLall($withdraw_after_deposit);

  if ($withdraw_after_deposit_result[0] >= 1) {
    unset($withdraw_after_deposit_result[0]);
    $result['withdraw_after_deposit_data'] = $withdraw_after_deposit_result;
  } else {
    $result['withdraw_after_deposit_data'] = null;
  }

  return $result;
}

function get_bettingrecords($casino_accounts, $receivetime_begin, $receivetime_end)
{
  $casino_acc_str = "('" . implode($casino_accounts, "', '") . "')";

  $bettingrecords_casino_category_sql =<<<SQL
  SELECT
    casino_account,
    casinoid as casino,
    SUM(betvalid) as bets
  FROM betrecordsremix
  WHERE casino_account IN {$casino_acc_str}
  AND receivetime BETWEEN '{$receivetime_begin}' AND '{$receivetime_end}'
  AND status = 1
  GROUP BY casino_account, casinoid;
SQL;

  $bettingrecords_result = runSQLall_betlog($bettingrecords_casino_category_sql);

  if (empty($bettingrecords_result[0])) {
    return false;
  }

  unset($bettingrecords_result[0]);

  return $bettingrecords_result;
}

function calculate_total_bet($records)
{
  $bet = 0;

  if (!empty($records)) {
    foreach($records as $record) {
      $bet += $record->bets;
    }
  }

  return $bet;
}

/**
 * 取得預算好的稽核資訊
 *
 * @param [type] $member_account
 * @param [type] $deposit_time
 * @param [type] $tzonename
 *
 * @return array
 */
function get_member_auditreport($member_account, $deposit_time, $tzonename)
{
	$debug = 0;
	$auditreport_sql = <<<SQL
  SELECT *,
        to_char((deposit_time1 AT TIME ZONE '{$tzonename}'),'YYYY-MM-DD HH24:MI:SS') AS deposit_time1, 
        to_char((deposit_time2 AT TIME ZONE '{$tzonename}'),'YYYY-MM-DD HH24:MI:SS') AS deposit_time2,
        to_char((updatetime AT TIME ZONE '{$tzonename}'),'YYYY-MM-DD HH24:MI:SS') AS updatetime
  FROM root_member_auditreport 
  WHERE member_account = '{$member_account}' 
  AND to_char((deposit_time1 AT TIME ZONE '{$tzonename}'),'YYYY-MM-DD HH24:MI:SS') IN {$deposit_time} 
  ORDER BY gtoken_id DESC;
SQL;

	$auditreport_sql_result = runSQLall($auditreport_sql, $debug);

	if (empty($auditreport_sql_result[0])) {
		return [];
	}

	unset($auditreport_sql_result[0]);

	return $auditreport_sql_result;
}

function get_auditreport_bytransactionid($acc, $transaction_id, $tzonename)
{
  $sql = <<<SQL
  SELECT *, 
          to_char((deposit_time1 AT TIME ZONE '{$tzonename}'),'YYYY-MM-DD HH24:MI:SS') AS deposit_time1, 
          to_char((deposit_time2 AT TIME ZONE '{$tzonename}'),'YYYY-MM-DD HH24:MI:SS') AS deposit_time2 
  FROM root_member_auditreport 
  WHERE member_account = '{$acc}' 
  AND transaction_id = '{$transaction_id}' 
  ORDER BY gtoken_id DESC;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  return $result;
}

/**
 * 組合新增或修改 root_member_auditreport 的 sql
 *
 * @param [type] $auditorial_details - 稽核資訊
 * @param [type] $action - 預設動作為 update
 * @return string
 */
function get_alter_auditreport_sql($auditorial_details, $action='update')
{
  if ($action == 'insert') {
    $sql = "
    INSERT INTO root_member_auditreport
    (
      member_account, deposit_time1, deposit_time2, audit_amount, afterdeposit_bet, 
      withdrawal_fee, offer_deduction_amount, updatetime, audit_method, gtoken_id, 
      audit_status, depositamount, transaction_id
    ) VALUES (
      '".$auditorial_details['member_account']."', '".$auditorial_details['deposit_time1']."', '".$auditorial_details['deposit_time2']."', '".$auditorial_details['audit_amount']."', '".$auditorial_details['afterdeposit_bet']."', 
      '".$auditorial_details['withdrawal_fee']."', '".$auditorial_details['offer_deduction_amount']."', now(), '".$auditorial_details['audit_method']."', '".$auditorial_details['gtoken_id']."', 
      '".$auditorial_details['audit_status']."', '".$auditorial_details['depositamount']."', '".$auditorial_details['transaction_id']."'
    )
    ;";
  } elseif ($action == 'update') {
    $sql = "
    UPDATE root_member_auditreport 
    SET deposit_time2 = '".$auditorial_details['deposit_time2']."', 
        afterdeposit_bet = '".$auditorial_details['afterdeposit_bet']."', 
        updatetime = now(), 
        audit_status = '".$auditorial_details['audit_status']."'
    WHERE member_account = '".$auditorial_details['member_account']."' 
    AND deposit_time1 = '".$auditorial_details['deposit_time1']."';
    ";
  }

  return $sql;
}

function get_all_acc($member_data)
{
  global $system_config;

  $acc_list = [
    'casino_acc' => '',
    'acc' => ''
  ];

  $casino_acc_list['casino_acc'] = [];

  $casino_accounts = json_decode($member_data->casino_accounts);

  foreach ($system_config['casino_list'] as $casino) {
    // $casino_acc = strtolower($casino).'_account';
    if (empty($casino_accounts->$casino)) {
      continue;
    }

    $casino_acc_list['casino_acc'][] = $casino_accounts->$casino->account;
  }

  $acc_list['casino_acc'] = array_unique($casino_acc_list['casino_acc']);

  $acc_list['acc'] = $member_data->account;

  return $acc_list;
}

function update_auditreport_transactionid($acc, $transaction_id)
{
  $sql = <<<SQL
  UPDATE root_member_auditreport
  SET transaction_id = '{$transaction_id}'
  WHERE member_account = '{$acc}'
  AND transaction_id IS NULL;
SQL;

  return runSQL($sql);
}

/**
 * 更新第一筆稽核資訊
 *
 * @param [type] $updateData
 * @return void
 */
function update_one_auditreport($updateData)
{
  $now = gmdate('Y-m-d H:i:s',time() + $updateData['timezone'] * 3600);
  // 單號
  $id = $updateData['depositData']['id'];
  // 存款金額
  $deposit_amount = $updateData['depositData']['deposit_amount'];
  // 稽核金額
  $auditmodeamount = $updateData['depositData']['auditmodeamount'];
  $auditmode = $updateData['depositData']['audit_method'];

  $transactiontime = $updateData['depositData']['transactiontime'];
  $transaction_id = $updateData['depositData']['transaction_id'];

  $total_bet = 0;
  if (!empty($updateData['acc_list']['casino_acc'])) {
    $records = get_bettingrecords($updateData['acc_list']['casino_acc'], $transactiontime, $now);
    $total_bet = calculate_total_bet($records);
  }

  // $total_bet = ($updateData['isInsert']) ? $total_bet : ($total_bet + $updateData['auditreport_data']->afterdeposit_bet);

  $is_audit = $total_bet >= $auditmodeamount ? 1 : 2;

  $total_bet = $total_bet;
  $auditorial_details['deposit_time1'] = $transactiontime;
  $action = 'insert';

  if (!$updateData['isInsert']) {
    $total_bet = ($total_bet + $updateData['auditreport_data']->afterdeposit_bet);
    $auditorial_details['deposit_time1'] = $updateData['auditreport_data']->deposit_time1;
    $action = 'update';
  }
  
  $auditorial_details['gtoken_id'] = $id;
  $auditorial_details['member_account'] = $updateData['acc_list']['acc'];
  // $auditorial_details['deposit_time1'] = ($updateData['isInsert']) ? $transactiontime : $updateData['auditreport_data']->deposit_time1;
  $auditorial_details['deposit_time2'] = $now;
  $auditorial_details['audit_amount'] = $auditmodeamount;
  $auditorial_details['afterdeposit_bet'] = $total_bet;
  $auditorial_details['audit_method'] = $auditmode;
  $auditorial_details['audit_status'] = $is_audit;
  $auditorial_details['depositamount'] = $deposit_amount;
  $auditorial_details['transaction_id'] = $transaction_id;

  // $auditreport_data = get_member_auditreport($auditorial_details['member_account'], $auditorial_details['deposit_time1'], $tzonename);
  // $action = ($auditreport_data == null) ? 'insert' : 'update';
  // $action = ($updateData['isInsert']) ? 'insert' : 'update';

  $auditorial_details['isclear'] = (!$updateData['auditreport_data']) ? 0 :  $updateData['auditreport_data']->isclear;

  if ($auditmode == 'shippingaudit') {
    $offer_deduction = $deposit_amount;
    $auditorial_details['withdrawal_fee'] = 0;
    $auditorial_details['offer_deduction_amount'] = ($action == 'update') ? round($updateData['auditreport_data']->offer_deduction_amount, 2) : round($deposit_amount, 2);
  } else {
    $administrative_amount = $auditmodeamount;
    $auditorial_details['withdrawal_fee'] = ($action == 'update') ? round($updateData['auditreport_data']->withdrawal_fee, 2) : round($auditmodeamount * ($updateData['administrative_cost_ratio'] / 100),2);
    $auditorial_details['offer_deduction_amount'] = 0;
  }

  $sql = get_alter_auditreport_sql($auditorial_details, $action);

  $result['detail'] = $auditorial_details;
  $result['sql'] = $sql;

  return $result;
}

/**
 * 以root_member_auditreport結果進行稽核計算
 *
 * @param [type] $member_data - 會員資料
 * @return array
 */
function get_auditorial_data($member_data)
{
  $total_wager = '0';
  $administrative_amount = '0';
  $offer_deduction = '0';

  $transaction_sql = '';

  $auditorial_details = null;

  $tzonename = get_tzonename($member_data->timezone);

  // 存提款資訊
  $withdraw_deposit_data = get_withdraw_deposit_data($member_data->account, $member_data->timezone, $tzonename);
  $withdraw_after_deposit_data = $withdraw_deposit_data['withdraw_after_deposit_data'];
  $withdraw_data = $withdraw_deposit_data['withdraw_data'];

  $member_grade = get_member_grade($member_data->grade);

  // 有取到資料表示在最後一次提款後有存款, 需要被稽核
  // 或至今為止有存款紀錄需要被稽核
  if ($withdraw_after_deposit_data != null) {

    $acc_list = get_all_acc($member_data);

    foreach ($withdraw_after_deposit_data as $key => $deposit_data) {
      $transactionTimeList[] = $deposit_data->transactiontime;
      $depositData[] = [
        'id' => $deposit_data->id,
        'auditmodeamount' => $deposit_data->auditmodeamount,
        'audit_method' => $deposit_data->auditmode,
        'transactiontime' => $deposit_data->transactiontime,
        'deposit_amount' => $deposit_data->deposit,
        'deposit_balance' => $deposit_data->balance,
        'transaction_id' => $deposit_data->transaction_id
      ];
    }

    $transactionTimeStr = "('" . implode($transactionTimeList, "', '") . "')";
    $data = get_member_auditreport($member_data->account, $transactionTimeStr, $tzonename);

	  $auditreportData = [];
    foreach ($data as $k => $v) {
      $auditreportData[$v->gtoken_id] = $v;
    }

    $updateData = [
      'acc_list' => $acc_list,
      'timezone' => $member_data->timezone,
      'tzonename' => $tzonename,
      'administrative_cost_ratio' => $member_grade->administrative_cost_ratio
    ];

    foreach ($depositData as $k => $v) {
      $wager_balance = '0'; 

      $deposit_amount = $v['deposit_amount'];
      $deposit_balance = $v['deposit_balance'];

      // $auditreport_data = get_member_auditreport($member_data->account, $deposit_data->transactiontime, $tzonename);

      $auditreport_data = (array_key_exists($v['id'], $auditreportData)) ? $auditreportData[$v['id']] : [];

      if ($k == 0 && $auditreport_data) {
        $updateData['auditreport_data'] = $auditreport_data;
        $updateData['depositData'] = $v;
        $updateData['depositData']['transactiontime'] = $auditreport_data->updatetime;
        $updateData['isInsert'] = (!$auditreport_data) ? true : false;
        $update_result = update_one_auditreport($updateData);

        $auditreport_data = (object)$update_result['detail'];
        $transaction_sql .= $update_result['sql'];
      } elseif (!$auditreport_data) {
        $updateData['auditreport_data'] = $auditreport_data;
        $updateData['depositData'] = $v;
        // $updateData['depositData']['transactiontime'] = $auditreport_data->updatetime;
        $updateData['isInsert'] = (!$auditreport_data) ? true : false;
        $update_result = update_one_auditreport($updateData);

        $auditreport_data = (object)$update_result['detail'];
        $transaction_sql .= $update_result['sql'];
      }

      $gtoken_id = $auditreport_data->gtoken_id;
      $member_account = $auditreport_data->member_account;
      $deposit_time1 = $auditreport_data->deposit_time1;
      $deposit_time2 = $auditreport_data->deposit_time2;
      $audit_amount = $auditreport_data->audit_amount;
      $afterdeposit_bet = $auditreport_data->afterdeposit_bet;
      $withdrawal_fee = $auditreport_data->withdrawal_fee;
      $offer_deduction_amount = $auditreport_data->offer_deduction_amount;
      $audit_method = $auditreport_data->audit_method;
      $audit_status = $auditreport_data->audit_status;
      $depositamount = $auditreport_data->depositamount;

      if ($afterdeposit_bet >= $audit_amount) {
        // 存款後投注量 - 稽核金額
        $audit_balance = round($afterdeposit_bet - $audit_amount,2);
        // 紀錄超過的金額
        $total_wager += $audit_balance;
        // 是否通過稽核
        $is_audit = 1;
      } elseif (($total_wager + $afterdeposit_bet) >= $audit_amount) {
          // 將上一筆存款後投注量 - 稽核金額的餘額加上該筆存款後投注量
          $total_wager += $afterdeposit_bet;
          $audit_balance = round($total_wager - $audit_amount,2);
          $total_wager = $audit_balance;
          $is_audit = 1;
      } else {
        if ($total_wager < $audit_amount) {
          $wager_balance = $total_wager;
        }

        $total_wager = '0';

        $is_audit = ($auditreport_data->isclear) ? 1 : 0;

        if ($audit_method == 'shippingaudit') {
          $offer_deduction += ($auditreport_data->isclear) ? 0 : $offer_deduction_amount;
        } else {
          $administrative_amount += ($auditreport_data->isclear) ? 0 : $withdrawal_fee;
        }
      }

      $auditorial_details[$gtoken_id] = [
        'gtoken_id' => $gtoken_id,
        'member_account' => $member_account,
        'deposit_time1' => $deposit_time1,
        'deposit_time2' => $deposit_time2,
        'audit_amount' => $audit_amount,
        'afterdeposit_bet' => round(($afterdeposit_bet + $wager_balance), 2),
        'offer_deduction_amount' => $offer_deduction_amount,
        'withdrawal_fee' => round($withdrawal_fee, 2),
        'audit_method' => $audit_method,
        'audit_status' => $is_audit,
        'deposit_amount' => $depositamount,
        'deposit_balance' => $deposit_balance
      ];
    }

    if (!empty($transaction_sql)) {
      $transaction_result = runSQLtransactions($transaction_sql);
      // $transaction_result = true;
      if (!$transaction_result) {
        die('稽核资讯更新失败');
      }
    }
  }

  $result['total_withdrawal_fee'] = $administrative_amount;
  $result['total_offer_deduction_amount'] = round($offer_deduction, 2);

  $result['withdraw_data'] = $withdraw_data;
  $result['auditorial_details'] = $auditorial_details;

  return $result;
}

function get_old_auditorial_data($m_data, $transaction_id)
{
  $auditorial_details = [];

  $tzonename = get_tzonename($m_data->timezone);

  $old_auditorial_data = get_auditreport_bytransactionid($m_data->account, $transaction_id, $tzonename);
  // $passbook_data = get_token_passbook($transaction_id);
  // var_dump($old_auditorial_data);

  if (!$old_auditorial_data) {
    return false;
  }

  foreach ($old_auditorial_data as $k => $v) {
    $auditorial_details[$k]['gtoken_id'] = $v->gtoken_id;
    $auditorial_details[$k]['member_account'] = $v->member_account;
    $auditorial_details[$k]['deposit_time1'] = $v->deposit_time1;
    $auditorial_details[$k]['deposit_time2'] = $v->deposit_time2;
    $auditorial_details[$k]['audit_amount'] = $v->audit_amount;
    $auditorial_details[$k]['afterdeposit_bet'] = $v->afterdeposit_bet;
    $auditorial_details[$k]['offer_deduction_amount'] = $v->offer_deduction_amount;
    $auditorial_details[$k]['withdrawal_fee'] = $v->withdrawal_fee;
    $auditorial_details[$k]['audit_method'] = $v->audit_method;
    $auditorial_details[$k]['audit_status'] = $v->audit_status;

    $auditorial_details[$k]['deposit_amount'] = $v->depositamount;
    // $auditorial_details[$key]['deposit_balance'] = $deposit_balance;
  }

  return $auditorial_details;
}

/**
 * 計算即時稽核相關資料
 * 
 * @param [type] $member_data - 會員資料及錢包資訊
 * @return array
 */
function get_auditreport_calculate_sql($member_data)
{
  $total_wager = '0';
  $administrative_amount = '0';
  $offer_deduction = '0';

  $auditorial_data = '';
  $transaction_sql = '';

  $auditorial_details = null;

  $tzonename = get_tzonename($member_data->timezone);

  // 存提款資訊
  $withdraw_deposit_data = get_withdraw_deposit_data($member_data->account, $member_data->timezone, $tzonename);
  $withdraw_after_deposit_data = $withdraw_deposit_data['withdraw_after_deposit_data'];
  $withdraw_data = $withdraw_deposit_data['withdraw_data'];

  $member_grade = get_member_grade($member_data->grade);

  // 有取到資料表示在最後一次提款後有存款, 需要被稽核
  // 或至今為止有存款紀錄需要被稽核
  if ($withdraw_after_deposit_data != null) {

    $acc_list = get_all_acc($member_data);

    foreach ($withdraw_after_deposit_data as $key => $deposit_data) {
      $transactionTimeList[] = $deposit_data->transactiontime;
      $depositData[$deposit_data->transactiontime] = [
        'id' => $deposit_data->id,
        'auditmodeamount' => $deposit_data->auditmodeamount,
        'audit_method' => $deposit_data->auditmode,
        'transactiontime' => $deposit_data->transactiontime,
        'deposit_amount' => $deposit_data->deposit,
        'deposit_balance' => $deposit_data->balance,
        'transaction_id' => $deposit_data->transaction_id
      ];
    }

    $transactionTimeStr = "('" . implode($transactionTimeList, "', '") . "')";
    $data = get_member_auditreport($member_data->account, $transactionTimeStr, $tzonename);

    foreach ($data as $k => $v) {
      $auditreportData[$v->gtoken_id] = $v;
    }

    $updateData = [
      'acc_list' => $acc_list,
      'timezone' => $member_data->timezone,
      'tzonename' => $tzonename,
      'administrative_cost_ratio' => $member_grade->administrative_cost_ratio
    ];

    foreach ($depositData as $k => $v) {
      $wager_balance = '0';

      $deposit_amount = $v['deposit_amount'];
      $deposit_balance = $v['deposit_balance'];

      $auditreport_data = (array_key_exists($v['id'], $auditreportData)) ? $auditreportData[$v['id']] : false;

      if ($k == 0 && $auditreport_data) {
        $updateData['auditreport_data'] = $auditreport_data;
        $updateData['depositData'] = $v;
        $updateData['depositData']['transactiontime'] = $auditreport_data->updatetime;
        $updateData['isInsert'] = (!$auditreport_data) ? true : false;
        $update_result = update_one_auditreport($updateData);

        $auditreport_data = (object)$update_result['detail'];
        $transaction_sql .= $update_result['sql'];
      } elseif (!$auditreport_data) {
        $updateData['auditreport_data'] = $auditreport_data;
        $updateData['depositData'] = $v;
        // $updateData['depositData']['transactiontime'] = $auditreport_data->updatetime;
        $updateData['isInsert'] = (!$auditreport_data) ? true : false;
        $update_result = update_one_auditreport($updateData);

        $auditreport_data = (object)$update_result['detail'];
        $transaction_sql .= $update_result['sql'];
      }

      $gtoken_id = $auditreport_data->gtoken_id;
      $member_account = $auditreport_data->member_account;
      $deposit_time1 = $auditreport_data->deposit_time1;
      $deposit_time2 = $auditreport_data->deposit_time2;
      $audit_amount = $auditreport_data->audit_amount;
      $afterdeposit_bet = $auditreport_data->afterdeposit_bet;
      $withdrawal_fee = $auditreport_data->withdrawal_fee;
      $offer_deduction_amount = $auditreport_data->offer_deduction_amount;
      $audit_method = $auditreport_data->audit_method;
      $audit_status = $auditreport_data->audit_status;
      $depositamount = $auditreport_data->depositamount;

      if ($afterdeposit_bet >= $audit_amount) {
        // 存款後投注量 - 稽核金額
        $audit_balance = round($afterdeposit_bet - $audit_amount,2);
        // 紀錄超過的金額
        $total_wager += $audit_balance;
        // 是否通過稽核
        $is_audit = 1;
      } elseif (($total_wager + $afterdeposit_bet) >= $audit_amount) {
          // 將上一筆存款後投注量 - 稽核金額的餘額加上該筆存款後投注量
          $total_wager += $afterdeposit_bet;
          $audit_balance = round($total_wager - $audit_amount,2);
          $total_wager = $audit_balance;
          $is_audit = 1;
      } else {
        if ($total_wager < $audit_amount) {
          $wager_balance = $total_wager;
        }

        $total_wager = '0';

        $is_audit = ($auditreport_data->isclear) ? 1 : 0;

        if ($audit_method == 'shippingaudit') {
          $offer_deduction += ($auditreport_data->isclear) ? 0 : $offer_deduction_amount;
        } else {
          $administrative_amount += ($auditreport_data->isclear) ? 0 : $withdrawal_fee;
        }
      }

      $auditorial_details[$gtoken_id] = [
        'gtoken_id' => $gtoken_id,
        'member_account' => $member_account,
        'deposit_time1' => $deposit_time1,
        'deposit_time2' => $deposit_time2,
        'audit_amount' => $audit_amount,
        'afterdeposit_bet' => round(($afterdeposit_bet + $wager_balance), 2),
        'offer_deduction_amount' => $offer_deduction_amount,
        'withdrawal_fee' => round($withdrawal_fee, 2),
        'audit_method' => $audit_method,
        'audit_status' => $is_audit,
        'deposit_amount' => $depositamount,
        'deposit_balance' => $deposit_balance
      ];

      // $transaction_sql .= ($auditreport_data == null || $key == 1) ? $update_result['sql'] : get_alter_auditreport_sql($auditorial_details[$key]);
      $auditorial_data['count'] = $key;
    }

    $auditorial_data['sql'] = $transaction_sql;

  }

  return $auditorial_data;
}
?>