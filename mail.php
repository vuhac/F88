<?php
// ----------------------------------------------------------------------------
// Features:	站內信件
// File Name:	mail.php
// Author:		Neil
// Related:
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

require_once dirname(__FILE__) ."/lib_mail.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

function combineInboxContentHtml($data)
{
  $html = '';

  foreach ($data as $mail) {
    // var_dump($mail);
    $isRead = ($mail->cs_readtime != '') ? 'unread' : '';
    $idDelete = ($mail->status == '0') ? 'delete' : '';
    $howlongage = getHowLongAgo($mail->sendtime);

    $html .= <<<HTML
    <tr class="inboxDataRow {$isRead}" id="{$mail->mailcode}_{$mail->mailtype}">
      <td>
        <div class="form-check">
          <input class="form-check-input position-static delInbox" type="checkbox" name="delMail" value="{$mail->mailcode}_{$mail->mailtype}" aria-label="inboxDelMail">
        </div>
      </td>
      <td>
        <button type="button" class="btn btn-link memberListlDetail" value="msgfromDetail">{$mail->msgfrom}</button>
      </td>
      <td>
        <button type="button" class="btn btn-link mailDetail {$idDelete}" value="inboxMailDetail">{$mail->subject}</button>
      </td>
      <td>{$mail->sendtime} <small>({$howlongage})</small></td>
    </tr>
HTML;
  }

  return $html;
}

function combineSentContentHtml($data)
{
  global $tr;

  $html = '';

  foreach ($data as $mail) {
    $msgto = ($mail->mailtype == 'group') ? $tr['total'].$mail->msgto.$tr['people'] : $mail->msgto;
    $howlongage = getHowLongAgo($mail->sendtime);
    $html .= <<<HTML
    <tr class="sentDataRow" id="{$mail->mailcode}_{$mail->mailtype}">
      <td>
        <div class="form-check">
          <input class="form-check-input position-static delSent" name="delMail" type="checkbox" value="{$mail->mailcode}_{$mail->mailtype}" aria-label="sentDelMail">
        </div>
      </td>
      <td>
        <button type="button" class="btn btn-link memberListlDetail" value="msgtoDetail">{$msgto}</button>
      </td>
      <td>
        <button type="button" class="btn btn-link mailDetail" value="sentMailDetail">{$mail->subject}</button>
      </td>
      <!-- <td>{$mail->msgfrom}</td> -->
      <td>{$mail->sendtime} <small>({$howlongage})</small></td>
    </tr>
HTML;
  }

  return $html;
}

