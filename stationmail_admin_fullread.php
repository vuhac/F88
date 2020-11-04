<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 站內信件閱讀全文內容。針對 stationmail_admin.php 的閱讀全文button動作執行顯示對應頁面。
// File Name:	stationmail_admin_fullread.php
// Author:		Yuan
// Related:     對應 stationmail_admin.php 做信件內容顯示
// Table:       root_stationmail
// Log:
//
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// var_dump($_SESSION);

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
$function_title 		= '信件內容';
// 擴充 head 內的 css or js
$extend_head				= '<link rel="stylesheet"  href="ui/stationmail_admin.css" >';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">首頁</a></li>
  <li><a href="#">系統管理</a></li>
  <li><a href="stationmail_admin.php">管理端站內信管理</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------



// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  $message_html = '';
  // 收件者預設 gpk
  $msgto_account = $stationmail['sendto_system_cs'];
  // 信件id
  $msgid  = filter_var($_GET['i'], FILTER_SANITIZE_STRING);
  // 該信件使用者帳號
  // $msgfrom_account  = filter_var($_GET['u'], FILTER_SANITIZE_STRING);
  // 該信件發信時間
  // $send_time = filter_var($_GET['t'], FILTER_SANITIZE_STRING);
  // 執行動作的來源 , 看從收件匣或寄件備份點擊閱讀全文按鈕
  // $who_call = filter_var($_GET['w'], FILTER_SANITIZE_STRING);

