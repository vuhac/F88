<?php
// ----------------------------------------------------------------------------
// Features :	後台 -- 娛樂城存款投注佣金計算
// File Name: agent_depositbet_calculation.php
// Author   : yaoyuan
// Related  :root_statisticsdailyreport
// Log      :
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 此計算程式所使用的 LIB
require_once dirname(__FILE__) ."/agent_depositbet_calculation_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// 計算佣金區間，開始、結束時間
$sdate = $edate = '';
if (isset($_GET['sdate']) and $_GET['sdate'] != null) {
    if (validateDate($_GET['sdate'], 'Y-m-d')) {
        $sdate = $_GET['sdate'];
    }
}
if (isset($_GET['edate']) and $_GET['edate'] != null) {
    if (validateDate($_GET['edate'], 'Y-m-d')) {
        $edate = $_GET['edate'];
    }
}


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數  $tr['Home'] = '首页';
// 功能標題，放在標題列及meta $tr['Agent profit and loss calculation'] = '代理商損益計算';
$function_title 		= $tr['Deposit betting commission calculation'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['profit and promotion'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

// 會員端 存款投注
$result_depositbet = get_protalsetting();

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {

  $current_datepicker =  gmdate('Y-m-d H:i:s',time() + -4*3600);
  $current_datepicker_start = date("Y-m", strtotime( "$current_datepicker")).'-01';
  // $current_datepicker_end = date("Y-m-d", strtotime( "$current_datepicker -1 day"));

  $current_datepicker_end = date("Y-m-d",time() + -4*3600); 


  if($edate>$current_datepicker_end){
    $edate=$current_datepicker_end;
  }


  $current_date = gmdate('Y-m-d',time() + -4*3600); // 今天
  $default_min_date = gmdate('Y-m-d',strtotime('- 2 month')); // 2個月

  $show_tips_html = '
    <div class="alert alert-info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
      '.$tr['Deposit betting commission calculation formula'].'
        <ul>
          <li>'.$tr['Rebate available'].'</li>
          <li>'.$tr['Each category bet amount'].'</li>
          <li>'.$tr['Deposit amount = total of all valid member deposits in the down line'].'</li>
        </ul>
    </div>';



  // $show_dateselector_html = '
  // <form action="agent_depositbet_calculation_action.php" method="post">

  //   <div class="form-inline">
  //     <div class="form-group">
  //       <div class="input-group mr-2 mb-2">
  //         <div class="input-group-addon">' .$tr['specified trial interval'] . '</div>
  //         <input type="text" name="sdate" class="form-control" placeholder=' . $tr['Starting time'] . ' aria-describedby="basic-addon1" id="register_date_start_time" value="'.$sdate.'">
  //         <span class="input-group-addon" id="basic-addon1">~</span>
  //         <input type="text" name="edate" class="form-control" placeholder=' . $tr['End time'] . ' aria-describedby="basic-addon1" id="register_date_end_time" value="'.$edate.'">
  //       </div>
  //     </div>
  //     <button type="button" class="btn btn-primary mx-2" onclick="gotoindex();">'.$tr['Inquiry'].'</button>
  //     <input type="hidden" name="a"     value="update_data">
  //     <button type="submit" class="btn btn-primary mx-2">'.$tr['update'].'</button>
  //     <div id="csv_dl"></div><div id="batchpayout_div_id"></div>
  //   </div>
  // </form>
  
  // <hr>';

  $show_dateselector_html =<<<HTML
  <form action="agent_depositbet_calculation_action.php" method="post">

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
          <input type="text" name="sdate" class="form-control" placeholder="{$tr['Starting time']}" aria-describedby="basic-addon1" id="register_date_start_time" value="{$sdate}">
          <span class="input-group-addon" id="basic-addon1">~</span>
          <input type="text" name="edate" class="form-control" placeholder="{$tr['End time']}" aria-describedby="basic-addon1" id="register_date_end_time" value="{$edate}">
        </div>
      </div>
      <button type="button" class="btn btn-primary mx-2" onclick="gotoindex();">{$tr['Inquiry']}</button>
      <input type="hidden" name="a"     value="update_data">
      <button type="submit" class="btn btn-primary mx-2">{$tr['update']}</button>
      <div id="csv_dl"></div><div id="batchpayout_div_id"></div>
    </div>
  </form>
  
  <hr>
HTML;

  // <button class="btn btn-primary" onclick="download_csv();">' .$tr['download csv'] .'</button>
  // ref. doc: http://xdsoft.net/jqplugins/datetimepicker/
  // 取得日期的 jquery datetime picker -- for birthday
  $extend_head .= <<<HTML
    <link rel="stylesheet" type="text/css" href="in/datetimepicker/jquery.datetimepicker.css"/>
HTML;

  $extend_js .= <<<HTML
    <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
HTML;

  // date 選擇器 https://jqueryui.com/datepicker/
  // http://api.jqueryui.com/datepicker/
  // minDate: '{$dateyearrange_start}-01-01',

  $extend_js .= <<<HTML
    <script>
    function get_parameter(){
      var datepicker_start = $("#register_date_start_time").val();
      var datepicker_end = $("#register_date_end_time").val();
      var url='sdate='+datepicker_start+'&edate='+ datepicker_end ;
      return url;
    }

    function gotoindex() {
      var datepicker_start = $("#register_date_start_time").val();
      var datepicker_end = $("#register_date_end_time").val();

      var start = new Date(datepicker_start.replace(/\-/g, "/"));
      var end = new Date(datepicker_end.replace(/\-/g, "/"));

      var today = new Date();
      // 最小搜尋時間
      var minDateTime = today.getFullYear()+'-'+(today.getMonth()-1)+'-'+today.getDate()+ ' ' ;//+ today.getHours()+':'+today.getMinutes();//+'00:00';
      // 最大搜尋時間
      var maxDateTime = today.getFullYear()+'-'+(today.getMonth()+1)+'-'+today.getDate()+ ' ' ;//+ today.getHours()+':'+today.getMinutes();

      // 開始時間<最小搜尋時間
      if((Date.parse(start)).valueOf() < (Date.parse(minDateTime)).valueOf()){
        alert('开始时间错误，请修改查询区间!');
        window.location.reload();
        return false;
      }
      // 開始時間>最大搜尋時間
      if((Date.parse(start)).valueOf() > (Date.parse(maxDateTime)).valueOf()){
        alert('时间错误，请修改查询区间!');
        window.location.reload();
        return false;
      }

      if((Date.parse(end)).valueOf() < (Date.parse(start)).valueOf()){
        alert('结束时间错误，请修改查询区间!');
        window.location.reload();
        return false;
      }
      if((Date.parse(start)).valueOf() > (Date.parse(end)).valueOf()){
        alert('开始时间错误，请修改查询区间!');
        window.location.reload();
        return false;
      }

      var purl=get_parameter();
      var goto_url = '{$_SERVER['PHP_SELF']}?'+purl;
      var goto_url = location.protocol + '//' + location.host + goto_url;
  
      location.href = goto_url;
    }

    function download_csv() {
        var purl=get_parameter();
        var goto_url = 'agent_depositbet_calculation_action.php?a=download_csv&'+purl;
        location.href = goto_url;
        // console.log(goto_url);
    }

    // for select day
    $('#register_date_start_time').datetimepicker({
      defaultDate: '{$current_datepicker_start}',
      minDate: '{$default_min_date}',
      maxDate: '{$current_datepicker_end}',
      timepicker: false,
      // defaultTime: '00:00', 
      format: 'Y-m-d',
      lang: 'en'
    });
    $('#register_date_end_time').datetimepicker({
      defaultDate:'{$current_date}',
      minDate: '{$default_min_date}',
      maxDate: '{$current_datepicker_end}',
      timepicker: false,
      // defaultTime: '23:59',
      format: 'Y-m-d',
      lang: 'en'
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
        var audit_type = $("#audit_type").val();

        switch (audit_calculate_type) {
          case 'audit_amount':
            $("#audit_amount").prop('disabled', false);
            $("#audit_ratio").prop('disabled', true);
            $("#audit_ratio").prop('value', '0');
            break;

          case 'audit_ratio':
            $("#audit_amount").prop('disabled', true);
            $("#audit_amount").prop('value', '0');
            $("#audit_ratio").prop('disabled', false);
            break;
        }

        if(audit_type == 'freeaudit') {
          $("#audit_amount").prop('disabled', true);
          $("#audit_ratio").prop('disabled', true);
          $("#audit_amount").prop('value', '0');
          $("#audit_ratio").prop('value', '0');
        } else {
          $("#audit_amount").prop('disabled', false);
          $("#audit_ratio").prop('disabled', false);
        }
    }

    function auditsetting(){
        var bonustype = $("#bonus_type").val();

        if(bonustype == ''){
          $("#payout_btn").prop('disabled', true);
        }else{
          if(bonustype == 'token'){
            $("#audit_type").prop('disabled', false);
            $("[name=audit_calculate_type]").prop('disabled', false);

            radio_check();
          }else{
            $("#audit_type").prop('disabled', true);
            $("#audit_amount").prop('disabled', true);
            $("#audit_ratio").prop('disabled', true);
            $("#audit_amount").prop('value', '0');
            $("#audit_ratio").prop('value', '0');

            $("[name=audit_calculate_type]").prop('disabled', true);
          }
          $("#payout_btn").prop('disabled', false);
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

      var show_text = '即将发放 {$sdate} ~ {$edate} 的佣金记录...';

      // 獎金類別 s1
      var bonus_type = $('#bonus_type').val();

      // 發送方式 s
      var payout_status = $('#bonus_defstatus').val();

      // 稽核方式 s2
      var audit_type = $('#audit_type').val();

      // 稽核種類： s5 audit_amount稽核金额  audit_ratio稽核倍数
      var audit_calculate_type = get_audit_calculate_type();

      // 稽核金额 s3
      var audit_amount = $('#audit_amount').val();

      // 稽核倍数 s4
      var audit_ratio = $('#audit_ratio').val();
      audit_ratio = Math.round(audit_ratio*100)/100 // 四捨五入到第二位

      // itransaction 
      var itransaction = $('#itransaction').val();
      // console.log(itransaction);

      var updatingcodeurl='agent_depositbet_calculation_action.php?a=profitloss_payout&payout_date={$sdate}&payout_end_date={$edate}&s='+payout_status+'&s1='+bonus_type+'&s2='+audit_type+'&s3='+audit_amount+'&s4='+audit_ratio+'&s5='+audit_calculate_type+'&transaction_id='+itransaction;

      // console.log(updatingcodeurl);
      if(bonus_type == 'token' && audit_type == 'none'){
        alert('{$tr['please select audit of payout method']}');
        // alert('请选择奖金的稽核方式！');
        $('#payout_btn').prop('disabled', false);
      } else if(audit_amount<0){
        alert('{$tr['audit amount']}{$tr['cannot be negative']}')
        $('#payout_btn').prop('disabled', false);
      } else if(audit_ratio<0){
        alert('{$tr['audit multiple']}{$tr['cannot be negative']}')
        $('#payout_btn').prop('disabled', false);
      } else{
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
HTML;

  // ---------------------------------------------------------------------------
  // 生成左邊的報表 list index
  // ---------------------------------------------------------------------------
  $indexmenu_stats_switch_html = indexmenu_stats_switch();

  // ---------------------------------------------------------------------------
  // 指定查詢月份的統計結算列表 -- 摘要
  // ---------------------------------------------------------------------------

  // 表格欄位名稱
  $table_colname_html = <<<HTML
  <tr>
    <th>#</th>
    <th>{$tr['The Role']}</th>
    <th>{$tr['Account']}</th>
    <th>{$tr['commissions']}</th>
    <th>{$tr['valid member']}</th>
    <th>{$tr['effective_member_achievement']}</th>
    <th>{$tr['total betting']}</th>
    <th>{$tr['downline_total_bet_reached']}</th>
    <th>{$tr['profit and loss']}</th>
    <th>{$tr['download detail']}</th>
  </tr>
HTML;

  // var_dump($b);


  // 列出資料, 主表格架構
  $show_list_html = '';
  // 列表
  $show_list_html = $show_list_html.'
    <table id="show_list" class="display" cellspacing="0" width="100%">
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
    <style>
      ul {margin-left: 40px; padding-left: 0;}
      .glyphicon-info-sign {margin-right:0.5em;}
    </style>
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
     
      function batchpayoutTmpl(data) {
        // console.log(data);
        if(data.is_payout=='1'){
          var payout_dis= `<button id="payout_btn" class="btn btn-info" title='已批次发送至彩金池，如需再次发送，請回主畫面按更新钮!'                       disabled>发送</button>`;
        }else{
          var payout_dis=`<button id="payout_btn"  class="btn btn-info" onclick="batchpayout();" disabled>发送</button>`;
        }
        // console.log(payout_dis);
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
              <td>\${data.sum_commission_html}</td>
              <td><select class="form-control" name="bonus_type" id="bonus_type"  onchange="auditsetting();"><option value="">--</option><option value="token">{$tr['Gtoken']}</option><option value="cash">{$tr['Franchise']}</option></select></td>
              <td><select class="form-control" name="bonus_defstatus" id="bonus_defstatus" ><option value="0">{$tr['Cancel']}</option><option value="1">{$tr['Can receive']}</option><option value="2" selected>{$tr['time out']}</option></select></td>
            </tr>
            <tr>
              <th bgcolor="#e6e9ed"><center>{$tr['Audit method']}</center></th>
              <td><select class="form-control" name="audit_type" id="audit_type" onchange="radio_check();" disabled><option value="none" selected="">--</option><option value="freeaudit">{$tr['freeaudit']}</option><option value="depositaudit">{$tr['Deposit audit']}</option><option value="shippingaudit">{$tr['Preferential deposit audit']}</option></select></td>
              <th bgcolor="#e6e9ed"><center><input type="radio" name="audit_calculate_type" value="audit_amount" onchange="radio_check();" checked>{$tr['audit amount']}</center></th>
              <td><input class="form-control" name="audit_amount" id="audit_amount" value="0" placeholder="{$tr['audit amount ex']}" disabled></td>
              <td><input type="hidden" id="itransaction" value="\${data.transaction_id}"</td>
            </tr>
            <tr>
              <td></td>
              <td></td>
              <th bgcolor="#e6e9ed"><center><input type="radio" name="audit_calculate_type" value="audit_ratio" onchange="radio_check();">{$tr['audit multiple']}</center></th>
              <td><input class="form-control" name="audit_ratio" id="audit_ratio" value="0" placeholder="{$tr['audit multiple ex']}" disabled></td>
              <td>\${payout_dis}
                  <button class="btn btn-warning" onclick="batchpayoutpage_close();">{$tr['Cancel']}</button></td>
            </tr>
          </tbody>
        </table>
        `
      }





      $(document).ready(function() {
        var purl=get_parameter();
        // console.log(purl);

        $("#show_list").DataTable( {
            "bProcessing": true,
            "bServerSide": true,
            "bRetrieve": true,
            "searching": false,
            "oLanguage": {
              "sSearch": "{$tr['Account']}",//"会员帐号:",
              "sEmptyTable": "{$tr['no data']}",//"目前没有资料!",
              "sLengthMenu": "{$tr['each page']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
              "sZeroRecords": "{$tr['no data']}",//"目前没有资料",
              "sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}",//"目前在第 _PAGE_ 页，共 _PAGES_ 页",
              "sInfoEmpty": "{$tr['no data']}",//"目前没有资料",
              "sInfoFiltered": "({$tr['from']}_MAX_{$tr['filtering in data']})"//"(从 _MAX_ 笔资料中过滤)"
            },
            "ajax": {
              "url": "agent_depositbet_calculation_action.php?a=reload_profitlist&"+purl,
              "dataSrc": function(json) {
                if(json.data.list.length > 0) {
                    // console.log(json.data.sent_payoutpool);
                    var link= `<a href="`+json.data.download_url+`" class="btn btn-success mx-2" role="button" aria-pressed="true">{$tr['download']}xls</a>`;
                    $("#csv_dl").html(link);
                    if(json.data.sent_payoutpool.is_payout=='1'){
                        var batchpayout_link=`<button type="button" title='{$tr['The batch has been sent to the winning pool. If you need to send it again, please press the update button!']}' disabled class="btn btn-info mx-2">{$tr['Batch sent']}</button>`;
                    }else{
                        var batchpayout_link=`<button type="button" id="batchpayout_html_btn" class="btn btn-info mx-2" onclick="batchpayout_html();">{$tr['batch sending']}</button>`;
                    }
                    $("#batchpayout_div_id").html(batchpayout_link);
                    $('#batchpayout').html(batchpayoutTmpl(json.data.sent_payoutpool));
                }
                return json.data.list;
              }
            },
            "columns": [
              // { "data": "id", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
              //     $(nTd).html("<a href=\'member_treemap.php?id="+oData.member_id+"\' target=\"_BLANK\" title=\"会员的组织结构状态\">"+oData.member_id+"</a>");
              //   }
              // },
              // { "data": "agent_therole", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
              //     $(nTd).html("<a href=\'member_account.php?a="+oData.member_id+"\' target=\"_BLANK\" title=\"检查会员的详细资料\">"+oData.member_account+"</a>");
              //   }
              // },
              // { "data": "agent_account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
              //     $(nTd).html("<a href=\'#\' title=\"会员身份 R=管理员 A=代理商 M=会员\">"+oData.member_therole+"</a>");
              //   }
              // },
              // { "data": "commission", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
              //     if(oData.parent_account =='-') {
              //       $(nTd).html(oData.parent_account);
              //     } else {
              //       $(nTd).html("<a href=\'member_account.php?a="+oData.member_parent_id+"\' target=\"_BLANK\" title=\"检查详细资料\">"+oData.parent_account+"</a>");
              //     }
                  
              //   }
              // },

              { "data": "id", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "agent_therole", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "agent_account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                  $(nTd).html("<a href=\'member_account.php?a="+oData.agent_id+"\' target=\"_BLANK\" title=\"检查会员的详细资料\">"+oData.agent_account+"</a>");},className: "dt-right"},
              { "data": "commission", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "valid_member", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "effective_membership_pass", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "valid_bet_sum", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "reach_bet_amount", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "profitlost_sum", "searchable": false, "orderable": true, className: "dt-right" },
              { "data": "dl_detail_code", "searchable": false, "orderable": false, className: "dt-right", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                  $(nTd).html("<a class=\"btn btn-sm btn-primary\" href=\'agent_depositbet_calculation_action.php?a=dl_detail&detail_xls="+oData.dl_detail_code+"\' target=\"_BLANK\" title=\"{$tr['details']}\">{$tr['details']}</a>");
                }
              }

            ]
        } );


      // $.getJSON("agent_depositbet_calculation_action.php?a=query_csv&"+purl,
      //   function(result){
      //     console.log(result);
      //     console.log(result.download_url);
      //     // var res = JSON.parse(result); 解json函式
      //     var link= `<a href="`+result.download_url+`" class="btn btn-success btn-sm" role="button" aria-pressed="true">{$tr['Export Excel']}</a>`;
      //     // $("#csv_dl").html(link);
      //   }
      // );


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
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title . '-' . $tr['host_name'];

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