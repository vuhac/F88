<?php
// ----------------------------------------------------------------------------
// Features:	站內訊息
// File Name:	message.php
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

require_once dirname(__FILE__) ."/lib_internal_message.php";

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
$function_title 		= '站内讯息';
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';


function combine_memberlist_html($acc, $isfirst, $unread_acc_list)
{
  $active = ($isfirst == '0') ? 'active' : '';
  $aria_selected = ($isfirst == '0') ? 'true' : 'false';

  $unread_html = '';

  if ($unread_acc_list) {
    if ($isfirst == '0' && array_key_exists($acc, $unread_acc_list)) {
      update_readtime($acc);
    } else {
      $unread_html = (!array_key_exists($acc, $unread_acc_list)) ? '' : combine_unread_msg_html($acc, $unread_acc_list[$acc]);
    }
  }
  // $unread_html = ($unread_acc_list && !array_key_exists($acc, $unread_acc_list)) ? '' : combine_unread_msg_html($acc, $unread_acc_list[$acc]);

  $html = <<<HTML
  <a class="nav-link tab {$active}" id="{$acc}_tab" data-toggle="pill" href="#{$acc}_msg_area" role="tab" aria-controls="{$acc}_msg_area" aria-selected="{$aria_selected}">
  {$acc}
  {$unread_html}
  </a>
HTML;

  return $html;
}

function combine_unread_msg_html($acc, $count)
{
  $html = '<span class="badge badge-light float-right" id="'.$acc.'_unread_count">'.$count.'</span>';

  return $html;
}

function combine_msgarea_html($acc, $isfirst)
{
  $active = ($isfirst == '0') ? 'active' : '';

  $msgs = get_msg_byacc($acc);
  $total_msg_number = count($msgs);
  $msgs = array_slice($msgs, 0, 30);

  $msg_html = combine_msg_html($acc, $msgs);
  $page_number_html = combine_page_number_html($acc, $total_msg_number);

  $html = <<<HTML
  <div class="tab-pane cotent_pane fade show {$active}" id="{$acc}_msg_area" role="tabpanel" aria-labelledby="{$acc}_tab">
    <div class="btn-group float-right" role="group" aria-label="change_page_btn_group">
      {$page_number_html}&nbsp;&nbsp;
      <button type="button" class="btn btn-sm btn-secondary last_page" data-toggle="tooltip" data-placement="bottom" title="較新" name="{$acc}_lastpage_0_{$total_msg_number}" id="{$acc}_lastpage_btn"><span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span></button>
      <button type="button" class="btn btn-sm btn-secondary next_page" data-toggle="tooltip" data-placement="bottom" title="較舊" name="{$acc}_nextpage_30_{$total_msg_number}" id="{$acc}_nextpage_btn"><span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span></button>
    </div>
    <br>
    <br>
    <div class="msgs" id="{$acc}_msgs">
    {$msg_html}
    </div>
  </div>
HTML;

  return $html;
}

function combine_page_number_html($acc, $total)
{
  $present = ($total < 30) ? $total : '30';
  $html = '<h5 id="'.$acc.'_col_number">1-'.$present.'列 (共'.$total.'列)</h5>';

  return $html;
}

function combine_msg_html($acc, $msgs)
{
  global $stationmail;

  $html = '';

  // unset($msgs[0]);
  foreach ($msgs as $v) {
    // $html .= ($v->msgfrom == $stationmail['sendto_system_cs']) ? combine_csmsg_html($acc, $v) : combine_usermsg_html($acc, $v);
    $html_arr[] = ($v->msgfrom == $stationmail['sendto_system_cs']) ? combine_csmsg_html($acc, $v) : combine_usermsg_html($acc, $v);
  }

  $html_arr = array_reverse($html_arr);
  foreach ($html_arr as $v) {
    $html .= $v;
  }


  return $html;
}

