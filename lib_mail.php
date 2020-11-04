<?php
function getHowLongAgo($date)
{
  $time = strtotime($date);
  $now = strtotime(gmdate('Y-m-d H:i:s',time() + -4*3600));
  // $now = time();
  $ago = $now - $time;

  if($ago < 60) {
    $when = round($ago);
    $s = ($when == 1)?"second":"seconds";
    return "$when $s ago";
  } elseif($ago < 3600) {
    $when = round($ago / 60);
    $m = ($when == 1)?"minute":"minutes";
    return "$when $m ago";
  } elseif($ago >= 3600 && $ago < 86400) {
    $when = round($ago / 60 / 60);
    $h = ($when == 1)?"hour":"hours";
    return "$when $h ago";
  } elseif($ago >= 86400 && $ago < 2629743.83) {
    $when = round($ago / 60 / 60 / 24);
    $d = ($when == 1)?"day":"days";
    return "$when $d ago";
  } elseif($ago >= 2629743.83 && $ago < 31556926) {
    $when = round($ago / 60 / 60 / 24 / 30.4375);
    $m = ($when == 1)?"month":"months";
    return "$when $m ago";
  } else {
    $when = round($ago / 60 / 60 / 24 / 365);
    $y = ($when == 1)?"year":"years";
    return "$when $y ago";
  }
}

function setMemcache($memcache, $key, $data, $timeout)
{
  global $config;
  global $system_mode;

  $memcache->addServer('localhost', 11211) or die ("Could not connect memcache server !! ");

  $key = ($system_mode == 'developer') ? $key.$config['website_domainname'] : $key.$config['projectid'];
  $memcache->set($key, $data, $timeout) or die ("Failed to save data at the memcache server");
}

function getMemcache($memcache, $key)
{
  global $config;
  global $system_mode;

  $memcache->addServer('localhost', 11211) or die ("Could not connect memcache server !! ");
  $key = ($system_mode == 'developer') ? $key.$config['website_domainname'] : $key.$config['projectid'];

  return $memcache->get($key);
}

function delMemcache($memcache, $key)
{
  global $config;
  global $system_mode;

  $memcache->addServer('localhost', 11211) or die ("Could not connect memcache server !! ");
  $key = ($system_mode == 'developer') ? $key.$config['website_domainname'] : $key.$config['projectid'];
  $memcache->delete($key);
}

function getInBoxMail($acc, $tzname = 'posix/Etc/GMT+4')
{
  $sql = <<<SQL
  SELECT *,
        to_char((sendtime AT TIME ZONE '{$tzname}') ,'YYYY-MM-DD HH24:MI:SS') AS sendtime,
        to_char((cs_readtime AT TIME ZONE '{$tzname}') ,'YYYY-MM-DD HH24:MI:SS') AS cs_readtime
  FROM root_stationmail
  WHERE msgto = '{$acc}'
  AND cs_status = 1
  ORDER BY id DESC
  LIMIT 10;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  return $result;
}

function getSentMail()
{
  $sql = <<<SQL
  SELECT *
  FROM sent_mail_view
  ORDER BY sendtime DESC
  LIMIT 10;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  return $result;
}

function getLoadMoreMailData(array $condition, $source, $count, $tzname = 'posix/Etc/GMT+4')
{
  global $stationmail;

  $data = [];

  $sendtime = '';
  $table = 'sent_mail_view';
  $status = '';

  if ($source === 'inbox') {
    $table = 'root_stationmail';
    $status = 'AND cs_status = 1';
    $sendtime = ",to_char((sendtime AT TIME ZONE '{$tzname}') ,'YYYY-MM-DD HH24:MI:SS') AS sendtime";
  }

  // $sqlWhereStr = ($condition['isSearch']) ? 'WHERE '.getSearchConditionSqlWhere($condition, $source) : '';

  if ($condition['isSearch']) {
    $sqlWhereStr = 'WHERE '.getSearchConditionSqlWhere($condition, $source);
  } else {
    $col = ($source === 'inbox') ? 'msgto' : 'msgfrom';
    $sqlWhereStr = 'WHERE '.$col." = '".$stationmail['sendto_system_cs']."'";
  }

  $sql = <<<SQL
  SELECT *{$sendtime}
  FROM {$table}
  {$sqlWhereStr}
  {$status}
  ORDER BY {$table}.sendtime DESC
  LIMIT 10
  OFFSET {$count};
SQL;

  $result = runSQLall($sql);

  $mailCount = getMailCount($table, $sqlWhereStr);

  if (empty($result[0]) || !$mailCount) {
    return false;
  }

  unset($result[0]);

  foreach ($result as $v) {
    if ($table == 'root_stationmail') {
      $v->cs_readtime = ($v->cs_readtime != '') ? $v->cs_readtime : '';
    }

    $data[] = array_merge((array)$v, ['howlongage' => getHowLongAgo($v->sendtime)]);
  }

  $data['count'] = $mailCount;

  return $data;
}

