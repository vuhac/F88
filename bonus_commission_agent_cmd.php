#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 直銷組織加盟金 -- 獨立排程執行
// File Name:	bonus_commission_agent_cmd.php
// Author:		Barkley,Fix by Ian
// Related:
// DB table:  root_statisticsdailyreport 每日營收日結報表
// DB table:  root_statisticsbonusagent 放射線組織獎金計算-代理加盟金
// Log:
// 將營運日報的資料，整理成為會員獎金分紅的報表，並且輸出成為資料表存放。
// 將計算完成的資料存放在 root_statisticsbonusagent 表格內,使用者可以指定日期更新
// 查詢的時候，會自動 insert data 到 root_statisticsbonusagent 表格
// 資料如果完整後，可以選擇計算出統計最後結果的資料。
// ----------------------------------------------------------------------------
// 程式開發的邏輯：
// -------------------------------------------------------------------------
// 透過一個指定的時間區間, 依據會員資料 root_member 搜尋日結報表的資料
// 依據 root_member 的上下階層關係, 列出每個會員的每一個上一代, 一直到 root
// 每一個會員的投注資訊, 透過日結報表統計指定的時間區間取得完整資訊，以利列出符合條件的會員(代理商)
// 依據每個會員日結報表資料(日結報表資料需要完整, 會員不存在的處理), 統計出來加盟金及總投注額
// -------------------------------------------------------------------------
// How to run ?
// usage command line : /usr/bin/php70 bonus_commission_agent_cmd.php
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// session_start();

$stats_showdata_count = 0;
$stats_insert_count = 0;
$stats_update_count = 0;
$stats_bonusamount_count = 0;

// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// betlog 專用的 DB lib
require_once dirname(__FILE__) ."/config_betlog.php";

// set memory limit
ini_set('memory_limit', '200M');

// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

// 程式 debug 開關, 0 = off , 1= on
$debug = 0;

// API 每次送出的最大數據筆數
// 用於進行帳號批次 LOCK 時使用
$api_limit = 1000;

// ----------------------------------
// 本程式使用的 function
// ----------------------------------

// -------------------------------------------------------------------------
// 1.1 以節點找出使用者的資料 -- from root_member
// -------------------------------------------------------------------------
function find_member_node($member_id, $tree_level, $current_datepicker_start, $current_datepicker, $find_parent) {
	global $timeover;

// 加上 memcached 加速,宣告使用 memcached 物件 , 連接看看. 不能連就停止.
$memcache = new Memcached();
$memcache->addServer('localhost', 11211) or die ("Could not connect memcache server !! ");
// 把 query 存成一個 key in memcache
$key = 'find_member_node'.$member_id.$current_datepicker_start.$current_datepicker;
$key_alive_show = sha1($key);

// 取得看看記憶體中，是否有這個 key 存在, 有的話直接抓 key
$getfrom_memcache_result = $memcache->get($key_alive_show);
if(!$getfrom_memcache_result) {

		$tree_level = $tree_level;
		//var_dump($find_parent);
		if($find_parent == 1 AND $timeover == 0){
			// 現在時間在計算周期間，尋找上一代會員資料
			// 在尋找會員的上一代時，如果遇到原上代已停用帳號，則要再向上找到非停用的帳號做為其上一代
			$status = 0;
			$search_member_id = $member_id;
			while($status == 0){
				//echo $search_member_id."\n";
				// 先把會員資料取出來，再判斷是否為停用（status = 0）
				// 如果是停用帳號，再找出此帳號的parant，一直到那帳號不是停用的帳號
				$member_sql = "SELECT id, account, parent_id, therole, status FROM root_member WHERE id = '$search_member_id';";
				//var_dump($member_sql);
				$member_result = runSQLall($member_sql);
				if($member_result[0]==1) {
					$status = $member_result[1]->status;
					$search_member_id = $member_result[1]->parent_id;
				}
			}
		}elseif($find_parent == 1 AND $timeover == 1){
			// 現在時間已超過計算周期的最後一天時，尋找上一代會員資料
			//$tree_level = 1; 不論 status 為何, 都要找出來. 否則會 lost data 問題.
			$member_sql = 'SELECT member_id as id, member_account as account, member_parent_id as parent_id,member_therole as therole FROM root_statisticsbonusagent
			WHERE  member_id = \''.$member_id.'\' AND dailydate_start =\''.$current_datepicker_start.'\' AND dailydate_end =\''.$current_datepicker.'\';';
			//var_dump($member_sql);
			$member_result = runSQLall($member_sql);
		}else{
			//$tree_level = 1; 不論 status 為何, 都要找出來. 否則會 lost data 問題.
			$member_sql = "SELECT id, account, parent_id, therole FROM root_member WHERE id = '$member_id';";
			//var_dump($member_sql);
			$member_result = runSQLall($member_sql);
		}
		$member_result = runSQLall($member_sql);
		//var_dump($member_result);
		if($member_result[0]==1){
			$tree = $member_result[1];
			$tree->level = $tree_level;

			$psql = "SELECT sum(all_bets) as sum_all_bets ,count(all_bets) as count_all_bets FROM root_statisticsdailyreport WHERE dailydate >= '$current_datepicker_start' AND dailydate <= '$current_datepicker' and member_id = $member_id;";
			$psql_result = runSQLall($psql);
			// return sum_all_bets	count_all_bets
			if($psql_result[0] == 1) {
				// 將總和即時間資訊寫入節點
				$tree->sum_date_start = $current_datepicker_start;
				$tree->sum_date_end = $current_datepicker;
				$tree->sum_all_bets = $psql_result[1]->sum_all_bets;
				$tree->count_all_bets = $psql_result[1]->count_all_bets;
			}else{
				$logger = "日報表資料 ID = $member_id 會員資料遺失, 請聯絡客服人員處理.";
				die($logger);
			}

		}else{
			$logger ="ID = $member_id 資料遺失, 請聯絡客服人員處理.";
			die($logger);
		}


// save to memcached ref:http://php.net/manual/en/memcached.set.php
$memcached_timeout = 120;
$memcache->set($key_alive_show, $tree, time()+$memcached_timeout) or die ("Failed to save data at the memcache server");
//echo "Store data in the cache (data will expire in $memcached_timeout seconds)<br/>\n";
}else{
	// 資料有存在記憶體中，直接取得 get from memcached
	$tree = $getfrom_memcache_result;
}

//var_dump($tree);
return($tree);
}

// -------------------------------------------------------------------------
// 1.2 找出上層節點的所有會員，直到 root -- from root_member
// -------------------------------------------------------------------------
function find_parent_node($member_id, $current_datepicker_start, $current_datepicker) {

	// 最大層數 100 代
	$tree_level_max = 100;

	$tree_level = 0;
	// treemap 為正常的組織階層
	$treemap[$member_id][$tree_level] = find_member_node($member_id, $tree_level, $current_datepicker_start, $current_datepicker,0);

	// $treemap_performance 唯有達標的組織階層
	//$treemap_performance[$member_id][$tree_level] = find_agent_performance_node($member_id, $tree_level);
	while($tree_level<=$tree_level_max) {
		$m_id = $treemap[$member_id][$tree_level]->parent_id;
		$m_account = $treemap[$member_id][$tree_level]->account;
		$tree_level = $tree_level+1;
		// 如果到了 root 的話跳離迴圈。表示已經到了最上層的會員了。
		if($m_account == 'root') {
			break;
		}else{
			$treemap[$member_id][$tree_level] = find_member_node($m_id, $tree_level, $current_datepicker_start, $current_datepicker,1);
		}
	}

	// var_dump($treemap);
	return($treemap);
}
// -------------------------------------------------------------------------

