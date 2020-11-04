<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 營運利潤獎金
// File Name:	bonus_commission_profit_summary.php
// Author:    Bakley
// Related:
// DB table: root_statisticsdailyreport
// DB table: root_statisticsbonusprofit  營運利潤獎金
// Log:
// 將營運日報的資料，整理成為會員獎金分紅的報表，並且輸出成為資料表存放。
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// betlog 專用的 DB lib
require_once dirname(__FILE__) ."/config_betlog.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title 		= '放射线组织奖金计算-营运利润奖金';
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">首頁</a></li>
  <li><a href="#">營收與行銷</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


// debug on = 1 , off =0
$debug = 0;

  // ---------------------------------------------------------------
  // MAIN start
  // ---------------------------------------------------------------

  // -------------------------------------------------------------------------
  // 尋找符合業績達成的上層, 共 n 代. 直到最上層 root 會員。
  // 再以計算出來的代數 account 判斷，哪些代數符合達成業績標準的會員。
  // -------------------------------------------------------------------------

  // -------------------------------------------------------------------------
  // 1.1 以節點找出使用者的資料 -- from root_member
  // -------------------------------------------------------------------------
  function find_member_node($member_id, $tree_level, $current_datepicker_start, $current_datepicker) {

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
	    //$tree_level = 1; 不論 status 為何, 都要找出來. 否則會 lost data 問題.
	    $member_sql = "SELECT id, account, parent_id, therole FROM root_member WHERE id = '$member_id';";
	    //var_dump($member_sql);
	    $member_result = runSQLall($member_sql);
	    //var_dump($member_result);
	    if($member_result[0]==1){
	      $tree = $member_result[1];
	      $tree->level = $tree_level;
        // 統計區間的數值總和, 所以日期需要 >= <=
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
    $tree_level_max = 120;

    $tree_level = 0;
    // treemap 為正常的組織階層
    $treemap[$member_id][$tree_level] = find_member_node($member_id, $tree_level, $current_datepicker_start, $current_datepicker);

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
        $treemap[$member_id][$tree_level] = find_member_node($m_id, $tree_level, $current_datepicker_start, $current_datepicker);
      }
    }

    // var_dump($treemap);
    return($treemap);
  }
  // -------------------------------------------------------------------------
  // END treemap
  // -------------------------------------------------------------------------


  // ----------------------------------------------------
  // Usages: bonus_commission_profit_data($userlist, $current_datepicker_start, $current_datepicker)
  // 計算出會員的貢獻營利額度及答標的會員
  // $userlist 陣列 member data
  // $current_datepicker_start  開始日期
  // $current_datepicker  結束日期
  // ----------------------------------------------------
  function bonus_commission_profit_data($userlist, $current_datepicker_start, $current_datepicker) {
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

    // 更新時把餘額清空，避免使用者誤判已經處理。(第二次更新資料庫時的欄位, 需要清空)
    // 會員個人的分潤合計
    $b['member_profitamount']      = NULL;
    // 會員個人的分潤付款(負數帳號為下次扣除)
    $b['member_profitamount_paid']      = NULL;
    // 會員個人的分潤付款時間
    $b['member_profitamount_paidtime']     = NULL;
    // 由多少 account 紀錄累積而來的
    $b['member_profitamount_count']     = NULL;
    // 會員個人上月留抵負債(下月計算時扣除)
    $b['lasttime_stayindebt']           = NULL;
    // 備註 -- 第一次 insert 生成後就不可以刪除或是清空
    $b['notes']                         = NULL;



      // -------------------------------------------
      // 找出會員所在的 tree 直到 root
      // -------------------------------------------
      $tree = find_parent_node($userlist->id,$current_datepicker_start, $current_datepicker);
      // var_dump($tree);

      // -------------------------------------------
      // 將原始的 $tree 轉換為--> 已經達標的 $ptree
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
      	  // root 就跳出了, 表示到頂了!!
        if($tree[$userlist->id][$level]->account == 'root') {
  		break;
        }else{
        	// 當 sum_all_bets 條件符合月結門檻 $rule['amountperformance_month'] 時，才可以列為分紅代
        	if($tree[$userlist->id][$level]->sum_all_bets >= $rule['amountperformance_month']) {
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
      // var_dump($skip_agent_tree);
      // -------------------------------------------
      // 被跳過的代理商 count
      // -------------------------------------------
      $skip_agent_tree_count = count($skip_agent_tree[$userlist->id]);
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
      // 達標的會員第2層
  	  $pti = 2;
      if(isset($ptree[$userlist->id][$pti]->account)) {
        // 達標代數會員帳號
  			$ptree_member_html[$pti] = $ptree[$userlist->id][$pti]->account;
        // 達標者身份
        $ptree_member_therole_html[$pti] = $ptree[$userlist->id][$pti]->therole;
  		}else{
  			$ptree_member_html[$pti] = 'n/a';
        $ptree_member_therole_html[$pti] = 'n/a';
  		}
      // 達標的會員第3層
  	  $pti = 3;
      if(isset($ptree[$userlist->id][$pti]->account)) {
        // 達標代數會員帳號
  			$ptree_member_html[$pti] = $ptree[$userlist->id][$pti]->account;
        // 達標者身份
        $ptree_member_therole_html[$pti] = $ptree[$userlist->id][$pti]->therole;
  		}else{
  			$ptree_member_html[$pti] = 'n/a';
        $ptree_member_therole_html[$pti] = 'n/a';
  		}
      // 達標的會員第4層
  	  $pti = 4;
      if(isset($ptree[$userlist->id][$pti]->account)) {
        // 達標代數會員帳號
  			$ptree_member_html[$pti] = $ptree[$userlist->id][$pti]->account;
        // 達標者身份
        $ptree_member_therole_html[$pti] = $ptree[$userlist->id][$pti]->therole;
  		}else{
  			$ptree_member_html[$pti] = 'n/a';
        $ptree_member_therole_html[$pti] = 'n/a';
  		}
      // 此寫法為了閱讀 array 方便, 第一個 index 使用了 member ID
      // 所以每次進入 loop 需要 free 記憶體, 否則筆數資料一多, 就會記憶體不足。
      unset($tree);
      unset($ptree);
      unset($skip_agent_tree);
      // -------------------------------------------
      // END
      // -------------------------------------------
      $b['profitaccount_1'] = $ptree_member_html[1];
      $b['ptree_member_therole_html_1'] = $ptree_member_therole_html[1];
      $b['profitaccount_2'] = $ptree_member_html[2];
      $b['ptree_member_therole_html_2'] = $ptree_member_therole_html[2];
      $b['profitaccount_3'] = $ptree_member_html[3];
      $b['ptree_member_therole_html_3'] = $ptree_member_therole_html[3];
      $b['profitaccount_4'] = $ptree_member_html[4];
      $b['ptree_member_therole_html_4'] = $ptree_member_therole_html[4];
      // 跳過的代數填入


      // -------------------------------------------
      // 紅利(3) 分潤的個人損益計算公式
      // -------------------------------------------
      // 這個公式等確認後，在寫成函式計算
      // $sum_all_profitlost_amount = sum_all_profitlost_amount($userlist[$i]->id, $current_datepicker_start, $current_datepicker);
      // 把所有時間範圍內的統計資料都撈出來.
      $profit_sql = "
      SELECT sum(mg_totalwager) as sum_mg_totalwager , sum(mg_totalpayout) as sum_mg_totalpayout, sum(mg_profitlost) as sum_mg_profitlost
      ,sum(tokenfavorable) as sum_tokenfavorable, sum(tokenpreferential) as sum_tokenpreferential
      ,sum(all_bets) as sum_all_bets, sum(all_wins) as sum_all_wins ,sum(all_profitlost) as sum_all_profitlost ,count(all_profitlost) as days_count, sum(all_count) as sum_all_count
      ,sum(cashadministrationfees) as cashadministrationfees_sum, sum(tokenadministrationfees) as tokenadministrationfees_sum ,sum(tokenadministration) as tokenadministration_sum
      ,sum(payonlinedeposit) as payonlinedeposit_sum, sum(tokendeposit) as tokendeposit_sum
      FROM root_statisticsdailyreport
      WHERE dailydate >= '$current_datepicker_start' AND dailydate <= '$current_datepicker' AND member_id = '".$userlist->id."';";
      //var_dump($profit_sql);
      // echo "<p> $profit_sql </p>";
      $profit_result = runSQLall($profit_sql);
      // 所有的損益狀況內容
      // var_dump($profit_result);

      if($profit_result[0] == 1) {
        $r['result'] = $profit_result[1];

        // 金流成本比例 0.8 ~ 2%
        $cashcost_rate = $rule['cashcost_rate']/100;
        // 金流成本 = (提款成本 + 出款成本) --- todo , 目前先不計算金流成本
        $member_profitlost_cashcost = 0;
        $b['member_profitlost_cashcost'] = $member_profitlost_cashcost;


        // 優惠成本
        $b['sum_tokenfavorable'] = round($r['result']->sum_tokenfavorable, 2);

        // 反水成本
        $b['sum_tokenpreferential'] = round($r['result']->sum_tokenpreferential, 2);

        // 行銷成本 = (優惠金額 + 反水金額)
        $member_profitlost_marketingcost = round(($r['result']->sum_tokenfavorable + $r['result']->sum_tokenpreferential),2);
        $b['member_profitlost_marketingcost'] = $member_profitlost_marketingcost;


        // 平台成本比例 5% ~ 17%, 以 12% 當平台固定成本
        $platformcost_rate = $rule['platformcost_rate']/100;
        // 平台成本 = 個人娛樂城損益 * 平台成本比例 (原本要分娛樂城因為避免計算困難, 拆帳不易在股利分配時再發放)
        // sum_all_profitlost 投注損益為負值, 則平台成本為 0
        if($r['result']->sum_all_profitlost < 0) {
          $member_profitlost_platformcost   = 0;
        }else{
          $member_profitlost_platformcost = round(($r['result']->sum_all_profitlost*$platformcost_rate),2);
        }
        $b['member_profitlost_platformcost'] = $member_profitlost_platformcost;


        // 個人貢獻 = 個人娛樂城虧損
        // 個人貢獻平台的損益 = 個人娛樂城損益 - 平台成本 - 行銷成本 - 金流成本
        $member_profitlost_amount = round(($r['result']->sum_all_profitlost - $member_profitlost_platformcost - $member_profitlost_marketingcost - $member_profitlost_cashcost),2);
        $b['profit_amount'] = $member_profitlost_amount;

        // 此紀錄累積統計的天數(日報表資料筆數) , 注意如果 null 在插入 sql 的時候 number 型態會不允許.
        $b['days_count'] = round($r['result']->days_count, 2);
        // 會員注單量
        $b['sum_all_count'] = round($r['result']->sum_all_count, 2);
        // 全部的投注金額
        $b['sum_all_bets'] = round($r['result']->sum_all_bets,2);
        // 全部的派彩金額
        $b['sum_all_wins'] = round($r['result']->sum_all_wins,2);
        // 全部的損益金額(未扣成本)
        $b['sum_all_profitlost'] = round($r['result']->sum_all_profitlost,2);

      }else{
        $logger = '會員'.$userlist->id.'資料('.$current_datepicker_start.'~'.$current_datepicker.')讀取錯誤，請聯絡開發人員處理。';
        die($logger);
      }

      // -------------------------------------------
      // 紅利(3) 分潤的個人損益計算公式 END
      // -------------------------------------------



      // -------------------------------------------
      // 營利獎金分紅額度, 依據損益 $member_profitlost_amount 四層分紅
      // -------------------------------------------
      //var_dump($member_profitlost_amount);
      //var_dump($rule);
      // 營業獎金分紅額度 - 第1代
      $profit_bonus_rate_amount_1 = round(($member_profitlost_amount*$rule['commission_1_rate']/100),2);
      $b['profit_amount_1'] = $profit_bonus_rate_amount_1;
      // 營業獎金分紅額度 - 第2代
      $profit_bonus_rate_amount_2 = round(($member_profitlost_amount*$rule['commission_2_rate']/100),2);
      $b['profit_amount_2'] = $profit_bonus_rate_amount_2;
      // 營業獎金分紅額度 - 第3代
      $profit_bonus_rate_amount_3 = round(($member_profitlost_amount*$rule['commission_3_rate']/100),2);
      $b['profit_amount_3'] = $profit_bonus_rate_amount_3;
      // 營業獎金分紅額度 - 第4代
      $profit_bonus_rate_amount_4 = round(($member_profitlost_amount*$rule['commission_4_rate']/100),2);
      $b['profit_amount_4'] = $profit_bonus_rate_amount_4;
      // ----------------------------------------------------


      // 第二次更新的資訊, 在第一次更新時, 顯示 n/a 表示不適用。
      // 上月留底 , 撈上個月的負值來處理。
      //$b['lasttime_stayindebt'] = NULL;
      // 第二次運算
      $b['member_profitamount_1'] = 'n/a';
      $b['member_profitamount_count_1'] = 'n/a';
      $b['member_profitamount_2'] = 'n/a';
      $b['member_profitamount_count_2'] = 'n/a';
      $b['member_profitamount_3'] = 'n/a';
      $b['member_profitamount_count_3'] = 'n/a';
      $b['member_profitamount_4'] = 'n/a';
      $b['member_profitamount_count_4'] = 'n/a';
      $b['member_profitamount'] = 'n/a';
      $b['member_profitamount_count'] = 'n/a';



      return($b);
  }
  // ----------------------------------------------------
  // END bonus_commission_profit_data
  // ----------------------------------------------------





  // -------------------------------------------------------------------------
  // 輸出目前系統的資料日期的表單, 以作為後續更新管理的參考
  // -------------------------------------------------------------------------

  // -------------------------------------------------------------------------
  // 取得目前系統的上一個月份的時間, 用來計算上期的欠帳. 如果沒有上一期帳單的話, 回傳為 false
  // Usage: pre_dailyrange($dailydate_start,$dailydate_end)
  // -------------------------------------------------------------------------
  function pre_dailyrange($dailydate_start,$dailydate_end) {

  // 列出系統資料統計月份
  $list_sql = '
  SELECT dailydate_start, dailydate_end,MIN(updatetime) as min , MAX(updatetime) as max,count(member_account) as member_account_count
  , sum(sum_all_profitlost) as sum_sum_all_profitlost, sum(profit_amount) as sum_profit_amount, sum(sum_all_bets) as sum_sum_all_bets, sum(sum_all_count) as sum_sum_all_count
  FROM root_statisticsbonusprofit
  GROUP BY dailydate_end,dailydate_start ORDER BY dailydate_start DESC;
  ';

  $list_result = runSQLall($list_sql);
  //var_dump($list_result);

  $pre_dailydate_start = NULL;
  $pre_dailydate_end = NULL;

  // 預設為失敗 , 如果沒有更新的話
  $r = false;

  if($list_result[0] > 0){
    // 把資料 dump 出來 to table
    for($i=1;$i<=$list_result[0];$i++) {
      // 取得上一個月計算週期的時間
      if($list_result[$i]->dailydate_start == $dailydate_start AND $list_result[$i]->dailydate_end == $dailydate_end ){
        $j = $i+1;
        if(isset($list_result[$j]->dailydate_start)  AND isset($list_result[$j]->dailydate_end)) {
          $r['pre_dailydate_start'] = $list_result[$j]->dailydate_start;
          $r['pre_dailydate_end']   = $list_result[$j]->dailydate_end;
        }
      }
    }
  }

  return($r);
  }
  // ---------------------------------------------------------------------------
  // END 取得目前系統的上一個月份的時間, 用來計算上期的欠帳
  // ---------------------------------------------------------------------------



  // ---------------------------------------------------------------------------
  // 檢查系統資料庫中 table root_statisticsbonusprofit 表格(放射線組織獎金計算-營運利潤獎金)有多少資料被生成了, 建立索引檔及提供可以更新的資訊
  // 搭配 indexmenu_stats_switch 使用
  // Usage: menu_profit_list_html()
  // ---------------------------------------------------------------------------
  function menu_profit_list_html() {

  // 列出系統資料統計月份
  $list_sql = '
  SELECT dailydate_start, dailydate_end,MIN(updatetime) as min , MAX(updatetime) as max,count(member_account) as member_account_count
  , sum(sum_all_profitlost) as sum_sum_all_profitlost, sum(profit_amount) as sum_profit_amount, sum(sum_all_bets) as sum_sum_all_bets, sum(sum_all_count) as sum_sum_all_count
  FROM root_statisticsbonusprofit
  GROUP BY dailydate_end,dailydate_start ORDER BY dailydate_start DESC;
  ';

  $list_result = runSQLall($list_sql);
  // var_dump($list_result);

  $list_stats_data = '';
  if($list_result[0] > 0){

    // 把資料 dump 出來 to table
    for($i=1;$i<=$list_result[0];$i++) {

      // 統計區間
      $date_range_html = '<a href="?current_datepicker='.$list_result[$i]->dailydate_start.'&update_bonusprofit_option=0" title="觀看指定區間">'.$list_result[$i]->dailydate_start.'~'.$list_result[$i]->dailydate_end.'</a>';
      // 資料數量
      $member_account_count_html = '<a href="#" title="統計資料更新的時間區間'.$list_result[$i]->min.'~'.$list_result[$i]->max.'">'.$list_result[$i]->member_account_count.'</a>';
      // 總投注量(娛樂城投注量)
      $sum_sum_all_bets_html = number_format($list_result[$i]->sum_sum_all_bets, 2, '.' ,',');
      // 總損益(未扣除成本)
      $sum_sum_all_profitlost_html = number_format($list_result[$i]->sum_sum_all_profitlost, 2, '.' ,',');
      // 總損益(已扣除成本)
      $sum_profit_amount_html = number_format($list_result[$i]->sum_profit_amount, 2, '.' ,',');
      // table
      $list_stats_data = $list_stats_data.'
      <tr>
        <td>'.$date_range_html.'</td>
        <td>'.$member_account_count_html.'</td>
        <td>'.$sum_sum_all_bets_html.'</td>
        <td>'.$sum_profit_amount_html.'</td>
      </tr>
      ';
    }

  }else{
    $list_stats_data = $list_stats_data.'
    <tr>
      <td></td>
      <td></td>
      <td></td>
      <td></td>
    </tr>
    ';
  }

  // 統計資料及索引
  $listdata_html = '
    <table class="table table-bordered small">
      <thead>
        <tr class="active">
          <th>統計區間<span class="glyphicon glyphicon-time"></span>(-04)</th>
          <th>資料數量</th>
          <th>總投注量</th>
          <th>總損益(已扣除成本)</th>
        </tr>
      </thead>
      <tbody style="background-color:rgba(255,255,255,0.4);">
        '.$list_stats_data.'
      </tbody>
    </table>';


  return($listdata_html);
  }
  // ---------------------------------------------------------------------------
  // END -- 檢查系統資料庫中 table root_statisticsbonusprofit 表格(放射線組織獎金計算-營運利潤獎金)有多少資料被生成了, 建立索引檔及提供可以更新的資訊
  // ---------------------------------------------------------------------------


  // ---------------------------------------------------------------------------
  // 加上 on / off開關 JS and CSS
  // ---------------------------------------------------------------------------
  function indexmenu_stats_switch() {

    // 選單表單
    $indexmenu_list_html = menu_profit_list_html();

    // 加上 on / off開關
    $indexmenu_stats_switch_html = '
    <span style="
    position: fixed;
    top: 5px;
    left: 5px;
    width: 450px;
    height: 20px;
    z-index: 1000;
    ">
    <button class="btn btn-primary btn-xs" id="hide">選單OFF</button>
    <button class="btn btn-success btn-xs" id="show">選單ON</button>
    </span>

    <div id="index_menu" style="display:block;
    position: fixed;
    top: 30px;
    left: 5px;
    width: 450px;
    height: 600px;
    overflow: auto;
    z-index: 999;
    -webkit-box-shadow: 0px 8px 35px #333;
    -moz-box-shadow: 0px 8px 35px #333;
    box-shadow: 0px 8px 35px #333;
    background: rgba(221, 221, 221, 1);
    ">
    '.$indexmenu_list_html.'
    </div>
    <script>
    $(document).ready(function(){
        $("#index_menu").fadeOut( "slow" );

        $("#hide").click(function(){
            $("#index_menu").fadeOut( "slow" );
        });
        $("#show").click(function(){
            $("#index_menu").fadeIn( "slow" );
        });
    });
    </script>
    ';


    return($indexmenu_stats_switch_html);
  }
  // ---------------------------------------------------------------------------
  // 加上 on / off開關 JS and CSS   END
  // ---------------------------------------------------------------------------


  // ---------------------------------------------------------------------------
  // 此報表的摘要函式, 指定查詢月份的統計結算列表
  // ---------------------------------------------------------------------------
  function summary_report($current_datepicker_start, $current_datepicker) {

    global $rule;

    // 統計區間
    $current_daterange_html = $current_datepicker_start.'~'.$current_datepicker;

    // 列出系統資料統計月份 , 分紅利潤不分盈虧
    $list_sql = "
    SELECT dailydate_start, dailydate_end,MIN(updatetime) as min , MAX(updatetime) as max,count(member_account) as member_account_count
    , sum(sum_all_profitlost) as sum_sum_all_profitlost, sum(profit_amount) as sum_profit_amount, sum(sum_all_bets) as sum_sum_all_bets, sum(sum_all_count) as sum_sum_all_count
    , sum(member_profitlost_platformcost) as sum_member_profitlost_platformcost, sum(member_profitlost_cashcost) as sum_member_profitlost_cashcost, sum(member_profitlost_marketingcost) as sum_member_profitlost_marketingcost
    , sum(member_profitamount) as sum_member_profitamount
    FROM root_statisticsbonusprofit WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."'
    GROUP BY dailydate_end,dailydate_start ORDER BY dailydate_start DESC;
    ";
    //var_dump($list_sql);
    //echo "<p>列出系統資料統計月份 , 分紅利潤不分盈虧</p>";
    //print_r($list_sql);
    $list_result = runSQLall($list_sql);
    //var_dump($list_result);


    // 列出系統資料統計月份, 分紅利潤只列 > 0 的數據
    $profit_sql = "
    SELECT dailydate_start, dailydate_end,MIN(updatetime) as min , MAX(updatetime) as max,count(member_account) as member_account_count
    , sum(sum_all_profitlost) as sum_sum_all_profitlost, sum(profit_amount) as sum_profit_amount, sum(sum_all_bets) as sum_sum_all_bets, sum(sum_all_count) as sum_sum_all_count
    , sum(member_profitlost_platformcost) as sum_member_profitlost_platformcost, sum(member_profitlost_cashcost) as sum_member_profitlost_cashcost, sum(member_profitlost_marketingcost) as sum_member_profitlost_marketingcost
    , sum(member_profitamount) as sum_member_profitamount
    FROM root_statisticsbonusprofit WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."'  AND member_profitamount >0
    GROUP BY dailydate_end,dailydate_start ORDER BY dailydate_start DESC;
    ";
    //echo "<p>列出系統資料統計月份, 分紅利潤只列 > 0 的數據</p>";
    //print_r($profit_sql);
    $profit_result = runSQLall($profit_sql);


    // 從日報表得到的損益值 -- 驗算合計
    $dailydate_sql = "SELECT sum(mg_totalwager) as sum_mg_totalwager , sum(mg_totalpayout) as sum_mg_totalpayout, sum(mg_profitlost) as sum_mg_profitlost
    ,sum(tokenfavorable) as sum_tokenfavorable, sum(tokenpreferential) as sum_tokenpreferential
    ,sum(all_bets) as sum_all_bets, sum(all_wins) as sum_all_wins ,sum(all_profitlost) as sum_all_profitlost ,count(all_profitlost) as days_count, sum(all_count) as sum_all_count
    ,sum(cashadministrationfees) as cashadministrationfees_sum, sum(tokenadministrationfees) as tokenadministrationfees_sum ,sum(tokenadministration) as tokenadministration_sum
    ,sum(payonlinedeposit) as payonlinedeposit_sum, sum(tokendeposit) as tokendeposit_sum
    FROM root_statisticsdailyreport
    WHERE dailydate >= '".$current_datepicker_start."' AND dailydate <= '".$current_datepicker."' ;";
    //echo "<p>從日報表得到的損益值 -- 驗算合計</p>";
    //print_r($dailydate_sql);
    $dailydate_result = runSQLall($dailydate_sql);


    // 全部發出的使用者的分潤總計
    $all_profit_amount_sql = "
    SELECT sum(sum_profit_amount) as sum_sum_profit_amount, sum(count_profit_amount) as count_profit_amount
    FROM (
    (SELECT '1' as no ,sum(profit_amount_1) as sum_profit_amount, count(profit_amount_1) as count_profit_amount
     FROM root_statisticsbonusprofit
    WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' )
    union
    (SELECT '2' as no ,sum(profit_amount_2) as sum_profit_amount, count(profit_amount_2) as count_profit_amount
     FROM root_statisticsbonusprofit
    WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' )
    union
    (SELECT '3' as no ,sum(profit_amount_3) as sum_profit_amount, count(profit_amount_3) as count_profit_amount
     FROM root_statisticsbonusprofit
    WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' )
    union
    (SELECT '4' as no ,sum(profit_amount_4) as sum_profit_amount, count(profit_amount_4) as count_profit_amount
     FROM root_statisticsbonusprofit
    WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' ) ) as profit_amount; ";
    $all_profit_amount_result = runSQLall($all_profit_amount_sql);

    // 未發出的分潤總計
    $na_profit_amount_sql = "
    SELECT sum(sum_profit_amount) as sum_sum_profit_amount, sum(count_profit_amount) as count_profit_amount
    FROM (
    (SELECT '1' as no ,sum(profit_amount_1) as sum_profit_amount, count(profit_amount_1) as count_profit_amount
     FROM root_statisticsbonusprofit
    WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' AND profitaccount_1= 'n/a')
    union
    (SELECT '2' as no ,sum(profit_amount_2) as sum_profit_amount, count(profit_amount_2) as count_profit_amount
     FROM root_statisticsbonusprofit
    WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' AND profitaccount_2= 'n/a' )
    union
    (SELECT '3' as no ,sum(profit_amount_3) as sum_profit_amount, count(profit_amount_3) as count_profit_amount
     FROM root_statisticsbonusprofit
    WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' AND profitaccount_3= 'n/a' )
    union
    (SELECT '4' as no ,sum(profit_amount_4) as sum_profit_amount, count(profit_amount_4) as count_profit_amount
     FROM root_statisticsbonusprofit
    WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' AND profitaccount_4= 'n/a' ) ) as profit_amount; ";
    $na_profit_amount_result = runSQLall($na_profit_amount_sql);



    $list_stats_data = '';
    if($list_result[0] > 0 AND $profit_result[0] > 0 AND $dailydate_result[0] > 0) {


      // 總投注量
      $sum_sum_all_bets_html = number_format($list_result[1]->sum_sum_all_bets, 2, '.' ,',');

      // 總注單量
      $sum_sum_all_count_html = number_format($list_result[1]->sum_sum_all_count, 0, '.' ,',');

      // 從日報表得到的損益值 -- 驗算合計
      $sum_all_profitlost_html = number_format($dailydate_result[1]->sum_all_profitlost, 2, '.' ,',');

      // 娛樂城平台成本總計
      $sum_member_profitlost_platformcost_html = number_format($list_result[1]->sum_member_profitlost_platformcost, 2, '.' ,',');

      // 金流成本總計
      $sum_member_profitlost_cashcost_html = number_format($list_result[1]->sum_member_profitlost_cashcost, 2, '.' ,',');

      // 行銷成本總計
      $sum_member_profitlost_marketingcost_html = number_format($list_result[1]->sum_member_profitlost_marketingcost, 2, '.' ,',');

      // 個人貢獻平台的損益總計
      $sum_profit_amount_html = number_format($list_result[1]->sum_profit_amount, 2, '.' ,',');

      // 系統平台分潤
      $profit_amount_system_html = number_format(($list_result[1]->sum_profit_amount * $rule['commission_root_rate']/100), 2, '.' ,',');

      // all 發出的分潤總計
      $all_profit_amount_html =  number_format($all_profit_amount_result[1]->sum_sum_profit_amount, 2, '.' ,',');

      // 未發出的分潤總計
      $na_profit_amount_html = number_format($na_profit_amount_result[1]->sum_sum_profit_amount, 2, '.' ,',');

      // 使用者分潤總計
      $sum_member_profitamount_html = number_format($list_result[1]->sum_member_profitamount, 2, '.' ,',');

      // 分潤的總計(只計算正值)
      $sum_member_profitamount_pos_html = number_format($profit_result[1]->sum_member_profitamount, 2, '.' ,',');

      $summary_report_data_html = '
      <tr>
        <td>'.$current_daterange_html.'</td>
        <td>'.$list_result[1]->member_account_count.'</td>
        <td>'.$sum_sum_all_bets_html.'</td>
        <td>'.$sum_sum_all_count_html.'</td>
        <td>'.$sum_all_profitlost_html.'</td>

        <td>'.$sum_member_profitlost_platformcost_html.'</td>
        <td>'.$sum_member_profitlost_cashcost_html.'</td>
        <td>'.$sum_member_profitlost_marketingcost_html.'</td>
        <td>'.$sum_profit_amount_html.'</td>
        <td>'.$profit_amount_system_html.'</td>
        <td>'.$all_profit_amount_html.'</td>

        <td>'.$na_profit_amount_html.'</td>
        <td>'.$sum_member_profitamount_html.'</td>
        <td>'.$sum_member_profitamount_pos_html.'</td>
      </tr>
      ';

      $summary_report_html = '
      <hr>
      <table class="table table-bordered small">
        <thead>
          <tr class="active">
            <th>統計區間</th>
            <th>資料數量</th>
            <th>總投注量</th>
            <th>總注單量</th>
            <th>娛樂城損益(日報)</th>

            <th>娛樂城平台成本總計</th>
            <th>金流成本總計</th>
            <th>行銷成本總計</th>
            <th>個人貢獻平台的損益總計</th>
            <th>系統平台分潤</th>
            <th>會員的損益四層分潤</th>


            <th>未發出的分潤總計</th>
            <th>使用者分潤總計</th>
            <th>分潤的總計(只計算正值)</th>
          </tr>
        </thead>
        <tbody style="background-color:rgba(255,255,255,0.4);">
          '.$summary_report_data_html.'
        </tbody>
      </table>
      </hr>
      ';

    }else{
      $summary_report_html = '';
    }

  return($summary_report_html);
  }
  // ---------------------------------------------------------------------------
  // 此報表的摘要函式, 指定查詢月份的統計結算列表
  // ---------------------------------------------------------------------------



  // -------------------------------------------------------------------------
  // 取得日期 - 決定開始用份的範圍日期
  // -------------------------------------------------------------------------
  // get example: ?current_datepicker=2017-02-03
  // ref: http://php.net/manual/en/function.checkdate.php
  function validateDate($date, $format = 'Y-m-d H:i:s')
  {
      $d = DateTime::createFromFormat($format, $date);
      return $d && $d->format($format) == $date;
  }
  // -------------------------------------------------------------------------
  // END function lib
  // -------------------------------------------------------------------------




  //
  //
  //
  // -------------------------------------------------------------------------
  // MAIN start
  // -------------------------------------------------------------------------
  //
  //
  //




  // 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
  if(isset($_GET['current_datepicker'])) {
    // 判斷格式資料是否正確
    if(validateDate($_GET['current_datepicker'], 'Y-m-d')) {
      $current_datepicker = $_GET['current_datepicker'];
    }else{
      // 轉換為美東的時間 date
      $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
      date_timezone_set($date, timezone_open('America/St_Thomas'));
      $current_datepicker = date_format($date, 'Y-m-d');
    }
  }else{
    // php 格式的 2017-02-24
    // 轉換為美東的時間 date
    $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
    date_timezone_set($date, timezone_open('America/St_Thomas'));
    $current_datepicker = date_format($date, 'Y-m-d');
  }
  // 營利獎金結算週期，每月的幾號？ 美東時間 (固定週期為月)
  //  $rule['stats_profit_day']    = 10;
  // 統計的期間時間 0 ~ 1month = 一個月
  //var_dump($current_datepicker);

  // 如果選擇的日期, 大於設定的月結日期，就以下個月顯示. 如果不是的話就是上個月顯示
  $current_date_d = date("d", strtotime( "$current_datepicker"));
  //var_dump($current_date_d);
  if($current_date_d > $rule['stats_profit_day']) {
    $date_fmt = 'Y-m-'.$rule['stats_profit_day'];
    $current_datepicker = date( $date_fmt, strtotime( "$current_datepicker +1 month"));
  }else{
    $date_fmt = 'Y-m-'.$rule['stats_profit_day'];
    $current_datepicker = date( $date_fmt, strtotime( "$current_datepicker"));
  }
  //var_dump($date_fmt);
  //var_dump($current_datepicker);

  // result:
  // 本月的開始日
  $current_datepicker_start = date( "Y-m-d", strtotime( "$current_datepicker -1 month +1 days"));
  //var_dump($current_datepicker_start);
  // 本月的結束日 = $current_datepicker
  // -------------------------------------------------------------------------
  // 取得日期 - 決定開始用份的範圍日期  END
  // -------------------------------------------------------------------------


  // 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
  if(isset($_GET['current_per_size']) AND $_GET['current_per_size'] != NULL ) {
    $current_per_size = $_GET['current_per_size'];
  }else{
    $current_per_size = 10000;
    //$current_per_size = 50;
  }

  // 起始頁面, 搭配 current_per_size 決定起始點位置
  if(isset($_GET['current_page_no']) AND $_GET['current_page_no'] != NULL ) {
    $current_page_no = $_GET['current_page_no'];
  }else{
    $current_page_no = 0;
  }

  // -------------------------------------------------------------------------
  // $_GET 取得動作
  // -------------------------------------------------------------------------
  //var_dump($_GET);
  // 變數：更新營利獎金的投注量資訊
  $action_status_number = 0;
  if(isset($_GET['update_bonusprofit_option']) AND $_GET['update_bonusprofit_option'] == '1') {
    $update_bonusprofit_option_status = true;
    $action_status_number = 1;
  }else{
    $update_bonusprofit_option_status = false;
  }
  // 變數：更新營利獎金的個人分配金額資訊
  if(isset($_GET['update_bonusprofit_option']) AND $_GET['update_bonusprofit_option'] == '2') {
    $update_bonusprofit_person_status = true;
    $action_status_number = 2;
  }else{
    $update_bonusprofit_person_status = false;
  }


  // -------------------------------------
  // 列出所有的會員資料及人數 SQL
  // -------------------------------------
  // 算人數
  $userlist_sql   = "SELECT * FROM root_member ORDER BY id;";
  // var_dump($userlist_sql);
  $userlist_count = runSQL($userlist_sql);

  // -------------------------------------
  // 分頁處理機制
  // -------------------------------------
  // 所有紀錄數量
  $page['all_records']     = $userlist_count;
  // 每頁顯示多少
  $page['per_size']        = $current_per_size;
  // 可以分成多少頁
  $page['number_max']      = ceil($page['all_records']/$page['per_size']);
  // 目前所在頁數
  $page['no']              = $current_page_no;
  // 換算後的開始紀錄為多少？
  $page['start_records']   = $page['no']*$page['per_size'];
  // var_dump($page);

  // 取出 root_member 資料
  $userlist_sql   = "SELECT * FROM root_member ORDER BY id OFFSET ".$page['start_records']." LIMIT ".$page['per_size']."  ;";
  // var_dump($userlist_sql);
  $userlist       = runSQLall($userlist_sql);
  $userlist_count = $userlist[0];

  // 先取得上個月的日期
  $pre_dailyrange = pre_dailyrange($current_datepicker_start,$current_datepicker);
  //var_dump($pre_dailyrange);

  // 存放列表的 html -- 表格 row -- tables DATA
  $show_listrow_html = '';
  // 判斷 root_member count 數量大於 1
  if($userlist[0] >= 1) {
    // 會員有資料，且存在數量為 $userlist_count


    // 以會員為主要 key 依序列出每個會員的貢獻金額
    for($i = 1 ; $i <= $userlist_count ; $i++){

      // var_dump($userlist[$i]);

      $b['dailydate_start'] = $current_datepicker_start;
      $b['dailydate_end'] = $current_datepicker;

      // ----------------------------------------------------
      // 會員帳號基本資訊, 無論是否有資料都會呈現。
      // ----------------------------------------------------
      // 會員ID
      $member_id_html = '<a href="member_treemap.php?id='.$userlist[$i]->id.'" target="_BLANK" title="會員的組織結構狀態">'.$userlist[$i]->id.'</a>';

      // 上一代的資訊
      $member_parent_html = '<a href="member_account.php?a='.$userlist[$i]->parent_id.'" target="_BLANK"  title="會員上一代資訊">'.$userlist[$i]->parent_id.'</a>';

      // 會員身份
      $member_therole_html = '<a href="#" title="會員身份 R=管理員 A=代理商 M=會員">'.$userlist[$i]->therole.'</a>';

      // 會員帳號
      $member_account_html = '<a href="member_account.php?a='.$userlist[$i]->id.'" target="_BLANK" title="檢查會員的詳細資料">'.$userlist[$i]->account.'</a>';
      // ---------------------------------------------------
      // 預設的四個欄位, 由 member 取得資訊
      $b['member_id'] = $userlist[$i]->id;
      $b['member_parent_id'] = $userlist[$i]->parent_id;
      $b['member_therole'] = $userlist[$i]->therole;
      $b['member_account'] = $userlist[$i]->account;




      // ----------------------------------------------------
      // 檢查資料是否在 root_statisticsbonusprofit DB 中 , 如果存在的話應該是已經生成了.
      // 如果 $update_bonusprofit_option_status = true , 就使用 update sql 更新 更新營利獎金的投注量資訊 資料
      // 如果不存在的話, 使用 insert 插入資料到系統內.
      // ----------------------------------------------------
      $check_data_alive_sql = "SELECT * FROM root_statisticsbonusprofit
      WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND member_account = '".$userlist[$i]->account."';";
      //var_dump($check_data_alive_sql);
      $check_data_alive_result = runSQLall($check_data_alive_sql);
      //echo '檢查時間範圍內會員是否有資料';
      //var_dump($check_data_alive_result);
      if($check_data_alive_result[0] == 1) {
        // 是否強至更新月結注單紀錄, 這個更新需要重新計算本月的分紅。(二次更新)
        if($update_bonusprofit_option_status == true) {
          // 取得指定使用者的資料
          $b = bonus_commission_profit_data($userlist[$i], $current_datepicker_start, $current_datepicker);
          // var_dump($b);

          // skip
          // lasttime_stayindebt = '".$b['lasttime_stayindebt']."',

          // 資料已經存在 update date , 只更新從每日報表的資料源
          $update_sql = "UPDATE root_statisticsbonusprofit SET
          member_parent_id = '".$b['member_parent_id']."',
          member_therole = '".$b['member_therole']."',
          updatetime = now(),
          member_level = '".$b['member_level']."',
          skip_bonusinfo = '".$b['skip_bonusinfo']."',
          profitaccount_1 = '".$b['profitaccount_1']."',
          profitaccount_2 = '".$b['profitaccount_2']."',
          profitaccount_3 = '".$b['profitaccount_3']."',
          profitaccount_4 = '".$b['profitaccount_4']."',
          profit_amount = '".$b['profit_amount']."',
          profit_amount_1 = '".$b['profit_amount_1']."',
          profit_amount_2 = '".$b['profit_amount_2']."',
          profit_amount_3 = '".$b['profit_amount_3']."',
          profit_amount_4 = '".$b['profit_amount_4']."',
          member_profitlost_cashcost = '".$b['member_profitlost_cashcost']."',
          member_profitlost_marketingcost = '".$b['member_profitlost_marketingcost']."',
          sum_tokenfavorable = '".$b['sum_tokenfavorable']."',
          sum_tokenpreferential = '".$b['sum_tokenpreferential']."',
          member_profitlost_platformcost = '".$b['member_profitlost_platformcost']."',
          days_count = '".$b['days_count']."',
          sum_all_count = '".$b['sum_all_count']."',
          sum_all_bets = '".$b['sum_all_bets']."',
          sum_all_wins = '".$b['sum_all_wins']."',
          sum_all_profitlost = '".$b['sum_all_profitlost']."'
          WHERE member_account = '".$b['member_account']."' AND dailydate_start = '".$b['dailydate_start']."' AND dailydate_end = '".$b['dailydate_end']."';
          ";
          if($debug == 1) {
            var_dump($update_sql);
            print_r($update_sql);
          }

          $update_result = runSQL($update_sql);
          if($update_result == 1){
            $logger = '更新月結投注紀錄'.$b['member_id'].'Update Success, member account is '.$b['member_account'].',date start:'.$current_datepicker_start.',date end:'.$current_datepicker;
            // echo $logger;
          }else{
            $logger = '更新月結投注紀錄'.$b['member_id'].'Update Fail, member account is '.$b['member_account'].',date start:'.$current_datepicker_start.',date end:'.$current_datepicker;
            die($logger);
          }


        }else{
          // 檢查時間範圍內會員是否有資料 , 因為有資料所以把資料從 DB 取出來處理.
          // 不更新到 DB 內, 可以快速的讀取資料庫內容.

          // 這個排列會影響 CSV 輸出的順序, 要注意一下.
          // data id
          $b['id'] = $check_data_alive_result[1]->id;
          $b['updatetime'] = $check_data_alive_result[1]->updatetime;

          // 預設有會員的 ID , Account, Role
          $b['member_level']  = $check_data_alive_result[1]->member_level;
          $b['skip_bonusinfo']  = $check_data_alive_result[1]->skip_bonusinfo;
          $skip_bonusinfo_count     = explode(":",$b['skip_bonusinfo']);
          //var_dump($skip_bonusinfo_count);  取得第一個字串，為跳過的代數
          $b['skip_agent_tree_count'] = $skip_bonusinfo_count[0];
          $b['profitaccount_1']  = $check_data_alive_result[1]->profitaccount_1;
          $b['profitaccount_2']  = $check_data_alive_result[1]->profitaccount_2;
          $b['profitaccount_3']  = $check_data_alive_result[1]->profitaccount_3;
          $b['profitaccount_4']  = $check_data_alive_result[1]->profitaccount_4;
          $b['profit_amount']  = $check_data_alive_result[1]->profit_amount;
          $b['profit_amount_1']  = $check_data_alive_result[1]->profit_amount_1;
          $b['profit_amount_2']  = $check_data_alive_result[1]->profit_amount_2;
          $b['profit_amount_3']  = $check_data_alive_result[1]->profit_amount_3;
          $b['profit_amount_4']  = $check_data_alive_result[1]->profit_amount_4;

          // 統計的欄位
          $b['member_profitlost_cashcost']  = $check_data_alive_result[1]->member_profitlost_cashcost;
          $b['member_profitlost_marketingcost']  = $check_data_alive_result[1]->member_profitlost_marketingcost;
          $b['sum_tokenfavorable']  = $check_data_alive_result[1]->sum_tokenfavorable;
          $b['sum_tokenpreferential']  = $check_data_alive_result[1]->sum_tokenpreferential;
          $b['member_profitlost_platformcost']  = $check_data_alive_result[1]->member_profitlost_platformcost;
          $b['days_count']  = $check_data_alive_result[1]->days_count;
          $b['sum_all_count']  = $check_data_alive_result[1]->sum_all_count;
          $b['sum_all_bets']  = $check_data_alive_result[1]->sum_all_bets;
          $b['sum_all_wins']  = $check_data_alive_result[1]->sum_all_wins;
          $b['sum_all_profitlost']  = $check_data_alive_result[1]->sum_all_profitlost;

          // 代理商本月分潤
          $b['member_profitamount_1']       = $check_data_alive_result[1]->member_profitamount_1;
          $b['member_profitamount_count_1'] = $check_data_alive_result[1]->member_profitamount_count_1;
          $b['member_profitamount_2']       = $check_data_alive_result[1]->member_profitamount_2;
          $b['member_profitamount_count_2'] = $check_data_alive_result[1]->member_profitamount_count_2;
          $b['member_profitamount_3']       = $check_data_alive_result[1]->member_profitamount_3;
          $b['member_profitamount_count_3'] = $check_data_alive_result[1]->member_profitamount_count_3;
          $b['member_profitamount_4']       = $check_data_alive_result[1]->member_profitamount_4;
          $b['member_profitamount_count_4'] = $check_data_alive_result[1]->member_profitamount_count_4;
          $b['member_profitamount_count']   = $check_data_alive_result[1]->member_profitamount_count;
          $b['member_profitamount']  = $check_data_alive_result[1]->member_profitamount;
          $b['member_profitamount_paid']  = $check_data_alive_result[1]->member_profitamount_paid;
          $b['member_profitamount_paidtime']  = $check_data_alive_result[1]->member_profitamount_paidtime;

          // 上月留抵
          $b['lasttime_stayindebt']  = $check_data_alive_result[1]->lasttime_stayindebt;
          // 備註
          $b['notes']  = $check_data_alive_result[1]->notes;

          // $logger = '不更新投注紀錄'.$b['member_id'].'Member account is '.$b['member_account'].',date start:'.$current_datepicker_start.',date end:'.$current_datepicker;
          // echo $logger;

          //var_dump($check_data_alive_result);


          // -------------------------------------------------------------------
          // 當資料庫的資料 都已經建立完成後, 重新加總這個資料
          // 第二次加總計算 -- 個人的的營利統計加總
          // -------------------------------------------------------------------

          // 如果變數 $update_bonusprofit_person_status 設定為 true (from $_GET) , 將個人的的營利統計加總.
          if($update_bonusprofit_person_status == true ){

            // 分潤第1代
            $member_profitamount_sql_1 = "SELECT sum(profit_amount_1) as sum_profit_amount, count(profit_amount_1) as count_profit_amount
            FROM root_statisticsbonusprofit WHERE dailydate_start >= '$current_datepicker_start' AND dailydate_end <= '$current_datepicker' AND profitaccount_1= '".$b['member_account']."' ;";
            if($debug == 1) {
              print_r($member_profitamount_sql_1);
            }

            $member_profitamount_result_1 = runSQLall($member_profitamount_sql_1);
            //var_dump($member_profitamount_result_1);
            if($member_profitamount_result_1[0] == 1) {
              if($member_profitamount_result_1[1]->sum_profit_amount == NULL) {
                $b['member_profitamount_1'] = 0;
              }else{
                $b['member_profitamount_1'] = $member_profitamount_result_1[1]->sum_profit_amount;
              }
              $b['member_profitamount_count_1'] =$member_profitamount_result_1[1]->count_profit_amount;
              //var_dump($member_profitamount_sql_1);
              //var_dump($member_profitamount_result_1);
            }else{
              $logger ='[BE4001]資料庫存取錯誤, 請聯絡管理人員處理. in '.$b['member_account'].'date:'.$current_datepicker_start.'~'.$current_datepicker;
              var_dump($logger);
              memberlog2db($_SESSION['agent']->account, 'bonus profit', 'error', "$logger");
            }

            // 分潤第2代
            $member_profitamount_sql_2 = "SELECT sum(profit_amount_2) as sum_profit_amount, count(profit_amount_2) as count_profit_amount
            FROM root_statisticsbonusprofit WHERE dailydate_start >= '$current_datepicker_start' AND dailydate_end <= '$current_datepicker' AND profitaccount_2= '".$b['member_account']."' ;";
            if($debug == 1) {
              print_r($member_profitamount_sql_2);
            }

            $member_profitamount_result_2 = runSQLall($member_profitamount_sql_2);
            // var_dump($member_profitamount_result_2);
            if($member_profitamount_result_2[0] == 1) {
              if($member_profitamount_result_2[1]->sum_profit_amount == NULL) {
                $b['member_profitamount_2'] = 0;
              }else{
                $b['member_profitamount_2'] = $member_profitamount_result_2[1]->sum_profit_amount;
              }
              $b['member_profitamount_count_2'] =$member_profitamount_result_2[1]->count_profit_amount;
              //var_dump($member_profitamount_sql_2);
              //var_dump($member_profitamount_result_2);
            }else{
              $logger ='[BE4002]資料庫存取錯誤, 請聯絡管理人員處理. in '.$b['member_account'].'date:'.$current_datepicker_start.'~'.$current_datepicker;
              var_dump($logger);
              memberlog2db($_SESSION['agent']->account, 'bonus profit', 'error', "$logger");
            }

            // 分潤第3代
            $member_profitamount_sql_3 = "SELECT sum(profit_amount_3) as sum_profit_amount, count(profit_amount_3) as count_profit_amount
            FROM root_statisticsbonusprofit WHERE dailydate_start >= '$current_datepicker_start' AND dailydate_end <= '$current_datepicker' AND profitaccount_3= '".$b['member_account']."' ;";
            if($debug == 1) {
              print_r($member_profitamount_sql_3);
            }

            $member_profitamount_result_3 = runSQLall($member_profitamount_sql_3);
            if($member_profitamount_result_3[0] == 1) {
              if($member_profitamount_result_3[1]->sum_profit_amount == NULL) {
                $b['member_profitamount_3'] = 0;
              }else{
                $b['member_profitamount_3'] = $member_profitamount_result_3[1]->sum_profit_amount;
              }
              $b['member_profitamount_count_3'] =$member_profitamount_result_3[1]->count_profit_amount;
              //var_dump($member_profitamount_sql_3);
              // var_dump($member_profitamount_result_3);
            }else{
              $logger ='[BE4003]資料庫存取錯誤, 請聯絡管理人員處理. in '.$b['member_account'].'date:'.$current_datepicker_start.'~'.$current_datepicker;
              var_dump($logger);
              memberlog2db($_SESSION['agent']->account, 'bonus profit', 'error', "$logger");
            }

            // 分潤第4代
            $member_profitamount_sql_4 = "SELECT sum(profit_amount_4) as sum_profit_amount, count(profit_amount_4) as count_profit_amount
            FROM root_statisticsbonusprofit WHERE dailydate_start >= '$current_datepicker_start' AND dailydate_end <= '$current_datepicker' AND profitaccount_4= '".$b['member_account']."' ;";
            if($debug == 1) {
              print_r($member_profitamount_sql_4);
            }

            $member_profitamount_result_4 = runSQLall($member_profitamount_sql_4);
            //var_dump($member_profitamount_result_4);
            if($member_profitamount_result_4[0] == 1) {
              if($member_profitamount_result_4[1]->sum_profit_amount == NULL) {
                $b['member_profitamount_4'] = 0;
              }else{
                $b['member_profitamount_4'] = $member_profitamount_result_4[1]->sum_profit_amount;
              }
              $b['member_profitamount_count_4'] =$member_profitamount_result_4[1]->count_profit_amount;
              //var_dump($member_profitamount_sql_4);
              //var_dump($member_profitamount_result_4);
            }else{
              $logger ='[BE4004]資料庫存取錯誤, 請聯絡管理人員處理. in '.$b['member_account'].'date:'.$current_datepicker_start.'~'.$current_datepicker;
              var_dump($logger);
              memberlog2db($_SESSION['agent']->account, 'bonus profit', 'error', "$logger");
            }

            // 分潤總和
            $b['member_profitamount'] = $b['member_profitamount_1'] + $b['member_profitamount_2'] + $b['member_profitamount_3'] + $b['member_profitamount_4'];

            // 分潤總筆數
            $b['member_profitamount_count'] = $b['member_profitamount_count_1'] + $b['member_profitamount_count_2'] + $b['member_profitamount_count_3'] + $b['member_profitamount_count_4'];

            // 如果沒有資料的話, 日期為空. 上個月沒有分潤資料則為空值也不會友日期, 全部給預設值 0
            if($pre_dailyrange != false) {
              // 上個月留抵 -- 搜尋上個月付款的資料, 如果付款為負, 紀錄在本月的此欄位上面. 當要手工付款時, 檢查 總分潤 - 上月留抵 是否大於0 , 如果大於 0 才發放現金
              $lasttime_member_profitamount_sql = "SELECT * FROM root_statisticsbonusprofit
              WHERE dailydate_start = '".$pre_dailyrange['pre_dailydate_start']."' AND dailydate_end = '".$pre_dailyrange['pre_dailydate_end']."' AND member_profitamount_paid < '0' AND member_account = '".$b['member_account']."';";
              if($debug == 1) {
                var_dump($lasttime_member_profitamount_sql);
              }
              // 取出上各月的分潤資料
              $lasttime_member_profitamount_result = runSQLall($lasttime_member_profitamount_sql);
              // 有資料, 上月付款額小於 0 的話
              if($lasttime_member_profitamount_result[0] > 0) {
                $b['lasttime_stayindebt'] = $lasttime_member_profitamount_result[1]->member_profitamount_paid;
                //var_dump($lasttime_member_profitamount_sql);
                //var_dump($lasttime_member_profitamount_result);
              }else{
                $b['lasttime_stayindebt'] = 0;
              }
              // 這個值不異變動, 每次 2 次統計時重新寫入.
            }else{
              // 上個沒有存在, 設為 0
              $b['lasttime_stayindebt'] = 0;
            }


            // 付款金額, 如果本月的結算 member_profitamount 為負值的話, 記帳在 member_profitamount_paid 欄位上面. 表達為負值(因為不轉帳,也不扣帳)
            // 把上月留抵 + 本月分潤 , 如果還是 < 0 的時候, 寫入 $b['member_profitamount_paid']
            if(($b['lasttime_stayindebt'] + $b['member_profitamount']) < 0) {
              $b['member_profitamount_paid'] = ($b['lasttime_stayindebt'] + $b['member_profitamount']);
              $member_profitamount_paid_sql = "member_profitamount_paid   = '".$b['member_profitamount_paid']."', ";
            }else{
              // 如果沒有小於 0  , 就啥也不動作. 不更新該欄位. 因為可能會有手動的紀錄產生.
              $member_profitamount_paid_sql = '';
            }
            //var_dump($member_profitamount_paid_sql);


            // 將第二次運算後的資料  update to sql
            $update_profit_sql = "UPDATE root_statisticsbonusprofit SET
            updatetime = now(),
            member_profitamount_1 = '".$b['member_profitamount_1']."',
            member_profitamount_2 = '".$b['member_profitamount_2']."',
            member_profitamount_3 = '".$b['member_profitamount_3']."',
            member_profitamount_4 = '".$b['member_profitamount_4']."',
            member_profitamount   = '".$b['member_profitamount']."',
            member_profitamount_count_1 = '".$b['member_profitamount_count_1']."',
            member_profitamount_count_2 = '".$b['member_profitamount_count_2']."',
            member_profitamount_count_3 = '".$b['member_profitamount_count_3']."',
            member_profitamount_count_4 = '".$b['member_profitamount_count_4']."',
            member_profitamount_count   = '".$b['member_profitamount_count']."',
            ".$member_profitamount_paid_sql."
            lasttime_stayindebt         = '".$b['lasttime_stayindebt']."'
            WHERE member_account = '".$b['member_account']."' AND dailydate_start = '".$b['dailydate_start']."' AND dailydate_end = '".$b['dailydate_end']."';
            ";
            if($debug == 1) {
              var_dump($update_profit_sql);
              print_r($update_profit_sql);
            }

            $update_profit_result = runSQLall($update_profit_sql);
            if($update_profit_result[0] == 1) {
              $logger = '更新第二次運算後的資料成功'.$b['member_account'].','.$b['dailydate_start'].','.$b['dailydate_end'];
              // echo $logger;
            }else{
              $logger = '更新第二次運算後的資料失敗'.$b['member_account'].','.$b['dailydate_start'].','.$b['dailydate_end'];
              // echo $logger;
            }

            // -------------------------------------------------------------------
            // 第二次加總計算 END
            // -------------------------------------------------------------------

            // 沒有加總計算才產生 CSV 內容

          }else{
            // 將 b 存成 csv data , 當純粹查詢的時候. 才顯示 csv download
            $csv_data['data'][$i] = $b;
          }

        }

      }else{
        // 沒有資料的處理  do insert sql data
        // 計算撈取時間範圍內的資料, 把資料插入資料庫中
        $b = bonus_commission_profit_data($userlist[$i], $current_datepicker_start, $current_datepicker);
        //var_dump($b);

        // 插入 insert SQL
        // ----------------------------------------------------
        //var_dump($b);
        $insert_sql = 'INSERT INTO "root_statisticsbonusprofit" ("member_account", "member_parent_id", "member_therole", "updatetime", "dailydate_start", "dailydate_end",
         "member_level", "skip_bonusinfo", "profitaccount_1", "profitaccount_2", "profitaccount_3", "profitaccount_4", "profit_amount",
         "profit_amount_1", "profit_amount_2", "profit_amount_3", "profit_amount_4", "member_profitamount", "member_profitamount_paid", "member_profitamount_paidtime", "notes", "lasttime_stayindebt",
         "member_profitlost_cashcost", "member_profitlost_marketingcost", "sum_tokenfavorable", "sum_tokenpreferential", "member_profitlost_platformcost",
         "days_count", "sum_all_count", "sum_all_bets", "sum_all_wins", "sum_all_profitlost"
         )'.
         "VALUES ('".$b['member_account']."', '".$b['member_parent_id']."', '".$b['member_therole']."', now(), '".$b['dailydate_start']."', '".$b['dailydate_end']."',
         '".$b['member_level']."', '".$b['skip_bonusinfo']."', '".$b['profitaccount_1']."', '".$b['profitaccount_2']."', '".$b['profitaccount_3']."', '".$b['profitaccount_4']."', '".$b['profit_amount']."',
         '".$b['profit_amount_1']."', '".$b['profit_amount_2']."', '".$b['profit_amount_3']."', '".$b['profit_amount_4']."', NULL, NULL, NULL, NULL, NULL,
         '".$b['member_profitlost_cashcost']."', '".$b['member_profitlost_marketingcost']."', '".$b['sum_tokenfavorable']."', '".$b['sum_tokenpreferential']."', '".$b['member_profitlost_platformcost']."',
         '".$b['days_count']."', '".$b['sum_all_count']."', '".$b['sum_all_bets']."', '".$b['sum_all_wins']."', '".$b['sum_all_profitlost']."'
         ); ";

        // print_r($insert_sql);

        $insert_result = runSQL($insert_sql);
        if($insert_result == 1){
          $logger = '沒有紀錄插入紀錄成功'.$b['member_id'].'Member account is '.$b['member_account'].',date start:'.$current_datepicker_start.',date end:'.$current_datepicker;
          //echo $logger;
        }else{
          $logger = '沒有紀錄插入紀錄失敗'.$b['member_id'].'Member account is '.$b['member_account'].',date start:'.$current_datepicker_start.',date end:'.$current_datepicker;
          die($logger);
        }
      }
      // -----------------------------------------------------------------------
      // END 第二次更新
      // -----------------------------------------------------------------------


      // -----------------------------------------------------------------------
      // 參考用數據 , 不顯示因為畫面塞不下
      // -----------------------------------------------------------------------
      $member_profitlost_ref = '統計天數'.$b['days_count'].',會員注單量'.$b['sum_all_count'].',投注'.$b['sum_all_bets'].',派彩'.$b['sum_all_wins'].
      ',損益(不扣成本)'.$b['sum_all_profitlost'].',平台佔成本比例'.$rule['platformcost_rate'].'%,平台成本'.$b['member_profitlost_platformcost'].
      ',行銷成本'.$b['member_profitlost_marketingcost'].',優惠成本'.$b['sum_tokenfavorable'].',反水成本'.$b['sum_tokenpreferential'].',金流成本'.$b['member_profitlost_cashcost'];
      // -----------------------------------------------------------------------

      // 付款資訊欄位, 包含動作處理 call 另外一個程式
      // 只有查詢模式的時候, 這個變數才會出現. 才讓使用者有所動作選擇.
      $paid_notes = '发放'.$b['member_account'].'帐号'.$b['dailydate_start'].'的營運利潤獎金';
      if(isset($b['id'])) {
        // 付款資訊 , 不處理,不更新. 變數設定但是這個選項不顯示
        // 沒有付過款項, 且款項大於 0
        if($b['member_profitamount_paid'] == NULL AND $b['member_profitamount'] > 0 ) {
          // 轉帳
          $member_profitamount_paid_html = '<a href="member_depositgcash.php?a='.$b['member_id'].'&gcash='.round($b['member_profitamount'],2).'&notes='.$paid_notes.'" title="立即进行转帐给'.$b['member_account'].'金额'.round($b['member_profitamount']).'" target="_blank"><span class="glyphicon glyphicon-plus-sign" aria-hidden="true"></a>';
          // 寫入付款欄位資訊
          $member_profitamount_paid_html = $member_profitamount_paid_html.'&nbsp;&nbsp;
          <a href="bonus_commission_profit_action.php?a=member_profitamount_paid&id='.$b['id'].'"  onclick="return confirm(\'请确认已经汇款完成了,再来更新此栏位 ??\')" title="金额写入这个栏位" target="_blank">
          <span class="glyphicon glyphicon-pencil" aria-hidden="true"></a>';

        // 沒有付過款項, 且款項小於 0
        }elseif($b['member_profitamount_paid'] == NULL AND $b['member_profitamount'] < 0 ) {
            $member_profitamount_paid_html = '<a href="#" title="本月營收為負營收，紀錄到下個月留抵"  target="_BLANK"><span class="glyphicon glyphicon-minus-sign" aria-hidden="true"></span></a>';

        // 沒有付過款項, 且款項為 0 , 身份為 Member
        }elseif($b['member_profitamount_paid'] == NULL AND $b['member_profitamount'] == 0 AND $b['member_therole'] == 'M' ) {
          $member_profitamount_paid_html = '<a href="#" title="會員身份無組織沒有分紅" target="_BLANK"><span class="glyphicon glyphicon-remove-circle" aria-hidden="true"></span></a>';

        // 沒有付過款項, 且款項為 0
        }elseif($b['member_profitamount_paid'] == NULL AND $b['member_profitamount'] == 0 ) {
          $member_profitamount_paid_html = '<a href="#" title="本月無營收" target="_BLANK"><span class="glyphicon glyphicon-ban-circle" aria-hidden="true"></span></a>';

        }else{
          if($b['member_profitamount_paid'] < 0) {
            $member_profitamount_paid_html = '<a href="#" title="本月累加上月後為負營收,系統自動紀錄於此下月留抵此金額."  target="_BLANK">'.$b['member_profitamount_paid'].'</a>';

          }else{
            $member_profitamount_paid_html = '<a href="#" title="已經有付款紀錄，請確認後再行轉帳轉帳到會員現金帳戶。"  target="_BLANK">'.$b['member_profitamount_paid'].'</a>';

          }
        }
        // var_dump($b);
      }else{
        $member_profitamount_paid_html = '<a href="#" title="只有查詢模式, 才有動作可以選擇"  target="_BLANK">n/a</a>';
      }

      // 把付款更新的時間, 改變一下呈現的格式. 避免太長的欄位
      // date("Y-m-d H:i:s",strtotime($b['member_profitamount_paidtime']))
      if($b['member_profitamount_paidtime'] == NULL) {
        $member_profitamount_paidtime_html = 'n/a';
      }else{
        $member_profitamount_paidtime_html = '<a href="#" title="'.$b['member_profitamount_paidtime'].'">'.date("m-d H:i",strtotime($b['member_profitamount_paidtime'])).'</a>';
      }




      // ----------------------------------------------------
      // 表格 row -- tables DATA
      // 前3個欄位是預設會員的資料
      $show_listrow_html = $show_listrow_html.'
      <tr>
        <td>'.$member_id_html.'</td>
        <td>'.$member_account_html.'</td>
        <td>'.$member_therole_html.'</td>
        <td>'.$b['member_level'].'</td>
        <td><a href="#" title="'.$b['skip_bonusinfo'].'">'.$b['skip_agent_tree_count'].'</a></td>
        <td><a href="#" title="'.$member_profitlost_ref.'">'.$b['profit_amount'].'</a></td>
        <td>'.$b['member_profitamount_count_1'].'</td>
        <td><span style="color:#9900ff;">'.$b['member_profitamount_1'].'<span></td>
        <td>'.$b['member_profitamount_count_2'].'</td>
        <td><span style="color:red;">'.$b['member_profitamount_2'].'<span></td>
        <td>'.$b['member_profitamount_count_3'].'</td>
        <td><span style="color:green;">'.$b['member_profitamount_3'].'<span></td>
        <td>'.$b['member_profitamount_count_4'].'</td>
        <td><span style="color:#ff00aa;">'.$b['member_profitamount_4'].'<span></td>
        <td>'.$b['member_profitamount_count'].'</td>
        <td><span style="color:blue;">'.$b['member_profitamount'].'<span></td>
        <td>'.$b['lasttime_stayindebt'].'</td>
      </tr>
      ';

/*

<td><span style="color:blue;">'.$b['profitaccount_1'].'<span></td>
<td><span style="color:red;">'.$b['profitaccount_2'].'<span></td>
<td><span style="color:green;">'.$b['profitaccount_3'].'<span></td>
<td><span style="color:#ff00aa;">'.$b['profitaccount_4'].'<span></td>
<td>'.$b['sum_all_bets'].'</td>
<td><span style="color:blue;">'.$b['profit_amount_1'].'<span></td>
<td><span style="color:red;">'.$b['profit_amount_2'].'<span></td>
<td><span style="color:green;">'.$b['profit_amount_3'].'<span></td>
<td><span style="color:#ff00aa;">'.$b['profit_amount_4'].'<span></td>
<td>'.$member_profitamount_paid_html.'</td>
<td>'.$member_profitamount_paidtime_html.'</td>
<td>'.$b['notes'].'</td>
*/
      // ----------------------------------------------------
    }
    // 表格資料 row list end

  }else{
    // NO member
    $show_listrow_html = $show_listrow_html.'
    <tr>
      <th></th><th></th><th></th>
      <th></th><th></th><th></th><th></th>
      <th></th><th></th><th></th><th></th>
      <th></th><th></th><th></th><th></th>
      <th></th><th></th><th></th><th></th>
      <th></th><th></th><th></th><th></th>
    </tr>
    ';
  }
  // ---------------------------------- END table data get
  // 表格欄位名稱
  $table_colname_html = '
  <tr>
    <th>會員ID</th>
    <th>會員帳號</th>
    <th>會員身份</th>
    <th>所在層數</th>
    <th>被跳過的代理</th>
    <th>個人貢獻平台的損益</th>
    <th><a href="#" title="代理商本月第1代分潤來源筆數">第1代分潤筆數</a></th>
    <th><a href="#" title="代理商本月第1代分潤">第1代分潤</a></th>
    <th><a href="#" title="代理商本月第2代分潤來源筆數">第2代分潤筆數</a></th>
    <th><a href="#" title="代理商本月第2代分潤">第2代分潤</a></th>
    <th><a href="#" title="代理商本月第3代分潤來源筆數">第3代分潤筆數</a></th>
    <th><a href="#" title="代理商本月第3代分潤">第3代分潤</a></th>
    <th><a href="#" title="代理商本月第4代分潤來源筆數">第4代分潤筆數</a></th>
    <th><a href="#" title="代理商本月第4代分潤">第4代分潤</a></th>
    <th><a href="#" title="代理商本月分潤來源總筆數">分潤來源總筆數</a></th>
    <th><a href="#" title="代理商本月分潤總和">分潤總和</a></th>
    <th><a href="#" title="代理商上月留抵">上月留抵</a></th>
  </tr>
  ';
/*
<th>達成第1代</th>
<th>達成第2代</th>
<th>達成第3代</th>
<th>達成第4代</th>
<th>總投注量</td>
<th>第1代分紅</th>
<th>第2代分紅</th>
<th>第3代分紅</th>
<th>第4代分紅</th>
<th><a href="#" title="代理商本月分潤付款金額">本月付款金額</a></th>
<th><a href="#" title="代理商本月分潤付款時間">本月付款時間</a></th>
<th>備註</th>
*/


// var_dump($b);
// $csv_data['data'][$i] = $b; 資料來自員 $b 陣列, 要注意資料量很大的時候需要改寫 csv file 的生成方式
// -------------------------------------------
// 寫入 CSV 檔案的抬頭 - -和實際的 table 並沒有完全的對應
// -------------------------------------------

$t = 0;
$csv_data['table_colname'][$t++] = '統計開始時間';
$csv_data['table_colname'][$t++] = '統計結束時間';
$csv_data['table_colname'][$t++] = '會員ID';
$csv_data['table_colname'][$t++] = '會員上層ID';
$csv_data['table_colname'][$t++] = '會員身份';
$csv_data['table_colname'][$t++] = '會員帳號';
$csv_data['table_colname'][$t++] = 'ID_PK';
$csv_data['table_colname'][$t++] = '最後更新時間';
$csv_data['table_colname'][$t++] = '所在層數';

$csv_data['table_colname'][$t++] = '被跳過得代理資訊';
$csv_data['table_colname'][$t++] = '被跳過得代理商數量';
$csv_data['table_colname'][$t++] = '達成業績第1代';
$csv_data['table_colname'][$t++] = '達成業績第2代';
$csv_data['table_colname'][$t++] = '達成業績第3代';
$csv_data['table_colname'][$t++] = '達成業績第4代';
$csv_data['table_colname'][$t++] = '個人貢獻平台的損益';
$csv_data['table_colname'][$t++] = '第1代分紅';
$csv_data['table_colname'][$t++] = '第2代分紅';
$csv_data['table_colname'][$t++] = '第3代分紅';
$csv_data['table_colname'][$t++] = '第4代分紅';

$csv_data['table_colname'][$t++] = '金流成本';
$csv_data['table_colname'][$t++] = '行銷成本 = (優惠金額 + 反水金額)';
$csv_data['table_colname'][$t++] = '優惠成本';
$csv_data['table_colname'][$t++] = '反水成本';
$csv_data['table_colname'][$t++] = '平台成本';
$csv_data['table_colname'][$t++] = '此紀錄累積統計的天數(日報表資料筆數)';
$csv_data['table_colname'][$t++] = '會員注單量';
$csv_data['table_colname'][$t++] = '全部的投注金額';
$csv_data['table_colname'][$t++] = '全部的派彩金額';
$csv_data['table_colname'][$t++] = '全部的損益金額(未扣成本)';

$csv_data['table_colname'][$t++] = '(2nd)代理商本月第1代分潤來源筆數';
$csv_data['table_colname'][$t++] = '(2nd)代理商本月第1代分潤';
$csv_data['table_colname'][$t++] = '(2nd)代理商本月第2代分潤來源筆數';
$csv_data['table_colname'][$t++] = '(2nd)代理商本月第2代分潤';
$csv_data['table_colname'][$t++] = '(2nd)代理商本月第3代分潤來源筆數';
$csv_data['table_colname'][$t++] = '(2nd)代理商本月第3代分潤';
$csv_data['table_colname'][$t++] = '(2nd)代理商本月第4代分潤來源筆數';
$csv_data['table_colname'][$t++] = '(2nd)代理商本月第4代分潤';
$csv_data['table_colname'][$t++] = '(2nd)代理商本月分潤來源總筆數';
$csv_data['table_colname'][$t++] = '(2nd)代理商本月分潤總和';
$csv_data['table_colname'][$t++] = '(2nd)代理商的分潤付款(負值帳號為留抵下次扣除)';
$csv_data['table_colname'][$t++] = '(2nd)代理商本月分潤付款時間';
$csv_data['table_colname'][$t++] = '(2nd)代理商會員個人上月留抵負債(下月計算時扣除)';
$csv_data['table_colname'][$t++] = '備註';
// var_dump($csv_data);

//var_dump($csv_data['table_colname']);
//var_dump($csv_data['data'][1]);
//var_dump($csv_data['data'][2]);

// -------------------------------------------
// 將內容輸出到 檔案 , csv format
// -------------------------------------------

// 有資料才執行 csv 輸出, 避免 insert or update or stats 生成同時也執行 csv 輸出
if(isset($csv_data['data'])) {

    $filename      = "bonusprofit_result_".$current_datepicker_start.'_'.$current_datepicker.'.csv';
    $absfilename   = dirname(__FILE__) ."/tmp_dl/$filename";
    $filehandle    = fopen("$absfilename","w");
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
    // -------------------------------------------
    // 下載按鈕
    // -------------------------------------------
    if(file_exists($absfilename)) {
      $csv_download_url_html = '<a href="./tmp_dl/'.$filename.'" class="btn btn-success" >下載CSV</a>';
    }else{
      $csv_download_url_html = '';
    }
    // -------------------------------------------
  }else{
    $csv_download_url_html = '';
  }
  // -------------------------------------------
  // 將內容輸出到 檔案 , csv format END
  // -------------------------------------------




  // -------------------------------------------------------------------------
  // sorttable 的 jquery and plug info
  // -------------------------------------------------------------------------
  $sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
  // $sorttablecss = ' class="table table-striped" ';

  // 列出資料, 主表格架構
  $show_list_html = '';
  // 列表
  $show_list_html = $show_list_html.'
  <table '.$sorttablecss.'>
  <thead>
  '.$table_colname_html.'
  </thead>
  <tfoot>
  '.$table_colname_html.'
  </tfoot>
  <tbody>
  '.$show_listrow_html.'
  </tbody>
  </table>
  ';

  // 參考使用 datatables 顯示
  // https://datatables.net/examples/styling/bootstrap.html
  $extend_head = $extend_head.'
  <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
  <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
  <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
  ';
  // DATA tables jquery plugging -- 要放在 head 內 不可以放 body
  $extend_head = $extend_head.'
  <script type="text/javascript" language="javascript" class="init">
    $(document).ready(function() {
      $("#show_list").DataTable( {
          "paging":   true,
          "ordering": true,
          "info":     true,
          "order": [[ 0, "desc" ]],
					"pageLength": 100
      } );
    } )
  </script>
  ';
  // -------------------------------------------------------------------------
  // sorttable 的 jquery and plug info END
  // -------------------------------------------------------------------------



  // -------------------------------------------------------------------------
  $show_tips_html = '<div class="alert alert-info">
  <p>* 目前查詢的日期為 '.$current_datepicker_start.' ~ '.$current_datepicker.' 的彩金報表，為美東時間(UTC -04)，每日結算時間範圍為 '.$current_datepicker.' 00:00:00 -04 ~ '.$current_datepicker.' 23:59:59 -04 </p>
  <p>* 對應的中原時間(UTC +08)範圍為：'.date( "Y-m-d", strtotime( "$current_datepicker -1 day")).' 13:00:00+08 ~ '.$current_datepicker.' 12:59:59+08</p>
  <p>'.$show_rule_html.'</p>
  </div>';


  // -------------------------------------------------------------------------
  // 加盟金計算報表 -- 選擇日期 -- FORM
  // -------------------------------------------------------------------------
  $date_selector_html = '
  <form class="form-inline" method="get">
    <div class="form-group">
      <div class="input-group">
        <div class="input-group-addon">指定查詢年份與月份</div>
        <div class="input-group-addon"><input type="text" class="form-control" name="current_datepicker" id="current_datepicker" placeholder="ex:2017-01-22" value="'.$current_datepicker.'"></div>
        <div class="input-group-addon"><input type="radio" value="0" name="update_bonusprofit_option" checked>查詢當月的營運利潤獎金統計</div>
        <div class="input-group-addon"><input type="radio" value="1" name="update_bonusprofit_option">(1)營運利潤獎金統計資料更新</div>
        <div class="input-group-addon"><input type="radio" value="2" name="update_bonusprofit_option">(2)個人營運利潤獎金統計更新</div>
    </div>
    </div>
    <button type="submit" class="btn btn-primary" id="daily_statistics_report_date_query"  onclick="blockscreengotoindex();" >查詢</button>
    '.$csv_download_url_html.'
  </form>
  <hr>';

  // 日期 jquery 選單 , 預設選取的日期範圍
  // default date
  $dateyearrange_start 	= date("Y");
  $dateyearrange_end 		= date("Y");
  $dateyearrange = $dateyearrange_start.':'.$dateyearrange_end;
  // ref: http://api.jqueryui.com/datepicker/#entry-examples
  $date_selector_js = '
  <script>
    $(document).ready(function() {
      $( "#current_datepicker" ).datepicker({
        yearRange: "'.$dateyearrange_start.':'.$dateyearrange_end.'",
        maxDate: "+0d",
        minDate: "-13w",
        showButtonPanel: true,
      	dateFormat: "yy-mm-dd",
      	changeMonth: true,
      	changeYear: true
      });
    } );
  </script>
  ';

  // 選擇日期 html
  $show_dateselector_html = $date_selector_html.$date_selector_js;
  // -------------------------------------------------------------------------




  // -------------------------------------------------------------------------
  // 目前動作狀態：
  $action_status = '';
  if($action_status_number == 0) {
    $action_status = $action_status.'<span class="label label-primary">(0)查詢當月的營運利潤獎金統計</span>';
    $action_status = $action_status.'<span class="label label-default">(1)營運利潤獎金統計資料更新</span>';
    $action_status = $action_status.'<span class="label label-default">(2)個人營運利潤獎金統計更新</span>';
  }elseif($action_status_number == 1) {
    $action_status = $action_status.'<span class="label label-default">(0)查詢當月的營運利潤獎金統計</span>';
    $action_status = $action_status.'<span class="label label-primary">(1)營運利潤獎金統計資料更新</span>';
    $action_status = $action_status.'<span class="label label-default">(2)個人營運利潤獎金統計更新</span>';
  }elseif($action_status_number == 2) {
    $action_status = $action_status.'<span class="label label-default">(0)查詢當月的營運利潤獎金統計</span>';
    $action_status = $action_status.'<span class="label label-default">(1)營運利潤獎金統計資料更新</span>';
    $action_status = $action_status.'<span class="label label-primary">(2)個人營運利潤獎金統計更新</span>';
  }

  // -------------------------------------------------------------------------
  // 顯示資料的參考資訊
  $show_datainfo_html = '<div class="alert alert-success">
  <p>* 目前動作狀態：'.$action_status.'</p>
  <p>* 日期 <span class="label label-default">'.$current_datepicker_start.'~'.$current_datepicker.'</span> 的營業利潤統計資料，每月'.$rule['stats_profit_day'].'日為結算日。 </p>
  <p>* 此次分紅代理商符合發放資格業績量(總投注量)大於 '.$config['currency_sign'].$rule['amountperformance_month'].'列入統計分潤。</p>
  <p>* 此分紅代理商只要營利結算為正值，即發放現金派彩。如為虧損狀態則保留到下月結算盈餘時扣除虧損後發放。
  </div>
  ';
  // -------------------------------------------------------------------------
  $show_datainfo_right_html = '<div class="alert alert-success">
  * 收入(3)-公司的營利獎金, 利潤成本計算公式：<br>
  時間區間：月結 <br>
  個人貢獻平台的損益 = 個人娛樂城損益 - 平台成本 - 行銷成本 - 金流成本 <br>
  平台成本 = (個人娛樂城損益 * 平台成本比例)  (平台成本比例 5% ~ 17%, 目前設定 12%) <br>
  行銷成本 = (優惠金額 + 反水金額) <br>
  金流成本 = (提款成本 + 出款成本) , 金流成本比例 0.8 ~ 2% <br>
  如果代理商損益經過四層分紅計算後，該月負值，則累積到下個月分潤盈餘扣儲上月留底後為正值後發放，如為負值則繼續累計。<br>
  </div>
  ';
  // -------------------------------------------------------------------------






  // ---------------------------------------------------------------------------
  // 生成左邊的報表 list index
  // ---------------------------------------------------------------------------
  $indexmenu_stats_switch_html = indexmenu_stats_switch();
  // ---------------------------------------------------------------------------
  // 生成左邊的報表 list index END
  // ---------------------------------------------------------------------------


  // ---------------------------------------------------------------------------
  // 指定查詢月份的統計結算列表 -- 摘要
  // ---------------------------------------------------------------------------
  $summary_report_html = summary_report($current_datepicker_start, $current_datepicker);
  // ---------------------------------------------------------------------------
  // 指定查詢月份的統計結算列表 -- 摘要 end
  // ---------------------------------------------------------------------------






  // -------------------------------------------------------------------------
  // 切成 1 欄版面的排版
  // -------------------------------------------------------------------------
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">

    <div class="col-12 col-md-12">
    '.$indexmenu_stats_switch_html.'
    '.$show_tips_html.'
    </div>

    <div class="col-12 col-md-12">
    '.$show_dateselector_html.'
    </div>

    <div class="col-12 col-md-6">
    '.$show_datainfo_html.'
    </div>
    <div class="col-12 col-md-6">
    '.$show_datainfo_right_html.'
    </div>

    <div class="col-12 col-md-12">
    '.$summary_report_html.'
    </div>

  	<div class="col-12 col-md-12">
      '.$show_list_html.'
  	</div>

	</div>
	<br>
  	<div class="row">
		<div id="preview_result"></div>
	</div>
	';
// -------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']								= $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/beadmin.tmpl.php");
include("template/beadmin_fluid.tmpl.php");
?>
