<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 會員等級管理-新增會員等級
// File Name:	member_import_action.php
// Author:		Neil
// Related:   服務 member_import.php
// DB Table:  root_member_grade
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) . "/lib_proccessing.php";

require_once dirname(__FILE__) . "/lib_file.php";

// 检查是否有权限操作
if(!(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']))) {
        echo '<script>alert("您无此操作权限!");history.go(-1);</script>';die();
}

// $tr['Illegal test'] = '(x)不合法的測試。';
if(isset($_GET['a'])) {
  $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
//  var_dump($_GET);
} else {
  die($tr['Illegal test']);
}
// var_dump($_SESSION);
// var_dump($_POST);
// var_dump($_GET);

/**
 * function
 */

 // 自動偵測編碼
 function ws_mb_detect_encoding($string, $enc = null, $ret = null)
 {

     static $enclist = array(

         'UTF-8', 'GBK', 'GB2312', 'GB18030',

     );
     $result = false;
     foreach ($enclist as $item) {
         //$sample = iconv($item, $item, $string);
         $sample = mb_convert_encoding($string, $item, $item);
         if (md5($sample) == md5($string)) {
             if ($ret === null) {$result = $item;} else { $result = true;}
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
 // -------------------------------------------------------------------------
 // datatable server process 分頁處理及驗證參數
 // -------------------------------------------------------------------------
 // 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
 if(isset($_GET['length']) AND $_GET['length'] != NULL ) {
   $current_per_size = filter_var($_GET['length'],FILTER_VALIDATE_INT);
 }else{
   $current_per_size = $page_config['datatables_pagelength'];
   //$current_per_size = 10;
 }

 // 起始頁面, 搭配 current_per_size 決定起始點位置
 if(isset($_GET['start']) AND $_GET['start'] != NULL ) {
   $current_page_no = filter_var($_GET['start'],FILTER_VALIDATE_INT);
 }else{
   $current_page_no = 0;
 }

 // datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
 if(isset($_GET['_'])){
   $secho = $_GET['_'];
 }else{
   $secho = '1';
 }
 // -------------------------------------------------------------------------
 // datatable server process 分頁處理及驗證參數  END
 // -------------------------------------------------------------------------



if($action == 'member_import') {
    $not_import = [];
    $tmp_file_path_final = csv_upload('member_import');
    // var_dump($tmp_file_path_final);

    if (($handle = fopen($tmp_file_path_final, "r")) == false) {
        http_response_code(406);
        echo json_encode([
            'message' => 'Failed to open uploaded file!',
        ]);

        // delete_upload_xls_tempfile($tmp_file_path_final);
        die();
    }

    $row_count = 1;

    $pdo_object = get_pdo_object();
    $pdo_object->beginTransaction();

    while (($data = fgetcsv($handle)) !== false) {

        $data = array_map('convert_encoding', $data);

        if ($row_count == 1) {
            $row_count++;
            continue;
        }
        if (!preg_match('/^[0-9a-zA-Z]{3,12}$/i', $data[0])){
            $not_import[] = $data[0].'&nbsp帐号不符合格式，请修正后再上传';
            $row_count++;
            continue;
        }
        
        $import_exist = runSQL("SELECT account FROM import_member WHERE account='$data[0]'");
        if ($import_exist){
          $not_import[] = $data[0].'&nbsp暂存区已存在相同帐号，请修正后再上传';
          $row_count++;
          continue;
        
        }

        $member_exist = runSQL("SELECT account FROM root_member WHERE account='$data[0]'");
        if ($member_exist){
          $not_import[] = $data[0].'&nbsp帐号已存在，请修正后再上传';
          $row_count++;
          continue;
        
        }


        $agent_exist = runSQL("SELECT account FROM root_member WHERE therole='A' AND account='$data[1]'");
        if (!$agent_exist) {
            $not_import[] = $data[0].'&nbsp代理商不存在，请修正后再上传';
            $row_count++;
            continue;
        }
        
        if (strtotime($data[3]) > strtotime(date("Y/m/d H:i:s"))){
          $not_import[] = $data[0].'&nbsp註册时间大于现在时间，请修正后再上传';
          $row_count++;
          continue;
        }

        // $data[0] = preg_replace('/([^A-Za-z0-9])/ui', '', $data[0] ?? '');
        $data[1] = preg_replace('/([^A-Za-z0-9])/ui', '', $data[1] ?? '');
        $data[2] = round(preg_replace('/([^0-9\s.])/ui', '', $data[2] ?? ''),'2');
        $data[3] = preg_replace('/([^A-Za-z0-9\p{Han}\s\-_@.:\/])/ui', '', $data[3] ?? '');
        $data[4] = preg_replace('/([^0-9])/ui', '', $data[4] ?? '');
        $data[5] = preg_replace('/([^A-Za-z0-9\p{Han}\s\-_@.])/ui', '', $data[5] ?? '');
        $data[6] = preg_replace('/([^A-Za-z0-9\p{Han}\s\-_@.])/ui', '', $data[6] ?? '');
        $data[7] = preg_replace('/([^A-Za-z0-9])/ui', '', $data[7] ?? '');

        switch(strtolower($data[5])){
          case '女':
          case 'woman':
          case 'female':
            $data[5] = '0';
            break;

          case '男':
          case 'man':
          case 'male':
            $data[5] = '1';
            break;

          default:
            $data[5] = '2';
            break;
        }

        $receivemoney_data = [
            ':account' => strtolower($data[0]) ?? '',
            ':agent' => strtolower($data[1]) ?? '',
            ':gtoken_balance' => $data[2] ?? '',
            ':enrollmentdate' => $data[3] ?? '',
            ':mobilenumber' => $data[4] ?? '',
            ':sex' => $data[5] ?? '',
            ':email' => $data[6] ?? '',
            ':wechat' => $data[7] ?? '',
        ];

        $sql = <<<SQL
      INSERT INTO import_member(account, agent, gtoken_balance, enrollmentdate, mobilenumber, sex, email, wechat)
        VALUES ( :account, :agent, :gtoken_balance, :enrollmentdate, :mobilenumber, :sex, :email, :wechat);
SQL;
        $member_check_result = $pdo_object->prepare("$sql");
        if (!$member_check_result->execute($receivemoney_data)) {
            // 請參考 postgresql error code 對應表 https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
            $debug_message = "runSQLall_prepared ERROR: ["
            . "\nerrorCode:" . $member_check_result->errorCode()
            . "\ninfo:" . $member_check_result->errorInfo()[2]
                . "\n]\n";

            error_log(date("Y-m-d H:i:s") . ' ' . $debug_message . ' SQL:' . $sql);
            $pdo_object->rollBack();

            http_response_code(406);
            echo json_encode([
                'message' => "第{$row_count}行錯誤: {$validate_result['message']}",
                'row' => $row_count,
                'row_data' => $data,
            ]);

            delete_upload_xls_tempfile($tmp_file_path_final);
            die();
        }
        $row_count++;
    }

    $pdo_object->commit();
    fclose($handle);

    $not_import_result = (count($not_import) > 0) ? ',但以下帐号，因不符合帐号规则，故不汇入！<br> '.implode('<br>',$not_import) : '';
    // if(count($not_import) > 0) http_response_code(406);

    echo json_encode([
        'message' => '会员资料已完成汇入暂存区'.$not_import_result,
        'total_row' => $row_count,
    ]);

    delete_upload_xls_tempfile($tmp_file_path_final);
}elseif($action == 'import_confirm') {
    $duplic_account = [];

    // 取得所有要匯入的帳戶資料
    $import_data = runSQLall('SELECT * FROM import_member;');

    // 取得所有要匯入的帳戶資料
    $bigagentid = runSQLall('SELECT id FROM root_member;')['1']->id;
    // var_dump($import_data);

    if($import_data['0'] <= '0') {
      http_response_code(406);
      echo json_encode([
          'message' => '（500）汇入失败，资料库读取错误，请洽客服人员！！'
      ]);
      die();
    }
    array_shift($import_data);

    $pdo_object = get_pdo_object();

    $sql = <<<SQL
    WITH ins AS
      (INSERT INTO root_member(account, enrollmentdate, mobilenumber, sex, email, wechat, parent_id, changetime, allow_login_passwordchg, grade, favorablerule, commissionrule )
        VALUES ( :account, :enrollmentdate, :mobilenumber, :sex, :email, :wechat, :agentid, now(), '2', :grade, :favorablerule, :commissionrule) RETURNING id)
    INSERT INTO root_member_wallets (id, changetime, gcash_balance, gtoken_balance)
    SELECT id, 'now()', '0', :gtoken_balance FROM ins;
SQL;

  $member_check_result = $pdo_object->prepare("$sql");

    $pdo_object->beginTransaction();

    // 匯入資料至root_member及root_member_wallets
    foreach($import_data AS $k => $d ) {

        $data = (array)$d;

        $confirm_root_member_data = [
            ':account' => $data['account'] ?? '',
            ':enrollmentdate' => $data['enrollmentdate'] ?? '',
            ':mobilenumber' => $data['mobilenumber'] ?? '',
            ':sex' => $data['sex'] ?? '',
            ':email' => $data['email'] ?? '',
            ':wechat' => $data['wechat'] ?? '',
            ':agentid' => $bigagentid,
            ':gtoken_balance' => $data['gtoken_balance'] ?? '',
            ':grade' => '1' ?? '',
            ':favorablerule' => '預設反水設定' ?? '',
            ':commissionrule' => '預設佣金設定' ?? '',
        ];

        try{
          $member_check_result->execute($confirm_root_member_data);

          // if(!$member_check_result) $pdo_object->rollBack();

        } catch (PDOException $e){
          // $pdo_object->rollBack();
          // var_dump($e);
          // if($e->errorInfo['0'] == '23505'){
            // 重覆帳號不進行新增，留下後通知頁面
            $duplic_account[] = $data['account'];
          // }else{
          //   // 請參考 postgresql error code 對應表 https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
          //   $debug_message = "DB ERROR: ["
          //   . "\nerrorCode:" . $e->errorInfo['0']
          //   . "\ninfo:" . $e->errorInfo['2']
          //       . "\n]\n";
          //   // echo $debug_message;
          //
          //   error_log(date("Y-m-d H:i:s") . ' ' . $debug_message . ' SQL:' . $sql);
          //
          //   http_response_code(406);
          //   echo json_encode([
          //       'message' => "（501）资料库存取错误，请洽客服人员！！".$debug_message,
          //   ]);
          //   die();
          // }
        }
    }

    $pdo_object->commit();

    // 更新代理的身份
    $update_agent = <<< SQL
    UPDATE root_member SET therole = 'A'
    WHERE account IN (
       SELECT DISTINCT(agent) FROM import_member
     ) AND allow_login_passwordchg = '2';
SQL;
    $import_data = runSQLall($update_agent);

    // 更新 parent_id
    $update_agent = <<< SQL
    UPDATE root_member rm SET parent_id = agentids.id, allow_login_passwordchg = '1', changetime = now()
    FROM (SELECT id,agent,im.account FROM import_member im INNER JOIN root_member rrm on rrm.account=im.agent) AS agentids
    WHERE rm.account = agentids.account AND allow_login_passwordchg = '2';
SQL;
    $import_data = runSQLall($update_agent);

    // 清空 import_member 的資料
    $clear_import_data = runSQL('DELETE FROM import_member;');

    $duplic_account_result = (count($duplic_account) > 0) ? ',但以下帐号因已有相同帐号存在，故不汇入！<br> '.implode('<br>',$duplic_account) : '';

    echo json_encode([
        'message' => '会员资料汇入已完成'.$duplic_account_result,
    ]);
}elseif($action == 'clear_import') {
    // 移除所有匯入的帳戶資料
    $clear_import_data = runSQL('DELETE FROM import_member;');

    echo json_encode([
        'message' => '移除未汇入资料完成',
    ]);

}elseif($action == 'query_log'){
  $show_listrow_array = [];
  $userlist_sql_tmp = 'SELECT * FROM import_member';
  $new_import_member_result = runSQL($userlist_sql_tmp.';');
  if($new_import_member_result > 0){

    // -----------------------------------------------------------------------
    // 分頁處理機制
    // -----------------------------------------------------------------------
    // 所有紀錄數量
    $page['all_records']     = $new_import_member_result;
    // 每頁顯示多少
    $page['per_size']        = $current_per_size;
    // 目前所在頁數
    $page['no']              = $current_page_no;
    // var_dump($page);

    // 處理 datatables 傳來的排序需求
    if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
      if($_GET['order'][0]['dir'] == 'asc'){ $sql_order_dir = 'ASC';
      }else{ $sql_order_dir = 'DESC';}
      if($_GET['order'][0]['column'] == 0){ $sql_order = 'ORDER BY account '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 1){ $sql_order = 'ORDER BY enrollmentdate '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 2){ $sql_order = 'ORDER BY mobilenumber '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 3){ $sql_order = 'ORDER BY sex '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 4){ $sql_order = 'ORDER BY email '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 5){ $sql_order = 'ORDER BY wechat '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 6){ $sql_order = 'ORDER BY agent '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 7){ $sql_order = 'ORDER BY gtoken_balance '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 8){ $sql_order = 'ORDER BY status '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 9){ $sql_order = 'ORDER BY therole '.$sql_order_dir;
      }else{ $sql_order = 'ORDER BY account ASC';}
    }else{ $sql_order = 'ORDER BY account ASC';}
    // 取出 root_member 資料
    $userlist_sql   = $userlist_sql_tmp." ".$sql_order." OFFSET ".$page['no']." LIMIT ".$page['per_size']." ;";
    // var_dump($userlist_sql);
    $userlist = runSQLall($userlist_sql);

    // 存放列表的 html -- 表格 row -- tables DATA
    $show_listrow_html = '';
    // 判斷 root_member count 數量大於 1
    if($userlist[0] >= 1) {
      // 以會員為主要 key 依序列出每個會員的貢獻金額
      for($i = 1 ; $i <= $userlist[0]; $i++){
  			$b = (array)$userlist[$i];
  			// get data member id


        // 顯示的表格資料內容
        $show_listrow_array[] = array(
          'account'=>$b['account'],
          'therole'=>$b['therole'],
          'agent'=>$b['agent'],
          'enrollmentdate'=>$b['enrollmentdate'],
          'mobilenumber'=>$b['mobilenumber'],
          'sex'=>$b['sex'],
          'email'=>$b['email'],
          'wechat'=>$b['wechat'],
          'gtoken_balance'=>$b['gtoken_balance']);
      }
    }
  }

  // 輸出給datatable
  $output = array(
      "draw" => $secho,
      "recordsTotal" => $page['per_size'] ?? 0,
      "recordsFiltered" => $page['all_records'] ?? 0,
      "data" => $show_listrow_array
    );
  // end member sql
  echo json_encode($output);
} elseif($action == 'import_template') {
  // 清除快取以防亂碼
  ob_end_clean();

    $csv_key_title = [$tr['Account'],$tr['Agent Account'],$tr['Balance'],$tr['admission time'],$tr['Cell Phone'],$tr['Gender'],$tr['Email'],$tr['WeChat Number']];
    // $csv_key_title = ['帐号','代理帐号','余额','註册时间','手机','性别','email','微信'];

    // -------------------------------------------
    // 將內容輸出到 檔案 , csv format
    // -------------------------------------------
    $file_name='poit'. date("YmdHis") . '.csv';
    $file_path = dirname(__FILE__) . '/tmp_dl/memberimport'. date("YmdHis") . '.csv';
    $csv_stream = new CSVWriter($file_path);
    // $csv_stream = new CSVStream($filename);
    $csv_stream->begin();
    // 將資料輸出到檔案 -- Title
    $csv_stream->writeRow($csv_key_title);
    $csv_stream->writeRow(['testuser1','agent1','10.1','2018/09/17 02:18:08','15753087161','男','example@qq.com','exampleweixin',]);
    $csv_stream->writeRow(['testuser2','testuser1','100.1','2018/09/17 05:18:08','15829547787','女','example01@qq.com','exampleweixin01',]);
    /**csvtoexcel***/
    $excel_stream=new csvtoexcel($file_name,$file_path);
    $excel_stream->begin();

    // var_dump($excel_stream);
    // var_dump($file_path);
    // die();

    return;

} elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);

}else{
}