function combine_csmsg_html($acc, $msg)
{
  $isread = (!empty($msg->readtime)) ? '<span>Read</span>&nbsp;&nbsp;' : '';
  $isDelete = ($msg->status == 0) ? '<span>Delete</span>&nbsp;&nbsp;' : '';

  $html = <<<HTML
  <div class="col text-right {$acc}_msg">
    <p class="agent_avatar_p">
      <span>GPK 客服</span>&nbsp;&nbsp;
      <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-earphone" disabled="disabled"></button>
    </p>
    <p class="bg-info" style="word-break: break-all;word-wrap: break-word;">
      <span>{$msg->message}</span>
      {$isDelete}
      {$isread}
      <span>{$msg->cst_sendtime}</span>
    </p>
  </div>
  <br>
HTML;

  return $html;
}

function combine_usermsg_html($acc, $msg)
{
  $isDelete = ($msg->status == 0) ? '<span>Delete</span>' : '';

  $html = <<<HTML
  <div class="col text-lift {$acc}_msg">
    <p class="inbox_member_avatar_p">
      <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-user" disabled="disabled"></button>&nbsp;&nbsp;
      <span>会员 {$msg->msgfrom}</span>
    </p>
    <p class="bg-success" style="word-break: break-all;word-wrap: break-word;">
      <strong>{$msg->subject}</strong><br>
      <span>{$msg->message}</span>
      <span>{$msg->cst_sendtime}</span>&nbsp;&nbsp;
      {$isDelete}
    </p>
  </div>
  <br>
HTML;

  return $html;
}


if(!isset($_SESSION['agent']) || $_SESSION['agent']->therole != 'R') {
  header('Location:./home.php');
  die();
}

$memberlist_html = '';
$msgarea_html = '';

$members = get_memberlist();

if ($members) {
  $acc_str = implode("','", $members);
  $unread_acc_list = get_unread_msg_byacc($acc_str);

  foreach ($members as $k => $v) {
    $acc_list[$v.'_tab'] = $v;
    $memberlist_html .= combine_memberlist_html($v, $k, $unread_acc_list);
    if ($k == '0') {
      $msgarea_html .= combine_msgarea_html($v, $k);
    }
  }
}

$acc_list_json = json_encode($acc_list);

$indexbody_content = <<<HTML
<div class="bd-example bd-example-tabs">
  <div class="row">
    <div class="col-4 m_list">
      <div class="float-lift">
        <button type="button" class="btn btn-info" data-toggle="modal" data-target="#add_msg_modal">新增訊息</button>
      </div>
      <br>
      <!-- X search cancel html-->
      <!--
      <div class="btn-group">
        <input id="searchinput" type="search" class="form-control">
        <span id="searchclear" class="glyphicon glyphicon-remove-circle"></span>
      </div>
      -->
      <form class="form-inline">
        <input class="form-control mr-sm-2" type="search" placeholder="Search" aria-label="Search" id="search_input">
        <button class="btn btn-success my-2 mr-2 my-sm-0" type="button" id="search_submit">搜寻</button>
        <button class="btn btn-danger my-sm-0" type="button" id="search_cancel">取消</button>
      </form>
      <br>
      <div class = "tabs">
        <div class="nav flex-column nav-pills" id="tabs_area" role="tablist" aria-orientation="vertical">
          {$memberlist_html}
        </div>
      </div>
    </div>
    <div class="col-8">
      <div class="tab-content" id="msg_area">
        {$msgarea_html}
      </div>
      <br><br>
      <div class="form-group">
        <textarea class="form-control" id="msg_textarea" rows="3"></textarea>
        <br>
        <button type="button" class="btn btn-success btn-lg btn-block" id="send_msg_btn">送出</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="add_msg_modal" tabindex="-1" role="dialog" aria-labelledby="add_msg_modal_title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h5 class="modal-title" id="modal_title">新增讯息</h5>
      </div>
      <div class="modal-body">
        <form>
          <div class="form-group">
            <label for="stranger_acc" class="col-form-label">致:</label>
            <input type="text" class="form-control" id="stranger_acc">
          </div>
          <div class="form-group">
            <label for="stranger_msg" class="col-form-label">讯息内容:</label>
            <textarea class="form-control" id="stranger_msg"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-dismiss="modal">取消</button>
        <button type="button" class="btn btn-success" id="send_stranger_msg_btn">送出</button>
      </div>
    </div>
  </div>
