<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 線上支付商戶管理 , 顯示詳細線上支付商戶資訊
// File Name:	deposit_company_config_detail.php
// Author:		Pia
// Related:   對應 deposit_company_config.php 各項資訊管理
// DB Table:
// Log:
// ----------------------------------------------------------------------------

//-----------------------------------------------------------------------------
// 將修改、新增表格 寫在最後面 依據帶入的動作不同 填入不同的變數
// 帳戶型態
// $edit_company_type_option
// 銀行名稱
// $deposit_company_companyname
// 戶名
// $deposit_company_accountname
// 銀行帳號
// $deposit_company_accountnumber
// 开户行网点
// $deposit_company_accountarea
// 可用會員等級
// $edit_gradename_checkbox_option
// 手續費(%)
// $deposit_company_cashfeerate
// 狀態
// $edit_status_select_option
// 其他資訊
// $deposit_company_notes
// 銀行網址
// $deposit_company_companyurl
// 其他另外的html
// $extend_deposit_company_form
// 傳送post的ajax
// $submit_to_save_js


session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

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
$function_title 		= $tr['Company Account Management'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置$tr['Home'] = '首頁';$tr['System Management'] = '系統管理';$tr['Company Account Management'] = '公司入款帳戶管理';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['System Management'].'</a></li>
  <li><a href="deposit_company_config.php">'.$tr['Company Account Management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

function get_cryptocurrency_list()
{
  $cryptocurrency = [
    'BTC' => '比特幣',
    'BCH' => '比特幣現金',
    'ETH' => '乙太幣',
    'ETC' => '乙太經典',
    'LTC' => '萊特幣',
    'XRP' => '瑞波幣',
    'XMR' => '門羅幣',
    'DASH' => '達世幣',
    'NEM' => '新經幣'
  ];

  return $cryptocurrency;
}

function get_companydata($id)
{
  if (empty($id)) {
    return false;
  }

  $sql = "SELECT * FROM root_deposit_company WHERE id='".$id."';";
  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  return $result[1];
}

function get_membergrade()
{
  $grade = [];

  $sql = "SELECT id, gradename FROM root_member_grade";
  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  return $result;
}

function combine_grade_html($membergrade, $companygrade)
{
  $html = '';

  foreach ($membergrade as $v) {
    $ischecked = (isset($companygrade[$v->gradename])) ? 'checked' : '';
    $html .= <<<HTML
    <label class="checkbox-inline">
      <input class="form-check-input" name="gradename" type="checkbox" value="{$v->id}_{$v->gradename}" {$ischecked}>$v->gradename
    </label>
HTML;
  }

  return $html;
}

function combine_status_html($status)
{
  global $status_config;

  $html = '';

  foreach ($status_config as $k => $v) {
    if ($k == 2) {
      continue;
    }

    if ($status != '') {
      $isselected = ($status == $k) ? 'selected' : '';
    } else {
      $isselected = ($k == 0) ? 'selected' : '';
    }

    $html .= <<<HTML
    <option value="{$k}" {$isselected}>{$v}</option>
HTML;
  }

  return $html;
}

function combine_acctype_html($type_config, $acctype)
{
  $html = '';

  foreach ($type_config as $k => $v) {
    if (!empty($acctype)) {
      $isselected = ($acctype == $v['code']) ? 'selected' : '';
    } else {
      $isselected = ($k == 0) ? 'selected' : '';
    }

    $html .= <<<HTML
    <option value="{$v['code']}" {$isselected}>{$v['name']}</option>
HTML;
  }

  return $html;
}

function combine_qrcode_html($type, $img_src)
{
  global $tr;
  $img_html = '<img id="'.$type.'_output" height="200" style="display:none">';
  if ($img_src != '') {
    $img_html = '<img id="'.$type.'_output" src="'.$img_src.'" height="200">';
  }

  $html = <<<HTML
  <div class="row form-group accountNumberArea">
    <label class="col-sm-2 control-label accountNumberLabel">{$tr['receipt code']}</label>
    <div class="col-sm-10 accountNumberDev">
      <button type="button" class="btn btn-primary btn-sm qrbtn" data-toggle="modal" data-target="#{$type}Modal">{$tr['select qr code']}</button>

      <div class="modal fade bs-example-modal-sm qrModal" id="{$type}Modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h4 class="modal-title" id="myModalLabel">{$tr['select image']}</h4>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>          
            </div>
            <div class="modal-body">
              <p>{$tr['preview image']}</p>
              {$img_html}<br><br>
              <input type="file" id="{$type}_img" onchange="openFile(event)">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal" id="{$type}" onclick="cancelQrCode(this.id)">{$tr['Cancel']}</button>
              <button type="button" class="btn btn-primary" id="{$type}" onclick="convert_img_todatauri_upload(this.id)">{$tr['confirm']}</button>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>  
HTML;

  return $html;
}

function combine_transaction_limit_html($limit)
{
  global $tr;

//单次存款上限
  // <div class="row form-group perTransactionLimitArea">
  //   <label class="col-sm-2 control-label">{$tr['Single deposit limit']}</label>
  //   <div class="col-sm-10 perTransactionLimitDev">
  //     <input type="number" class="form-control" id="perTransactionLimit" placeholder="{$tr['Single deposit limit']}" value="{$limit['perTransactionLimit']}" step="1" min="0">
  //   </div>
  // </div>
  $html = <<<HTML
  <div class="row form-group dailyTransactionLimitArea">
    <label class="col-sm-2 control-label">{$tr['today deposit limit']}</label>
    <div class="col-sm-10 dailyTransactionLimitDev">
      <input type="number" class="form-control" id="dailyTransactionLimit" placeholder="{$tr['today deposit limit']}" value="{$limit['dailyTransactionLimit']}" step="1" min="0">
    </div>
  </div>
  <div class="row form-group monthlyTransactionLimitArea">
    <label class="col-sm-2 control-label">{$tr['this month deposit limit']}</label>
    <div class="col-sm-10 monthlyTransactionLimitDev">
      <input type="number" class="form-control" id="monthlyTransactionLimit" placeholder="{$tr['this month deposit limit']}" value="{$limit['monthlyTransactionLimit']}" step="1" min="0">
    </div>
  </div>
HTML;

  return $html;
}

function combine_bank_required_field_html($accnum, $accarea)
{
  global $tr;

  $html = <<<HTML
  <div class="row form-group accountNumberArea">
    <label class="col-sm-2 control-label accountNumberLabel">{$tr['Account']}</label>
    <div class="col-sm-10 accountNumberDev">
      <input type="text" class="form-control" id="accountNumberInput" placeholder="{$tr['Bank account']}" value="{$accnum}">
    </div>
  </div>
  <div class="row form-group accountArea">
    <label class="col-sm-2 control-label">{$tr['bank of deposit network ip']}</label>
    <div class="col-sm-10 accountDev">
      <input type="text" class="form-control validate[maxSize[50]]" maxlength="50" id="accountInput" placeholder="{$tr['bank of deposit network ip']}({$tr['max']}50{$tr['word']})" value="{$accarea}">
    </div>
  </div>
HTML;

  return $html;
}

function combine_website_html($url)
{
  global $tr;

  $html = <<<HTML
  <div class="row form-group companyurlArea">
    <label class="col-sm-2 control-label">{$tr['Bank Website']}</label>
    <div class="col-sm-10">
      <input type="text" class="form-control"  id="companyurlInput" placeholder="{$tr['Bank Website']}" value="{$url}">
    </div>
  </div>
HTML;

  return $html;
}

function combine_cryptocurrency_html($cryptocurrency)
{
  $option = '<option value="">请选择虚拟货币种类</option>';

  $cryptocurrency_type = get_cryptocurrency_list();
  foreach ($cryptocurrency_type as $abbreviation => $cryptocurrencyName) {
    $isselected = ($cryptocurrency == $abbreviation) ? 'selected' : '';

    $option .= <<<HTML
    <option value="{$abbreviation}" {$isselected}>{$cryptocurrencyName}({$abbreviation})</option>
HTML;
  }

  $html = <<<HTML
  <div class="row form-group cryptocurrencyTypeArea">
    <label class="col-sm-2 control-label">虚拟货币种类</label>
    <div class="col-sm-10 cryptocurrencyTypeDev">
      <select class="form-control" id="cryptocurrency">
        {$option}
      </select>
    </div>
  </div>
HTML;

  return $html;
}

function combine_virtualmoney_required_field_html($exchangerate)
{
  global $tr;

  $html = <<<HTML
  <div class="row form-group exchangeRateArea">
    <label class="col-sm-2 control-label">
    汇率
    <span class="glyphicon glyphicon-info-sign" title="此设定为1虚拟货币可兑换多少额度"></span>
    </label>
    <div class="col-sm-10 exchangeRateDev">
      <input type="text" class="form-control" id="exchangeRateInput" placeholder="汇率" value="{$exchangerate}">
    </div>
  </div>
HTML;

  return $html;
}

function combine_type_required_field_html($companydata)
{
  switch ($companydata['type']) {
    case 'wechat':
      $html = combine_qrcode_html($companydata['type'], $companydata['accountnumber']);
      break;
    case 'virtualmoney':
      $html = combine_qrcode_html($companydata['type'], $companydata['accountnumber'])
              .combine_cryptocurrency_html($companydata['cryptocurrency'])
              .combine_virtualmoney_required_field_html($companydata['exchangerate']);
      break;
    default:
      $html = combine_bank_required_field_html($companydata['accountnumber'], $companydata['accountarea']);
      break;
  }

  return $html;
}

// main
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  $status_config = [$tr['off'],$tr['open'], $tr['delete']];

  $company_type_config = [
    0 => [
      'code' => 'bank',
      'name' => $tr['bank']
    ],
    1 => [
      'code' => 'wechat',
      'name' => '微信 QR Code 付款'
    ],
    2 => [
      'code' => 'virtualmoney',
      'name' => '虚拟货币付款'
    ]
  ];

  $companydata = [
    'type' => 'bank',
    'companyname' => '',
    'accountname' => '',
    'accountnumber' => '',
    'accountarea' => '',
    'status' => '0',
    'notes' => '',
    'companyurl' => '',
    'cashfeerate' => 0,
    'exchangerate' => 0,
    'cryptocurrency' => '',
    'grade' => '',
    'transaction_limit' => [
      'perTransactionLimit' => 0,
      'dailyTransactionLimit' => 0,
      'monthlyTransactionLimit' => 0
    ]
  ];

  $type_html = combine_type_required_field_html($companydata);

  $id = (isset($_GET['i']) && !empty($_GET['i'])) ? filter_var($_GET['i'], FILTER_SANITIZE_STRING) : '0';

  $company_r = get_companydata($id);

  if (!$company_r) {
    $id = '0';
  }

  $action = 'insert';

  if (!empty($id)) {
    $action = 'update';

    $companydata = [
      'type' => $company_r->type,
      'companyname' => $company_r->companyname,
      'accountname' => $company_r->accountname,
      'accountnumber' => $company_r->accountnumber,
      'accountarea' => $company_r->accountarea,
      'status' => $company_r->status,
      'notes' => $company_r->notes,
      'companyurl' => $company_r->companyurl,
      'cashfeerate' => $company_r->cashfeerate,
      'exchangerate' => $company_r->exchangerate,
      'cryptocurrency' => $company_r->cryptocurrency,
      'grade' => json_decode($company_r->grade, true),
      'transaction_limit' => json_decode($company_r->transaction_limit, true)
    ];

    $type_html = combine_type_required_field_html($companydata);
  }

  $membergrade = get_membergrade();

  $grade_html = ($membergrade) ? combine_grade_html($membergrade, $companydata['grade']) : '会员等级查询错误或尚未设定可用等级';
  $status_html = combine_status_html($companydata['status']);
  $acctype_html = combine_acctype_html($company_type_config, $companydata['type']);
  $transaction_limit_html = combine_transaction_limit_html($companydata['transaction_limit']);

  $website_html = '';
  if ($companydata['type'] == 'bank') {
    $website_html = combine_website_html($companydata['companyurl']);
  }

  $html = <<<HTML
  <button type="button" class="btn btn-danger">{$tr['required field']}</button>
  <hr>
  <div class="form-horizontal">
    <div class="row form-group typeArea">
      <label class="col-sm-2 control-label">{$tr['Account Type']}</label>
      <div class="col-sm-10 typeDev">
        <select class="form-control" id="type">
          {$acctype_html}
        </select>
      </div>
    </div>
    <div class="row form-group companyNameArea">
      <label class="col-sm-2 control-label">{$tr['service name']}</label>
      <div class="col-sm-10 companyNameDev">
        <input type="text" class="form-control validate[maxSize[50]]" maxlength="50" id="companyNameInput"  placeholder="{$tr['service name']}({$tr['max']}50{$tr['word']})" value="{$companydata['companyname']}">
      </div>
    </div>
    <div class="row form-group accountNameArea">
      <label class="col-sm-2 control-label">{$tr['account name']}</label>
      <div class="col-sm-10 accountNameDev">
        <input type="text" class="form-control validate[maxSize[50]]" maxlength="50" id="accountNameInput" placeholder="{$tr['account name']}({$tr['max']}50{$tr['word']})" value="{$companydata['accountname']}">
      </div>
    </div>
    {$type_html}
    <div class="row form-group gradeNameIdArea">
      <label class="col-sm-2 control-label">{$tr['Available membership level']}</label>
      <div class="col-sm-10 gradeNameIdDev">
        <div class="form-check" id="gradeNameId" >
            {$grade_html}
        </div>
      </div>
    </div>
    {$transaction_limit_html}
    <div class="row form-group cashFeeRateArea">
      <label class="col-sm-2 control-label">{$tr['Fee']}(%)</label>
      <div class="col-sm-10 cashFeeRateDev">
        <input type="number" class="form-control"  id="cashFeeRate" placeholder="{$tr['Fee']}(%)" value="{$companydata['cashfeerate']}" step="0.01" min="0">
      </div>
    </div>
    <div class="row form-group">
      <label class="col-sm-2 control-label">{$tr['State']}</label>
      <div class="col-sm-10">
        <select class="form-control" id="status">
          {$status_html}
        </select>
      </div>
    </div>

    <button type="button" class="btn btn-warning">{$tr['Optional field']}</button>
    <hr>
    <div class="row form-group notes">
      <label class="col-sm-2 control-label">{$tr['Other Information']}</label>
      <div class="col-sm-10">
        <input type="text" class="form-control validate[maxSize[500]]" maxlength="500" id="notes" placeholder="{$tr['Other Information']}({$tr['max']}500{$tr['word']})" value="{$companydata['notes']}">
      </div>
    </div>
    {$website_html}
    <div class="row form-group">
      <div class="col-sm-offset-2 col-sm-10">
        <button id="submit_to_save" class="btn btn-primary" type="button">{$tr['Save']}</button>
        <a class="btn btn-default" href="./deposit_company_config.php" role="button">{$tr['return']}</a>
      </div>
    </div>
  </div>
  <!-- <div class="row">
    <div id="preview_result"></div>
  </div> -->
HTML;

  // change page js
  $extend_js = <<<JS
  <script>
  $(document).on('change','#type',function(){
    var typeid = $(this).val();
    var cryptocurrencyTypeAreaIsExist = parseInt($('.cryptocurrencyTypeArea').length);
    var exchangeRateAreaIsExist = parseInt($('.exchangeRateArea').length);
    var accNumberInputVal = (typeid == '{$companydata['type']}') ? '{$companydata['accountnumber']}' : '';

    if (typeid == 'bank') {
      removeQrCodeHtml();
      addBankHtml(accNumberInputVal);

      if (cryptocurrencyTypeAreaIsExist != 0) {
        $('.cryptocurrencyTypeArea').remove();
      }

      if (exchangeRateAreaIsExist != 0) {
        $('.exchangeRateArea').remove();
      }
    } else {
      var qrCodeIsExist = parseInt($('.qrbtn').length);

      (qrCodeIsExist === 0) ? removeBankHtml() : removeQrCodeHtml();

      addQrCodeHtml(typeid, accNumberInputVal);

      if (typeid == 'virtualmoney' && exchangeRateAreaIsExist === 0) {
        addCryptocurrencyHtml('{$companydata['cryptocurrency']}');
        addExchangeRateHtml('{$companydata['exchangerate']}');
      } else {
        $('.cryptocurrencyTypeArea').remove();
        $('.exchangeRateArea').remove();
      }
    }
  });

  function addQrCodeHtml(type, imgsrc)
  {
    var imgSrcHtml = `<img id="`+type+`_output" height="200" style="display:none">`;
    if (imgsrc != '') {
      imgSrcHtml = `<img id="`+type+`_output" src="`+imgsrc+`" height="200">`;
    }

    var qrcode_html = `
    <button type="button" class="btn btn-primary btn-sm qrbtn" data-toggle="modal" data-target="#`+type+`Modal">{$tr['select qr code']}</button>

    <div class="modal fade bs-example-modal-sm qrModal" id="`+type+`Modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
      <div class="modal-dialog" role="document">
        <div class="modal-content">

          <div class="modal-header">
            <h4 class="modal-title" id="myModalLabel">{$tr['select image']}</h4>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>          
          </div>

          <div class="modal-body">
            <p>{$tr['preview image']}</p>
            `+imgSrcHtml+`<br><br>
            <input type="file" id="`+type+`_img" onchange="openFile(event)">
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal" id="`+type+`" onclick="cancelQrCode(this.id)">{$tr['Cancel']}</button>
            <button type="button" class="btn btn-primary" id="`+type+`" onclick="convert_img_todatauri_upload(this.id)">{$tr['confirm']}</button>
          </div>

        </div>
      </div>
    </div>
    `;

    $('.accountNumberLabel').text('收款码');
    $('.accountNumberDev').append(qrcode_html);
  }

  function removeQrCodeHtml()
  {
    $('.qrbtn').remove();
    $('.qrModal').remove();
  }

  function addBankHtml(accNumberInputVal) {
    var acchtml = `<input type="text" class="form-control" id="accountNumberInput" placeholder="帐号" value="`+accNumberInputVal+`">`;

    var bankWebSiteHtml = `
    <div class="row form-group companyurlArea">
      <label class="col-sm-2 control-label">{$tr['Bank Website']}</label>
      <div class="col-sm-10">
        <input type="text" class="form-control"  id="companyurlInput" placeholder="{$tr['Bank Website']}" value="{$companydata['companyurl']}">
      </div>
    </div>
    `;

    var bankNetworkHtml = `
    <div class="row form-group accountArea">
      <label class="col-sm-2 control-label">{$tr['bank of deposit network ip']}</label>
      <div class="col-sm-10">
        <input type="text" class="form-control validate[maxSize[50]]" maxlength="50" id="accountInput" placeholder="{$tr['bank of deposit network ip']}({$tr['max']}50{$tr['word']})" value="{$companydata['accountarea']}">
      </div>
    </div>
    `;

    $('.accountNumberLabel').text('帐号');
    $('.accountNumberDev').append(acchtml);
    $('.accountNumberArea').after(bankNetworkHtml);
    $('.notes').after(bankWebSiteHtml);
  }

  function removeBankHtml() {
    $('.accountArea').remove();
    $('.companyurlArea').remove();
    $('#accountNumberInput').remove();
  }

  function getCryptocurrencyList() {
    var cryptocurrency = {
      'BTC' : '比特幣',
      'BCH' : '比特幣現金',
      'ETH' : '乙太幣',
      'ETC' : '乙太經典',
      'LTC' : '萊特幣',
      'XRP' : '瑞波幣',
      'XMR' : '門羅幣',
      'DASH' : '達世幣',
      'NEM' : '新經幣'
    };

    return cryptocurrency;
  }

  function addCryptocurrencyHtml(cryptocurrency) {
    var option = '<option value="">请选择虚拟货币种类</option>';
    var cryptocurrencyList = getCryptocurrencyList();

    $.each(cryptocurrencyList, function(abbreviation, cryptocurrencyName) {
      var isselected = (cryptocurrency == abbreviation) ? 'selected' : '';
      option += `<option value="`+abbreviation+`" `+isselected+`>`+cryptocurrencyName+`(`+abbreviation+`)</option>`
    });

    var html = `
    <div class="row form-group cryptocurrencyTypeArea">
      <label class="col-sm-2 control-label">虚拟货币种类</label>
      <div class="col-sm-10 cryptocurrencyTypeDev">
        <select class="form-control" id="cryptocurrency">
          `+option+`
        </select>
      </div>
    </div>
    `;

    $('.accountNumberArea').after(html);
  }

  function addExchangeRateHtml(exchangeRate) {
    var html = `
    <div class="row form-group exchangeRateArea">
      <label class="col-sm-2 control-label">
      汇率
      <span class="glyphicon glyphicon-info-sign" title="此设定为1虚拟货币可兑换多少额度"></span>
      </label>
      <div class="col-sm-10 exchangeRateDev">
        <input type="number" class="form-control" id="exchangeRateInput" placeholder="汇率" value="`+exchangeRate+`" step="0.01" min="0">
      </div>
    </div>
    `;

    $('.cryptocurrencyTypeArea').after(html);
  }
  </script>
JS;

$form_list='';
  
$form_list = $form_list.'
  <form class="form-horizontald" role="form" id="deposit_form">
    '.$html.'
  </form>
';
$html = $form_list;

  	// JS 開頭
$extend_head = $extend_head. <<<HTML
<script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
<script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
<link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

<script type="text/javascript" language="javascript" class="init">
  $(document).ready(function () {
    $("#deposit_form").validationEngine();
  });
</script>
HTML; 

  // qrcode js
  $extend_js .= <<<JS
  <script>
  function openFile(event) {
    var input = event.target;
    var output_id = input.id.split('_img');
    var file = input.files[0];

    if( file.size > 102400) {
      $('#'+output_id[0]+'_img').val('');
      alert('{$tr['Image is larger than 100kb, please re-select image']}');
      // alert('图片大于100kb，请重新选择图片');
      return;
    }

    if (file.type != 'image/png' && file.type != 'image/jpeg') {
      $('#'+output_id[0]+'_img').val('');
      alert('{$tr['Uploaded file is not a picture, please re-select']}');
      // alert('上传的档案非图片，请重新选择');

      return;
    }

    var reader = new FileReader();
    reader.readAsDataURL(file);

    reader.onload = function() {
      var dataURL = reader.result;

      var image = new Image();

      image.src = dataURL;
      image.onload = function() {
        if(this.width > 245 || this.height > 245) {
          $('#'+output_id[0]+'_img').val('');
          alert('{$tr['Image exceeds length and width 245 * 245 limit']}');
          // alert('图片超过长宽 245 * 245 限制');
          return;
        } else {
          $('#'+output_id[0]+'_output').attr('src', dataURL).show();
        }
      }
    }
  }

  function convert_img_todatauri_upload(id) {
    $('#'+id+'Modal').modal('toggle');
  }

  function cancelQrCode(id)
  {
    $('#'+id+'_img').val('');
    $('#'+id+'_output').attr('src', '').hide();
  }
  </script>
JS;

  // post data js
  $extend_js .= <<<JS
  <script>
  function getall_checkbox() {
    var allVals = [];
    $('#gradeNameId :checked').each(function() {
      allVals.push($(this).val());
    });
    return allVals;
  }

  $(document).on("click",'#submit_to_save',function(){
    var transactionLimit = {
      'perTransactionLimit' : $('#perTransactionLimit').val(),
      'dailyTransactionLimit' : $('#dailyTransactionLimit').val(),
      'monthlyTransactionLimit' : $('#monthlyTransactionLimit').val()
    };

    var defaultData = {
      'type' : $('#type').val(),
      'companyname' : $('#companyNameInput').val(),
      'accountname' : $('#accountNameInput').val(),
      'grade' : getall_checkbox(), 
      'transaction_limit' : transactionLimit,
      'cashfeerate' : $('#cashFeeRate').val(),
      'status' : $('#status').val(),
      'notes' : $('#notes').val()
    };

    var bank = {
      'accountnumber' : $('#accountNumberInput').val(),
      'accountarea' : $('#accountInput').val(), 
      'companyurl' : $('#companyurlInput').val()
    };

    var wechat = {'accountnumber' : $('#'+defaultData['type']+'_output').attr('src')};
    
    var virtualmoney = {
      'accountnumber' : $('#'+defaultData['type']+'_output').attr('src'),
      'exchangerate' : $('#exchangeRateInput').val(),
      'cryptocurrency' : $('#cryptocurrency').val()
    };

    var data = Object.assign(defaultData, eval(defaultData['type']));

    if ('{$action}' == 'update') {
      var id = {'id' : {$id}};
      data = Object.assign(data, id);
    }

    if ($('#cashFeeRate').val()<0) {
      alert ('手续费不得為負數');
    } else {
      $.ajax({
        type: 'POST',
        url: 'deposit_company_config_detail_action.php',
        data: {
          action: '{$action}',
          data: JSON.stringify(data)
        },
        success: function(resp) {
          // $('#preview_result').html(resp);
          var res = JSON.parse(resp);
          if (res.code === 'success') {
            alert(res.result);
            window.location.replace("./deposit_company_config.php");
          } else {
            alert(res.result);
            return false;
          }
        }
      });
    }
  });
  </script>
JS;

} else {
  // 沒有登入的顯示提示俊息 $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
  $html  =$tr['only management and login mamber'] ;
}

// 切成 1 欄版面
$indexbody_content = '
<div class="row">
  <div class="col-12 col-md-12">
  '.$html.'
  </div>
</div>
<br>
<div class="row">
  <div id="preview_result"></div>
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