// -------------------------------------------
// (1) 新進會員傭金統計
// -------------------------------------------
// 輸入會員資料及 日起 start - end , 回應傭金的金額或是 0
function sum_agency_commission_amount($member_id, $current_datepicker_start, $current_datepicker){
	$sql = "SELECT sum(agency_commission) as sum_agency_commission ,count(agency_commission) as count_agency_commission FROM root_statisticsdailyreport
	WHERE dailydate >= '$current_datepicker_start' AND dailydate <= '$current_datepicker' AND member_id = '".$member_id."'
	HAVING sum(agency_commission) > 0 ;";
	//var_dump($sql);
	$agency_commission_result = runSQLall($sql);
	//var_dump($agency_commission_result);

	// sql 成立且傭金大於 0 的話，才 return 列出。
	if($agency_commission_result[0] == 1) {
		$agency_commission_amount = $agency_commission_result[1]->sum_agency_commission;
	}else{
		$agency_commission_amount = false;
	}
	return($agency_commission_amount);
}
// -------------------------------------------

// -------------------------------------------------------------------------
// round 1
// 根據 member_is + date start + date end 找出相對應的代數及傭金計算資料
// -------------------------------------------------------------------------
function statisticsbonusagent($member_id,$current_datepicker_start, $current_datepicker) {
	// -------------------------------------------
	// 找出會員所在的 tree 直到 root
	// -------------------------------------------
	$tree = find_parent_node($member_id,$current_datepicker_start, $current_datepicker);
	// var_dump($tree);

	// 會員所在組織的代數
	$member_tree_level_number = count($tree[$member_id])-1;
	$statisticsbonusagent['member_level'] = $member_tree_level_number;

	// 上1代member account
	$level=1;
	if(!isset($tree[$member_id][$level]->account) ){
		$member_tree_r[$level] = 'n/a';
	}else{
		$member_tree_r[$level] = $tree[$member_id][$level]->account;
	}
	$statisticsbonusagent['level_account_1'] = $member_tree_r[$level];

	// 上2代member account
	$level++;
	if(!isset($tree[$member_id][$level]->account) ){
		$member_tree_r[$level] = 'n/a';
	}else{
		$member_tree_r[$level] = $tree[$member_id][$level]->account;
	}
	$statisticsbonusagent['level_account_2'] = $member_tree_r[$level];

	// 上3代member account
	$level++;
	if(!isset($tree[$member_id][$level]->account) ){
		$member_tree_r[$level] = 'n/a';
	}else{
		$member_tree_r[$level] = $tree[$member_id][$level]->account;
	}
	$statisticsbonusagent['level_account_3'] = $member_tree_r[$level];

	// 上4代member account
	$level++;
	if(!isset($tree[$member_id][$level]->account) ){
		$member_tree_r[$level] = 'n/a';
	}else{
		$member_tree_r[$level] = $tree[$member_id][$level]->account;
	}
	$statisticsbonusagent['level_account_4'] = $member_tree_r[$level];

	// 此寫法為了閱讀 array 方便, 第一個 index 使用了 member ID
	// 所以每次進入 loop 需要 free 記憶體, 否則筆數資料一多, 就會記憶體不足。
	unset($tree);
	// -------------------------------------------

	// -------------------------------------------
	// 會員加盟傭金加總 (但是會員傭金一個人不可能超過一筆)
	// -------------------------------------------
	$sum_agency_commission_amount_result = sum_agency_commission_amount($member_id, $current_datepicker_start, $current_datepicker);
	//var_dump($sum_agency_commission_amount_result);
	if($sum_agency_commission_amount_result != false ){
		$agency_commission_amount = $sum_agency_commission_amount_result;
		//var_dump($statisticsbonusagent);
	}else{
		// 沒有大於 1 的金額設定為 0, 沒有 count
		$agency_commission_amount = 0;
		//$logger = ' 傭金 sum_agency_commission_amount 計算有問題,請聯絡客服人員.';
		//die($logger);
	}
	$statisticsbonusagent['agency_commission'] = $agency_commission_amount;

	// -------------------------------------------
	// 會員加盟金四層分紅
	// -------------------------------------------
	global $rule;
	// 會員加盟傭金分紅 - 第1代
	$agency_commission_amount_1 = round(($agency_commission_amount*$rule['commission_1_rate']/100), 2);
	// 會員加盟傭金分紅 - 第2代
	$agency_commission_amount_2 = round(($agency_commission_amount*$rule['commission_2_rate']/100), 2);
	// 會員加盟傭金分紅 - 第3代
	$agency_commission_amount_3 = round(($agency_commission_amount*$rule['commission_3_rate']/100), 2);
	// 會員加盟傭金分紅 - 第4代
	$agency_commission_amount_4 = round(($agency_commission_amount*$rule['commission_4_rate']/100), 2);
	// 公司分紅收入
	$agency_commission_root     = round(($agency_commission_amount*$rule['commission_root_rate']/100), 2);
	// 以上加總須為 100%
	$statisticsbonusagent['level_bonus_1'] = $agency_commission_amount_1;
	$statisticsbonusagent['level_bonus_2'] = $agency_commission_amount_2;
	$statisticsbonusagent['level_bonus_3'] = $agency_commission_amount_3;
	$statisticsbonusagent['level_bonus_4'] = $agency_commission_amount_4;
	$statisticsbonusagent['company_bonus'] = $agency_commission_root;

	return($statisticsbonusagent);
}
// -------------------------------------------------------------------------
// 找出相對應的代數及傭金計算資料 END
// -------------------------------------------------------------------------


// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) == $date;
}

// ----------------------------------
// 本程式使用的 function END
// ----------------------------------

// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
// var_dump($_SERVER);
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
if(isset($_SERVER['HTTP_USER_AGENT']) OR isset($_SERVER['SERVER_NAME'])) {
  die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}
//if(isset($_SERVER['USER']) AND $_SERVER['USER'] == 'nginx' ) {
//  die('不允許使用網頁執行，請確認你的呼叫來源。');
//}
// -----------------------------------------------------------------
// 命令列參數解析
// -----------------------------------------------------------------

// 取得今天的日期
// 轉換為美東的時間 date
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
date_timezone_set($date, timezone_open('America/St_Thomas'));
$current_date = date_format($date, 'Y-m-d');
$current_date_timestamp = strtotime($current_date);

