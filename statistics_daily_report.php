  <?php
// ----------------------------------------------------------------------------
// Features:    後台 -- 每日營收日結報表(已生成資料庫頁面)
// File Name:    statistics_daily_report.php
// Author:        Barkley
// DB table:  root_statisticsdailyreport  每日營收日結報表
// Related:   每日營收報表, 搭配的程式功能說明
// statistics_daily_immediately.php    後台 - 即時統計 - 每日營收日結報表, 要修改下面的程式增加項目的時候，需要先使用這只程式即時測試函式並驗證。
// statistics_daily_report.php         後台 - 每日營收日結報表(讀取已生成資料庫頁面), 透過 php system 功能呼叫 statistics_daily_output_cmd.php 執行, 主要都從這個程式開始呼叫。
// statistics_daily_report_lib.php     後台 - 每日營收日結報表 - 專用函式庫(計算資料使用函式, 每個統計項目的公式都放這裡)
// statistics_daily_report_action.php  後台 - 每日營收日結報表動作程式 - 透過此程式呼叫 php system command 功能, 及其他後續擴充功能.
// statistics_daily_output_cmd.php     後台 - 每日營收日結報表(命令列模式, 主要用來排程生成日報表)
// command example: /usr/bin/php70 /home/testgpk2demo/web/begpk2/statistics_daily_report_output_cmd.php run 2017-02-26
// Log:
// 2017.2.27 改寫,原本的即時計算移除.以資料庫為主,排程定時統計。
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

// 投注紀錄檔 DB config 及 runSQLall_DB2 lib -- 搭配日結報表函式庫使用
require_once dirname(__FILE__) . "/config_betlog.php";
// 日結報表函式庫
require_once dirname(__FILE__) . "/statistics_daily_report_lib.php";
// 遊戲管理列表專用函式庫
require_once dirname(__FILE__) ."/casino_switch_process_lib.php";


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
$function_title = $tr['revenue report'];
// 擴充 head 內的 css or js
$extend_head = '';
// 放在結尾的 js
$extend_js = '';
// body 內的主要內容
$indexbody_content = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['profit and promotion'] . '</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';
// ----------------------------------------------------------------------------

// --------------------------------------------------------------------------
// 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
// --------------------------------------------------------------------------
$account = '';
$edate = gmdate('Y-m-d', strtotime('now -1 days -4 hours'));
$sdate = $edate;
$only_nonzero = true;
isset($_GET['sdate']) and validateDate($_GET['sdate'], 'Y-m-d') and $sdate = $_GET['sdate'];
isset($_GET['edate']) and validateDate($_GET['edate'], 'Y-m-d') and $edate = $_GET['edate'];
isset($_GET['account']) and $account = filter_input(INPUT_GET, 'account');
isset($_GET['only_nonzero']) and $only_nonzero = filter_input(INPUT_GET, 'only_nonzero', FILTER_VALIDATE_BOOLEAN);

