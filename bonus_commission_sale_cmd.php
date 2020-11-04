#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 營業獎金 -- 獨立排程執行
// File Name:	cron/bonus_commission_sale_cmd.php
// Author:		Barkley,Fix by Ian
// Related:	bonus_commission_sale.php
// 					bonus_commission_sale_action.php
// 				DB table:  root_statisticsdailyreport 每日統計報表
// 				DB table:  root_statisticsbonussale 營運獎金報表, 資料由每日統計報表生成, 並且計算後輸出到此表.
// Desc: 所有站台的 MG 投注紀錄檔案都使用這個程式, 此程式無須依附其他程式，可以獨立寫入資料庫。
//			此程式配合投注紀錄資料庫, 收集來自 CASINO 的投注紀錄.
// Log:
// 將營運日報的資料，整理成為會員獎金分紅的報表，並且輸出成為資料表存放。
// ----------------------------------------------------------------------------
// 程式開發的邏輯：
// -------------------------------------------------------------------------
// 透過一個指定的時間區間, 依據會員資料 root_member 搜尋日結報表的資料
// 依據 root_member 的上下階層關係, 列出每個會員的每一個上一代, 一直到 root
// 每一個會員的投注資訊, 透過日結報表統計指定的時間區間取得完整資訊，以利列出符合條件的會員(代理商)
// 依據每個會員日結報表資料(日結報表資料需要完整, 會員不存在的處理), 統計出來分配金額
// -------------------------------------------------------------------------
// How to run ?
// usage command line : /usr/bin/php70 bonus_commission_sale_cmd.php test/run 2017-06-06
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// session_start();

// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

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

