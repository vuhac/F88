<?php
// ----------------------------------------------------------------------------
// Features: 新增網域.子網域
// File Name:	subdomain_management_create.php
// Author: Neil
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

require_once dirname(__FILE__) ."/lib_subdomain_management.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// 功能標題，放在標題列及meta
$function_title 		= $tr['subdomain_management_create'];//'新增網域'
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';


if(!isset($_SESSION['agent']) || !in_array($_SESSION['agent']->account,$su['ops'])) {
  header('Location:./home.php');
  die();
}


// function combineThemePathHtml($id)
// {
//   $option = '';

//   $themeList = getAllStyleSettingName();

//   if (!$themeList) {
//     return '查無主題路徑設定';
//   }

//   foreach ($themeList as $v) {
//     $option .= <<<HTML
//     <option>{$v->name}</option>
// HTML;
//   }

//   $html = <<<HTML
//   <select class="form-control" id="{$id}ThemePath">
//     {$option}
//   </select>
// HTML;

//   return $html;
// }

function combineCardHtml()
{
  global $tr;
  $html = '';

  $flatformStr = [
    'mobile' => $tr['Mobile version'],//'手机版'
    'desktop' => $tr['PC version']//'桌机版'
  ];

  foreach ($flatformStr as $flatform => $str) {
    // $themePathHtml = combineThemePathHtml($flatform);
    $themeList = themeList($flatform);
    $html .= <<<HTML
    <div class="col-sm-6">
      <div class="card">
        <div class="card-header">{$str}</div>
        <div class="card-body">
          <form>
            <div class="form-group row">
              <label for="{$flatform}WebType" class="col-sm-2 col-form-label">{$tr['webType']}</label>
              <div class="col-sm-10">
                <input type="text" readonly class="form-control-plaintext" id="webType" value="{$flatform}">
              </div>
            </div>
            <div class="form-group row">
              <label for="{$flatform}ThemePath" class="col-sm-2 col-form-label">{$tr['ThemePath']}</label>
              <div class="col-sm-10">
                {$themeList['html']}
              </div>
            </div>
            <div class="form-group row">
              <label for="{$flatform}SubadmainName" class="col-sm-2 col-form-label">{$tr['SubadmainName']}</label>
              <div class="col-sm-10">
                <input type="text" class="form-control" id="{$flatform}SubadmainName" placeholder="{$tr['SubadmainName']}">
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
HTML;
  }

  return $html;
}

$action = 'createDomain';
$id = '';
$admainUrlVal = '';
$admainStatusIsChecked = '';

if (isset($_GET['i']) && !empty($_GET['i'])) {
  $function_title = $tr['Add Subadmain'];//'新增子网域'
  $action = 'createSubDomain';
  $id = filter_var($_GET['i'], FILTER_SANITIZE_STRING);
  $domainSetting = getDomainSetting($id);

  if (!$domainSetting) {
    echo "<script>alert('".$tr['This domain setting could not be found']."');location.href='./subdomain_management.php'</script>";
  }

  $admainUrlVal = 'value = "'.$domainSetting->domainname.'" disabled';
  $admainStatusIsChecked = ($domainSetting->open == 1) ? 'checked disabled' : 'disabled';
}

$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] .'</a></li>
  <li><a href="#">' . $tr['maintenance'] . '</a></li>
  <li><a href="subdomain_management.php">'.$tr['subdomain management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';

$cardHtml = combineCardHtml();

$html = <<<HTML
<form>
  <div class="form-group row">
    <label for="admainUrl" class="col-sm-2 col-form-label">{$tr['domain']}</label>
    <div class="col-sm-4">
      <input type="text" class="form-control" id="admainUrl" placeholder="{$tr['domain']}" {$admainUrlVal}>
    </div>
  </div>
  <div class="form-group row">
    <label for="admainStatus" class="col-sm-2 col-form-label">{$tr['State']}</label>
    <div class="col-sm-10">
      <div class="col-12 material-switch pull-left">
        <input class="form-check-input switch" id="admainStatus" type="checkbox" {$admainStatusIsChecked}>
        <label for="admainStatus" class="label-success"></label>
      </div>
    </div>
  </div>