// 初始化變數
// 功能標題，放在標題列及meta
$function_title 		= $tr['letters management'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['profit and promotion'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


$inboxContentHtml = '<tr><td colspan="4">'.$tr['no mail was found'].'</td><tr>';
$delAllInboxHtml = '';
$inboxLoadMoreBtn = '';

$inbox = getInBoxMail($stationmail['sendto_system_cs']);
if ($inbox) {
  $inboxContentHtml = combineInboxContentHtml($inbox);
  $delAllInboxHtml = '
  <div class="form-check">
    <input class="form-check-input position-static delAllInbox" type="checkbox" aria-label="delAllInbox">
  </div>
  ';
  $inboxLoadMoreBtn = '<button type="button" class="btn btn-primary btn-lg btn-block loadMore" id="inboxLoadMore">'.$tr['loading'].'</button>';
}

$sentContentHtml = '<tr><td colspan="4">'.$tr['no mail was found'].'</td><tr>';
$delAllSentHtml = '';
$sentLoadMoreBtn = '';

$sent = getSentMail();
if ($sent) {
  $sentContentHtml = combineSentContentHtml($sent);
  $delAllSentHtml = '
  <div class="form-check">
    <input class="form-check-input position-static delAllSent" type="checkbox" aria-label="delAllSent">
  </div>
  ';
  $sentLoadMoreBtn = '<button type="button" class="btn btn-primary btn-lg btn-block loadMore" id="sentLoadMore">'.$tr['loading'].'</button>';
}

$indexbody_content = <<<HTML
<ul class="nav nav-tabs" id="myTab" role="tablist">
  <li class="nav-item mr-2">
    <a class="nav-link mailbox" id="sendMailTab" data-toggle="tab" href="#sendMail" role="tab" aria-controls="home" aria-selected="true"><span class="glyphicon glyphicon-plus mr-1"></span>{$tr['draft']}</a>
  <li class="nav-item">
    <a class="nav-link active mailbox show" id="inboxTab" data-toggle="tab" href="#inbox" role="tab" aria-controls="inbox" aria-selected="false">{$tr['inbox']}</a>
  </li>
  <li class="nav-item">
    <a class="nav-link mailbox" id="sentTab" data-toggle="tab" href="#sent" role="tab" aria-controls="sent" aria-selected="false">{$tr['sent']}</a>
  </li>
</ul>
<div class="tab-content" id="myTabContent">
  <!-- sendMailTab Content -->
  <div class="tab-pane" id="sendMail" role="tabpanel" aria-labelledby="sendMailTab">
    <br>
    <form>
      <div class="form-group row">
        <div class="col-sm-2">{$tr['Recipient']}</div>
        <div class="col-sm-3">
          <div class="form-check form-check-inline">
            <input class="form-check-input recipientCheck" type="radio" name="exampleRadios" id="memberCheckbox" value="member" checked>
            <label class="form-check-label" for="memberCheckbox">
              {$tr['Recipient']}
            </label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input recipientCheck" type="radio" name="exampleRadios" id="allMemberCheckbox" value="allMember">
            <label class="form-check-label" for="allMemberCheckbox">
              {$tr['whole website']}
            </label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input recipientCheck" type="radio" name="exampleRadios" id="uploadCsvCheckbox" value="uploadCsv">
            <label class="form-check-label" for="uploadCsvCheckbox">
              {$tr['import into excel']}
            </label>
          </div>
        </div>
        <div class="col-sm-7" id="sendMemberCount">{$tr['total']} 0 {$tr['people']}</div>
      </div>
      <div class="form-group row">
        <div class="col-sm-2"></div>
        <div class="col-sm-10" id="sendData">
          <input type="text" class="form-control" id="sendTo" placeholder="{$tr['can be multiple entered, separated by half width commas']}">
        </div>
      </div>
      <div class="form-group row">
        <label for="subject" class="col-sm-2 col-form-label">{$tr['subject']}</label>
        <div class="col-sm-10">
          <input type="text" class="form-control" id="subject" placeholder="{$tr['subject']}" onkeypress="if (event.keyCode == 13) {return false;}">
        </div>
      </div>
      <div class="form-group row">
        <div class="col-sm-2">{$tr['letter content']}</div>
        <div class="col-sm-10">
          <div id="editor"></div>
        </div>
      </div>
    </form>
    <div class="form-group row">
        <div class="col-sm-2"></div>
        <div class="col-sm-10">
          <!-- <button type="button" class="btn btn-primary btn-lg btn-block" id="sendMailBtn">發送</button> -->
          <div class="d-flex bd-highlight my-3">
            <div class="p-2 bd-highlight"><button type="button" class="btn btn-primary" id="sendMailBtn">{$tr['send']}</button></div>
            <div class="ml-auto p-2 bd-highlight"><button type="button" class="btn btn-info" id="previewMailBtn">{$tr['peview']}</button></div>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- inboxTab Content -->
  <div class="tab-pane fade show active" id="inbox" role="tabpanel" aria-labelledby="inboxTab">
    <div class="d-flex bd-highlight my-3">
      <div class="p-2 bd-highlight"><button type="button" class="btn btn-danger btn-sm delMails" id="delInbox" value="delInbox"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button></div>
      <div class="p-2 bd-highlight">
        <div class="btn-group" role="group" aria-label="readButtonGroup">
          <button type="button" class="btn btn-secondary btn-sm" id="markRead">{$tr['mark read']}</button>
          <button type="button" class="btn btn-secondary btn-sm" id="markUnread">{$tr['mark unread']}</button>
        </div>
      </div>


      <div class="ml-auto p-2 bd-highlight">
        <form class="form-inline">
          <label class="mr-2" for="inlineFormCustomSelectPref">{$tr['sender']}</label>
          <input class="form-control" id="inboxMsgfromAcc" type="text" placeholder="{$tr['input account of sender']}">
          <label class="mr-2 ml-2" for="inlineFormCustomSelectPref">{$tr['subject']}</label>
          <input class="form-control" id="inboxSubject" type="text" placeholder="{$tr['input keywords of subject']}">
          <label class="mr-2 ml-2" for="inlineFormCustomSelectPref">{$tr['date']}</label>
          <select class="form-control dateSelect" id="inboxDateSelect">
            <option value="all">{$tr['all']}</option>
            <option value="yesterday">{$tr['yesterday']}</option>
            <option value="today">{$tr['today']}</option>
            <option value="thisWeek">{$tr['this week']}</option>
            <option value="thisMonth">{$tr['this month']}</option>
          </select>
          <div class="form-check ml-2">
            <input class="form-check-input" type="checkbox" id="read" checked>
            <label class="form-check-label" for="read">{$tr['read']}</label>
          </div>
          <div class="form-check ml-2">
            <input class="form-check-input" type="checkbox" id="unread" checked>
            <label class="form-check-label" for="unread">{$tr['unread']}</label>
          </div>
          <button type="button" class="btn btn-primary btn-sm ml-2 search" id="inboxSearch"><span class="glyphicon glyphicon-search" aria-hidden="true"></span>{$tr['search'] }</button>
        </form>
      </div>
    </div>
    <table class="table table-hover" id="inboxTable">
      <thead>
        <tr>
          <th scope="col">
            {$delAllInboxHtml}
          </th>
          <th scope="col">{$tr['sender']}</th>
          <th scope="col">{$tr['subject']}</th>
          <th scope="col">{$tr['date of sending']}</th>
        </tr>
      </thead>
      <tbody id="inboxContent">
        {$inboxContentHtml}
      </tbody>
    </table>
    {$inboxLoadMoreBtn}
  </div>

  <!-- sentTab Content -->
  <div class="tab-pane fade" id="sent" role="tabpanel" aria-labelledby="sentTab">
    <div class="d-flex bd-highlight my-3">
      <div class="mr-auto p-2 bd-highlight"><button type="button" class="btn btn-danger btn-sm delMails" id="delSent" value="delSent"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button></div>
      <div class="p-2 bd-highlight">
        <form class="form-inline">
          <label class="mr-2" for="inlineFormCustomSelectPref">{$tr['Recipient']}</label>
          <input class="form-control" id="sentMsgtoAcc" type="text" placeholder="{$tr['input account of recipient']}">
          <label class="mr-2 ml-2" for="inlineFormCustomSelectPref">{$tr['subject']}</label>
          <input class="form-control" id="sentSubject" type="text" placeholder="{$tr['input keywords of subject']}">
          <label class="mr-2 ml-2" for="inlineFormCustomSelectPref">{$tr['date']}</label>
          <select class="form-control dateSelect" id="sentDateSelect">
            <option value="all">{$tr['all']}</option>
            <option value="yesterday">{$tr['yesterday']}</option>
            <option value="today">{$tr['today']}</option>
            <option value="thisWeek">{$tr['this week']}</option>
            <option value="thisMonth">{$tr['this month']}</option>
          </select>
          <button type="button" class="btn btn-primary btn-sm ml-2 search" id="sentSearch"><span class="glyphicon glyphicon-search" aria-hidden="true"></span>{$tr['search'] }</button>
        </form>
      </div>
    </div>
    <table class="table table-hover" id="sentTable">
      <thead>
        <tr>
          <th scope="col">
            {$delAllSentHtml}
          </th>
          <th scope="col">{$tr['Recipient']}</th>
          <th scope="col">{$tr['subject']}</th>
          <!-- <th scope="col">寄件人</th> -->
          <th scope="col">{$tr['date of sending']}</th>
        </tr>
      </thead>
      <tbody id="sentContent">
        {$sentContentHtml}
      </tbody>
    </table>
    {$sentLoadMoreBtn}
  </div>
</div>

<!-- Member List Modal -->
<div class="modal fade" id="memberListModal" tabindex="-1" role="dialog" aria-labelledby="memberListModalTitle" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="memberListModalTitle"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <table class="table table-hover" id="memberListTable">
          <thead id="memberListCol"></thead>
          <tbody id="memberListContent"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">{$tr['off']}</button>
      </div>
    </div>
  </div>
</div>

<!-- Mail Detail Modal -->
<div class="modal fade" id="mailDetailModal" tabindex="-1" role="dialog" aria-labelledby="mailDetailTitle" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mailDetailTitle"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="mailDetailContent"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger delMails" id="delMail">{$tr['delete']}</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">{$tr['off']}</button>
      </div>
    </div>
  </div>
</div>

<!-- upload csv modal -->
<div class="modal fade" id="uploadCsvModal" tabindex="-1" role="dialog" aria-labelledby="uploadCsvModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title clearfix">{$tr['import message']}</h5>
        <button type="button" class="close pull-right" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-12">
            <form id="csv-submit-form" class="form-inline" method="post">
              <div class="input-group input-group-sm mr-1 my-1">
                <div class="input-group-addon">Excel</div>
                <div class="input-group-addon">
                  <input type="file" class="form-control" name="csv" id="csvfile">
                </div>
              </div>
              <button type="button" class="btn btn-primary mr-1 my-1" id="uploadCsv">{$tr['uploading']}</button>
              <a class="btn btn-primary" href="sendmail_action.php?a=csvTemplate" role="button">{$tr['getting excel template']}</a>
            </form>
          </div>
          <div class="col-12" style="height:15px;"></div>
          <div id="csv-upload-progress" class="col-12"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- preview mail modal -->
<div class="modal fade bd-example-modal-sm" id="previewMailModal" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewMailTitle">{$tr['preview the mail']}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="previewMail"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">{$tr['off']}</button>
      </div>
    </div>
  </div>
</div>
<!-- <div id="preview_result"></div> -->
HTML;

$extend_head = <<<HTML
<script src="in/ckeditor180712/ckeditor.js"></script>
<script src="in/js/clipboard.min.js"></script>
<script src="in/jquery.blockUI.js"></script>
<script src="in/js/mail.js"></script>
<script src="in/js/sendmail.js"></script>
<script>
var csrf = '{$csrftoken}';
</script>

<style>
#inboxContent tr.unread {
    background: rgba(227, 226, 226, 0.47843);
}

button.mailDetail.delete {
    text-decoration: line-through;
}
</style>
HTML;

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
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title.'<p id="mail_mq" class="mb-0 ml-auto float-right" style="color: #dc3545; display: none;"></p>';
// 主要內容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");