// -------------------------------------------------------------------------
// 1.1 以節點找出使用者的資料 -- from root_member
// -------------------------------------------------------------------------
function find_member_node($member_id, $tree_level, $current_datepicker_start, $current_datepicker, $find_parent) {
	global $timeover;
	// 加上 memcached 加速,宣告使用 memcached 物件 , 連接看看. 不能連就停止.
	$memcache = new Memcached();
	$memcache->addServer('localhost', 11211) or die ("Could not connect memcache server !! ");
	// 把 query 存成一個 key in memcache
	$key = $member_id.$current_datepicker_start.$current_datepicker;
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
			$member_sql = 'SELECT member_id as id, member_account as account, member_parent_id as parent_id,member_therole as therole FROM root_statisticsbonussale
			WHERE  member_id = \''.$member_id.'\' AND dailydate_start =\''.$current_datepicker_start.'\' AND dailydate_end =\''.$current_datepicker.'\';';
			//var_dump($member_sql);
			$member_result = runSQLall($member_sql);
		}else{
			//$tree_level = 1; 不論 status 為何, 都要找出來. 否則會 lost data 問題.
			$member_sql = "SELECT id, account, parent_id, therole FROM root_member WHERE id = '$member_id';";
			//var_dump($member_sql);
			$member_result = runSQLall($member_sql);
		}
		//var_dump($member_result);
		if($member_result[0]==1){
			//var_dump($member_result);
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

	// 最大層數
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
// find member tree to root , END
// -------------------------------------------------------------------------


// -------------------------------------------
// (1) 總和投注量的 SQL , 傳入 member_id and date range
// -------------------------------------------
function sum_all_bets_amount($member_id, $current_datepicker_start, $current_datepicker ) {
  $sql = "SELECT sum(all_bets) as sum_all_bets ,sum(all_count) as sum_all_count FROM root_statisticsdailyreport
  	WHERE dailydate >= '$current_datepicker_start' AND dailydate <= '$current_datepicker' AND member_id = '".$member_id."';";
  // var_dump($sql);
  $all_bets_amount_result = runSQLall($sql);
  // var_dump($all_bets_amount_result);

  if($all_bets_amount_result[0] == 1) {
    $all_bets_amount = $all_bets_amount_result[1];
    // 有可能會傳會 null, 表示有帳號, 但是沒有投注紀錄
  }else{
    $all_bets_amount = false;
  }
  return($all_bets_amount);
}
// -------------------------------------------


// -------------------------------------------
// bonus_commission_sale_data($userlist, $current_datepicker_start, $current_datepicker) {
// $userlist 陣列 member data
// $current_datepicker_start
// $current_datepicker
// 條列每個會員的營業資料及分紅金額
// -------------------------------------------
function bonus_commission_sale_data($userlist, $current_datepicker_start, $current_datepicker) {
	// 變數
	global $rule;
	// var_dump($userlist);

	// -------------------------------------------
	// 會員的資料資訊
	// -------------------------------------------
	$b['member_id']              = $userlist->id;
	$b['member_account']         = $userlist->account;
	$b['member_parent_id']       = $userlist->parent_id;
	$b['member_therole']         = $userlist->therole;
	$b['dailydate_start']        = $current_datepicker_start;
	$b['dailydate_end']          = $current_datepicker;

	// 在第一次更新時,忽略這些欄位
	$b['member_bonusamount_1']  = 'n/a';
	$b['member_bonuscount_1']   = 'n/a';
	$b['member_bonusamount_2']  = 'n/a';
	$b['member_bonuscount_2']   = 'n/a';
	$b['member_bonusamount_3']  = 'n/a';
	$b['member_bonuscount_3']   = 'n/a';
	$b['member_bonusamount_4']  = 'n/a';
	$b['member_bonuscount_4']   = 'n/a';

	$b['member_bonusamount']          = 'n/a';
	$b['member_bonusamount_count']    = 'n/a';
	$b['member_bonusamount_paid']     = 'n/a';
	// 這兩個變數由付款時候再更新
	$b['member_bonusamount_paid']     = 'n/a';
	$b['member_bonusamount_paidtime'] = 'n/a';
	$b['notes']                       = 'n/a';


	// -------------------------------------------
	// 找出會員所在的 tree 直到 root
	// -------------------------------------------
	$tree = find_parent_node($userlist->id,$current_datepicker_start, $current_datepicker);
	//var_dump($tree);

	// -------------------------------------------
	// 將原始的 $tree 轉換為--> 已經達標的 $ptree , 並且紀錄過程中被 skip 的代理商。
	// -------------------------------------------
	$skip_agent_tree_list = null;
	$skip_agent_tree = null;
	$level = 0;
	$plevel = 0;
	$ptree[$userlist->id][$plevel] = $tree[$userlist->id][$level];
	$plevel++;
	// 此 node member 有幾階層 , array 數量 -1 , 因為 0 開始
	$member_tree_level_number = count($tree[$userlist->id])-1;
	$b['member_level'] = $member_tree_level_number;
	for($level=1;$level<=$member_tree_level_number;$level++){
			// root 就跳出了, 表示到頂 root 了!!
		if($tree[$userlist->id][$level]->account == 'root') {
			break;
		}else{
			// 當 sum_all_bets 條件符合 $rule['amountperformance'] 時，才可以列為分紅代
			if($tree[$userlist->id][$level]->sum_all_bets >= $rule['amountperformance']) {
				$ptree[$userlist->id][$plevel] = $tree[$userlist->id][$level];
				$plevel++;
			}else{
				// 沒有達標 被跳過的代理商，以及再哪一個會員，那一代。
				// 在哪一個會員
				//var_dump($userlist[$i]->id);
				//var_dump($userlist[$i]->account);
				// 在哪一個會員的那一代
				//var_dump($level);
				//var_dump($tree[$userlist[$i]->id][$level]);
				// 在哪一個會員的那一代跳過了那個代理商
				//var_dump($tree[$userlist[$i]->id][$level]->id);
				//var_dump($tree[$userlist[$i]->id][$level]->account);
				// 完整的樹狀資訊
				//var_dump($tree[$userlist[$i]->id]);
				// 將被跳過得代理商資訊存下來，以作為行銷的回饋。
				$skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['agentid'] = $tree[$userlist->id][$level]->id;
				$skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['agentaccount'] = $tree[$userlist->id][$level]->account;
				$skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['memberid'] = $userlist->id;
				$skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['memberaccount'] = $userlist->account;
				$skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['level'] = $level;
				$skip_agent_tree[$userlist->id][$tree[$userlist->id][$level]->id]['sum_all_bets'] = $tree[$userlist->id][$level]->sum_all_bets;
				// 被跳過的 agent , 簡易描述資料文字，預計 save in DB
				// 代理商:會員:層級:代理商投注量
				if($level == 1) {
					$skip_agent_tree_list = $userlist->account.':'.$tree[$userlist->id][$level]->account.':'.$level.':'.$tree[$userlist->id][$level]->sum_all_bets;
				}else{
					$skip_agent_tree_list = $skip_agent_tree_list.','.$userlist->account.':'.$tree[$userlist->id][$level]->account.':'.$level.':'.$tree[$userlist->id][$level]->sum_all_bets;
				}
			}
		}
	}
	// -------------------------------------------
	// 被跳過的代理商, 那個代理商在哪一個會員的那一代,金額是
	//var_dump($skip_agent_tree);
	//var_dump($skip_agent_tree_list);

	// -------------------------------------------
	// 被跳過的代理商 count
	// -------------------------------------------
	$skip_agent_tree_count = (is_array($skip_agent_tree[$userlist->id])) ?  count($skip_agent_tree[$userlist->id]) : '0';
	$b['skip_agent_tree_count'] = $skip_agent_tree_count;
	$skip_agent_tree_html = '<a href="#" title="'.$skip_agent_tree_list.'" >'.$skip_agent_tree_count.'</a>';
	$b['skip_bonusinfo'] = $skip_agent_tree_count.':'.$skip_agent_tree_list;

	// 達標的代理商
	// var_dump($ptree);
	// -------------------------------------------
	// 達標的會員第1層
	// -------------------------------------------
	$pti = 1;
	if(isset($ptree[$userlist->id][$pti]->account)) {
		// 達標代數會員帳號
		$ptree_member_html[$pti] = $ptree[$userlist->id][$pti]->account;
		// 達標者身份
		$ptree_member_therole_html[$pti] = $ptree[$userlist->id][$pti]->therole;
	}else{
		$ptree_member_html[$pti] = 'n/a';
		$ptree_member_therole_html[$pti] = 'n/a';
	}
	$b['perforaccount_1'] = $ptree_member_html[$pti];
	$b['ptree_member_therole_1'] = $ptree_member_therole_html[$pti];

	// 達標的會員第2層
	$pti = 2;
	if(isset($ptree[$userlist->id][$pti]->account)) {
		$ptree_member_html[$pti] = $ptree[$userlist->id][$pti]->account;
		$ptree_member_therole_html[$pti] = $ptree[$userlist->id][$pti]->therole;
	}else{
		$ptree_member_html[$pti] = 'n/a';
		$ptree_member_therole_html[$pti] = 'n/a';
	}
	$b['perforaccount_2'] = $ptree_member_html[$pti];
	$b['ptree_member_therole_2'] = $ptree_member_therole_html[$pti];

	// 達標的會員第3層
	$pti = 3;
	if(isset($ptree[$userlist->id][$pti]->account)) {
		$ptree_member_html[$pti] = $ptree[$userlist->id][$pti]->account;
		$ptree_member_therole_html[$pti] = $ptree[$userlist->id][$pti]->therole;
	}else{
		$ptree_member_html[$pti] = 'n/a';
		$ptree_member_therole_html[$pti] = 'n/a';
	}
	$b['perforaccount_3'] = $ptree_member_html[$pti];
	$b['ptree_member_therole_3'] = $ptree_member_therole_html[$pti];

	// 達標的會員第4層
	$pti = 4;
	if(isset($ptree[$userlist->id][$pti]->account)) {
		$ptree_member_html[$pti] = $ptree[$userlist->id][$pti]->account;
		$ptree_member_therole_html[$pti] = $ptree[$userlist->id][$pti]->therole;
	}else{
		$ptree_member_html[$pti] = 'n/a';
		$ptree_member_therole_html[$pti] = 'n/a';
	}
	$b['perforaccount_4'] = $ptree_member_html[$pti];
	$b['ptree_member_therole_4'] = $ptree_member_therole_html[$pti];

	// var_dump($ptree);
	/*
	array (size=1)
		132 =>
			array (size=2)
				0 =>
					object(stdClass)[16]
						public 'id' => int 132
						public 'account' => string 'tfa2u125n9' (length=10)
						public 'parent_id' => int 125
						public 'therole' => string 'A' (length=1)
						public 'level' => int 0
						public 'sum_date_start' => string '2017-02-05' (length=10)
						public 'sum_date_end' => string '2017-02-12' (length=10)
						public 'sum_all_bets' => string '65014.03' (length=8)
						public 'count_all_bets' => int 6
				1 =>
					object(stdClass)[2]
						public 'id' => int 125
						public 'account' => string 'tfa1u122n2' (length=10)
						public 'parent_id' => int 122
						public 'therole' => string 'A' (length=1)
						public 'level' => int 0
						public 'sum_date_start' => string '2017-02-05' (length=10)
						public 'sum_date_end' => string '2017-02-12' (length=10)
						public 'sum_all_bets' => string '92428.01' (length=8)
						public 'count_all_bets' => int 6
	*/
	// 此寫法為了閱讀 array 方便, 第一個 index 使用了 member ID
	// 所以每次進入 loop 需要 free 記憶體, 否則筆數資料一多, 就會記憶體不足。
	unset($tree);
	unset($ptree);
	unset($skip_agent_tree);
	// -------------------------------------------


	// -------------------------------------------
	// 投注單 sum_all_bets_amount()
	// -------------------------------------------
	$all_bets_amount_html = '';
	$all_bets_amount = sum_all_bets_amount($userlist->id, $current_datepicker_start, $current_datepicker);
	//var_dump($all_bets_amount);
	if($all_bets_amount != false){
		$all_bets_amount_html = $all_bets_amount->sum_all_bets;
	}else{
		$all_bets_amount_html = 0 ;
	}
	// 檢查萬一是 null  , 要設定為 0
	if( $all_bets_amount->sum_all_bets == NULL) {
		$b['all_betsamount'] = 0;
	}else{
		$b['all_betsamount'] = $all_bets_amount->sum_all_bets;
	}
	if($all_bets_amount->sum_all_count == NULL) {
		$b['all_betscount'] = 0;
	}else{
		$b['all_betscount'] = $all_bets_amount->sum_all_count;
	}


	// -------------------------------------------
	// 營業獎金分紅額度
	// -------------------------------------------
	// 分紅比率 in title
	$sale_bonus_rate_amount     = $rule['sale_bonus_rate'].htmlspecialchars("%", ENT_QUOTES);;
	// 可分紅金額 -- 營業獎金分紅額度 2%
	$sale_bonus_rate_allamount  = round(($all_bets_amount_html*$rule['sale_bonus_rate']/100), 2);
	$b['perfor_bounsamount']    = $sale_bonus_rate_allamount;

	// 營業獎金分紅額度 - 第1代
	$sale_bonus_rate_amount_1 = round(($sale_bonus_rate_allamount*$rule['commission_1_rate']/100), 2);
	$b['perforbouns_1']       = $sale_bonus_rate_amount_1;
	// 營業獎金分紅額度 - 第2代
	$sale_bonus_rate_amount_2 = round(($sale_bonus_rate_allamount*$rule['commission_2_rate']/100), 2);
	$b['perforbouns_2']       = $sale_bonus_rate_amount_2;
	// 營業獎金分紅額度 - 第3代
	$sale_bonus_rate_amount_3 = round(($sale_bonus_rate_allamount*$rule['commission_3_rate']/100), 2);
	$b['perforbouns_3']       = $sale_bonus_rate_amount_3;
	// 營業獎金分紅額度 - 第4代
	$sale_bonus_rate_amount_4 = round(($sale_bonus_rate_allamount*$rule['commission_4_rate']/100), 2);
	$b['perforbouns_4']       = $sale_bonus_rate_amount_4;
	// 營業獎金分紅額度 - 公司分配
	$sale_bonus_rate_amount_root = round(($sale_bonus_rate_allamount*$rule['commission_root_rate']/100), 2);
	$b['perforbouns_root']    = $sale_bonus_rate_amount_root;

return($b);
}
// ----------------------------------------------------
// end of function  條列每個會員的營業資料及分紅金額
// ----------------------------------------------------


// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) == $date;
}

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