</form>
<br>
HTML;

$html .= <<<HTML
<div class="card">
  <div class="card-header"></div>
  <div class="card-body">
    <form>
      <div class="form-group row">
        <label for="websiteFooter" class="col-sm-2 col-form-label">{$tr['subdmoain name']}
          <span class="glyphicon glyphicon-info-sign" data-toggle="tooltip" data-placement="top"  title="{$tr['subdomain site alert']}"></span>        
        </label>
        <div class="col-sm-4">
          <input type="text" class="form-control" id="websiteName" placeholder="{$tr['subdomain site']}">
        </div>
      </div>
      <div class="form-group row">
        <label for="websiteFooter" class="col-sm-2 col-form-label">{$tr['websiteFooter']}</label>
        <div class="col-sm-4">
          <input type="text" class="form-control" id="websiteFooter" placeholder="{$tr['websiteFooter']}">
        </div>

        <label for="webType" class="col-sm-2 col-form-label">{$tr['webType']}</label>
        <div class="col-sm-4">
          <select class="form-control" id="webType">
            <option>casino</option>
            <option>ezshop</option>
          </select>
        </div>
      </div>
      <div class="form-group row">
        <label for="hostName" class="col-sm-2 col-form-label">{$tr['hostName']}
        <span class="glyphicon glyphicon-info-sign" data-toggle="tooltip" data-placement="top"  title="{$tr['hostName alert']}"></span>
        </label>
        <div class="col-sm-4">
          <input type="text" class="form-control" id="hostName" placeholder="{$tr['hostName']}">
        </div>
        <label for="googleID" class="col-sm-2 col-form-label">{$tr['googleID']}</label>
        <div class="col-sm-4">
          <input type="text" class="form-control" id="googleID" placeholder="{$tr['googleID']}">
        </div>
      </div>
      <div class="form-group row">
        <label for="subadmainStatus" class="col-sm-2 col-form-label">{$tr['State']}</label>
        <div class="col-sm-10">
          <div class="col-12 material-switch pull-left">
            <input class="form-check-input switch" id="subadmainStatus" type="checkbox">
            <label for="subadmainStatus" class="label-success"></label>
          </div>
        </div>
      </div>
      <div class="form-group row">
        <label for="agent" class="col-sm-2 col-form-label">{$tr['Identity Agent']}</label>
        <div class="col-sm-4">
          <input type="text" class="form-control" id="agent" placeholder="{$tr['Identity Agent']}">
        </div>
      </div>

      <div class="form-group row">
        <label for="companyName"" class="col-sm-2 col-form-label">{$tr['companyName']}
        <span class="glyphicon glyphicon-info-sign" data-toggle="tooltip" data-placement="top"  title="{$tr['hostName alert']}"></span>
        </label>
        <div class="col-sm-4">
          <input type="text" class="form-control" id="companyName" placeholder="{$tr['companyName']}">
        </div>
        <label for="companyShortName" class="col-sm-2 col-form-label">{$tr['companyShortName']}</label>
        <div class="col-sm-4">
          <input type="text" class="form-control" id="companyShortName" placeholder="{$tr['companyShortName']}">
        </div>
      </div>

      <div class="form-group row">
        <label for="company-logo" class="col-sm-2 col-form-label">logo
          <span class="glyphicon glyphicon-info-sign mr-2" data-toggle="tooltip" data-placement="top"  title="{$tr['Image file does not exceed 2MB']}">
          </span>
        </label>
				<div class="col-xs-6">
					<div class="row border py-3 m-2 m-md-0">
						<div class="col-auto">							  
						  <div class="dropdown">
							  <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							              		{$tr['options']}
							  </button>
							  <div class="dropdown-menu"  id="v-pills-tab" role="tablist" aria-labelledby="dropdownMenuButton">
							    <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
									  <a class="dropdown-item active" data-group="img" data-toggle="pill" href="#v-pills-upload" role="tab" aria-selected="true">{$tr['upload image']}</a>
									  <a class="dropdown-item" data-group="img" data-toggle="pill" href="#v-pills-url" role="tab" aria-selected="false">{$tr['image url']}</a>
									</div>
							  </div>
							</div>
						</div>
						<div class="col-sm-8 pl-0">
							<div class="tab-content ml-2" id="v-pills-tabContent">
							  <div class="tab-pane fade show active" id="v-pills-upload" role="tabpanel">
							            		{$tr['upload files for 2mb']}
							    <input class="mt-1" type="file" accept="image/*" name="file" id="upload_logo_img" >
							  </div>
							  <div class="tab-pane fade" id="v-pills-url" role="tabpanel">
							            		{$tr['image url']}
							    <input type="text" class="form-control mt-1" id="logo_url" placeholder="{$tr['enter image url']}">
							  </div>
							</div>
						</div>
					</div>
				</div>
			</div>

      <div class="form-group row">
        <label for="company-logo" class="col-sm-2 col-form-label">favicon
          <span class="glyphicon glyphicon-info-sign mr-2" data-toggle="tooltip" data-placement="top"  title="{$tr['Image file does not exceed 2MB']}">
          </span>
        </label>
				<div class="col-xs-6">
					<div class="row border py-3 m-2 m-md-0">
						<div class="col-auto">							  
						  <div class="dropdown">
							  <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							              		{$tr['options']}
							  </button>
							  <div class="dropdown-menu"  id="v-favicon-tab" role="tablist" aria-labelledby="dropdownMenuButton">
							    <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
									  <a class="dropdown-item active" data-group="img" data-toggle="pill" href="#v-favicon-upload" role="tab" aria-selected="true">{$tr['upload image']}</a>
									  <a class="dropdown-item" data-group="img" data-toggle="pill" href="#v-favicon-url" role="tab" aria-selected="false">{$tr['image url']}</a>
									</div>
							  </div>
							</div>
						</div>
						<div class="col-sm-8 pl-0">
							<div class="tab-content ml-2" id="v-favicon-tabContent">
							  <div class="tab-pane fade show active" id="v-favicon-upload" role="tabpanel">
							            		{$tr['upload files for 2mb']}
							    <input class="mt-1" type="file" accept="image/x-icon" name="file" id="upload_favicon_img" >
							  </div>
							  <div class="tab-pane fade" id="v-favicon-url" role="tabpanel">
							            		{$tr['image url']}
							    <input type="text" class="form-control mt-1" id="favicon_url" placeholder="{$tr['enter image url']}">
							  </div>
							</div>
						</div>
					</div>
				</div>
			</div>

      <div class="form-group row">
        <label for="note" class="col-sm-2 col-form-label">{$tr['note']}</label>
        <div class="col-sm-10">
          <textarea class="form-control" id="note" rows="5"></textarea>
        </div>
      </div>
    </form>
    <div class="row">
      {$cardHtml}
    </div>
  </div>