</div>

<div id="submit_result"></div>
HTML;

$extend_head = <<<STYLE
<style>
div.tabs {
    height: 600px;
    overflow-y: scroll;
}

div.msgs {
    height: 500px;
    overflow-y: scroll;
}
</style>
STYLE;

// $extend_head = <<<STYLE
// <style>
// #searchinput {
//   width: 200px;
// }
// #searchclear {
//   position: absolute;
//   right: 5px;
//   top: 0;
//   bottom: 0;
//   height: 14px;
//   margin: auto;
//   font-size: 14px;
//   cursor: pointer;
//   color: #ccc;
// }
// </style>
// STYLE;

$extend_js = <<<JS
<script>
$(document).ready(function() {
  $('.msgs').scrollTop(999999);

  $('#search_cancel').click(function(){
    location.reload();
  });
});

$(document).on("click",'#send_msg_btn',function(){
  var acc = $('.tab.active').attr('id').split('_tab');
  var msg = $('#msg_textarea').val();
  var first_tab = $('.tab').first().attr('id');

  if (msg == '') {
    return;
  }

  $.ajax({
    type: 'POST',
    url: 'message_action.php',
    data: {
      action: 'sned_msg',
      user_acc: acc[0],
      message: msg
    },
    success: function(resp) {
      var res = JSON.parse(resp);
      if (res.result === 'success') {
        $('#'+acc[0]+'_tab').remove();

        $('#tabs_area').prepend(`
        <a class="nav-link tab active" id="`+acc[0]+`_tab" data-toggle="pill" href="#`+acc[0]+`_msg_area" role="tab" aria-controls="`+acc[0]+`_msg_area" aria-selected="true">`+acc[0]+`</a>
        `)

        var lastpage_btn_id = $('#'+acc[0]+'_lastpage_btn').attr('name').split('_');
        var total_msg_num = parseInt(lastpage_btn_id.pop()) + 1;
        var page = parseInt(lastpage_btn_id.pop());
        var msg_count = parseInt($('.'+acc[0]+'_msg').length);

        if (page === 0) {
          if ((msg_count + 1) > 30) {
            $('#'+acc[0]+'_msgs div:eq(29)').after(`
            <br>
            <div class="col text-right `+acc[0]+`_msg">
              <p class="agent_avatar_p">
                <span>GPK 客服</span>&nbsp;&nbsp;
                <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-earphone" disabled="disabled"></button>
              </p>
              <p class="bg-info" style="word-break: break-all;word-wrap: break-word;">
                <span>`+res.message+`</span>
                <span>`+res.cst_sendtime+`</span>
              </p>
            </div>
            `);

            $('.'+acc[0]+'_msg').first().remove();
          } else {
            $('#'+acc[0]+'_msgs').append(`
            <div class="col text-right `+acc[0]+`_msg">
              <p class="agent_avatar_p">
                <span>GPK 客服</span>&nbsp;&nbsp;
                <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-earphone" disabled="disabled"></button>
              </p>
              <p class="bg-info" style="word-break: break-all;word-wrap: break-word;">
                <span>`+res.message+`</span>
                <span>`+res.cst_sendtime+`</span>
              </p>
            </div>
            <br>
            `)
          }

          var s_page_num = 1;
          var e_page_num = ((msg_count + 1) > 30) ? 30 : msg_count;
        } else {
          var s_page_num = page + 1;
          var e_page_num = (msg_count === 30) ? (page + 30) : (page + msg_count) + 1;
        }

        $('#'+acc[0]+'_lastpage_btn').attr('name', acc[0]+'_lastpage_'+page+'_'+total_msg_num);
        $('#'+acc[0]+'_nextpage_btn').attr('name', acc[0]+'_nextpage_'+(page + 30)+'_'+total_msg_num);

        var col_text = $('#'+acc[0]+'_col_number').text(s_page_num+'-'+e_page_num+'列 (共'+total_msg_num+'列)');

        $('.msgs').scrollTop(999999);
        $('#msg_textarea').val('');

      } else {
        alert(res.message);
      }
    }
  });
});

