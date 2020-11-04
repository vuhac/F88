<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 營業獎金
// File Name:	bonus_commission_sale_summary.php
// Author:    Barkley
// Related:
// DB table:  root_statisticsdailyreport 每日統計報表
// DB table:  root_statisticsbonussale 營運獎金報表, 資料由每日統計報表生成, 並且計算後輸出到此表.
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


session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// betlog 專用的 DB lib
//require_once dirname(__FILE__) ."/config_betlog.php";

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
$function_title 		= '放射线组织奖金计算-营业奖金';
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

  // ---------------------------------------------------------------
  // MAIN start
  // ---------------------------------------------------------------

  // -------------------------------------------------------------------------
  // find member tree to root
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
  // find member tree to root , END
  // -------------------------------------------------------------------------



  // ---------------------------------------------------------------------------
  // 檢查系統資料庫中 table root_statisticsbonussale 表格(放射線組織獎金計算-營業獎金)有多少資料被生成了, 建立索引檔及提供可以更新的資訊
  // 搭配 indexmenu_stats_switch 使用
  // Usage: menu_sale_list_html()
  // ---------------------------------------------------------------------------
  function menu_sale_list_html() {


  // -------------------------------------------------------------------------
  // 列表(目前 DB 內存在的 date
  // -------------------------------------------------------------------------
  $daily_list_range_sql = "select count(dailydate_start) as count_dailydate , dailydate_start,dailydate_end ,MIN(updatetime) as min , MAX(updatetime) as max
  ,sum(perfor_bounsamount) as sum_perfor_bounsamount , sum(member_bonusamount) as sum_member_bonusamount
  FROM root_statisticsbonussale GROUP BY dailydate_start,dailydate_end ORDER BY dailydate_start DESC ;";
  $daily_list_range_result = runSQLall($daily_list_range_sql);
  //var_dump($daily_list_range_result);
  $dailydate_stats_data = '';
  for($g=1;$g<=$daily_list_range_result[0];$g++){
    // 日期區間
    $dailydate_list_value_html = '<a href="?current_datepicker='.$daily_list_range_result[$g]->dailydate_start.'&update_bonussale_option=0" target="_top" title="'.$daily_list_range_result[$g]->min.'~'.$daily_list_range_result[$g]->max.'">'.$daily_list_range_result[$g]->dailydate_start.'~'.$daily_list_range_result[$g]->dailydate_end.'</a>';
    // 資料數量
    $dailydate_count = $daily_list_range_result[$g]->count_dailydate;
    // 營業獎金分紅合計
    $sum_perfor_bounsamount = $daily_list_range_result[$g]->sum_perfor_bounsamount;
    // 會員個人的分紅合計
    $sum_member_bonusamount = $daily_list_range_result[$g]->sum_member_bonusamount;

    $dailydate_stats_data = $dailydate_stats_data.
    '<tr>
      <td>'.$dailydate_list_value_html.'</td>
      <td>'.$dailydate_count.'</td>
      <td>'.$sum_perfor_bounsamount.'</td>
      <td>'.$sum_member_bonusamount.'</td>
    </tr>
    ';
  }

  // 選單表格
  $daily_list_range_table_html = '
  <table class="table table-bordered small">
    <thead>
      <tr class="active">
        <th>日期區間<span class="glyphicon glyphicon-time"></span>(-04)</th>
        <th>資料數量</th>
        <th>營業獎金分紅合計</th>
        <th>會員個人的分紅合計</th>
      </tr>
    </thead>
    <tbody style="background-color:rgba(255,255,255,0.9);">
      '.$dailydate_stats_data.'
    </tbody>
  </table>
  ';


  // 左上角可隱藏選單列表
  $dailydate_index_stats_switch_html = '
  <span style="
  position: fixed;
  top: 5px;
  left: 5px;
  width: 400px;
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
  width: 400px;
  height: 80%;
  overflow: auto;
  z-index: 999;
  -webkit-box-shadow: 0px 8px 35px #333;
  -moz-box-shadow: 0px 8px 35px #333;
  box-shadow: 0px 8px 35px #333;
  background-color:rgba(255,255,255,0.9);
  ">
  '.$daily_list_range_table_html.'
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

  return($dailydate_index_stats_switch_html);
  }

  // -------------------------------------------------------------------------





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


  // -------------------------------------------
  // 計算目前時間區間內，符合 $rule['amountperformance'] 發放資格的條件的會員人數。
  // -------------------------------------------
  function amountperformance_userlist_count($current_datepicker_start, $current_datepicker, $amountperformance ) {

    $sql = "SELECT sum(all_bets) as sum_all_bets ,count(all_bets) as count_all_bets FROM root_statisticsdailyreport
WHERE dailydate >= '$current_datepicker_start' AND dailydate <= '$current_datepicker' GROUP BY member_id HAVING sum(all_bets) >= $amountperformance ;";

    //var_dump($sql);
    $amountperformance_userlist_count = runSQL($sql);
    //var_dump($all_bets_amount_result);

    return($amountperformance_userlist_count);
  }
  // -------------------------------------------

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

  // -------------------------------------------------------------------------
  // $_GET 取得日期
  // -------------------------------------------------------------------------
  // get example: ?current_datepicker=2017-02-03
  // ref: http://php.net/manual/en/function.checkdate.php
  function validateDate($date, $format = 'Y-m-d H:i:s')
  {
      $d = DateTime::createFromFormat($format, $date);
      return $d && $d->format($format) == $date;
  }

  // 預設星期幾為預設 7 天的起始週期
  $weekday = 	$rule['stats_weekday'];
  // 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
  if(isset($_GET['current_datepicker'])) {
    // 判斷格式資料是否正確
    if(validateDate($_GET['current_datepicker'], 'Y-m-d')) {
      $current_datepicker = $_GET['current_datepicker'];
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
    // php 格式的 2017-02-24
    // default 抓取本週最近的星期三
    $current_datepicker = date('Y-m-d' ,strtotime("$weekday"));
    // var_dump($current_datepicker);
  }

  //var_dump($current_datepicker);
  //echo date('Y-m-d H:i:sP');
  // 統計的期間時間 1-7  $rule['stats_bonus_days']
  $stats_bonus_days         = $rule['stats_bonus_days']-1;
  $current_datepicker_start = date( "Y-m-d", strtotime( "$current_datepicker -$stats_bonus_days day"));
  //var_dump($current_datepicker_start);

  // 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
  if(isset($_GET['current_per_size']) AND $_GET['current_per_size'] != NULL ) {
    $current_per_size = $_GET['current_per_size'];
  }else{
    $current_per_size = 100000;
    //$current_per_size = 25 ;
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
  // 變數：更新營業獎金的投注量資訊
  if(isset($_GET['update_bonussale_option']) AND $_GET['update_bonussale_option'] == '1') {
    $update_bonussale_status = true;
  }else{
    $update_bonussale_status = false;
  }
  // 變數：更新營業獎金的個人分配金額資訊
  if(isset($_GET['update_bonussale_option']) AND $_GET['update_bonussale_option'] == '2') {
    $update_bonussale_amount_status = true;
  }else{
    $update_bonussale_amount_status = false;
  }


	// 計算目前時間區間內，符合 $rule['amountperformance'] 發放資格的條件的會員人數。
	$amountperformance_userlist_count = amountperformance_userlist_count($current_datepicker_start, $current_datepicker, $rule['amountperformance'] );
  // var_dump($amountperformance_userlist_count);
  // -------------------------------------------



  // -------------------------------------
  // 列出所有的會員資料及人數 SQL
  // -------------------------------------
  // 算人數
  $userlist_sql   = "SELECT * FROM root_member ORDER BY id;";
  //var_dump($userlist_sql);
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
  //var_dump($userlist_sql);
  $userlist       = runSQLall($userlist_sql);
  $userlist_count = $userlist[0];



  // 存放列表的 html -- 表格 row -- tables DATA
  $show_listrow_html = '';
  // 判斷 root_member count 數量大於 1 , 有會員資料的話才繼續
  if($userlist[0] >= 1) {
    // 統計插入的資料有多少
    $stats_insert_count = 0;
    // 統計 update 的資料有多少
    $stats_update_count = 0;
    // 統計更新個人結算的資料有多少。
    $stats_bonusamount_count = 0;
    // 統計顯示的資料有多少
    $stats_showdata_count = 0;

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
      //var_dump($getdata_bonussale_result);

      // 指定的日期 + 會員 , 沒有資料的狀況處理
      if($getdata_bonussale_result[0] == 0) {
        // data 取得資料 , 從日報即時計算, 速度較慢.判斷資料是否全部都有取得.
        $b = bonus_commission_sale_data($userlist[$i],$current_datepicker_start, $current_datepicker);
        //var_dump($b);

        // 沒資料 insert
        $insert_sql = 'INSERT INTO "root_statisticsbonussale" ("member_account", "member_parent_id", "member_therole", "updatetime", "dailydate_start", "dailydate_end", "member_level", "skip_bonusinfo"
          , "perforaccount_1", "perforaccount_2", "perforaccount_3", "perforaccount_4", "all_betsamount", "all_betscount"
          , "perfor_bounsamount", "perforbouns_1", "perforbouns_2", "perforbouns_3", "perforbouns_4", "perforbouns_root")'.
        "VALUES ('".$b['member_account']."', '".$b['member_parent_id']."', '".$b['member_therole']."', now(), '".$b['dailydate_start']."', '".$b['dailydate_end']."', '".$b['member_level']."', '".$b['skip_bonusinfo']."'
        , '".$b['perforaccount_1']."', '".$b['perforaccount_2']."', '".$b['perforaccount_3']."', '".$b['perforaccount_4']."', '".$b['all_betsamount']."', '".$b['all_betscount']."'
        , '".$b['perfor_bounsamount']."', '".$b['perforbouns_1']."', '".$b['perforbouns_2']."', '".$b['perforbouns_3']."', '".$b['perforbouns_4']."', '".$b['perforbouns_root']."');";
        //var_dump($insert_sql);
        //print_r($insert_sql);
        $bonussale_result = runSQL($insert_sql);
        if($bonussale_result == 1) {
          //var_dump($bonussale_result);
          //echo 'no data to insert - '.$b['member_account'].'<br>';
          $stats_insert_count++;
        }else{
          $logger = "$current_datepicker_start ~ $current_datepicker".'會員 '.$userlist[$i]->account.' 插入資料有問題，請聯絡開發人員處理。';
          die($logger);
        }

      }else{
        // 指定的會員 + 日期, 內有資料的狀態選擇
        // var_dump($getdata_bonussale_result[1]->member_account);
        // 是否從每日營收報表, 更新統計資料到 root_statisticsbonussale
        if($update_bonussale_status == true) {
          // 有資料的狀態選擇,更新raw資料
          // data 取得資料 , 從日報即時計算, 速度較慢.判斷資料是否全部都有取得.
          $b = bonus_commission_sale_data($userlist[$i],$current_datepicker_start, $current_datepicker);
          //var_dump($b);

          // 檢查是否有 count 資料
          // echo 'Update 已經存在的資料';

          $update_sql = "
          UPDATE root_statisticsbonussale SET
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

          //var_dump($update_sql);
          //print_r($update_sql);
          $update_result = runSQL($update_sql);
          //var_dump($update_result);
          if($update_result == 1) {
            // echo '更新統計資料 - '.$b['member_account'].'<br>';
            $stats_update_count++;
          }else{
            $logger = "$current_datepicker_start ~ $current_datepicker".'會員 '.$b['member_account'].'更新統計資料有問題，請聯絡開發人員處理。';
            die($logger);
          }

        }else{
          // 有資料的狀態選擇,不更新 raw data
          // 取出資料，並計算獎金分紅寫入 DB

          $b['id']                  = $getdata_bonussale_result[1]->id;
          // get data member id
          $b['member_id']    = $userlist[$i]->id;
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

          // 是否更新計算個人的獎金收入合計
          if($update_bonussale_amount_status == true) {
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
              //var_dump($bns_update_sql);
              $bns_update_result = runSQL($bns_update_sql);
              //var_dump($bns_update_result);
              if($bns_update_result == 1) {
                // 更新個人分紅收入累計 + 1
                $stats_bonusamount_count++;
                $logger = 'Success 帳號'.$b['member_account']."日期: $current_datepicker_start ~ $current_datepicker".'更新個人分紅收入欄位。';
                // echo  $logger;
              }else{
                $logger = 'False 帳號'.$b['member_account']."日期: $current_datepicker_start ~ $current_datepicker".'更新個人分紅收入欄位更新失敗，請聯絡開發人員處理。';
                die($logger);
              }

            }else{
              $logger = 'False 帳號'.$b['member_account']."日期: $current_datepicker_start ~ $current_datepicker".'營收分紅加總失敗，請聯絡開發人員處理。';
              die($logger);
            }
            //var_dump($b['member_bonusamount']);
            //var_dump($b['count_perforbouns_sumall']);


          }else{
            // 不更新, 單純顯示
            //echo '不更新 show table data - '.$userlist[$i]->account.'<br>';
            $stats_showdata_count++;

            // 只有在這個狀態才可以允許 download CSV , 因為 array 排列才會正確.
            $csv_data['data'][$i] = $b;

          }

        }

      }
      // end update
