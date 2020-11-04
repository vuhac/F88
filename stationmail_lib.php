<?php

/**
 * @param [type] $sendto_system
 * @param [type] $sendfrom
 * @param [type] $send_subject_text
 * @param [type] $send_message_text
 * @return array
 * 
 * 多個收件人以逗號區隔, 如:userA,userB
 */
function send_mail($sendto_system, $sendfrom, $send_subject_text, $send_message_text)
{
  $sendmail_sql = '';

  $sendto_system = filter_var($sendto_system, FILTER_SANITIZE_STRING);
  $sendfrom = filter_var($sendfrom, FILTER_SANITIZE_STRING);
  $send_subject_text = filter_var($send_subject_text, FILTER_SANITIZE_STRING);
  $send_message_text = filter_var($send_message_text, FILTER_SANITIZE_STRING);

  if (empty($send_message_text) || empty($send_subject_text) || empty($sendto_system)) {
    $error_msg = '信件发送失败，收件人、信件主旨及内容不能为空';
    return array('status' => false, 'result' => $error_msg);
  }

  // 檢查信件主旨.內容字數
  if (mb_strlen($send_message_text, 'utf-8') > 1000) {
      $send_message_text = mb_substr($send_message_text, 0, 1000, 'utf8');
  }

  if (mb_strlen($send_subject_text, 'utf-8') > 100) {
      $send_message_text = mb_substr($send_message_text, 0, 100, 'utf8');
  }

  // 插入換行符號
  $send_message_text = nl2br($send_message_text);
  // 送進來的訊息內容沒有換行 , 塞入一個<br />
  $send_message_text = (strpos($send_message_text, '<br />') == false) ? $send_message_text.'<br />' : $send_message_text;

  // 多個與單一收件者 sql 組合
  $sendmail_sqlvalue = "(now(), '$send_subject_text', '$sendfrom', '$sendto_system', '$send_message_text', NULL, '1')";
  if (strpos($sendto_system, ',') != false) {
      $addressee = '';
      $addressee_arr = explode(',', $sendto_system);

      foreach ($addressee_arr as $key => $member_acc) {
        $member_isexist_result = check_member_isexist($member_acc);
        if (!$member_isexist_result) {
          $error_msg = '会员'.$member_acc.'不存在或冻结状态，请重新确认收件人是否正确';
          return array('status' => false, 'result' => $error_msg);
        }

        $addressee[$key] = "(now(), '$send_subject_text', '$sendfrom', '$member_acc', '$send_message_text', NULL, '1')";
      }

      $sendmail_sqlvalue = implode(",", $addressee);
  } else {
    $member_isexist_result = check_member_isexist($sendto_system);
    if (!$member_isexist_result) {
      $error_msg = '会员'.$sendto_system.'不存在或冻结状态，请重新确认收件人是否正确';
      return array('status' => false, 'result' => $error_msg);
    }
  }

  $sendmail_sql = 'INSERT INTO "root_stationmail" ("sendtime", "subject", "msgfrom", "msgto", "message", "readtime", "status")'."VALUES $sendmail_sqlvalue;";
  $sendmail_sql_result = runSQL($sendmail_sql);

  if (!$sendmail_sql_result) {
    $error_msg = '信件发送失败';
    return array('status' => false, 'result' => $error_msg);
  }

  return array('status' => true, 'result' => '信件发送成功');
}

/**
 * @param [type] $acc
 * @return array
 */
function check_member_isexist($acc)
{
  $sql = <<<SQL
  SELECT * 
  FROM root_member
  WHERE account = '$acc'
  AND status = '1'
SQL;

  $result = runSQL($sql);

  return $result;
}

/**
 * @param [type] $msgto_acc
 * @param [type] $tzonename
 * @return array
 */
function get_inboxmail($msgto_acc, $tzonename)
{
  $sql = <<<SQL
  SELECT 
    id,
    sendtime, 
    to_char((sendtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS' )  as sendtime, 
    msgfrom, 
    msgto, 
    subject, 
    message, 
    to_char((readtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS' ) as readtime 
  FROM root_stationmail 
  WHERE msgto = '$msgto_acc' 
  AND status = '1'
  ORDER BY id DESC;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    $error_msg = '查无收件匣信件';
    return array('status' => false, 'result' => $error_msg);
  }

  unset($result[0]);
  return array('status' => true, 'result' => (object)$result);
}

/**
 * @param [type] $msgto_acc
 * @param [type] $start_time
 * @param [type] $end_time
 * @param [type] $tzonename
 * @return array
 */
function get_timerange_inboxmail($msgto_acc, $start_time, $end_time, $tzonename)
{
  $sql = <<<SQL
  SELECT 
    id,
    sendtime, 
    to_char((sendtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as sendtime, 
    msgfrom, 
    msgto, 
    subject, 
    message, 
    to_char((readtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as readtime 
  FROM root_stationmail 
  WHERE msgto = '$msgto_acc' 
  AND to_char((sendtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD') >= '$start_time' 
  AND to_char((sendtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD') <= '$end_time' 
  AND status = '1'
  ORDER BY id DESC;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    $error_msg = $start_time.' ~ '.$end_time.' 区间内无收件匣信件';
    return array('status' => false, 'result' => $error_msg);
  }

  unset($result[0]);
  return array('status' => true, 'result' => (object)$result);
}

/**
 * @param [type] $msgfrom_acc
 * @param [type] $tzonename
 * @return array
 */
function get_sendbackupmail($msgfrom_acc, $tzonename)
{
  $sql = <<<SQL
  SELECT 
    id, 
    sendtime, 
    to_char((sendtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as sendtime, 
    msgfrom, 
    msgto, 
    subject, 
    message 
  FROM root_stationmail 
  WHERE msgfrom = '$msgfrom_acc' 
  AND status = 1 
  ORDER BY id DESC;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    $error_msg = '查无寄件备份';
    return array('status' => false, 'result' => $error_msg);
  }

  unset($result[0]);
  return array('status' => true, 'result' => (object)$result);
}

/**
 * @param [type] $msgfrom_acc
 * @param [type] $start_time
 * @param [type] $end_time
 * @param [type] $tzonename
 * @return array
 */
function get_timerange_sendbackupmail($msgfrom_acc, $start_time, $end_time, $tzonename)
{
  $sql = <<<SQL
  SELECT 
    id, 
    sendtime, 
    to_char((sendtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as sendtime, 
    msgfrom, 
    msgto, 
    subject, 
    message 
  FROM root_stationmail 
  WHERE msgfrom = '$msgfrom_acc' 
  AND to_char((sendtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD') >= '$start_time' 
  AND to_char((sendtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD') <= '$end_time' 
  AND status = 1 
  ORDER BY id;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    $error_msg = $start_time.' ~ '.$end_time.' 区间内无寄件备份';
    return array('status' => false, 'result' => $error_msg);
  }

  unset($result[0]);
  return array('status' => true, 'result' => (object)$result);
}

/**
 * @param [type] $timezone
 * @return array
 */
function get_tzonename($timezone)
{
  if(!empty($timezone)) {
		$tz = $timezone;
	} else {
		$tz = '+08';
  }
  
	// 轉換時區所要用的 sql timezone 參數
	$tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
	$tzone = runSQLALL($tzsql);

  if($tzone[0] == 1) {
		$tzonename = $tzone[1]->name;
	} else {
		$tzonename = 'posix/Etc/GMT-8';
  }
  
  return $tzonename;
}