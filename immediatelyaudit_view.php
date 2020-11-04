<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 即時稽核
// File Name:	immediately_audit.php
// Author:		侑駿
// Related:
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// 投注紀錄檔 DB config 及 runSQLall_DB2 lib -- 搭配日結報表函式庫使用
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
$function_title 		= '即時稽核';
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  // -----------------------------------------
  // MG CASINO 資料表函式 $mg_account 帶入錢包的 MG帳號
  // -----------------------------------------
  function bettingrecords_mg($mg_account , $today_date){
    // var_dump($mg_account);
    if($mg_account != NULL) {
      // 資料庫依據不同的條件變換資料庫檔案
      $mg_bettingrecords_tables = 'test_mg_bettingrecords';
      // MG投注量總算
      // $mg_sql = 'SELECT SUM("TotalWager") as totalwager_sum, SUM("TotalPayout") as totalpayout_sum FROM "'.$mg_bettingrecords_tables.'"  WHERE "AccountNumber"'." = '$mg_account'  AND gamereceivetime = '$today_date';";
      $mg_sql = 'SELECT SUM("TotalWager") as totalwager_sum FROM "'.$mg_bettingrecords_tables.'"  WHERE "AccountNumber"'." = '$mg_account' AND gamereceivetime >= '$today_date' AND gamereceivetime < 'now()' GROUP BY \"AccountNumber\";";
      // var_dump($mg_sql);
      $mg_result = runSQLall_DB2($mg_sql);
      // var_dump($mg_result);

      if($mg_result[0] == 1) {
        $r['TotalWager']  = $mg_result[1]->totalwager_sum;
        // $r['TotalPayout'] = $mg_result[1]->totalpayout_sum;
        // $r['ProgressiveWage']   = $mg_result[1]->ProgressiveWage;
        // $r['ProfitLost']  = $r['TotalWager'] - $r['TotalPayout'];
        $r['code']        = TRUE;
        $r['messages']    = 'No data';
      }else{
        $r['TotalWager']  = 0;
        $r['TotalPayout'] = 0;
        // $r['ProgressiveWage']   = $mg_result[1]->ProgressiveWage;
        // $r['ProfitLost']  = $r['TotalWager'] - $r['TotalPayout'];
        $r['code']     = FALSE;
        $r['messages'] = 'DB query error';
      }
    }else{
      $r['amount']   = NULL;
      $r['code']     = 2;
      $r['messages'] = 'No data';
    }
    // var_dump($r);
    return($r);
  }
  // -----------------------------------------
  // 查詢指定帳號
  if(isset($_GET['a']) AND $_GET['a'] != NULL) {
    $query_id = filter_var($_GET['a'],FILTER_SANITIZE_STRING);
  }else{
    $query_id = 1;
  }
  // -------------------------------------
  // 搜尋「會員帳號」、「會員雙錢包」資訊, 搭配統計計算出每個人的營運貢獻狀況。 並且製作分頁功能
  // -------------------------------------
  $member_list_sql = "
  SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id
  WHERE root_member.status = '1'
  AND root_member.id = '".$query_id."';
  ";
  $member_list_result = runSQLall($member_list_sql);
  // var_dump($member_list_result);
  // -------------------------------------
  // 搜尋會員的「入款」、「存款」、「稽核」的設定值
  // -------------------------------------
  $grade_level_sql = "SELECT * FROM root_member_grade WHERE gradename = '".$member_list_result[1]->favorablerule."'";
  $grade_level_result = runSQLall($grade_level_sql);
  // var_dump($grade_level_result);
  // -------------------------------------
  // 行政費用設定值
  // -------------------------------------
  if ($grade_level_result[0] == 1) {
    $admin_cost_ratio = $grade_level_result[1]->administrative_cost_ratio;
  } else {
    $admin_cost_ratio = "(尚无费用)";
  }

  // 使用者所在的時區，sql 依據所在時區顯示 time
    // -------------------------------------
    if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
      $tz = $_SESSION['agent']->timezone;
    }else{
      $tz = '+08';
    }
    // var_dump($tz);
    // 轉換時區所要用的 sql timezone 參數
    $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
    $tzone = runSQLALL($tzsql);
    // var_dump($tzone);

    if($tzone[0]==1){
      $tzonename = $tzone[1]->name;
    }else{
      $tzonename = 'posix/Etc/GMT-8';
    }

    $show_listrow_html = "";
    // 存款金額合計
    $deposit_money_total = 0;
    // 總打碼量
    $deposit_total_html = 0;

    // 判斷是否有會員資料
    if ($member_list_result[0] >= 1) {
        // 會員帳號的使用變數
        $member_account = $member_list_result[1]->account;
        // 會員編號的使用變數
        $member_id = $member_list_result[1]->id;
        // MG帳戶的使用變數
        $mg_account = $member_list_result[1]->mg_account;
        // var_dump($member_account);
        // ----------------------------------------------------
        // 取款資料
        // ----------------------------------------------------
        $withdraw_sql = "
        SELECT *, to_char((applicationtime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as applicationtime_tz
        FROM root_withdraw_review
        WHERE account = '".$member_account."'
        AND status = 1
        ORDER BY applicationtime_tz DESC
        LIMIT 1;
        ";
        $withdraw_result = runSQLALL($withdraw_sql);
        // var_dump($withdraw_result);

        // ----------------------------------------------------
        // 如果無核可的取款時間，就由今天將全部的資料列表出來
        // ----------------------------------------------------
        if ($withdraw_result[0] == 1) {
          $last_times = $withdraw_result[1]->applicationtime_tz;
          // ----------------------------------------------------
          // 搜尋會員「存款稽核」與「優惠稽核」範圍時間
          // ----------------------------------------------------
          $gtokenpassbook_sql = "
          SELECT *, to_char((transaction_time AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as transaction_tz
          FROM root_member_gtokenpassbook
          WHERE source_transferaccount = '".$member_account."'
          AND transaction_time >= '".$last_times."' AND transaction_time < 'now()'
          AND auditmode = 'depositaudit'
          AND (summary = ' 代幣存款 ' OR summary = ' 代幣反水 ' OR summary = ' 代幣優惠 ')
          ORDER BY transaction_tz DESC;
          ";
        }else{
          $last_times = date("Y-m-d H:i:s");
          // ----------------------------------------------------
          // 搜尋會員「存款金額」與「存款稽核」範圍時間
          // ----------------------------------------------------
          $gtokenpassbook_sql = "
          SELECT *, to_char((transaction_time AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as transaction_tz
          FROM root_member_gtokenpassbook
          WHERE source_transferaccount = '".$member_account."'
          AND transaction_time < 'now()'
          AND auditmode = 'depositaudit'
          AND (summary = ' 代幣存款 ' OR summary = ' 代幣反水 ' OR summary = ' 代幣優惠 ')
          ORDER BY transaction_tz DESC;
          ";
        }
        $gtokenpassbook_result = runSQLALL($gtokenpassbook_sql);
        $gtokenpassbook_count = runSQL($gtokenpassbook_sql);
        var_dump($gtokenpassbook_result);

        for ($i = 1; $i <= $gtokenpassbook_result[0]; $i++) {
          // var_dump($gtokenpassbook_result[$i]);
          // ----------------------------------------------------
          // 存款投注量(存款后打碼)
          // ----------------------------------------------------
          $mg_result = bettingrecords_mg($mg_account, $gtokenpassbook_result[$i]->transaction_tz);
          if ($mg_result["code"] == 1) {
            $deposit_amount_html = $mg_result["TotalWager"] / 100;
            $deposit_total_html = $deposit_total_html + $deposit_amount_html;
          } else {
            $deposit_amount_html = 0;
            $deposit_total_html = 0;
          }

          // ----------------------------------------------------
          // 存款金額
          // ----------------------------------------------------
          if ($gtokenpassbook_result[$i]->deposit != 0) {
            $deposit_money_html = $gtokenpassbook_result[$i]->deposit;
            $deposit_money_total = $deposit_money_total + $deposit_money_html;
          } else {
            $deposit_money_html = 0;
            $deposit_money_total = 0;
          }
          // var_dump($deposit_money_total);
          // ----------------------------------------------------
          // 存款稽核
          // ----------------------------------------------------
          if ($deposit_amount_html > $gtokenpassbook_result[$i]->auditmodeamount) {
            $auditing_html = "(免稽核)";
            $admin_costs = "(无)";
          } else {
            $auditing_html = "(稽核)";
            $admin_costs = "$ ".$admin_cost_ratio / 100 . "%";
          }

          // var_dump($mg_result);
          // ----------------------------------------------------
          // 表格 row -- tables DATA list
          // ----------------------------------------------------
          $show_listrow_html = $show_listrow_html."
          <tr>
            <td>".$member_account."</td>
            <td>".$gtokenpassbook_result[$i]->transaction_tz."</td>
            <td>".$last_times."</td>
            <td>".$gtokenpassbook_result[$i]->summary."</td>
            <td>$ ".$deposit_amount_html."</td>
            <td>$ ".$deposit_money_html."</td>
            <td>".$auditing_html."</td>
            <td>".$admin_costs."</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
          </tr>
          ";
        }
    }

    // ----------------------------------------------------
    // 表格欄位名稱
    // ----------------------------------------------------
    $table_colname_html = '
    <tr>
      <th>會員帳戶</th>
      <th>存款时间</th>
      <th>取款時間</th>
      <th>稽核概要</th>
      <th>存款后打码</th>
      <th>存款金额</th>
      <th>存款稽核</th>
      <th>行政费用</th>
      <th>优惠金额</th>
      <th>优惠稽核</th>
      <th>优惠扣除</th>
    </tr>
    ';
    // enable sort table
    // search data
    $sorttablecss = 'id="show_list"  class="display" cellspacing="0" width="100%" ';
    // $sorttablecss = ' class="table table-striped" ';

    $show_tips_html = '<div class="alert alert-success">
    * 存款记录共 '.$gtokenpassbook_count.' 笔<br>
    * 存款稽核全数通过<br>
    * 优惠稽核全数通过<br>
    * 總打碼量:$ '.$deposit_total_html.'<br>
    * 總存款金額合計:$ '.$deposit_money_total.'<br>
    </div>';

    // 列出資料, 主表格架構
    $show_list_html = '';
    $show_list_html = $show_list_html.'
    <table '.$sorttablecss.'>
    <thead>
    '.$table_colname_html.'
    </thead>
    <tbody>
    '.$show_listrow_html.'
    </tbody>
    <tfoot>
    '.$table_colname_html.'
    </tfoot>
    </table>
    <hr>
    ';

    $date_selector_html = '
    <hr>
    <a href="./member.php" class="btn btn-primary">回会员详细资料</a>
    <button class="btn btn-danger" id="daily_statistics_report_date_query">修改稽核(未完成)</button>
    <button class="btn btn-info" id="daily_statistics_report_date_query">清除所有稽核(未完成)</button>
    <div id="preview_result"></div>
    <hr>
    <form class="form-inline" method="get">
      <div class="form-group">
        <div class="input-group">
          <div class="input-group-addon">輸入會員編號</div>
          <div class="input-group-addon">
          <input type="text" class="form-control" name="a" placeholder="輸入會員編號ex:1"></div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" id="daily_statistics_report_date_query">即時查詢</button>
    </form>
    <hr>
    ';

    // 選擇日期 html
    // $show_dateselector_html = $date_selector_html.$date_selector_js;
    $show_dateselector_html = $date_selector_html;
// -------------------------------------------------------------------------
    // 參考使用 datatables 顯示
    // https://datatables.net/examples/styling/bootstrap.html
    $extend_head = $extend_head.'
    <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
    <link rel="stylesheet" type="text/css" href="./in/datetimepicker/jquery.datetimepicker.css"/>
    <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
    <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
    <script type="text/javascript" src="./in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
    ';


    // DATA tables jquery plugging -- 要放在 head 內 不可以放 body
    $extend_head = $extend_head.'
    <script type="text/javascript" language="javascript" class="init">
      $(document).ready(function() {
        $("#show_list").DataTable( {
            "paging":   true,
            "ordering": true,
            "info":     true,
            order: [[ 0, "desc" ]]
        } );
        // datetime picker
        $("#start_datetimes, #end_datetimes").datetimepicker({
          yearOffset:0,
          lang:"ch",
          timepicker:true,
          format:"Y-m-d H:i:s"
        })

      } )
    </script>
    ';


    // 即時編輯工具 ref: https://vitalets.github.io/x-editable/docs.html#gettingstarted
    $extend_head = $extend_head.'
    <!-- x-editable (bootstrap version) -->
    <link href="in/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
    <script src="in/bootstrap3-editable/js/bootstrap-editable.min.js"></script>
    ';

  // 切成 1 欄版面
  $indexbody_content = '';
  $indexbody_content = $indexbody_content.'
  <div class="row">
    <div class="col-12 col-md-12">
      '.$show_tips_html.'
    </div>
    <div class="col-12 col-md-12">
      '.$show_dateselector_html.'
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

}else{
  // 沒有登入的顯示提示俊息
  $show_transaction_list_html  = '(x) 只有管理員或有權限的會員才可以登入觀看。';

  // 切成 1 欄版面
  $indexbody_content = '';
  $indexbody_content = $indexbody_content.'
  <div class="row">
    <div class="col-12 col-md-12">
    '.$show_transaction_list_html.'
    </div>
  </div>
  <br>
  <div class="row">
    <div id="preview_result"></div>
  </div>
  ';
}

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
