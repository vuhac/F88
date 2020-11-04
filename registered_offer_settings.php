<?php
// ----------------------------------------------------------------------------
// Features:	后台-- 會員端設定 - 優惠管理(註冊送彩金) 
// File Name:	registered_offer_settings.php
// Author:		
// Related:   
// DB Table:  
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主机及资料库设定
require_once dirname(__FILE__) ."/config.php";
// 支援多国语系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自订函式库
require_once dirname(__FILE__) ."/lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步纪录该 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 检查权限是否合法，允许就会放行。否则中止。
agent_permission();
// ----------------------------------------------------------------------------



// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化变数
// 功能标题，放在标题列及meta
$function_title 		= $tr['promotion Offer Editor'].'( '.$tr['register send bonus'].' )';
// 扩充 head 内的 css or js
$extend_head				= '';
// 放在结尾的 js
$extend_js					= '';
// body 内的主要内容
$indexbody_content	= '';
// 目前所在位置 - 配合选单位置让使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['homepage'].'</a></li>
  <li><a href="#">'.$tr['System Management'].'</a></li>
  <li><a href="protal_setting_deltail.php?sn=default">'.$tr['Members client settings'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

$switch_status = (isset($protalsetting['registered_offer_switch_status']) && $protalsetting['registered_offer_switch_status'] == 'on') 
                  ? 'checked' : '';
$gift_amount = (isset($protalsetting['registered_offer_gift_amount'])) ? $protalsetting['registered_offer_gift_amount'] : '0';
$review_amount = (isset($protalsetting['registered_offer_review_amount'])) ? $protalsetting['registered_offer_review_amount'] : '0';

$btn_html = <<<HTML
<p align="right">
  <button id="submit" type="button" data-type="submit" class="btn btn-success">{$tr['Save']}</button>
</p>
HTML;

$indexbody_content = <<<HTML
<table class="table mb-0 special_style_input">
  <tbody>
<tr>
  <td class="border-0">
    <table class="table table-bordered">
      <tr class="active text-center">
        <td width="10%">{$tr['Enabled or not']}</td>
        <td class="info">{$tr['gift amount']}</td>
        <td class="info">{$tr['audit amount']}</td>
      </tr>
      <tr>
        <td>
          <div class="col-12 col-md-12 status-offer-switch pull-left">
            <input id="switch_status" type="checkbox" name="switch_status" class="checkbox_switch" value="0" {$switch_status}/>
            <label for="switch_status" class="label-success"></label>
          </div>
        </td>
        <td>
          <input type="number" class="form-control" placeholder="" id="gift_amount" value="{$gift_amount}">
        </td>
        <td>
          <input type="number" class="form-control" placeholder="" id="review_amount" value="{$review_amount}">
        </td>
      </tr>
    </table>
  </td>
</tr>
</tbody>
</table>
<div id="preview_result"></div>
{$btn_html}
HTML;


$extend_js = <<<HTML
<script>
const submitbtn = document.querySelector('[data-type="submit"]');
submitbtn.addEventListener("click", submit_sed);

//贈送金額
const giftamount = document.getElementById("gift_amount");
//稽核金額
const reviewamount = document.getElementById("review_amount");

function submit_sed(e) {
  // 開關
  const switchs = (document.querySelector('#switch_status').checked) ? 'on' : 'off';
  //贈送金額
  const giftsdata = document.getElementById("gift_amount").value;
  //稽核金額
  const reviewsdata = document.getElementById("review_amount").value;

  if (giftsdata < 0){
    return alert( '贈送金額不可以是負數');
  }

  if (reviewsdata < 0) {
    return alert('稽核金額不可以是負數');
  }

  if (giftsdata == '' || reviewsdata == '') {
    return alert('贈送金額及稽核金額不可為空');
  }

  let data = {
    switchs: switchs,
    gifts: giftsdata,
    reviews:reviewsdata
  };

  sedajax(data);   
}

//ajax
function sedajax(data){  
  $.ajax({ 
    url: "registered_offer_settings_action.php",
    type: "POST",
    data: {
      action: 'edit',
      data: data,
    },
    success:function(result){
      // $('#preview_result').html(result);      
      let data = JSON.parse(result);

      if (data.status == 'success') {
        alert(data.result);
        //贈送金額
        // giftamount.value = data.data['gift_amount'];
        //稽核金額
        // reviewamount.value = data.data['review_amount'];
        //啟用
        // const switchstatus = document.getElementById("switch_status");
        // switchstatus.checked = data.data['switch_status'];
      } else {
        alert(data.result);
      }  
    },
    error:function(){
      alert('{$tr['error, please contact the developer for processing.']}');
    }
 });
}

</script>
HTML;

  // 將 checkbox 堆疊成 switch 的 css
  $extend_head = <<<HTML
  <style>
  .special_style_input .status-offer-switch > input[type=checkbox] {
      visibility:hidden;
  }
  .special_style_input .status-offer-switch > label {
      cursor: pointer;
      height: 0px;
      position: relative;
      width: 40px;
  }
  .special_style_input .status-offer-switch > label::before {
      background: rgb(0, 0, 0);
      box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
      border-radius: 8px;
      content: '';
      height: 16px;
      margin-top: -3px;
      margin-left: 5px;
      position:absolute;
      opacity: 0.3;
      transition: all 0.4s ease-in-out;
      width: 30px;
  }
  .special_style_input .status-offer-switch > label::after {
      background: rgb(255, 255, 255);
      border-radius: 16px;
      box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
      content: '';
      height: 16px;
      left: 0px;
      margin-top: -3px;
      margin-left: 5px;
      position: absolute;
      top: 0px;
      transition: all 0.3s ease-in-out;
      width: 16px;
  }
  .special_style_input .status-offer-switch > input[type=checkbox]:checked + label::before {
      background: inherit;
      opacity: 0.5;
  }
  .special_style_input .status-offer-switch > input[type=checkbox]:checked + label::after {
      background: inherit;
      left: 16px;
  }
  </style>
  HTML;


// ----------------------------------------------------------------------------
// 准备填入的内容
// ----------------------------------------------------------------------------

// 将内容塞到 html meta 的关键字, SEO 加强使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 页面大标题
$tmpl['page_title']								= $menu_breadcrumbs;
// 扩充再 head 的内容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 扩充于档案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 主要内容 -- title
$tmpl['paneltitle_content'] 			= '<div class="d-flex align-items-center"><a href="protal_setting_deltail.php?sn=default" class="btn btn-outline-secondary btn-xs mr-2"><i class="fas fa-reply mr-1"></i>'.$tr['return'].'</a>'.$function_title.'</div>';
// 主要内容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入内容结束。底下为页面的样板。以变数型态塞入变数显示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");
?>