function getMailDataBySearchCondition(array $condition, $source, $tzname = 'posix/Etc/GMT+4')
{
  $data = [];

  $sendtime = '';
  $table = 'sent_mail_view';
  $status = '';

  if ($source === 'inbox') {
    $table = 'root_stationmail';
    $status = 'AND cs_status = 1';
    $sendtime = ",to_char((sendtime AT TIME ZONE '{$tzname}') ,'YYYY-MM-DD HH24:MI:SS') AS sendtime";
  }

  $sqlWhereStr = 'WHERE '.getSearchConditionSqlWhere($condition, $source);

  $sql = <<<SQL
    SELECT *{$sendtime}
    FROM {$table}
    {$sqlWhereStr}
    {$status}
    ORDER BY {$table}.sendtime DESC
    LIMIT 10
    OFFSET 0;
SQL;

  $result = runSQLall($sql);

  $mailCount = getMailCount($table, $sqlWhereStr);

  if (empty($result[0]) || !$mailCount) {
    return false;
  }

  unset($result[0]);

  foreach ($result as $v) {
    if ($table == 'root_stationmail') {
      $v->cs_readtime = ($v->cs_readtime != '') ? $v->cs_readtime : '';
    }

    $data[] = array_merge((array)$v, ['howlongage' => getHowLongAgo($v->sendtime)]);
  }

  $data['count'] = $mailCount;

  return $data;
}

function getMailCount($table, $sqlWhereStr)
{
  $status = ($table == 'root_stationmail')? 'AND cs_status = 1' : '';
  $sql = <<<SQL
  SELECT {$table}.mailcode
  FROM {$table}
  {$sqlWhereStr}
  {$status}
  ORDER BY {$table}.sendtime DESC;
SQL;

  $result = runSQL($sql);

  if (!$result) {
    return false;
  }

  return $result;
}

function getMailContent($mailcode, $mailtype, $tzname = 'posix/Etc/GMT+4')
{
  global $tr;

  // $table = ($mailtype == 'group') ? 'root_cs_groupmail' : 'root_stationmail';
  // $msgto = ($mailtype == 'group') ? 'sent_mail_view.msgto' : $table.'.msgto';

  if ($mailtype == 'group') {
    $sql = <<<SQL
    SELECT root_cs_groupmail.id,
        sent_mail_view.msgto,
        root_cs_groupmail.msgfrom,
        root_cs_groupmail.subject,
        root_cs_groupmail.message,
        root_cs_groupmail.mailcode,
        root_cs_groupmail.mailtype,
        root_cs_groupmail.template_mail,
        to_char((root_cs_groupmail.sendtime AT TIME ZONE '{$tzname}') ,'YYYY-MM-DD HH24:MI:SS') AS sendtime
    FROM root_cs_groupmail
    LEFT JOIN sent_mail_view
    ON root_cs_groupmail.mailcode = sent_mail_view.mailcode
    WHERE root_cs_groupmail.mailcode = '{$mailcode}';
SQL;
  } else {
    $sql = <<<SQL
    SELECT root_stationmail.id,
        root_stationmail.msgto,
        root_stationmail.msgfrom,
        root_stationmail.subject,
        root_stationmail.message,
        root_stationmail.mailcode,
        root_stationmail.mailtype,
        root_stationmail.status,
        to_char((root_stationmail.sendtime AT TIME ZONE '{$tzname}') ,'YYYY-MM-DD HH24:MI:SS') AS sendtime,
        to_char((root_stationmail.cs_readtime AT TIME ZONE '{$tzname}') ,'YYYY-MM-DD HH24:MI:SS') AS cs_readtime
    FROM root_stationmail
    WHERE root_stationmail.mailcode = '{$mailcode}';
SQL;
  }

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  $data = array_merge((array)$result[1], ['howlongage' => getHowLongAgo($result[1]->sendtime)]);
  $data['message'] = htmlspecialchars_decode($data['message']);

  if ($mailtype == 'group') {
    $data['msgto'] = $tr['total'].trim($data['msgto']).$tr['people'];
  }

  return $data;
}