// --------------------------------------------------------------------------
// 共有 4 個 get 變數取得
// --------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// 檢查系統資料庫中 table root_statisticsdailyreport 表格(每日營收統計報表)，有多少天的資料已經被生成了。
// Usage: dailydate_index_stats($list_number = 10, $list_date='2017-02-24')
// list_number  列出多少筆, 預設 30 筆
// list_date    從那一天開始列出, 預設今天
// ---------------------------------------------------------------------------
function dailydate_index_stats($list_number = 10, $list_date = '2017-02-24')
{
    global $su;
    global $tr;
    /* 表格資料內容 */
    $dailydate_stats_data = '';
    /* 列出幾天內的 */
    $d_max = $list_number;

    /* 列出 n 天內的生成資料 */
    $date_max =  $list_date;
    $date_min =  date("Y-m-d", strtotime("$list_date -$d_max days"));

    $dailydate_count_sql = <<<SQL
        SELECT min,
               max,
               member_account_count,
               to_char((min AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as min_tz,
               to_char((max AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as max_tz,
               dailydate
        FROM (
            SELECT dailydate,
                   MIN(updatetime) as min,
                   MAX (updatetime) as max,
                   count(member_account) as member_account_count
            FROM root_statisticsdailyreport
            WHERE dailydate BETWEEN '$date_min' AND '$date_max'
            AND member_therole != 'R'
            AND member_account NOT IN ('{$GLOBALS['gcash_cashier_account']}', '{$GLOBALS['gtoken_cashier_account']}')
            %1\$s
            %2\$s
            GROUP BY dailydate
        ) as dailydateindex
    SQL;

    $dailydate_count_sql = sprintf(
        $dailydate_count_sql,
        !$GLOBALS['account'] ? '' : "AND member_account = '{$GLOBALS['account']}'",
        !$GLOBALS['only_nonzero'] ? '' : "AND (" . WHERE_NONZERO_COND . ")"
    );

    $dailydate_count_result = runSQLall_prepared($dailydate_count_sql);
    /* 列出每個日期的生成資料 */
    for ($d = 0; $d <= $d_max; $d++) {
        $dailydate_value = strtotime($list_date);
        $dailydate_list_value = date("Y-m-d", strtotime("-$d day", $dailydate_value));
        $dailydate_row = array_filter($dailydate_count_result, function ($dailydate_row) use ($dailydate_list_value) {
            return $dailydate_row->dailydate === $dailydate_list_value;
        });
        $dailydate_row = array_pop($dailydate_row);

        if (!$dailydate_row) {
            // 沒有資料
            $dailydate_count = 0;
            $dailydate_list_value_html = '<a href="?sdate=%1$s&edate=%1$s" onclick="blockscreengotoindex();" title="切換顯示日期到 %1$s">%1$s</a>';
            $dailydate_list_value_html = sprintf($dailydate_list_value_html, $dailydate_list_value);

            $dailydate_updatetime = 'N/A';
            // only superuser can reload
            if (in_array($_SESSION['agent']->account, $su['superuser'])) {
                $dailydate_updatetime = '<a href="#" onclick="reload_dailydate_stats_data(\'' . $dailydate_list_value . '\')"  title="沒有資料(點擊可以立即更新)"><span class="glyphicon glyphicon-retweet"></span>N/A</a>';
            }

            // reload button
            $dailydate_update_action = '<button type="button" class="btn btn-info btn-xs" onclick="reload_dailydate_stats_data(\'' . $dailydate_list_value . '\')" value="RELOAD"><span class="glyphicon glyphicon-refresh" aria-hidden="true"></span></button>';
            $dailydate_stats_data = $dailydate_stats_data . '
                <tr>
                <td>' . $dailydate_list_value_html . '</td>
                <td>' . $dailydate_count . '</td>
                <td>' . $dailydate_updatetime . '</td>
                </tr>
            ';
        } else {
            // 有資料
            // 日報日期 + index url link
            $dailydate_list_value_html = '<a href="?sdate=%1$s&edate=%1$s" onclick="blockscreengotoindex();" title="切換顯示日期到 %1$s">%1$s</a>';
            $dailydate_list_value_html = sprintf($dailydate_list_value_html, $dailydate_list_value);
            // data count
            $dailydate_count = $dailydate_row->member_account_count;
            // update time

            $dailydate_updatetime = '<a href="#" title="資料集內的資料最後更新時間區間：美東時間(-4)' . $dailydate_row->min_tz . '~' . $dailydate_row->max_tz . '">' . date("m-d_H", strtotime($dailydate_row->max_tz));
            $dailydate_update_action = '';

            // only superuser can reload
            if (in_array($_SESSION['agent']->account, $su['superuser'])) {
                $dailydate_updatetime = '<a href="#" onclick="reload_dailydate_stats_data(\'' . $dailydate_list_value . '\')"  title="資料集內的資料最後更新時間區間：美東時間(-4)' . $dailydate_row->min_tz . '~' . $dailydate_row->max_tz . '(點擊可以立即更新)">' . date("m-d_H", strtotime($dailydate_row->max_tz)) . ' <button class="glyphicon glyphicon-refresh"></button></a>';
                $dailydate_update_action = '<button type="button" class="btn btn-info btn-xs" onclick="reload_dailydate_stats_data(\'' . $dailydate_list_value . '\')" value="RELOAD"><span class="glyphicon glyphicon-refresh" aria-hidden="true"></span></button>';
            }

            // reload button , skip it，合併到上面一列。
            $dailydate_stats_data = $dailydate_stats_data . '
            <tr>
            <td>' . $dailydate_list_value_html . '</td>
            <td>' . $dailydate_count . '</td>
            <td>' . $dailydate_updatetime . '</td>
            </tr>
            ';
        }
    }

// 將資料送出
    $update_data_js_html = "
<script>
	function reload_dailydate_stats_data(update_dailydate){
    var update_dailydate = update_dailydate;
    var confirm_text ='你确定要产生/更新日期为'+update_dailydate+'的日报表?';
    var r = confirm(confirm_text);
    if (r === true) {
      var gotourl   = 'statistics_daily_report_action.php?a=cmdrun&d='+update_dailydate;
      var win_title = '更新生成每日营收日结报表';
      var wait_text = " . '\'<div style="width: 100%;		height: 100vh;		display: flex;		justify-content: center;		align-items: center;		overflow: hidden;">执行中，1000笔纪录约需60秒。请勿关闭视窗.<img src="./ui/loading.gif"></div>\';' . "
      myWindow = window.open('', win_title, 'status=yes,resizable=yes,top=0,left=0,height=600,width=800', false);
      myWindow.document.write(wait_text);
      myWindow.moveTo(0,0);
      myWindow = window.open(gotourl, win_title, 'status=yes,resizable=yes,top=0,left=0,height=600,width=800', false);
      myWindow.focus();
    }else{
      // user cancel
    }
	};
</script>
";

// 統計資料及索引
    $dailydate_stats_html = '
  <table class="table table-bordered small">
    <thead>
      <tr class="active">
        <th>'.$tr['Statistical date'].'</th>
        <th>'.$tr['number of data'].'</th>
        <th>'.$tr['last update time'].'</th>
      </tr>
    </thead>
    <tbody style="background-color:rgba(255,255,255,0.9);">
      ' . $dailydate_stats_data . '
    </tbody>
  </table>
' . $update_data_js_html;

    return ($dailydate_stats_html);
}
// ---------------------------------------------------------------------------
// 左邊的報表 list END
// ---------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 選擇查詢--顯示一個時間 date y-m-d 的日報表統計數據
// 指定顯示的日期
// Deprecated
// -------------------------------------------------------------------------
function show_dateselector_output($current_datepicker)
{

    global $csv_download_url_html;
    global $tr;
    // 日報表 -- 選擇日期
    $date_selector_html ='
  <span>
  <form class="form-inline" method="get">
    <div class="form-group">
      <div class="input-group mr-2">
        <div class="input-group-addon">'.$tr['date et'].'</div>
        <div class="input-group-addon"><input type="text" class="form-control" name="current_datepicker" id="current_datepicker" placeholder="ex:2017-01-20" value="'.$current_datepicker.'"></div>
      </div>
    </div>
    <button class="btn btn-primary mr-2" id="daily_statistics_report_date_query" onclick="gotoindex();">'.$tr['specified date to inquiry'].'</button>

    <span id="csv_button">'.$csv_download_url_html.'</span>
  </form>
  </span>
';
// <button class="btn btn-primary mr-2" id="daily_statistics_report_date_query" onclick="gotoindex();">'.$tr['specified date to inquiry'].'</button>

    // default date
    $dateyearrange_start = date("Y");
    $dateyearrange_end = date("Y");
    $dateyearrange = $dateyearrange_start . ':' . $dateyearrange_end;
    // ref: http://api.jqueryui.com/datepicker/#entry-examples

    // -------------------------
    // 2020-2-11
    // for datetimepicker
    $new_date = '00:00:00';
    $est_current_datetime = gmdate('H:i:s', time()+-4 * 3600);
    $caluc_hours = round((strtotime($est_current_datetime) - strtotime($new_date))/(60*60*24)); // 計算相差之天數

    if($caluc_hours == 0){
      $datetimepick = '-1d';
    }else{
      $datetimepick = '-2d';
    }
    // -------------------------

    $date_selector_js = '
  <script>
    $(document).ready(function() {
      $( "#current_datepicker" ).datepicker({
        yearRange: "' . $dateyearrange_start . ':' . $dateyearrange_end . '",
        maxDate: "'.$datetimepick.'",
        minDate: "-6w",
        showButtonPanel: true,
      	dateFormat: "yy-mm-dd",
      	changeMonth: true,
      	changeYear: true
      });
    } );
  </script>
  ';
  // 原版
  // $date_selector_js = '
  // <script>
  //   $(document).ready(function() {
  //     $( "#current_datepicker" ).datepicker({
  //       yearRange: "' . $dateyearrange_start . ':' . $dateyearrange_end . '",
  //       maxDate: "+0d",
  //       minDate: "-6w",
  //       showButtonPanel: true,
  //     	dateFormat: "yy-mm-dd",
  //     	changeMonth: true,
  //     	changeYear: true
  //     });
  //   } );
  // </script>
  // ';


    // 選擇日期 html
    $show_dateselector_html = $date_selector_html . $date_selector_js;

    return ($show_dateselector_html);
}
// -------------------------------------------------------------------------
// END function
// -------------------------------------------------------------------------

$table_colname_front_html = <<<HTML
<tr>
  <th rowspan="2">ID</th>
  <th rowspan="2">{$tr['Account']}</th>
  <th rowspan="2">{$tr['identity']}</th>
  <th rowspan="2">{$tr['the upline agents']}</th>
  <th rowspan="2">{$tr['effective bet amount']}</th>
  <th rowspan="2">{$tr['profit and loss']}</th>
  <th rowspan="2">{$tr['bet slip']}</th>

  <th colspan="3">{$tr['deposits']}</th>

  <th colspan="2">{$tr['withdrawals']}</th>

  <th rowspan="2">{$tr['cash transfer']}</th>
  <th rowspan="2">{$tr['preferential']}</th>
  <th rowspan="2">{$tr['Bonus']}</th>
  <th rowspan="2">{$tr['deposits fee']}</th>

  <th colspan="2">{$tr['withdrawal fee']}</th>

  <th rowspan="2">{$tr['Franchise Fee']}</th>

  <th colspan="2">{$tr['wallet balance']}</th>


  <th rowspan="2" title="进入游戏时，自动从使用者现金帐户转帐到游戏币帐户的本日累计总和，负值代表个人本日转出多少现金总和。">{$tr['cash to tokens']}</th> <!-- 現金轉代幣 -->
  <th rowspan="2" title="本日管理员游戏币存款到使用者的总和，带有自订稽核值，可从存簿检查，会员数值只有正值，负值为系统的出纳帐号。">{$tr['Token deposit']}</th> <!-- 代幣存款-->
  <th rowspan="2" title="设计为回收错误的游戏币发放，包含游戏币存款、优惠、反水及派彩等项目，本日该使用者回收的总和，数值只有正值。">{$tr['token recovery']}</th> <!-- 代幣回收 -->

</tr>
<tr>
  <!-- 存款 -->
  <th>{$tr['online']}</th>
  <th>{$tr['company']}</th>
  <th>{$tr['manual']}</th>
  <!-- 提款 -->
  <th>{$tr['Franchise']}</th>
  <th>{$tr['Gtoken']}</th>
  <!-- 提款费用 -->
  <th>{$tr['Administrative costs'] }</th>
  <th>{$tr['Administrative deduction']}</th>
  <!-- 钱包余额 -->
  <th>{$tr['Franchise']}</th>
  <th>{$tr['Gtoken']}</th>
</tr>
HTML;

// 表格欄位名稱
$table_colname_html = <<<HTML
<tr>
  <th>ID</th>
  <th>{$tr['Account']}</th>
  <th>{$tr['identity']}</th>
  <th>{$tr['the upline agents']}</th>
  <th>{$tr['effective bet amount']}</th>
  <th>{$tr['profit and loss']}</th>
  <th>{$tr['bet slip']}</th>

  <!-- 存款 -->
  <th>{$tr['online']}</th>
  <th>{$tr['company']}</th>
  <th>{$tr['manual']}</th>
  <!-- 提款 -->
  <th>{$tr['Franchise']}</th>
  <th>{$tr['Gtoken']}</th>

  <th>{$tr['cash transfer']}</th>
  <th>{$tr['preferential']}</th>
  <th>{$tr['Bonus']}</th>
  <th>{$tr['deposits fee']}</th>

  <!-- 提款费用 -->
  <th>{$tr['Administrative costs'] }</th>
  <th>{$tr['Administrative deduction']}</th>

  <th>{$tr['Franchise Fee']}</th>

  <!-- 钱包余额 -->
  <th>{$tr['Franchise']}</th>
  <th>{$tr['Gtoken']}</th>

  <th title="进入游戏时，自动从使用者现金帐户转帐到游戏币帐户的本日累计总和，负值代表个人本日转出多少现金总和。">{$tr['cash to tokens']}</th> <!-- 現金轉代幣 -->
  <th title="本日管理员游戏币存款到使用者的总和，带有自订稽核值，可从存簿检查，会员数值只有正值，负值为系统的出纳帐号。">{$tr['Token deposit']}</th> <!-- 代幣存款-->
  <th title="设计为回收错误的游戏币发放，包含游戏币存款、优惠、反水及派彩等项目，本日该使用者回收的总和，数值只有正值。">{$tr['token recovery']}</th> <!-- 代幣回收 -->

</tr>
HTML;

$sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
// $sorttablecss = ' class="table table-striped" ';
// 列出資料, 主表格架構
$show_list_html = '';
// 列表
$show_list_html = $show_list_html . '
<table ' . $sorttablecss . '>
<thead>
' . $table_colname_front_html . '
</thead>
</table>
';

// 參考使用 datatables 顯示
// https://datatables.net/examples/styling/bootstrap.html
$extend_head = $extend_head . '
  <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
  <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
  <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
  <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
  <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
  ';

// DATA tables jquery plugging -- 要放在 head 內 不可以放 body
$extend_head .= <<<HTML
  <script type="text/javascript" language="javascript" class="init">
    $(document).ready(function() {
      $("#show_list").DataTable( {
          dom: '<tipl>',
          oLanguage: {
              "sEmptyTable": "{$tr['no_data']}",//"目前没有资料!",
              "sLengthMenu": "{$tr['display']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
              "sInfo": "{$tr['Display']} _START_ {$tr['to']} _END_ {$tr['result']},{$tr['total']} _TOTAL_ {$tr['item']}",//"显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
              "sInfoFiltered": "({$tr['from']} _MAX_ {$tr['filtering in data']})"//"(由 _MAX_ 项结果过滤)"
          },
          scrollX: true,
          bProcessing: true,
          bServerSide: true,
          bRetrieve: true,
          searching: false,
          ajax: "statistics_daily_report_action.php?a=reload_dailyreport&sdate={$sdate}&edate={$edate}&account={$account}&only_nonzero={$only_nonzero}",
          columns: [
            { data: "id", fnCreatedCell: function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'member_treemap.php?id="+oData.id+"\' target=\"_BLANK\" title=\"會員的組織詳細資料\">"+oData.id+"</a>");
              }
            },
            { data: "account", fnCreatedCell: function (nTd, sData, oData, iRow, iCol) {
              $(nTd).html("<a href=\'member_account.php?a="+oData.id+"\' target=\"_BLANK\" title=\"會員的帳號資訊查詢\">"+oData.account+"</a>");
              }
            },
            { data: "therole", fnCreatedCell: function (nTd, sData, oData, iRow, iCol) {
              $(nTd).html("<a href=\'#\' title=\"會員身份 R=管理員 A=代理商 M=會員\">"+oData.therole+"</a>");
              }
            },
            { data: "parent", fnCreatedCell: function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html('-');
                oData.parent_account != '-' &&
                $(nTd).html(`<a href="member_treemap.php?id=\${oData.parent}" target="_BLANK" title="會員上一代組織詳細資料">\${oData.parent_account}</a>`)
              }
            },
            { data: "casino_all_bets", searchable: false, orderable: true, className: "dt-right" },
            { data: "casino_all_profitlost", "searchable": false, "orderable": true, className:"dt-right", fnCreatedCell: function (nTd, sData, oData, iRow, iCol) {
                if(oData.casino_all_profitlost < 0){
                  $(nTd).html("<span style=\"color:green;\">"+oData.casino_all_profitlost+"</span>");
                }else{
                  $(nTd).html("<span style=\"color:green;\">"+oData.casino_all_profitlost+"</span>");
                }
              }
            },
            { data: "casino_all_count", searchable: false, orderable: true, className:"dt-right" },

            {
              data: "api_deposits",
              searchable: false,
              orderable: true,
              className:"dt-right",
            },
            { data: "company_deposits", searchable: false, orderable: true, className:"dt-right" },
            { data: "gcash_cashdeposit", searchable: false, orderable: true, className:"dt-right" },

            { data: "gcash_cashwithdrawal", searchable: false, orderable: true, className:"dt-right" },
            { data: "gtoken_tokengcash", searchable: false, orderable: true, className:"dt-right" },

            { data: "gcash_cashtransfer", searchable: false, orderable: true, className:"dt-right" },
            { data: "gtoken_tokenfavorable", searchable: false, orderable: true, className:"dt-right" },
            { data: "gtoken_tokenpreferential", searchable: false, orderable: true, className:"dt-right" },
            { data: "cash_fee", searchable: false, orderable: false, className:"dt-right" },

            { data: "gtokenpassbook_tokenadministrationfees", searchable: false, orderable: true, className:"dt-right" },
            { data: "gtokenpassbook_tokenadministration", searchable: false, orderable: true, className:"dt-right" },

            { data: "agent_review_reult", searchable: false, orderable: true, className:"dt-right" },

            { data: "member_gcash", searchable: false, orderable: true, className:"dt-right" },
            { data: "member_gtoken", searchable: false, orderable: true, className:"dt-right" },

            { data: "gcash_cashgtoken", searchable: false, orderable: true, className:"dt-right" },
            { data: "gtoken_tokendeposit", searchable: false, orderable: true, className:"dt-right" },
            { data: "gtoken_tokenrecycling", searchable: false, orderable: true, className:"dt-right" }
          ]
      });
    });
  </script>
HTML;

// -------------------------------------------
// 下載按鈕
// -------------------------------------------
// $filename = "daily_report_result_" . $current_datepicker . '.csv';
// $absfilename = dirname(__FILE__) . "/tmp_dl/".$filename;

// 檔案名稱
// $filename = "daily_report_result_".$current_datepicker;
$filename = "daily_report_result_{$sdate}_to_{$edate}";
$absfilename = "./tmp_dl/".$filename.".xlsx";

//$tr['set up as csv'] = '建立CSV';
if (file_exists($absfilename)) {
    // $csv_download_url_html = '<a href="./tmp_dl/' . $filename . '" class="btn btn-success" >'.$tr['Export Excel'].'</a>';
    $csv_download_url_html = '<a href="'.$absfilename . '" class="btn btn-success btn-sm" >'.$tr['Export Excel'].'</a>';


} else {
    $csv_download_url_html = '<button class="btn btn-warning btn-sm" onclick="make_csv();" >'.$tr['Build Excel'].'</button>';
}

// -------------------------------------------
// 計算會員數量
// $userlist_sql = "SELECT count(*) FROM root_member;";
// $userlist_count = runSQLall($userlist_sql)[1]->count;
// -------------------------------------
// 計算每日日期的資料，已經生成再資料表 statisticsdailyreport 中的數量
// $dailystats_sql = "SELECT count(*) FROM root_statisticsdailyreport  WHERE dailydate BETWEEN '$sdate' AND '$edate';";
// $dailystats_count = runSQLall($dailystats_sql)[1]->count;
// -------------------------------------

// -------------------------------------------------------------------------
/* https://proj.jutainet.com/issues/4307 */
$show_tips_html = <<<HTML
<div class="alert alert-success">
  <p class="mb-1">*{$tr['The current date of the query is']} $sdate ~ $edate; {$tr['The radiation organization bonus report for the US East Time (UTC -04), the daily settlement time range is']}{$tr['The range is']} $sdate 00:00:00 ~ $edate 23:59:59</p>
  <p class="mb-1">*{$tr['System settlement time is daily']} 00:00 - 00:30 {$tr['about 30 minutes']}</p>
</div>
HTML;

/* $show_tips_html = <<<HTML
<div class="alert alert-success">
  <p class="mb-1">*{$tr['The current date of the query is']} $sdate ~ $edate; {$tr['The range is']} $sdate 00:00:00 -04 ~ $edate 23:59:59 -04</p>
  <p class="mb-1">*{$tr['The corresponding time is from']} $sdate 12:00:00+08 ~ $edate 11:59:59+08</p>
  <p class="mb-1">*{$tr['Currently system members have']} $userlist_count {$tr['Pen (regardless of validity), the system counts the "Daily Revenue Daily Report" database, date']} $sdate ~ $edate {$tr['have']} $dailystats_count {$tr['Count']}</p>
</div>
HTML; */

  // <p>* 目前查询的日期为 ' . $current_datepicker . ' 的营收日报表，为美东时间（UTC -04），每日结算时间范围为 ' . $current_datepicker . ' 00:00:00 -04 ~ ' . $current_datepicker . ' 23:59:59 -04 </p>
  // <p>* ' . $tr['The corresponding time is from'] . ' ' . date("Y-m-d", strtotime("$current_datepicker -1 day")) . ' 13:00:00+08 ~ ' . $current_datepicker . ' 12:59:59+08</p>
  // <p>* 目前系统会员有' . $userlist_count . '笔（不论有效无效），系统统计「每日营收日结报表」资料库，日期 ' . $current_datepicker . ' 有 ' . $dailystats_count . ' 笔。</p>
  // </div>';

// $show_tips_html = $show_tips_html.'
//   <a href="statistics_daily_immediately.php" title="切換到每日營收日結報表(即時統計)" onclick="blockscreengotoindex();" class="btn btn-default">每日營收日結報表(即時統計)</a>
//   <a href="statistics_daily_report.php" title="切換到每日營收日結報表(已統計生成的資料)" class="btn btn-success">每日營收日結報表(已統計生成的資料)</a>
//   <hr>';
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 輸出 $current_datepicker 選擇日期的表單 -- 即時查詢指定日期
// -------------------------------------------------------------------------
// $show_dateselector_html = show_dateselector_output($current_datepicker);
$show_dateselector_html = '<span id="csv_button" class="float-right" style="margin-top: -4px;">' . $csv_download_url_html . '</span>';
// var_dump($show_dateselector_html);
// -------------------------------------------------------------------------

// --------------------------------------------
// 單日統計及日期列表 -- 左邊欄位的索引資料
$est_lastdate = gmdate('Y-m-d', strtotime('now -1 days -4 hours'));
$dailydate_index_stats_html = dailydate_index_stats(60, $est_lastdate);
// --------------------------------------------

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
  <button class="btn btn-success btn-sm" id="show">'.$tr['menu on'].'</button>
  </span>

  <div id="index_menu" style="display:block;
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
  ' . $dailydate_index_stats_html . '
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
// 本日摘要
// -------------------------------------------------------------------------

// get casino game categories
$casino_game_categories_displayname = get_casino_game_categories_displayname();
$casino_game_categories = $casino_game_categories_displayname['categories'];
$casino_game_displayname = $casino_game_categories_displayname['displayname'];
// var_dump($casino_game_categories);

$casino_attributes = [];

// get casino lib
$lib = new casino_switch_process_lib();

foreach ($casino_game_categories as $casino => $categories) {
    $casino_attributes[] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino . '_bets' . "') :: numeric(20,2)) , 0) as sum_" . $casino . '_bets';
    $casino_attributes[] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino . '_wins' . "') :: numeric(20,2)) , 0) as sum_" . $casino . '_wins';
    $casino_attributes[] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino . '_profitlost' . "') :: numeric(20,2)) , 0) as sum_" . $casino . '_profitlost';
    $casino_attributes[] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino . '_count' . "') :: numeric(20,2)) , 0) as sum_" . $casino . '_count';

    foreach ($categories as $category) {
        $casino_category = $casino . '_' . $category;

        $casino_attributes[] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_bets' . "') :: numeric(20,2)) , 0) as sum_" . $casino_category . '_bets';
        $casino_attributes[] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_wins' . "') :: numeric(20,2)) , 0) as sum_" . $casino_category . '_wins';
        $casino_attributes[] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_profitlost' . "') :: numeric(20,2)) , 0) as sum_" . $casino_category . '_profitlost';
        $casino_attributes[] = "coalesce( SUM( (root_statisticsdailyreport.betlog_detail->>'" . $casino_category . '_count' . "') :: numeric(20,2)) , 0) as sum_" . $casino_category . '_count';
    }
}

