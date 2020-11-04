<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 反水計算及反水派送
// File Name:	preferential_calculation.php
// Author:    Barkley
// Related:   DB root_favorable(會員反水設定及打碼設定)
// Log:
// 由每日報表，統計投注額後，依據設定比例 1% ~ 3% ，發放反水給予會員。
// 反水可以轉帳到代幣帳戶代幣帳戶可以設定稽核，也可以轉帳到現金帳戶
// 2017.3.5 v0.5
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
$function_title 		= $tr['Casino Preferential calculation'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' .$tr['profit and promotion'] . '</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

// 除錯開關 debug =  1--> on , 0 --> off
$debug = 0;

// ---------------------------------------------------------------
// MAIN start
// ---------------------------------------------------------------

// ---------------------------------------------------------------------------
// 檢查系統資料庫中 table root_statisticsdailypreferential 表格(每日反水統計報表)，有多少天的資料已經被生成了。
// Usage: dailydate_index_stats($list_number = 10, $list_date='2017-02-24')
// list_number  列出多少筆, 預設 30 筆
// list_date    從那一天開始列出, 預設今天
// ---------------------------------------------------------------------------
function dailydate_index_stats($list_number = 10, $list_date='2017-02-24') {
  global $su;
  global $tr;
  // 表格資料內容
  $dailydate_stats_data = '';
  // 列出幾天內的
  $d_max = $list_number;

  $dailydate_count_sql = "SELECT dailydate, count(member_account) as count_member_account ,sum(all_bets_amount) as sum_all_bets_amount, sum(all_favorablerate_amount) as sum_all_favorablerate_amount
  ,to_char((MAX(updatetime) AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as lastupdate, to_char((MIN(updatetime) AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as min_tz , to_char((MAX(updatetime)  AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as max_tz
  FROM root_statisticsdailypreferential GROUP BY dailydate ORDER BY dailydate DESC LIMIT $list_number;";
  // 資料 SQL
  $dailydate_count_result = runSQLall($dailydate_count_sql);
  // 列表
  if($dailydate_count_result[0] >= 1) {
    for($i=1;$i<=$dailydate_count_result[0];$i++) {
      // 有資料
      $dailydate = $dailydate_count_result[$i]->dailydate;
      $dailydate_list_value_html = '<a href="preferential_calculation.php?current_datepicker='.$dailydate.'">'.$dailydate.'</a>';
      $count_member_account_html =  $dailydate_count_result[$i]->count_member_account;
      $sum_all_bets_amount =  $dailydate_count_result[$i]->sum_all_bets_amount;
      $last_updatetime = $dailydate_count_result[$i]->lastupdate;
      $min_tz = $dailydate_count_result[$i]->min_tz;
      $max_tz = $dailydate_count_result[$i]->max_tz;

      $reload_button = '<a href="#"  title="资料集内的资料最后更新时间：美东时间（-5）'.$min_tz.'~'.$max_tz.'">' . $last_updatetime;

      // only superuser can reload
      if(in_array($_SESSION['agent']->account, $su['superuser'])) {
        $reload_button = '<a href="#" onclick="preferential_update(\''.$dailydate.'\')"  title="资料集内的资料最后更新时间：美东时间（-5）'.$min_tz.'~'.$max_tz.'(点击可以立即更新)">
        '.$last_updatetime.' <button class="glyphicon glyphicon-refresh"></button></a>';
      }

      $dailydate_stats_data = $dailydate_stats_data.'
      <tr>
        <td>'.$dailydate_list_value_html.'</td>
        <td>'.$count_member_account_html.'</td>
        <td>'.$sum_all_bets_amount.'</td>
        <td id="update_'.$dailydate.'">
          ' . $reload_button . '
        </td>
      </tr>
      ';
    }
  }else{
    // 沒有資料
    $dailydate_stats_data = $dailydate_stats_data.'
    <tr>
      <td></td>
      <td></td>
      <td></td>
    </tr>
    ';
  }
  // end if had data


  // 統計資料及索引
  $dailydate_stats_html = '
    <table class="table table-bordered small">
      <thead>
        <tr class="active">
          <th>'.$tr['bonus date'].'</th>
          <th>'.$tr['Number of data'].'</th>
          <th>'.$tr['today betting'].'</th>
          <th>'.$tr['last update time'].'</th>
        </tr>
      </thead>
      <tbody style="background-color:#eee;">
        '.$dailydate_stats_data.'
      </tbody>
    </table>
  ';

  return($dailydate_stats_html);
}
// ---------------------------------------------------------------------------
// 左邊的報表 list END
// ---------------------------------------------------------------------------

// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
  $d = DateTime::createFromFormat($format, $date);
  return $d && $d->format($format) == $date;
}


// 取得 today date get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if(isset($_GET['current_datepicker']) && validateDate($_GET['current_datepicker'], 'Y-m-d')) {
  // 格式正確
  $current_datepicker = $_GET['current_datepicker'];
}else{
  // php 格式的 2017-02-24
  // 轉換為美東的時間 date
  $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
  date_timezone_set($date, timezone_open('America/St_Thomas'));
  $current_datepicker = date_format($date, 'Y-m-d');
}

$filter_empty = false;
if(isset($_GET['filter_empty']) && $_GET['filter_empty'] == 'true') {
  $filter_empty = true;
}

//var_dump($current_datepicker);
//echo date('Y-m-d H:i:sP');
// ---------------------------------------------------------------
// var_dump($_GET);

  // $filename      = "bonuspreferential_result_".$current_datepicker.'.csv';
  // $absfilename   = dirname(__FILE__) ."/tmp_dl/$filename";

  $filename    = "bonuspreferential_result_".$current_datepicker;
  $absfilename = "./tmp_dl/".$filename.".xlsx";

  // -------------------------------------------
  // CSV下載按鈕
  // -------------------------------------------
  if(file_exists($absfilename)) {
    // $csv_download_url_html = '<a href="./tmp_dl/'.$filename.'" class="btn btn-success" >下载CSV</a>';

    $csv_download_url_html = '<a href="'.$absfilename.'" class="btn btn-success" >'.$tr['download'].'EXCEL</a>';

  }else{
    $csv_download_url_html = '';

  }

  // -------------------------------------------
  // CSV下載按鈕 END
  // -------------------------------------------

  // table title
  // 表格欄位名稱
  $table_colname_html = '
  <tr>
    <th>' . $tr['ID'] . '</th>
    <th>' . $tr['member upper id'].'</th>
    <th>' . $tr['identity'] . '</th>
    <th>' . $tr['Account'] . '</th>
    <th>' . $tr['Bonus level'] .'</th>
    <th>' . $tr['date'] . '</th>
    <th>' . $tr['total betting'] . '</th>
    <th>' . $tr['total amount of bonus'].'</th>
    <th>' . $tr['self bonus'].'</th>
    <th>' . $tr['preferential of agent'] . '</th>
    <th>' . $tr['preferential detail'] .'</th>
    <th>' . $tr['preferential transfer'] .'</th>
    <th>' . $tr['sent bonus'].'</th>
    <th>' . $tr['The difference that needs to be reissued'].'</th>
  </tr>
  ';

  // ---------------------------------- END table data get






  // ---------------------------------------------------------------------------
  // 本日累計總表 -- summary REPORT
  // ---------------------------------------------------------------------------
  $sum_sql  =<<<SQL
  SELECT
    COUNT(member_account) as count_member_account,
    SUM(mg_totalwager) as sum_mg_totalwager,
    SUM(mg_favorablerate_amount) as sum_mg_favorablerate_amount,
    SUM(all_favorablerate_amount) as sum_all_favorablerate_amount,
    SUM(all_favorablerate_beensent_amount) as sum_all_favorablerate_beensent_amount,
    SUM(all_favorablerate_difference_amount) as sum_all_favorablerate_difference_amount,
    SUM(all_bets_amount) as sum_all_bets_amount,
    SUM( CAST (all_favorablerate_amount_detail->>'self_favorable' AS NUMERIC) ) as sum_self_favorable
  FROM root_statisticsdailypreferential
  WHERE dailydate = '$current_datepicker';
SQL;

  $sum_result      = runSQLall($sum_sql);
  // var_dump($sum_sql);
  //print_r($sum_sql);
  //var_dump($sum_result);
  if($sum_result[0] == 1) {

  }

  // 會員數量
  $count_member_account_html = $sum_result[1]->count_member_account;

  // MG電子投注量
  $sum_mg_totalwager_html = money_format('%i', $sum_result[1]->sum_mg_totalwager);
  // MG電子派採量
  $sum_mg_favorablerate_amount_html = money_format('%i', $sum_result[1]->sum_mg_favorablerate_amount);

  // 本日累計總打碼
  $sum_all_bets_amount_html = money_format('%i', $sum_result[1]->sum_all_bets_amount);
  // 本日總反水
  $sum_all_favorablerate_amount_html = money_format('%i', $sum_result[1]->sum_all_favorablerate_amount);

  // 本日已經發送的反水
  $sum_all_favorablerate_beensent_amount_html  = money_format('%i', $sum_result[1]->sum_all_favorablerate_beensent_amount);
  // 需要補發的差額
  $all_favorablerate_difference_amount_html  = money_format('%i', $sum_result[1]->sum_all_favorablerate_difference_amount);

  $sum_self_favorable = money_format('%i', $sum_result[1]->sum_self_favorable);
  $sum_agent_favorable = money_format('%i', $sum_result[1]->sum_all_favorablerate_amount - $sum_result[1]->sum_self_favorable);

  $count_member_payout_sql  = "SELECT all_favorablerate_difference_amount FROM root_statisticsdailypreferential WHERE all_favorablerate_difference_amount != '0' AND dailydate = '".$current_datepicker."'; ";
  //echo $count_member_payout_sql;
  $count_member_payout = runSQL($count_member_payout_sql);

  if($sum_result[1]->sum_all_favorablerate_difference_amount > 0){
    $batchreleasebutton_html = '<button id="batchpayout_html_btn" class="btn btn-info" onclick="batchpayout_html();">'.$tr['batch sending'].'</button>';
    $batchpayout_html = '
      <div style="display: none;width: 800px;" id="batchpayout">
        <table class="table table-bordered">
          <thead>
            <tr bgcolor="#e6e9ed">
              <th>'.$tr['date'].'</th>
              <th>'.$tr['number of sent'].'</th>
              <th>'.$tr['estimated of amount of bonus'].'</th>
              <th>'.$tr['Bonus category'].'</th>
              <th>'.$tr['sending method'].'</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>'.$current_datepicker.'</td>
              <td>'.$count_member_payout.'</td>
              <td>'.$all_favorablerate_difference_amount_html.'</td>
              <td><select class="form-control" name="bonus_type" id="bonus_type"  onchange="auditsetting();"><option value="">--</option><option value="token">游戏币</option><option value="cash">现金</option></select></td>
              <td><select class="form-control" name="bonus_defstatus" id="bonus_defstatus" ><option value="0">取消</option><option value="1">可领取</option><option value="2" selected>暂停</option></select></td>
            </tr>
            <tr>
              <th bgcolor="#e6e9ed"><center>稽核方式</center></th>
              <td><select class="form-control" name="audit_type" id="audit_type" onchange="radio_check();" disabled><option value="none" selected="">--</option><option value="freeaudit">免稽核</option><option value="depositaudit">存款稽核</option><option value="shippingaudit">优惠稽核</option></select></td>
              <th bgcolor="#e6e9ed"><center><input type="radio" name="audit_calculate_type" value="audit_amount" onchange="radio_check();" checked disabled>稽核金额</center></th>
              <td><input class="form-control" name="audit_amount" id="audit_amount" value="0" placeholder="稽核金额，EX：100" disabled></td>
              <td></td>
            </tr>
            <tr>
              <td></td>
              <td></td>
              <th bgcolor="#e6e9ed"><center><input type="radio" name="audit_calculate_type" value="audit_ratio" onchange="radio_check();" disabled>稽核倍数</center></th>
              <td><input class="form-control" name="audit_ratio" id="audit_ratio" value="0" placeholder="稽核倍数，EX：0.4" disabled></td>
              <td><button id="payout_btn" class="btn btn-info" onclick="batchpayout();" disabled>发送</button>
                  <button class="btn btn-warning" onclick="batchpayoutpage_close();">取消</button></td>
            </tr>
          </tbody>
        </table>
      </div>';
  }else{
    $batchreleasebutton_html = '<button id="batchpayout_html_btn" class="btn btn-info" disabled>'.$tr['batch sending'].'</button>';
    $batchpayout_html = '';
  }

  // 表格欄位名稱
  $table_sum_colname_html = '
  <tr bgcolor="#e6e9ed">
    <th>' . $tr['date'] .'</th>
    <th>' . $tr['Number of data'] . '</th>
    <th>' . $tr['today total betting'] . '</th>
    <th>'.$tr['today total amount of bonus'] .'</th>
    <th>'.$tr['today total self amount of bonus'].'</th>
    <th>'.$tr['today total betting commissions'].'</th>
    <th>'.$tr['today sent bonus'].'</th>
    <th>'.$tr['The difference that needs to be reissued'].'</th>
    <th></th>
  </tr>
  ';
  // 每個欄位的總和結算
  $table_sum_data_html = '
  <tr>
    <td>'.$current_datepicker.'</td>
    <td>'.$count_member_account_html.'</td>
    <td>'.$sum_all_bets_amount_html.'</td>
    <td>'.$sum_all_favorablerate_amount_html.'</td>
    <td>'.$sum_self_favorable.'</td>
    <td>'.$sum_agent_favorable.'</td>
    <td>'.$sum_all_favorablerate_beensent_amount_html.'</td>
    <td>'.$all_favorablerate_difference_amount_html.'</td>
    <td>'.$batchreleasebutton_html.'</td>
  </tr>
  ';
  // ---------------------------------------------------------------------------
  // 本日累計總表 -- summary REPORT  END
  // ---------------------------------------------------------------------------





  // ---------------------------------------------------------------------------
  // 即時計算後的反水列表
  // ---------------------------------------------------------------------------

  $sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
  // $sorttablecss = ' class="table table-striped" ';

  // 列出資料, 主表格架構
  $show_list_html = '';

  // DATA 總和列表 -- summary
  $show_list_html = $show_list_html.'
  <table class="table table-bordered">
  <thead>
  '.$table_sum_colname_html.'
  </thead>
  <tbody>
  '.$table_sum_data_html.'
  </tbody>
  </table>
  <hr>
  ';

  // DATA 列表
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
  // ---------------------------------------------------------------------------

  // ---------------------------------------------------------------------------
  // 參考使用 datatables 顯示
  // https://datatables.net/examples/styling/bootstrap.html
  $extend_head = $extend_head.'
  <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
  <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
  <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
  <script type="text/javascript" language="javascript" src="./in/jquery.blockUI.js"></script>
  <!-- jquery datetimepicker js+css -->
  <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
  <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
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
            "sSearch": "'.$tr['Account'].'",//"會員帳號:",
            "sEmptyTable": "'.$tr['no data'].'",//"目前沒有資料!",
            "sLengthMenu": "'.$tr['each page'].' _MENU_ '.$tr['Count'].'",//"每頁顯示 _MENU_ 筆",
            "sZeroRecords":  "'.$tr['no data'].'",//"目前沒有資料",
            "sInfo": "'.$tr['now at'].' _PAGE_，'.$tr['total'].' _PAGES_ '.$tr['page'].'",//"目前在第 _PAGE_ 頁，共 _PAGES_ 頁",
            "sInfoEmpty": "'.$tr['no data'].'",//"目前沒有資料",
            "sInfoFiltered": "('.$tr['from'].' _MAX_ '.$tr['filtering in data'].')" ,//"(從 _MAX_ 筆資料中過濾)"
          },
          "ajax": "preferential_calculation_action.php?a=reload_preferentiallist&current_datepicker='.$current_datepicker.'&filter_empty='.$filter_empty.'",
          "columns": [
            { "data": "id", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'member_treemap.php?id="+oData.id+"\' target=\"_BLANK\" title=\"會員的組織詳細資料\">"+oData.id+"</a>");}},
            { "data": "parent", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'member_treemap.php?id="+oData.parent+"\' target=\"_BLANK\" title=\"會員上一代組織詳細資料\">"+oData.parent+"</a>");}},
            { "data": "therole", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'#\' title=\"會員身份 R=管理員 A=代理商 M=會員\">"+oData.therole+"</a>");}},
            { "data": "account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'member_account.php?a="+oData.id+"\' target=\"_BLANK\" title=\"會員的帳號資訊查詢\">"+oData.account+"</a>");}},
            { "data": "favorablerate_level", "searchable": false, "orderable": true },
            { "data": "dailydate", "searchable": false, "orderable": true },
            { "data": "all_bets_amount", "searchable": false, "orderable": true, className: "dt-right" },
            { "data": "all_favorablerate_amount", "searchable": false, "orderable": true, className: "dt-right" },
            { "data": "self_favorable", "searchable": false, "orderable": false, className: "dt-right" },
            { "data": "agent_favorable", "searchable": false, "orderable": false, className: "dt-right" },
            { "data": "all_favorablerate_amount_detail_html", "searchable": false, "orderable": false },
            { "data": "all_favorablerate_beensent_amount_html", "searchable": false, "orderable": false },
            { "data": "all_favorablerate_beensent_amount", "searchable": false, "orderable": true, className: "dt-right" },
            { "data": "all_favorablerate_difference_amount", "searchable": false, "orderable": true, className: "dt-right" }
            ]
      } );
    } )
  </script>
  ';
  // ---------------------------------------------------------------------------

  // 即時計算更新資料用 FUNCTION
  $extend_head = $extend_head.'
  <script type="text/javascript" language="javascript" class="init">
  function preferential_update(query_datas){
    var show_text = \'即将更新 \'+String(query_datas)+\' 的反水記錄...\';
    var updating_img = \'更新中...<img width="20px" height="20px" src=\"ui/loading.gif\" />\';
    var updatingcodeurl=\'preferential_calculation_action.php?a=prefer_update&prefer_update_date=\'+query_datas;

    if(confirm(show_text)){
      $("#update_"+query_datas).html(updating_img);
      myWindow = window.open(updatingcodeurl, \'gpk_window\', \'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800\', false);
  		myWindow.focus();
      setTimeout(function(){location.reload();},3000);
    }
  }
  function get_audit_calculate_type() {
    var radios = document.getElementsByName(\'audit_calculate_type\');

    for (var i = 0, length = radios.length; i < length; i++){
     if (radios[i].checked){
       return radios[i].value;
     }
    }

    return \'\';
  }
  function radio_check(){
    var radios = document.getElementsByName(\'audit_calculate_type\');
    var audit_calculate_type = get_audit_calculate_type();
    var audit_type = $("#audit_type").val();

    switch (audit_calculate_type) {
      case \'audit_amount\':
        $("#audit_amount").prop(\'disabled\', false);
        $("#audit_ratio").prop(\'disabled\', true);
        $("#audit_ratio").prop(\'value\', \'0\');
        break;

      case \'audit_ratio\':
        $("#audit_amount").prop(\'disabled\', true);
        $("#audit_amount").prop(\'value\', \'0\');
        $("#audit_ratio").prop(\'disabled\', false);
        break;
    }

    if(audit_type == "freeaudit") {
      $("#audit_amount").prop("disabled", true);
      $("#audit_ratio").prop("disabled", true);
      $("#audit_amount").prop("value", "0");
      $("#audit_ratio").prop("value", "0");
    } else {
      $("#audit_amount").prop("disabled", false);
      $("#audit_ratio").prop("disabled", false);
    }
  }
  function auditsetting(){
    var bonustype = $("#bonus_type").val();

    if(bonustype == ""){
      $("#payout_btn").prop(\'disabled\', true);
    }else{
      if(bonustype == \'token\'){
        $("#audit_type").prop(\'disabled\', false);
        $("[name=audit_calculate_type]").prop(\'disabled\', false);

        radio_check();
      }else{
        $("#audit_type").prop(\'disabled\', true);
        $("#audit_amount").prop(\'disabled\', true);
        $("#audit_amount").prop(\'value\', \'0\');
        $("#audit_ratio").prop(\'disabled\', true);
        $("#audit_ratio").prop(\'value\', \'0\');

        $("[name=audit_calculate_type]").prop(\'disabled\', true);
      }
      $("#payout_btn").prop(\'disabled\', false);
    }
  }
  function batchpayout_html(){ // 開啟時間區間選擇
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
  function batchpayoutpage_close(){ // 關閉時間區間選擇
    $.unblockUI();
  }
  function batchpayout(){
    $("#payout_btn").prop(\'disabled\', true);

    var show_text = \'即将發放 '.$current_datepicker.' 的反水記錄...\';
    var payout_status = $("#bonus_defstatus").val();
    var bonus_type = $("#bonus_type").val();
    var audit_type = $("#audit_type").val();
    var audit_amount = $("#audit_amount").val();
    var audit_ratio = $("#audit_ratio").val();
    var audit_calculate_type = get_audit_calculate_type();
    var updatingcodeurl = \'preferential_calculation_action.php?a=prefer_payout_update&prefer_payout_date='.$current_datepicker.'&s=\'+payout_status+\'&s1=\'+bonus_type+\'&s2=\'+audit_type+\'&s3=\'+audit_amount+\'&s4=\'+audit_ratio+\'&s5=\'+audit_calculate_type;

    // console.log(audit_type);

    if(bonus_type == \'token\' && audit_type == \'none\'){
      alert(\'请选择奖金的稽核方式！\');
      $("#payout_btn").prop(\'disabled\', false);
    }else{
      if(confirm(show_text)){
        $.unblockUI();
        $("#batchpayout_html_btn").prop(\'disabled\', true);
        myWindow = window.open(updatingcodeurl, \'gpk_window\', \'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800\', false);
    		myWindow.focus();
      }else{
        $("#payout_btn").prop(\'disabled\', false);
      }
    }
  }
  </script>
  ';



  // ---------------------------------------------------------------------------
  $show_tips_html = '<div class="alert alert-success">
  <p>* '.$tr['The current date of the query is'].' '.$current_datepicker.' '.$tr['The radiation organization bonus report for the US East Time (UTC -04), the daily settlement time range is'].''.$current_datepicker.' 00:00:00 ~ '.$current_datepicker.' 23:59:59 </p>
  <p>* '.$tr['please check'].'<a href="preferential_calculation_config.php"> '.$tr['Preferential setting'].'</a> '.$tr['each casino setting paremeters and agency organization transfer and accounting setup'].' </p>
  </div>
  ';

  // $show_tips_html = '<div class="alert alert-success">
  // <p>* 目前查询的日期为 '.$current_datepicker.' 的放射线组织奖金报表，为美东时间(UTC -05)，每日结算时间范围为 '.$current_datepicker.' 00:00:00-05 ~ '.$current_datepicker.' 23:59:59-05 </p>
  // <p>* '. $tr['The corresponding time is from'].' '.date( "Y-m-d", strtotime( "$current_datepicker -1 day")).' 13:00:00+08 ~ '.$current_datepicker.' 12:59:59+08</p>
  // <p>* 反水设定参考 系统管理-<a href="preferential_calculation_config.php">反水设定</a> 的各娱乐城设定参数 及 代理商组织转帐及分佣设定 生成。 </p>
  // </div>
  // ';

  // ---------------------------------------------------------------------------
  // 日報表 -- 選擇日期
  $filter_empty_checkbox_checked = '';
  if($filter_empty) {
    $filter_empty_checkbox_checked = 'checked';
  }

  $date_selector_html = '
  <hr>
  <form class="form-inline" method="get">
    <div class="form-group">
      <div class="input-group mr-2">
        <div class="input-group-addon">'.$tr['date et'].'</div>
        <div class="input-group-addon"><input type="text" class="form-control" name="current_datepicker" id="daily_statistics_report_date" placeholder="ex:2017-01-20" value="'.$current_datepicker.'"></div>
      </div>
    </div>
    <div class="checkbox">
      <label>
        <input type="checkbox" name="filter_empty" value="true" ' . $filter_empty_checkbox_checked . '> ' .$tr['only show betting members'] . '
      </label>
    </div>
    <button type="submit" class="btn btn-primary ml-2 mr-2" id="daily_statistics_report_date_query">' .$tr['search'] .'</button>
    '.$csv_download_url_html.'
  </form>
  <hr>
  ';

  // default date , 預設只能查詢前一週 minDate 的。
  $dateyearrange_start 	= date("Y");
  $dateyearrange_end 		= date("Y");
  $dateyearrange = $dateyearrange_start.':'.$dateyearrange_end;

  $current_date = gmdate('Y-m-d',time() + -4*3600);
  $default_min_date = gmdate('Y-m-d',strtotime('- 2 month'));
  $week = gmdate('Y-m-d',strtotime('- 7 days')); // 7天

  // ref: http://api.jqueryui.com/datepicker/#entry-examples
  // $date_selector_js = '
  // <script>
  //   $(document).ready(function() {
  //     $( "#daily_statistics_report_date" ).datepicker({
  //       yearRange: "'.$dateyearrange_start.':'.$dateyearrange_end.'",
  //       maxDate: "+0d",
  //       minDate: "-13w",
  //       showButtonPanel: true,
  //     	dateFormat: "yy-mm-dd",
  //     	changeMonth: true,
  //     	changeYear: true
  //     });
  //   } );
  // </script>
  // ';
  $date_selector_js = '
  <script>
    $(document).ready(function() {
      $( "#daily_statistics_report_date" ).datetimepicker({
        minDate: "'.$default_min_date.'",
        maxDate: "'.$current_date.'",
        showButtonPanel: true,
        timepicker:false,
        format: "Y-m-d",
        changeMonth: true,
        changeYear: true,
        step:1,
        initTime: "00:00"
      });
    } );
  </script>
  ';


  // 選擇日期 html
  $show_dateselector_html = $date_selector_html.$date_selector_js;
  // -------------------------------------------------------------------------




  // -------------------------------------------------------------------------
  // 輸出 系統內資料庫 root_statisticsdailypreferential 的反水報表, 以有已經有資料的為列表.
  // -------------------------------------------------------------------------

  // get EST time UTC-5 , 用美東時間來產生每天的日期並寫依序計算。
  //$est_date = gmdate('Y-m-d H:i:s',time() + -5*3600);
  //var_dump($est_date);
  $est_date = gmdate('Y-m-d',time() + -5*3600);

  // 反水日期內容列表 -- 左邊欄位的索引資料
  $dailydate_index_statisticsdailypreferential_html = dailydate_index_stats(30, "$est_date");
  // 加上 on / off開關
  $dailydate_index_stats_switch_html = '
  <span style="
  position: fixed;
  top: 5px;
  left: 5px;
  width: 420px;
  height: 20px;
  z-index: 1000;
  ">
  <button class="btn btn-primary btn-xs" style="display: none" id="hide">'.$tr['menu off'].'</button>
  <button class="btn btn-success btn-xs" id="show">'.$tr['menu on'].'</button>
  </span>

  <div id="index_menu" style="display:block;
  background-color: #e6e9ed;
  position: fixed;
  top: 30px;
  left: 5px;
  width: 420px;
  height: 95%;
  overflow: auto;
  z-index: 999;
  -webkit-box-shadow: 0px 8px 35px #333;
  -moz-box-shadow: 0px 8px 35px #333;
  box-shadow: 0px 8px 35px #333;
  ">
  '.$dailydate_index_statisticsdailypreferential_html.'
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
  // -------------------------------------------------------------------------




  // -------------------------------------------------------------------------
  // 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
    <div class="col-12 col-md-12">
    '.$show_tips_html.'
    '.$dailydate_index_stats_switch_html.'
    </div>

    <div class="col-12 col-md-12">
    '.$show_dateselector_html.'
    </div>

    <hr>
		<div class="col-12 col-md-12">
    '.$show_list_html.'
		</div>

	</div>
  	<br>
	<div class="row">
  		<div id="preview_result"></div>

	</div>
	'.$batchpayout_html;


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