function getMailRecipientSenderAccList($mailCode, $mailType, $mailSource, $count = 0)
{
  $acclist = [];

  if ($mailSource == 'sent' && $mailType == 'group') {
    $sql = <<<SQL
    SELECT root_member.id,
          root_member_groupmail.msgto,
          root_member_groupmail.readtime,
          root_member_groupmail.status
    FROM root_member_groupmail
    LEFT JOIN root_member
    ON root_member_groupmail.msgto = root_member.account
    WHERE root_member_groupmail.mailcode = '{$mailCode}'
    ORDER BY root_member_groupmail.msgto
    LIMIT 10
    OFFSET {$count};
SQL;
  } else {
    $sql = <<<SQL
    SELECT root_member.id,
          root_stationmail.msgto,
          root_stationmail.msgfrom,
          root_stationmail.readtime,
          root_stationmail.status
    FROM root_stationmail
    LEFT JOIN root_member
    ON root_stationmail.msgto = root_member.account
    WHERE root_stationmail.mailcode = '{$mailCode}';
SQL;
  }

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  foreach ($result as $v) {
    $acclist[] = [
      'id' => $v->id,
      // 'acc' => ($mailType == 'group') ? $v->msgto : $v->msgfrom,
      'acc' => ($mailSource == 'inbox') ? $v->msgfrom : $v->msgto,
      'readtime' => ($v->readtime != '') ? $v->readtime : '',
      'isDelete' => $v->status
    ];
  }

  return $acclist;
}

function getMailRecipientAccCount($mailcode)
{
  $sql = <<<SQL
  SELECT COUNT(mailcode)
  FROM root_member_groupmail
  WHERE mailcode = '{$mailcode}';
SQL;

  $result = runSQLall($sql);

  return $result[1]->count;
}

function getLoadMoreRecipientAcc($mailcode, $count, $tzname = 'posix/Etc/GMT+4')
{
  $acclist = [];

  $sql = <<<SQL
  SELECT msgto,
        to_char((readtime AT TIME ZONE '{$tzname}') ,'YYYY-MM-DD HH24:MI:SS') AS readtime
  FROM root_member_groupmail
  WHERE mailcode = '{$mailcode}'
  ORDER BY msgto
  LIMIT 10
  OFFSET {$count};
SQL;


  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  foreach ($result as $v) {
    $acclist[] = [
      'acc' => $v->msgto,
      'readtime' => ($v->readtime != '') ? $v->readtime : ''
    ];
  }

  return $acclist;
}

function updateRead($mailcode)
{
  global $stationmail;

  $sql = <<<SQL
  UPDATE root_stationmail
  SET cs_readtime = now()
  WHERE mailcode = '{$mailcode}'
  AND msgto = '{$stationmail['sendto_system_cs']}'
  AND cs_readtime IS NULL;
SQL;

  $result = runSQL($sql);

  if (!$result) {
    return false;
  }

  return true;
}

function markRead($mailcode)
{
  global $stationmail;

  $sql = [];

  foreach ($mailcode as $code) {
    $codeStr = explode('_', $code);

    $sql[] = <<<SQL
    UPDATE root_stationmail
    SET cs_readtime = now()
    WHERE mailcode = '{$codeStr[0]}'
    AND msgto = '{$stationmail['sendto_system_cs']}';
SQL;

    if (count($sql) >= 200) {
      $sql = 'BEGIN;'.implode(';', $sql).'COMMIT;';
      $result = runSQLtransactions($sql);

      if (!$result) {
        return false;
      }

      $sql = [];
    }
  }

  $sql = 'BEGIN;'.implode(';', $sql).'COMMIT;';

  return runSQLtransactions($sql);
}