$casino_attributes_sql = (count($casino_attributes) >= 1) ? ','.implode(',', $casino_attributes) : '';

$summary_sql = <<<SQL
  SELECT
    root_statisticsdailyreport.dailydate,
    count(root_statisticsdailyreport.dailydate ) as count_dailydate,
    sum(agency_commission) as sum_agency_commission,
    sum(all_bets) as sum_all_bets,
    sum(all_wins) as sum_all_wins,
    sum(all_profitlost) as sum_all_profitlost,
    sum(all_count) as sum_all_count,
    sum(tokendeposit) as sum_tokendeposit,
    sum(tokenfavorable) as sum_tokenfavorable,
    sum(tokenpreferential) as sum_tokenpreferential,
    sum(tokenpay) as sum_tokenpay,
    sum(tokengcash) as sum_tokengcash,
    sum(tokenrecycling) as sum_tokenrecycling,
    sum(cashdeposit) + sum(apicashdeposit) + sum(company_deposits) as sum_cashdeposit,
    sum(apicashdeposit) as sum_apicashdeposit,
    sum(cashtransfer) as sum_cashtransfer,
    sum(cashwithdrawal) as sum_cashwithdrawal,
    sum(cashgtoken) as sum_cashgtoken,
    sum(cashadministrationfees) as sum_cashadministrationfees,
    sum(tokenadministrationfees) as sum_tokenadministrationfees,
    sum(tokenadministration) as sum_tokenadministration

    $casino_attributes_sql

  FROM root_statisticsdailyreport
  WHERE root_statisticsdailyreport.dailydate BETWEEN '$sdate' AND '$edate'
    AND (root_statisticsdailyreport.member_account != 'gcashcashier'
    AND root_statisticsdailyreport.member_account != 'gtokencashier'

    -- 2020/02/10 過濾角色為R的
    AND root_statisticsdailyreport.member_therole != 'R'
    --
    %1\$s

    )
  GROUP BY root_statisticsdailyreport.dailydate
  %2\$s
  ORDER BY dailydate DESC
  ;