$(document).on("click",'#send_stranger_msg_btn',function(){
  var choose_acc = $('.tab.active').attr('id').split('_tab');
  var acc = $('#stranger_acc').val();
  var msg = $('#stranger_msg').val();

  var accs_json = JSON.parse('{$acc_list_json}');
  var accs_arr = $.map(accs_json, function(item) {
    return item;
  });

  if (acc == '' || msg == '') {
    return;
  }

  $.ajax({
    type: 'POST',
    url: 'message_action.php',
    data: {
      action: 'send_msg_tostranger',
      user_acc: acc,
      message: msg
    },
    success: function(resp) {
      var res = JSON.parse(resp);
      if (res.result === 'success') {
        var resp_acc = $.unique(res.accs.split(','));

        $.each(resp_acc, function(k, v) {
          // var last_acc = (k === 0) ? choose_acc[0] : resp_acc[k-1];
          var active = '';
          if ($.inArray(v, accs_arr) >= 0) {
            if ($('.tab.active').attr('id') == v+'_tab') {
              var msg_count = parseInt($('.'+v+'_msg').length);
              var lastpage_btn_id = $('#'+v+'_lastpage_btn').attr('name').split('_');
              var total_msg_num = parseInt(lastpage_btn_id.pop()) + 1;
              var page = parseInt(lastpage_btn_id.pop());

              if (page === 0) {
                if ((msg_count + 1) > 30) {
                  $('#'+v+'_msgs div:eq(29)').after(`
                  <br>
                  <div class="col text-right `+v+`_msg">
                    <p class="agent_avatar_p">
                      <span>GPK 客服</span>&nbsp;&nbsp;
                      <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-earphone" disabled="disabled"></button>
                    </p>
                    <p class="bg-info" style="word-break: break-all;word-wrap: break-word;">
                      <span>`+res.message+`</span>
                      <span>`+res.cst_sendtime+`</span>
                    </p>
                  </div>
                  `);

                  $('.'+v+'_msg').first().remove();
                } else {
                  $('#'+v+'_msgs').append(`
                  <div class="col text-right `+v+`_msg">
                    <p class="agent_avatar_p">
                      <span>GPK 客服</span>&nbsp;&nbsp;
                      <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-earphone" disabled="disabled"></button>
                    </p>
                    <p class="bg-info" style="word-break: break-all;word-wrap: break-word;">
                      <span>`+res.message+`</span>
                      <span>`+res.cst_sendtime+`</span>
                    </p>
                  </div>
                  <br>
                  `)
                }

                var s_page_num = 1;
                var e_page_num = ((msg_count + 1) > 30) ? 30 : msg_count;
              } else {
                var s_page_num = page + 1;
                var e_page_num = (msg_count === 30) ? (page + 30) : (page + msg_count) + 1;
              }

              $('#'+v+'_lastpage_btn').attr('name', v+'_lastpage_'+page+'_'+total_msg_num);
              $('#'+v+'_nextpage_btn').attr('name', v+'_nextpage_'+(page + 30)+'_'+total_msg_num);

              var col_text = $('#'+v+'_col_number').text(s_page_num+'-'+e_page_num+'列 (共'+total_msg_num+'列)');
              var active = 'active show';
            }

            $('#'+v+'_tab').remove();
          } else {
            $('#msg_area').prepend(`
            <div class="tab-pane cotent_pane fade" id="`+v+`_msg_area" role="tabpanel" aria-labelledby="`+v+`_tab">
              <div class="btn-group float-right" role="group" aria-label="change_page_btn_group">
                <h5 id="`+v+`_col_number">1-1列 (共1列)</h5>&nbsp;&nbsp;
                <button type="button" class="btn btn-sm btn-secondary last_page" data-toggle="tooltip" data-placement="bottom" title="較新" name="`+v+`_lastpage_0_1" id="`+v+`_lastpage_btn"><span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span></button>
                <button type="button" class="btn btn-sm btn-secondary next_page" data-toggle="tooltip" data-placement="bottom" title="較舊" name="`+v+`_nextpage_30_1" id="`+v+`_nextpage_btn"><span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span></button>
              </div>
              <br>
              <br>
              <div class="msgs" id="`+v+`_msgs">
                <div class="col text-right `+v+`_msg">
                  <p class="agent_avatar_p">
                    <span>GPK 客服</span>&nbsp;&nbsp;
                    <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-earphone" disabled="disabled"></button>
                  </p>
                  <p class="bg-info" style="word-break: break-all;word-wrap: break-word;">
                    <span>`+res.message+`</span>
                    <span>`+res.cst_sendtime+`</span>
                  </p>
                </div>
              </div>
            </div>
            `)
          }

          $('#tabs_area').prepend(`
          <a class="nav-link tab `+active+`" id="`+v+`_tab" data-toggle="pill" href="#`+v+`_msg_area" role="tab" aria-controls="`+v+`_msg_area" aria-selected="true">`+v+`</a>
          `)

          if ($('.'+v+'_msg').length > 30) {
            $('.'+v+'_msg').last().remove();
          }
        });

        $('.msgs').scrollTop(999999);
        $('#add_msg_modal').modal('toggle');
        $('#stranger_acc').val('');
        $('#stranger_msg').val('');
      } else {
        alert(res.message);
      }
    }
  });
});