function markUnread($mailcode)
{
  global $stationmail;

  $sql = [];

  foreach ($mailcode as $code) {
    $codeStr = explode('_', $code);

    $sql[] = <<<SQL
    UPDATE root_stationmail
    SET cs_readtime = NULL
    WHERE mailcode = '{$codeStr[0]}'
    AND msgto = '{$stationmail['sendto_system_cs']}';
SQL;

    if (count($sql) >= 200) {
      $sql = 'BEGIN;'.implode(';', $sql).'COMMIT;';
      $result = runSQLtransactions($sql);

      if (!$result) {
        return false;
      }

      $sql = [];
    }
  }

  $sql = 'BEGIN;'.implode(';', $sql).'COMMIT;';

  return runSQLtransactions($sql);
}

function deleteMail($mailcode)
{
  $sql = [];

  foreach ($mailcode as $code) {
    $codeStr = explode('_', $code);

    $table = ($codeStr[1] == 'group') ? 'root_cs_groupmail' : 'root_stationmail';
    $whereCol = ($codeStr[1] == 'group') ? 'status' : 'cs_status';

    $sql[] = <<<SQL
    UPDATE {$table}
    SET {$whereCol} = 0
    WHERE mailcode = '{$codeStr[0]}';
SQL;

    if (count($sql) >= 200) {
      $sql = 'BEGIN;'.implode(';', $sql).'COMMIT;';
      $result = runSQLtransactions($sql);

      if (!$result) {
        return false;
      }

      $sql = [];
    }
  }

  $sql = 'BEGIN;'.implode(';', $sql).'COMMIT;';

  return runSQLtransactions($sql);
}

function getSearchCondition($data)
{
  unset($data['order']);
  unset($data['source']);
  unset($data['count']);

  return $data;
}

function getSearchConditionSqlWhere($condition, $source)
{
  global $stationmail;

  $conditionArr = [];
  $isReadSqlWhere = [];
  $conditionCol = ['msgto', 'msgfrom', 'subject', 'date', 'read', 'unread'];

  if ($source === 'inbox') {
    // $table = 'root_stationmail';
    $sqlWhereStr = "msgto = '".$stationmail['sendto_system_cs']."'";
  } else {
    // $table = 'sent_mail_view';
    $sqlWhereStr = "msgfrom = '".$stationmail['sendto_system_cs']."'";
  }

  foreach ($conditionCol as $v) {
    if (!isset($condition[$v]) || $condition[$v] == '') {
      continue;
    }

    if ($v == 'date') {
      switch ($condition[$v]) {
        case 'today':
          $sdate = date('Y-m-d', strtotime('now')).' 00:00:00';
          $edate = date('Y-m-d', strtotime('now')).' 23:59:59';
          $conditionArr[] = "(sendtime BETWEEN '$sdate' AND '$edate'".')';
          break;
        case 'yesterday':
          $sdate = date('Y-m-d', strtotime(' - 1 days')).' 00:00:00';
          $edate = date('Y-m-d', strtotime(' - 1 days')).' 23:59:59';
          $conditionArr[] = "(sendtime BETWEEN '$sdate' AND '$edate'".')';
          break;
        case 'thisWeek':
          $sdate = date('Y-m-d', strtotime('now - ' . date('w', time()).'days')).' 00:00:00';
          $edate = date('Y-m-d H:i:s', strtotime('now'));
          $conditionArr[] = "(sendtime BETWEEN '$sdate' AND '$edate'".')';
          break;
        case 'thisMonth':
          $sdate = date('Y-m-01', strtotime('now')).' 00:00:00';
          $edate = date("Y-m-t", strtotime('now')).' 23:59:59';
          $conditionArr[] = "(sendtime BETWEEN '$sdate' AND '$edate'".')';
          break;
        default:
          break;
      }
    } elseif ($v == 'read') {
      if(empty($condition[$v])) {
        continue;
      }
      $isReadSqlWhere[] = "cs_readtime IS NOT NULL";
    } elseif ($v == 'unread') {
      if(empty($condition[$v])) {
        continue;
      }
      $isReadSqlWhere[] = "cs_readtime IS NULL";
    } else {
      if ($source === 'inbox') {
        $conditionArr[] = ($v != 'subject') ? $v." = '$condition[$v]'" : $v." LIKE '%$condition[$v]%'";
      } else {
        $conditionArr[] = ($v != 'subject') ? "(mailcode IN (SELECT mailcode FROM root_member_groupmail WHERE msgto = '$condition[$v]') OR msgto = '$condition[$v]')" : $v." LIKE '%$condition[$v]%'";
      }
    }
  }

  if (!empty(count($conditionArr))) {
    if (count($conditionArr) > 1) {
      $sqlWhereStr .= ' AND '.implode(' AND ', $conditionArr);
    } else {
      $sqlWhereStr .= ' AND '.$conditionArr[0];
    }
  }

  if (!empty(count($isReadSqlWhere))) {
    if (count($isReadSqlWhere) > 1) {
      $sqlWhereStr .= ' AND ('.implode(' OR ', $isReadSqlWhere).')';
    } else {
      $sqlWhereStr .= ' AND '.$isReadSqlWhere[0];
    }
  }

  return $sqlWhereStr;
}