if(isset($argv[1]) AND ($argv[1] == 'test' OR $argv[1] == 'run') ){
  if(isset($argv[2]) AND validateDate($argv[2], 'Y-m-d') ){
		if($argv[2] <= $current_date){
	    //如果有的話且格式正確, 取得日期. 沒有的話中止
			$current_datepicker = $argv[2];
		}else{
		  // command 動作 時間
		  echo "command [test|run] YYYY-MM-DD \n";
		  die('no test and run');
		}
  }else{
		$current_datepicker = $current_date;
		// $current_datepicker = date('Y-m-d');
  }
  $argv_check = $argv[1];
	$current_datepicker_gmt = gmdate('Y-m-d H:i:s.u',strtotime($current_datepicker.'23:59:59 -04')+8*3600).'+08:00';
}else{
  // command 動作 時間
  echo "command [test|run] YYYY-MM-DD \n";
  die('no test and run');
}

if(isset($argv[3]) AND $argv[3] == 'web' ){
	$web_check = 1;
	$output_html  = '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
	$file_key = sha1('agentbonus'.$current_datepicker);
	$reload_file = dirname(__FILE__) .'/tmp_dl/agentbonus_'.$file_key.'.tmp';
	file_put_contents($reload_file,$output_html);
}elseif(isset($argv[3]) AND $argv[3] == 'sql' ){
	if(isset($argv[4]) AND filter_var($argv[4], FILTER_VALIDATE_INT) ){
		$web_check = 2;
		$updatelog_id = filter_var($argv[4], FILTER_VALIDATE_INT);
		$updatelog_sql = "SELECT * FROM root_bonusupdatelog WHERE id ='$updatelog_id';";
		$updatelog_result = runSQL($updatelog_sql);
		if($updatelog_result == 0){
			die('No root_bonusupdatelog ID');
		}
	}else{
		die('No root_bonusupdatelog ID');
	}
}else{
	$web_check = 0;
}

$logger ='';


// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// round 1. 新增或更新會員資料
// ----------------------------------------------------------------------------

if($web_check == 1){
	$output_html  = '<p align="center">round 1. 新增或更新會員資料 - 更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
	file_put_contents($reload_file,$output_html);
}elseif($web_check == 2){
	$updatlog_note = 'round 1. 新增或更新會員資料 - 更新中';
	$updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'0\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
	if($argv_check == 'test'){
		echo $updatelog_sql;
	}elseif($argv_check == 'run'){
		$updatelog_result = runSQLall($updatelog_sql);
	}
}else{
	echo "round 1. 新增或更新會員資料 - 開始\n";
}


// -------------------------------------------------------------------------
// find member tree to root
// 尋找符合業績達成的上層, 共 n 代. 直到最上層 root 會員。
// 再以計算出來的代數 account 判斷，哪些代數符合達成業績標準的會員。
// -------------------------------------------------------------------------

$current_datepicker_start = $current_datepicker;
$bonus_days_timestamp = strtotime($current_datepicker_start);
// 取出 root_member 資料
if($current_date_timestamp <= $bonus_days_timestamp){
	$userlist_sql = "SELECT id,account,parent_id,therole FROM root_member WHERE enrollmentdate <= '$current_datepicker_gmt' OR enrollmentdate IS NULL ORDER BY id ASC;";
	$timeover = 0;
	$userlist       = runSQLall($userlist_sql);
}else{
	$userlist_sql = 'SELECT member_id as id, member_account as account, member_parent_id as parent_id,member_therole as therole FROM root_statisticsbonusagent
	WHERE dailydate_start =\''.$current_datepicker_start.'\' AND dailydate_end =\''.$current_datepicker.'\';';
	$timeover = 1;
	$userlist       = runSQLall($userlist_sql);
	if($userlist[0] == 0) {
		$userlist_sql = "SELECT id,account,parent_id,therole FROM root_member WHERE enrollmentdate <= '$current_datepicker_gmt' OR enrollmentdate IS NULL ORDER BY id ASC;";
		$timeover = 0;
		$userlist       = runSQLall($userlist_sql);
	}
}
//var_dump($userlist_sql);
$userlist_count = $userlist[0];

