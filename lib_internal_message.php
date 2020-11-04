<?php
// ----------------------------------------------------------------------------
// Features:	站內訊息lib
// File Name:	lib_internal_message.php
// Author:		Neil
// Related:   
// Log:
//
// ----------------------------------------------------------------------------

function get_memberlist()
{
  global $tzonename;
  global $stationmail;

  $users = [];

  $sql = <<<SQL
  SELECT DISTINCT msgto, 
          msgfrom, 
          MAX(to_char((sendtime AT TIME ZONE '{$tzonename}'),'YYYY-MM-DD HH24:MI:SS' )) AS sendtime
  FROM root_stationmail
  GROUP BY msgto, msgfrom
  ORDER BY sendtime DESC;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  foreach ($result as $v) {
    if ($v->msgto != $stationmail['sendto_system_cs'] && !in_array($v->msgto, $users)) {
      $users[] = $v->msgto;
    }

    if ($v->msgfrom != $stationmail['sendto_system_cs'] && !in_array($v->msgfrom, $users)) {
      $users[] = $v->msgfrom;
    }
  }

  return $users;
}

function get_msg_byacc($user_acc)
{
  global $tzonename;
  global $stationmail;

  $cs_acc = $stationmail['sendto_system_cs'];

  $sql = <<<SQL
  SELECT id, 
          sendtime,
          subject,
          to_char((sendtime AT TIME ZONE '{$tzonename}'),'YYYY-MM-DD HH24:MI:SS' ) as cst_sendtime,
          msgfrom,
          msgto,
          message,
          to_char((readtime AT TIME ZONE '{$tzonename}'),'YYYY-MM-DD HH24:MI:SS' ) as readtime,
          status 
  FROM root_stationmail 
  WHERE (msgfrom = '{$cs_acc}' AND msgto = '{$user_acc}') 
  OR (msgfrom = '{$user_acc}' AND msgto = '{$cs_acc}') 
  ORDER BY sendtime DESC;
  -- ORDER BY id DESC;
  -- LIMIT 30;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  return $result;
}

function get_msg_bypage($user_acc, $page, $page_action)
{
  global $tzonename;
  global $stationmail;

  $cs_acc = $stationmail['sendto_system_cs'];

  $sql = <<<SQL
  SELECT id, 
          sendtime, 
          subject,
          to_char((sendtime AT TIME ZONE '{$tzonename}'),'YYYY-MM-DD HH24:MI:SS' ) as cst_sendtime ,
          msgfrom,
          msgto,
          message,
          to_char((readtime AT TIME ZONE '{$tzonename}'),'YYYY-MM-DD HH24:MI:SS' ) as readtime,
          status 
  FROM root_stationmail 
  WHERE msgfrom = '{$user_acc}' 
  OR msgto = '{$user_acc}' 
  ORDER BY sendtime DESC
  -- ORDER BY id DESC
  LIMIT 30 
  OFFSET {$page};
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  return $result;
}

function get_unread_msg_byacc($user_acc)
{
  global $tzonename;
  global $stationmail;

  $cs_acc = $stationmail['sendto_system_cs'];

  $sql = <<<SQL
  SELECT msgfrom,
          count(id) AS unread_count
  FROM root_stationmail 
  WHERE readtime IS NULL 
  AND msgfrom IN ('{$user_acc}')
  AND msgto = '{$cs_acc}'
  GROUP BY msgfrom;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  foreach ($result as $v) {
    $unread_arr[$v->msgfrom] = $v->unread_count;
  }

  return $unread_arr;
}

function sned_msg_touser($validate_result)
{
  global $stationmail;

  $sql_val = [
    'to_acc' => $validate_result['result']['user_acc'],
    'from_acc' => $stationmail['sendto_system_cs'],
    'title' => '客服讯息',
    'message' => $validate_result['result']['message']
  ];

  $sql = combine_insert_sql($sql_val);
  $sql_result = runSQL($sql);

  if (!$sql_result) {
    $result = [
      'result' => 'fail',
      'message' => '讯息新增失败'
    ];

    return $result;
  }

  $now = gmdate('Y-m-d H:i:s',time() + +8 * 3600);

  $result = [
    'result' => 'success',
    'message' => $validate_result['result']['message'],
    'cst_sendtime' => $now
  ];

  return $result;
}

