<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 時時反水查詢
// File Name:	realtime_reward.php
// Author:    yaoyuan
// Related  :root_protalsetting    ->時時反水設定值。
//           root_statisticsbetting->依照日期區間，撈十分鐘報表。
//           root_realtime_reward  ->時時反水資料。
//           root_receivemoney     ->反水打入彩金池db。

// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
// 此計算程式所使用的 LIB
require_once dirname(__FILE__) . "/realtime_reward_lib.php";


// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// 查詢反水區間，開始、結束時間
$sdate = $edate = '';
if (isset($_REQUEST['sdate']) and $_REQUEST['sdate'] != null) {
    if (validateDate($_REQUEST['sdate'], 'Y-m-d H:i')) {
        $sdate = $_REQUEST['sdate'];
    }
}
if (isset($_REQUEST['edate']) and $_REQUEST['edate'] != null) {
    if (validateDate($_REQUEST['edate'], 'Y-m-d H:i')) {
        $edate = $_REQUEST['edate'];
    }
}
if (isset($_REQUEST['show_start_date']) and $_REQUEST['show_start_date'] != null) {
    if (validateDate($_REQUEST['show_start_date'], 'Y-m-d H:i')) {
        $sdate = $_REQUEST['show_start_date'];
    }
}
if (isset($_REQUEST['show_end_date']) and $_REQUEST['show_end_date'] != null) {
    if (validateDate($_REQUEST['show_end_date'], 'Y-m-d H:i')) {
        $edate = $_REQUEST['show_end_date'];
    }
}