// 處理進度 % , 用來顯示紀錄進度。
$percentage_current = 0;
// 判斷 root_member count 數量大於 1 , 有會員資料的話才繼續
if($userlist[0] >= 1) {
  // 會員有資料，且存在數量為 $userlist_count
  // ----------------------------------------------------
  // 以會員資料為主要 key 依序列出每個會員的貢獻金額
  for($i = 1 ; $i <= $userlist_count ; $i++){
		// 程式邏輯：
		// 1.讀取資料庫中的資料，看是否存在. 如果存在的話，就直接更新，並且讀取資料show in web
		// 2.如果不存在的話，就直接INSERT，並且讀取資料show in web
		$getdata_bonusagent_sql = "SELECT * FROM root_statisticsbonusagent WHERE member_account = '".$userlist[$i]->account."' AND dailydate_start ='".$current_datepicker_start."' AND dailydate_end ='".$current_datepicker."' ;";
		//var_dump($getdata_bonusagent_sql);
		$getdata_bonusagent_result = runSQLall($getdata_bonusagent_sql);
		if($getdata_bonusagent_result[0] == 0) {
			// DB root_statisticsbonusagent 沒有資料, 執行 insert data

			// -------------------------------------------
			// 取得目前每日報表中的資料
			$b = statisticsbonusagent($userlist[$i]->id,$current_datepicker_start, $current_datepicker);
			if($b != false) {
				$b['member_id']              = $userlist[$i]->id;
				$b['member_account']         = $userlist[$i]->account;
				$b['member_therole']         = $userlist[$i]->therole;
				$b['member_parent_id']       = $userlist[$i]->parent_id;
				$b['dailydate_start']        = $current_datepicker_start;
				$b['dailydate_end']          = $current_datepicker;

				// 第二次運算才會生成的資料, 先給一個預設值,但是不寫入資料庫. default = null
				$b['member_bonus_1']          = 'n/a';
				$b['member_bonuscount_1']     = 'n/a';
				$b['member_bonus_2']          = 'n/a';
				$b['member_bonuscount_2']     = 'n/a';
				$b['member_bonus_3']          = 'n/a';
				$b['member_bonuscount_3']     = 'n/a';
				$b['member_bonus_4']          = 'n/a';
				$b['member_bonuscount_4']     = 'n/a';
				$b['member_bonuscount']       = 'n/a';
				$b['member_bonusamount']      = 'n/a';
				// 付款紀錄及時間 and notes,此階段不適用(n/a)先保持預設值, 不寫入資料庫.
				$b['member_bonusamount_paid']     = 'n/a';
				$b['member_bonusamount_paidtime'] = 'n/a';
				$b['notes']                       = 'n/a';
				//var_dump($b);
			}else{
				$logger = '每日報表中的資料生成錯誤';
				die($logger);
			}
			// -------------------------------------------

			$insert_sql = '
			INSERT INTO "root_statisticsbonusagent" ("member_id","member_account", "member_parent_id", "member_therole", "updatetime", "dailydate_start", "dailydate_end",
			"member_level", "level_account_1", "level_account_2", "level_account_3", "level_account_4", "agency_commission", "level_bonus_1", "level_bonus_2", "level_bonus_3", "level_bonus_4", "company_bonus"
			)'.
			"VALUES ('".$b['member_id']."', '".$b['member_account']."', '".$b['member_parent_id']."', '".$b['member_therole']."', now()
			, '".$b['dailydate_start']."', '".$b['dailydate_end']."', '".$b['member_level']."', '".$b['level_account_1']."', '".$b['level_account_2']."', '".$b['level_account_3']."', '".$b['level_account_4']."'
			, '".$b['agency_commission']."', '".$b['level_bonus_1']."', '".$b['level_bonus_2']."', '".$b['level_bonus_3']."', '".$b['level_bonus_4']."', '".$b['company_bonus']."'
			);";

			if($argv_check == 'test'){
				var_dump($insert_sql);
				$insertdata_bonusagent_result = 1;
				//print_r($insert_sql);
			}elseif($argv_check == 'run'){
				$insertdata_bonusagent_result = runSQL($insert_sql);
			}

			if($insertdata_bonusagent_result == 1) {
				//var_dump($insertdata_bonusagent_result);
				//echo 'no data to insert - '.$b['member_account'].'<br>';
				$stats_insert_count++;
			}else{
				$logger = $current_datepicker_start.'~'.$current_datepicker.' 會員 '.$userlist[$i]->account.' 插入資料有問題，請聯絡開發人員處理。';
				die($logger);
			}

			// ------- bonus update log ------------------------
			// 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
			$percentage_html     = round(($i/$userlist[0]),2)*100;
			$process_record_html = "$i/$userlist[0]";
			$process_times_html  = round((microtime(true) - $program_start_time),3);
			$counting_r = $percentage_html%10;

			if($web_check == 1 AND $counting_r == 0){
				$output_sub_html  = $output_html.'<p align="center">'.$percentage_html.'%</p>';
				file_put_contents($reload_file,$output_sub_html);
			}elseif($web_check == 2 AND $counting_r == 0){
			  $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \''.$percentage_html.'\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
			  if($argv_check == 'test'){
			    echo $updatelog_sql;
			  }elseif($argv_check == 'run'){
			    $updatelog_result = runSQLall($updatelog_sql);
			  }
			}elseif($web_check == 0){
				if($percentage_html != $percentage_current) {
					if($counting_r == 0) {
						echo "\n目前處理 $current_datepicker 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
					}else{
						echo $percentage_html.'% ';
					}
					$percentage_current = $percentage_html;
				}
			}
			// -------------------------------------------------

		}else{
			// DB 有資料 update step1 資料
			// -------------------------------------------
			// 取得目前每日報表中的資料
			$b = statisticsbonusagent($userlist[$i]->id,$current_datepicker_start, $current_datepicker);
			if($b != false) {
				$b['member_id']              = $userlist[$i]->id;
				$b['member_account']         = $userlist[$i]->account;
				$b['member_therole']         = $userlist[$i]->therole;
				$b['member_parent_id']       = $userlist[$i]->parent_id;
				$b['dailydate_start']        = $current_datepicker_start;
				$b['dailydate_end']          = $current_datepicker;

				// 第二次運算才會生成的資料, 先給一個預設值,但是不寫入資料庫. default = null
				$b['member_bonus_1']          = 'n/a';
				$b['member_bonuscount_1']     = 'n/a';
				$b['member_bonus_2']          = 'n/a';
				$b['member_bonuscount_2']     = 'n/a';
				$b['member_bonus_3']          = 'n/a';
				$b['member_bonuscount_3']     = 'n/a';
				$b['member_bonus_4']          = 'n/a';
				$b['member_bonuscount_4']     = 'n/a';
				$b['member_bonuscount']       = 'n/a';
				$b['member_bonusamount']      = 'n/a';
				// 付款紀錄及時間 and notes,此階段不適用(n/a)先保持預設值, 不寫入資料庫.
				$b['member_bonusamount_paid']     = 'n/a';
				$b['member_bonusamount_paidtime'] = 'n/a';
				$b['notes']                       = 'n/a';
				//var_dump($b);

				$update_sql = '
				UPDATE "root_statisticsbonusagent" SET
				"member_id" = \''.$b['member_id'].'\',
				"member_account" = \''.$b['member_account'].'\',
				"member_parent_id" = \''.$b['member_parent_id'].'\',
				"member_therole" = \''.$b['member_therole'].'\',
				"updatetime" = now(),
				"dailydate_start" = \''.$b['dailydate_start'].'\',
				"dailydate_end" = \''.$b['dailydate_end'].'\',
				"member_level" = \''.$b['member_level'].'\',
				"level_account_1" = \''.$b['level_account_1'].'\',
				"level_account_2" = \''.$b['level_account_2'].'\',
				"level_account_3" = \''.$b['level_account_3'].'\',
				"level_account_4" = \''.$b['level_account_4'].'\',
				"agency_commission" = \''.$b['agency_commission'].'\',
				"level_bonus_1" = \''.$b['level_bonus_1'].'\',
				"level_bonus_2" = \''.$b['level_bonus_2'].'\',
				"level_bonus_3" = \''.$b['level_bonus_3'].'\',
				"level_bonus_4" = \''.$b['level_bonus_4'].'\',
				"company_bonus" = \''.$b['company_bonus'].'\'
				WHERE "member_account" = \''.$b['member_account'].'\' and "dailydate_start" = \''.$b['dailydate_start'].'\' and "dailydate_end" = \''.$b['dailydate_end'].'\';
				';
				// echo $b['member_account'].'='.$update_sql."<br>";
				$logger =  '更新會員資料'.$b['member_id'].'='.$b['member_account'].'='.$update_sql;


			if($argv_check == 'test'){
				var_dump($update_sql);
				$update_bonusagent_result = 1;
		    //print_r($update_sql);
			}elseif($argv_check == 'run'){
				$update_bonusagent_result = runSQL($update_sql);
			}

	    //var_dump($update_bonusagent_result);
	    if($update_bonusagent_result == 1) {
	      // echo '更新統計資料 - '.$b['member_account'].'<br>';
	      $stats_update_count++;
	    }else{
	      $logger = $current_datepicker_start.'~'.$current_datepicker.' 會員 '.$b['member_account'].'更新統計資料有問題，請聯絡開發人員處理。';
	      die($logger);
	    }

			// ------- bonus update log ------------------------
			// 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
			$percentage_html     = round(($i/$userlist[0]),2)*100;
			$process_record_html = "$i/$userlist[0]";
			$process_times_html  = round((microtime(true) - $program_start_time),3);
			$counting_r = $percentage_html%10;

			if($web_check == 1 AND $counting_r == 0){
				$output_sub_html  = $output_html.'<p align="center">'.$percentage_html.'%</p>';
				file_put_contents($reload_file,$output_sub_html);
			}elseif($web_check == 2 AND $counting_r == 0){
			  $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \''.$percentage_html.'\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
			  if($argv_check == 'test'){
			    echo $updatelog_sql;
			  }elseif($argv_check == 'run'){
			    $updatelog_result = runSQLall($updatelog_sql);
			  }
			}elseif($web_check == 0){
				if($percentage_html != $percentage_current) {
					if($counting_r == 0) {
						echo "\n目前處理 $current_datepicker 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
					}else{
						echo $percentage_html.'% ';
					}
					$percentage_current = $percentage_html;
				}
			}
			// -------------------------------------------------
		}
		}
	}
}

