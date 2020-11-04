<?php
// ----------------------------------------------------------------------------
// Features:    後台-- 系統彩金發放管理 -- 動作
// File Name:    receivemoney_management_action.php
// Author:        Barkley Fix by Ian
// Related:   對應 receivemoney_management.php、receivemoney_management_detail.php
//            DB root_receivemoney
// Log:
// 2017.7.21 update
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

require_once dirname(__FILE__) . "/lib_proccessing.php";

require_once dirname(__FILE__) . "/lib_file.php";

// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// ----------------------------------
// 本程式使用的 function
// ----------------------------------

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

function validate_CSV_row_data(array &$data)
{
    $r = [
        'pass' => true,
        'message' => '',
    ];

    // check account existence
    $sql = <<<SQL
  SELECT id
    FROM root_member
    WHERE root_member.account = :member_account;
SQL;
    $member_check_result = runSQLall_prepared($sql, [':member_account' => $data['account']]);

    if (!isset($member_check_result[0])) {
        $r['pass'] = false;
        $r['message'] = '帐号不存在';
        return $r;
    }

    
    //validate gcash && gtoken can't be imported when both of them are > 0 at the same time
    if( $data['gcash'] >= 0 && $data['gtoken'] >= 0){
        if( $data['gcash'] > 0 && $data['gtoken'] > 0){
            $r['pass'] = false;
            $r['message'] = '请勿同时发放游戏币与现金,如有需要,请使用各别档案汇入'; 
            return $r;
        }
    }else{
        $r['pass'] = false;
        $r['message'] = '游戏币与现金请勿设置为负数'; 
        return $r;
    }
    
    
    // validate dailydate format
    try {
        $datetime = new \DateTime($data['receivedeadline']);
        $data['receivedeadline'] = $datetime->format('Y-m-d h:i:s');
    } catch (\Exception $e) {
        $r['pass'] = false;
        $r['message'] = '时间格式错误';
        return $r;
    }

    //validate data can't be imported when deadline < now time
    if( strtotime($data['receivedeadline']) <  strtotime(date('Y-m-d h:i:s')) ){
        $r['pass'] = false;
        $r['message'] = '奖金失效时间请勿设置为已过期时间';
        return $r;
    }

    // validate data format
    $filter_args = [
        'gcash' => ['filter' => FILTER_VALIDATE_FLOAT],
        'gtoken' => ['filter' => FILTER_VALIDATE_FLOAT],
        'status' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 0, 'max_range' => 2]],
        'auditmode' => ['filter' => FILTER_VALIDATE_REGEXP, 'options' => ['regexp' => '/(freeaudit|depositaudit|shippingaudit)$/']],
        'auditmodeamount' => ['filter' => FILTER_VALIDATE_FLOAT],
    ];
    $filter_result = filter_var_array($data, $filter_args);

    // print_r($filter_result);
    // die();

    if ($filter_result['gcash'] === false) {
        $r['pass'] = false;
        $r['message'] = '金额格式错误';
        return $r;
    } elseif ($filter_result['gtoken'] === false) {
        $r['pass'] = false;
        $r['message'] = '金额格式错误';
        return $r;
    } elseif ($filter_result['status'] === false) {
        $r['pass'] = false;
        $r['message'] = '彩金状态格式错误';
        return $r;
    } elseif ($filter_result['auditmode'] === false) {
        $r['pass'] = false;
        $r['message'] = '稽核方式错误';
        return $r;
    } elseif ($filter_result['auditmodeamount'] === false) {
        $r['pass'] = false;
        $r['message'] = '金额格式错误';
        return $r;
    }

    return $r;
}

