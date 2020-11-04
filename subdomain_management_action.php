<?php
// ----------------------------------------------------------------------------
// Features: 網域及子網域管理新增.修改.刪除動作處理
// File Name:	subdomain_management_action.php
// Author: Neil
// Related:
// Log:
// ----------------------------------------------------------------------------
session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/lib_subdomain_management.php";
//CDN上傳
require_once dirname(__FILE__) ."/lib_cdnupload.php";

if(!isset($_SESSION['agent']) || !in_array($_SESSION['agent']->account,$su['ops'])) {
  header('Location:./home.php');
  die();
}

$postData = json_decode($_POST['data']);

//CDN上傳logo
if(isset($_FILES['upload_logo_img'])){
  $logo = new CDNConnection($_FILES['upload_logo_img']);
  //檔案類型確認
  if ($logo->CheckFile(array('jpg', 'png', 'bmp')) != true) {
    $return['logger'] = $tr['upload failed Format error']."jpg,png,bmp";
    echo json_encode($return);
    die();
  }
  //上傳檔案
  $img_upload_res = $logo->UploatFile('upload/company_logo/');
  if ($img_upload_res['res'] != 1) {
      $return['logger'] = $tr['upload failed'];
      echo json_encode($return);
      die();
  }else{
    $postData->upload_logo_img=$img_upload_res['url'];
  }
  //$del_res = DeleteCDNFile('upload/logo_img/',$gamechk['image']);
}elseif(isset($_POST['upload_logo_img'])){
  $postData->upload_logo_img=$_POST['upload_logo_img'];
}

//CDN上傳favicon
if(isset($_FILES['upload_favicon_img'])){
  $logo = new CDNConnection($_FILES['upload_favicon_img']);
  //檔案類型確認
  if ($logo->CheckFile(array('ico')) != true) {
    $return['logger'] = $tr['upload failed Format error'].".ico";
    echo json_encode($return);
    die();
  }
  //上傳檔案
  $favicon_upload_res = $logo->UploatFile('upload/favicon/');
  if ($favicon_upload_res['res'] != 1) {
      $return['logger'] = $tr['upload failed'];
      echo json_encode($return);
      die();
  }else{
    $postData->upload_favicon_img=$favicon_upload_res['url'];
  }
  //$del_res = DeleteCDNFile('upload/logo_img/',$gamechk['image']);
}elseif(isset($_POST['upload_favicon_img'])){
  $postData->upload_favicon_img=$_POST['upload_favicon_img'];
}

if ($_POST['action'] != 'delDomain' && $_POST['action'] != 'delSubDomain') {
  $validateResult = validateData($postData);

  if (!$validateResult['status']) {
    echo json_encode(array('status' => 'fail', 'message' => $validateResult['result']));
    die();
  }

  $configData = combineSubdomaimJson($validateResult['result']);
}

if ($_POST['action'] != 'createDomain') {
  $domainSettings = (isset($postData->sid)) ? validateId($postData->id, $postData->sid) : validateId($postData->id);

  if (!$domainSettings) {
    echo json_encode(array('status' => 'fail', 'message' => $tr['Incorrect domain or subdomain settings']));//'错误的网域或子网域设定'
    die();
  }
}

switch ($_POST['action']) {
  case 'createDomain':
    $sql = combineInsertSql($validateResult['result'], json_encode([1 => $configData]));
    $sqlResult = runSQL($sql);

    if (!$sqlResult) {
      echo json_encode(array('status' => 'fail', 'message' => $tr['Domain setting added failed']));//'网域设定新增失败'
      die();
    }

    echo json_encode(array('status' => 'success', 'message' => $tr['Domain settings added successfully']));//'网域设定新增成功'
    break;
  case 'createSubDomain':
    if (count($domainSettings['subdomainSettings'])) {
      array_push($domainSettings['subdomainSettings'], $configData);
    } else {
      $domainSettings['subdomainSettings']['1'] = $configData;
    }

    $sql = combineUpdateSql($domainSettings['id'], $validateResult['result'], json_encode($domainSettings['subdomainSettings']));
    $sqlResult = runSQL($sql);

    if (!$sqlResult) {
      echo json_encode(array('status' => 'fail', 'message' => $tr['Subdomain setting update failed']));//'子网域设定更新失败'
      die();
    }

    echo json_encode(array('status' => 'success', 'message' => $tr['Subdomain settings update successfully']));//'子网域设定更新成功'
    break;
  case 'edit':
    $sid = $domainSettings['sid'];
    $domainSettings['subdomainSettings'][$sid] = $configData;

    $sql = combineUpdateSql($domainSettings['id'], $validateResult['result'], json_encode($domainSettings['subdomainSettings']));
    $sqlResult = runSQL($sql);

    if (!$sqlResult) {
      echo json_encode(array('status' => 'fail', 'message' => $tr['Subdomain setting update failed']));//'子网域设定更新失败'
      die();
    }

    echo json_encode(array('status' => 'success', 'message' => $tr['Subdomain settings update successfully']));//'子网域设定更新成功'
    break;
  case 'delDomain':
    $component_id=[];
    foreach($domainSettings['subdomainSettings'] as $key => $value) {
        array_push($component_id, $domainSettings['subdomainSettings'][$key]['component']);
    }
    $sql = combineUpdateStatusSql($domainSettings['id']);
    $sqlResult = runSQL($sql);

    if (!$sqlResult) {
      echo json_encode(array('status' => 'fail', 'message' => $tr['Domain setting deletion failed']));//'网域设定删除失败'
      die();
    }

    //刪除stylesetting
    for ($i=0; $i < count($component_id); $i++) {
      if($component_id[$i] != 0){
        runSQLall('UPDATE site_stylesetting set open = 2 WHERE id='.$component_id[$i].';');
      }
    }
    echo json_encode(array('status' => 'success', 'message' => $tr['Domain setting deletion successfully']));//'网域设定删除成功'
    break;
  case 'delSubDomain':
    $sid = $domainSettings['sid'];
    $component_id = $domainSettings['subdomainSettings'][$sid]['component'];
    unset($domainSettings['subdomainSettings'][$sid]);
    $sql = combineUpdateStatusSql($domainSettings['id'], json_encode($domainSettings['subdomainSettings']));
    $sqlResult = runSQL($sql);

    if (!$sqlResult) {
      echo json_encode(array('status' => 'fail', 'message' => $tr['Subdomain setting deletion failed']));//'子网域设定删除失败'
      die();
    }
    //刪除stylesetting
    if(isset($component_id) AND  $component_id != 0){
      runSQLall('UPDATE site_stylesetting set open = 2 WHERE id='.$component_id.';');
    }

    echo json_encode(array('status' => 'success', 'message' => $tr['Subdomain setting deletion successfully']));//'子网域设定删除成功'
    break;
  default:
    echo json_encode(array('status' => 'fail', 'message' => $tr['Wrong operation']));//'错误的动作请求'
    die();
    break;
}

