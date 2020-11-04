<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 針對 systemconfig_ann.php 和 systemconfig_ann_editor.php  執行對應動作
// File Name:	systemconfig_ann_action.php
// Author:    Mavis
// Related:   systemconfig_ann_editor.php, systemconfig_ann.php
// DB Table:
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once __DIR__ . '/Utils/RabbitMQ/Publish.php';
require_once __DIR__ . '/Utils/MessageTransform.php';

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

if(isset($_GET['a']) AND $_SESSION['agent'] ->therole == 'R'){
	$action = filter_var($_GET['a'],FILTER_SANITIZE_STRING);
	
	$mq = Publish::getInstance();
	$msg = MessageTransform::getInstance();

} else {
	die('(x)不合法的測試');
}

if(isset($_POST['id'])){
	$id = filter_var($_POST['id'],FILTER_SANITIZE_NUMBER_INT);
}
// 開關狀態
if(isset($_POST['status'])){
	$is_openstatus = filter_var($_POST['status'], FILTER_SANITIZE_NUMBER_INT);
}
// 名稱
if(isset($_POST['name']) AND $_POST['name']!= null){
	$site_announcement_name = filter_var($_POST['name'],FILTER_SANITIZE_STRING);
}
// 公告標題
if(isset($_POST['title']) AND $_POST['title'] != null){
	$site_announcement_title = filter_var($_POST['title'],FILTER_SANITIZE_STRING);
}
// 公告開始有效期間
if(isset($_POST['start_day']) ){
	$site_announcement_effect_time = filter_var($_POST['start_day'],FILTER_SANITIZE_STRING);
}
// 公告結束時間
if(isset($_POST['end_day'])){
	$site_announcement_end_time = filter_var($_POST['end_day'],FILTER_SANITIZE_STRING);
}
// 新增或編輯頁面的狀態
if(isset($_POST['site_announcement_status_open'])){
	$site_announcement_status = filter_var($_POST['site_announcement_status_open'],FILTER_SANITIZE_NUMBER_INT);
}

if(isset($_POST['editor_data_id']) AND $_POST['editor_data_id'] != null){
	$editor_data_id = filter_var($_POST['editor_data_id'], FILTER_SANITIZE_NUMBER_INT);
}
if(isset($_POST['editor_data']) AND $_POST['editor_data'] != null){
	$editor = filter_var($_POST['editor_data'],FILTER_SANITIZE_STRING);
}

// 執行rabbit mq
function to_run_mq($msg,$mq,$title){

	$currentDate = date("Y-m-d H:i:s", strtotime('now'));
    $notifyMsg = $msg->notifyMsg('SiteAnnouncement', $title, $currentDate);
	$notifyResult = $mq->fanoutNotify('msg_notify', $notifyMsg);

	// $notifyResult = $mq->directNotify('direct_test', 'direct_test', $notifyMsg);
}