// ----------------------------------------------------------------------------
// round 2. 更新會員加盟金
// ----------------------------------------------------------------------------

if($web_check == 1){
	$output_html  = '<p align="center">round 2. 更新會員加盟金資料 - 更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
	file_put_contents($reload_file,$output_html);
}elseif($web_check == 2){
	$updatlog_note = 'round 2. 更新會員加盟金資料 - 更新中';
	$updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'0\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
	if($argv_check == 'test'){
		echo $updatelog_sql;
	}elseif($argv_check == 'run'){
		$updatelog_result = runSQLall($updatelog_sql);
	}
}else{
	echo "round 2. 更新會員加盟金資料 - 開始\n";
}

//$userlist_sql = "SELECT * FROM root_member WHERE enrollmentdate <= '$current_datepicker_gmt' OR enrollmentdate IS NULL ORDER BY id ASC;";
//var_dump($userlist_sql);
$userlist_sql = 'SELECT member_id as id, member_account as account, member_parent_id as parent_id,member_therole as therole FROM root_statisticsbonusagent
	WHERE dailydate_start =\''.$current_datepicker_start.'\' AND dailydate_end =\''.$current_datepicker.'\';';
$userlist       = runSQLall($userlist_sql);
$userlist_count = $userlist[0];

// 處理進度 % , 用來顯示紀錄進度。
$percentage_current = 0;