</div>
<br>
<div class="row">
  <div class="col-12 col-md-3"></div>
  <div class="col-12 col-md-3">
    <button id="submitSetting" class="btn btn-success btn-block" type="button">{$tr['Submit']}</button>
  </div>
  <div class="col-12 col-md-3">
    <a class="btn btn-secondary btn-block" href="./subdomain_management.php" role="button">{$tr['Cancel']}</a>
  </div>
  <div class="col-12 col-md-3"></div>
</div>
HTML;

$indexbody_content = $html;

$extend_head = "
<style>
.material-switch > input[type=\"checkbox\"] {
    visibility:hidden;
}

.material-switch > label {
    cursor: pointer;
    height: 0px;
    position: relative;
    width: 40px;
}

.material-switch > label::before {
    background: rgb(0, 0, 0);
    box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
    border-radius: 8px;
    content: '';
    height: 16px;
    margin-top: -8px;
    margin-left: -15px;
    position:absolute;
    opacity: 0.3;
    transition: all 0.4s ease-in-out;
    width: 30px;
}
.material-switch > label::after {
    background: rgb(255, 255, 255);
    border-radius: 16px;
    box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
    content: '';
    height: 16px;
    left: -4px;
    margin-top: -8px;
    margin-left: -15px;
    position: absolute;
    top: 0px;
    transition: all 0.3s ease-in-out;
    width: 16px;
}
.material-switch > input[type=\"checkbox\"]:checked + label::before {
    background: inherit;
    opacity: 0.5;
}
.material-switch > input[type=\"checkbox\"]:checked + label::after {
    background: inherit;
    left: 20px;
}