SQL;
// print($summary_sql);

$nonzero_sql = "HAVING (" . HAVING_NONZERO_COND . ")";

/* count(data) of summary */
$count_summary_sql = <<<SQL
    SELECT count(*) as count_dailydate_member FROM root_statisticsdailyreport
    WHERE root_statisticsdailyreport.dailydate BETWEEN '$sdate' AND '$edate'
    AND root_statisticsdailyreport.member_account != 'gcashcashier'
    AND root_statisticsdailyreport.member_account != 'gtokencashier'
    AND root_statisticsdailyreport.member_therole != 'R'
    %1\$s
    %2\$s
SQL;

$count_summary_sql = sprintf(
    $count_summary_sql,
    !$account ? '' : "AND member_account = '$account'",
    !$only_nonzero ? '' : "AND (" . WHERE_NONZERO_COND . ")"
);

$count_dailydate_member = runSQLall($count_summary_sql)[1]->count_dailydate_member;

$summary_sql = sprintf(
    $summary_sql,
    !$account ? '' : "AND member_account = '$account'",
    /* 過濾無營收日報 */
    $only_nonzero ? $nonzero_sql : ''
);

$summary_results = runSQLall($summary_sql);
$summary_result[0] = $summary_results[0];

if ($summary_results[0] >= 1) {
    /* 加總區間日報統計 */
    $summary_result[1] = new stdClass;
    $summary_results = array_slice($summary_results, 1);

    foreach(get_object_vars($summary_results[0]) as $result_col => $result_val) {
        $summary_result[1]->$result_col = array_column($summary_results, $result_col);
        if ($result_col === 'dailydate') continue;
        $summary_result[1]->$result_col = array_sum($summary_result[1]->$result_col);
    }

    $summary_result[1]->dailydate = count($summary_result[1]->dailydate) == 1 ? $sdate : "$sdate" . '<div class="text-center">~</div>' . "$edate";
}