// -------------------------------------------------------------
// 新增 或 修改 公告內容
// ----------------------------------------------------------
if($action == 'edit_offer' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND  in_array($_SESSION['agent']->account, $su['ops'])) {
	// 公告內容
	$editor_data_remove_jstag     = preg_replace('/<.*script.*>/', '', $editor);
	$editor_data_remove_iframetag = preg_replace('/<.*iframe.*>/', '', $editor_data_remove_jstag);
	$editor_data_encode           = trim(htmlspecialchars($editor_data_remove_iframetag, ENT_QUOTES));

	$today = gmdate('Y/m/d',time() + $_SESSION['agent']->timezone * 3600);
	$year = date('Y/m/d', strtotime("$today +10 year"));

	if($site_announcement_effect_time != '') {
		$site_announcement_effecttime = $site_announcement_effect_time; //.' '.'00:00:00';
	}

	if($site_announcement_end_time == '') {
		// 結束時間 沒選 預設10年
		$site_announcement_endtime = $site_announcement_end_time.$year; //. ' '.'23:59:59';
	} else{
		  // 結束時間 按照使用者選的日期
		$site_announcement_endtime = $site_announcement_end_time;
	}
	/*
	if($site_announcement_endtime != '') {
		$site_announcement_endtime = $site_announcement_endtime; //. ' '.'23:59:59';
	}
	 */

	if($site_announcement_name != '' AND $site_announcement_title != '' AND $editor_data_encode != '') {

		if($site_announcement_effect_time != '' AND $site_announcement_endtime != '' AND $site_announcement_effect_time < $site_announcement_endtime) {
			// 如果id是空的
			if($id == ''){
				$select_site_announcement_sql =<<<SQL
				SELECT * FROM site_announcement WHERE id = NULL AND status != '2'
SQL;
			} else{
				$select_site_announcement_sql =<<<SQL
				SELECT * FROM site_announcement WHERE id = '{$id}' AND status != '2'
SQL;
			}

			$select_site_announcement_result = runSQL($select_site_announcement_sql);

			if($select_site_announcement_result == 0) {

				// 新增
				$sql =<<<SQL
				INSERT INTO site_announcement (operator,name,title,content,status,effecttime,endtime,showinmessage) 
				VALUES ('{$_SESSION['agent']->account}','{$site_announcement_name}','{$site_announcement_title}','{$editor_data_encode}','{$site_announcement_status}','{$site_announcement_effecttime}','{$site_announcement_endtime}','1')
SQL;
				$insert_result = runSQL($sql);
				
				// $logger = $insert_result ? '新增平台公告成功' : '新增平台公告失敗';
				if($insert_result == 1){
					// rabbit mq
					to_run_mq($msg,$mq,$site_announcement_title);
					$logger = '新增平台公告成功';
				}else{
					$logger = '新增平台公告失败';
				}

			} else {
				// 更新
				$sql =<<<SQL
				UPDATE site_announcement SET operator = '{$_SESSION['agent'] ->account}', name ='{$site_announcement_name}' , title = '{$site_announcement_title}' , content ='{$editor_data_encode}' ,status ='{$site_announcement_status}',effecttime = '{$site_announcement_effecttime}' , endtime ='{$site_announcement_endtime}'
				WHERE id = '{$id}'
SQL;
				$update_result = runSQL($sql);

				if($site_announcement_status == '1' AND $site_announcement_endtime >= $today){
					// 修改了公告内容，公告訊息 已讀改未讀
					$sql =<<<SQL
					SELECT * FROM site_announcement_status 
					WHERE account = '{$_SESSION['agent']->account}' 
					AND ann_id = '{$id}'
SQL;
					if(!empty(runSQL($sql))){
						$edit_update_sql =<<<SQL
						UPDATE site_announcement_status 
						SET watchingstatus = '0' 
						WHERE account = '{$_SESSION['agent']->account}' 
						AND ann_id ='{$id}'
SQL;
						$result = runSQL($edit_update_sql);
					}
				}
				// $logger = $update_result ? '平台公告更新成功' : '平台公告更新失敗';

				if($update_result == 1 OR $result == 1){

					// rabbit mq
					to_run_mq($msg,$mq,$site_announcement_title);
					$logger = '平台公告更新成功';
				}else{
					$logger = '平台公告更新失败';
				}
			}

			echo '<script>alert("'.$logger.'");location.href="systemconfig_ann.php";</script>';

		} else{
			$logger = '未选择发布时间，或开始时间大于结束时间，请选择正确时间。';
    		echo '<script>alert("'.$logger.'");</script>';
		}
	} else {
  		$logger = '请确认公告名称，公告标题和公告内容是否正确填入。';
  		echo '<script>alert("'.$logger.'");</script>';
	}
} elseif($action == 'edit_status' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND  in_array($_SESSION['agent']->account, $su['ops'])) {
	// 修改 公告狀態
	// 1 = 顯示; 0 = 關閉

	if($id != '' AND $is_openstatus != '') {
		/*
		$search_id =<<<SQL
		SELECT id FROM site_announcement WHERE id = '{$id}' AND status = '1'
SQL;
*/
		$search_id =<<<SQL
		SELECT id FROM site_announcement WHERE id = '{$id}' AND status != '2'
SQL;
		// var_dump($search_id);die();
		$search_id_result = runSQLall($search_id);

		if($search_id_result[0] == 1) {
			$edit_sql =<<<SQL
			UPDATE site_announcement SET status ='{$is_openstatus}' WHERE id = '{$search_id_result[1]->id}'
SQL;
			$edit_result = runSQL($edit_sql);
			// rabbit mq
			to_run_mq($msg,$mq,$site_announcement_title);
		} else {
			$logger = $tr['Query error or the data has been deleted'];
			echo '<script>alert("'.$logger.'");location.reload();</script>';
		}
	} else {
		$logger = $tr['Wrong attempt'];
		echo '<script>alert("'.$logger.'");location.reload();</script>';
	}

} elseif($action == 'delete' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND  in_array($_SESSION['agent']->account, $su['ops'])) {
	//	--------------------------------------------------------------------
	// 公告刪除
	// 1 = 存在; 0 = 刪除
	// ---------------------------------------------------------------------

	if($id != ''){
		/*
		$search_id =<<<SQL
		SELECT id FROM site_announcement WHERE id = '{$id}' AND status !='1'
SQL;
		*/
		// 是否真有這個公告ID
		$search_id =<<<SQL
		SELECT id FROM site_announcement WHERE id = '{$id}' AND status !='2'
SQL;
		 
		$search_id_result = runSQLall($search_id);

		if($search_id_result[0] == 1) {	
			
			$delete_sql =<<<SQL
			UPDATE site_announcement SET showinmessage = '0' , status = '2' WHERE id ='{$search_id_result[1]->id}'
SQL;

	/*
			$delete_sql =<<<SQL
			UPDATE site_announcement SET status = '2' ,showinmessage = '0' WHERE id ='{$search_id_result[1]->id}' AND showinmessage = '0'
SQL;
	*/
			$delete_result = runSQLall($delete_sql);

			if($delete_result){
				// 刪除成功

				// rabbit mq
				// to_run_mq($msg,$mq,$site_announcement_title);
				$logger = $tr['Delete successfully'];
				// $final_result['code'] = 1;
				echo '<script>alert("'.$logger.'");location.reload();</script>';
			} else {
				// 刪除失敗
				$logger = $tr['delete failed'];
				echo '<script>alert("'.$logger.'");</script>';
			}
		} else {
			// 查詢錯誤 或 無此筆資料
			$logger = $tr['Query error or the data has been deleted'];
			echo '<script>alert("'.$logger.'");location.reload();</script>';
		}

	} else{
		$logger = $tr['Wrong attempt'];
    	echo '<script>alert("'.$logger.'");location.reload();</script>';
	}

} elseif($action == 'test'){
	var_dump($_POST);
    echo 'ERROR';
}

?>