//  var_dump($msgid . " " . $msgfrom_account . " " . $send_time . " " . $who_call);

  if ($msgid != '') {
    $mail_sql = "SELECT *, to_char((sendtime AT TIME ZONE '$tzonename'),'MM-DD HH24:MI:SS' ) AS sendtime FROM root_stationmail WHERE id = '".$msgid."' AND (msgfrom = '$msgto_account' OR msgto = '$msgto_account');";
    // var_dump($mail_sql);
    $mail_sql_result = runSQLall($mail_sql);
    // var_dump($mail_sql_result);

    if ($mail_sql_result[0] == 1) {
      $msgto_account = $mail_sql_result[1]->msgto;
      $msgfrom_account = $mail_sql_result[1]->msgfrom;
      $send_time = $mail_sql_result[1]->sendtime;

      if ($msgto_account == $stationmail['sendto_system_cs']) {
        $who_call = 'inbox';
      } else {
        $who_call = 'send_backup';
      }

      // 顯示是否已讀訊息
      $read_msg = 'Read';

      // 使用者所在的時區，sql 依據所在時區顯示 time
      if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
        $tz = $_SESSION['agent']->timezone;
      } else {
        $tz = '+08';
      }
        // 轉換時區所要用的 sql timezone 參數
        $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
        $tzone = runSQLALL($tzsql);
        // var_dump($tzone);
      if($tzone[0]==1) {
        $tzonename = $tzone[1]->name;
      } else {
        $tzonename = 'posix/Etc/GMT-8';
      }

      // $fullreal_btngroup_title = '
      // <div class="row">
      //   <div class="col-12 col-md-3">
      //     <div class="btn-group">
      //         <a href="stationmail_admin.php" class="btn btn-primary" role="button" aria-pressed="true">收件匣</a>
      //         <a href="stationmail_admin.php" class="btn btn-primary" role="button" aria-pressed="true">寄 信</a>
      //       <a href="stationmail_admin.php" class="btn btn-primary" role="button" aria-pressed="true">寄件備份</a>
      //     </div>
      //   </div>
      //   <div class="col-12 col-md-6">
      //       <h2 class="modal-body text-center" id="sendABackup_send_message_text">與 '.$msgfrom_account.' 的對話</h2>
      //   </div>
      //   <div class="col-12 col-md-3">
      //   </div>
      // </div>
      // ';

      if ($who_call == 'inbox') {

        // -----------------------------------------------------------------------------------------------------------------------------------------------
        //  收件匣點擊閱讀全文按鈕頁面組合 start
        // -----------------------------------------------------------------------------------------------------------------------------------------------
        /**
         * 取得該信件寄件時間之前的來回信內容
         * 並檢查有無寫入過已讀時間 , 如無寫入就更新已讀時間
         * 如已有已讀時間就顯示已讀字樣
         */
        $sql = "SELECT id,sendtime, to_char((sendtime AT TIME ZONE '$tzonename'),'MM-DD HH24:MI:SS' )  as cst_sendtime ,msgfrom,msgto,message,readtime,status FROM root_stationmail WHERE ((msgfrom = '$msgfrom_account' AND msgto = '$msgto_account') OR (msgfrom = '$msgto_account' AND msgto = '$msgfrom_account')) ORDER BY id DESC LIMIT 50;";
        // var_dump($sql);
        $msg_result = runSQLall($sql);
        // var_dump($msg_result);

        $talk_title_html = '<h2 class="modal-body text-center" id="sendABackup_send_message_text">與 '.$msgfrom_account.' 的對話</h2>';

        if($msg_result[0] >= 0 ) {
          for($i=1;$i<=$msg_result[0];$i++) {
            if($msg_result[$i]->msgfrom == $msgfrom_account) {
              // from A -- station

              // 更新已讀時間
              if ($msg_result[$i]->readtime == null) {
                $readtimeUpdatesql = "UPDATE root_stationmail SET readtime = now() WHERE id = '".$msg_result[$i]->id."' AND msgto = '$msgto_account'";
                // var_dump($readtimeUpdatesql);
                $readtimeUpdatesql_result = runSQLall($readtimeUpdatesql);
                // var_dump($readtimeUpdatesql_result);
              }

              $message_a_id = $msg_result[$i]->id;
              $message_a_text = $msg_result[$i]->message;
              $message_a_time = $msg_result[$i]->cst_sendtime;
              $message_a_name = $msgto_account;
              $message_a_readtime = $msg_result[$i]->readtime;
              $message_a_status = $msg_result[$i]->status;
              // $message_a_read = $msg_result[$i]->read;
              $message_html = $message_html.'
              <div class="row">
                <div class="col-12 col-md-3">
                </div>
                <div class="col-12 col-md-6">
                  <p align="left" class="inbox_member_avatar_p"><button type="button" class="btn btn-default btn-lg glyphicon glyphicon-user" disabled="disabled"></button>&nbsp;&nbsp;<span>會員 '.$msg_result[$i]->msgfrom.'</span></p>
                  <p align="left" class="bg-success inbox_member_msg_p" onclick="fullread_delete_mail(\''.$message_a_id.'\',\''.$msgfrom_account.'\',\''.$msgto_account.'\',\''.$message_a_status.'\',\''.$who_call.'\')"><td class="inbox_memger_msg_td">'.$message_a_text.'<td>&nbsp;<span class="inbox_member_msg_time_span">'.$message_a_time.'</span>&nbsp;<span class="inbox_member_read_msg_span">'.$read_msg.'</span></p>
                </div>
                <div class="col-12 col-md-3">
                </div>
              </div>
              ';
              //<button class="btn btn-info" type="submit">'.$message_a_text.' </button>
            } else {
              // to B -- customer
              $message_b_text = $msg_result[$i]->message;
              $message_b_time = $msg_result[$i]->cst_sendtime;
              $message_b_name = $msgto_account;
              $message_a_readtime = $msg_result[$i]->readtime;
              // $message_b_read = $msg_result[$i]->read;
              $message_html = $message_html.'
              <div class="row">
                <div class="col-12 col-md-3">
                </div>
                <div class="col-12 col-md-6">
                    <p align="right" class="agent_avatar_p"><span>GPK 客服</span>&nbsp;&nbsp;<button type="button" class="btn btn-default btn-lg glyphicon glyphicon-earphone" disabled="disabled"></button></p>
                    <p align="right" class="bg-info agent_time_p" id="agent_time_p">
                      <td class="msg">'.$message_b_text.'<td>&nbsp;<span>'.$message_b_time.'</span>
                    </p>
                </div>
                  <div class="col-12 col-md-3">
                  </div>
              </div>
              ';
              //<button class="btn btn-success" type="submit">'.$message_b_text.'</button>
            }
            // end if

          }
          // end loop get message

        } else {
          $logger = 'DB error';
          echo $logger;
        }

        // -----------------------------------------------------------------------------------------------------------------------------------------------
        //  收件匣點擊閱讀全文按鈕頁面組合 end
        // -----------------------------------------------------------------------------------------------------------------------------------------------

      } else {

        // -----------------------------------------------------------------------------------------------------------------------------------------------
        //  寄件備份點擊閱讀全文按鈕頁面組合 start
        // -----------------------------------------------------------------------------------------------------------------------------------------------
        // $sql = "SELECT id,sendtime, to_char((sendtime AT TIME ZONE '$tzonename'),'MM-DD HH24:MI:SS' )  as cst_sendtime ,msgfrom,msgto,message,readtime,status FROM root_stationmail WHERE ((msgfrom = '$msgfrom_account' AND msgto = '$msgto_account') OR (msgfrom = '$msgto_account' AND msgto = '$msgfrom_account')) ORDER BY id DESC LIMIT 50;";
        $sql = "SELECT id,sendtime, to_char((sendtime AT TIME ZONE '$tzonename'),'MM-DD HH24:MI:SS' )  as cst_sendtime ,msgfrom,msgto,message,readtime,status FROM root_stationmail WHERE id = '$msgid' AND msgfrom = '$msgfrom_account' AND msgto = '$msgto_account';";
        // var_dump($sql);
        $msg_result = runSQLall($sql);
        // var_dump($msg_result);

        $talk_title_html = '<h2 class="modal-body text-center" id="sendABackup_send_message_text">與 '.$msgto_account.' 的對話</h2>';

        // var_dump($msgto_account);
        if($msg_result[0] >= 0 ) {
          if($msg_result[1]->msgfrom == $stationmail['sendto_system_cs']) {
            $message_a_id = $msg_result[1]->id;
            $message_a_text = $msg_result[1]->message;
            $message_a_time = $msg_result[1]->cst_sendtime;
            $message_a_name = $msgto_account;
            $message_a_readtime = $msg_result[1]->readtime;
            $message_a_status = $msg_result[1]->status;
            // $message_a_read = $msg_result[$i]->read;

              $message_html = $message_html.'
              <div class="row">
                <div class="col-12 col-md-3">
                </div>
                <div class="col-12 col-md-6">
                    <p align="right" class="send_backup_avatar_p"><span>GPK 客服</span>&nbsp;&nbsp;<button type="button" class="btn btn-default btn-lg glyphicon glyphicon-earphone send_backup_avatar" disabled="disabled"></button></p>
                    <p align="right" class="bg-info agent_p" id="agent_p">
                      <td class="send_backup_msg_td">'.$message_a_text.'<td>&nbsp;<span style="color:#808080;" class="send_backup_time_span">'.$message_a_time.'</span>
                    </p>
                </div>
                  <div class="col-12 col-md-3">
                  </div>
              </div>
              ';
          }
        }

        // -----------------------------------------------------------------------------------------------------------------------------------------------
        //  寄件備份點擊閱讀全文按鈕頁面組合 end
        // -----------------------------------------------------------------------------------------------------------------------------------------------

      }


      $fullreal_btngroup_title = '
      <div class="row">
        <div class="col-12 col-md-4">
          <div class="btn-group">
              <a href="stationmail_admin.php" class="btn btn-primary" role="button" aria-pressed="true">收件匣</a>
              <a href="stationmail_admin.php" class="btn btn-primary" role="button" aria-pressed="true">寄 信</a>
            <a href="stationmail_admin.php" class="btn btn-primary" role="button" aria-pressed="true">寄件備份</a>
          </div>
        </div>
        <div class="col-12 col-md-6"></div>
        <div class="col-12 col-md-2"></div>
      </div>
      <div class="row">
        <div class="col-12 col-md-3"></div>
        <div class="col-12 col-md-6">
            '.$talk_title_html.'
        </div>
        <div class="col-12 col-md-3"></div>
      </div>
      ';


      $message_html = $fullreal_btngroup_title.$message_html;

      $send_message_html = '';
      $send_message_html = $send_message_html.'

      <div class="row">
        <div class="col-12 col-md-3"></div>
        <div class="col-12 col-md-6">
          <hr>
          <div class="row">
            <div class="col-md-8"></div>
            <div class="col-md-2"></div>
            <div class="col-md-2">
                <a href="stationmail_admin.php" class="btn btn-primary" role="button" aria-pressed="true">回站內信管理</a>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-3"></div>
      </div>
      ';

    } else {
      // 查無此訊息
      $message_html = '(x) 查無此訊息。';
      $send_message_html = '';
    }
    
  } else {
    // 錯誤的嘗試
    $message_html = '(x) 錯誤的嘗試。';
    $send_message_html = '';
  }

}else{
	// 不合法登入者的顯示訊息
	$message_html = '(x) 請先登入會員，才可以使用此功能。';
  $send_message_html = '';
}