</style>
";

$extend_js = <<<JS
<script>
$(document).on("click",'#submitSetting',function(){
  var logo_img=$('#upload_logo_img')[0].files[0]?$('#upload_logo_img')[0].files[0]:$('#logo_url').val()

  var favicon_img=$('#upload_favicon_img')[0].files[0]?$('#upload_favicon_img')[0].files[0]:$('#favicon_url').val()
  
  var data = {
    'admainUrl' : $('#admainUrl').val(),
    'admainStatus' : $("#admainStatus").prop("checked") ? '1' : '0',
    'websiteName' : $('#websiteName').val(),
    'websiteFooter' : $('#websiteFooter').val(),
    'webType' : $('#webType').val(),
    'hostName' : $('#hostName').val(),
    'googleID' : $('#googleID').val(),
    'companyShortName' : $('#companyShortName').val(),
    'companyName' : $('#companyName').val(),
    'subadmainStatus' : $("#subadmainStatus").prop("checked") ? '1' : '0',
    'agent' : $('#agent').val(),
    'note' : $('#note').val(),
    'mobileThemePath' : $('#mobileThemePath').val(),
    'mobileSubadmainName' : $('#mobileSubadmainName').val(),
    'desktopThemePath' : $('#desktopThemePath').val(),
    'desktopSubadmainName' : $('#desktopSubadmainName').val(),
    'component' : 0
  };

  if ('{$action}' != 'createDomain') {
    data = Object.assign(data, {'id': '{$id}'});
  }

  var formData = new FormData();
  formData.append('action', '{$action}');
  formData.append('data', JSON.stringify(data));
  formData.append('upload_logo_img', logo_img);
  formData.append('upload_favicon_img', favicon_img);

  var r = confirm('{$tr['sure to add']}?');
  if(r) {
    $.ajax({
      type: 'POST',
      url: 'subdomain_management_action.php',
      data : formData,
      cache:false,
      contentType: false,
      processData: false,
      // data: {
      //   action: '{$action}',
      //   data: JSON.stringify(data)
      // },
      success: function(resp) {
        var res = JSON.parse(resp);
        if (res.status == 'success') {
          alert(res.message);
          location.href='./subdomain_management.php'
        } else {
          alert(res.message);
        }
      },
      error: function(res) {
          $('#progress_bar').remove();
          if(res.status == 413) {
              alert('{$tr['The file is too large (more than 2MB)']}');
              // alert('档案过大(超过2MB)');
          } else {
            alert('{$tr['There was an error uploading the file. Please try again later.']}');
            // alert('上传文件发生错误，请稍后再试。');				
          }
      }
    });
  }
});
$(function () {
  $('[data-toggle="tooltip"]').tooltip()
})
</script>
JS;

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description']    = $tr['host_descript'];
$tmpl['html_meta_author']         = $tr['host_author'];
$tmpl['html_meta_title']          = $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']               = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']              = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']                = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content']       = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']        = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");
