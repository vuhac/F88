<?php
// ----------------------------------------------------------------------------
// Features :    後台 -- 聯營股東損益計算
// File Name: agent_profitloss_calculation.php
// Author   :
// Related  :
// Log      :
// ----------------------------------------------------------------------------
// 對應資料表
// 相關的檔案
// 功能說明
// 1.透過每日報表資料, 計算統計出每日的個節點營利損益狀態
// 2.依據分用比例, 從上到下分配營利的盈餘, 以每日為單位。
// 3.加總指定區間的資料, 成為個節點的每日損益狀態.
// 4.每月分配股東的損益到獎金分發的表格
// update   : yyyy.mm.dd

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 此計算程式所使用的 LIB
require_once dirname(__FILE__) ."/agent_profitloss_calculation_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


// ---------------------------------------------------------------------------
// 檢查系統資料庫中 table root_statisticsbonusprofit 表格(放射線組織獎金計算-營運利潤獎金)有多少資料被生成了, 建立索引檔及提供可以更新的資訊
// 搭配 indexmenu_stats_switch 使用
// Usage: menu_profit_list_html()
// ---------------------------------------------------------------------------
function menu_profit_list_html()
{
    global $tr;

    // 列出系統資料統計月份
    $list_sql = <<<SQL
        SELECT "dailydate",
               "end_date",
               "agent_recursive_sumbets",
               "agent_recursive_sumbetsprofit",
               "agent_recursive_agent_count"
        FROM "root_commission_dailyreport"
        WHERE "member_account" = 'root'
        ORDER BY "dailydate" DESC
        LIMIT 60;
    SQL;
    $list_result = runSQLall($list_sql);
    // var_dump($list_result);

    $list_stats_data = '';
    if ($list_result[0] > 0) {
        // 把資料 dump 出來 to table
        for ($i=1; $i <= $list_result[0]; $i++) {

            // 統計區間
            if ( empty($list_result[$i]->end_date) ) {
                $date_range = $list_result[$i]->dailydate;
                $end_date = $list_result[$i]->dailydate;
            } else {
                $date_range = $list_result[$i]->dailydate . ' ~ ' . $list_result[$i]->end_date;
                $end_date = $list_result[$i]->end_date;
            }

            $get_list_url = 'agent_profitloss_calculation.php?current_datepicker_start='.$list_result[$i]->dailydate.'&current_datepicker_end='.$end_date;

            $date_range_html = '<a href="' . $get_list_url . '" title="'.$tr['Watch the specified interval'].'">'.$date_range.'</a>';
            // 資料數量
            $member_account_count_html = '<a href="' . $get_list_url . '">'.$list_result[$i]->agent_recursive_agent_count.'</a>';
            // 總投注量(娛樂城投注量)
            $sum_sum_all_bets_html = number_format($list_result[$i]->agent_recursive_sumbets, 2, '.' ,',');
            // 總損益
            $sum_sum_all_profitlost_html = number_format($list_result[$i]->agent_recursive_sumbetsprofit, 2, '.' ,',');

            // table
            $list_stats_data .= <<<HTML
                <tr>
                    <td>{$date_range_html}</td>
                    <td>'{$member_account_count_html}</td>
                    <td>{$sum_sum_all_bets_html}</td>
                    <td>{$sum_sum_all_profitlost_html}</td>
                </tr>
            HTML;
        }
    } else {
        $list_stats_data .= <<<HTML
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        HTML;
    }

    // 統計資料及索引
    $listdata_html = <<<HTML
        <table class="table table-bordered small">
            <thead>
                <tr class="active">
                    <th>{$tr['Statistical interval']}<span class="glyphicon glyphicon-time"></span>(-04)</th>
                    <th>{$tr['Number of data']}</th>
                    <th>{$tr['total betting']}</th>
                    <th>{$tr['total profit and loss']}</th>
                </tr>
            </thead>
            <tbody style="background-color:rgba(255,255,255,0.4);">{$list_stats_data}</tbody>
        </table>
    HTML;

    return($listdata_html);
}
// ---------------------------------------------------------------------------
// END -- 檢查系統資料庫中 table root_statisticsbonusprofit 表格(放射線組織獎金計算-營運利潤獎金)有多少資料被生成了, 建立索引檔及提供可以更新的資訊
// ---------------------------------------------------------------------------