$(document).on("click",'#search_submit',function(){
  var search_acc = $('#search_input').val();

  if (search_acc == '') {
    return;
  }

  $.ajax({
    type: 'POST',
    url: 'message_action.php',
    data: {
      action: 'search_user',
      user_acc: search_acc
    },
    success: function(resp) {
      var res = JSON.parse(resp);
      if (res.result === 'success') {
        var colnumber = (res.total_msg_count > 30) ? '30' : res.total_msg_count;

        $('.tab').remove();

        $('#tabs_area').prepend(`
        <a class="nav-link tab active" id="`+search_acc+`_tab" data-toggle="pill" href="#`+search_acc+`_msg_area" role="tab" aria-controls="`+search_acc+`_msg_area" aria-selected="true">`+search_acc+`</a>
        `)

        $('.cotent_pane').remove();

        $('#msg_area').prepend(`
        <div class="tab-pane cotent_pane fade show active" id="`+search_acc+`_msg_area" role="tabpanel" aria-labelledby="`+search_acc+`_tab">
          <div class="btn-group float-right" role="group" aria-label="change_page_btn_group">
            <h5 id="`+search_acc+`_col_number">1-`+colnumber+`列 (共`+res.total_msg_count+`列)</h5>&nbsp;&nbsp;
            <button type="button" class="btn btn-sm btn-secondary last_page" data-toggle="tooltip" data-placement="bottom" title="較新" name="`+search_acc+`_lastpage_0_`+res.total_msg_count+`" id="`+search_acc+`_lastpage_btn"><span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span></button>
            <button type="button" class="btn btn-sm btn-secondary next_page" data-toggle="tooltip" data-placement="bottom" title="較舊" name="`+search_acc+`_nextpage_30_`+res.total_msg_count+`" id="`+search_acc+`_nextpage_btn"><span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span></button>
          </div>
          <br>
          <br>
          <div class="msgs" id="`+search_acc+`_msgs"></div>
        </div>
        `)

        $.each(res.message, function(k, v) {
          if (v.msgfrom == search_acc) {
            $('#'+search_acc+'_msgs').append(`
            <div class="col text-lift `+search_acc+`_msg">
              <p class="inbox_member_avatar_p">
                <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-user" disabled="disabled"></button>&nbsp;&nbsp;
                <span>会员 `+v.msgfrom+`</span>
              </p>
              <p class="bg-success" style="word-break: break-all;word-wrap: break-word;">
                <strong>`+v.subject+`</strong><br>
                <span>`+v.message+`</span>
                <span>`+v.sendtime+`</span>
              </p>
            </div>
            <br>
            `)
          } else {
            $('#'+search_acc+'_msgs').append(`
            <div class="col text-right `+search_acc+`_msg">
              <p class="agent_avatar_p">
                <span>GPK 客服</span>&nbsp;&nbsp;
                <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-earphone" disabled="disabled"></button>
              </p>
              <p class="bg-info" style="word-break: break-all;word-wrap: break-word;">
                <span>`+v.message+`</span>
                <span>`+((v.readtime != null) ? 'Read' : '')+`</span>&nbsp;&nbsp;
                <span>`+v.sendtime+`</span>
              </p>
            </div>
            <br>
            `)
          }
        });

        $('.msgs').scrollTop(999999);
      } else {
        alert(res.message);
      }
    }
  });
});

