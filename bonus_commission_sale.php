<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 營業獎金
// File Name:	bonus_commission_sale.php
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
  ,sum(perfor_bounsamount) as sum_perfor_bounsamount , sum(member_bonusamount) as sum_member_bonusamount , to_char((MAX(updatetime) AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as sum_updatetime
  , to_char((MIN(updatetime) AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as min_tz , to_char((MAX(updatetime)  AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as max_tz
  FROM root_statisticsbonussale GROUP BY dailydate_start,dailydate_end ORDER BY dailydate_start DESC ;";
  $daily_list_range_result = runSQLall($daily_list_range_sql);
  //var_dump($daily_list_range_result);
  $dailydate_stats_data = '';
  for($g=1;$g<=$daily_list_range_result[0];$g++){
    // 日期區間
    $dailydate_list_value_html = '<a href="?current_datepicker='.$daily_list_range_result[$g]->dailydate_start.'" target="_top" title="'.$daily_list_range_result[$g]->min.'~'.$daily_list_range_result[$g]->max.'">'.$daily_list_range_result[$g]->dailydate_start.'~'.$daily_list_range_result[$g]->dailydate_end.'</a>';
    // 資料數量
    $dailydate_count = $daily_list_range_result[$g]->count_dailydate;
    // 營業獎金分紅合計
    $sum_perfor_bounsamount = $daily_list_range_result[$g]->sum_perfor_bounsamount;
    // 會員個人的分紅合計
    $sum_member_bonusamount = $daily_list_range_result[$g]->sum_member_bonusamount;
    // 更新時間
    $updatetime = $daily_list_range_result[$g]->sum_updatetime;
    $min_tz = $daily_list_range_result[$g]->min_tz;
    $max_tz = $daily_list_range_result[$g]->max_tz;
    // 更新資料用時間點
    $dataupdate_time = date("Y-m-d", strtotime($daily_list_range_result[$g]->dailydate_end));
    $date_range = $daily_list_range_result[$g]->dailydate_start.'~'.$daily_list_range_result[$g]->dailydate_end;

    $dailydate_stats_data = $dailydate_stats_data.
    '<tr>
      <td>'.$dailydate_list_value_html.'</td>
      <td>'.$dailydate_count.'</td>
      <td>'.$sum_perfor_bounsamount.'</td>
      <td>'.$sum_member_bonusamount.'</td>
      <td id="update_'.$dataupdate_time.'">
      <a href="#" onclick="bonus_update(\''.$dataupdate_time.'\',\''.$date_range.'\')"  title="資料集內的資料最後更新時間：美東時間(-4)'.$min_tz.'~'.$max_tz.'(點擊可以立即更新)">
      '.$updatetime.' <button class="glyphicon glyphicon-refresh"></button></a></td>
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
        <th>更新時間</th>
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
  <button class="btn btn-success btn-xs" id="show">選單ON</button>
  <button class="btn btn-primary btn-xs" style="display: none" id="hide">選單OFF</button>
  </span>

  <div id="index_menu" style="display:block;
  background-color: #e6e9ed;
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
      $("#index_menu").fadeOut( "fast" );

      $("#hide").click(function(){
          $("#index_menu").fadeOut( "fast" );
          $("#hide").hide();
          $("#show").show();
      });
      $("#show").click(function(){
          $("#index_menu").fadeIn( "fast" );
          $("#hide").show();
          $("#show").hide();
      });
  });
  </script>
  ';


  // 即時計算更新資料用 FUNCTION
  $dailydate_index_stats_switch_html = $dailydate_index_stats_switch_html.'
  <script type="text/javascript" language="javascript" class="init">
  function bonus_update(query_datas,date_range){
    var show_text = \'即将更新 \'+String(date_range)+\' 的獎金記錄...\';
    var updating_img = \'更新中...<img width="20px" height="20px" src=\"ui/loading.gif\" />\';
    var updatingcodeurl = \'bonus_commission_sale_action.php?a=bonus_update&bonus_update_date=\'+query_datas;

    if(confirm(show_text)){
      $("#update_"+query_datas).html(updating_img);
      myWindow = window.open(updatingcodeurl, \'gpk_window\', \'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800\', false);
  		myWindow.focus();
      setTimeout(function(){location.reload();},3000);
    }
  }
  </script>
  ';

  return($dailydate_index_stats_switch_html);
  }

  // -------------------------------------------------------------------------

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

  // 分紅比率 in title , 設定在 system_config.php 檔案內 , 單位 %
  $sale_bonus_rate_amount     = $rule['sale_bonus_rate'];

  // 算 root_member 人數
  $userlist_sql = "SELECT * FROM root_statisticsbonussale
  WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker';";
  // var_dump($userlist_sql);
  $page['all_records'] = runSQL($userlist_sql);

  // 計算目前時間區間內，符合 $rule['amountperformance'] 發放資格的條件的會員人數。
	$amountperformance_userlist_count = amountperformance_userlist_count($current_datepicker_start, $current_datepicker, $rule['amountperformance'] );
  // var_dump($amountperformance_userlist_count);
  // -------------------------------------------

  // 表格欄位名稱
  $table_colname_html = '
  <tr>
    <th>會員ID</th>
    <th>帳號</th>
    <th>會員身份</th>
    <th>所在層數</th>
    <th>被跳過的代理數量</th>
    <th>達成第1代</th>
    <th>達成第2代</th>
    <th>達成第3代</th>
    <th>達成第4代</th>
    <th>總投注量</th>
    <th>營業獎金分紅('.$sale_bonus_rate_amount.')</th>
    <th>第1代營運紅利</th>
    <th>第2代營運紅利</th>
    <th>第3代營運紅利</th>
    <th>第4代營運紅利</th>
    <th>公司營運紅利</th>
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
    <th>個人的紅利已發放額度</th>
    <th>個人的紅利發放時間</th>
    <th>備註</th>
  </tr>
  ';


  // -------------------------------------------
  // 計算系統統合性摘要 - summary
  // -------------------------------------------


  // -------------------------------------------
  // 下載按鈕
  // -------------------------------------------
  $filename       = "bonussale_result_".$current_datepicker_start.'_'.$current_datepicker.'.csv';
  $absfilename    = dirname(__FILE__) ."/tmp_dl/$filename";
  if(file_exists($absfilename)) {
    $csv_download_url_html = '<a href="./tmp_dl/'.$filename.'" class="btn btn-success" >下載CSV</a>';
  }else{
    $csv_download_url_html = '';
  }
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
          "bProcessing": true,
          "bServerSide": true,
          "bRetrieve": true,
          "searching": false,
          "oLanguage": {
            "sSearch": "會員帳號:",
            "sEmptyTable": "目前沒有資料!",
            "sLengthMenu": "每頁顯示 _MENU_ 筆",
            "sZeroRecords": "目前沒有資料",
            "sInfo": "目前在第 _PAGE_ 頁，共 _PAGES_ 頁",
            "sInfoEmpty": "目前沒有資料",
            "sInfoFiltered": "(從 _MAX_ 筆資料中過濾)"
          },
          "ajax": "bonus_commission_sale_action.php?a=reload_salelist&current_datepicker='.$current_datepicker.'",
          "columns": [
            { "data": "id", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'member_treemap.php?id="+oData.id+"\' target=\"_BLANK\" title=\"會員的組織結構狀態\">"+oData.id+"</a>");}},
            { "data": "account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'member_account.php?a="+oData.id+"\' target=\"_BLANK\" title=\"檢查會員的詳細資料\">"+oData.account+"</a>");}},
            { "data": "therole", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'#\' title=\"會員身份 R=管理員 A=代理商 M=會員\">"+oData.therole+"</a>");}},
            { "data": "member_level", "searchable": "false", "orderable": true },
            { "data": "skip_agent_tree_count", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'#\' title=\""+oData.skip_bonusinfo+"\">"+oData.skip_agent_tree_count+"</a>");}},
            { "data": "perforaccount_1", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:blue;\">"+oData.perforaccount_1+"</span>");}},
            { "data": "perforaccount_2", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:red;\">"+oData.perforaccount_2+"</span>");}},
            { "data": "perforaccount_3", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:green;\">"+oData.perforaccount_3+"</span>");}},
            { "data": "perforaccount_4", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:#ff00aa;\">"+oData.perforaccount_4+"</span>");}},
            { "data": "all_betsamount", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'#\' title=\"投注筆數"+oData.all_betscount+"\">"+oData.all_betsamount+"</a>");}},
            { "data": "perfor_bounsamount", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:#ff00aa;\">"+oData.perfor_bounsamount+"</span>");}},
            { "data": "perforbouns_1", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:blue;\">"+oData.perforbouns_1+"</span>");}},
            { "data": "perforbouns_2", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:red;\">"+oData.perforbouns_2+"</span>");}},
            { "data": "perforbouns_3", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:green;\">"+oData.perforbouns_3+"</span>");}},
            { "data": "perforbouns_4", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:#ff00aa;\">"+oData.perforbouns_4+"</span>");}},
            { "data": "perforbouns_root", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:#ff00aa;\">"+oData.perforbouns_root+"</span>");}},
            { "data": "member_bonuscount_1", "searchable": "false", "orderable": true },
            { "data": "member_bonusamount_1", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:blue;\">"+oData.member_bonusamount_1+"</span>");}},
            { "data": "member_bonuscount_2", "searchable": "false", "orderable": true },
            { "data": "member_bonusamount_2", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:red;\">"+oData.member_bonusamount_2+"</span>");}},
            { "data": "member_bonuscount_3", "searchable": "false", "orderable": true },
            { "data": "member_bonusamount_3", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:green;\">"+oData.member_bonusamount_3+"</span>");}},
            { "data": "member_bonuscount_4", "searchable": "false", "orderable": true },
            { "data": "member_bonusamount_4", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:#ff00aa;\">"+oData.member_bonusamount_4+"</span>");}},
            { "data": "member_bonusamount_count", "searchable": "false", "orderable": true },
            { "data": "member_bonusamount", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:#ff00aa;\">"+oData.member_bonusamount+"</span>");}},
            { "data": "member_bonusamount_paid", "searchable": "false", "orderable": true },
            { "data": "member_bonusamount_paidtime", "searchable": "false", "orderable": true },
            { "data": "note", "searchable": "false", "orderable": true }
            ]
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
        <div class="input-group-addon">查詢結算日前'.$rule['stats_bonus_days'].'天的營業獎金統計(會自動轉成該計算週期時間範圍)</div>
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

    $list_sql = "
    SELECT * FROM root_statisticsbonussale
    WHERE dailydate_start = '".$current_datepicker_start."' AND dailydate_end = '".$current_datepicker."' AND member_bonusamount > '0' AND member_bonusamount_paid IS NULL;";
    //print_r($list_sql);
    $payoutmember_count = runSQL($list_sql);

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

      if($payoutmember_count > 0){
        $batchreleasebutton_html = '<button id="batchpayout_html_btn" class="btn btn-info" onclick="batchpayout_html();">批次發送</button>';
        $batchpayout_html = '
          <div style="display: none;width: 800px;" id="batchpayout">
            <table class="table table-bordered">
              <thead>
                <tr bgcolor="#e6e9ed">
                  <th>日期</th>
                  <th>發送筆數</th>
                  <th>預計發送的紅利總計</th>
                  <th>獎金類別</th>
                  <th>發送方式</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>'.$current_daterange_html.'</td>
                  <td>'.$payoutmember_count.'</td>
                  <td>'.$sum_member_bonusamount_html.'</td>
                  <td><select class="form-control" name="bonus_type" id="bonus_type"  onchange="auditsetting();"><option value="">--</option><option value="token">現金</option><option value="cash">加盟金</option></select></td>
                  <td><select class="form-control" name="bonus_defstatus" id="bonus_defstatus" ><option value="0">取消</option><option value="1">可領取</option><option value="2" selected>暫停</option></select></td>
                </tr>
                <tr>
                  <th bgcolor="#e6e9ed"><center>稽核方式</center></th>
                  <td><select class="form-control" name="audit_type" id="audit_type" disabled><option value="none" selected="">--</option><option value="freeaudit">免稽核</option><option value="depositaudit">存款稽核</option><option value="shippingaudit">優惠存款稽核</option></select></td>
                  <th bgcolor="#e6e9ed"><center>稽核金額</center></th>
                  <td><input class="form-control" name="audit_amount" id="audit_amount" value="0" placeholder="稽核金額，EX：100" disabled></td>
                  <td><button id="payout_btn" class="btn btn-info" onclick="batchpayout();" disabled>發送</button>
                      <button class="btn btn-warning" onclick="batchpayoutpage_close();">取消</button></td>
                </tr>
              </tbody>
            </table>
          </div>';
      }else{
        $batchreleasebutton_html = '<button id="batchpayout_html_btn" class="btn btn-info" disabled>批次發送</button>';
        $batchpayout_html = '';
      }

      $summary_payout_js = '
      <script type="text/javascript" language="javascript" class="init">
      function auditsetting(){
        var bonustype = $("#bonus_type").val();
        console.log(bonustype);

        if(bonustype == ""){
          $("#payout_btn").prop(\'disabled\', true);
        }else{
          if(bonustype == \'token\'){
            $("#audit_type").prop(\'disabled\', false);
            $("#audit_amount").prop(\'disabled\', false);
          }else{
            $("#audit_type").prop(\'disabled\', true);
            $("#audit_amount").prop(\'disabled\', true);
          }
          $("#payout_btn").prop(\'disabled\', false);
        }
      }
      function batchpayout_html(){
        $.blockUI(
        {
          message: $(\'#batchpayout\'),
          css: {
           padding: 0,
           margin: 0,
           width: \'800px\',
           top: \'30%\',
           left: \'25%\',
           border: \'none\',
           cursor: \'auto\'
          }
        });
      }
      function batchpayoutpage_close(){
        $.unblockUI();
      }
      function batchpayout(){
        $("#payout_btn").prop(\'disabled\', true);

        var show_text = \'即将發放 '.$current_daterange_html.' 的紅利...\';
        var payout_status = $("#bonus_defstatus").val();
        var bonus_type = $("#bonus_type").val();
        var audit_type = $("#audit_type").val();
        var audit_amount = $("#audit_amount").val();
        var payoutupdatingcodeurl=\'bonus_commission_sale_action.php?a=salebonus_payout_update&salebonus_payout_date='.$current_datepicker_start.'&s=\'+payout_status+\'&s1=\'+bonus_type+\'&s2=\'+audit_type+\'&s3=\'+audit_amount;

        if(bonus_type == \'token\' && audit_type == \'none\'){
          alert(\'請選擇獎金的稽核方式！\');
        }else{
          if(confirm(show_text)){
            $.unblockUI();
            $("#batchpayout_html_btn").prop(\'disabled\', true);
            myWindow = window.open(payoutupdatingcodeurl, \'gpk_window\', \'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800\', false);
        		myWindow.focus();
          }else{
            $("#payout_btn").prop(\'disabled\', false);
          }
        }
      }
      </script>';

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
        <td>'.$batchreleasebutton_html.'</td>
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
            <th></th>
          </tr>
        </thead>
        <tbody style="background-color:rgba(255,255,255,0.4);">
          '.$summary_report_data_html.'
        </tbody>
      </table>
      </hr>
      '.$batchpayout_html.$summary_payout_js;

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