if ($summary_result[0] >= 1) {
    $summary_profit_ratio = '0 %';
    if ($summary_result[1]->sum_all_bets > 0) {
        $summary_profit_ratio = number_format($summary_result[1]->sum_all_profitlost / $summary_result[1]->sum_all_bets * 100, 2, '.', '') . ' %';
    }

    // 摘要內容
    $summary_items_html = '
  <tr>
  <th width="80px" class="d-none">' . $summary_result[1]->dailydate . '</th>
  <th>' . $count_dailydate_member . '</th>
  <th>' . $summary_result[1]->sum_agency_commission . '</th>
  <th>' . $summary_result[1]->sum_tokendeposit . '</th>
  <th>' . $summary_result[1]->sum_tokenfavorable . '</th>
  <th>' . $summary_result[1]->sum_tokenpreferential . '</th>
  <th>' . $summary_result[1]->sum_tokenpay . '</th>
  <th>' . $summary_result[1]->sum_tokengcash . '</th>
  <th>' . $summary_result[1]->sum_tokenrecycling . '</th>
  <th>' . $summary_result[1]->sum_cashdeposit . '</th>
  <th>' . $summary_result[1]->sum_cashtransfer . '</th>
  <th>' . $summary_result[1]->sum_cashwithdrawal . '</th>
  <th>' . $summary_result[1]->sum_cashgtoken . '</th>
  <th>' . $summary_result[1]->sum_all_bets . '</th>
  <th>' . $summary_result[1]->sum_all_wins . '</th>
  <th>' . $summary_result[1]->sum_all_profitlost . '</th>
  <th>' . $summary_result[1]->sum_all_count . '</th>
  <th class="text-right">' . $summary_profit_ratio . '</th>
  </tr>';

    $casino_summary_toggle = <<<HTML
  <div class="col-12 d-flex">
    <a class="btn btn-secondary text-white ml-auto" role="button" data-toggle="collapse" href="#casino_summary_collapse" aria-expanded="false" aria-controls="collapseExample">
      {$tr['details of casino']}
      <i class="fas statistics_icon fa-chevron-down"></i>
    </a>
  </div>
HTML;

    $casino_summary = <<<HTML
  <div class="col-12">
    <div class="collapse" id="casino_summary_collapse">
      <div class="row">
HTML;

    foreach ($casino_game_categories as $casino => $categories) {
        // $casino_name = $tr[strtoupper($casino)] ?? strtoupper($casino);
        $casino_name = $lib->getCurrentLanguageCasinoName($casino_game_displayname[$casino], $_SESSION['lang']);
        $casino_summary .= <<<HTML
        <div class="col-12 col-sm-6 col-md-4">
          <hr/>
          <h4 class="hs">{$casino_name}</h4>
          <table class="table table-bordered small">
            <tr class="active">
              <th></th>
              <th class="text-right">{$tr['betting']}</th>
              <th class="text-right">{$tr['Payout']}</th>
              <th class="text-right">{$tr['profit and loss']}</th>
              <th class="text-right">{$tr['Profit and loss / betting (%)']}</th>
            </tr>
HTML;
        foreach ($categories as $category) {

            $bet_attribute = 'sum_' . $casino . '_' . $category . '_bets';
            $win_attribute = 'sum_' . $casino . '_' . $category . '_wins';
            $profitlost_attribute = 'sum_' . $casino . '_' . $category . '_profitlost';

            $bet = $summary_result[1]->$bet_attribute;
            $win = $summary_result[1]->$win_attribute;
            $profitlost = $summary_result[1]->$profitlost_attribute;
            $profitlost_ratio = '0 %';
            if ($bet != 0) {
                $profitlost_ratio = number_format($profitlost / $bet * 100, 2, '.', '') . ' %';
            }

            $category_name = $tr[$category] ?? $category;

            $casino_summary .= <<<HTML
        <tr>
          <td>$category_name</td>
          <td align="right">$bet</td>
          <td align="right">$win</td>
          <td align="right">$profitlost</td>
          <td align="right">$profitlost_ratio</td>
        </tr>
HTML;

        }

        $bet_attribute = 'sum_' . $casino . '_bets';
        $win_attribute = 'sum_' . $casino . '_wins';
        $profitlost_attribute = 'sum_' . $casino . '_profitlost';

        $bet = $summary_result[1]->$bet_attribute;
        $win = $summary_result[1]->$win_attribute;
        $profitlost = $summary_result[1]->$profitlost_attribute;
        $profitlost_ratio = '0 %';
        if ($bet != 0) {
            $profitlost_ratio = number_format($profitlost / $bet * 100, 2, '.', '') . ' %';
        }

        $casino_summary .= <<<HTML
      <tr>
        <td><strong>{$tr['total']}</strong></td>
        <td align="right"><strong>$bet</strong></td>
        <td align="right"><strong>$win</strong></td>
        <td align="right"><strong>$profitlost</strong></td>
        <td align="right"><strong>$profitlost_ratio</strong></td>
      </tr>
HTML;

        $casino_summary .= <<<HTML
      </table>
    </div>
HTML;

    }

    $casino_summary .= '</div></div></div>';

} else {
    // 日報表資料還沒生成
    $summary_items_html = '
  <tr>
  <th width="80px" class="d-none">' . "$sdate<div class=\"text-center\">~</div>$edate" . '</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  <th>NULL</th>
  </tr>';

    $casino_summary_toggle = '';
    $casino_summary = '';
}