/*
      var_dump($b['member_bonuscount_1']);
      var_dump($b['member_bonuscount_2']);
      var_dump($b['member_bonuscount_3']);
      var_dump($b['member_bonuscount_4']);
*/

      // ----------------------------------------------------
      // 表格顯示, 如有不同的顯示方式再這裡調整
      // ----------------------------------------------------
      // 會員的 ID
      $member_id_html = '<a href="member_treemap.php?id='.$b['member_id'].'" target="_BLANK" title="列出會員的組織圖">'.$b['member_id'].'</a>';
      // 會員的 account
      $member_account_html = '<a href="member_account.php?a='.$b['member_id'].'" target="_BLANK" title="檢查會員的詳細資料">'.$b['member_account'].'</a>';
      // skip agent
      $skip_agent_tree_html = '<a href="#" title="'.$b['skip_bonusinfo'].'">'.$b['skip_agent_tree_count'].'</a>';
      // 總投注量及投注筆數
      $all_betsamount_html  = '<a href="#" title="投注筆數'.$b['all_betscount'].'">'.$b['all_betsamount'].'</a>';
      // 分紅比率 in title , 設定在 system_config.php 檔案內 , 單位 %
      $sale_bonus_rate_amount     = $rule['sale_bonus_rate'];


      // 發放 member_bonusamount_paid
      $paid_notes = '发放'.$b['member_account'].'帐号'.$b['dailydate_start'].'的营业奖金'.round($b['member_bonusamount'],2);
      if($b['member_bonusamount'] > 0 AND $b['member_bonusamount_paid'] == NULL) {
        // 轉帳
        $member_bonusamount_paid_html = '<a href="member_depositgcash.php?a='.$b['member_id'].'&gcash='.round($b['member_bonusamount'],2).'&notes='.$paid_notes.'" title="立即进行转帐给'.$b['member_account'].'金额'.round($b['member_bonusamount'],2).'" target="_blank"><span class="glyphicon glyphicon-plus-sign" aria-hidden="true"></a>';
        // 寫入付款欄位資訊
        $member_bonusamount_paid_html = $member_bonusamount_paid_html.'&nbsp;&nbsp;<a href="bonus_commission_sale_action.php?a=member_bonusamount_paid&id='.$b['id'].'"  onclick="return confirm(\'请确认已经汇款完成了,再来更新此栏位 ??\')" title="金额写入这个栏位" target="_blank"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></a>';
      }elseif($b['member_bonusamount'] == 0 AND ($b['member_bonusamount_paid'] == NULL OR $b['member_bonusamount_paid'] == 'n/a')) {
        $member_bonusamount_paid_html = '<a href="#" title="无须转帐">n/a</a>';
      }else{
        $member_bonusamount_paid_html = '<a href="bonus_commission_sale.php" title="已经发放了">'.round($b['member_bonusamount_paid']).'</a>';
      }

      // 把付款更新的時間, 改變一下呈現的格式. 避免太長的欄位
      // date("Y-m-d H:i:s",strtotime($b['member_profitamount_paidtime']))
      //var_dump($b['member_bonusamount_paidtime']);
      if($b['member_bonusamount_paidtime'] == NULL OR $b['member_bonusamount_paidtime'] == 'n/a') {
        $member_bonusamount_paidtime_html = 'n/a';
      }else{
        $member_bonusamount_paidtime_html = '<a href="#" title="'.$b['member_bonusamount_paidtime'].'">'.date("m-d H:i",strtotime($b['member_bonusamount_paidtime'])).'</a>';
      }

      // ----------------------------------------------------
      // 表格 row -- tables DATA
      $show_listrow_html = $show_listrow_html.'
      <tr>
        <td>'.$member_id_html.'</td>
        <td>'.$member_account_html.'</td>
        <td>'.$b['member_therole'].'</td>
        <td>'.$b['member_level'].'</td>
        <td>'.$skip_agent_tree_html.'</td>
        <td>'.$all_betsamount_html.'</td>
        <td>'.$b['member_bonuscount_1'].'</td>
        <td>'.$b['member_bonusamount_1'].'</td>
        <td>'.$b['member_bonuscount_2'].'</td>
        <td>'.$b['member_bonusamount_2'].'</td>
        <td>'.$b['member_bonuscount_3'].'</td>
        <td>'.$b['member_bonusamount_3'].'</td>
        <td>'.$b['member_bonuscount_4'].'</td>
        <td>'.$b['member_bonusamount_4'].'</td>
        <td>'.$b['member_bonusamount_count'].'</td>
        <td><span style="color:blue;">'.$b['member_bonusamount'].'</span></td>
      </tr>
      ';