function send_msg_tostranger($validate_result)
{
  global $stationmail;

  $sql_val = [
    'to_acc' => $validate_result['result']['user_acc'],
    'from_acc' => $stationmail['sendto_system_cs'],
    'title' => '客服讯息',  
    'message' => $validate_result['result']['message']
  ];

  $sql = combine_insert_sql($sql_val);
  $sql_result = runSQL($sql);

  if (!$sql_result) {
    $result = [
      'result' => 'fail',
      'message' => '讯息新增失败'
    ];

    return $result;
  }

  $now = gmdate('Y-m-d H:i:s',time() + +8 * 3600);

  $result = [
    'result' => 'success',
    'accs' => $validate_result['result']['user_acc'],
    'message' => $validate_result['result']['message'],
    'cst_sendtime' => $now
  ];

  return $result;
}

function get_all_msg($validate_result)
{
  $all_msg = get_msg_byacc($validate_result['result']['user_acc']);

  if (!$all_msg) {
    $result = [
      'result' => 'fail',
      'message' => '使用者讯息查询失败'
    ];

    return $result;
  }

  $total_msg_count = count($all_msg);
  $all_msg = array_slice($all_msg, 0, 30);
  foreach ($all_msg as $v) {
    $msg_data[] = [
      'msgfrom' => $v->msgfrom,
      'subject' => $v->subject,
      'message' => $v->message,
      'sendtime' => $v->cst_sendtime,
      'readtime' => $v->readtime,
      'status' => $v->status
    ];
  }

  $result = [
    'result' => 'success',
    'total_msg_count' => $total_msg_count,
    'message' => array_reverse($msg_data)
  ];

  return $result;
}

function change_page($validate_result)
{
  if ($validate_result['result']['page_action'] == 'nextpage') {
    $total_msg_num = $validate_result['result']['total_msg_number'];
    $page = $validate_result['result']['page'];
    $nextpage = $page + 30;
    $lastpage = $page;
    $start_msg_number = $page + 1;

    if ((($total_msg_num - 30) % 30) == 0) {
      $end_msg_number = $nextpage;
    } else {
      $end_msg_number = (($total_msg_num - $page) > 30) ? ($page + 30) : $total_msg_num;
    }
  } else {
    $page = $validate_result['result']['page'] - 30;
    $lastpage = $page;
    $nextpage = $validate_result['result']['page'];
    $start_msg_number = $page + 1;
    $end_msg_number = $nextpage;
  }

  $msg = get_msg_bypage($validate_result['result']['user_acc'], $page, $validate_result['result']['page_action']);
  $col_number = $lastpage + 1;
  $total_msg_count = count($msg) + 30;

  if (!$msg ) {
    $result = [
      'result' => 'fail',
      'message' => '查无讯息'
    ];

    return $result;
  }

  $msg = array_reverse($msg);
  foreach ($msg as $v) {
    $msg_data[] = [
      'msgfrom' => $v->msgfrom,
      'subject' => $v->subject,
      'message' => $v->message,
      'sendtime' => $v->cst_sendtime,
      'readtime' => $v->readtime,
      'status' => $v->status
    ];
  }

  $result = [
    'result' => 'success',
    'lastpage' => $lastpage,
    'nextpage' => $nextpage,
    'col_number' => $col_number,
    'start_msg_number' => $start_msg_number,
    'end_msg_number' => $end_msg_number,
    'message' => $msg_data
  ];

  return $result;
}

function update_readtime($acc) {
  $result = [
    'result' => 'success',
    'message' => ''
  ];

  $sql = combine_update_readtime_sql($acc);
  $sql_result = runSQL($sql);

  if (!$sql_result) {
    $result = [
      'result' => 'fail',
      'message' => '已读讯息时间更新失败'
    ];

    return $result;
  }

  return $result;
}

// 組合新增訊息sql
function combine_insert_sql($sql_val)
{
  $insert_value = '';

  if (strpos($sql_val['to_acc'], ',') != false) {
    $acc_arr = explode(',', $sql_val['to_acc']);

    foreach ($acc_arr as $k => $to_acc) {
      $insert_value[$k] = "(now(), '".$sql_val['from_acc']."', '".$to_acc."', '".$sql_val['title']."', '".$sql_val['message']."', NULL, '1')";
    }

    $insert_value = implode(",", $insert_value);
  } else {
    $insert_value = "(now(), '".$sql_val['from_acc']."', '".$sql_val['to_acc']."', '".$sql_val['title']."', '".$sql_val['message']."', NULL, '1')";
  }

  $sql = <<<SQL
  INSERT INTO root_stationmail 
  (
    sendtime, msgfrom, msgto, subject, message, readtime, status
  ) VALUES {$insert_value};
SQL;

  return $sql;
}

function combine_update_readtime_sql($acc)
{
  $sql = <<<SQL
  UPDATE root_stationmail 
  SET readtime = now() 
  WHERE msgfrom = '{$acc}';
SQL;

  return $sql;
}