// 摘要說明
$summary_items_title_html = '
<tr class="active">
  <th class="d-none">' . $tr['Statistical date'] . '</th>
  <th>' . $tr['number of data'] . '<span class="glyphicon glyphicon-info-sign" title="時間範圍內的結算資料筆數"></span></th>
  <th>' . $tr['commission of agent'] . '</th>
  <th>' . $tr['Token deposit'] . ' </th>
  <th>' . $tr['token discount'] . ' </th>
  <th>' . $tr['token bouns'] . ' </th>
  <th>' . $tr['Token Payout'] . ' </th>
  <th>' . $tr['token to cash'] . ' </th>
  <th>' . $tr['token recovery'] . '</th>
  <th>' . $tr['cash deposit'] . ' </th>
  <th>' . $tr['cash transfer'] . ' </th>
  <th>' . $tr['cash withdrawal'] . ' </th>
  <th>' . $tr['cash to tokens'] . ' </th>
  <th>' . $tr['casino total betting']. '</th>
  <th>' . $tr['casino total payout']. '</th>
  <th>' .$tr['casino total profit and loss']. '</th>
  <th>' .$tr['casino betting slip'].'</th>
  <th class="text-right">' . $tr['total profit and loss and betting'] . '</th>
</tr>';

// 輸出 html
$summary_html = '
<div class="statistics_table_width">
<table class="table table-bordered small">
  <thead>
  ' . $summary_items_title_html . '
  </thead>
  <tbody style="background-color:rgba(255,255,255,0.9);">
  ' . $summary_items_html . '
  </tbody>
