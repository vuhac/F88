<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 公告訊息管理 對應 deposit_company_config.php
// File Name:	deposit_company_config_action.php
// Author:		Yuan
// Related:   服務 deposit_company_config.php
// DB Table:  root_deposit_company
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";


// var_dump($_POST);
// var_dump(json_decode($_POST['data']));
// die();

if(!isset($_SESSION['agent']) || $_SESSION['agent']->therole != 'R') {
  header('Location:./home.php');
  die();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $validate_r = validatedata($_POST);

  if ($validate_r['code'] == 'error') {
    echo json_encode($validate_r);
    die();
  }

  switch ($validate_r['result']['action']) {
    case 'insert':
      unset($validate_r['result']['action']);
      $sql_r = runSQL(combine_insert_sql($validate_r['result']));

      if (!$sql_r) {
        echo json_encode(array('code' => 'error', 'result' => '公司存款帐户管理新增失敗'));
        die();
      }

      echo json_encode(array('code' => 'success', 'result' => '公司存款帐户管理新增成功'));
      break;
    case 'update':
      unset($validate_r['result']['action']);
      $sql_r = runSQL(combine_update_sql($validate_r['result']));

      if (!$sql_r) {
        echo json_encode(array('code' => 'success', 'result' => '公司存款帐户管理更新失敗'));
        die();
      }

      echo json_encode(array('code' => 'success', 'result' => '公司存款帐户管理更新成功'));
      break;
    default:
      echo json_encode(array('code' => 'error', 'result' => '错误的动作请求'));
      break;
  }
}

function validatedata($post)
{
  $input = [];

  $postData = json_decode($post['data']);

  $action = [
    'insert',
    'update'
  ];

  $requiredField = [
    'type',
    'companyname',
    'accountname',
    'accountnumber',
    'grade',
    'transaction_limit',
    'cashfeerate',
    'status'
  ];

  $bankRequiredField = [
    'accountarea'
  ];

  $virtualmoneyRequiredField = [
    'exchangerate',
    'cryptocurrency'
  ];

  $optionalField = [
    'notes',
    'companyurl'
  ];

  $typeArr = [
    'bank',
    'wechat',
    'virtualmoney'
  ];

  if (!in_array($post['action'], $action)) {
    return array('code' => 'error', 'result' => '错误的动作请求');
  }

  $input['action'] = $post['action'];

  if ($post['action'] == 'update' && (!isset($postData->id) || empty($postData->id))) {
    return array('code' => 'error', 'result' => '查无此资料，更新失败');
  } elseif ($post['action'] == 'update' && isset($postData->id)) {
    $id = filter_var($postData->id, FILTER_SANITIZE_STRING);

    if (empty($id)) {
      return array('code' => 'error', 'result' => '查无此资料，更新失败');
    }

    $input['id'] = $id;
  }

  if (!in_array($postData->type, $typeArr)) {
    return array('code' => 'error', 'result' => '错误的帐户型态');
  }

  if ($postData->type == 'bank') {
    $requiredField = array_merge($requiredField, $bankRequiredField);
  } elseif ($postData->type == 'virtualmoney') {
    $requiredField = array_merge($requiredField, $virtualmoneyRequiredField);
  }

  foreach ($requiredField as $v) {
    if (!array_key_exists($v, $postData)) {
      return array('code' => 'error', 'result' => '请确认帐户型态必填栏位是否正确');
    }

    // $filter_r = ($v == 'grade' || $v == 'transaction_limit') ? validate_grade_transactionlimit($postData->$v, $v) : validate_otherdata($postData->$v);

    if ($v == 'grade' || $v == 'transaction_limit') {
      $filter_r = validate_grade_transactionlimit($postData->$v, $v);
    } elseif ($v == 'status') {
      $filter_r = filter_var($postData->$v, FILTER_SANITIZE_STRING);
    } else {
      $filter_r = validate_otherdata($postData->$v);
    }

    if ($filter_r === false) {
      return array('code' => 'error', 'result' => '请确认所有必填栏位皆已填写且不可为0');
    }

    $input[$v] = $filter_r;

    unset($postData->$v);
  }

  foreach ($optionalField as $v) {
    if (!isset($postData->$v)) {
      continue;
    }

    $input[$v] = (empty($postData->$v)) ? '' : filter_var($postData->$v, FILTER_SANITIZE_STRING);
  }

  foreach ($input['grade'] as $k => $v) {
    $grade = explode('_', $v);

    $grades[$grade[1]] = $grade[0];
  }

  foreach ($input['transaction_limit'] as $k => $v) {
    $transactionLimits[$k] = $v;
  }

  $input['grade'] = json_encode($grades);
  $input['transaction_limit'] = json_encode($transactionLimits);

  return array('code' => 'success', 'result' => $input);
}

function validate_grade_transactionlimit($input, $datatype)
{
  $result = [];

  if (!is_object($input) && !is_array($input)) {
    return false;
  }

  if (empty($input)) {
    return false;
  }

  foreach ($input as $k => $v) {
    $filter_r = filter_var($v, FILTER_SANITIZE_STRING);

    if ($filter_r == '' || $filter_r == '0') {
      return false;
    }

    if ($datatype == 'grade') {
      $result[] = $filter_r;
    } else {
      $result[$k] = $filter_r;
    }

  }

  return $result;
}

function validate_otherdata($input)
{
  $result = filter_var($input, FILTER_SANITIZE_STRING);

  if ($result == '' || $result == '0') {
    return false;
  }

  return $result;
}

function combine_insert_sql($input)
{
  $cols = [];
  $vals = [];

  foreach ($input as $k => $v) {
    if ($v == '') {
      continue;
    }

    $cols[] = $k;
    $vals[] = $v;
  }

  $col = implode(',', $cols);
  $val = implode("','", $vals);

  $sql = <<<SQL
  INSERT INTO root_deposit_company
  (
    {$col}
  ) VALUES (
    '{$val}'
  );
SQL;

  return $sql;
}

function combine_update_sql($input)
{
  $setValues = [];

  $id = $input['id'];

  unset($input['id']);
  foreach ($input as $k => $v) {
    if ($v == '') {
      continue;
    }

    $setValues[] = $k.' = '."'$v'";
  }

  $setValue = implode(",", $setValues);

  $sql = <<<SQL
  UPDATE root_deposit_company
  SET {$setValue}
  WHERE id = '{$id}';
SQL;

  return $sql;
}
