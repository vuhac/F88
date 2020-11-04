<?php
// ----------------------------------------------------------------------------
// Features:	站內信件動作處理
// File Name:	mail_action.php
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

require_once dirname(__FILE__) ."/lib_mail.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

$postData = json_decode($_POST['data']);
$validateResult = validatePost($postData);

if (!$validateResult) {
  echo json_encode(['status' => 'fail', 'result' => $tr['Data is error']]);
  die();
}

switch ($_POST['action']) {
  case 'search':
    $source = $validateResult['source'];
    $condition = getSearchCondition($validateResult);

    $mail = getMailDataBySearchCondition($condition, $source);

    if (!$mail) {
      echo json_encode(['status' => 'fail', 'result' => $tr['no mail was found']]);
      die();
    }

    $mailCount = $mail['count'];
    unset($mail['count']);

    echo json_encode(['status' => 'success', 'result' => $mail, 'count' => $mailCount]);
    break;
  case 'memberList':
    $accList = getMailRecipientSenderAccList($validateResult['mailCode'], $validateResult['mailType'], $validateResult['source']);
    if (!$accList) {
      echo json_encode(['status' => 'fail', 'result' => $tr['no mail was found']]);
      die();
    }

    $data = [
      'accList' => $accList,
      'count' => ($validateResult['mailType'] == 'group') ? getMailRecipientAccCount($validateResult['mailCode']) : '1'
    ];

    echo json_encode(['status' => 'success', 'result' => $data]);
    break;
  case 'content':
    $mailContent = getMailContent($validateResult['mailCode'], $validateResult['mailType']);

    if (!$mailContent) {
      echo json_encode(['status' => 'fail', 'result' => $tr['no mail was found']]);
      die();
    }

    if ($validateResult['source'] == 'inbox' && $mailContent['cs_readtime'] == '') {
      $updateResult = updateRead($validateResult['mailCode']);

      if (!$updateResult) {
        echo json_encode(['status' => 'fail', 'result' => $tr['The status of read you update is failed']]);
        die();
      }
    }

    echo json_encode(['status' => 'success', 'result' => $mailContent]);
    break;
  case 'loadMore':
    $source = $validateResult['source'];
    $count = $validateResult['count'];
    $condition = getSearchCondition($validateResult);

    $mail = getLoadMoreMailData($condition, $source, $count);

    if (!$mail) {
      echo json_encode(['status' => 'fail', 'result' => $tr['no mail was found']]);
      die();
    }

    $mailCount = $mail['count'];
    unset($mail['count']);

    echo json_encode(['status' => 'success', 'result' => $mail, 'count' => $mailCount]);
    break;
  case 'loadMoreAccList':
    $accList = getMailRecipientSenderAccList($validateResult['mailCode'], $validateResult['mailType'], $validateResult['source'], $validateResult['count']);

    if (!$accList) {
      echo json_encode(['status' => 'fail', 'result' => $tr['no mail was found']]);
      die();
    }

    $data = [
      'accList' => $accList,
      'count' => getMailRecipientAccCount($validateResult['mailCode'])
    ];

    echo json_encode(['status' => 'success', 'result' => $data]);
    break;
  case 'delete':
    $mailcode = str_replace('delMail=', '', $validateResult['mails']);
    $mailcode = explode("&", $mailcode);

    $delResult = deleteMail($mailcode);

    if (!$delResult) {
      echo json_encode(['status' => 'fail', 'result' => $tr['Mail delete unsuccessfully please try again']]);
      die();
    }

    echo json_encode(['status' => 'success', 'result' => $tr['Mail delete successfully']]);
    break;
  case 'markRead':
    $mailcode = str_replace('delMail=', '', $validateResult['mails']);
    $mailcode = explode("&", $mailcode);

    if ($validateResult['markAction'] != 'markRead' && $validateResult['markAction'] != 'markUnread') {
      echo json_encode(['status' => 'success', 'result' => $tr['Bad request']]);
    }

    if ($validateResult['markAction'] == 'markRead') {
      $markResult = markRead($mailcode);
    } else {
      $markResult = markUnread($mailcode);
    }

    if (!$markResult) {
      echo json_encode(['status' => 'success', 'result' => $tr['The status of read you update is failed']]);
    }

    echo json_encode(['status' => 'success', 'result' => $mailcode]);
    break;
  default:
    echo json_encode(['status' => 'fail', 'result' => $tr['Bad request']]);
    break;
}

function validatePost($post)
{
  $input = [];

  foreach ($post as $k => $v) {
    $input[$k] = filter_var($v, FILTER_SANITIZE_STRING);

    if ($input[$k] == '') {
      continue;
    }
  }

  return $input;
}