</table>
</div>';

// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 切成 1 欄版面
$indexbody_content = '';
$indexbody_content = $indexbody_content . '
	<div class="row statistics_daily">
    ' . $dailydate_index_stats_switch_html. '
    <div class="col-12">
    ' . $show_tips_html . '
    </div>
    <div class="col-12">
    ' . $summary_html . '
    </div>
    ' . $casino_summary_toggle . '
    ' . $casino_summary . '
		<div class="col-12">
    ' . $show_list_html . '
		</div>

	</div>
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';
// -------------------------------------------------------------------------

/* -------------------------------------------------------------------------
    left panel
------------------------------------------------------------------------- */
$lefttitle_content = <<<HTML
<span class="glyphicon glyphicon-search" aria-hidden="true"></span>{$tr['Search criteria']}
HTML;
$leftbody_content = <<<HTML
<form id="search_form" name="search_form" method="get">

<div class="row">
  <div class="col-12">
    <label for="account">{$tr['Account']}</label>
  </div>
  <div class="col-12 form-group">
    <input type="text" class="form-control" name="account" id="account" placeholder="{$tr['Account']}" value="{$account}"/>
  </div>
</div>

<div class="row">
  <div class="col-12 d-flex">
    <label>{$tr['Statistical interval']}</label>
    <div class="btn-group btn-group-sm ml-auto application" role="group" aria-label="Button group with nested dropdown">
      <button type="button" class="btn btn-secondary first">{$tr['grade default']}</button>
      <div class="btn-group btn-group-sm" role="group">
        <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></button>
        <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
          <a class="dropdown-item yesterday" onclick="settimerange('yesterday','yesterday')">{$tr['yesterday']}</a>
          <a class="dropdown-item week" onclick="settimerange('thisweek2','week')">{$tr['This week']}</a>
          <a class="dropdown-item lastweek" onclick="settimerange('lastweek','lastweek')">{$tr['Last week']}</a>
          <a class="dropdown-item month" onclick="settimerange('thismonth2','month')">{$tr['this month']}</a>
          <a class="dropdown-item lastmonth" onclick="settimerange('lastmonth','lastmonth')">{$tr['last month']}</a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 form-group rwd_doublerow_time">
    <div class="input-group">
      <div class="input-group-prepend">
        <span class="input-group-text">{$tr['Starting time']}</span>
      </div>
      <input type="text" class="form-control" name="sdate" placeholder="ex:2017-01-20" required data-type="dateYMD" autocomplete="off" value="$sdate"/>
    </div>
    <div class="input-group">
      <div class="input-group-prepend">
        <span class="input-group-text">{$tr['End time']}</span>
      </div>
      <input type="text" class="form-control" name="edate" placeholder="ex:2017-01-20" required data-type="dateYMD" autocomplete="off" value="$edate"/>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <label>{$tr['option']}</label>
  </div>
  <div class="col-12">
    <label class="ml-3">
      <input type="checkbox" name="only_nonzero" value="true" />
      {$tr['Filter data whose daily settlement is 0']}
    </label>
  </div>
</div>

</form>
<hr />
<div class="row">
  <div class="col-12">
    <button id="submit_to_inquiry" form="search_form" class="btn btn-success btn-sm btn-block" type="submit">{$tr['Inquiry']}
    </button>
  </div>
