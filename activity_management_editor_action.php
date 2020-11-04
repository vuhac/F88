<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 活動優惠管理動作處理
// File Name:	activity_management_editor_action.php
// Author:    Mavis
// Related:
// DB Table: root_promotion_activity,root_promotion_code
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 文字檔
require_once dirname(__FILE__) ."/activity_management_editor_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// id
$id = (isset($_POST['id'])) ?	filter_var($_POST['id'],FILTER_SANITIZE_NUMBER_INT) : '';

// 活動名稱
$name = (isset($_POST['name']))?	filter_var($_POST['name'],FILTER_SANITIZE_STRING) : '';

// 活動代碼(4碼)
$activity_id = (isset($_POST['activity_id']) && $_POST['activity_id'] != NULL)? filter_var($_POST['activity_id'],FILTER_SANITIZE_STRING) : '';

//活動說明
$desc = (isset($_POST['desc']))? filter_var($_POST['desc'],FILTER_SANITIZE_STRING) : '';

// 活動開始時間，沒選預設現在，有選填它的時間
$sdate = (isset($_POST['sdate']) && $_POST['sdate'] != '')? filter_var($_POST['sdate'],FILTER_SANITIZE_STRING) : gmdate('Y/m/d',time() + $_SESSION['agent']->timezone * 3600);

// 活動結束時間，結束時間沒選，預設1個月後的23:59，有填它的時間
$edate = (isset($_POST['edate']) && $_POST['edate'] != '')? filter_var($_POST['edate'],FILTER_SANITIZE_STRING) : date('Y/m/d', strtotime("$sdate +3 month")). ' 23:59:00';

// 優惠碼數量
$promo = (isset($_POST['promo_number']))? filter_var($_POST['promo_number'],FILTER_SANITIZE_NUMBER_INT) : '';

// 每筆優惠碼的獎金
$activity_promo_money = (isset($_POST['money']))? filter_var($_POST['money'],FILTER_VALIDATE_FLOAT) : '';

// 獎金類別(預設 遊戲幣)
$activity_coin_classification = (isset($_POST['classification']))? filter_var($_POST['classification'],FILTER_SANITIZE_STRING) : '';

// 網域
$select_showdomain = (isset($_POST['domain']))? filter_var($_POST['domain'],FILTER_SANITIZE_STRING) : '';

// 子網域
$sub = (isset($_POST['sub']))? filter_var($_POST['sub'],FILTER_SANITIZE_STRING) : '';

// 稽核方式
$select_audit = (isset($_POST['select_audit']))? filter_var($_POST['select_audit'],FILTER_SANITIZE_STRING) : '';

// 稽核類別(稽核倍數,稽核金額)
// 如果稽核方式是免稽核，稽核類別=freeaudit
$audit_type = (isset($_POST['audit_type']) && $_POST['select_audit'] != 'freeaudit')? filter_var($_POST['audit_type'],FILTER_SANITIZE_STRING) : $audit_type = 'freeaudit';

// 稽核的值
$audit = (isset($_POST['audit_value']))? filter_var($_POST['audit_value'],FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION) : '0';

// 活動是否啟用
$activity_status_open = (isset($_POST['activity_status_open']))? filter_var($_POST['activity_status_open'],FILTER_SANITIZE_NUMBER_INT) : '';

// 備註
$note = (isset($_POST['note']))? filter_var($_POST['note'],FILTER_SANITIZE_STRING) : '';

// var_dump($_POST);die();
// --------------------------
// 條件 jsonb
// --------------------------
if(isset($_POST['others']) || isset($_POST['account_type'])){
	// 使用者限制 checkbox 預設勾選不能改

	// 帳戶類型
	$member_agent_reqs = filter_var($_POST['account_type'],FILTER_SANITIZE_STRING);

	$other_requirements = filter_var_array($_POST['others'],FILTER_SANITIZE_STRING);
	//yaoyuan
	foreach($other_requirements as $value){
		$other_req[]= empty($value)?'0':$value;
	}
	$tojsondata['user_therole']=$member_agent_reqs;
	$tojsondata['reg_member_time']=$other_req[0];
	$tojsondata['desposit_amount']=$other_req[1];
	$tojsondata['betting_amount']=$other_req[2];

	$all_encode = json_encode($tojsondata);

}
// var_dump($_POST);die();
if(isset($_GET['a']) && $_SESSION['agent']->therole == 'R'){
	$action = filter_var($_GET['a'],FILTER_SANITIZE_STRING);
} else {
	die('(x)不合法的測試');
}


