<?php
// ----------------------------------------------------------------------------
// Features:    後台 -- 代理商申請審核頁面
// File Name:    agent_review_info.php
// Author:        侑骏
// Related:        對應後台 agent_review.php
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

if(isset($_GET['id'])) {
    $action = filter_var($_GET['id'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的測試');
}
// var_dump($action);

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
$function_title = $tr['Agent application for review'];
// 擴充 head 內的 css or js
$extend_head = '';
// 放在結尾的 js
$extend_js = '';
// body 內的主要內容
$indexbody_content = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['Members and Agents'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($action) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {


  // 使用者所在的時區，sql 依據所在時區顯示 time
    // -------------------------------------
    if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
      $tz = $_SESSION['agent']->timezone;
    }else{
      $tz = '+08';
    }
    // 轉換時區所要用的 sql timezone 參數
    $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
    $tzone = runSQLALL($tzsql);

    if($tzone[0]==1){
      $tzonename = $tzone[1]->name;
    }else{
      $tzonename = 'posix/Etc/GMT-8';
    }

    // 搜寻 root_deposit_review 單筆資料
    $agent_review_sql = <<<SQL
        SELECT *, to_char(("applicationtime" AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as "applicationtime_tz"
        FROM "root_agent_review"
        WHERE id = '$action'
    SQL;
    // var_dump($agent_review_sql);exit;

  $agent_review_result = runSQLALL($agent_review_sql);
//   echo '<pre>', var_dump($agent_review_result), '</pre>'; exit();
  if($agent_review_result[0] == 1){

    // 判斷審核的狀態
    if($agent_review_result[1]->status == 2){
      $depositing_status_html = "
      <button id=\"agreen_ok\" class=\"btn btn-success btn-sm active\" role=\"button\">{$tr['agree']}</button>
      <button id=\"agreen_cancel\"class=\"btn btn-danger btn-sm active\" role=\"button\">{$tr['disagree']}</button>
      ";
    }else if($agent_review_result[1]->status == 1){
      $depositing_status_html = "
      <label class=\"label label-warning role=\"label\">{$tr['seq examination passed']}</label>
      ";
    }else{
      $depositing_status_html = "
      <label class=\"label label-danger role=\"label\">{$tr['application reject']}</label>
      ";
    }

    // 列出資料, 主表格架構
    $show_list_tbody_html = '';

    // 会员帐号
    $show_list_tbody_html = $show_list_tbody_html.'
    <tr>
      <td><strong>'.$tr['account'].'</strong></td>
      <td>'.$agent_review_result[1]->account.'</td>
      <td></td>
    </tr>
    ';

    // 付款金额
    $show_list_tbody_html = $show_list_tbody_html.'
    <tr>
      <td><strong>'.$tr['payment amount'].'</strong></td>
      <td>'.$agent_review_result[1]->amount.'</td>
      <td></td>
    </tr>
    ';

    // 存款人姓名
    $show_list_tbody_html = $show_list_tbody_html.'
    <tr>
      <td><strong>'.$tr['registered_name'].'</strong></td>
      <td>'.$agent_review_result[1]->realname.'</td>
      <td></td>
    </tr>
    ';

    // 申请时间
    // $agent_review_result[1]->applicationtime_tz
    $show_list_tbody_html = $show_list_tbody_html.'
    <tr>
      <td><strong>'.$tr['application time'].'</strong></td>
      <td>'.gmdate('Y-m-d H:i:s', strtotime($agent_review_result[1]->applicationtime_tz)+-4 * 3600).'</td>
      <td></td>
    </tr>
    ';

    $sns1 = $protalsetting["custom_sns_rservice_1"]??$tr['sns1'];
    $sns2 = $protalsetting["custom_sns_rservice_2"]??$tr['sns2'];
    $contactuser_html = '<p>
    '.$tr['Cell Phone'].': '.$agent_review_result[1]->mobilenumber.'<br>
    '.$tr['email'].': '.$agent_review_result[1]->email.'<br>
    '.$sns1.': '.$agent_review_result[1]->wechat.'<br>
    '.$sns2.': '.$agent_review_result[1]->qq.'<br>
    </p>';
    // 聯絡方式
    $show_list_tbody_html = $show_list_tbody_html.'
    <tr>
      <td><strong>'.$tr['contact method'].'</strong></td>
      <td>'.$contactuser_html.'</td>
      <td></td>
    </tr>
    ';

    $geoinfo_html = '<p>
    '.$tr['Browser fingerprint'].': <a href="member_log.php?fp='.$agent_review_result[1]->fingerprinting.'" title="找出曾经在系统内的纪录" target="_BLANK">'.$agent_review_result[1]->fingerprinting.'</a><br>
    IP: <a href="http://freeapi.ipip.net/'.$agent_review_result[1]->applicationip.'" target="_BLANK" title="查询IP来源可能地址位置">'.$agent_review_result[1]->applicationip.'</a><br>
    </p>';
    // 地理位置及瀏覽器指紋資訊
    $show_list_tbody_html = $show_list_tbody_html.'
    <tr>
      <td><strong>'.$tr['Geographic location and browser fingerprint'].'</strong></td>
      <td>'.$geoinfo_html.'</td>
      <td>'.$tr['User Geographic Device Information submitted by withdrawal'].'</td>
    </tr>
    ';

    // 對帳處理人員帳號
    $show_list_tbody_html = $show_list_tbody_html.'
    <tr>
      <td><strong>'.$tr['Account processing staff account'].'</strong></td>
      <td>'.$agent_review_result[1]->processingaccount.'</td>
      <td></td>
    </tr>
    ';

    // 對帳完成的時間
    $show_list_tbody_html = $show_list_tbody_html.'
    <tr>
      <td><strong>'.$tr['Reconciliation completed time'].'</strong></td>
      <td>'.$agent_review_result[1]->processingtime.'</td>
      <td></td>
    </tr>
    ';

    // 处理资讯紀錄
    $notes_form_html = '
    <div class="form-group">
      <textarea class="form-control" rows="5" id="notes">'.$agent_review_result[1]->notes.'</textarea>
      <button type="button" class="btn btn-default btn-sm" id="agreen_update_notes">'.$tr['update'].'</button>
    </div>
    ';
    // 处理资讯紀錄
    $show_list_tbody_html = $show_list_tbody_html.'
    <tr>
      <td><strong>'.$tr['info'].'</strong></td>
      <td colspan="2">'.$notes_form_html.'</td>
    </tr>
    ';

    $submit_desc_html = '<p>
    * '.$tr['Agree, update this record immediately and set it as completed.'].'<br>
    * '.$tr['Not agree, the system will automatically return the balance that has been deducted to the customer.'].'<br>
    <p>';
    // 审核状态
    $show_list_tbody_html = $show_list_tbody_html.'
    <tr>
      <td><strong>'.$tr['Approval Status'].'</strong></td>
      <td>'.$depositing_status_html.'</td>
      <td>'.$submit_desc_html.'</td>
    </tr>
    ';

    // 返回上一页
    $show_list_return_html = '<p align="right"><a href="agent_review.php" class="btn btn-success btn-sm active" role="button">'.$tr['go back to the last page'].'</a></p>';

    // 欄位標題
    $show_list_thead_html = '
    <tr>
      <th>'.$tr['field'].'</th>
      <th>'.$tr['content'].'</th>
      <th>'.$tr['Remark'].'</th>
    </tr>
    ';

    // 以表格方式呈現
    $show_list_html = '
    <table class="table">
      <thead>
      '.$show_list_thead_html.'
      </thead>
      <tbody>
      '.$show_list_tbody_html.'
      </tbody>
    </table>
    ';

  }else{
    $logger = $tr['This order number has been processed so far, please do not re-process it.'];
    echo $logger;die();
  }



  // 切成 1 欄版面
    $indexbody_content = '';
    $indexbody_content = $indexbody_content.'
  <div class="row">
        <div class="col-12 col-md-12">
    '.$show_list_html.'
        </div>
    </div>
    <hr>
  '.$show_list_return_html.'
    <div class="row">
        <div id="preview_result"></div>
    </div>
    ';
}else{
  // 沒有登入的顯示提示俊息
    $show_transaction_list_html  = $tr['only management and login mamber'];

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


// 審核狀態按鈕JS
$audit_js = "
$(document).ready(function(){
  // 同意
  $('#agreen_ok').click(function(){
    $('#agreen_ok').attr('disabled', 'disabled');
    var r = confirm('是否确认审核同意?');
    var id = ".$_GET['id'].";
    var notes = $('#notes').val();

    if(r == true){
      $.post('agent_review_action.php?a=agent_review_submit',
        {
          agent_review_id: id,
          agent_notes: notes
        },
        function(result){
          $('#preview_result').html(result);
        }
      )
    }else{
      window.location.reload();
    }
  });
  // 取消
  $('#agreen_cancel').click(function(){
    $('#agreen_cancel').attr('disabled', 'disabled');
    var r = confirm('是否确认审核拒絕?');
    var id = ".$_GET['id'].";
    var notes = $('#notes').val();

    if(r == true){
      $.post('agent_review_action.php?a=agent_review_cancel',
        {
          agent_review_id: id,
          agent_notes: notes
        },
        function(result){
          $('#preview_result').html(result);
        }
      )
    }else{
      window.location.reload();
    }
  });

  // 更新 notes
  $('#agreen_update_notes').click(function(){
    $('#agreen_update_notes').attr('disabled', 'disabled');
    var r = confirm('确定是否更新处理资讯?');
    var id = ".$_GET['id'].";
    var notes = $('#notes').val();
    if(r == true){
      $.post('agent_review_action.php?a=agreen_update_notes',
        {
          agent_review_id: id,
          agent_notes: notes
        },
        function(result){
          $('#preview_result').html(result);
        }
      )
    }else{
      window.location.reload();
    }
  });

});
";
$extend_js = $extend_js."
<script>
".$audit_js."
</script>
";
// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description']         = $tr['host_descript'];
$tmpl['html_meta_author']                     = $tr['host_author'];
$tmpl['html_meta_title']                     = $function_title.'-'.$tr['host_name'];

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
include("template/beadmin.tmpl.php");

?>