</div>
HTML;
// -------------------------------------------------------------------------
// javascript function
// -------------------------------------------------------------------------
$extend_head .= <<<HTML
<script>
/* Deprecated
function gotoindex() {
  var datepicker = $("#current_datepicker").val();
  var goto_url = '?current_datepicker='+datepicker;
  window.location.replace(goto_url);
}; */
function make_csv(){
  // var datepicker = $("#c2urrent_datepicker").val();
  var sdate = $("#search_form [name=sdate]").val();
  var edate = $("#search_form [name=edate]").val();
  var account = $("#account").val();
  var only_nonzero = $("#search_form [name=only_nonzero]").prop('checked');
  var show_text = '建立EXCEL檔中...<img width=20px height=20px src="ui/loading.gif" />';
  var updatingcodeurl = `statistics_daily_report_action.php?a=makecsv&sdate=\${sdate}&edate=\${edate}&account=\${account}&only_nonzero=\${only_nonzero}`;
  $("#csv_button").html(show_text);

  $.get(updatingcodeurl, function(result){
      $("#csv_button").html(result);
    }
  );
}
$(function(){
  $('#casino_summary_collapse').on('show.bs.collapse', function () {
    $('.statistics_icon').css({'transform':'rotate(180deg)'});
  });
  $('#casino_summary_collapse').on('hidden.bs.collapse', function () {
    $('.statistics_icon').css({'transform':'rotate(0deg)'});
  });
});

</script>
HTML;

$extend_head .= <<<HTML
<script>
// 本日、昨日、本周、上周、上個月button
function settimerange(alias,text) {
  _time = utils.getTime(alias);
  // 單號申請時間
  $("#search_form [name=sdate]").val(_time.start);
  $("#search_form [name=edate]").val(_time.end);
  //console.log(_time);

  //更換顯示到選單外 20200525新增
  var currentonclick = $('.'+text+'').attr('onclick');
  var currenttext = $('.'+text+'').text();

  //first change
  $('.application .first').removeClass('week month');
  $('.application .first').attr('onclick',currentonclick);
  $('.application .first').text(currenttext);
}
</script>
HTML;

/* utils */
$extend_js .= <<<HTML
<script>
window.utils = {
  clip2board: function (target) {
    var TextRange = document.createRange();
    TextRange.selectNode(target);
    sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(TextRange);

    document.execCommand("copy") ? alert('{$tr['Success.']}>') : alert('{$tr['fail']}');
  },
  toggleText: function (target) {
    target.style.display = target.style.display === 'none' ? 'block' : 'none'
  },
  // depends on moment.js
  getTime: function (alias) {
    var timezone = 'America/St_Thomas'
    var _now = moment().tz(timezone)
    var _moment = _now.clone()
    var scheme = 'YYYY-MM-DD'
    var start, end

    var week_today = _moment.format(scheme);
    switch (alias) {
      case 'now':
        scheme = 'YYYY-MM-DD HH:mm:ss'
        start = _moment.format(scheme)
        end = _moment.format(scheme)
        break;
      case 'today':
        start = `\${_moment.format(scheme)}`
        end = `\${_moment.format(scheme)}`
        break
      case 'yesterday':
        _moment.add(-1, 'd')
        start = `\${_moment.format(scheme)}`
        end = `\${_moment.format(scheme)}`
        break;
      case 'thisweek':
        start = `\${_moment.day(0).format(scheme)}`
        end = `\${week_today}`

        // start = `\${_moment.day(0).format(scheme)}`
        // end = `\${_moment.day(6).format(scheme)}`
        break;
      case 'thisweek2':
        _moment.add(-1, 'd')
        end = `\${_moment.format(scheme)}`
        start = `\${_moment.day(0).format(scheme)}`
        break;
      case 'lastweek':
          end = `\${_moment.day(6).add(-1, 'w').format(scheme)}`
          start = `\${_moment.day(0).format(scheme)}`
        break;
      case 'thismonth':
        start = `\${_moment.date(1).format(scheme)}`
        end = `\${week_today}`

        // start = `\${_moment.date(1).format(scheme)}`
        // end = `\${_moment.add(1, 'M').add(-1, 'd').format(scheme)}`
        break;
      case 'thismonth2':
        _moment.add(-1, 'd')
        end = `\${_moment.format(scheme)}`
        start = `\${_moment.date(1).format(scheme)}`
        break;
      case 'lastmonth':
        end = `\${_moment.date(1).add(-1, 'd').format(scheme)}`
        start = `\${_moment.date(1).format(scheme)}`
        break;
      default:
    }
    return {
      _now,
      start,
      end,
      breakpoint: _now.format(scheme)
    }
  }
}

// 寬度計算
$(document).ready(function(){
  $(window).load(function(){
    tablewidth()
  });
  $(window).resize(function(){
    tablewidth()
  }).resize();


  $('.panel-heading[data-target="#searchindex"]').click(function(){
    //等候變化
    setTimeout(function(){
      tablewidth()
    },500)
  });

  $('.panel-heading.collapsed').click(function(){
    //等候變化
    setTimeout(function(){
      tablewidth()
    },500)
  });

});
function tablewidth(){
  var window_w = $(window).width();
  var window_contain = 990;
  if ( window_w > window_contain ){
    var padding_w = 110;
    var window_w = $(window).width();
    var left_w = $('.panel.panel-default').width();
    var newtable_w = parseInt( window_w - left_w - padding_w );
    $('.statistics_daily div.dataTables_wrapper').width(newtable_w);
    $('.statistics_table_width').width(newtable_w);
    // console.log('window_w :' +window_w);
    // console.log('left_w :' +left_w);
    // console.log('newtable_w :' +newtable_w);
  }
}

</script>
HTML;

/* ready function */
$extend_js .= <<<HTML
<script>
$(document).ready(function() {
  $('input[data-type="dateYMD"]').datetimepicker({
    showButtonPanel: true,
    changeMonth: true,
    changeYear: false,
    timepicker: false,
    format: "Y-m-d",
    step:1,
    maxDate: '$est_lastdate'
  });
  $("#search_form [name=only_nonzero]").prop('checked', $only_nonzero);
  $("#search_form").on('submit', function (e) {
    e.preventDefault()
    let orig_params = $(this).serializeArray()
    let params = new URLSearchParams();
    orig_params.forEach(param => params.append(param.name, param.value))
    orig_params.find(param => param.name == 'only_nonzero') === undefined && params.append('only_nonzero', false)
    location.search = '?' + params.toString()
  })
});
</script>
HTML;

// -------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $function_title . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>' . $tr['Query results'] . $show_dateselector_html;
// 主要內容 -- content
$tmpl['panelbody_content'] = $indexbody_content;

/* left column */
$tmpl['indextitle_content'] = $lefttitle_content;
$tmpl['indexbody_content'] = $leftbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/beadmin.tmpl.php");
// include "template/beadmin_fluid.tmpl.php";
include "template/s2col.tmpl.php";
?>
