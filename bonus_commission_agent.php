<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 直銷組織加盟金
// File Name:	bonus_commission_agent.php
// Author:    Barkley
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
// 功能標題，放在標題列及meta  $tr['Radiation tissue bonus calculation'] = '放射線組織獎金計算';  $tr['Agent Franchise Fee'] = '代理加盟金';
$function_title     = $tr['Radiation tissue bonus calculation'].'-'.$tr['Agent Franchise Fee'];
// 擴充 head 內的 css or js
$extend_head        = '';
// 放在結尾的 js
$extend_js          = '';
// body 內的主要內容
$indexbody_content  = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置 $tr['profit and promotion'] = '營收與行銷';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['profit and promotion'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

  // ---------------------------------------------------------------
  // MAIN start
  // ---------------------------------------------------------------

  // -------------------------------------------------------------------------
  // 尋找符合業績達成的上層, 共 n 代. 直到最上層 root 會員。
  // 再以計算出來的代數 account 判斷，哪些代數符合達成業績標準的會員。
  // -------------------------------------------------------------------------

  // ---------------------------------------------------------------------------
  // 檢查系統資料庫中 table root_statisticsbonusagent 表格(放射線組織獎金計算-加盟傭金計算)有多少資料被生成了, 建立索引檔及提供可以更新的資訊
  // 搭配 indexmenu_stats_switch 使用
  // Usage: menu_agent_list_html()
  // ---------------------------------------------------------------------------
  function menu_agent_list_html() {
  global $tr;
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

      // 統計區間 $tr['Watch the contents of the specified time range'] = '觀看指定時間區間的內容';
      $date_range_html = '<a href="?current_datepicker='.$list_result[$i]->dailydate_start.'" title="'.$tr['Watch the contents of the specified time range'].'" target="_top">'.$list_result[$i]->dailydate_start.' ~ '.$list_result[$i]->dailydate_end.'</a>';
      // 資料數量 $tr['Statistical data update time interval'] = '統計資料更新的時間區間';;
      $member_account_count_html = '<a href="#" title="'.$tr['Statistical data update time interval'].''.$list_result[$i]->min.'~'.$list_result[$i]->max.'">'.$list_result[$i]->member_account_count.'</a>';
      // 總傭金量
      $sum_agency_commission_html = number_format($list_result[$i]->sum_agency_commission, 0, '.' ,',');
      // 總傭金筆數
      $count_agency_commission_html = number_format($list_result[$i]->count_agency_commission, 0, '.' ,',');
      // 分傭合計, 如果為 0 表示還沒做第二階計算
      $sum_member_bonusamount_html = number_format($list_result[$i]->sum_member_bonusamount, 0, '.' ,',');

      // table $tr['Data update'] = '資料更新';
      $list_stats_data = $list_stats_data.'
      <tr>
        <td>'.$date_range_html.'</td>
        <td>'.$member_account_count_html.'</td>
        <td>'.$sum_agency_commission_html.'</td>
        <td>'.$count_agency_commission_html.'</td>
        <td>'.$sum_member_bonusamount_html.'</td>
        <td><a href="#" onclick="bonus_update(\''.$list_result[$i]->dailydate_start.'\',\''.$list_result[$i]->dailydate_end.'\')" title="'.$tr['Data update'].'"><button class="glyphicon glyphicon-refresh"></button></a></td>
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
      <td></td>
    </tr>
    ';
  }

  // 統計資料及索引 $tr['Data update'] = '資料更新';  $tr['Statistical interval'] = '統計區間';  $tr['Number of data'] = '資料數量';  $tr['Total commissions'] = '總傭金量';  $tr['commission Count'] = '傭金筆數'; $tr['Total personal commissions'] = '個人傭金合計';
  $listdata_html = '
    <table class="table table-bordered small">
      <thead>
        <tr class="active">
          <th>'.$tr['Statistical interval'].'<span class="glyphicon glyphicon-time"></span>(-04)</th>
          <th>'.$tr['Number of data'].'</th>
          <th>'.$tr['Total commissions'].'</th>
          <th>'.$tr['commission Count'].'</th>
          <th>'.$tr['Total personal commissions'].'</th>
          <th>'.$tr['Data update'].'</th>
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
    global $tr;
    // 選單表單
    $indexmenu_list_html = menu_agent_list_html();

    // 加上 on / off開關 $tr['Menu'] = '選單'; $tr['Upcoming updates'] = '即將更新';  $tr['Bonus record'] = '的獎金記錄';  $tr['updating'] = '更新中';
    $indexmenu_stats_switch_html = '
    <span style="
    position: fixed;
    top: 5px;
    left: 5px;
    width: 400px;
    height: 20px;
    z-index: 1000;
    ">
    <button class="btn btn-primary btn-xs" style="display: none" id="hide">'.$tr['Menu'].'OFF</button>
    <button class="btn btn-success btn-xs" id="show">'.$tr['Menu'].'ON</button>
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
    function bonus_update(query_datas,date_range){
      var show_text = \''.$tr['Upcoming updates'].' \'+String(date_range)+\' '.$tr['Bonus record'].'...\';
      var updating_img = \''.$tr['updating'].'...<img width="20px" height="20px" src=\"ui/loading.gif\" />\';
      var updatingcodeurl = \'bonus_commission_agent_action.php?a=bonus_update&current_datepicker=\'+query_datas;

      if(confirm(show_text)){
        $("#update_"+query_datas).html(updating_img);
        myWindow = window.open(updatingcodeurl, \'gpk_window\', \'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800\', false);
    		myWindow.focus();
        setTimeout(function(){location.reload();},3000);
      }
    }
    </script>
    ';


    return($indexmenu_stats_switch_html);
  }
  // ---------------------------------------------------------------------------
  // 加上 on / off開關 JS and CSS   END
  // ---------------------------------------------------------------------------

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

  // 表格欄位名稱 $tr['Identity Member'] = '會員';    $tr['Account'] = '帳號'; $tr['Member Identity'] = '會員身份'; $tr['number of layers'] = '所在層數'; $tr['Upper 1st generation'] = '上層第1代'; $tr['Upper 2nd generation'] = '上層第2代';$tr['upper third generation'] = '上層第3代';$tr['upper 4th generation'] = '上層第4代';$tr['organization'] = '組織';$tr['Agent Franchise Fee'] = '代理加盟金';$tr['The first generation take commission'] = '上層第1代分傭';$tr['The second generation take commission'] = '上層第2代分傭';$tr['The third generation take commission'] = '上層第3代分傭';$tr['The fourth generation take commission'] = '上層第4代分傭';$tr['Company commission income'] = '公司分傭收入';$tr['Personal first generation commission amount'] = '個人第1代分傭筆數';$tr['Personal first generation commission accumulated'] = '個人第1代分傭累計';$tr['Personal second generation commission amount'] = '個人第2代分傭筆數';$tr['Personal second generation commission accumulated'] = '個人第2代分傭累計';$tr['Personal third generation commission amount'] = '個人第3代分傭筆數';$tr['Personal third generation commission accumulated'] = '個人第3代分傭累計';$tr['Personal 4th generation commission amount'] = '個人第4代分傭筆數';$tr['Personal 4th generation commission accumulated'] = '個人第4代分傭累計';$tr['Personal sub-commission amount'] = '個人分傭筆數';$tr['Personal sub-commission accumulated'] = '個人分傭合計';$tr['Individual has paid the amount'] = '個人已發放金額';$tr['Dividend distribution time'] = '分紅發放時間';
  $table_colname_html = '
  <tr>
    <th>'.$tr['Identity Member'].'ID</th>
    <th>'.$tr['Account'].'</th>
    <th>'.$tr['Member Identity'].'</th>
    <th>'.$tr['number of layers'].'</th>
    <th>'.$tr['Upper 1st generation'].'</th>
    <th>'.$tr['Upper 2nd generation'].'</th>
    <th>'.$tr['upper third generation'].'</th>
    <th>'.$tr['upper 4th generation'].'</th>
    <th>'.$tr['organization'].$tr['Agent Franchise Fee'].'</th>
    <th>'.$tr['The first generation take commission'].'</th>
    <th>'.$tr['The second generation take commission'].'</th>
    <th>'.$tr['The third generation take commission'].'</th>
    <th>'.$tr['The fourth generation take commission'].'</th>
    <th>'.$tr['Company commission income'].'</th>
    <th>'.$tr['Personal first generation commission amount'].'</th>
    <th>'.$tr['Personal first generation commission accumulated'].'</th>
    <th>'.$tr['Personal second generation commission amount'].'</th>
    <th>'.$tr['Personal second generation commission accumulated'].'</th>
    <th>'.$tr['Personal third generation commission amount'].'</th>
    <th>'.$tr['Personal third generation commission accumulated'].'</th>
    <th>'.$tr['Personal 4th generation commission amount'].'</th>
    <th>'.$tr['Personal 4th generation commission accumulated'].'</th>
    <th>'.$tr['Personal sub-commission amount'].'</th>
    <th>'.$tr['Personal sub-commission accumulated'].'</th>
    <th>'.$tr['Individual has paid the amount'].'</th>
    <th>'.$tr['Dividend distribution time'].'</th>
    <th>'.$tr['Remark'].'</th>
  </tr>
  ';

  // -------------------------------------------
  // 下載按鈕 $tr['update'] = '更新'; $tr['download'] = '下載';
  // -------------------------------------------
  $filename      = "bonusagent_result_".$current_datepicker_start.'_'.$current_datepicker.'.csv';
  $absfilename   = dirname(__FILE__) ."/tmp_dl/$filename";
  if(file_exists($absfilename)) {
    $csv_download_url_html = '<a href="./tmp_dl/'.$filename.'" class="btn btn-success" >'.$tr['download'].'CSV</a>';
  }else{
    $csv_download_url_html = '<button class="btn btn-primary" onclick="bonus_now_update();" >'.$tr['update'].'</button>';
  }

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
  </table>
  ';

  // 參考使用 datatables 顯示
  // https://datatables.net/examples/styling/bootstrap.html
  $extend_head = $extend_head.'
  <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
  <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
  <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
  ';
  // DATA tables jquery plugging -- 要放在 head 內 不可以放 body $tr['Member Account'] = '會員帳號';$tr['Check membership details'] = '檢查會員的詳細資料';$tr['Identity Management'] = '管理員';$tr['Identity Agent'] = '代理商';$tr['Identity Member'] = '會員';$tr['Currently no information'] = '目前沒有資料';$tr['Every page shows'] = '每頁顯示';$tr['Count'] = '筆';$tr['Currently in the'] = '目前在第';$tr['page'] = '頁';$tr['Total'] = '共';$tr['page'] = '頁';$tr['Currently no information'] = '目前沒有資料';$tr['From'] = '從';$tr['counts data filtering'] = '筆資料中過濾';$tr['Members of the organizational structure of the state'] = '會員的組織結構狀態';$tr['Member Identity'] = '會員身份';$tr['Identity Management Title'] = '管理員';
$extend_head = $extend_head.'
  <script type="text/javascript" language="javascript" class="init">
    $(document).ready(function() {
      $("#show_list").DataTable( {
          "bProcessing": true,
          "bServerSide": true,
          "bRetrieve": true,
          "searching": true,
          "ajax": "bonus_commission_agent_action.php?a=bonusagent_show&current_datepicker='.$current_datepicker.'",
          "oLanguage": {
            "sSearch": "'.$tr['Account'].':",
            "sEmptyTable": "'.$tr['Currently no information'].'!",
            "sLengthMenu": "'.$tr['Every page shows'].' _MENU_ '.$tr['Count'].'",
            "sZeroRecords": "'.$tr['Currently no information'].'",
            "sInfo": "'.$tr['Currently in the'].' _PAGE_ '.$tr['page'].'，'.$tr['total'].' _PAGES_ '.$tr['page'].'",
            "sInfoEmpty": "'.$tr['Currently no information'].'",
            "sInfoFiltered": "'.$tr['From'].' _MAX_ '.$tr['counts data filtering'].')"
          },
          "columns": [
            { "data": "id", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'member_treemap.php?id="+oData.id+"\' target=\"_BLANK\" title=\"'.$tr['Members of the organizational structure of the state'].'\">"+oData .id+"</a>");}},
            { "data": "account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'member_account.php?a="+oData.id+"\' target=\"_BLANK\" title=\"'.$tr['Check membership details'].'\">"+oData .account+"</a>");}},
            { "data": "therole", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'#\' title=\"'.$tr['Member Identity'].'R='.$tr['Identity Management Title'].'A='.$tr['Identity Agent'].'M='.$tr['Identity Member'].'\">"+oData.therole+"</a>"); }},
            { "data": "member_level", "searchable": false, "orderable": true },
            { "data": "level_account_1", "searchable": true, "orderable": true },
            { "data": "level_account_2", "searchable": true, "orderable": true },
            { "data": "level_account_3", "searchable": true, "orderable": true },
            { "data": "level_account_4", "searchable": true, "orderable": true },
            { "data": "agency_commission", "searchable": false, "orderable": true },
            { "data": "level_bonus_1", "searchable": false, "orderable": true },
            { "data": "level_bonus_2", "searchable": false, "orderable": true },
            { "data": "level_bonus_3", "searchable": false, "orderable": true },
            { "data": "level_bonus_4", "searchable": false, "orderable": true },
            { "data": "company_bonus", "searchable": false, "orderable": true },
            { "data": "member_bonuscount_1", "searchable": false, "orderable": true },
            { "data": "member_bonus_1", "searchable": false, "orderable": true },
            { "data": "member_bonuscount_2", "searchable": false, "orderable": true },
            { "data": "member_bonus_2", "searchable": false, "orderable": true },
            { "data": "member_bonuscount_3", "searchable": false, "orderable": true },
            { "data": "member_bonus_3", "searchable": false, "orderable": true },
            { "data": "member_bonuscount_4", "searchable": false, "orderable": true },
            { "data": "member_bonus_4", "searchable": false, "orderable": true },
            { "data": "member_bonuscount", "searchable": false, "orderable": true },
            { "data": "member_bonusamount", "searchable": false, "orderable": true },
            { "data": "member_bonusamount_paid_html", "searchable": false, "orderable": true },
            { "data": "member_bonusamount_paidtime_html", "searchable": false, "orderable": true },
            { "data": "note", "searchable": false, "orderable": true }
            ]
      } );
      $.get("bonus_commission_agent_action.php?a=show_summary&current_datepicker='.$current_datepicker.'",
        function(result){
          $("#show_summary").html(result);
      });
    } )

  </script>
  ';
// -------------------------------------------------------------------------
// sorttable 的 jquery and plug info END
// -------------------------------------------------------------------------


  // -------------------------------------------------------------------------

  //$tr['Daily Revenue Statement'] = '每日營收日結報表'; $tr['The current query date is'] = '目前查詢的日期為';$tr['The bonus report for the US East Time'] = '的獎金報表，為美東時間';$tr['Daily settlement time range is'] = '每日結算時間範圍為';$tr['correspond Taiwan Standard Time'] = '對應的中原時間';$tr['The range is'] = '範圍為';$tr['If you need to update the data, you need to be more'] = '如果需要更新資料，需要先更新';$tr['Followed by the order'] = '再依序執行';$tr['Settlement date information update'] = '結算日資料更新及';$tr['Individual bonus commission update,Can get the latest information'] = '個人分紅傭金更新，才可以得到最新的資料';
  // -------------------------------------------------------------------------
  $show_tips_html = '<div class="alert alert-default">
  <p>* '.$tr['The current query date is'].' '.$current_datepicker_start.' ~ '.$current_datepicker.' '.$tr['The bonus report for the US East Time'].'(UTC -04)，'.$tr['Daily settlement time range is'].' '.$current_datepicker.' 00:00:00 -04 ~ '.$current_datepicker.' 23:59:59 -04 </p>
  <p>* '.$tr['correspond Taiwan Standard Time'].'(UTC +08)'.$tr['The range is'].'：'.date( "Y-m-d", strtotime( "$current_datepicker -1 day")).' 13:00:00+08 ~ '.$current_datepicker.' 12:59:59+08</p>
  <p>* '.$tr['If you need to update the data, you need to be more'].'<a href="statistics_daily_report.php" target="_BLANK">'.$tr['Daily Revenue Statement'].'</a>，'.$tr['Followed by the order'].'(1)'.$tr['Settlement date information update'].'(2)'.$tr['Individual bonus commission update,Can get the latest information'].'。</p>
  <p>'.$show_rule_html.'</p>
  </div>';

  // $tr['EDT(GMT -5)'] = '美東時間';$tr['Inquiry'] = '查詢';$tr['Commission settlement date'] = '傭金結算日';
  // -------------------------------------------------------------------------
  // 加盟金計算報表 -- 選擇日期 -- FORM
  $date_selector_html = '
  <form class="form-inline" method="get">
    <div class="form-group">
      <div class="input-group">
        <div class="input-group-addon">'.$tr['Commission settlement date'].$tr['EDT(GMT -5)'].'</div>
        <div class="input-group-addon"><input type="text" class="form-control" name="current_datepicker" id="current_datepicker" placeholder="ex:2017-01-22" value="'.$current_datepicker.'"></div>
      </div>
    </div>
    <button class="btn btn-primary" onclick="gotoindex();" >'.$tr['Inquiry'].'</button>
    '.$csv_download_url_html.'
  </form>
  <hr>';

  // default date
  $dateyearrange_start 	= date("Y");
  $dateyearrange_end 		= date("Y");
  $dateyearrange = $dateyearrange_start.':'.$dateyearrange_end;
  // ref: http://api.jqueryui.com/datepicker/#entry-examples $tr['Bonuses will be updated soon'] = '即將更新獎金記錄';
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
    function gotoindex() {
      var datepicker = $("#current_datepicker").val();
      var goto_url = "'.$_SERVER['PHP_SELF'].'\'?current_datepicker="+datepicker;
      window.location.replace(goto_url);
    }
    function bonus_now_update(){
      var datepicker = $("#current_datepicker").val();
      var show_text = \''.$tr['Bonuses will be updated soon'].'...\';
      var goto_url = "'.$_SERVER['PHP_SELF'].'?current_datepicker="+datepicker;
      var updatingcodeurl = "bonus_commission_agent_action.php?a=bonus_update&current_datepicker="+datepicker;

      if(confirm(show_text)){
        myWindow = window.open(updatingcodeurl, \'gpk_window\', \'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800\', false);
    		myWindow.focus();
      }
    }
  </script>
  ';

  // 選擇日期 html
  $show_dateselector_html = $date_selector_html.$date_selector_js;
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

  <div id="show_summary" class="col-12 col-md-12">
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