// ---------------------------------------------------------------------------
// 加上 on / off開關 JS and CSS
// ---------------------------------------------------------------------------
function indexmenu_stats_switch()
{
    global $tr;

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
    <button class="btn btn-primary btn-xs" style="display: none" id="hide">'.$tr['menu off'].'</button>
    <button class="btn btn-success btn-xs" id="show">'.$tr['menu on'].'</button>
    </span>

    <div id="index_menu" style="display:block;
    background-color: #e6e9ed;
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


    return($indexmenu_stats_switch_html);
}
// ---------------------------------------------------------------------------
// 加上 on / off開關 JS and CSS   END
// ---------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數  $tr['Home'] = '首页';
// 功能標題，放在標題列及meta $tr['Agent profit and loss calculation'] = '代理商損益計算';
$function_title         = $tr['Casino Agent profitloss calculation'];
// 擴充 head 內的 css or js
$extend_head                = '';
// 放在結尾的 js
$extend_js                    = '';
// body 內的主要內容
$indexbody_content    = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['profit and promotion'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

// 有登入，且身份為管理員 R 才可以使用這個功能。
if ( isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ) {

  $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
  $current_datepicker = date_format($date, 'Y-m-d');
  $current_date_m = date("m", strtotime( "$current_datepicker"));
  $current_date_Y = date("Y", strtotime( "$current_datepicker"));

  $current_date_d = date("d",strtotime("$current_datepicker"));

  $current_datepicker_start = $current_date_Y.'-'.$current_date_m.'-01';
  // $current_datepicker_end = $current_date_Y.'-'.$current_date_m.'-28';

  $current_datepicker_end = $current_date_Y.'-'.$current_date_m.'-'.$current_date_d;


  // 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
  if(isset($_GET['current_datepicker_start'])) {
    // 判斷格式資料是否正確
    if(validateDate($_GET['current_datepicker_start'], 'Y-m-d')) {
      $current_datepicker_start = $_GET['current_datepicker_start'];
      $current_datepicker_end = $_GET['current_datepicker_end'];
    }
  }

  $filter_empty = ( ( isset($_GET['filter_empty']) && ($_GET['filter_empty'] == "true") ) ? true : false );


  $show_tips_html = '<div class="alert alert-info">
    <p>* '.$tr['The current date of the query is'].' '.$current_datepicker_start.' ~ '.$current_datepicker_end.$tr['The bonus report is for U.S. East Time (UTC -04) and the daily settlement time range is'].' '.$current_datepicker_end.' 00:00:00 ~ '.$current_datepicker_end.' 23:59:59 </p>
    </div>';

  $filter_empty_checkbox_checked = ( ($filter_empty) ? 'checked' : '' );


  // 20200505
  $show_dateselector_html =<<<HTML
    <div class="form-inline">
      <div class="btn-group mr-2 mb-2">
        <button type="button" class="btn btn-secondary" onclick="settimerange('thisweek')">{$tr['This week']}</button>
        <button type="button" class="btn btn-secondary" onclick="settimerange('thismonth')">{$tr['this month']}</button>
        <button type="button" class="btn btn-secondary" onclick="settimerange('today')">{$tr['Today']}</button>
        <button type="button" class="btn btn-secondary" onclick="settimerange('yesterday')">{$tr['yesterday']}</button>
        <button type="button" class="btn btn-secondary" onclick="settimerange('lastmonth')">{$tr['last month']}</button>
      </div>

      <div class="form-group">
        <div class="input-group mr-2 mb-2">
          <div class="input-group-addon">{$tr['specified trial interval']}</div>
          <input type="text" class="form-control" placeholder="{$tr['Starting time']}" aria-describedby="basic-addon1" id="register_date_start_time" value="{$current_datepicker_start}">
          <span class="input-group-addon" id="basic-addon1">~</span>
          <input type="text" class="form-control" placeholder="{$tr['End time']}" aria-describedby="basic-addon1" id="register_date_end_time" value="{$current_datepicker_end}">
        </div>
      </div>

      <div class="checkbox">
        <label>
          <input type="checkbox" id="filter_empty" name="filter_empty" {$filter_empty_checkbox_checked}>{$tr['to filter null']}
        </label>
      </div>

      <button class="btn btn-primary mx-2" onclick="gotoindex();">{$tr['trial calculation']}</button>
      <button class="btn btn-primary" onclick="download_csv();">{$tr['Export Excel']}</button>
    </div>
  <hr>
HTML;


  // ref. doc: http://xdsoft.net/jqplugins/datetimepicker/
  // 取得日期的 jquery datetime picker -- for birthday
  $extend_head = $extend_head . '<link rel="stylesheet" type="text/css" href="in/datetimepicker/jquery.datetimepicker.css"/>';
  $extend_js = $extend_js . '<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>';

  // date 選擇器 https://jqueryui.com/datepicker/
  // http://api.jqueryui.com/datepicker/
  // 14 - 100 歲為年齡範圍， 25-55 為主流客戶。
  $dateyearrange_start = date("Y") - 100;
  $dateyearrange_end = date("Y/m/d");
  $datedefauleyear = date("Y");


  $extend_js = $extend_js . "
  <script>
  function gotoindex() {
    var datepicker_start = $(\"#register_date_start_time\").val();
    var datepicker_end = $(\"#register_date_end_time\").val();
    var filter_empty = 'false';
    if ($('#filter_empty').is(':checked')) {
      filter_empty = 'true';
    }

    var goto_url = '".$_SERVER['PHP_SELF']."?current_datepicker_start=' + datepicker_start + '&current_datepicker_end=' + datepicker_end + '&filter_empty=' + filter_empty;
    var goto_url = location.protocol + '//' + location.host + goto_url;
    location.href = goto_url;
  }

  function download_csv() {
    var datepicker_start = $(\"#register_date_start_time\").val();
    var datepicker_end = $(\"#register_date_end_time\").val();
    var filter_empty = 'false';
    if ($('#filter_empty').is(':checked')) {
      filter_empty = 'true';
    }

    var goto_url = 'agent_profitloss_calculation_action.php?a=download_csv&current_datepicker_start=' + datepicker_start + '&current_datepicker_end=' + datepicker_end + '&filter_empty=' + filter_empty;
    location.href = goto_url;
  }

  // for select day
  $('#register_date_start_time').datetimepicker({
      defaultDate:'" . $datedefauleyear . "-01-01',
      minDate: '" . $dateyearrange_start . "-01-01',
    maxDate: '" . $dateyearrange_end . "',
    timepicker:false,
      format:'Y-m-d',
      lang:'en'
  });

  $('#register_date_end_time').datetimepicker({
      defaultDate:'" . $datedefauleyear . "-01-01',
      minDate: '" . $dateyearrange_start . "-01-01',
    maxDate: '" . $dateyearrange_end . "',
    timepicker:false,
      format:'Y-m-d',
      lang:'en'
  });

  function getnowtime(){
    var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD HH:mm');

    return NowDate;
  }

  // 本日、昨日、本周、上周、上個月button
  function settimerange(alias) {
    _time = utils.getTime(alias);
    $('#register_date_start_time').val(_time.start);
    $('#register_date_end_time').val(_time.end);
  }

  function get_audit_calculate_type() {
    var radios = document.getElementsByName('audit_calculate_type');

    for (var i = 0, length = radios.length; i < length; i++){
     if (radios[i].checked){
       return radios[i].value;
     }
    }

    return '';
  }

  function radio_check(){
    var radios = document.getElementsByName('audit_calculate_type');
    var audit_calculate_type = get_audit_calculate_type();
    var audit_type = $(\"#audit_type\").val();

    switch (audit_calculate_type) {
      case 'audit_amount':
        $(\"#audit_amount\").prop('disabled', false);
        $(\"#audit_ratio\").prop('disabled', true);
        $(\"#audit_ratio\").prop('value', '0');
        break;

      case 'audit_ratio':
        $(\"#audit_amount\").prop('disabled', true);
        $(\"#audit_amount\").prop('value', '0');
        $(\"#audit_ratio\").prop('disabled', false);
        break;
    }

    if(audit_type == 'freeaudit') {
      $(\"#audit_amount\").prop('disabled', true);
      $(\"#audit_ratio\").prop('disabled', true);
      $(\"#audit_amount\").prop('value', '0');
      $(\"#audit_ratio\").prop('value', '0');
    } else {
      $(\"#audit_amount\").prop('disabled', false);
      $(\"#audit_ratio\").prop('disabled', false);
    }
  }

  function auditsetting(){
    var bonustype = $(\"#bonus_type\").val();

    if(bonustype == ''){
      $(\"#payout_btn\").prop('disabled', true);
    }else{
      if(bonustype == 'token'){
        $(\"#audit_type\").prop('disabled', false);
        $(\"[name=audit_calculate_type]\").prop('disabled', false);

        radio_check();
      }else{
        $(\"#audit_type\").prop('disabled', true);
        $(\"#audit_amount\").prop('disabled', true);
        $(\"#audit_ratio\").prop('disabled', true);
        $(\"#audit_amount\").prop('value', '0');
        $(\"#audit_ratio\").prop('value', '0');

        $(\"[name=audit_calculate_type]\").prop('disabled', true);
      }
      $(\"#payout_btn\").prop('disabled', false);
    }
  }

  function batchpayout_html(){ // 開啟時間區間選擇
    $.blockUI(
    {
      message: $('#batchpayout'),
      css: {
       padding: 0,
       margin: 0,
       width: '800px',
       top: '30%',
       left: '25%',
       border: 'none',
       cursor: 'auto'
      }
    });
  }

  function batchpayoutpage_close(){ // 關閉時間區間選擇
    $.unblockUI();
  }

  function batchpayout(){
    $('#payout_btn').prop('disabled', true);

    var show_text = '即将发放 " . $current_datepicker_start . " ~ " . $current_datepicker_end . " 的佣金记录...';
    var payout_status = $('#bonus_defstatus').val();
    var bonus_type = $('#bonus_type').val();
    var audit_type = $('#audit_type').val();
    var audit_amount = $('#audit_amount').val();
    var audit_ratio = $('#audit_ratio').val();
    var audit_calculate_type = get_audit_calculate_type();
    var updatingcodeurl='agent_profitloss_calculation_action.php?a=profitloss_payout&payout_date=" . $current_datepicker_start . "&payout_end_date=" . $current_datepicker_end . "&s='+payout_status+'&s1='+bonus_type+'&s2='+audit_type+'&s3='+audit_amount+'&s4='+audit_ratio+'&s5='+audit_calculate_type;

    // console.log(audit_type);

    if(bonus_type == 'token' && audit_type == 'none'){
      alert('{$tr['Please choose the bonus audit method!']}');
      $('#payout_btn').prop('disabled', false);
    }else{
      if(confirm(show_text)){
        $.unblockUI();
        $('#batchpayout_html_btn').prop('disabled', true);
        myWindow = window.open(updatingcodeurl, 'gpk_window', 'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800', false);
            myWindow.focus();
      }else{
        $('#payout_btn').prop('disabled', false);
      }
    }
  }


  </script>
  ";

  $show_datainfo_html = '<div class="alert alert-success">
    * ' . $tr['Casino Agent profitloss calculation'] . ', '.$tr['calculationformula'] .'：<br>
    '.$tr['Time interval: according to the specified query interval'].' <br>
    '.$tr['Agent profit and loss calcu'].' <br>
    '.$tr['Platform cost calcu'].'<a href="commission_setting.php"> '.$tr['Agent level'].'</a> '.$tr['setting'].') <br>
    '.$tr['Marketing cost calcu'].'<a href="commission_setting.php"> '.$tr['Agent level'].'</a> '.$tr['setting'].') <br><br>
    * '.$tr['Sub-commission ratio setting reference Agent organization transfer and sub-commission setting'].'<br>
    * '.$tr['If the profit and loss of the agent'].'<br>
    </div>
    ';

    // $show_datainfo_html = '<div class="alert alert-success">
    // * ' . $tr['Casino Agent profitloss calculation'] . ', '.$tr['calculationformula'] .'：<br>
    // 时间区间：依照指定查询区间 <br>
    // 代理商损益 = (一级代理之下所有会员娱乐城损益 - 平台成本 - 行销成本) * (分佣比例) <br>
    // 平台成本 = (娱乐城损益 * 平台成本比例) (平台成本比例: 依照<a href="commission_setting.php">代理商分佣等级</a>之设定) <br>
    // 行销成本 = (优惠金额 + 反水金额) * (承担比例) (承担比例: 依照<a href="commission_setting.php">代理商分佣等级</a>之设定) <br><br>
    // * 分佣比例設定參考 代理商组织转帐及分佣设定 生成。<br>
    // * 如果代理商损益经过分佣计算后，为负值，则累积到下期分润盈余扣除上次留底后为正值后发放，如为负值则继续累计<br>
    // </div>
    // ';

  // ---------------------------------------------------------------------------
  // 生成左邊的報表 list index
  // ---------------------------------------------------------------------------
  $indexmenu_stats_switch_html = indexmenu_stats_switch();

  // ---------------------------------------------------------------------------
  // 指定查詢月份的統計結算列表 -- 摘要
  // ---------------------------------------------------------------------------

  // 娛樂城佣金計算變數
  $id = $tr['ID'];
  $account = $tr['Account'];
  $identity = $tr['identity'];
  $effevtive_members = $tr['numbers of effective members'];
  $effective_bet_amount = $tr['effective bet amount'];
  $profit_and_loss = $tr['profit and loss'];
  $commission_of_agent = $tr['commission of agent'];
  $note = $tr['note'];

  // 表格欄位名稱
  $table_colname_html = <<<HTML
  <tr>
    <th>$id</th>
    <th>$account</th>
    <th>$identity</th>
    <th>{$tr['Direct Supervisor']}</th>
    <!-- <th>所属总代</th> -->
    <th>$effevtive_members</th>
    <th>$effective_bet_amount</th>
    <th>$profit_and_loss</th>
    <th>$commission_of_agent</th>
    <th>$note</th>
    <th></th>
  </tr>
HTML;

  // var_dump($b);


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
  $extend_head .= '
    <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
    <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
    <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
    ';
    // DATA tables jquery plugging -- 要放在 head 內 不可以放 body
  $extend_head .= <<<HTML
    <script type="text/javascript" language="javascript" class="init">
    var utils = {
      getTime: function (alias) {
        var timezone = "America/St_Thomas";
        var _now = moment().tz(timezone);
        var _moment = _now.clone();
        var scheme = "YYYY-MM-DD";
        var start, end;

        var week_today = _moment.format(scheme);

        switch (alias) {
          case "now":
            scheme = "YYYY-MM-DD HH:mm:ss";
            start = _moment.format(scheme);
            end = _moment.format(scheme);
            break;
          case "today":
            start = _moment.format(scheme);
            end = _moment.format(scheme);
            break;
          case "yesterday":
            _moment.add(-1, "d");
            start = _moment.format(scheme);
            end = _moment.format(scheme);
            break;
          case "thisweek":
            start = _moment.day(0).format(scheme);
            end = week_today;// 到今天

            // start = _moment.day(0).format(scheme);
            // end = _moment.day(6).format(scheme);
            break;
          case "thismonth":
            start = _moment.date(1).format(scheme);
            end = week_today;

            // end = _moment.add(1, "M").add(-1, "d").format(scheme);
            break;
          case "lastmonth":
            end = _moment.date(1).add(-1, "d").format(scheme);
            start = _moment.date(1).format(scheme);
            break;
          default:
        }
        return {
          _now,
          start,
          end,
          breakpoint: _now.format(scheme),
        };
      },
    };

      function summaryTmpl(data) {
        return `
        <hr>
        <table class="table table-bordered small">
          <thead>
            <tr class="active">
              <th>{$tr['Statistical interval']}</th>
              <th>{$tr['Number of data']}</th>
              <th>{$tr['numbers of suppliers']}</th>
              <th>{$tr['total member']}</th>

              <th>{$tr['total betting']}</th>
              <th>{$tr['profit and loss of casino']}</th>
              <th>{$tr['profit and loss of casino total betting']}</th>



              <th>{$tr['total commission(only for positive)']}</th>
              <th>{$tr['total accumulated to the next sub-commission (negative value)']}</th>
              <th>{$tr['total commission (positive + negative)']}</th>
              <th>{$tr['total commission (positive + negative) / total bet amount (%)']}</th>
            </tr>
          </thead>
          <tbody style="background-color:rgba(255,255,255,0.4);">
            <tr>
              <td>\${data.current_daterange_html}</td>
              <td>\${data.agent_count_html}</td>
              <td>\${data.agent_count_html}</td>
              <td>\${data.member_count_html}</td>
              <td>\${data.sum_sum_all_bets_html}</td>
              <td>\${data.sum_all_profitlost_html}</td>
              <td>\${data.sum_all_profitlost_ratio_html}</td>
              <td>\${data.sum_member_profitamount_pos_html}</td>
              <td>\${data.sum_member_profitamount_negitive_html}</td>
              <td>\${data.sum_member_profitamount_html}</td>
              <td>\${data.sum_member_profit_radio_html}</td>
            </tr>
          </tbody>
        </table>


        <hr>
        <table class="table table-bordered small">
          <thead>
            <tr class="active">

              <th>{$tr['profit and loss of casino']}</th>
              <th>{$tr['profit of administrator']}({$tr['profit of administrator']}/{$tr['total profit and loss']})</th>
              <th>{$tr['Agent sub-commission total']}(分佣合计/{$tr['profit and loss of casino']})</th>

            </tr>
          </thead>
          <tbody style="background-color:rgba(255,255,255,0.4);">
          <tr>
            <td>\${data.sum_all_profitlost_html} (100 %) </td>
            <td>\${data.root_commission_html} ( \${data.root_commission_ratio} ) </td>
            <td>\${data.sum_member_profitamount_html} (  \${data.agent_commission_ratio} )
            <button id="batchpayout_html_btn" class="btn btn-info float-right" onclick="batchpayout_html();">{$tr['batch sending']}</button> </td>
          </tr>
          </tbody>
        </table>
        </hr>
        `
      }

      function batchpayoutTmpl(data) {
        return `
        <table class="table table-bordered">
          <thead>
            <tr bgcolor="#e6e9ed">
              <th>{$tr['date']}</th>
              <th>{$tr['number of sent']}</th>
              <th>{$tr['estimated of amount of commission']}</th>
              <th>{$tr['Bonus category']}</th>
              <th>{$tr['sending method']}</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>\${data.current_daterange_html}</td>
              <td>\${data.agent_count_html}</td>
              <td>\${data.sum_member_profitamount_pos_html}</td>
              <td><select class="form-control" name="bonus_type" id="bonus_type"  onchange="auditsetting();"><option value="">--</option><option value="token">{$tr['Gtoken']}</option><option value="cash">{$tr['Franchise']}</option></select></td>
              <td><select class="form-control" name="bonus_defstatus" id="bonus_defstatus" ><option value="0">{$tr['Cancel']}</option><option value="1">{$tr['Can receive']}</option><option value="2" selected>{$tr['time out']}</option></select></td>
            </tr>
            <tr>
              <th bgcolor="#e6e9ed"><center>{$tr['Audit method']}</center></th>
              <td><select class="form-control" name="audit_type" id="audit_type" onchange="radio_check();" disabled><option value="none" selected="">--</option><option value="freeaudit">{$tr['freeaudit']}</option><option value="depositaudit">{$tr['Deposit audit']}</option><option value="shippingaudit">{$tr['Preferential deposit audit']}</option></select></td>
              <th bgcolor="#e6e9ed"><center><input type="radio" name="audit_calculate_type" value="audit_amount" onchange="radio_check();" checked>{$tr['audit amount']}</center></th>
              <td><input class="form-control" name="audit_amount" id="audit_amount" value="0" placeholder="{$tr['audit amount ex']}" disabled></td>
              <td></td>
            </tr>
            <tr>
              <td></td>
              <td></td>
              <th bgcolor="#e6e9ed"><center><input type="radio" name="audit_calculate_type" value="audit_ratio" onchange="radio_check();">{$tr['audit multiple']}</center></th>
              <td><input class="form-control" name="audit_ratio" id="audit_ratio" value="0" placeholder="{$tr['audit multiple ex']}" disabled></td>
              <td><button id="payout_btn" class="btn btn-info" onclick="batchpayout();" disabled>{$tr['send']}</button>
                  <button class="btn btn-warning" onclick="batchpayoutpage_close();">{$tr['Cancel']}</button></td>
            </tr>
          </tbody>
        </table>
        `
      }

      $(document).ready(function() {

        $("#show_list").DataTable( {
            "bProcessing": true,
            "bServerSide": true,
            "bRetrieve": true,
            "searching": false,
            "oLanguage": {
              "sSearch": "{$tr['Account']}",//"会员帐号:",
              "sEmptyTable": "{$tr['no data'] }",//,"目前没有资料!",
              "sLengthMenu": "{$tr['each page']} _MENU_ {$tr['Count']}",//,"每页显示 _MENU_ 笔",
              "sZeroRecords": "{$tr['no data'] }",//,"目前没有资料!",
              "sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}",//"目前在第 _PAGE_ 页，共 _PAGES_ 页",
              "sInfoEmpty": "{$tr['no data'] }",//"目前没有资料",
              "sInfoFiltered": "({$tr['from']} _MAX_ {$tr['filtering in data']})"//"(从 _MAX_ 笔资料中过滤)"
            },
            "ajax": {
              "url": "agent_profitloss_calculation_action.php?a=reload_profitlist&current_datepicker_start=$current_datepicker_start&current_datepicker_end=$current_datepicker_end&filter_empty=$filter_empty",
              "dataSrc": function(json) {
                if(json.data.list.length > 0) {
                  $('#summary_report_html').html(summaryTmpl(json.data.summary));
                  $('#batchpayout').html(batchpayoutTmpl(json.data.summary));
                }
                return json.data.list;
              }
            },
            "columns": [
              { "data": "member_id", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                  $(nTd).html("<a href=\'member_treemap.php?id="+oData.member_id+"\' target=\"_BLANK\" title=\"{$tr['Members of the organizational structure of the state']}\">"+oData.member_id+"</a>");
                }
              },
              { "data": "member_account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                  $(nTd).html("<a href=\'member_account.php?a="+oData.member_id+"\' target=\"_BLANK\" title=\"{$tr['Inspection details']}\">"+oData.member_account+"</a>");
                }
              },
              { "data": "member_therole", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                  $(nTd).html("<a href=\'#\' title=\"{$tr['Membership R=Administrator A=Agent M=Member']}\">"+oData.member_therole+"</a>");
                }
              },
              { "data": "parent_account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                  if(oData.parent_account =='-') {
                    $(nTd).html(oData.parent_account);
                  } else {
                    $(nTd).html("<a href=\'member_account.php?a="+oData.member_parent_id+"\' target=\"_BLANK\" title=\"{$tr['Inspection details']}\">"+oData.parent_account+"</a>");
                  }

                }
              },
              // { "data": "first_agent", "searchable": false, "orderable": true },
              { "data": "agent_valid_member_recursives_count", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "agent_recursive_sumbets", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "agent_recursive_sumbetsprofit", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "agent_commission", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "note", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "agent_commission", "searchable": false, "orderable": true, className: "dt-right", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                  $(nTd).html("<a class=\"btn btn-sm btn-primary\" href=\'"+oData.detail_url+"\' target=\"_BLANK\" title=\"{$tr['Details']}\">{$tr['Details']}</a>");
                }
              }

            ]
        } );

      } )
    </script>
HTML;
  // -------------------------------------------------------------------------
  // sorttable 的 jquery and plug info END
  // -------------------------------------------------------------------------



  // -------------------------------------------------------------------------
  // 切成 1 欄版面的排版
  // -------------------------------------------------------------------------
  $indexbody_content = '';
  $indexbody_content = $indexbody_content.'
      <div class="row">

      <div class="col-xs-12">
      '.$indexmenu_stats_switch_html.'
      '.$show_tips_html.'
      </div>

      <div class="col-xs-12">
      '.$show_dateselector_html.'
      </div>

      <div class="col-xs-12">
      '.$show_datainfo_html.'
      </div>

      <div id="summary_report_html" class="col-xs-12">
      </div>

        <div class="col-xs-12">
        '.$show_list_html.'
        </div>
      </div>
      </div>
      <br>
        <div class="row">
          <div id="preview_result"></div>
      </div>
      <div style="display: none;width: 800px;" id="batchpayout"></div';
  // -------------------------------------------------------------------------


}else{
    // 沒有登入的顯示提示俊息
    $show_html  = '(x) 只有管​​理员或有权限的会员才可以登入观看。';

    // 切成 1 欄版面
    $indexbody_content = '';
    $indexbody_content = $indexbody_content.'
    <div class="row">
      <div class="col-xs-12 col-md-12">
      '.$show_html.'
      </div>
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
$tmpl['html_meta_description']         = $tr['host_descript'];
$tmpl['html_meta_author']                     = $tr['host_author'];
$tmpl['html_meta_title']                     = $function_title . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title']                                = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']                            = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']                                = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content']             = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']                = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/beadmin.tmpl.php");
include("template/beadmin_fluid.tmpl.php");

?>