// 判斷 root_member count 數量大於 1 , 有會員資料的話才繼續
if($userlist[0] >= 1) {
  // 會員有資料，且存在數量為 $userlist_count
  // ----------------------------------------------------
  // 以會員資料為主要 key 依序列出每個會員的貢獻金額
  for($i = 1 ; $i <= $userlist_count ; $i++){
		// 有資料的狀態選擇,不更新 raw data
		// 取出資料，並計算獎金分紅寫入 DB
		$getdata_bonusagent_sql = "SELECT * FROM root_statisticsbonusagent WHERE member_account = '".$userlist[$i]->account."' AND dailydate_start ='".$current_datepicker_start."' AND dailydate_end ='".$current_datepicker."' ;";
		//var_dump($getdata_bonusagent_sql);
		$getdata_bonusagent_result = runSQLall($getdata_bonusagent_sql);

		// 指定的日期 + 會員 , 沒有資料的狀況處理
		if($getdata_bonusagent_result[0] >= 1) {
			// DB 有資料 show data
			//var_dump($getdata_bonusagent_result);
			// var_dump($getdata_bonusagent_result[1]->id);
			// 資料庫內的 PK
			$b['id']                     = $getdata_bonusagent_result[1]->id;
			// 會員的 member ID
			$b['member_id']              = $userlist[$i]->id;
			$b['member_account']         = $getdata_bonusagent_result[1]->member_account;
			$b['member_therole']         = $getdata_bonusagent_result[1]->member_therole;
			$b['member_parent_id']       = $getdata_bonusagent_result[1]->member_parent_id;
			$b['dailydate_start']        = $getdata_bonusagent_result[1]->dailydate_start;
			$b['dailydate_end']          = $getdata_bonusagent_result[1]->dailydate_end;
			$b['member_level']           = $getdata_bonusagent_result[1]->member_level;
			$b['level_account_1']        = $getdata_bonusagent_result[1]->level_account_1;
			$b['level_account_2']        = $getdata_bonusagent_result[1]->level_account_2;
			$b['level_account_3']        = $getdata_bonusagent_result[1]->level_account_3;
			$b['level_account_4']        = $getdata_bonusagent_result[1]->level_account_4;
			$b['agency_commission']      = $getdata_bonusagent_result[1]->agency_commission;
			$b['level_bonus_1']          = $getdata_bonusagent_result[1]->level_bonus_1;
			$b['level_bonus_2']          = $getdata_bonusagent_result[1]->level_bonus_2;
			$b['level_bonus_3']          = $getdata_bonusagent_result[1]->level_bonus_3;
			$b['level_bonus_4']          = $getdata_bonusagent_result[1]->level_bonus_4;
			$b['company_bonus']          = $getdata_bonusagent_result[1]->company_bonus;

			$b['member_bonuscount_1']     = $getdata_bonusagent_result[1]->member_bonuscount_1;
			$b['member_bonus_1']     = $getdata_bonusagent_result[1]->member_bonus_1;
			$b['member_bonuscount_2']     = $getdata_bonusagent_result[1]->member_bonuscount_2;
			$b['member_bonus_2']     = $getdata_bonusagent_result[1]->member_bonus_2;
			$b['member_bonuscount_3']     = $getdata_bonusagent_result[1]->member_bonuscount_3;
			$b['member_bonus_3']     = $getdata_bonusagent_result[1]->member_bonus_3;
			$b['member_bonuscount_4']     = $getdata_bonusagent_result[1]->member_bonuscount_4;
			$b['member_bonus_4']     = $getdata_bonusagent_result[1]->member_bonus_4;

			$b['member_bonuscount']     = $getdata_bonusagent_result[1]->member_bonuscount;
			$b['member_bonusamount']     = $getdata_bonusagent_result[1]->member_bonusamount;
			$b['member_bonusamount_paid']= $getdata_bonusagent_result[1]->member_bonusamount_paid;
			$b['member_bonusamount_paidtime'] = $getdata_bonusagent_result[1]->member_bonusamount_paidtime;
			$b['notes']                   = $getdata_bonusagent_result[1]->notes;
			// 上面變數的順序, 會影響 csv 輸出的順序

			// 變數：如果要產生分紅統計資料的話, 設定為 true , 產生分紅筆數, 及分紅合計
			//$update_bonusagent_amount_status = true;
			//$update_bonusagent_amount_status = false;
			//var_dump($update_bonusagent_amount_status);

			// 統計 by member_account
			$lb_sql[1]     = "SELECT count(level_bonus_1) as count_level_bonus_1 ,sum(level_bonus_1) as sum_level_bonus_1
			FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
			AND level_bonus_1 > 0 AND level_account_1= '".$userlist[$i]->account."';";
			$lb_result[1]  = runSQLall($lb_sql[1]);

			$lb_sql[2]     = "SELECT count(level_bonus_2) as count_level_bonus_2 ,sum(level_bonus_2) as sum_level_bonus_2
			FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
			AND level_bonus_2 > 0 AND level_account_2= '".$userlist[$i]->account."';";
			$lb_result[2]  = runSQLall($lb_sql[2]);

			$lb_sql[3]     = "SELECT count(level_bonus_3) as count_level_bonus_3 ,sum(level_bonus_3) as sum_level_bonus_3
			FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
			AND level_bonus_3 > 0 AND level_account_3= '".$userlist[$i]->account."';";
			$lb_result[3]  = runSQLall($lb_sql[3]);

			$lb_sql[4]     = "SELECT count(level_bonus_4) as count_level_bonus_4 ,sum(level_bonus_4) as sum_level_bonus_4
			FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
			AND level_bonus_4 > 0 AND level_account_4= '".$userlist[$i]->account."';";
			$lb_result[4]  = runSQLall($lb_sql[4]);

			//var_dump($lb_result);
			if($lb_result[1][0] == 1 AND $lb_result[2][0] == 1 AND $lb_result[3][0] == 1 AND $lb_result[4][0] == 1){
				// 四層分紅累算 , 及數量
				$b['member_bonus_1'] = round($lb_result[1][1]->sum_level_bonus_1,2);
				$b['member_bonus_2'] = round($lb_result[2][1]->sum_level_bonus_2,2);
				$b['member_bonus_3'] = round($lb_result[3][1]->sum_level_bonus_3,2);
				$b['member_bonus_4'] = round($lb_result[4][1]->sum_level_bonus_4,2);
				$b['member_bonuscount_1'] = round($lb_result[1][1]->count_level_bonus_1);
				$b['member_bonuscount_2'] = round($lb_result[2][1]->count_level_bonus_2);
				$b['member_bonuscount_3'] = round($lb_result[3][1]->count_level_bonus_3);
				$b['member_bonuscount_4'] = round($lb_result[4][1]->count_level_bonus_4);

				// 覆蓋前面的變數
				$b['member_bonusamount'] = round(($lb_result[1][1]->sum_level_bonus_1+$lb_result[2][1]->sum_level_bonus_2+$lb_result[3][1]->sum_level_bonus_3+$lb_result[4][1]->sum_level_bonus_4),2);
				$b['member_bonuscount']  = round($lb_result[1][1]->count_level_bonus_1+$lb_result[2][1]->count_level_bonus_2+$lb_result[3][1]->count_level_bonus_3+$lb_result[4][1]->count_level_bonus_4);

				// 如果 member_bonusamount and member_bonuscount 都大於 0 才 update
				$update_bonus_sql = "
				UPDATE root_statisticsbonusagent SET
				updatetime = now(),
				member_bonus_1 = '".$b['member_bonus_1']."',
				member_bonus_2 = '".$b['member_bonus_2']."',
				member_bonus_3 = '".$b['member_bonus_3']."',
				member_bonus_4 = '".$b['member_bonus_4']."',
				member_bonuscount_1 = '".$b['member_bonuscount_1']."',
				member_bonuscount_2 = '".$b['member_bonuscount_2']."',
				member_bonuscount_3 = '".$b['member_bonuscount_3']."',
				member_bonuscount_4 = '".$b['member_bonuscount_4']."',
				member_bonuscount = '".$b['member_bonuscount']."',
				member_bonusamount = '".$b['member_bonusamount']."'
				WHERE member_account = '".$b['member_account']."' and dailydate_start = '".$b['dailydate_start']."' and dailydate_end = '".$b['dailydate_end']."';
				";

				if($argv_check == 'test'){
					var_dump($update_bonus_sql);
					$update_bonus_result = 1;
					//print_r($update_bonus_sql);
			 	}elseif($argv_check == 'run'){
					//echo $update_bonus_sql;
					$update_bonus_result = runSQL($update_bonus_sql);
			 	}
	      //var_dump($update_bonus_result);
	      if($update_bonus_result == 1) {
	        // 更新個人分紅收入累計 + 1
	        $stats_bonusamount_count++;
	        $logger = $logger.'Success 帳號'.$b['member_account']."日期: $current_datepicker_start ~ $current_datepicker".'更新加盟金收入欄位。\n';
	        // echo  $logger;
	      }else{
	        $logger = $logger.'False 帳號'.$b['member_account']."日期: $current_datepicker_start ~ $current_datepicker".'更新 DB table 的 member_bonuscount 及 member_bonusamount 欄位失敗,請聯絡開發人員處理。\n';
	        die($logger);
	      }

				// ------- bonus update log ------------------------
				// 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
				$percentage_html     = round(($i/$userlist[0]),2)*100;
				$process_record_html = "$i/$userlist[0]";
				$process_times_html  = round((microtime(true) - $program_start_time),3);
				$counting_r = $percentage_html%10;

				if($web_check == 1 AND $counting_r == 0){
					$output_sub_html  = $output_html.'<p align="center">'.$percentage_html.'%</p>';
					file_put_contents($reload_file,$output_sub_html);
				}elseif($web_check == 2 AND $counting_r == 0){
				  $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \''.$percentage_html.'\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
				  if($argv_check == 'test'){
				    echo $updatelog_sql;
				  }elseif($argv_check == 'run'){
				    $updatelog_result = runSQLall($updatelog_sql);
				  }
				}elseif($web_check == 0){
					if($percentage_html != $percentage_current) {
						if($counting_r == 0) {
							echo "\n目前處理 $current_datepicker 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
						}else{
							echo $percentage_html.'% ';
						}
						$percentage_current = $percentage_html;
					}
				}
			}else{
				$b['member_bonusamount'] = 'n/a';
				$b['member_bonuscount']  = 'n/a';
				$logger = '計算 member_bonuscount 及 member_bonusamount 欄位失敗,請聯絡開發人員處理。';
				die($logger);
			}
			// -------------------------------------------------
    }else{
      $logger = $logger.'False 帳號'.$b['member_account']."日期: $current_datepicker_start ~ $current_datepicker".'加盟金計算失敗，請聯絡開發人員處理。\n';
      die($logger);
    }
    //var_dump($b['member_bonusamount']);
    //var_dump($b['count_perforbouns_sumall']);
  }
}
// ----------------------------------------------------------------------------
// round 3. 輸出 CSV 檔
// ----------------------------------------------------------------------------
if($web_check == 1){
	$output_html  = '<p align="center">round 3. 輸出 CSV 檔 - 更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
	file_put_contents($reload_file,$output_html);
}elseif($web_check == 2){
	$updatlog_note = 'round 3. 輸出 CSV 檔 - 更新中';
	$updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'0\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
	if($argv_check == 'test'){
		echo $updatelog_sql;
	}elseif($argv_check == 'run'){
		$updatelog_result = runSQLall($updatelog_sql);
	}
}else{
	echo "round 3. 輸出 CSV 檔 - 開始\n";
}

// -------------------------------------
// 列出所有的會員資料及人數 SQL
// -------------------------------------
// 算 root_member 人數
$userlist_sql = "SELECT * FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' ORDER BY member_id ASC;";
// 取出 root_member 資料

// var_dump($userlist_sql);
$userlist = runSQLall($userlist_sql);

$b['dailydate_start'] = $current_datepicker_start;
$b['dailydate_end'] = $current_datepicker;

// 處理進度 % , 用來顯示紀錄進度。
$percentage_current = 0;