$(document).on("click",'.last_page, .next_page',function(){
  var pdata = $(this).attr('name').split('_');
  var total_msg_number = parseInt(pdata.pop());

  if (pdata[2] == 0 || pdata[2] > total_msg_number) {
    return;
  }

  $.ajax({
    type: 'POST',
    url: 'message_action.php',
    data: {
      action: 'change_page',
      user_acc: pdata[0],
      page_action: pdata[1],
      page: pdata[2],
      total_msg_number: total_msg_number
    },
    success: function(resp) {
      var res = JSON.parse(resp);
      if (res.result === 'success') {
        $('#'+pdata[0]+'_msg_area').remove();

        $('#msg_area').prepend(`
        <div class="tab-pane cotent_pane fade show active" id="`+pdata[0]+`_msg_area" role="tabpanel" aria-labelledby="`+pdata[0]+`_tab">
          <div class="btn-group float-right" role="group" aria-label="change_page_btn_group">
            <h5 id="`+pdata[0]+`_col_number">`+res.start_msg_number+`-`+res.end_msg_number+`列 (共`+total_msg_number+`列)</h5>&nbsp;&nbsp;
            <button type="button" class="btn btn-sm btn-secondary last_page" data-toggle="tooltip" data-placement="bottom" title="較新" name="`+pdata[0]+`_lastpage_`+res.lastpage+`_`+total_msg_number+`" id="`+pdata[0]+`_lastpage_btn"><span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span></button>
            <button type="button" class="btn btn-sm btn-secondary next_page" data-toggle="tooltip" data-placement="bottom" title="較舊" name="`+pdata[0]+`_nextpage_`+res.nextpage+`_`+total_msg_number+`" id="`+pdata[0]+`_nextpage_btn"><span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span></button>
          </div>
          <br>
          <br>
          <div class="msgs" id="`+pdata[0]+`_msgs"></div>
        </div>
        `);

        $.each(res.message, function(k, v) {
          var isRead = (v.readtime != null) ? '<span>Read</span>&nbsp;&nbsp;' : '';
          var isDelete = (v.status == 0) ? '<span>Delete</span>&nbsp;&nbsp;' : '';

          if (v.msgfrom == pdata[0]) {
            $('#'+pdata[0]+'_msgs').append(`
            <div class="col text-lift `+pdata[0]+`_msg">
              <p class="inbox_member_avatar_p">
                <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-user" disabled="disabled"></button>&nbsp;&nbsp;
                <span>会员 `+v.msgfrom+`</span>
              </p>
              <p class="bg-success" style="word-break: break-all;word-wrap: break-word;">
                <strong>`+v.subject+`</strong><br>
                <span>`+v.message+`</span>
                <span>`+v.sendtime+`</span>
                `+isDelete+`
              </p>
            </div>
            <br>
            `)
          } else {
            $('#'+pdata[0]+'_msgs').append(`
            <div class="col text-right `+pdata[0]+`_msg">
              <p class="agent_avatar_p">
                <span>GPK 客服</span>&nbsp;&nbsp;
                <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-earphone" disabled="disabled"></button>
              </p>
              <p class="bg-info" style="word-break: break-all;word-wrap: break-word;">
                <span>`+v.message+`</span>
                `+isDelete+`
                `+isRead+`
                <span>`+v.sendtime+`</span>
              </p>
            </div>
            <br>
            `)
          }
        });

        $('.msgs').scrollTop(999999);
      } else {
        alert(res.message);
      }
    }
  });
});