function create_receivemoney($pdo_object, array $data)
{
    global $tr;

    $sql = <<<SQL
  INSERT INTO root_receivemoney (
    status, gcash_balance, gtoken_balance, receivedeadlinetime, auditmode, auditmodeamount,
    summary, system_note, member_account, prizecategories, givemoneytime, member_id, updatetime, transaction_category,
    givemoney_member_account, last_modify_member_account, reconciliation_reference
  ) SELECT
    :status, :gcash, :gtoken, :receivedeadline, :auditmode, :auditmodeamount,
    :summary, :note, :account, :prizecategories, :now_datetime, root_member.id, now(), :transaction_category,
    -- :summary, :note, :account, :prizecategories, now(), root_member.id, now(), :transaction_category,
    :givemoney_member_account, :last_modify_member_account, :reconciliation_reference
  FROM root_member
  WHERE root_member.account = :member_account;
SQL;

    $sth = $pdo_object->prepare("$sql");

    if (!$sth->execute([
        'status' => $data['status'],
        'gcash' => $data['gcash'],
        'gtoken' => $data['gtoken'],
        'receivedeadline' => $data['receivedeadline'] . '-04',
        'auditmode' => $data['auditmode'],
        'auditmodeamount' => $data['auditmodeamount'],
        'summary' => $data['summary'],
        'note' => $data['note'],
        'account' => $data['account'],
        'member_account' => $data['account'],
        'prizecategories' => $data['prizecategories'],
        'transaction_category' => 'tokenfavorable',
        'givemoney_member_account' => $_SESSION['agent']->account,
        'last_modify_member_account' => $_SESSION['agent']->account,
        'reconciliation_reference' => $tr['Reconciliation information'] . '對帳資訊',
        'now_datetime'=>$data['now_datetime'],
    ])) {
        // 請參考 postgresql error code 對應表 https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
        $debug_message = "runSQLall_prepared ERROR: ["
        . "\nerrorCode:" . $sth->errorCode()
        . "\ninfo:" . $sth->errorInfo()[2]
            . "\n]\n";

        error_log(date("Y-m-d H:i:s") . ' ' . $debug_message . ' SQL:' . $sql);
        $db_dump_result_all = false;
        echo "$debug_message";

        $pdo_object->rollBack();
        die();
    }

    $msg         = $_SESSION['agent']->account . ' 執行彩金匯入。帳號：' . $data['account'] . '。gcash：'.$data['gcash'].'元。gtoken：'.$data['gtoken'].'元。領取截止日期：'.$data['receivedeadline'].'。發放時間：'.$data['now_datetime'].'。獎金類別：'.$data['prizecategories'].'。摘要：'.$data['summary'].'。'; //客服
    // 操作人員的 web http remote ip
    if(isset($_SERVER["REMOTE_ADDR"])) {
        $agent_ip = $_SERVER["REMOTE_ADDR"];
    }else{
        // $agent_ip = 'no_remote_addr';
        $agent_ip = '0.0.0.0';
    }
    // 操作人員使用的 browser 指紋碼, 有可能會沒有指紋碼. JS close 的時候會發生
    if(isset($_SESSION['fingertracker'])) {
        $fingertracker = $_SESSION['fingertracker'];
    }else{
        $fingertracker = 'no_fingerprinting';
    }
    // 執行的程式檔名 - client
    if(isset($_SERVER['SCRIPT_NAME'])){
        $script_name = filter_var($_SERVER['SCRIPT_NAME'], FILTER_SANITIZE_MAGIC_QUOTES);
    }else{
        $script_name = 'no_script_name';
    }
    // 瀏覽器資訊 - client
    if(isset($_SERVER['HTTP_USER_AGENT'])) {
        $http_user_agent = filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_MAGIC_QUOTES);
    }else{
        $http_user_agent = 'no_http_user_agent';
    }
    // 使用者的 cookie 資訊
    if(isset($_SERVER['HTTP_COOKIE'])) {
        $http_cookie = filter_var($_SERVER['HTTP_COOKIE'], FILTER_SANITIZE_MAGIC_QUOTES);
    }else{
        $http_cookie = 'no_cookie';
    }
    // 使用 $_GET 的傳入網址
    if(isset($_SERVER['QUERY_STRING'])) {
        $query_string = filter_var($_SERVER['QUERY_STRING'], FILTER_SANITIZE_MAGIC_QUOTES);
    }else{
        $query_string = 'no_query_string';
    }

  
    $log_sql = <<<SQL
            INSERT INTO root_memberlog 
            (who,service, message,message_level ,agent_ip, fingerprinting_id,script_name,http_user_agent, http_cookie, query_string,target_users,message_log,site,sub_service) 
            values(
            :who,'marketing',:message,'notice',:agent_ip,:fingerprinting_id,:script_name,:http_user_agent,:http_cookie,:query_string,:target_users,
            :message_log,'b','payout');