/*
<td>'.$b['perforaccount_1'].'</td>
<td>'.$b['perforaccount_2'].'</td>
<td>'.$b['perforaccount_3'].'</td>
<td>'.$b['perforaccount_4'].'</td>
<td>'.$b['perfor_bounsamount'].'</td>
<td>'.$b['perforbouns_1'].'</td>
<td>'.$b['perforbouns_2'].'</td>
<td>'.$b['perforbouns_3'].'</td>
<td>'.$b['perforbouns_4'].'</td>
<td>'.$b['perforbouns_root'].'</td>
<td>'.$member_bonusamount_paid_html.'</td>
<td>'.$member_bonusamount_paidtime_html.'</td>
<td>'.$b['notes'].'</td>
*/


      // 表格資料 row list end
    }
    // end of for
    // ----------------------------------------------------
  }else{
    // 沒有會員資料, 顯示空白的資訊。
    $show_listrow_html = $show_listrow_html.'
    <tr>
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
    <th>帳號</th>
    <th>會員身份</th>
    <th>所在層數</th>
    <th>被跳過的代理數量</th>
    <th>總投注量</th>
    <th>個人的紅利第1代筆數</th>
    <th>個人的紅利第1代合計</th>
    <th>個人的紅利第2代筆數</th>
    <th>個人的紅利第2代合計</th>
    <th>個人的紅利第3代筆數</th>
    <th>個人的紅利第3代合計</th>
    <th>個人的紅利第4代筆數</th>
    <th>個人的紅利第4代合計</th>
    <th>個人的紅利來源筆數</th>
    <th>個人的紅利合計</th>
  </tr>
  ';

/*
<th>達成第1代</th>
<th>達成第2代</th>
<th>達成第3代</th>
<th>達成第4代</th>
<th>營業獎金分紅('.$sale_bonus_rate_amount.')</th>
<th>第1代營運紅利</th>
<th>第2代營運紅利</th>
<th>第3代營運紅利</th>
<th>第4代營運紅利</th>
<th>公司營運紅利</th>
<th>個人的紅利已發放額度</th>
<th>個人的紅利發放時間</th>
<th>備註</th>

*/

  // -------------------------------------------
  // 計算系統統合性摘要 - summary
  // -------------------------------------------


  // var_dump($b);
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
  // 執行的動作或結果說明，可以透過執行的數量確定運作再那各區塊。
  $run_report_result = "<p> *
  統計顯示的資料 =  $stats_showdata_count ,
  統計此時間區間插入(Insert)的資料 =  $stats_insert_count ,
  統計營業獎金投注量資料更新(Update)   =  $stats_update_count ,
  統計個人營業獎金更新(Update) =  $stats_bonusamount_count
  </p>";
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
  <hr>
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
          "order": [[ 0, "asc" ]],
					"pageLength": 100
      } );
    } )
  </script>
  ';
  // -------------------------------------------------------------------------




  // -------------------------------------------------------------------------
  $show_tips_html = '<div class="alert alert-info">
  <p>* 目前查詢的日期為 '.$current_datepicker_start.' ~ '.$current_datepicker.' 的彩金報表，為美東時間(UTC -04)，每日結算時間範圍為 '.$current_datepicker.' 00:00:00 -04 ~ '.$current_datepicker.' 23:59:59 -04 </p>
  <p>* 對應的中原時間(UTC +08)範圍為：'.date( "Y-m-d", strtotime( "$current_datepicker -1 day")).' 13:00:00+08 ~ '.$current_datepicker.' 12:59:59+08</p>
  <p>'.$show_rule_html.'</p>
  </div>';

  // 營業獎金統計週期 美東時間(日)
  // $rule['stats_bonus_days']   = 7;
  // -------------------------------------------------------------------------
  // 加盟金計算報表 -- 選擇日期 -- FORM
  $date_selector_html = '
  <form class="form-inline" method="get">
    <div class="form-group">
      <div class="input-group">
        <div class="input-group-addon">結算日(美東時間)</div>
        <div class="input-group-addon"><input type="text" class="form-control" name="current_datepicker" id="current_datepicker" placeholder="ex:2017-01-22" value="'.$current_datepicker.'"></div>
        <div class="input-group-addon"><input type="radio" value="0" name="update_bonussale_option" checked>查詢結算日前'.$rule['stats_bonus_days'].'天的營業獎金統計(會自動轉成該計算週期時間範圍)</div>
        <div class="input-group-addon"><input type="radio" value="1" name="update_bonussale_option">(1)營業獎金投注量資料更新</div>
        <div class="input-group-addon"><input type="radio" value="2" name="update_bonussale_option">(2)個人營業獎金更新</div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary" id="update_bonussale_option_query" onclick="blockscreengotoindex();" >執行</button>
    '.$csv_download_url_html.'
  </form>
  <hr>';

  // default date
  $dateyearrange_start 	= date("Y");
  $dateyearrange_end 		= date("Y");
  $dateyearrange = $dateyearrange_start.':'.$dateyearrange_end;
  // ref: http://api.jqueryui.com/datepicker/#entry-examples
  // 限制只能選取某個星期
  $date_selector_js = '
  <script>
    $(document).ready(function() {
      $( "#current_datepicker" ).datepicker({
        yearRange: "'.$dateyearrange_start.':'.$dateyearrange_end.'",
        maxDate: "+0d",
        minDate: "-12w",
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
  // ref: http://htmlarrows.com/math/
  // 顯示資料的參考資訊
  $show_datainfo_html = '<div class="alert alert-success">
  <p>* 查詢的日期為&nbsp;<button type="button" class="btn btn-default btn-xs">'.$current_datepicker_start.'~'.$current_datepicker.'&nbsp;</button>的營業資料(統計週期'.$rule['stats_bonus_days'].'天預設 '.$weekday.' 為計算的結束點)，共有'.$page['all_records'].'筆 </p>
  <p>* 此次營業獎金分紅符合發放資格業績量大於 <button type="button" class="btn btn-default btn-xs">'.money_format('%i', $rule['amountperformance']).' </button>的有'.$amountperformance_userlist_count.'筆</p>
  <p>'.$run_report_result.'</p>
  </div>
  ';


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
    , sum(all_betsamount) as sum_all_betsamount , sum(all_betscount) as sum_all_betscount
    , sum(perfor_bounsamount) as sum_perfor_bounsamount, sum(member_bonusamount) as sum_member_bonusamount
    , sum(perforbouns_root) as sum_perforbouns_root, sum(member_bonusamount_count) as sum_member_bonusamount_count
    FROM root_statisticsbonussale WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."'
    GROUP BY dailydate_end,dailydate_start ORDER BY dailydate_start DESC;    ";
    //print_r($list_sql);
    $list_result = runSQLall($list_sql);
    //var_dump($list_result);

    // 未發出的分紅總計
    $na_bouns_sql = "
    SELECT sum(sum_perforbouns) as sum_perforbouns_all , sum(count_perforbouns) as count_perforbouns_sumall FROM (
    (SELECT  sum(perforbouns_1) as sum_perforbouns, count(perforbouns_1) as count_perforbouns
    FROM root_statisticsbonussale WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND perforaccount_1= 'n/a')
    union
    (SELECT  sum(perforbouns_2) as sum_perforbouns, count(perforbouns_2) as count_perforbouns
    FROM root_statisticsbonussale WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND perforaccount_2= 'n/a')
    union
    (SELECT  sum(perforbouns_3) as sum_perforbouns, count(perforbouns_3) as count_perforbouns
    FROM root_statisticsbonussale WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND perforaccount_3= 'n/a')
    union
    (SELECT  sum(perforbouns_4) as sum_perforbouns, count(perforbouns_4) as count_perforbouns
    FROM root_statisticsbonussale WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND perforaccount_4= 'n/a')
    ) as na_all;";
    $na_bouns_result = runSQLall($na_bouns_sql);



    $list_stats_data = '';
    if($list_result[0] > 0 ) {
      $member_account_count_html = $list_result[1]->member_account_count;
      // 總投注量
      $sum_all_betsamount_html = number_format($list_result[1]->sum_all_betsamount, 2, '.' ,',');
      // 總注單量
      $sum_all_betscount_html = number_format($list_result[1]->sum_all_betscount, 0, '.' ,',');
      // 營業獎金分紅 - sum
      $sum_perfor_bounsamount_html = number_format($list_result[1]->sum_perfor_bounsamount, 2, '.' ,',');
      // 會員個人的分紅合計
      $sum_member_bonusamount_html = number_format($list_result[1]->sum_member_bonusamount, 2, '.' ,',');
      // 會員分紅紅利有多少紅利組成
      $sum_member_bonusamount_count_html = number_format($list_result[1]->sum_member_bonusamount_count, 2, '.' ,',');
      // 系統的紅利總計
      $sum_perforbouns_root_html = number_format($list_result[1]->sum_perforbouns_root, 2, '.' ,',');

      // 未發出的分紅總計
      $na_bouns_html = number_format($na_bouns_result[1]->sum_perforbouns_all, 2, '.' ,',');

      $summary_report_data_html = '
      <tr>
        <td>'.$current_daterange_html.'</td>
        <td>'.$member_account_count_html.'</td>
        <td>'.$sum_all_betsamount_html.'</td>
        <td>'.$sum_all_betscount_html.'</td>
        <td>'.$sum_perfor_bounsamount_html.'</td>

        <td>'.$sum_member_bonusamount_count_html.'</td>
        <td>'.$sum_member_bonusamount_html.'</td>
        <td>'.$sum_perforbouns_root_html.'</td>
        <td>'.$na_bouns_html.'</td>
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
            <th>營業獎金分紅('.$rule['sale_bonus_rate'].'%)</th>

            <th>個人的紅利筆數累計</th>
            <th>個人的紅利總計</th>
            <th>系統的紅利總計</th>
            <th>未發出的分紅總計</th>
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


  // ---------------------------------------------------------------------------
  // 摘要報表
  // ---------------------------------------------------------------------------
  $summary_report_html = summary_report($current_datepicker_start, $current_datepicker);
  // ---------------------------------------------------------------------------
  // 摘要報表 end
  // ---------------------------------------------------------------------------


  // ---------------------------------------------------------------------------
  // 生成左邊的報表 list index
  // ---------------------------------------------------------------------------
  $dailydate_index_stats_switch_html = menu_sale_list_html();
  // ---------------------------------------------------------------------------
  // 生成左邊的報表 list index END
  // ---------------------------------------------------------------------------





  // -------------------------------------------------------------------------
  // 切成 1 欄版面
  // -------------------------------------------------------------------------
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">

    <div class="col-12 col-md-12">
    '.$dailydate_index_stats_switch_html.'
    '.$show_tips_html.'
    </div>

    <div class="col-12 col-md-12">
    '.$show_dateselector_html.'
    </div>

    <div class="col-12 col-md-12">
    '.$show_datainfo_html.'
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
// '.$preview_result.'

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