// 網域選單
if($action == 'select_subdomain'){

	$return_str = '';

	// 子網域
	foreach($search_domainname[$sub] as $option_value){
		$return_str.='
			<option class="sub_domain" id="'.$option_value.'" value="'.$option_value.'">'.$option_value.'</option>';
	}
	echo $return_str;

}

// 新增或編輯
if($action == 'edit_activity' && $_SESSION['agent']->therole == 'R'){
	// 活動
	if($name != NULL && $promo != NULL && $activity_promo_money != NULL AND $sub != NULL){
		if($sdate >= $edate){
			$logger = $tr['Start time can not be greater than the end time, please select the time again'];
			echo "<script>alert('".$logger."');</script>";
		}else{
			if($id == ''){
				// 新增活動
				$select_sql=<<<SQL
				SELECT * FROM root_promotion_activity
				WHERE id = NULL
				AND activity_status != '2'
SQL;
			} else{
				// 編輯活動
				$select_sql=<<<SQL
				SELECT * FROM root_promotion_activity
				WHERE id = '{$id}'
				AND activity_status != '2'
SQL;
			}
			// var_dump($select_sql);die();
			$select_result = runSQL($select_sql);

			if($select_result == 0){
				// 活動代碼
				$act_id = get_act_id();

				// 新增
				$sql =<<<SQL
					INSERT INTO root_promotion_activity
					(activity_id,activity_name,activity_status,activity_desc,effecttime,endtime,activity_domain,note,bouns_number,bouns_amount,bouns_classification,bonus_auditclass,bonus_audit,promocode_req,activity_subdomain,operator,audit_classification)
					VALUES('{$act_id}','{$name}','{$activity_status_open}','{$desc}','{$sdate}','{$edate}','{$select_showdomain}','{$note}','{$promo}','{$activity_promo_money}','{$activity_coin_classification}','{$select_audit}','{$audit}','{$all_encode}','{$sub}','{$_SESSION['agent']->account}','{$audit_type}')
SQL;

				$sql_result = runSQL($sql);
				// 優惠碼
				$serial = random_serial($promo,$id);
				$logger = $tr['The new event was successful.'];


			} else{
				// 更新
				$sql =<<<SQL
				UPDATE root_promotion_activity
					SET activity_name = '{$name}',
					activity_status = '{$activity_status_open}',
					activity_desc = '{$desc}',
					effecttime = '{$sdate}',
					endtime = '{$edate}',
					activity_domain= '{$select_showdomain}',
					note= '{$note}',
					bouns_number = '{$promo}',
					bouns_amount = '{$activity_promo_money}',
					bouns_classification = '{$activity_coin_classification}',
					bonus_auditclass = '{$select_audit}',
					bonus_audit = '{$audit}',
					promocode_req = '{$all_encode}',
					activity_subdomain = '{$sub}',
					audit_classification = '{$audit_type}'
				WHERE id = '{$id}'
SQL;

				$sql_result = runSQL($sql);

				$logger = $tr['Successful editing activity'];

			}
			echo "<script>alert('".$logger."');location.href='activity_management.php';</script>";
		}
	} else{
		$logger = $tr['Please fill in the event information'];
		echo "<script>alert('".$logger."');</script>";
	}
}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);
  echo 'ERROR';
}

// 更改狀態
if($action == 'edit_status' && $_SESSION['agent']->therole == 'R'){
	if($id != '' AND $activity_status_open != ''){
		$select_sql=<<<SQL
			SELECT id FROM root_promotion_activity
			WHERE id = '{$id}'
			AND activity_status != '2'
SQL;

		$sql_result = runSQLall($select_sql);

		if($sql_result[0] == 1){
			$sql =<<<SQL
			UPDATE root_promotion_activity
				SET activity_status = '{$activity_status_open}'
			WHERE id = '{$id}'
SQL;
			$sql_result = runSQL($sql);
		}
	}
} elseif($action == 'delete' AND $_SESSION['agent']->therole == 'R'){
	// 刪除
	if($id != ''){
		$select_sql=<<<SQL
			SELECT id FROM root_promotion_activity
			WHERE id = '{$id}'
SQL;

		$sql_result = runSQLall($select_sql);

		// 2= 刪除 1= 開啟 0 = 關閉
		if($sql_result[0] == 1){
			$sql =<<<SQL
			UPDATE root_promotion_activity
				SET activity_status = '2'
			WHERE id = '{$id}'
SQL;
			$sql_result = runSQL($sql);
		}

	}
}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);
  echo 'ERROR';
}

?>