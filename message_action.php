<?php
// ----------------------------------------------------------------------------
// Features:	站內訊息動作處理
// File Name:	message_action.php
// Author:		Neil
// Related:   
// Log:
//
// ----------------------------------------------------------------------------
session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/lib_internal_message.php";

// var_dump($_POST);

if(!isset($_SESSION['agent']) || $_SESSION['agent']->therole != 'R') {
  header('Location:./home.php');
  die();
}

$action_arr = [
  'sned_msg',
  'send_msg_tostranger',
  'search_user',
  'search_user_data',
  'change_page',
  'update_readtime',
  'update_msg',
  'update_tabdata'
];

$action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);

if (!isset($_POST['action']) || !in_array($_POST['action'], $action_arr)) {
  $result = [
    'result' => 'fail',
    'message' => '错误的动作请求'
  ];

  echo json_encode($result);
  die();
}

$validate_result = validatedata($_POST);

// 驗證失敗
if (!$validate_result['status']) {
  $result = [
    'result' => 'fail',
    'message' => $validate_result['message']
  ];

  echo json_encode($result);
  die();
}

switch ($action) {
  case 'sned_msg':
    echo json_encode(sned_msg_touser($validate_result));
    break;
  case 'send_msg_tostranger':
    echo json_encode(send_msg_tostranger($validate_result));
    break;
  case 'search_user':
    echo json_encode(get_all_msg($validate_result));
    break;
  case 'change_page':
    echo json_encode(change_page($validate_result));
    break;
  case 'update_readtime':
    echo json_encode(update_readtime($validate_result['result']['user_acc']));
    break;
  case 'update_msg':
    echo json_encode(get_all_msg($validate_result));
    break;
  case 'update_tabdata':
    $update_r = update_readtime($validate_result['result']['user_acc']);
    if ($update_r['result'] == 'fail') {
      echo json_encode($update_r);
      die();
    }

    echo json_encode(get_all_msg($validate_result));
    break;
  default:
    $result = [
      'result' => 'fail',
      'message' => '错误的尝试'
    ];

    echo json_encode($result);
    break;
}

// 驗證資料
function validatedata($post)
{
  $input = [
    'result' => '',
    'user_acc' => '',
    'message' => '',
    'page_action' => '',
    'page' => '',
    'total_msg_number' => ''
  ];

  if (isset($post['user_acc']) && !empty($post['user_acc'])) {
    $acc = filter_var($post['user_acc'], FILTER_SANITIZE_STRING);

    if (empty($acc)) {
      $error_msg = '不合法的会员帐号';

      return array('status' => false, 'message' => $error_msg);
    }

    $check_member_result = (strpos($acc, ',') != false) ? validat_users($acc) : validat_user($acc);

    if (!$check_member_result) {
      $error_msg = '收件人不存在或冻结状态，请重新确认';

      return array('status' => false, 'message' => $error_msg);
    }

    $input['user_acc'] = $acc;

  }

  if (isset($post['message']) && !empty($post['message'])) {
    $message = filter_var($post['message'], FILTER_SANITIZE_STRING);

    if (empty($message)) {
      $error_msg = '不合法的讯息内容';

      return array('status' => false, 'message' => $error_msg);
    }

    // 超過1000字全部去除
    if (mb_strlen($message, 'utf-8') > 1000) {
      $message = mb_substr($message, 0, 1000, 'utf8');
    }

    // 插入換行符號
    $message = nl2br($message);
    // 送進來的訊息內容沒有換行, 塞入一個<br />
    // $message = (strpos($message, '<br />') == false) ? $message.'<br />' : $message.'<br />';

    $input['message'] = $message.'<br />';

  }

  if (isset($post['page_action']) && !empty($post['page_action'])) {
    $page_action = filter_var($post['page_action'], FILTER_SANITIZE_STRING);

    if (empty($page_action)) {
      $error_msg = '不合法的动作';

      return array('status' => false, 'message' => $error_msg);
    }

    $input['page_action'] = $page_action;
  }

  if (isset($post['page']) && !empty($post['page'])) {
    $page = filter_var($post['page'], FILTER_SANITIZE_STRING);

    if (empty($page)) {
      $error_msg = '不合法的页数';

      return array('status' => false, 'message' => $error_msg);
    }

    $input['page'] = $page;
  }

  if (isset($post['total_msg_number']) && !empty($post['total_msg_number'])) {
    $total_msg_number = filter_var($post['total_msg_number'], FILTER_SANITIZE_STRING);

    if (empty($total_msg_number)) {
      $error_msg = '不合法的讯息数';

      return array('status' => false, 'message' => $error_msg);
    }

    $input['total_msg_number'] = $total_msg_number;
  }

  return array('status' => true, 'result' => $input);
}

function validat_user($acc)
{
  $sql = <<<SQL
  SELECT * 
  FROM root_member
  WHERE account = '{$acc}'
  AND status != '0';
SQL;

  $sql_result = runSQL($sql);

  return $sql_result;
}

function validat_users($acc)
{
  $acc_arr = explode(',', $acc);
  $acc = implode("','", $acc_arr);

  $sql = <<<SQL
  SELECT * 
  FROM root_member
  WHERE account IN ('{$acc}')
  AND status != '0';
SQL;

  $result = runSQL($sql);

  return $result;
}