function getAllMemberAccount()
{
  $list = [];

  $sql = <<<SQL
  SELECT account
  FROM root_member
  WHERE therole != 'R'
  AND therole != 'T'
  AND status = '1';
SQL;

  $r = runSQLall($sql);

  if (empty($r[0])) {
    return false;
  }

  unset($r[0]);

  foreach ($r as $v) {
    $list[] = $v->account;
  }

  return $list;
}

function getAllMemberCount()
{
  $sql = <<<SQL
  SELECT COUNT(account)
  FROM root_member
  WHERE status = '1'
  AND therole != 'R'
  AND therole != 'T';
SQL;

  $r = runSQLall($sql);

  if (empty($r[0])) {
    return false;
  }

  unset($r[0]);

  return $r[1]->count;
}

function getAddGroupSentSql($data)
{
  $sql = <<<SQL
    INSERT INTO root_cs_groupmail
    (
      msgfrom, subject, message, mailcode, template_mail
    ) VALUES (
      '{$data['msgfrom']}', '{$data['subject']}', '{$data['message']}', '{$data['mailcode']}', '{$data['template_mail']}'
    );
SQL;

  return $sql;
}

function getSendPersonaMailSql($data, $table)
{
  $sql = <<<SQL
  INSERT INTO {$table}
  (
    msgfrom, msgto, subject, message, mailcode
  ) VALUES (
    '{$data['msgfrom']}', '{$data['msgto']}', '{$data['subject']}', '{$data['message']}', '{$data['mailcode']}'
  );
SQL;

  return $sql;
}

function getSendGroupMailSql($data, $table)
{
  if (isset($data['template'])) {
    $sql = <<<SQL
    INSERT INTO {$table}
    (
      msgto, subject, message, mailcode, template
    ) VALUES (
      '{$data['msgto']}', '{$data['subject']}', '{$data['message']}', '{$data['mailcode']}', '{$data['template']}'
    );
SQL;
  } else {
    $sql = <<<SQL
    INSERT INTO {$table}
    (
      msgto, subject, message, mailcode
    ) VALUES (
      '{$data['msgto']}', '{$data['subject']}', '{$data['message']}', '{$data['mailcode']}'
    );
SQL;
  }

  return $sql;
}

function getSendMailSql($data, $table)
{
  if (isset($data['template'])) {
    $sql = <<<SQL
    INSERT INTO {$table}
    (
      msgto, subject, message, mailcode, template
    ) VALUES (
      '{$data['msgto']}', '{$data['subject']}', '{$data['message']}', '{$data['mailcode']}', '{$data['template']}'
    );
SQL;
  } else {
    $sql = <<<SQL
    INSERT INTO {$table}
    (
      msgto, subject, message, mailcode
    ) VALUES (
      '{$data['msgto']}', '{$data['subject']}', '{$data['message']}', '{$data['mailcode']}'
    );
SQL;
  }

  return $sql;
}
