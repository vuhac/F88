<?php
// ----------------------------------------------------------------------------
// Features:	後台--站內信件管理，主要針對會員，客服回應訊息。及訊息查詢。
// File Name:	stationmail_admin.php
// Author:		Yuan
// Related:		對應前台 stationmail.php
//              收件匣信件內容顯示 stationmail_admin_fullread.php
// DB Table:  root_announcement
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/stationmail_lib.php";

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
$function_title 		= $tr['letters management of admin'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] .'</a></li>
  <li><a href="#">' . $tr['System Management'] . '</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

function check_search_islegitimate($starttime, $endtime)
{
    if (empty($starttime) || empty($endtime)) {
        $error_msg = '请选择正确的开始及结束时间';
        return array('status' => false, 'result' => $error_msg);
    }

    if ($starttime > $endtime) {
        $error_msg = '开始时间不可大于结束时间';
        return array('status' => false, 'result' => $error_msg);
    }

    return array('status' => true, 'result' => 'OK');

}

function get_inbox_tablecontent_html($allmail, $cs_acc)
{
    $html = '';

    /*
    信件內容需使用 strstr() 處理字串 , 否則有換行的內容會不只顯示一行
        ex : 內容1
              內容2<
    */
    foreach ($allmail as $key => $maildata) {
        $html = $html.'
        <tr>
            <td class="text-left">'.$key.'</td>
            <td class="text-left">'.$maildata->msgfrom.'</td>
            <td class="text-left">'.$cs_acc.'</td>
            <td class="text-left">'.$maildata->sendtime.'</td>
            <td class="text-left">'.mb_substr($maildata->subject, 0, 10).'</td>
            <td class="text-left">
                <div class="row">
                    <div class="col-md-7">
                        '.mb_substr(strstr($maildata->message, '<br />', true), 0, 10).'
                    </div>
                    <div class="col-md-2">
                        <a href="stationmail_admin_fullread.php?i='.$maildata->id.'" class="btn btn-primary btn-lg btn-xs" role="button" aria-pressed="true">阅读全文</a>
                    </div>
                </div>
            </td>
            <td class="text-left">'.$maildata->readtime.'</td>
        </tr>
        ';
    }

    return $html;
}