// 判斷 root_member count 數量大於 1
if($userlist[0] >= 1) {
	// 以會員為主要 key 依序列出每個會員的貢獻金額
	for($i = 1 ; $i <= $userlist[0]; $i++){
		// 存成 csv data
    $j = 0;

		// 資料庫內的 PK
		$csv_data['data'][$i][$j++] = $userlist[$i]->id;
		// 會員的 member ID
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_id;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_account;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_therole;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_parent_id;
		$csv_data['data'][$i][$j++] = $userlist[$i]->dailydate_start;
		$csv_data['data'][$i][$j++] = $userlist[$i]->dailydate_end;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_level;
		$csv_data['data'][$i][$j++] = $userlist[$i]->level_account_1;
		$csv_data['data'][$i][$j++] = $userlist[$i]->level_account_2;
		$csv_data['data'][$i][$j++] = $userlist[$i]->level_account_3;
		$csv_data['data'][$i][$j++] = $userlist[$i]->level_account_4;
		$csv_data['data'][$i][$j++] = $userlist[$i]->agency_commission;
		$csv_data['data'][$i][$j++] = $userlist[$i]->level_bonus_1;
		$csv_data['data'][$i][$j++] = $userlist[$i]->level_bonus_2;
		$csv_data['data'][$i][$j++] = $userlist[$i]->level_bonus_3;
		$csv_data['data'][$i][$j++] = $userlist[$i]->level_bonus_4;
		$csv_data['data'][$i][$j++] = $userlist[$i]->company_bonus;

		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonuscount_1;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonus_1;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonuscount_2;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonus_2;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonuscount_3;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonus_3;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonuscount_4;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonus_4;

		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonuscount;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonusamount;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonusamount_paid;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonusamount_paidtime;
		$csv_data['data'][$i][$j++] = $userlist[$i]->notes;

		// 不更新, 單純顯示
		//echo '不更新 show table data - '.$userlist[$i]->account.'<br>';
		$stats_showdata_count++;
		// 表格資料 row list end

		// ------- bonus update log ------------------------
		// 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
		$percentage_html     = round(($i/$userlist[0]),2)*100;
		$process_record_html = "$i/$userlist[0]";
		$process_times_html  = round((microtime(true) - $program_start_time),3);
		$counting_r = $percentage_html%10;

		if($web_check == 1 AND $counting_r == 0){
			$output_sub_html  = $output_html.'<p align="center">'.$percentage_html.'%</p>';
			file_put_contents($reload_file,$output_sub_html);
		}elseif($web_check == 2 AND $counting_r == 0){
		  $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \''.$percentage_html.'\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
		  if($argv_check == 'test'){
		    echo $updatelog_sql;
		  }elseif($argv_check == 'run'){
		    $updatelog_result = runSQLall($updatelog_sql);
		  }
		}elseif($web_check == 0){
			if($percentage_html != $percentage_current) {
				if($counting_r == 0) {
					echo "\n目前處理 $current_datepicker 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
				}else{
					echo $percentage_html.'% ';
				}
				$percentage_current = $percentage_html;
			}
		}
		// -------------------------------------------------

	}
	// end of for
	// ----------------------------------------------------
}

// -------------------------------------------
// 計算系統統合性摘要 - summary
// -------------------------------------------

// 此時間範圍
$csv_data['summary']['時間範圍'] = "$current_datepicker_start ~ $current_datepicker";
// 此時間範圍的會員人數
$csv_data['summary']['時間範圍的會員人數'] = $userlist_count;

// 組織代理加盟金的總額
// 組織代理加盟金的筆數
$summary_agency_commission_sql =  "SELECT sum(agency_commission) as sum_agency_commission, count(agency_commission) as count_agency_commission
FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND agency_commission > 0;";
$summary_agency_commission_result = runSQLall($summary_agency_commission_sql);
$csv_data['summary']['加盟金的總額']  = $summary_agency_commission_result[1]->sum_agency_commission;
$csv_data['summary']['加盟金的筆數']  = $summary_agency_commission_result[1]->count_agency_commission;

// 組織代理個人分紅的最多和最少
// 組織代理分紅的有分配到的個人人數
// 組織代理分紅的的有分配到的個人餘額合計
$summary_member_bonusamount_sql = "SELECT sum(member_bonusamount) as sum_member_bonusamount , count(member_bonusamount) as count_member_bonusamount
, max(member_bonusamount) as max_member_bonusamount, min(member_bonusamount) as min_member_bonusamount, sum(member_bonuscount) as sum_member_bonuscount
FROM root_statisticsbonusagent
WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND member_bonusamount > 0;";
$summary_member_bonusamount_result = runSQLall($summary_member_bonusamount_sql);
$csv_data['summary']['個人分紅的最多']               = $summary_member_bonusamount_result[1]->max_member_bonusamount;
$csv_data['summary']['個人分紅的最少']               = $summary_member_bonusamount_result[1]->min_member_bonusamount;
$csv_data['summary']['分紅的有分配到的個人人數']      = $summary_member_bonusamount_result[1]->count_member_bonusamount;
$csv_data['summary']['分紅的有分配到的個人餘額合計']    = $summary_member_bonusamount_result[1]->sum_member_bonusamount;
$csv_data['summary']['分紅的有分配到的個人餘額筆數合計'] = $summary_member_bonusamount_result[1]->sum_member_bonuscount;

//  組織代理分紅公司分配收入合計
$sum_company_bonus_sql = "SELECT sum(company_bonus) as sum_company_bonus FROM root_statisticsbonusagent
WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND company_bonus > 0;";
$sum_company_bonus_result = runSQLall($sum_company_bonus_sql);
$csv_data['summary']['分紅的公司收入合計'] = $sum_company_bonus_result[1]->sum_company_bonus;

// 組織代理加盟金的沒有分配到的總和餘額(n/a內容)
// 組織代理加盟金的沒有分配到的總和筆數(n/a內容)
$lb_sql[1]     = "SELECT count(level_bonus_1) as count_level_bonus_1 ,sum(level_bonus_1) as sum_level_bonus_1
FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
AND level_bonus_1 > 0 AND level_account_1= 'n/a';";
$lb_result[1]  = runSQLall($lb_sql[1]);
$lb_sql[2]     = "SELECT count(level_bonus_2) as count_level_bonus_2 ,sum(level_bonus_2) as sum_level_bonus_2
FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
AND level_bonus_2 > 0 AND level_account_2= 'n/a';";
$lb_result[2]  = runSQLall($lb_sql[2]);
$lb_sql[3]     = "SELECT count(level_bonus_3) as count_level_bonus_3 ,sum(level_bonus_3) as sum_level_bonus_3
FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
AND level_bonus_3 > 0 AND level_account_3= 'n/a';";
$lb_result[3]  = runSQLall($lb_sql[3]);
$lb_sql[4]     = "SELECT count(level_bonus_4) as count_level_bonus_4 ,sum(level_bonus_4) as sum_level_bonus_4
FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
AND level_bonus_4 > 0 AND level_account_4= 'n/a';";
$lb_result[4]  = runSQLall($lb_sql[4]);
//var_dump($lb_sql);
//var_dump($lb_result);
if($lb_result[1][0] == 1 AND $lb_result[2][0] == 1 AND $lb_result[3][0] == 1 AND $lb_result[4][0] == 1){
	$csv_data['summary']['沒有分配到的總和餘額'] = $lb_result[1][1]->sum_level_bonus_1+$lb_result[2][1]->sum_level_bonus_2+$lb_result[3][1]->sum_level_bonus_3+$lb_result[4][1]->sum_level_bonus_4;
	$csv_data['summary']['沒有分配到的總和筆數'] = $lb_result[1][1]->count_level_bonus_1+$lb_result[2][1]->count_level_bonus_2+$lb_result[3][1]->count_level_bonus_3+$lb_result[4][1]->count_level_bonus_4;
}else{
	$csv_data['summary']['沒有分配到的總和餘額'] = NULL;
	$csv_data['summary']['沒有分配到的總和筆數'] = NULL;
}

