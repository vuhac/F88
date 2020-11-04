<?php
// ----------------------------------------------------------------------------
// Features:	寄件動作處理
// File Name:	sendmail_action.php
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

require_once dirname(__FILE__) . "/lib_file.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

if (isset($_GET['a'])) {
  $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
} else {
  die($tr['Illegal test']);
}

// 自動偵測編碼
function ws_mb_detect_encoding($string, $enc = null, $ret = null)
{

  static $enclist = ['UTF-8', 'GBK', 'GB2312', 'GB18030'];

  $result = false;
  foreach ($enclist as $item) {
    //$sample = iconv($item, $item, $string);
    $sample = mb_convert_encoding($string, $item, $item);
    if (md5($sample) == md5($string)) {
        if ($ret === null) {
          $result = $item;
        } else {
          $result = true;
        }
        break;
    }
  }
  return $result;
}

function convert_encoding($content)
{
    $encoding = ws_mb_detect_encoding($content);
    $content = mb_convert_encoding($content, 'UTF-8', $encoding);

    return $content;
}

if ($action == 'csvTemplate' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
  // 清除快取以防亂碼
  ob_end_clean();

  $csv_key_title = [
    '会员帐号',
    '优惠码',
    '活动网址',
    '彩金',
    '(栏位五)',
    '(栏位六)',
    '(栏位七)',
    '(栏位八)',
    '(栏位九)',
    '(栏位十)',
    '(栏位十一)'
  ];

  // -------------------------------------------
  // 將內容輸出到 檔案 , csv format
  // -------------------------------------------
  $file_name='mail'. date("YmdHis") . '.csv';
  $file_path = dirname(__FILE__) . '/tmp_dl/mail'. date("YmdHis") . '.csv';
  $csv_stream = new CSVWriter($file_path);
  $csv_stream->begin();
  // 將資料輸出到檔案 -- Title
  $csv_stream->writeRow($csv_key_title);
  $csv_stream->writeRow(['范例', 'AAABBBCCCDDDEEE', 'https://aaa.com', '666', 'example', 'example', 'example', 'example', 'example', 'example', 'example']);
  /**csvtoexcel***/
  $excel_stream=new csvtoexcel($file_name,$file_path);
  $excel_stream->begin();

  delete_upload_xls_tempfile($file_path);
  return;

} elseif ($action == 'uploadCsv' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
  $colName = [];
  $colContent = [];

  $valid_exts = ['xlsx','xls']; // valid extensions
  $max_size = 30000 * 1024; // max file size in bytes

  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['status' => 'fail', 'result' => $tr['Bad request']]);
    return;
  }

  if (!isset($_FILES['csv'])) {
    echo json_encode(['status' => 'fail', 'result' => $tr['File not found']]);
    return;
  }

  if (!is_uploaded_file($_FILES['csv']['tmp_name'])) {
    echo json_encode([
      'status' => 'fail', 
      'result' => $tr['File not found'],
      'file_error' => $_FILES['csv']['error'],
      'data' => $_FILES
    ]);
    return;
  }

  // get uploaded file extension
  $ext = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));

  // looking for format and size validity
  if (!in_array($ext, $valid_exts) and $_FILES['csv']['size'] < $max_size) {
    echo json_encode(['status' => 'fail', 'result' => $tr['File uploading failed:Format error or the file is too large']]);
    return;
  }

  $tmp_file_path = $_FILES['csv']['tmp_name'];

  // remove BOM
  // $content = file_get_contents($tmp_file_path);
  // file_put_contents($tmp_file_path, str_replace("\xEF\xBB\xBF",'', $content));
  $file_name='groupmail'. date("YmdHis");
  $destination_file = dirname(__FILE__) . '/tmp_dl/'. $file_name . '.csv';
  $tmp_file_path_final = exceltocsv($tmp_file_path, $destination_file, $ext);

  if (($handle = fopen($tmp_file_path_final, "r")) == false) {
    echo json_encode(['status' => 'fail', 'result' => $tr['Failed to open uploaded file']]);

    delete_upload_xls_tempfile($tmp_file_path_final);
    return;
  }

  $row_count = 1;

  $pdo_object = get_pdo_object();
  $pdo_object->beginTransaction();

  $colCode = [
    '%S00', '%S01', '%S02', '%S03', '%S04', '%S05', '%S06', '%S07', '%S08', '%S09', '%S10'
  ];

  while (($data = fgetcsv($handle)) !== false) {
    $data = array_map('convert_encoding', $data);

    if ($row_count == 1) {
      $colName = [
        str_replace("\"", '', $data[0]) ?? '',
        str_replace("\"", '', $data[1]) ?? '',
        str_replace("\"", '', $data[2]) ?? '',
        str_replace("\"", '', $data[3]) ?? '',
        str_replace("\"", '', $data[4]) ?? '',
        str_replace("\"", '', $data[5]) ?? '',
        str_replace("\"", '', $data[6]) ?? '',
        str_replace("\"", '', $data[7]) ?? '',
        str_replace("\"", '', $data[8]) ?? '',
        str_replace("\"", '', $data[9]) ?? '',
        str_replace("\"", '', $data[10]) ?? ''
      ];

      $row_count++;
      continue;
    }

    $mail_data = [
      str_replace("\"", '', $data[0]) ?? '',
      str_replace("\"", '', $data[1]) ?? '',
      str_replace("\"", '', $data[2]) ?? '',
      str_replace("\"", '', $data[3]) ?? '',
      str_replace("\"", '', $data[4]) ?? '',
      str_replace("\"", '', $data[5]) ?? '',
      str_replace("\"", '', $data[6]) ?? '',
      str_replace("\"", '', $data[7]) ?? '',
      str_replace("\"", '', $data[8]) ?? '',
      str_replace("\"", '', $data[9]) ?? '',
      str_replace("\"", '', $data[10]) ?? ''
    ];

    if (empty(implode('', $mail_data))) {
      $row_count++;
      continue;
    }

    $colContent[] = $mail_data;

    $row_count++;
  }

  $cacheData = [
    'code' => $colCode,
    'colName' => $colName,
    'content' => $colContent
  ];

  $pdo_object->commit();
  fclose($handle);

  $memcache = new Memcached();

  $mailData = getMemcache($memcache, 'mail_'.$_SESSION['agent']->account);

  if ($mailData) {
    delMemcache($memcache, 'mail_'.$_SESSION['agent']->account);
  }

  setMemcache($memcache, 'mail_'.$_SESSION['agent']->account, $cacheData, 600);

  echo json_encode([
    'status' => 'success',
    'result' => $tr['import successfully'],
    'colName' => $colName,
    'count' => count($colContent)
  ]);

  delete_upload_xls_tempfile($tmp_file_path_final);
} elseif ($action == 'sendMail' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
  $memcache = new Memcached();
  $batchedSqlExecutor = new BatchedSqlExecutor(200);

  $message = preg_replace('/<.*script.*>/', '', $_POST['data']['message']);
  $message = preg_replace('/<.*iframe.*>/', '', $message);
  $message = trim(htmlspecialchars($message, ENT_QUOTES));

  $memberList = getAllMemberAccount();

  $subject = filter_var($_POST['data']['subject'], FILTER_SANITIZE_STRING);

  if ($subject == '') {
    echo json_encode(['status' => 'fail', 'result' => $tr['Subject is incorrect']]);
    die();
  }

  switch ($_POST['data']['recipient']) {
    case 'member':
      $table = 'root_stationmail';
      $mailcode = 'persona'.date('YmdHis').$_SESSION['agent']->salt;

      $members = explode(',', $_POST['data']['sendTo']);

      if (count($members) > 1) {
        $table = 'root_member_groupmail';
        $mailcode = 'group'.date('YmdHis').$_SESSION['agent']->salt;
        $batchedSqlExecutor->push(
          getAddGroupSentSql([
            'msgfrom' => $stationmail['sendto_system_cs'],
            'subject' => $subject,
            'message' => $message,
            'mailcode' => sha1($mailcode),
            'template_mail' => 0
          ])
        );
      }

      foreach ($members as $acc) {
        $account = filter_var($acc, FILTER_SANITIZE_STRING);

        if ($account == '') {
          echo json_encode(['status' => 'fail', 'result' => $tr['Account is incorrect']]);
          die();
        }

        if (!in_array($account, $memberList)) {
          echo json_encode(['status' => 'fail', 'result' => $tr['Account'].': '.$account.' '.$tr['is nonexistent or error']]);
          die();
        }

        if ($table == 'root_stationmail') {
          $sql = getSendPersonaMailSql([
            'msgto' => $account,
            'msgfrom' => $stationmail['sendto_system_cs'],
            'subject' => $subject,
            'message' => $message,
            'mailcode' => sha1($mailcode)
          ], $table);
        } else {
          $sql = getSendGroupMailSql([
            'msgto' => $account,
            'msgfrom' => $stationmail['sendto_system_cs'],
            'subject' => $subject,
            'message' => $message,
            'mailcode' => sha1($mailcode)
          ], $table);
        }

        $batchedSqlExecutor->push($sql);
      }

      $batchedSqlExecutor->execute();
      echo json_encode(['status' => 'success', 'result' => $tr['Mail sent successfully']]);
      break;
    case 'allMember':
      $mailcode = 'group'.date('YmdHis').$_SESSION['agent']->salt;

      if (!$memberList) {
        echo json_encode(['status' => 'fail', 'result' => $tr['Account of member you inquired is error']]);
        die();
      }

      $batchedSqlExecutor->push(
        getAddGroupSentSql([
          'msgfrom' => $stationmail['sendto_system_cs'],
          'subject' => $subject,
          'message' => $message,
          'mailcode' => sha1($mailcode),
          'template_mail' => 0
        ])
      );

      foreach ($memberList as $v) {
        $batchedSqlExecutor->push(
          getSendMailSql([
            'msgto' => $v,
            'msgfrom' => $stationmail['sendto_system_cs'],
            'subject' => $subject,
            'message' => $message,
            'mailcode' => sha1($mailcode)
          ], 'root_member_groupmail')
        );
      }

      $batchedSqlExecutor->execute();

      echo json_encode(['status' => 'success', 'result' => $tr['Mail sent successfully']]);
      break;
    case 'uploadCsv':
      $isAddCsMailSql = false;
      $table = 'root_stationmail';
      $mailcode = 'persona'.date('YmdHis').$_SESSION['agent']->salt;

      $mailData = getMemcache($memcache, 'mail_'.$_SESSION['agent']->account);

      if (!$mailData) {
        echo json_encode(['status' => 'fail', 'result' => $tr['Data is nonexistent please try again']]);
        die();
      }

      if (count($mailData['content']) > 1) {
        $table = 'root_member_groupmail';
        $mailcode = 'group'.date('YmdHis').$_SESSION['agent']->salt;
      }

      $sendMail = [
        'total' => count($mailData['content']),
        // 'success' => 0,
        'fail' => 0
      ];

      foreach ($mailData['content'] as $k => $v) {
        if (!in_array($v[0], $memberList)) {
          // echo json_encode(['status' => 'fail', 'result' => $tr['Account'].': '.$v[0].' '.$tr['is nonexistent or error']]);
          // die();
          $sendMail['fail']++;
          continue;
        }

        if (!$isAddCsMailSql) {
          $batchedSqlExecutor->push(
            getAddGroupSentSql([
              'msgfrom' => $stationmail['sendto_system_cs'],
              'subject' => $subject,
              'message' => $message,
              'mailcode' => sha1($mailcode),
              'template_mail' => 1
            ])
          );

          $isAddCsMailSql = true;
        }

        $mailTemplate = [
          'code' => $mailData['code'],
          'colName' => $mailData['colName'],
          'content' => $v
        ];

        if ($table == 'root_stationmail') {
          $sql = getSendPersonaMailSql([
            'msgto' => $v[0],
            'msgfrom' => $stationmail['sendto_system_cs'],
            'subject' => $subject,
            'message' => $message,
            'mailcode' => sha1($mailcode),
            'template' => json_encode($mailTemplate)
          ], $table);
        } else {
          $sql = getSendGroupMailSql([
            'msgto' => $v[0],
            'msgfrom' => $stationmail['sendto_system_cs'],
            'subject' => $subject,
            'message' => $message,
            'mailcode' => sha1($mailcode),
            'template' => json_encode($mailTemplate)
          ], $table);
        }

        $batchedSqlExecutor->push($sql);
      }

      $batchedSqlExecutor->execute();

      delMemcache($memcache, 'mail_'.$_SESSION['agent']->account);
      echo json_encode(['status' => 'success', 'result' => $tr['Mail sent successfully'].'('.floor($sendMail['total'] - $sendMail['fail']).' / '.$sendMail['total'].')']);
      break;
    default:
      echo json_encode(['status' => 'fail', 'result' => $tr['Bad request']]);
      break;
  }

} elseif ($action == 'preview' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
  $memcache = new Memcached();
  $mailData = getMemcache($memcache, 'mail_'.$_SESSION['agent']->account);

  if (!$mailData) {
    echo json_encode(['status' => 'fail', 'result' => $tr['Your data is expired please try again']]);
    die();
  }

  $subject = $_POST['data']['subject'];
  $message = $_POST['data']['message'];
  $code = $mailData['code'];
  $content = $mailData['content'][0];

  $result = [
    'subject' => str_replace($code, $content, $subject),
    'message' => str_replace($code, $content, $message)
  ];

  echo json_encode(['status' => 'success', 'result' => $result]);
} elseif ($action == 'allMemberCount' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
  $count = getAllMemberCount();

  if (!$count) {
    echo json_encode(['status' => 'fail', 'result' => $tr['Membership you Inquired is error']]);
    die();
  }

  echo json_encode(['status' => 'success', 'result' => $count]);
} elseif ($action == 'downloadMailDetail' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
  // 清除快取以防亂碼
  ob_end_clean();

  $mailcode = filter_var($_GET['code'], FILTER_SANITIZE_STRING);

  if ($mailcode == '') {
    // echo '<script>alert("錯誤的信件代碼");window.location.replace("./mail.php");</script>';
    echo '<script>alert('.$tr['Wrong mail code'].');</script>';
    die();
  }

  $sql = <<<SQL
  SELECT COUNT(id)
  FROM root_member_groupmail
  WHERE mailcode = '{$mailcode}';
SQL;

  $dataCount = runSQLall($sql);

  if (empty($dataCount[0])) {
    // echo '<script>alert("查無信件資料");window.location.replace("./mail.php");</script>';
    echo '<script>alert("'.$tr['no mail was found'].'");</script>';
    die();
  }

  $num = ceil($dataCount[1]->count / 50000);

  $offset = 0;

  for ($i = 0; $i <= $num; $i++) {
    $offset += ($i * 50000);
    
    $templateSql = <<<SQL
    SELECT template
    FROM root_member_groupmail
    WHERE mailcode = '{$mailcode}'
    LIMIT 50000
    OFFSET {$offset};
SQL;

    $result = runSQLall($templateSql);

    if (empty($result[0])) {
      // echo '<script>alert("查無信件資料");window.location.replace("./mail.php");</script>';
      echo '<script>alert("'.$tr['no mail was found'].'");</script>';
      die();
    }

    unset($result[0]);

    $file_name='mail'. date("YmdHis") . '.csv';
    $file_path = dirname(__FILE__) . '/tmp_dl/mail'. date("YmdHis") . '.csv';
    $csv_stream = new CSVWriter($file_path);
    $csv_stream->begin();

    foreach ($result as $k => $v) {
      $mailTemplate = json_decode($v->template);

      if ($k == 1) {
        $csv_stream->writeRow($mailTemplate->colName);
      }

      $csv_stream->writeRow($mailTemplate->content);
    }

    $excel_stream=new csvtoexcel($file_name,$file_path);
    $excel_stream->begin();

    delete_upload_xls_tempfile($file_path);
  }
}