<?php
require_once dirname(__FILE__) . "/config.php";

if (isset($_SERVER['HTTP_USER_AGENT']) or isset($_SERVER['SERVER_NAME'])) {
  die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}

if (isset($argv[1]) && $argv[1] == 'run') {

  $monthsAgo = (isset($argv[2]) && ($argv[2] > 0 && $argv[2] <=3)) ? $argv[2] : 1;
  // $monthsAgo = $argv[2];

} else {
  echo "Command: run [months_ago]\n";
  echo "Example: run [1|2|3]\n";
  echo "months_ago 單位為月，預設1個月，需大於0小於等於3\n";
  die();
}

$sql = <<<SQL
DELETE 
FROM root_cs_groupmail
WHERE sendtime < (now() - interval '{$monthsAgo} month');
SQL;

$result = runSQL($sql);

if (!$result) {
  echo $monthsAgo." 個月前目前無群組信件資料需刪除\n";
  die();
}

echo "已刪除 ".$monthsAgo." 個月前群組信件資料\n";