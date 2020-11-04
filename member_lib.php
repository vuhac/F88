<?php

function get_memberdata_byid($id, $skip_status = false)
{
  global $tr;

  $sql = <<<SQL
  SELECT *
  FROM root_member
  JOIN root_member_wallets
  ON root_member.id=root_member_wallets.id
  WHERE root_member.id = '$id'
SQL;

  if(!$skip_status) $sql .="  AND root_member.status = '1'";

  $result = runSQLALL($sql);

	if (empty($result[0])) {
		$error_msg = $tr['Member information query error'];
		return array('status' => false, 'result' => $error_msg);
	}
  $casino_info = json_decode($result[1]->casino_accounts,'true');
  if(count($casino_info) >= 1){
    foreach($casino_info as $cid => $cinfo){
      $cid = strtolower($cid);
      $cida = $cid.'_account';
      $cidp = $cid.'_password';
      $cidb = $cid.'_balance';
      $result[1]->$cida = $cinfo['account'];
      $result[1]->$cidp = $cinfo['password'];
      $result[1]->$cidb = $cinfo['balance'];
    }
  }

	return array('status' => true, 'result' => $result[1]);
}

function get_memberdata_byaccount($acc)
{
  global $tr;

  $sql = <<<SQL
  SELECT *
  FROM root_member
  JOIN root_member_wallets
  ON root_member.id=root_member_wallets.id
  WHERE root_member.account = '$acc'
  AND root_member.status = '1'
SQL;

  $result = runSQLALL($sql);

	if (empty($result[0])) {
		$error_msg = $tr['Member information query error'];
		return array('status' => false, 'result' => $error_msg);
	}
  $casino_info = json_decode($result[1]->casino_accounts,'true');
  if(count($casino_info) >= 1){
    foreach($casino_info as $cid => $cinfo){
      $cid = strtolower($cid);
      $cida = $cid.'_account';
      $cidp = $cid.'_password';
      $cidb = $cid.'_balance';
      $result[1]->$cida = $cinfo['account'];
      $result[1]->$cidp = $cinfo['password'];
      $result[1]->$cidb = $cinfo['balance'];
    }
  }

	return array('status' => true, 'result' => $result[1]);
}

// check_member_permissions
function check_member_therole($m)
{
  global $config;

	if ($_SESSION['agent']->therole == 'A' && $m->therole == 'R') {
		$error_msg = '權限不足，無法使用此功能。';
		return array('status' => false, 'result' => $error_msg);
  }

  if ($_SESSION['agent']->account != $config['system_company_account'] && $m->account == $config['system_company_account']) {
    $error_msg = '權限不足，無法使用此功能。';
		return array('status' => false, 'result' => $error_msg);
  }

	if ($m->therole == 'R' && $m->account != $_SESSION['agent']->account) {
		$error_msg = '不可使用其他管理帳號資料，請重新查詢。';
		return array('status' => false, 'result' => $error_msg);
	}

	return array('status' => true, 'result' => 'OK');
}

function check_searchid($get_value)
{
  if(empty($get_value)) {
    return false;
  }

  $id = filter_var($get_value,FILTER_SANITIZE_STRING);

  if(empty($id)) {
    return false;
  }

  return true;
}


/**
 *  依據會員 ID 更新會員帳號狀態
 *
 * @param mixed $id 會員 ID
 * @param mixed $status 帳號狀態
 * @param int $debug 除錯模式，0 為非除錯模式
 *
 * @return int SQL執行狀態
 */
function updateMemberStatusById($id, $status, $debug = 0)
{
	$sql = 'UPDATE root_member SET "status" = \''. $status .'\' WHERE "id" = '. $id .';';
	return runSQL($sql, $debug);
}