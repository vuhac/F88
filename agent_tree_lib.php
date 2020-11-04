<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 統一使用來計算代理商數狀結構的函式庫
// File Name:	agent_tree_lib.php
// Author:    Barkley
// Related:
// DB table:  member
// DB table:
// Log:
//
// ----------------------------------------------------------------------------
// 程式開發的邏輯：
// -------------------------------------------------------------------------
// 透過一個指定的時間區間, 依據會員資料 root_member 搜尋日結報表的資料
// 依據 root_member 的上下階層關係, 列出每個會員的每一個上一代, 一直到 root
// 每一個會員的投注資訊, 透過日結報表統計指定的時間區間取得完整資訊，以利列出符合條件的會員(代理商)
// 依據每個會員日結報表資料(日結報表資料需要完整, 會員不存在的處理), 統計出來加盟金及總投注額
// -------------------------------------------------------------------------



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
$function_title 		= '計算代理商樹狀結構的函式庫';
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';


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
	$key = 'find_member_node'.$member_id.$current_datepicker_start.$current_datepicker;
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

    // 最大層數 100 代
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





  // ---------------------------------------------------------------------------
  // 檢查系統資料庫中 table root_statisticsbonusagent 表格(放射線組織獎金計算-加盟傭金計算)有多少資料被生成了, 建立索引檔及提供可以更新的資訊
  // 搭配 indexmenu_stats_switch 使用
  // Usage: menu_agent_list_html()
  // ---------------------------------------------------------------------------
  function menu_agent_list_html() {

  // 列出系統資料統計月份
  $list_sql = '
  SELECT dailydate_start, dailydate_end, MIN(updatetime) as min , MAX(updatetime) as max,count(member_account) as member_account_count
  , sum(agency_commission) as sum_agency_commission, count(agency_commission) as count_agency_commission, sum(member_bonusamount) as sum_member_bonusamount
  FROM root_statisticsbonusagent
  GROUP BY dailydate_end,dailydate_start ORDER BY dailydate_start DESC;
  ';

  $list_result = runSQLall($list_sql);
  // var_dump($list_result);

  $list_stats_data = '';
  if($list_result[0] > 0){

    // 把資料 dump 出來 to table
    for($i=1;$i<=$list_result[0];$i++) {

      // 統計區間
      $date_range_html = '<a href="?current_datepicker='.$list_result[$i]->dailydate_start.'&update_bonusagent_radio=0" title="觀看指定時間區間的內容" target="_top">'.$list_result[$i]->dailydate_start.'~'.$list_result[$i]->dailydate_end.'</a>';
      // 資料數量
      $member_account_count_html = '<a href="#" title="統計資料更新的時間區間'.$list_result[$i]->min.'~'.$list_result[$i]->max.'">'.$list_result[$i]->member_account_count.'</a>';
      // 總傭金量
      $sum_agency_commission_html = number_format($list_result[$i]->sum_agency_commission, 0, '.' ,',');
      // 總傭金筆數
      $count_agency_commission_html = number_format($list_result[$i]->count_agency_commission, 0, '.' ,',');
      // 分傭合計, 如果為 0 表示還沒做第二階計算
      $sum_member_bonusamount_html = number_format($list_result[$i]->sum_member_bonusamount, 0, '.' ,',');

      // table
      $list_stats_data = $list_stats_data.'
      <tr>
        <td>'.$date_range_html.'</td>
        <td>'.$member_account_count_html.'</td>
        <td>'.$sum_agency_commission_html.'</td>
        <td>'.$count_agency_commission_html.'</td>
        <td>'.$sum_member_bonusamount_html.'</td>
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
          <th>總傭金量</th>
          <th>傭金筆數</th>
          <th>個人傭金合計</th>
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
    $indexmenu_list_html = menu_agent_list_html();

    // 加上 on / off開關
    $indexmenu_stats_switch_html = '
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
    height: 95%;
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
    //    var_dump($tree);

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
    var_dump($tree);
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
  //var_dump($_GET);
  // 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
  if(isset($_GET['current_datepicker'])) {
    // 判斷格式資料是否正確, 不正確以今天的美東時間為主
    $current_datepicker = validateDate($_GET['current_datepicker'], 'Y-m-d');
    //var_dump($current_datepicker);
    if($current_datepicker) {
      $current_datepicker = $_GET['current_datepicker'];
    }else{
      // 轉換為美東的時間 date
      $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
    }
  }else{
    // 轉換為美東的時間 date
    $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
  }
  //var_dump($current_datepicker);
  //echo date('Y-m-d H:i:sP');
  // 統計的期間時間 $rule['stats_commission_days'] 參考次變數
  $stats_commission_days = $rule['stats_commission_days'] - 1;
  $current_datepicker_start = date( "Y-m-d", strtotime( "$current_datepicker -$stats_commission_days day"));

  // 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
  if(isset($_GET['current_per_size']) AND $_GET['current_per_size'] != NULL ) {
    $current_per_size = $_GET['current_per_size'];
  }else{
    //$current_per_size = 1000000;
    $current_per_size = 20;
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
  // var_dump($_GET);
  $action_status_number = 0;
  // 變數：每日統計日報更新成為獎金分紅計算 raw data
  if(isset($_GET['update_bonusagent_radio']) AND $_GET['update_bonusagent_radio'] == '1') {
    $action_status_number = 1;
    $update_bonusagent_status = true;
  }else{
    $update_bonusagent_status = false;
  }
  // 變數：如果要產生分紅統計資料的話, 設定為 true,產生分紅筆數, 及分紅合計
  if(isset($_GET['update_bonusagent_radio']) AND $_GET['update_bonusagent_radio'] == '2') {
    $action_status_number = 2;
    $update_bonusagent_amount_status = true;
  }else{
    $update_bonusagent_amount_status = false;
  }


  // -------------------------------------
  // 列出所有的會員資料及人數 SQL
  // -------------------------------------
  // 算 root_member 人數
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
  var_dump($userlist_sql);
  $userlist       = runSQLall($userlist_sql);
  $userlist_count = $userlist[0];


  // 存放列表的 html -- 表格 row -- tables DATA
  $show_listrow_html = '';
  // 判斷 root_member count 數量大於 1
  if($userlist[0] >= 1) {
    // 會員有資料，且存在數量為 $userlist_count

    // 檢查當所有的時間區間，資料數量和 root_member 數量一樣時，再來執行。(確定資料有生成，才執行後面的分紅統計資料計算) $count_lb_result == $userlist_count
    $count_lb_sql   = "SELECT *  FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker';";
    //var_dump($count_lb_sql);
    $count_lb_result= runSQL($count_lb_sql);
    //var_dump($count_lb_result);

    // todo: 檢查日報統計資料, 看看是否時間範圍內的資料數量都一樣. 不一樣的話中止 insert 行為

    // 以會員為主要 key 依序列出每個會員的貢獻金額
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
        INSERT INTO "root_statisticsbonusagent" ("member_account", "member_parent_id", "member_therole", "updatetime", "dailydate_start", "dailydate_end",
        "member_level", "level_account_1", "level_account_2", "level_account_3", "level_account_4", "agency_commission", "level_bonus_1", "level_bonus_2", "level_bonus_3", "level_bonus_4", "company_bonus"
        )'.
        "VALUES ('".$b['member_account']."', '".$b['member_parent_id']."', '".$b['member_therole']."', now()
        , '".$b['dailydate_start']."', '".$b['dailydate_end']."', '".$b['member_level']."', '".$b['level_account_1']."', '".$b['level_account_2']."', '".$b['level_account_3']."', '".$b['level_account_4']."'
        , '".$b['agency_commission']."', '".$b['level_bonus_1']."', '".$b['level_bonus_2']."', '".$b['level_bonus_3']."', '".$b['level_bonus_4']."', '".$b['company_bonus']."'
        );
        ";
        //echo $insert_sql;
        $insertdata_bonusagent_result = runSQL($insert_sql);
        if($insertdata_bonusagent_result == 1) {
          // 成功
          //echo $userlist[$i]->id.'ok,';
        }else{
          //失敗
          $logger = $userlist[$i]->id.' error';
          die($logger);
        }

      }else{
        // DB root_statisticsbonusagent 有這個 member 資料, 檢查是否需要更新，沒有的話把DB 資料取出.

        // 變數：每日統計日報更新成為獎金分紅計算 raw data update
        //$update_bonusagent_status = true;
        // $update_bonusagent_status = false;
        if($update_bonusagent_status) {
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
            $update_bonusagent_result = runSQL($update_sql);
            if($update_bonusagent_result == 1) {
              // 成功
              //echo $logger;
            }else{
              // 失敗
              die($logger);
            }
            // 更新資料 if end
          }else{
            $logger = 'DB root_statisticsbonusagent的'.$b['member_id'].'取得目前每日報表中的資料生成錯誤';
            die($logger);
          }
          // end update $update_bonusagent_status
          // -------------------------------------------
        }else{
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
          if($update_bonusagent_amount_status){
            // 如果資料庫中的資料一樣的話，才可以執行更新。
            //var_dump($count_lb_result);
            //var_dump($userlist_count);
            if($count_lb_result == $userlist_count) {
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

                // print_r($update_bonus_sql);
                //var_dump($update_bonus_sql);
                $update_bonus_result = runSQL($update_bonus_sql);
                if($update_bonus_result == 1) {
                  //var_dump($update_bonus_sql);
                  // 成功更新
                }else{
                  $logger = '更新 DB table 的 member_bonuscount 及 member_bonusamount 欄位失敗,請聯絡開發人員處理。';
                  die($logger);
                }

              }else{
                $b['member_bonusamount'] = 'n/a';
                $b['member_bonuscount']  = 'n/a';
                $logger = '計算 member_bonuscount 及 member_bonusamount 欄位失敗,請聯絡開發人員處理。';
                die($logger);
              }
            }else{
              // 資料數量不一樣，放棄更新。
              $logger = '時間區間內的，每日報表資料數量不一樣，放棄更新。';
            }
          }else{
            // 產生分紅統計資料不允許
          }
          // end $update_bonusagent_amount_status
        }
        // end show data if else
      }
      // end update or insert data



      // ----------------------------------------------------
      // 表格顯示, 如有不同的顯示方式再這裡調整
      // ----------------------------------------------------
      // 會員的 ID
      $member_id_html = '<a href="member_treemap.php?id='.$b['member_id'].'" target="_BLANK" title="列出會員的組織圖">'.$b['member_id'].'</a>';
      // 會員的 account
      $member_account_html = '<a href="member_account.php?a='.$b['member_id'].'" target="_BLANK" title="檢查會員的詳細資料">'.$b['member_account'].'</a>';
      // ----------------------------------------------------

      // 發放 member_bonusamount_paid
      $paid_notes = '发放'.$b['member_account'].'帐号'.$b['dailydate_start'].'的加盟佣金';
      if($b['member_bonusamount'] > 0 AND $b['member_bonusamount_paid'] == NULL) {
        // 轉帳
        $member_bonusamount_paid_html = '<a href="member_depositgcash.php?a='.$b['member_id'].'&gcash='.round($b['member_bonusamount']).'&notes='.$paid_notes.'" title="立即进行转帐给'.$b['member_account'].'金额'.round($b['member_bonusamount']).'" target="_blank"><span class="glyphicon glyphicon-plus-sign" aria-hidden="true"></a>';
        // 寫入付款欄位資訊
        $member_bonusamount_paid_html = $member_bonusamount_paid_html.'&nbsp;&nbsp;<a href="bonus_commission_agent_action.php?a=member_bonusamount_paid&id='.$b['id'].'"  onclick="return confirm(\'请确认已经汇款完成了,再来更新此栏位 ??\')" title="金额写入这个栏位" target="_blank"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></a>';
      }elseif($b['member_bonusamount'] == 0 AND $b['member_bonusamount_paid'] == NULL) {
        $member_bonusamount_paid_html = '<a href="#" title="无须转帐">n/a</a>';
      }else{
        $member_bonusamount_paid_html = '<a href="bonus_commission_agent_action.php" title="已经发放了">'.round($b['member_bonusamount_paid']).'</a>';
      }

      // 把付款更新的時間, 改變一下呈現的格式. 避免太長的欄位
      // date("Y-m-d H:i:s",strtotime($b['member_profitamount_paidtime']))
      if($b['member_bonusamount_paidtime'] == NULL) {
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
        <td>'.$b['level_account_1'].'</td>
        <td>'.$b['level_account_2'].'</td>
        <td>'.$b['level_account_3'].'</td>
        <td>'.$b['level_account_4'].'</td>
        <td>'.$b['agency_commission'].'</td>
        <td>'.$b['level_bonus_1'].'</td>
        <td>'.$b['level_bonus_2'].'</td>
        <td>'.$b['level_bonus_3'].'</td>
        <td>'.$b['level_bonus_4'].'</td>
        <td>'.$b['company_bonus'].'</td>
        <td>'.$b['member_bonuscount_1'].'</td>
        <td>'.$b['member_bonus_1'].'</td>
        <td>'.$b['member_bonuscount_2'].'</td>
        <td>'.$b['member_bonus_2'].'</td>
        <td>'.$b['member_bonuscount_3'].'</td>
        <td>'.$b['member_bonus_3'].'</td>
        <td>'.$b['member_bonuscount_4'].'</td>
        <td>'.$b['member_bonus_4'].'</td>
        <td>'.$b['member_bonuscount'].'</td>
        <td>'.$b['member_bonusamount'].'</td>
        <td>'.$member_bonusamount_paid_html.'</td>
        <td>'.$member_bonusamount_paidtime_html.'</td>
        <td>'.$b['notes'].'</td>
      </tr>
      ';
      // ----------------------------------------------------
      // 收集本次 member 資料 , 到 csv 準備輸出
      // ----------------------------------------------------
      $csv_data['data'][$i] = $b;
    }
    // ------------------------------------------------------
    // 表格資料 row list , end for loop
    // ------------------------------------------------------
  }else{
    // NO member
    $show_listrow_html = $show_listrow_html.'
    <tr>
      <th></th><th></th><th></th>
      <th></th><th></th><th></th><th></th>
      <th></th><th></th><th></th><th></th>
      <th></th><th></th><th></th><th></th>
      <th></th><th></th><th></th><th></th>
    </tr>
    ';
  }
  // end member sql



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

  $table_summary_html = '
  <table class="table table-bordered small">
    <thead>
      <tr class="active">
        <th>時間範圍</th>
        <th>時間範圍的會員人數</th>
        <th>加盟金的總額</th>
        <th>加盟金的筆數</th>
        <th>個人分紅的最多</th>
        <th>個人分紅的最少</th>
        <th>分紅的有分配到的個人人數</th>
        <th>分紅的有分配到的個人餘額合計</th>
        <th>分紅的有分配到的個人餘額筆數合計</th>
        <th>分紅的公司收入合計</th>
        <th>沒有分配到的總和餘額</th>
        <th>沒有分配到的總和筆數</th>
      </tr>
    </thead>
    <tbody style="background-color:rgba(255,255,255,0.4);">
      <tr>
      <td>'.$csv_data['summary']['時間範圍'].'</td>
      <td>'.$csv_data['summary']['時間範圍的會員人數'].'</td>
      <td>'.$csv_data['summary']['加盟金的總額'].'</td>
      <td>'.$csv_data['summary']['加盟金的筆數'].'</td>
        <td>'.$csv_data['summary']['個人分紅的最多'].'</td>
        <td>'.$csv_data['summary']['個人分紅的最少'].'</td>
        <td>'.$csv_data['summary']['分紅的有分配到的個人人數'].'</td>
        <td>'.$csv_data['summary']['分紅的有分配到的個人餘額合計'].'</td>
        <td>'.$csv_data['summary']['分紅的有分配到的個人餘額筆數合計'].'</td>
        <td>'.$csv_data['summary']['分紅的公司收入合計'].'</td>
        <td>'.$csv_data['summary']['沒有分配到的總和餘額'].'</td>
        <td>'.$csv_data['summary']['沒有分配到的總和筆數'].'</td>
      </tr>
    </tbody>
  </table>
  <hr>
  ';

  // var_dump($csv_data['summary']);
  // -------------------------------------------
  // 計算系統統合性摘要 - summary END
  // -------------------------------------------


  // ---------------------------------- END table data get
  // 表格欄位名稱
  $table_colname_html = '
  <tr>
    <th>會員ID</th>
    <th>帳號</th>
    <th>會員身份</th>
    <th>所在層數</th>
    <th>上層第1代</th>
    <th>上層第2代</th>
    <th>上層第3代</th>
    <th>上層第4代</th>
    <th>組織代理加盟金</th>
    <th>上層第1代分傭</th>
    <th>上層第2代分傭</th>
    <th>上層第3代分傭</th>
    <th>上層第4代分傭</th>
    <th>公司分傭收入</th>
    <th>個人第1代分傭筆數</th>
    <th>個人第1代分傭累計</th>
    <th>個人第2代分傭筆數</th>
    <th>個人第2代分傭累計</th>
    <th>個人第3代分傭筆數</th>
    <th>個人第3代分傭累計</th>
    <th>個人第4代分傭筆數</th>
    <th>個人第4代分傭累計</th>
    <th>個人分傭筆數</th>
    <th>個人分傭合計</th>
    <th>個人已發放金額</th>
    <th>分紅發放時間</th>
    <th>備註</th>
  </tr>
  ';



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
  $filename      = "bonusagent_result_".$current_datepicker_start.'_'.$current_datepicker.'.csv';
  $absfilename   = dirname(__FILE__) ."/tmp_dl/$filename";
  $filehandle    = fopen("$absfilename","w");
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
  // -------------------------------------------
  // 將內容輸出到 檔案 , csv format  END
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
          "order": [[ 0, "asc" ]],
					"pageLength": 100
      } );
    } )
  </script>
  ';

  // -------------------------------------------------------------------------


  // -------------------------------------------------------------------------
  $show_tips_html = '<div class="alert alert-default">
  <p>* 目前查詢的日期為 '.$current_datepicker_start.' ~ '.$current_datepicker.' 的彩金報表，為美東時間(UTC -04)，每日結算時間範圍為 '.$current_datepicker.' 00:00:00 -04 ~ '.$current_datepicker.' 23:59:59 -04 </p>
  <p>* 對應的中原時間(UTC +08)範圍為：'.date( "Y-m-d", strtotime( "$current_datepicker -1 day")).' 13:00:00+08 ~ '.$current_datepicker.' 12:59:59+08</p>
  <p>* 如果需要更新資料，需要先更新<a href="statistics_daily_report.php" target="_BLANK">每日營收日結報表</a>，再依序執行(1)結算日資料更新及(2)個人分紅傭金更新，才可以得到最新的資料。</p>
  <p>'.$show_rule_html.'</p>
  </div>';


  // -------------------------------------------------------------------------
  // 加盟金計算報表 -- 選擇日期 -- FORM
  $date_selector_html = '
  <form class="form-inline" method="get">
    <div class="form-group">
      <div class="input-group">
        <div class="input-group-addon">傭金結算日(美東時間)</div>
        <div class="input-group-addon"><input type="text" class="form-control" name="current_datepicker" id="current_datepicker" placeholder="ex:2017-01-22" value="'.$current_datepicker.'"></div>
        <div class="input-group-addon"><input type="radio" value="0" name="update_bonusagent_radio" checked>查詢指定日其的傭金統計</div>
        <div class="input-group-addon"><input type="radio" value="1" name="update_bonusagent_radio">(1)結算日資料更新</div>
        <div class="input-group-addon"><input type="radio" value="2" name="update_bonusagent_radio">(2)個人分紅傭金更新</div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary" id="daily_statistics_report_date_query" onclick="blockscreengotoindex();" >執行</button>
    '.$csv_download_url_html.'
  </form>
  <hr>';

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
    $action_status = $action_status.'<span class="label label-primary">(0)查詢結算日</span>';
    $action_status = $action_status.'<span class="label label-default">(1)結算日資料更新</span>';
    $action_status = $action_status.'<span class="label label-default">(2)個人分紅傭金更新</span>';
  }elseif($action_status_number == 1) {
    $action_status = $action_status.'<span class="label label-default">(0)查詢結算日</span>';
    $action_status = $action_status.'<span class="label label-primary">(1)結算日資料更新</span>';
    $action_status = $action_status.'<span class="label label-default">(2)個人分紅傭金更新</span>';
  }elseif($action_status_number == 2) {
    $action_status = $action_status.'<span class="label label-default">(0)查詢結算日</span>';
    $action_status = $action_status.'<span class="label label-default">(1)結算日資料更新</span>';
    $action_status = $action_status.'<span class="label label-primary">(2)個人分紅傭金更新</span>';
  }

  // -------------------------------------------------------------------------
  // 顯示資料的參考資訊 , 系統回應的訊息
  $show_datainfo_html = '<div class="alert alert-success" id="action_messages">
  <p>* 目前動作狀態：'.$action_status.'</p>
  </div>
  ';
  $show_data_info_html = $show_datainfo_html;
  // -------------------------------------------------------------------------




  // ---------------------------------------------------------------------------
  // 生成左邊的報表 list index
  // ---------------------------------------------------------------------------
  $indexmenu_stats_switch_html = indexmenu_stats_switch();
  // ---------------------------------------------------------------------------
  // 生成左邊的報表 list index END
  // ---------------------------------------------------------------------------



// -------------------------------------------------------------------------
// 切成 1 欄版面
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

  <div class="col-12 col-md-12">
  '.$show_data_info_html.'
  </div>

  <div class="col-12 col-md-12">
  '.$table_summary_html.'
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
$tmpl['page_title']								= '<h2><strong>'.$function_title.'</strong></h2><hr>';
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
