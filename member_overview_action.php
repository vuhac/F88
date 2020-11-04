<?php
// ----------------------------------------------------------------------------
// Features:	管理端的會員查詢
// File Name:	member_action.php
// Author:		Barkley
// Related:   對應 member.php
// Log:
// 2019.03.27 新增匯出功能 Letter
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

require_once dirname(__FILE__) . "/lib_proccessing.php";

require_once dirname(__FILE__) . "/lib_file.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


if (isset($_GET['a'])) {
	$action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{//$tr['Illegal test'] = '(x)不合法的測試。';
	die($tr['Illegal test']);
}

$debug = 0;
// var_dump($_SESSION);
// var_dump($_POST);die();
// var_dump($_GET);die();

// 會員列表
function memberlist(){
	$memberlist_sql = "SELECT m.id,m.account,m.therole,m.parent_id,m.enrollmentdate,m.grade,m.favorablerule,m.commissionrule,m.status,s.gcash_balance,s.gtoken_balance FROM root_member m INNER JOIN root_member_wallets s on m.id = s.id WHERE m.therole != 'R' ORDER BY m.enrollmentdate DESC LIMIT 100";
	$memberlist_sql_result = runSQLall($memberlist_sql);
	for ($x = 1; $x <= $memberlist_sql_result[0]; $x++) {
		$member_list_array[] = $memberlist_sql_result[$x];   
	}
	//取得代理商帳號
	for ($x = 1; $x <= $memberlist_sql_result[0]; $x++) {
		$parent_id = $member_list_array[$x-1] -> parent_id;
		//讓帳號與ID存在 順便可以驗證
		$member_list_array[$x-1] -> parent_account = memberparent($parent_id);		
	}	
	//+8時區轉換
	for ($x = 1; $x <= $memberlist_sql_result[0]; $x++) {
		$time_id = $member_list_array[$x-1] -> id;	
		$member_list_array[$x-1] -> enrollmentdate = enrollmentdatetime($time_id);
	}
	$member_list_json = json_encode($member_list_array);
	echo '{"data": '.$member_list_json.'}';
}
// +8時區轉換
function enrollmentdatetime($id){
	$memberentime_sql = "SELECT enrollmentdate FROM root_member WHERE id ='$id';";
	$memberentime_sql_result = runSQLall($memberentime_sql);
	$memberentime = gmdate('Y-m-d H:i',strtotime($memberentime_sql_result[1] -> enrollmentdate) + -4*3600);
	return $memberentime;
}

//找出代理商帳號
function memberparent($id){
	$memberparent_sql = "SELECT account FROM root_member WHERE id ='$id';";
	$memberparent_sql_result = runSQLall($memberparent_sql);
	$member_parent_json = json_encode($memberparent_sql_result);
	return $memberparent_sql_result[1] -> account;
}

//更改會員狀態
function update_setting($id, $column_name, $column_value, $success_msg, $failed_msg)
{
	global $tr;
	if ($column_value != '' AND $id != '') {
		$update_sql = "UPDATE root_member SET ".$column_name." = '" . $column_value . "' WHERE id = '" . $id . "';";
		$update_sql_result = runSQL($update_sql);

		if ($update_sql_result == 1) {
			// 更新成功
			$logger = $success_msg;
			echo '<script>alert("' . $logger . '");location.reload();</script>';
		} else {
			// 更新失敗
			$logger = $failed_msg;
			echo '<script>alert("' . $logger . '");location.reload();</script>';
		}

	} else {
		// 錯誤嘗試
		// 因送過來的值不合法 $tr['Wrong attempt'] = '(x)錯誤的嘗試。';
		$logger = $tr['Wrong attempt'];
		echo '<script>alert("' . $logger . '");;</script>';
	}
}

if (isset($_GET['a']) AND $_GET['a'] == 'memberlist') {
	memberlist();
}else if ($action == 'change_member_status' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
	//變更會員狀態
	$user_id = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
	$status_name = filter_var($_POST['status_name'], FILTER_SANITIZE_STRING);
	if ($status_name == $tr['Wallet Disable']) {
		$status = '0';
	} elseif ($status_name == $tr['Wallet Valid']) {
		$status = '1';
	} elseif ($status_name == $tr['account freeze']) {
		$status = '2';
	}

	if ($status != '') {
		$member_sql = "SELECT * FROM root_member WHERE id = '".$user_id."';";
		$member_sql_result = runSQLall($member_sql);

		if ($member_sql_result[0] == 1) {
			$column_name = 'status';
			// $tr['Member account status is modified'] = '會員帳號狀態修改完成。'; $tr['Member account status modification failed'] = '會員帳號狀態修改失敗。';
			$success_msg = $tr['Member account status is modified'];
			$failed_msg = $tr['Member account status modification failed'];

			update_setting($user_id, $column_name, $status, $success_msg, $failed_msg);
		} else {
			// $tr['Member information query error'] = '會員資料查詢錯誤。';
			$logger = $tr['Member information query error'];
			echo '<script>alert("' . $logger . '");</script>';
		}
	} else {
		// 錯誤嘗試 $tr['Wrong attempt'] = '(x)錯誤的嘗試。';
		$logger = $tr['Wrong attempt'];
		echo '<script>alert("' . $logger . '");location.reload();</script>';
	}
} else if ($action == 'change_mamber_grade' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
	//会员等级管理
	$user_id = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
	$grade_name = filter_var($_POST['grade_name'], FILTER_SANITIZE_STRING);

	if ($grade_name != '') {

		$grade_sql = "SELECT * FROM root_member_grade WHERE gradename = '" . $grade_name . "';";
		$grade_sql_result = runSQLALL($grade_sql);

		if ($grade_sql_result[0] == 1) {
			$member_sql = "SELECT * FROM root_member WHERE id = '".$user_id."';";
			$member_sql_result = runSQLall($member_sql);

			if ($member_sql_result[0] == 1) {
				$column_name = 'grade';
				// $tr['Member account level is modified'] = '會員帳號等級修改完成。'; $tr['Member account level modification failed'] = '會員帳號等級修改失敗。';
				$success_msg = $tr['Member account level is modified'];
				$failed_msg = $tr['Member account level modification failed'];

				update_setting($user_id, $column_name, $grade_sql_result[1]->id, $success_msg, $failed_msg);
			} else {
				// $tr['Member information query error'] = '會員資料查詢錯誤。';
				$logger = $tr['Member information query error'];
				echo '<script>alert("' . $logger . '");</script>';
			}
		} else {
			// $tr['there is no membership level information'] = '查無此會員等級資訊。';
			$logger = $tr['there is no membership level information'];
			echo '<script>alert("' . $logger . '");</script>';
		}

	} else {
		// 錯誤嘗試 $tr['Wrong attempt'] = '(x)錯誤的嘗試。';
		$logger = $tr['Wrong attempt'];
		echo '<script>alert("' . $logger . '");location.reload();</script>';
	}	
}else if ($action == 'change_mamber_preferential_name' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
	//反水等級修改
	$user_id = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
	$preferentia_name = filter_var($_POST['preferential_name'], FILTER_SANITIZE_STRING);

	if ($preferentia_name != '') {
		$member_sql = "SELECT * FROM root_member WHERE id = '".$user_id."';";
		$member_sql_result = runSQLall($member_sql);

		if ($member_sql_result[0] == 1) {
			$column_name = 'favorablerule';
			// $tr['Member account bouns level is modified'] = '會員帳號反水等級修改完成。'; $tr['Member account bouns level changes failed'] = '會員帳號反水等級修改失敗。';
			$success_msg = $tr['Member account bouns level is modified'];
			$failed_msg = $tr['Member account bouns level changes failed'];

			update_setting($user_id, $column_name, $preferentia_name, $success_msg, $failed_msg);
		} else {
			// $tr['Member information query error'] = '會員資料查詢錯誤。';
			$logger = $tr['Member information query error'];
			echo '<script>alert("' . $logger . '");</script>';
		}
	} else {
		// 錯誤嘗試 $tr['Wrong attempt'] = '(x)錯誤的嘗試。';
		$logger = $tr['Wrong attempt'];
		echo '<script>alert("' . $logger . '");location.reload();</script>';
	}
}else if ($action == 'change_mamber_commission_name' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
//佣金設定
	$user_id = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
	$commission_name = filter_var($_POST['commission_name'], FILTER_SANITIZE_STRING);

	if ($commission_name != '') {
		$member_sql = "SELECT * FROM root_member WHERE id = '".$user_id."';";
		$member_sql_result = runSQLall($member_sql);

		if ($member_sql_result[0] == 1) {
			$column_name = 'commissionrule';
			// $tr['Member account commission set to complete the modification'] = '會員帳號佣金設定修改完成。';$tr['Member account commission set to amend the failure'] = '會員帳號佣金設定修改失敗。';
			$success_msg = $tr['Member account commission set to complete the modification'];
			$failed_msg = $tr['Member account commission set to amend the failure'];

			update_setting($user_id, $column_name, $commission_name, $success_msg, $failed_msg);
		} else {
			// $tr['Member information query error'] = '會員資料查詢錯誤。';
			$logger = $tr['Member information query error'];
			echo '<script>alert("' . $logger . '");</script>';
		}
	} else {
		// 錯誤嘗試 $tr['Wrong attempt'] = '(x)錯誤的嘗試。';
		$logger = $tr['Wrong attempt'];
		echo '<script>alert("' . $logger . '");location.reload();</script>';
	}
}
?>