function get_sendbackup_tablecontent_html($sendbackup_allmeil, $cs_acc)
{
    $html = '';

    /*
    信件內容需使用 strstr() 處理字串 , 否則有換行的內容會不只顯示一行
     ex : 內容1
           內容2<
    */
    foreach ($sendbackup_allmeil as $key => $maildata) {
        $html = $html.'
        <tr>
            <td class="text-left">'.$key.'</td>
            <td class="text-left">'.$cs_acc.'</td>
            <td class="text-left">'.$maildata->msgto.'</td>
            <td class="text-left">'.$maildata->sendtime.'</td>
            <td class="text-left">'.mb_substr($maildata->subject, 0, 10).'</td>
            <td class="text-left">
                <div class="row">
                    <div class="col-md-6">
                        '.mb_substr(strstr($maildata->message, '<br />', true), 0, 10).'
                    </div>
                    <div class="col-md-3">
                        <a href="stationmail_admin_fullread.php?i='.$maildata->id.'" class="btn btn-primary btn-lg btn-xs" role="button" aria-pressed="true">阅读全文</a>
                    </div>
                </div>
            </td>
        </tr>
        ';
    }

    return $html;
}

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

    // 收件匣表格內容
    $inbox_tablecontent_html = '';
    //寄件備份表格內容
    $sendbackup_tablecontent_html = '';

    $msgfrom_account = $stationmail['sendto_system_cs'];
    $msgto_account = '';

    $tzonename = get_tzonename($_SESSION['agent']->timezone);

    $today = gmdate('Y-m-d',time() + $_SESSION['agent']->timezone * 3600);

    $inboxmail_search_starttime = $today;
    $inboxmail_search_endtime = $today;
    if (isset($_GET['stime']) && isset($_GET['etime'])) {
        $inboxmail_search_starttime = $_GET['stime'];
        $inboxmail_search_endtime = $_GET['etime'];

        $check_timerange_result = (object)check_search_islegitimate($inboxmail_search_starttime, $inboxmail_search_endtime);
        if (!$check_timerange_result->status) {
            echo "<script>alert('".$check_timerange_result->result."');window.location.replace('./stationmail_admin.php');</script>";
        }
    }

    $time_range	= '
    <form class="form-inline" method="get">
        <div class="form-group mr-2 mb-1">
            <div class="input-group">
                <div class="input-group-addon">' . $tr['inbox specified query'] . '</div>
                <input type="text" class="form-control" aria-describedby="basic-addon1" id="inboxmail_search_starttime" name="stime" value="'.$inboxmail_search_starttime.'" placeholder='.$tr['Starting time'].'>
                <span class="input-group-addon" id="basic-addon1">~</span>
                <input type="text" class="form-control" aria-describedby="basic-addon1" id="inboxmail_search_endtime" name="etime" value="'.$inboxmail_search_endtime.'" placeholder='.$tr['End time'].'>
            </div>
        </div>
        <button class="btn btn-primary mr-2" type="submit" role="button" id="timerange_search_btn">' . $tr['Inquiry'] . '</button>
        <button type="button" class="btn btn-success" id="send_mail" style="display:inline-block;float: right;margin-right: 5px;"><span class="glyphicon glyphicon-plus" aria-hidden="true">' . $tr['compose'] . '</span></button>
    </form>
    <hr>
    ';

    $extend_js = $extend_js."
    <script>
    $(document).ready(function() {
        $('#send_mail').click(function() {
            $('#outBox_Tab').click();
        });
    });
    </script>
    ";

    // ref. doc: http://xdsoft.net/jqplugins/datetimepicker/
    // 取得日期的 jquery datetime picker -- for birthday
    $extend_head = $extend_head.'<link rel="stylesheet" type="text/css" href="in/datetimepicker/jquery.datetimepicker.css"/>';
    $extend_js = $extend_js.'<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>';

    // date 選擇器 https://jqueryui.com/datepicker/
    // http://api.jqueryui.com/datepicker/
    // 14 - 100 歲為年齡範圍， 25-55 為主流客戶。
    $dateyearrange_start = date("Y") - 100;
    $dateyearrange_end = date("Y/m/d");
    $datedefauleyear = date("Y/m/d");

    $extend_js = $extend_js."
    <script>
    // for select day
    $('#inboxmail_search_starttime, #inboxmail_search_endtime').datetimepicker({
        defaultDate:'" . $datedefauleyear . "',
        minDate: '" . $dateyearrange_start . "-01-01',
        maxDate: '" . $dateyearrange_end . "',
        timepicker:false,
        format:'Y-m-d',
        lang:'en'
    });
    </script>
    ";

    // -----------------------------------------------------------------------------------------------------------------------------------------------
    //  收件匣 start
    // -----------------------------------------------------------------------------------------------------------------------------------------------

    $inbox_tablecolname = '
    <tr>
        <th class="text-left">' . $tr['number'] . '</th>
        <th class="text-left">' . $tr['sender'] . '</th>
        <th class="text-left">' . $tr['recipient'] . '</th>
        <th class="text-left">' . $tr['date'] . '</th>
        <th class="text-left">' . $tr['subject'] . '</th>
        <th class="text-left">' . $tr['content'] . '</th>
        <th class="text-left">' . $tr['read'] . '</th>
    </tr>
    ';

    $inbox = (object)get_timerange_inboxmail($msgfrom_account, $inboxmail_search_starttime, $inboxmail_search_endtime, $tzonename);
    if ($inbox->status) {
        $inbox_tablecontent_html = get_inbox_tablecontent_html($inbox->result, $msgfrom_account);
    }

    // -----------------------------------------------------------------------------------------------------------------------------------------------
    //  收件匣 end
    // -----------------------------------------------------------------------------------------------------------------------------------------------


    // -----------------------------------------------------------------------------------------------------------------------------------------------
    //  寄件備份 start
    // -----------------------------------------------------------------------------------------------------------------------------------------------

    $sendbackup_tablecolname = '
    <tr>
        <th class="text-left">' . $tr['number'] . '</th>
        <th class="text-left">' . $tr['sender'] . '</th>
        <th class="text-left">' . $tr['recipient'] . '</th>
        <th class="text-left">' . $tr['date'] . '</th>
        <th class="text-left">' . $tr['subject'] . '</th>
        <th class="text-left">' . $tr['content'] . '</th>
    </tr>
    ';

    $sendbackup = (object)get_sendbackupmail($msgfrom_account, $tzonename);
    if ($sendbackup->status) {
        $sendbackup_tablecontent_html = get_sendbackup_tablecontent_html($sendbackup->result, $msgfrom_account);
    }

    // -----------------------------------------------------------------------------------------------------------------------------------------------
    //  寄件備份 end
    // -----------------------------------------------------------------------------------------------------------------------------------------------


    // 刪除信件 js function
    $extend_js = $extend_js."
	<script>
	function delete_mail(mail_id,mail_from,who_call) {
		var show_text = '确定要删除信件吗？';

		if(confirm(show_text)) {
			if(jQuery.trim(mail_id) != '' && jQuery.trim(mail_from) != '' && jQuery.trim(who_call) != '') {
				$.post('stationmail_admin_action.php?a=delete_mail',
                    {
                        mail_id: mail_id,
                        mail_from: mail_from,
                        who_call: who_call,
                    },
                    function(result) {
                        $('#preview_result').html(result);
                    }
                );
			} else {
				alert('信件删除失败！');
			}
		}
	}
    </script>";
    

    // -----------------------------------------------------------------------------------------------------------------------------------------------
    //  寄件匣 start
    // -----------------------------------------------------------------------------------------------------------------------------------------------

    $outBox_html = '
    <div class="col-12 col-md-12">
        <div class="row">
            <div class="col-md-2"></div>

            <div class="col-md-8">

                <div class="form-group">
                    <label for="addressee" class="control-label">' . $tr['recipient'] . '</label>
                    <input type="text" class="form-control" id="outBox_send_addressee" name="outBox_send_addressee" placeholder="填写多个收件人请以逗号区隔 ex : userA,userB,userC" onkeyup="value=value.replace(/[^a-zA-Z0-9|,]/,\'\')" value="'.$msgto_account.'">
                </div>
                <div class="form-group">
                    <label for="sender" class="control-label">' . $tr['sender'] . '</label>
                    <input type="text" class="form-control" id="outBox_send_sender" name="outBox_send_sender" value="'.$msgfrom_account.'" disabled="disabled">
                </div>
                <div class="form-group">
                    <label for="outBox_send_subject_text_label" class="control-label">' . $tr['subject'] . '</label>
                    <textarea class="form-control" rows="1" id="outBox_send_subject_text" name="outBox_send_subject_text" placeholder="最大字数限制 100 字" onkeyup="outBox_send_subject_words_deal()"></textarea>
                </div>
                <div class="form-group">
                    <label for="outBox_send_message_text_label" class="control-label">' . $tr['content'] . '</label>
                    <textarea class="form-control" cols="45" rows="5" id="outBox_send_message_text" name="outBox_send_message_text" placeholder="最大字数限制 1000 字" onkeyup="outBox_send_message_words_deal()"></textarea>
                </div>
                <div class="form-group">
                    <input id="outBox_submit" name="outBox_submit" type="submit" value="Send" class="btn btn-primary">
                </div>

            </div>

            <div class="col-md-2"></div>
        </div>
    </div>
     ';

    $extend_js = $extend_js."
    <script>
    $(document).ready(function() {
        $('#outBox_submit').click(function() {
            var send_message_text = $('#outBox_send_message_text').val();
            var send_subject_text = $('#outBox_send_subject_text').val();
            var sendto_system = $('#outBox_send_addressee').val();

            if(jQuery.trim(send_message_text) == '' || jQuery.trim(send_subject_text) == '' || jQuery.trim(sendto_system) == '') {
                alert('信件发送失败，收件人、信件主旨及内容不能为空！');
            } else {
                $.post('stationmail_admin_action.php?a=stationmail_send',
                    {
                        sendto_system : sendto_system,
                        send_subject_text : send_subject_text,
                        send_message_text : send_message_text
                    },
                    function(result) {
                        $('#preview_result').html(result);
                    }
                );
            }
        });
    });
    </script>
    ";

    /*
     使用者輸入字數限制 js

     outBox_send_subject_words_deal() - 寄信主旨字數判斷 , 超過120字會自動刪除並跳出提醒
     outBox_send_message_words_deal() - 寄信內容字數判斷 , 超過1000字會自動刪除並跳出提醒
     */
    $extend_js = $extend_js."
    <Script>
    function outBox_send_subject_words_deal() {
        var curLength = $('#outBox_send_subject_text').val().length;
        if (curLength > 100) {
            var num = $('#outBox_send_subject_text').val().substr(0, 100);
            $('#outBox_send_subject_text').val(num);
            alert('超过字数限制，多出的字将被移除！');
        } else {
            $('#textCount').text(100 - $('#outBox_send_subject_text').val().length);
        }
    }

    function outBox_send_message_words_deal() {
        var curLength = $('#outBox_send_message_text').val().length;
        if (curLength > 1000) {
            var num = $('#outBox_send_message_text').val().substr(0, 1000);
            $('#outBox_send_message_text').val(num);
            alert('超过字数限制，多出的字将被移除！');
        } else {
            $('#textCount').text(1000 - $('#outBox_send_message_text').val().length);
        }
    }
    </Script>
    ";

    // -----------------------------------------------------------------------------------------------------------------------------------------------
    //  寄件匣 end
    // -----------------------------------------------------------------------------------------------------------------------------------------------


    // -----------------------------------------------------------------------------------------------------------------------------------------------
    //  tab 內容組合 start
    // -----------------------------------------------------------------------------------------------------------------------------------------------

    $mail_tab = $time_range.'
    <div>
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" class="active"><a href="#inbox_View" aria-controls="inBox_Tab" role="tab" data-toggle="tab">' . $tr['inbox'] . '</a></li>
            <li role="presentation"><a href="#outbox_View" aria-controls="outBox_Tab" role="tab" data-toggle="tab" id="outBox_Tab">' . $tr['draft'] . '</a></li>
            <li role="presentation"><a href="#sendABackup_View" aria-controls="outBox_Tab" role="tab" data-toggle="tab">' . $tr['sent'] . '</a></li>
        </ul>

        <!-- Tab panes -->
        <!-- 收件匣 tab 內容 -->
        <div class="tab-content col-12 col-md-12">
        <br>
            <div role="tabpanel" class="tab-pane active col-12 col-md-12" id="inbox_View">
                <table id="inbox_transaction_list" class="table" cellspacing="0" width="100%">
                    <thead>
                        '.$inbox_tablecolname.'
                    </thead>
                    <tbody>
                        '.$inbox_tablecontent_html.'
                    </tbody>
                </table>
            </div>

            <!-- 寄信 tab 內容 -->
            <div role="tabpanel" class="tab-pane col-12 col-md-12" id="outbox_View">
            <br>
                '.$outBox_html.'
            <br>
            </div>

            <!-- 寄件備份 tab 內容 -->
            <div role="tabpanel" class="tab-pane col-12 col-md-12" id="sendABackup_View">
                <table id="sendABackup_transaction_list" class="table" cellspacing="0" width="100%">
                    <thead>
                     '.$sendbackup_tablecolname.'
                    </thead>
                    <tbody>
                        '.$sendbackup_tablecontent_html.'
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="row">
		<div id="preview_result"></div>
	</div>
    ';

    // DATA tables jquery plugging -- 要放在 head 內 不可以放 body
    $extend_head = $extend_head.'
	<script type="text/javascript" language="javascript" class="init">
		$(document).ready(function() {
			$("#inbox_transaction_list, #sendABackup_transaction_list").DataTable( {
					"paging":   true,
					"ordering": true,
					"info":     true,
                    "order": [[ 0, "asc" ]],
                    "pageLength": 50
			} );
		} )
	</script>
	';

    // 參考使用 datatables 顯示
    // https://datatables.net/examples/styling/bootstrap.html
    $extend_head = $extend_head.'
	<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
	<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
	<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
	';


    // -----------------------------------------------------------------------------------------------------------------------------------------------
    //  tab 內容組合 end
    // -----------------------------------------------------------------------------------------------------------------------------------------------

} else {
	// 沒有登入的顯示提示俊息
    $mail_tab  = '(x) 只有管理員或有權限的會員才可以登入觀看。';

	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 col-md-12">
	  '.$mail_tab.'
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
$tmpl['page_title']								= $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']				= $mail_tab;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");


?>