$(document).on('click','.tab',function(){
  var choose_acc = $('.cotent_pane.active').attr('id').split('_msg_area');
  var tab_id = $(this).attr('id').split('_');
  var action = '';

  if ($('#'+tab_id[0]+'_unread_count').length != 0) {
    var action = 'update_readtime'
  }

  if ($('#'+tab_id[0]+'_msg_area').length === 0) {
    var action = 'update_msg'
  }

  if ($('#'+tab_id[0]+'_unread_count').length != 0 && $('#'+tab_id[0]+'_msg_area').length === 0) {
    var action = 'update_tabdata'
  }

  if (action == '' || tab_id[0] == '') {
    return;
  }

  $.ajax({
    type: 'POST',
    url: 'message_action.php',
    data: {
      action: action,
      user_acc: tab_id[0],
    },
    success: function(resp) {
      var res = JSON.parse(resp);
      if (res.result === 'success') {
        if (action != 'update_msg') {
          $('#'+tab_id[0]+'_unread_count').remove();
        }

        if (action != 'update_readtime') {
          $('#'+choose_acc[0]+'_msg_area').removeClass('active');
          var colnumber = (res.total_msg_count > 30) ? '30' : res.total_msg_count;

          $('#msg_area').prepend(`
          <div class="tab-pane cotent_pane fade show active" id="`+tab_id[0]+`_msg_area" role="tabpanel" aria-labelledby="`+tab_id[0]+`_tab">
            <div class="btn-group float-right" role="group" aria-label="change_page_btn_group">
              <h5 id="`+tab_id[0]+`_col_number">1-`+colnumber+`列 (共`+res.total_msg_count+`列)</h5>&nbsp;&nbsp;
              <button type="button" class="btn btn-sm btn-secondary last_page" data-toggle="tooltip" data-placement="bottom" title="較新" name="`+tab_id[0]+`_lastpage_0_`+res.total_msg_count+`" id="`+tab_id[0]+`_lastpage_btn"><span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span></button>
              <button type="button" class="btn btn-sm btn-secondary next_page" data-toggle="tooltip" data-placement="bottom" title="較舊" name="`+tab_id[0]+`_nextpage_30_`+res.total_msg_count+`" id="`+tab_id[0]+`_nextpage_btn"><span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span></button>
            </div>
            <br>
            <br>
            <div class="msgs" id="`+tab_id[0]+`_msgs"></div>
          </div>
          `);

          $.each(res.message, function(k, v) {
            if (v.msgfrom == tab_id[0]) {
              $('#'+tab_id[0]+'_msgs').append(`
              <div class="col text-lift `+tab_id[0]+`_msg">
                <p class="inbox_member_avatar_p">
                  <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-user" disabled="disabled"></button>&nbsp;&nbsp;
                  <span>会员 `+v.msgfrom+`</span>
                </p>
                <p class="bg-success" style="word-break: break-all;word-wrap: break-word;">
                  <strong>`+v.subject+`</strong><br>
                  <span>`+v.message+`</span>
                  <span>`+v.sendtime+`</span>
                </p>
              </div>
              <br>
              `)
            } else {
              $('#'+tab_id[0]+'_msgs').append(`
              <div class="col text-right `+tab_id[0]+`_msg">
                <p class="agent_avatar_p">
                  <span>GPK 客服</span>&nbsp;&nbsp;
                  <button type="button" class="btn btn-default btn-lg glyphicon glyphicon-earphone" disabled="disabled"></button>
                </p>
                <p class="bg-info" style="word-break: break-all;word-wrap: break-word;">
                  <span>`+v.message+`</span>
                  <span>`+((v.readtime != null) ? 'Read' : '')+`</span>&nbsp;&nbsp;
                  <span>`+v.sendtime+`</span>
                </p>
              </div>
              <br>
              `)
            }
          });

          $('.msgs').scrollTop(999999);
        }
      } else {
        alert(res.message);
      }
    }
  });

});
</script>
JS;

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
include("template/beadmin.tmpl.php");


?>