// -----------------------------------------------------------------------------------------------------------------------------------------------
//  信件內容事件 js 組合 start
// -----------------------------------------------------------------------------------------------------------------------------------------------


// 刪除信件 js function
$extend_js = $extend_js."
	<script>
	function fullread_delete_mail(mail_id,mail_from,mail_to,status,who_call) {
		var show_text = '確定要刪除信件嗎？';
		if(confirm(show_text)) {
			if(jQuery.trim(status) == '0') {
			    alert('此訊息已不存在收件匣！');
			} else {
			    $.post('stationmail_admin_fullread_action.php?a=delete_mail',
                {
                    mail_id: mail_id,
                    mail_from: mail_from,
                    mail_to: mail_to,
                    status: status,
                    who_call: who_call,
                },
                function(result) {
                    $('#preview').html(result);
                });
			}
		}
	}
	</script>";


// -----------------------------------------------------------------------------------------------------------------------------------------------
//  信件內容事件 js 組合 end
// -----------------------------------------------------------------------------------------------------------------------------------------------

// 切成 3 欄版面
$indexbody_content = $indexbody_content.'
'.$message_html.'
'.$send_message_html.'
<div class="row">
	<div class="col-12 col-md-3">
	</div>

	<div class="col-12 col-md-6">
		<div id="preview"></div>
	</div>

	<div class="col-12 col-md-3">
	</div>
</div>
';


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
include("template/beadmin.tmpl.php");

?>