SQL;

    $log_sth = $pdo_object->prepare("$log_sql");
    // var_dump($log_sth);die();
    if (!$log_sth->execute([
        'who' => $_SESSION['agent']->account,
        'message' => $msg,
        'agent_ip' => $agent_ip,
        'fingerprinting_id' => $fingertracker,
        'script_name' => $script_name,
        'http_user_agent' => $http_user_agent,
        'http_cookie' => $http_cookie,
        'query_string' => $query_string,
        'target_users' => $data['account'],
        'message_log' => $msg,
    ])) {
        // 請參考 postgresql error code 對應表 https://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
        $debug_message = "runSQLall_prepared ERROR: ["
        . "\nerrorCode:" . $log_sth->errorCode()
        . "\ninfo:" . $log_sth->errorInfo()[2]
            . "\n]\n";

        error_log(date("Y-m-d H:i:s") . ' ' . $debug_message . ' SQL:' . $log_sql);
        $db_dump_result_all = false;
        echo "$debug_message";

        $pdo_object->rollBack();
        die();
    }


    // 匯入完成，則寫入memberlog
    // $msg         = $_SESSION['agent']->account . ' 執行彩金匯入。帳號："' . $data['account'] . '"，gcash：'.$data['gcash'].'，gtoken：'.$data['gcash'].'，領取截止日期：'.$data['receivedeadline'].'，發放時間：'.$data['now_datetime'].'，獎金類別：'.$data['prizecategories'].'對帳資訊：'.$tr['Reconciliation information'].'摘要：'.$data['summary']; //客服
    // $msg_log     = $msg; //RD
    // $sub_service = "payout";
    // memberlogtodb($_SESSION['agent']->account, "marketing", "notice", $msg, $data['account'], "$msg_log", "b", $sub_service);
}

// ----------------------------------
// 本程式使用的 function END
// ----------------------------------

// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------
// 取得頁面傳來的操作指令 $tr['Illegal test'] = '(x)不合法的測試。';
if (isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
} else {
    die($tr['Illegal test']);
}