if(isset($_REQUEST['trans_id'])){
    $trans_id = filter_var($_REQUEST['trans_id'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    $trans_id='';
}
if(isset($_REQUEST['is_payout'])){
    $is_payout = filter_var($_REQUEST['is_payout'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數  $tr['Home'] = '首页';
// 功能標題，放在標題列及meta ;
$function_title = $tr['Rebate inquiry'];
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

// 有登入，且身份為管理員 R 才可以使用這個功能。
if (isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    // 取出美東時間
    $current_datepicker       = gmdate('Y-m-d H:i:s', time()+-4 * 3600);
    $current_datepicker_start   = date("Y-m-d H:i", strtotime("$current_datepicker"));
    $current_datepicker_end   = date("Y-m-d H:i", strtotime("$current_datepicker"));

    if ($edate > $current_datepicker_end) {
        $edate = $current_datepicker_end;
    }

    if (in_array($_SESSION['agent']->account, $su['superuser'])) {
        $show_reissue_id = ' <a href="#" class="btn btn-primary" onclick="msgconfirm()" id="reissue_id"  title="点击可以补发区间时时反水">'.$tr['bouns reissue'].'</a>';
        $show_comment = '<li>'.$tr['reissuing interval：About the start time, one hour is automatically added as the statistical interval'].'</li>
                       <li>'.$tr['reissued condition : If the reissuing interval has rebates data and is sent to the winning pool, it cannot be reissued'].'</li>';
        // $show_reissue_id = ''; // 補發按鈕
        // $show_comment = ''; // 功能說明部分內容
    } else {
        $show_reissue_id = '';
        $show_comment='';
    }

    // 功能說明文字
    $show_tips_html = '
    <div class="alert alert-info"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
      ' . $tr['Description of Realtime Rebate inquiry'] . '
        <ul>
          <li>'.$tr['Rebates available = Bet amount for each category * Casino rebate ratio'].'</li>
          <li>'.$tr["Stake amount per category = total number of members' own categories"].'</li>
          <li>'.$tr["If the member's total bet is not greater than the rebate set threshold, no rebate will be issued"].'</li>
          <li>'.$tr['Rebates setting for the whole website: System ->  Member set -> Rebates setting'].'</li>
          '.$show_comment.'
        </ul>
    </div>';

    $show_dateselector_html = '
  <form action="realtime_reward_action.php" method="post">
    <div class="form-inline">
      <div class="form-group">
        <div class="input-group mr-2 mb-2">
          <div class="input-group-addon">' . $tr['Current query interval'] . '</div>
          <input type="text" name="sdate" class="form-control" placeholder=' . $tr['Starting time'] . ' aria-describedby="basic-addon1" id="register_date_start_time" value="' . $sdate . '">
          <span class="input-group-addon" id="basic-addon1">~</span>
          <input type="text" name="edate" class="form-control" placeholder=' . $tr['End time'] . ' aria-describedby="basic-addon1" id="register_date_end_time" value="' . $edate . '">
        </div>
      </div>
      <button type="button" class="btn btn-primary mx-2" onclick="gotoindex();">' . $tr['Inquiry'] . '</button>
      '.$show_reissue_id.'
      <div id="csv_dl"></div>
    </div>
  </form>
  <hr>';

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
      var transaction_num ='{$trans_id}';
      if (transaction_num !='' ){
          var url='trans_id='+transaction_num;
      }else{
          var datepicker_start = $("#register_date_start_time").val();
          var datepicker_end = $("#register_date_end_time").val();
          var url='sdate='+datepicker_start+'&edate='+ datepicker_end ;
      }
      return url;
    }

    function get_datatimeparameter(){
          var datepicker_start = $("#register_date_start_time").val();
          var datepicker_end = $("#register_date_end_time").val();
          var url='sdate='+datepicker_start+'&edate='+ datepicker_end ;
      return url;
    }

    function gotoindex() {
        var purl=get_datatimeparameter();
        var goto_url = '{$_SERVER['PHP_SELF']}?'+purl;
        var goto_url = location.protocol + '//' + location.host + goto_url;
        // console.log(goto_url);
        location.href = goto_url;
    }

    // 確認完畢要補發反水，則送到後台
    function msgconfirm(){
      // 自訂日期轉換成時間的格式 https: //developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/toISOString
      if (!Date.prototype.toISOhourString) {
        (function() {
          function pad(number) {
            if (number < 10) {
              return '0' + number;
            }
            return number;
          }

          Date.prototype.toISOhourString = function() {
            return this.getUTCFullYear() +
              '-' + pad(this.getUTCMonth() + 1) +
              '-' + pad(this.getUTCDate()) +
              ' ' + pad(this.getUTCHours())
              +':00';
          };
        }());
      }
        var st_datetime = $("#register_date_start_time").val();
        var combine_date = st_datetime.replace(" ", "T") + ':00Z';

        var date2 = new Date(combine_date);

        // 開始時間，不需增加一小時
        var start_date_val = date2.toISOhourString();
        $("#register_date_start_time").val(start_date_val);

        // 增加一小時
        date2.setHours(date2. getHours() + 1);
        $("#register_date_end_time").val(date2.toISOhourString());

        if ( ($("#register_date_start_time").val() == '') || ($("#register_date_end_time").val() == '') ) {
            alert('开始时间或结束时间，不得为空!');
            return false;
        }

        if ( $("#register_date_start_time").val() > $("#register_date_end_time").val() ) {
            alert('开始时间大於結束时间!');
            return false;
        }

        var purl=get_datatimeparameter();
        $.getJSON("realtime_reward_action.php?a=query_existing_data&"+purl,
          function(result){
            if(result=='0'){
              alert('(X)不得补发有时时反水资料，且已发至彩金池!')
              return false;
            }else{
              if(confirm("确定要补发反水区间："+start_date_val+"～"+$("#register_date_end_time").val()+"，至彩金池？") == true) {
                  $("#reissue_id").prop("disabled",true);
                  var updatingcodeurl='realtime_reward_action.php?a=update_data&'+purl;
                  myWindow = window.open(updatingcodeurl, '补发时时反水资料', 'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800', false);
                  myWindow.focus();
                  setTimeout(function(){location.href="realtime_reward.php?"+purl;},3000);
                  // location.href =goto_url;
              }
            }
        });
    }
    function payout_batch_update(trans_id,date_range){
        if(confirm("确定要发送反水区间："+date_range+" 至彩金池吗？") == true) {
          $.getJSON("realtime_reward_action.php?a=payout_batch&trans_id="+trans_id,function(result){
              if(result.data.errormsg!=''){
                alert(result.data.errormsg);
                return false;
              }else{
                alert(result.data.successmsg);
                location.href=result.data.download_url;
              }
          })
        };



    }


    // for select day
    $('#register_date_start_time').datetimepicker({
      defaultDate:'{$current_datepicker_start}',
      maxDate: '{$current_datepicker_end}',
      formatTime: "H",
      timepicker:true,
      format:'Y-m-d H:i',
      lang:'en'
    });
    $('#register_date_end_time').datetimepicker({
      defaultDate:'{$current_datepicker_end}',
      maxDate: '{$current_datepicker_end}',
      formatTime: "H",
      timepicker:true,
      format:'Y-m-d H:i',
      lang:'en'
    });

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

      // itransaction
      var itransaction = $('#itransaction').val();
      // console.log(itransaction);

      var updatingcodeurl='realtime_reward_action.php?a=profitloss_payout&payout_date={$sdate}&payout_end_date={$edate}&s='+payout_status+'&s1='+bonus_type+'&s2='+audit_type+'&s3='+audit_amount+'&s4='+audit_ratio+'&s5='+audit_calculate_type+'&transaction_id='+itransaction;

      // console.log(updatingcodeurl);
      if(bonus_type == 'token' && audit_type == 'none'){
        alert('{$tr['please select audit of payout method']}');
        // alert('请选择奖金的稽核方式！');
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
            <th>{$tr['Account']}</th>
            <th>{$tr['The Role']}</th>
            <th>{$tr['reward']}</th>
            <th>{$tr['total betting']}</th>
            <th>{$tr['meet the bonus step']}</th>
            <th>{$tr['Starting time']}</th>
            <th>{$tr['End time']}</th>
            <th>{$tr['Release time']}</th>
            <th>{$tr['payout']}</th>
        </tr>
    HTML;

    // 列出資料, 主表格架構
    $show_list_html = '';

    // 列表
    $show_list_html .= <<<HTML
        <table id="show_list" class="display" cellspacing="0" width="100%">
            <thead>{$table_colname_html}</thead>
            <tfoot>{$table_colname_html}</tfoot>
        </table>
    HTML;

    // 參考使用 datatables 顯示
    // https://datatables.net/examples/styling/bootstrap.html
    $extend_head .= <<<HTML
        <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
        <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
        <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
        <style>
            ul {margin-left: 40px; padding-left: 0;}
            .glyphicon-info-sign {margin-right:0.5em;}
        </style>
    HTML;

    // DATA tables jquery plugging -- 要放在 head 內 不可以放 body
    $extend_head .= <<<HTML
        <script type="text/javascript" language="javascript" class="init">
        function batchpayoutTmpl(data) {
            // console.log(data);
            if (data.is_payout == '1') {
                var payout_dis = `<button id="payout_btn" class="btn btn-info" title='已批次发送至彩金池，如需再次发送，請回主畫面按更新钮!' disabled>发送</button>`;
            } else {
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
            var purl = get_parameter(); // console.log(purl);
            $("#show_list").DataTable( {
                "bProcessing": true,
                "bServerSide": true,
                "bRetrieve": true,
                "searching": false,
                "aaSorting": [[ 6, "desc" ]],
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
                    "url": "realtime_reward_action.php?a=reload_rewardlist&"+purl,
                    "dataSrc": function(json) {
                        if (json.data.is_error) {
                            alert(json.data.is_error);
                            $("#register_date_start_time").val("");
                            $("#register_date_end_time").val("");
                            return false;
                        }
                        if (json.data.list.length > 0) {
                            var link= `<a href="`+json.data.download_url+`" class="btn btn-success mx-2" role="button" aria-pressed="true">{$tr['download']}xls</a>`;
                            $("#csv_dl").html(link);
                        }
                        return json.data.list;
                    }
                },
                "columns": [
                    { "data": "id", "searchable": false, "orderable": true, className: "dt-right" },
                    { "data": "member_account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                        $(nTd).html("<a href=\'member_account.php?a="+oData.member_id+"\' target=\"_BLANK\" title=\"检查会员的详细资料\">"+oData.member_account+"</a>");},className: "dt-right"},
                    { "data": "member_therole", "searchable": false, "orderable": true, className: "dt-right" },
                    { "data": "real_reward_amount", "searchable": false, "orderable": true, className: "dt-right" },
                    { "data": "bet_sum", "searchable": false, "orderable": true, className: "dt-right" },
                    { "data": "reach_bet_amount", "searchable": false, "orderable": true, className: "dt-right" },
                    { "data": "start_date", "searchable": false, "orderable": true, className: "dt-right" },
                    { "data": "end_date", "searchable": false, "orderable": true, className: "dt-right" },
                    { "data": "payout_date", "searchable": false, "orderable": true, className: "dt-right" },
                    { "data": "is_payout", "searchable": false, "orderable": true, className: "dt-right" },
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
    $indexbody_content = '
  	    <div class="row">
            <div class="col-xs-12">' . $indexmenu_stats_switch_html . $show_tips_html . '</div>
            <div class="col-xs-12">' . $show_dateselector_html . '</div>
            <div id="summary_report_html" class="col-xs-12"></div>
            <div class="col-xs-12">' . $show_list_html . '</div>
        </div>
        <br>
    	<div class="row">
  		    <div id="preview_result"></div>
  	    </div>
        <div style="display: none;width: 800px;" id="batchpayout"></div>
        <div style="display: none;width: 800px;" id="idprogressbar"></div>';
    // -------------------------------------------------------------------------

} else {
    // 沒有登入的顯示提示俊息
    $show_html = '(x) 只有管​​理员或有权限的会员才可以登入观看。';

    // 切成 1 欄版面
    $indexbody_content = '';
    $indexbody_content = $indexbody_content . '
	<div class="row">
	  <div class="col-xs-12 col-md-12">
	  ' . $show_html . '
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
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author']      = $tr['host_author'];
$tmpl['html_meta_title']       = $function_title . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>' . $function_title;
// 主要內容 -- content
$tmpl['panelbody_content'] = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/beadmin.tmpl.php");
include "template/beadmin_fluid.tmpl.php";