$t=0;
$csv_data['summary_title'][$t++] ='時間範圍';
$csv_data['summary_title'][$t++] ='時間範圍的會員人數';
$csv_data['summary_title'][$t++] ='加盟金的總額';
$csv_data['summary_title'][$t++] ='加盟金的筆數';
$csv_data['summary_title'][$t++] ='個人分紅的最多';
$csv_data['summary_title'][$t++] ='個人分紅的最少';
$csv_data['summary_title'][$t++] ='分紅的有分配到的個人人數';
$csv_data['summary_title'][$t++] ='分紅的有分配到的個人餘額合計';
$csv_data['summary_title'][$t++] ='分紅的有分配到的個人餘額筆數合計';
$csv_data['summary_title'][$t++] ='分紅的公司收入合計';
$csv_data['summary_title'][$t++] ='沒有分配到的總和餘額';
$csv_data['summary_title'][$t++] ='沒有分配到的總和筆數';

// -------------------------------------------
// 寫入 CSV 檔案的抬頭
// -------------------------------------------
$t = 0;
$csv_data['table_colname'][$t++] = 'ID_PK';
$csv_data['table_colname'][$t++] = '會員ID';
$csv_data['table_colname'][$t++] = '帳號';
$csv_data['table_colname'][$t++] = '會員身份';
$csv_data['table_colname'][$t++] = '會員上層ID';
$csv_data['table_colname'][$t++] = '結束日期';
$csv_data['table_colname'][$t++] = '開始日期';
$csv_data['table_colname'][$t++] = '所在層數';
$csv_data['table_colname'][$t++] = '上層第1代';
$csv_data['table_colname'][$t++] = '上層第2代';
$csv_data['table_colname'][$t++] = '上層第3代';
$csv_data['table_colname'][$t++] = '上層第4代';
$csv_data['table_colname'][$t++] = '組織代理加盟金';
$csv_data['table_colname'][$t++] = '第1代分紅';
$csv_data['table_colname'][$t++] = '第2代分紅';
$csv_data['table_colname'][$t++] = '第3代分紅';
$csv_data['table_colname'][$t++] = '第4代分紅';
$csv_data['table_colname'][$t++] = '公司分紅收入';
$csv_data['table_colname'][$t++] = '個人第1代分傭筆數';
$csv_data['table_colname'][$t++] = '個人第1代分傭累計';
$csv_data['table_colname'][$t++] = '個人第2代分傭筆數';
$csv_data['table_colname'][$t++] = '個人第2代分傭累計';
$csv_data['table_colname'][$t++] = '個人第3代分傭筆數';
$csv_data['table_colname'][$t++] = '個人第3代分傭累計';
$csv_data['table_colname'][$t++] = '個人第4代分傭筆數';
$csv_data['table_colname'][$t++] = '個人第4代分傭累計';
$csv_data['table_colname'][$t++] = '個人分紅筆數';
$csv_data['table_colname'][$t++] = '個人分紅合計';
$csv_data['table_colname'][$t++] = '個人已發放金額';
$csv_data['table_colname'][$t++] = '分紅發放時間';
$csv_data['table_colname'][$t++] = '備註';
//var_dump($csv_data);

// -------------------------------------------
// 將內容輸出到 檔案 , csv format
// -------------------------------------------

// 有資料才執行 csv 輸出, 避免 insert or update or stats 生成同時也執行 csv 輸出
if(isset($csv_data['data'])) {

	$filename      = "bonusagent_result_".$current_datepicker_start.'_'.$current_datepicker.'.csv';
  $absfilename    = dirname(__FILE__) ."/tmp_dl/$filename";
	$filehandle     = fopen("$absfilename","w");
	if($filehandle!=false) {
    // Windows下使用BOM来标记文本文件的编码方式, 否則 EXCEL 開啟這個檔案會是亂碼
    fwrite($filehandle,chr(0xEF).chr(0xBB).chr(0xBF));
    // -------------------------------------------
    // 將資料輸出到檔案 -- Summary
    fputcsv($filehandle, $csv_data['summary_title']);
    fputcsv($filehandle, $csv_data['summary']);

    // 將資料輸出到檔案 -- Title
    fputcsv($filehandle, $csv_data['table_colname']);
    // 將資料輸出到檔案 -- data
    foreach ($csv_data['data'] as $fields) {
      fputcsv($filehandle, $fields);
    }
    // 將資料輸出到檔案 -- Title
    fputcsv($filehandle, $csv_data['table_colname']);

    fclose($filehandle);
    // -------------------------------------------
    // 下載按鈕
    // -------------------------------------------
    if(file_exists($absfilename)) {
      $csv_download_url_html = '<a href="./tmp_dl/'.$filename.'" class="btn btn-success" >下載CSV</a>';
    }else{
      $csv_download_url_html = '';
    }
  }else{
    // var_dump($absfilename);
    $csv_download_url_html = '檔案'.$absfilename.'開啟錯誤，請檢查權限設定。';
  }
}
// -------------------------------------------
// 將內容輸出到 檔案 , csv format  END
// -------------------------------------------

// --------------------------------------------
// MAIN END
// --------------------------------------------

// ----------------------------------------------------------------------------
// 統計結果
// ----------------------------------------------------------------------------
$run_report_result = "
  此區間總會員人數 = $userlist_count 人 ,\n
  CSV統計顯示的資料 =  $stats_showdata_count ,\n
  統計此時間區間插入(Insert)的資料 =  $stats_insert_count ,\n
  統計營運利潤獎金投注量資料更新(Update)   =  $stats_update_count ,\n
  統計個人營運利潤獎金更新(Update) =  $stats_bonusamount_count";

// 算累積花費時間
$program_end_time =  microtime(true);
$program_time = $program_end_time-$program_start_time;
$logger = $run_report_result."\n累積花費時間: ".$program_time ." \n";
if($web_check == 1){

	$dellogfile_js = '
	<script src="in/jquery/jquery.min.js"></script>
	<script type="text/javascript" language="javascript" class="init">
	function dellogfile(){
		$.get("bonus_commission_agent_action.php?a=agentbonus_del&k='.$file_key.'",
		function(result){
			window.close();
		});
	}
	</script>';

	$logger_html = nl2br($logger).'<br><br><p align="center"><button type="button" onclick="dellogfile();">關閉視窗</button></p><script>alert(\'已完成資料更新！\');</script>'.$dellogfile_js;
	file_put_contents($reload_file,$logger_html);
}elseif($web_check == 2){
	$updatlog_note = nl2br($logger);
	$updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'1000\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
	if($argv_check == 'test'){
		echo $updatelog_sql;
	}elseif($argv_check == 'run'){
		$updatelog_result = runSQLall($updatelog_sql);
	}
}else{
	echo $logger;
}
// --------------------------------------------
// 統計結果 END
// --------------------------------------------

?>