function validateId($id, $sid = '')
{
  $id = filter_var($id, FILTER_SANITIZE_STRING);
  $domainSetting = getDomainSetting($id);
  if (!$domainSetting) {
    return false;
  }

  $subDomainSetting = json_decode($domainSetting->configdata, true);

  $setting = [
    'id' => $id,
    'domainSetting' => $domainSetting,
    'subdomainSettings' => $subDomainSetting
  ];

  if ($sid != '') {
    $sid = filter_var($sid, FILTER_SANITIZE_STRING);

    if (!in_array($sid, array_keys($subDomainSetting))) {
      return false;
    }

    $setting['sid'] = $sid;
  }

  return $setting;
}

function validateData($post)
{
  global $tr;
  $input = [
    'admainUrl' => '',
    'admainStatus' => '0',
    'websiteName' =>'',
    'websiteFooter' => '',
    'webType' => '',
    'hostName' => '',
    'googleID' => '',
    'companyName' => '',
    'companyShortName' => '',
    'subadmainStatus' => '0',
    'agent' => 'bigagent',
    'note' => '',
    'mobileThemePath' => '',
    'mobileSubadmainName' => '',
    'desktopThemePath' => '',
    'desktopSubadmainName' => '',
    'component' => '0',
    'upload_logo_img' => '',
    'upload_favicon_img' => ''
  ];

  $colName = [
    'admainUrl' => $tr['domain'],//'网域'
    'admainStatus' => $tr['domain status'],//'网域状态'
    'websiteName' =>$tr['subdmoain name'],//'子站台名称'
    'websiteFooter' => $tr['websiteFooter'],//'平台footer'
    'webType' => $tr['webType'],//网站类型'
    'hostName' => $tr['hostName'],//'主机名称'
    'googleID' => $tr['googleID'],//'Google分析ID'
    'companyName' => $tr['companyName'],
    'companyShortName' => $tr['companyShortName'],
    'subadmainStatus' => $tr['subdomain status'],//'子网域状态'
    'agent' => $tr['Identity Agent'],//'代理商'
    'note' => $tr['note'],//'备注'
    'mobileThemePath' => $tr['mobileThemePath'],//'手机版主题路径'
    'mobileSubadmainName' => $tr['mobileSubadmainName'],//'手机板子网域名称'
    'desktopThemePath' => $tr['desktopThemePath'],//'桌机版主题路径'
    'desktopSubadmainName' => $tr['desktopSubadmainName'],//'桌机版子网域名称'
    'component' => $tr['component'],//'广告组件'
    'upload_logo_img' => $tr['upload image'],//上傳圖檔
    'upload_favicon_img' => $tr['upload image']
  ];

  $required = [
    'admainUrl',
    'admainStatus',
    'webType',
    'subadmainStatus',
    'mobileThemePath',
    'mobileSubadmainName',
    'desktopThemePath',
    'desktopSubadmainName',
    'websiteName'
  ];

  foreach ($input as $k => $v) {
    // if ($k != 'note' && !array_key_exists($k, $post)) {
    //   return array('status' => false, 'result' => '必填栏位'.$input[$k].'不存在，请确认后再行操作');
    // }

    ${$k} = filter_var($post->$k, FILTER_SANITIZE_STRING);

    // if ($k != 'note' && ${$k} == '') {
    //   return array('status' => false, 'result' => $input[$k].'不合法');
    // }
    if (in_array($k, $required) && ${$k} == '') {
      return array('status' => false, 'result' => $colName[$k].$tr['Data is error']);//'不合法'
    }

    if ($k == 'agent' && ${$k} != '') {
      $checkResult = checkAgentAcc(${$k});

      if (!$checkResult) {
        return array('status' => false, 'result' => $tr['Account'].${$k}.$tr['Not legal or not acting']);//'帳號''不合法或不为代理'
      }
    }

    //字數限制
    if ($k == 'hostName' || $k == 'websiteName') {
      if(mb_strlen( ${$k} , "utf-8") > 10)
        return array('status' => false, 'result' => $colName[$k].$tr['cannot exceed 10 characters']);//'字數不得超過10字元'
    }

    $input[$k] = (${$k} == '') ? $input[$k] : ${$k};
  }

  return array('status' => true, 'result' => $input);
}

function checkAgentAcc($acc)
{
  $sql = <<<SQL
  SELECT *
  FROM root_member
  WHERE account = '{$acc}'
  AND therole = 'A'
SQL;

  return runSQL($sql);
}