if ($action == 'get_csv_template' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
  // 清除快取以防亂碼
  ob_end_clean();

    $csv_key_title = [
        $tr['Recipient account'].' ',
        $tr['Franchise'].' (请勿同时发放游戏币与现金,如有需要,请各别发放) ',
        $tr['Gtoken'].' (请勿同时发放游戏币与现金,如有需要,请各别发放) ',
        '彩金状态 (0=取消，1=可领取，2=暂停)',
        $tr['Bonus Time to lose efficacy(US East Time)'],
        '稽核方式 (freeaudit=免稽核，depositaudit=存款稽核，shippingaudit=优惠稽核)',
        '稽核金额 (小数点两位)',
        $tr['Bonus summary'],
        $tr['Bonus category'],
        // iconv('UTF-8', 'ISO-2022-CN', $tr['Recipient account']),
        // iconv('UTF-8', 'ISO-2022-CN', $tr['Franchise']),
        // iconv('UTF-8', 'ISO-2022-CN', $tr['Gtoken']),
        // iconv('UTF-8', 'ISO-2022-CN', '彩金状态 (0=取消,1=可领取,2=暂停)'),
        // iconv('UTF-8', 'ISO-2022-CN', $tr['Bonus Time to lose efficacy(US East Time)']),
        // iconv('UTF-8', 'ISO-2022-CN', '稽核方式 (freeaudit=免稽核,depositaudit=存款稽核,shippingaudit=优惠稽核)'),
        // iconv('UTF-8', 'ISO-2022-CN', '稽核金额 (小数点两位)'),
        // iconv('UTF-8', 'ISO-2022-CN', $tr['Bonus summary']),
        // iconv('UTF-8', 'ISO-2022-CN', $tr['Bonus category']),
    ];

    // -------------------------------------------
    // 將內容輸出到 檔案 , csv format
    // -------------------------------------------
    $file_name='poit'. date("YmdHis") . '.csv';
    $file_path = dirname(__FILE__) . '/tmp_dl/poit'. date("YmdHis") . '.csv';
    $csv_stream = new CSVWriter($file_path);
    // $csv_stream = new CSVStream($filename);
    $csv_stream->begin();
    // 將資料輸出到檔案 -- Title
    $csv_stream->writeRow($csv_key_title);
    $csv_stream->writeRow(['范例', '0.00', '20.00', '1', '2019-12-31 12:00:00', 'freeaudit', '0.00', '范例', 'import-example',]);
    /**csvtoexcel***/
    $excel_stream=new csvtoexcel($file_name,$file_path);
    $excel_stream->begin();

    // var_dump($excel_stream);
    // var_dump($file_path);
    // die();

    return;

} elseif ($action == 'upload_csv' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {

    header('Content-Type: application/json');

    $valid_exts = ['xlsx','xls']; // valid extensions
    $max_size = 30000 * 1024; // max file size in bytes

    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        http_response_code(406);
        echo json_encode([
            'message' => 'Bad request!',
        ]);
        return;
    }

    if (!isset($_FILES['csv'])) {
        http_response_code(406);
        echo json_encode([
            'message' => 'No file in form!',
        ]);
        return;
    }

    if (!is_uploaded_file($_FILES['csv']['tmp_name'])) {
        http_response_code(406);
        echo json_encode([
            'message' => 'Upload Fail: File not uploaded!',
            'file_error' => $_FILES['csv']['error'],
            'data' => $_FILES,
        ]);
        return;
    }

    // get uploaded file extension
    $ext = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));

    // looking for format and size validity
    if (!in_array($ext, $valid_exts) and $_FILES['csv']['size'] < $max_size) {
        http_response_code(406);
        echo json_encode([
            'message' => 'Upload Fail: Unsupported file format or It is too large to upload!',
        ]);
        return;
    }

    $tmp_file_path = $_FILES['csv']['tmp_name'];

    // remove BOM
    // $content = file_get_contents($tmp_file_path);
    // file_put_contents($tmp_file_path, str_replace("\xEF\xBB\xBF",'', $content));
    $file_name='uppot'. date("YmdHis");
    $destination_file = dirname(__FILE__) . '/tmp_dl/'. $file_name . '.csv';
    $tmp_file_path_final=exceltocsv($tmp_file_path,$destination_file,$ext);

    // return var_dump($tmp_file_path_final);die();



    if (($handle = fopen($tmp_file_path_final, "r")) == false) {
        http_response_code(406);
        echo json_encode([
            'message' => 'Failed to open uploaded file!',
        ]);

        delete_upload_xls_tempfile($tmp_file_path_final);
        return;
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

        $prizecategories = preg_replace('/([^A-Za-z0-9\p{Han}\s\-_@.])/ui', '', $data[8] ?? '');
        
        //彩金發放時間，不要有毫微秒 
        $now_datetime = gmdate("Y-m-d H:i:s", time()+8*3600) . '+08:00';
        // var_dump($now_datetime);die();
        $receivemoney_data = [
            'account' => $data[0] ?? '',
            'gcash' => $data[1] ?? '',
            'gtoken' => $data[2] ?? '',
            'status' => $data[3] ?? '',
            'receivedeadline' => $data[4] ?? '',
            'auditmode' => $data[5] ?? '',
            'auditmodeamount' => $data[6] ?? '',
            'summary' => $data[7] ?? '',
            'note' => '',
            'prizecategories' => $prizecategories,
            'now_datetime'=>$now_datetime,
        ];

        if (empty(implode($receivemoney_data, ''))) {
            $row_count++;
            continue;
        }

        $validate_result = validate_CSV_row_data($receivemoney_data);

        if (!$validate_result['pass']) {
            $pdo_object->rollBack();

            http_response_code(406);
            echo json_encode([
                'message' => "第{$row_count}行錯誤: {$validate_result['message']}",
                'row' => $row_count,
                'row_data' => $data,
            ]);

            delete_upload_xls_tempfile($tmp_file_path_final);
            return;
        }
        
        create_receivemoney($pdo_object, $receivemoney_data);
        $row_count++;
    }

    $pdo_object->commit();
    fclose($handle);

    echo json_encode([
        'message' => '汇入完成',
        'total_row' => $row_count,
    ]);


    delete_upload_xls_tempfile($tmp_file_path_final);

} else {
    echo '(x)不合法的測試';
}