// 預設星期幾為預設 7 天的起始週期
$weekday = 	$rule['stats_weekday'];
//
if(isset($argv[1]) AND ($argv[1] == 'test' OR $argv[1] == 'run') ){
  if(isset($argv[2]) AND validateDate($argv[2], 'Y-m-d') ){
		$orig_datepicker = $argv[2];
		if($argv[2] <= $current_date){
	    //如果有的話且格式正確, 取得日期. 沒有的話中止
	    $current_datepicker = $argv[2];
	    // 日期如果大於今天的話，就以今天當週為日期。
	    if(strtotime($current_datepicker) <= strtotime(date("Y-m-d"))) {
	      // default 抓取指定日期最近的星期三, 超過指定日期，的星期三.
	      $current_datepicker = date('Y-m-d' ,strtotime("$weekday",strtotime($current_datepicker)));
	    }else{
	      // default 抓取最近的星期三
	      $current_datepicker = date('Y-m-d' ,strtotime("$weekday"));
	    }
		}else{
	    // default 抓取最近的星期三
	    $current_datepicker = date('Y-m-d' ,strtotime("$weekday"));
		}
  }else{
    // default 抓取最近的星期三
    $current_datepicker = date('Y-m-d' ,strtotime("$weekday"));
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
	$file_key = sha1('salebonus'.$orig_datepicker);
	$reload_file = dirname(__FILE__) .'/tmp_dl/salebonus_'.$file_key.'.tmp';
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

$stats_bonus_days         = $rule['stats_bonus_days']-1;
$current_datepicker_start = date( "Y-m-d", strtotime( "$current_datepicker -$stats_bonus_days day"));
$bonus_days_end_timestamp = strtotime($current_datepicker);
// 取出 root_member 資料
/*$userlist_sql   = "WITH memberlists AS
	(SELECT *,to_char((enrollmentdate AT TIME ZONE 'AST'),'YYYY-MM-DD') AS enrollmentdate_edt FROM root_member)
	SELECT * FROM memberlists WHERE enrollmentdate_edt <= '$current_datepicker' OR enrollmentdate_edt IS NULL ORDER BY id ASC;";*/
if($current_date_timestamp <= $bonus_days_end_timestamp){
	$userlist_sql = "SELECT id,account,parent_id,therole FROM root_member WHERE enrollmentdate <= '$current_datepicker_gmt' OR enrollmentdate IS NULL ORDER BY id ASC;";
	$timeover = 0;
	$userlist       = runSQLall($userlist_sql);
}else{
	$userlist_sql = 'SELECT member_id as id, member_account as account, member_parent_id as parent_id,member_therole as therole FROM root_statisticsbonussale
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
// 統計插入的資料有多少
$stats_insert_count = 0;
// 統計 update 的資料有多少
$stats_update_count = 0;
// 統計更新個人結算的資料有多少。
$stats_bonusamount_count = 0;
// 統計顯示的資料有多少
$stats_showdata_count = 0;
// 判斷 root_member count 數量大於 1 , 有會員資料的話才繼續
// 20171103 -- 加上時間判斷，如果現在時間已超過結算日，則不再更新會員資料，
// 				以免會員樹結構變更造成錯帳
if($userlist[0] >= 1) {

  // 會員有資料，且存在數量為 $userlist_count
  // ----------------------------------------------------
  // 以會員資料為主要 key 依序列出每個會員的貢獻金額
  for($i = 1 ; $i <= $userlist_count ; $i++){
    // 判斷此 member_account 是否存在 DB 中,沒有的話 insert DATA
    // 有的話直接取出 data
    // 如果有資料, 但是 $update_bonussale_status 設定為更新營業資料的旗標的話, 執行 update data 行為
    // 如果有資料, 且數量和 member 數量同的話. 當 $update_bonussale_amount_status 旗標為 true 時重新計算填入個人分紅的表單
    $getdata_bonussale_sql = "SELECT * FROM root_statisticsbonussale
    WHERE member_account = '".$userlist[$i]->account."' AND dailydate_start ='".$current_datepicker_start."' AND dailydate_end ='".$current_datepicker."' ;";
    //var_dump($getdata_bonussale_sql);
    $getdata_bonussale_result = runSQLall($getdata_bonussale_sql);

		// 指定的日期 + 會員 , 沒有資料的狀況處理
		if($getdata_bonussale_result[0] == 0) {
			// data 取得資料 , 從日報即時計算, 速度較慢.判斷資料是否全部都有取得.
			$b = bonus_commission_sale_data($userlist[$i],$current_datepicker_start, $current_datepicker);
			//var_dump($b);

			// 沒資料 insert
			$insert_sql = 'INSERT INTO "root_statisticsbonussale" ("member_id","member_account", "member_parent_id", "member_therole", "updatetime", "dailydate_start", "dailydate_end", "member_level", "skip_bonusinfo"
				, "perforaccount_1", "perforaccount_2", "perforaccount_3", "perforaccount_4", "all_betsamount", "all_betscount"
				, "perfor_bounsamount", "perforbouns_1", "perforbouns_2", "perforbouns_3", "perforbouns_4", "perforbouns_root")'.
			"VALUES ('".$b['member_id']."', '".$b['member_account']."', '".$b['member_parent_id']."', '".$b['member_therole']."', now(), '".$b['dailydate_start']."', '".$b['dailydate_end']."', '".$b['member_level']."', '".$b['skip_bonusinfo']."'
			, '".$b['perforaccount_1']."', '".$b['perforaccount_2']."', '".$b['perforaccount_3']."', '".$b['perforaccount_4']."', '".$b['all_betsamount']."', '".$b['all_betscount']."'
			, '".$b['perfor_bounsamount']."', '".$b['perforbouns_1']."', '".$b['perforbouns_2']."', '".$b['perforbouns_3']."', '".$b['perforbouns_4']."', '".$b['perforbouns_root']."');";

			if($argv_check == 'test'){
				var_dump($insert_sql);
				$bonussale_result = 1;
				//print_r($insert_sql);
			}elseif($argv_check == 'run'){
				$bonussale_result = runSQL($insert_sql);
			}

			if($bonussale_result == 1) {
				//var_dump($bonussale_result);
				//echo 'no data to insert - '.$b['member_account'].'<br>';
				$stats_insert_count++;
			}else{
				$logger = "$current_datepicker_start ~ $current_datepicker".'會員 '.$userlist[$i]->account.' 插入資料有問題，請聯絡開發人員處理。';
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
			// 指定的會員 + 日期, 內有資料的狀態選擇
			// var_dump($getdata_bonussale_result[1]->member_account);
			// 是否從每日營收報表, 更新統計資料到 root_statisticsbonussale
	    // 有資料的狀態選擇,更新raw資料
	    // data 取得資料 , 從日報即時計算, 速度較慢.判斷資料是否全部都有取得.
	    $b = bonus_commission_sale_data($userlist[$i],$current_datepicker_start, $current_datepicker);
	    //var_dump($b);

	    // 檢查是否有 count 資料
	    // echo 'Update 已經存在的資料';

	    $update_sql = "
	    UPDATE root_statisticsbonussale SET
	    member_id = '".$b['member_id']."',
	    member_account = '".$b['member_account']."',
	    member_parent_id = '".$b['member_parent_id']."',
	    member_therole = '".$b['member_therole']."',
	    updatetime = NOW(),
	    dailydate_start = '".$b['dailydate_start']."',
	    dailydate_end = '".$b['dailydate_end']."',
	    member_level = '".$b['member_level']."',
	    skip_bonusinfo = '".$b['skip_bonusinfo']."',
	    perforaccount_1 = '".$b['perforaccount_1']."',
	    perforaccount_2 = '".$b['perforaccount_2']."',
	    perforaccount_3 = '".$b['perforaccount_3']."',
	    perforaccount_4 = '".$b['perforaccount_4']."',
	    all_betsamount = '".$b['all_betsamount']."',
	    all_betscount = '".$b['all_betscount']."',
	    perfor_bounsamount = '".$b['perfor_bounsamount']."',
	    perforbouns_1 = '".$b['perforbouns_1']."',
	    perforbouns_2 = '".$b['perforbouns_2']."',
	    perforbouns_3 = '".$b['perforbouns_3']."',
	    perforbouns_4 = '".$b['perforbouns_4']."',
	    perforbouns_root = '".$b['perforbouns_root']."'
	    WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND member_account = '".$userlist[$i]->account."';
	    ";

			if($argv_check == 'test'){
				var_dump($update_sql);
				$update_result = 1;
		    //print_r($update_sql);
			}elseif($argv_check == 'run'){
				$update_result = runSQL($update_sql);
			}

	    //var_dump($update_result);
	    if($update_result == 1) {
	      // echo '更新統計資料 - '.$b['member_account'].'<br>';
	      $stats_update_count++;
	    }else{
	      $logger = "$current_datepicker_start ~ $current_datepicker".'會員 '.$b['member_account'].'更新統計資料有問題，請聯絡開發人員處理。';
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
}else{
	die('無資料或資料庫錯誤！');
}

// ----------------------------------------------------------------------------
// round 2. 更新會員營業獎金
// ----------------------------------------------------------------------------

if($web_check == 1){
	$output_html  = '<p align="center">round 2. 更新會員營業獎金資料 - 更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
	file_put_contents($reload_file,$output_html);
}elseif($web_check == 2){
	$updatlog_note = 'round 2. 更新會員營業獎金資料 - 更新中';
	$updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'0\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
	if($argv_check == 'test'){
		echo $updatelog_sql;
	}elseif($argv_check == 'run'){
		$updatelog_result = runSQLall($updatelog_sql);
	}
}else{
	echo "round 2. 更新會員營業獎金資料 - 開始\n";
}

$userlist_sql = 'SELECT member_account as account,member_id as id FROM root_statisticsbonussale
WHERE dailydate_start =\''.$current_datepicker_start.'\' AND dailydate_end =\''.$current_datepicker.'\';';
//var_dump($userlist_sql);
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
		$getdata_bonussale_sql = "SELECT * FROM root_statisticsbonussale
		WHERE member_account = '".$userlist[$i]->account."' AND dailydate_start ='".$current_datepicker_start."' AND dailydate_end ='".$current_datepicker."' ;";
		//var_dump($getdata_bonussale_sql);
		$getdata_bonussale_result = runSQLall($getdata_bonussale_sql);

		// 指定的日期 + 會員 , 沒有資料的狀況處理
		if($getdata_bonussale_result[0] >= 1) {

			$b['id']                  = $getdata_bonussale_result[1]->id;
			// get data member id
			$b['member_id']  	  			= $userlist[$i]->id;
			$b['member_account']      = $getdata_bonussale_result[1]->member_account;
			$b['member_therole']      = $getdata_bonussale_result[1]->member_therole;
			$b['member_parent_id']    = $getdata_bonussale_result[1]->member_parent_id;
			$b['updatetime']          = $getdata_bonussale_result[1]->updatetime;
			$b['member_level']        = $getdata_bonussale_result[1]->member_level;
			$b['skip_bonusinfo']      = $getdata_bonussale_result[1]->skip_bonusinfo;
			$skip_bonusinfo_count     = explode(":",$b['skip_bonusinfo']);
			//var_dump($skip_bonusinfo_count);  取得第一個字串，為跳過的代數
			$b['skip_agent_tree_count'] = $skip_bonusinfo_count[0];
			$b['dailydate_start']     = $getdata_bonussale_result[1]->dailydate_start;
			$b['dailydate_end']       = $getdata_bonussale_result[1]->dailydate_end;
			$b['perforaccount_1']     = $getdata_bonussale_result[1]->perforaccount_1;
			$b['perforaccount_2']     = $getdata_bonussale_result[1]->perforaccount_2;
			$b['perforaccount_3']     = $getdata_bonussale_result[1]->perforaccount_3;
			$b['perforaccount_4']     = $getdata_bonussale_result[1]->perforaccount_4;
			$b['all_betsamount']      = $getdata_bonussale_result[1]->all_betsamount;
			$b['all_betscount']       = $getdata_bonussale_result[1]->all_betscount;
			$b['perfor_bounsamount']  = $getdata_bonussale_result[1]->perfor_bounsamount;
			$b['perforbouns_1']       = $getdata_bonussale_result[1]->perforbouns_1;
			$b['perforbouns_2']       = $getdata_bonussale_result[1]->perforbouns_2;
			$b['perforbouns_3']       = $getdata_bonussale_result[1]->perforbouns_3;
			$b['perforbouns_4']       = $getdata_bonussale_result[1]->perforbouns_4;
			$b['perforbouns_root']    = $getdata_bonussale_result[1]->perforbouns_root;

			// 個人從四層取得的資訊
			$b['member_bonusamount_1']  = $getdata_bonussale_result[1]->member_bonusamount_1;
			$b['member_bonuscount_1']   = $getdata_bonussale_result[1]->member_bonuscount_1;
			$b['member_bonusamount_2']  = $getdata_bonussale_result[1]->member_bonusamount_2;
			$b['member_bonuscount_2']   = $getdata_bonussale_result[1]->member_bonuscount_2;
			$b['member_bonusamount_3']  = $getdata_bonussale_result[1]->member_bonusamount_3;
			$b['member_bonuscount_3']   = $getdata_bonussale_result[1]->member_bonuscount_3;
			$b['member_bonusamount_4']  = $getdata_bonussale_result[1]->member_bonusamount_4;
			$b['member_bonuscount_4']   = $getdata_bonussale_result[1]->member_bonuscount_4;

			$b['member_bonusamount']            = $getdata_bonussale_result[1]->member_bonusamount;
			$b['member_bonusamount_count']      = $getdata_bonussale_result[1]->member_bonusamount_count;
			$b['member_bonusamount_paid']       = $getdata_bonussale_result[1]->member_bonusamount_paid;
			$b['member_bonusamount_paidtime']   = $getdata_bonussale_result[1]->member_bonusamount_paidtime;
			$b['notes']                         = $getdata_bonussale_result[1]->notes;

	    // 更新個人獎金收入
	    //echo '更新獎金收入 update - '.$userlist[$i]->account.'<br>';
	    // 營收分紅加總
	    $member_bonusamount_sql = "
	      SELECT sum(sum_perforbouns) as sum_perforbouns_all , sum(count_perforbouns) as count_perforbouns_sumall FROM (
	      (SELECT  sum(perforbouns_1) as sum_perforbouns, count(perforbouns_1) as count_perforbouns
	      FROM root_statisticsbonussale WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND perforaccount_1= '".$b['member_account']."')
	      union
	      (SELECT  sum(perforbouns_2) as sum_perforbouns, count(perforbouns_2) as count_perforbouns
	      FROM root_statisticsbonussale WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND perforaccount_2= '".$b['member_account']."')
	      union
	      (SELECT  sum(perforbouns_3) as sum_perforbouns, count(perforbouns_3) as count_perforbouns
	      FROM root_statisticsbonussale WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND perforaccount_3= '".$b['member_account']."')
	      union
	      (SELECT  sum(perforbouns_4) as sum_perforbouns, count(perforbouns_4) as count_perforbouns
	      FROM root_statisticsbonussale WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND perforaccount_4= '".$b['member_account']."')
	      ) as pppp_all;";

	    //var_dump($member_bonusamount_sql );
	    // print_r($member_bonusamount_sql);
	    $member_bonusamount_result = runSQLall($member_bonusamount_sql);

	    $member_bonus_1_sql = "SELECT sum(perforbouns_1) as sum_perforbouns_1, count(perforbouns_1) as count_perforbouns_1
	      FROM root_statisticsbonussale
	      WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' AND perforaccount_1= '".$b['member_account']."';";
	    $member_bonus_1_result = runSQLall($member_bonus_1_sql);

	    $member_bonus_2_sql = "SELECT sum(perforbouns_2) as sum_perforbouns_2, count(perforbouns_2) as count_perforbouns_2
	      FROM root_statisticsbonussale
	      WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' AND perforaccount_2= '".$b['member_account']."';";
	    $member_bonus_2_result = runSQLall($member_bonus_2_sql);

	    $member_bonus_3_sql = "SELECT sum(perforbouns_3) as sum_perforbouns_3, count(perforbouns_3) as count_perforbouns_3
	      FROM root_statisticsbonussale
	      WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' AND perforaccount_3= '".$b['member_account']."';";
	    $member_bonus_3_result = runSQLall($member_bonus_3_sql);

	    $member_bonus_4_sql = "SELECT sum(perforbouns_4) as sum_perforbouns_4, count(perforbouns_4) as count_perforbouns_4
	      FROM root_statisticsbonussale
	      WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' AND perforaccount_4= '".$b['member_account']."';";
	    $member_bonus_4_result = runSQLall($member_bonus_4_sql);

	    //var_dump($member_bonusamount_result);
	    // 如資料存在的話
	    if($member_bonusamount_result[0] == 1 AND $member_bonus_1_result[0] == 1) {
	      // 代理商的分紅第1代合計, 代理商的分紅第1代累計
	      $b['member_bonusamount_1']  = round($member_bonus_1_result[1]->sum_perforbouns_1, 2);
	      $b['member_bonuscount_1']   = round($member_bonus_1_result[1]->count_perforbouns_1);

	      // 代理商的分紅第2代合計, 代理商的分紅第2代累計
	      $b['member_bonusamount_2']  = round($member_bonus_2_result[1]->sum_perforbouns_2, 2);
	      $b['member_bonuscount_2']   = round($member_bonus_2_result[1]->count_perforbouns_2);

	      // 代理商的分紅第3代合計, 代理商的分紅第2代累計
	      $b['member_bonusamount_3']  = round($member_bonus_3_result[1]->sum_perforbouns_3, 2);
	      $b['member_bonuscount_3']   = round($member_bonus_3_result[1]->count_perforbouns_3);

	      // 代理商的分紅第2代合計, 代理商的分紅第2代累計
	      $b['member_bonusamount_4']  = round($member_bonus_4_result[1]->sum_perforbouns_4, 2);
	      $b['member_bonuscount_4']   = round($member_bonus_4_result[1]->count_perforbouns_4);

	      // 代理商的分紅合計,
	      $b['member_bonusamount'] = $member_bonusamount_result[1]->sum_perforbouns_all;
	      if($b['member_bonusamount'] == NULL) {
	        $b['member_bonusamount'] = 0;
	      }
	      // 代理商分紅紅利有多少紅利組成
	      $b['member_bonusamount_count'] = $member_bonusamount_result[1]->count_perforbouns_sumall;

	      // 寫入 DB
	      $bns_update_sql = "UPDATE root_statisticsbonussale
	        SET updatetime = NOW(), member_bonusamount = '".$b['member_bonusamount']."', member_bonusamount_count = '".$b['member_bonusamount_count']."'
	        ,member_bonusamount_1 = '".$b['member_bonusamount_1']."' ,member_bonusamount_2 = '".$b['member_bonusamount_2']."' ,member_bonusamount_3 = '".$b['member_bonusamount_3']."' ,member_bonusamount_4 = '".$b['member_bonusamount_4']."'
	        ,member_bonuscount_1  = '".$b['member_bonuscount_1']."' ,member_bonuscount_2 = '".$b['member_bonuscount_2']."' ,member_bonuscount_3 = '".$b['member_bonuscount_3']."' ,member_bonuscount_4 = '".$b['member_bonuscount_4']."'
	         WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND member_account = '".$userlist[$i]->account."';
	         ";


				if($argv_check == 'test'){
					//var_dump($bns_update_sql);
					$bns_update_result = 1;
					//print_r($update_sql);
			 	}elseif($argv_check == 'run'){
					//echo $bns_update_sql;
					$bns_update_result = runSQL($bns_update_sql);
			 	}
	      //var_dump($bns_update_result);
	      if($bns_update_result == 1) {
	        // 更新個人分紅收入累計 + 1
	        $stats_bonusamount_count++;
	        $logger = $logger.'Success 帳號'.$b['member_account']."日期: $current_datepicker_start ~ $current_datepicker".'更新個人分紅收入欄位。\n';
	        // echo  $logger;
	      }else{
	        $logger = $logger.'False 帳號'.$b['member_account']."日期: $current_datepicker_start ~ $current_datepicker".'更新個人分紅收入欄位更新失敗，請聯絡開發人員處理。\n';
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
    }else{
      $logger = $logger.'False 帳號'.$b['member_account']."日期: $current_datepicker_start ~ $current_datepicker".'營收分紅加總失敗，請聯絡開發人員處理。\n';
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
$userlist_sql = "SELECT * FROM root_statisticsbonussale
WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' ORDER BY member_id ASC;";
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
		$j = 0;

		// 資料庫內的 PK
		$csv_data['data'][$i][$j++] = $userlist[$i]->id;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_id;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_account;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_therole;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_parent_id;
		$csv_data['data'][$i][$j++] = $userlist[$i]->updatetime;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_level;
		$csv_data['data'][$i][$j++] = $userlist[$i]->skip_bonusinfo;
		$skip_bonusinfo_count     = explode(":",$userlist[$i]->skip_bonusinfo);
		//var_dump($skip_bonusinfo_count);  取得第一個字串，為跳過的代數
		$csv_data['data'][$i][$j++] = $skip_bonusinfo_count[0];
		$csv_data['data'][$i][$j++] = $userlist[$i]->dailydate_start;
		$csv_data['data'][$i][$j++] = $userlist[$i]->dailydate_end;
		$csv_data['data'][$i][$j++] = $userlist[$i]->perforaccount_1;
		$csv_data['data'][$i][$j++] = $userlist[$i]->perforaccount_2;
		$csv_data['data'][$i][$j++] = $userlist[$i]->perforaccount_3;
		$csv_data['data'][$i][$j++] = $userlist[$i]->perforaccount_4;
		$csv_data['data'][$i][$j++] = $userlist[$i]->all_betsamount;
		$csv_data['data'][$i][$j++] = $userlist[$i]->all_betscount;
		$csv_data['data'][$i][$j++] = $userlist[$i]->perfor_bounsamount;
		$csv_data['data'][$i][$j++] = $userlist[$i]->perforbouns_1;
		$csv_data['data'][$i][$j++] = $userlist[$i]->perforbouns_2;
		$csv_data['data'][$i][$j++] = $userlist[$i]->perforbouns_3;
		$csv_data['data'][$i][$j++] = $userlist[$i]->perforbouns_4;
		$csv_data['data'][$i][$j++] = $userlist[$i]->perforbouns_root;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonusamount_1;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonuscount_1;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonusamount_2;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonuscount_2;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonusamount_3;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonuscount_3;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonusamount_4;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonuscount_4;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonusamount;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonusamount_count;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonusamount_paid;
		$csv_data['data'][$i][$j++] = $userlist[$i]->member_bonusamount_paidtime;
		$csv_data['data'][$i][$j++] = $userlist[$i]->notes;

		// 不更新, 單純顯示
		//echo '不更新 show table data - '.$userlist[$i]->account.'<br>';
		$stats_showdata_count++;

		// 只有在這個狀態才可以允許 download CSV , 因為 array 排列才會正確.
		//$csv_data['data'][$i] = $b;
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
// 寫入 CSV 檔案的抬頭 - -和實際的 table 並沒有完全的對應
// -------------------------------------------
$t = 0;
$csv_data['table_colname'][$t++] = 'ID_PK';
$csv_data['table_colname'][$t++] = '會員ID';
$csv_data['table_colname'][$t++] = '會員帳號';
$csv_data['table_colname'][$t++] = '會員身份';
$csv_data['table_colname'][$t++] = '會員上層ID';
$csv_data['table_colname'][$t++] = '最後更新時間';
$csv_data['table_colname'][$t++] = '所在層數';
$csv_data['table_colname'][$t++] = '被跳過得代理資訊';
$csv_data['table_colname'][$t++] = '被跳過得代理商數量';
$csv_data['table_colname'][$t++] = '開始日期';
$csv_data['table_colname'][$t++] = '結束日期';
$csv_data['table_colname'][$t++] = '達成業績第1代';
$csv_data['table_colname'][$t++] = '達成業績第2代';
$csv_data['table_colname'][$t++] = '達成業績第3代';
$csv_data['table_colname'][$t++] = '達成業績第4代';
$csv_data['table_colname'][$t++] = '會員總投注量';
$csv_data['table_colname'][$t++] = '營業獎金分紅額度';
$csv_data['table_colname'][$t++] = '第1代營運紅利';
$csv_data['table_colname'][$t++] = '第2代營運紅利';
$csv_data['table_colname'][$t++] = '第3代營運紅利';
$csv_data['table_colname'][$t++] = '第4代營運紅利';
$csv_data['table_colname'][$t++] = '公司營運紅利收入';
$csv_data['table_colname'][$t++] = '個人的紅利第1代筆數';
$csv_data['table_colname'][$t++] = '個人的紅利第1代合計';
$csv_data['table_colname'][$t++] = '個人的紅利第2代筆數';
$csv_data['table_colname'][$t++] = '個人的紅利第2代合計';
$csv_data['table_colname'][$t++] = '個人的紅利第3代筆數';
$csv_data['table_colname'][$t++] = '個人的紅利第3代合計';
$csv_data['table_colname'][$t++] = '個人的紅利第4代筆數';
$csv_data['table_colname'][$t++] = '個人的紅利第4代合計';
$csv_data['table_colname'][$t++] = '個人紅利合計';
$csv_data['table_colname'][$t++] = '個人紅利來源筆數';
$csv_data['table_colname'][$t++] = '個人紅利已發放額度';
$csv_data['table_colname'][$t++] = '個人紅利發放時間';
$csv_data['table_colname'][$t++] = '備註';
//var_dump($csv_data);

// -------------------------------------------
// 將內容輸出到 檔案 , csv format
// -------------------------------------------

// 有資料才執行 csv 輸出, 避免 insert or update or stats 生成同時也執行 csv 輸出
if(isset($csv_data['data'])) {

	$filename       = "bonussale_result_".$current_datepicker_start.'_'.$current_datepicker.'.csv';
	$absfilename    = dirname(__FILE__) ."/tmp_dl/$filename";
	$filehandle     = fopen("$absfilename","w");
	// Windows下使用BOM来标记文本文件的编码方式, 否則 EXCEL 開啟這個檔案會是亂碼
	fwrite($filehandle,chr(0xEF).chr(0xBB).chr(0xBF));
	// -------------------------------------------
	// 將資料輸出到檔案 -- Summary
	//fputcsv($filehandle, $csv_data['summary_title']);
	//fputcsv($filehandle, $csv_data['summary']);

	// 將資料輸出到檔案 -- Title
	fputcsv($filehandle, $csv_data['table_colname']);

	// 將資料輸出到檔案 -- data
	foreach ($csv_data['data'] as $fields) {
		fputcsv($filehandle, $fields);
	}

	fclose($filehandle);
}


// -------------------------------------------
// 執行的動作或結果說明，可以透過執行的數量確定運作再那各區塊。
$run_report_result = "
  統計顯示的資料 =  $stats_showdata_count ,\n
  統計此時間區間插入(Insert)的資料 =  $stats_insert_count ,\n
  統計營業獎金投注量資料更新(Update)   =  $stats_update_count ,\n
  統計個人營業獎金更新(Update) =  $stats_bonusamount_count";
// -------------------------------------------

if($debug == 1){
	$run_report_result = $logger.$run_report_result;
}

// 算累積花費時間
$program_end_time =  microtime(true);
$program_time = $program_end_time-$program_start_time;
$logger = $run_report_result."\n累積花費時間: ".$program_time ." \n";
if($web_check == 1){

	$dellogfile_js = '
	<script src="in/jquery/jquery.min.js"></script>
	<script type="text/javascript" language="javascript" class="init">
	function dellogfile(){
		$.get("bonus_commission_sale_action.php?a=salebonus_del&k='.$file_key.'",
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
// MAIN END
// --------------------------------------------

?>
