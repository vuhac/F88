<?php
// ----------------------------------------
// Features:    後台 -- 系統設定檔
// File Name:    config.php
// Author:        barkley
// Related:   system_config.php 配合 project 專用的設定
// Log:
// -----------------------------------------------------------------------------
// 算累每個程式累計花費時間 start , 統計設定在 foot 的函式
$program_start_time =  microtime(true);
// -----------------------------------------------------------------------------

// 陣列宣告
$pdo = [];
$redisdb = [];
$config = [];
$tr = [];
$betlogpdo=[];

// 排程類程序用變數
$config['PHPCLI'] = '/usr/bin/php';

// -----------------------------------------------------------------------------
// 切換系統模式及資料庫
// -----------------------------------------------------------------------------
// VERSION.txt 檔案內容放置 release version or developer , 依據此變數自動判斷目前所在開發環境
$version_url = dirname(__FILE__).'/version.txt';
if(file_exists($version_url)) {
    $system_mode = strtolower(trim(file_get_contents($version_url)));
    // $system_mode = 'developer';
    // $system_mode = 'release version';

    // 開發者的環境設定檔
    if($system_mode == 'developer') {
        // postgresql DB infomation
        $pdo['db']                    = "pgsql";
        $pdo['host']                = "10.22.114.110";
        $pdo['host4write']    = "10.22.114.110";
        $pdo['dbname']            = "dev";
        $pdo['user']                = "lamort";
        $pdo['password']        = "t9052108";

        // 注單用DB設定
        $betlogpdo['db']                = "pgsql";
        $betlogpdo['host']            = "10.22.112.8";
        $betlogpdo['user']            = "gpk"; // same as schema
        $betlogpdo['password']    = "www@gpk17";

        // redis server DB information
        // redis server DB use information 每次登入最長的 timeout 時間, 系統預設 timeout 設定在 redisdb 的 timeout.
        // php session 改成預設寫入寫在 redisdb db 0 上面
        // redisdb DB 1 為後台的 login 資訊
        // redisdb DB 2 為前台的 login 資訊
        $redisdb['host']        = '10.22.114.104';
        $redisdb['auth']        = '123456';
        $redisdb['db']            = 1;
        $redisdb['db_front']     = 2;

        // 系統是否顯示debug資訊 ref:http://www.php.net/manual/en/function.error-reporting.php
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', 'ON');
        ini_set('display_startup_errors', 'ON');

        // mqtt config
        $config['mqtt_url'] = 'wss://message.shopeebuy.com:11883/';
        $config['mqtt_host'] = 'message.shopeebuy.com';
        $config['mqtt_port'] = 1883;
        $config['mqtt_username'] = 'mtchang';
        $config['mqtt_password'] = 'qw';
        $config['mqtt_channel_hash'] = false;
        $config['mqtt_message_reciever_host'] = 'http://dright.jutainet.com/message/public';
        $config['mqtt_channel_hash_salt'] = 'hhhee';

        $config['rebbit_host'] = '10.22.115.40';
		$config['rebbit_port'] = '5672';
		$config['rebbit_vhost'] = 'demo_mq';
		$config['rebbit_user'] = 'demo';
        $config['rebbit_password'] = 'demo@jtn@2019';

        $config['rebbit_js_url'] = 'wss://rabbitmq.jutainet.com:53533/ws';
        $config['rebbit_js_vhost'] = 'demo_mq';
        $config['rebbit_js_user'] = 'java';
        $config['rebbit_js_password'] = '12345';

        // GPK2 API 代理參數
        $config['gpk2_url'] = 'http://gapi.apighub.com/';
        $config['gpk2_apikey'] = '0c6626eee994cb11f96d2db685cba311';
        $config['gpk2_token'] = 'fe96dd032a4c25733bb06df1eecb4f9b3e5f3b3cab22f275cf1ad4d6fcb67c2668f6d022d59cb2f6bc85a495e44ee27a5b9de51b3def30735a910516ea5e7a79';

        // 金流 API 代理參數
        $config['gpk2_pay'] = [
            'apiHost' => 'https://demo.shopeebuy.com',
            'apiKey' => '05638f481550f98085f0f91e1edcad0c',
            'apiToken' => '645e056ab315d45a0875860e43b50321e2e8a453a836eb23e831771ba4a0de7fdd223f8ced36233d59bd93b037fd78bce3e5b6aef06514f1af52e072129db7e0',
        ];
    // -----------------------------------------------------------------------------
    }else{

        // postgresql DB infomation
        $pdo['db']                    = "pgsql";
        $pdo['host']                = "10.22.114.110";
        $pdo['host4write']    = "10.22.114.110";
        $pdo['dbname']            = "testgpk";
        $pdo['user']                = "gpk";
        $pdo['password']        = "a0926033571";

        // 注單用DB設定
        $betlogpdo['db']                = "pgsql";
        $betlogpdo['host']            = "10.22.112.8";
        $betlogpdo['user']            = "gpk"; // same as schema
        $betlogpdo['password']    = "www@gpk17";

        // redis server DB information
        // redis server DB use information 每次登入最長的 timeout 時間, 系統預設 timeout 設定在 redisdb 的 timeout.
        // php session 改成預設寫入寫在 redisdb db 0 上面
        // redisdb DB 1 為後台的 login 資訊
        // redisdb DB 2 為前台的 login 資訊
        $redisdb['host']        = '10.22.114.104';
        $redisdb['auth']        = '123456';
        $redisdb['db']            = 1;

        // 系統是否顯示debug資訊 ref:http://www.php.net/manual/en/function.error-reporting.php
        ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_STRICT);
        ini_set('display_errors', 'Off');
        ini_set('display_startup_errors', 'Off');

        // mqtt config
        $config['mqtt_url'] = 'wss://message.shopeebuy.com:11883/';
        $config['mqtt_host'] = 'message.shopeebuy.com';
        $config['mqtt_port'] = 1883;
        $config['mqtt_username'] = 'mtchang';
        $config['mqtt_password'] = 'qw';
        $config['mqtt_channel_hash'] = true;
        $config['mqtt_message_reciever_host'] = 'http://message.shopeebuy.com';
        $config['mqtt_channel_hash_salt'] = 'hhhee';

        $config['rebbit_host'] = '10.22.115.40';
		$config['rebbit_port'] = '5672';
		$config['rebbit_vhost'] = 'demo_mq';
		$config['rebbit_user'] = 'demo';
        $config['rebbit_password'] = 'demo@jtn@2019';

        $config['rebbit_js_url'] = 'wss://rabbitmq.jutainet.com:53533/ws';
		$config['rebbit_js_vhost'] = 'demo_mq';
		$config['rebbit_js_user'] = 'java';
		$config['rebbit_js_password'] = '12345';

        // GPK2 API 代理參數
        $config['gpk2_url'] = 'http://gapi.apighub.com/';
        $config['gpk2_apikey'] = 'ed72d97659db0dba54cab42621c60c3f';
        $config['gpk2_token'] = 'efd696f88282129f20b146e80fafebbca5795a7748880624ead3e74a13418e30166d7b4e6945a32a01fb6eb643d2d1393cda366303bbae732083a420d8ced4ec';

        // 金流 API 代理參數
        $config['gpk2_pay'] = [
            'apiHost' => 'https://demo.shopeebuy.com',
            'apiKey' => '823eabc9c17e5c2f29935ac208c820e6',
            'apiToken' => '038535ec7e3d39262beb1d1e00070d785a9ce7f475071da6945370d4496de36908d67390bbd40685b2f6fd5c25e07ecef5ef517aa9c8392790b6e59f76e59d30',
        ];
    // -----------------------------------------------------------------------------
    }
}else{
    // 沒有設定 STOP
    die('system mode set error.');
}


// 前後台通用設定檔
require_once dirname(__FILE__) ."/lib_common.php";

// -----------------------------------------------------------------------------
$config['hostname'] = 'DEMO';
$config['footer']   = 'CopyRight @2020 GPK';
// 此前台網站的 web root URL
$config['website_domainname']    = 'bedev.gpk17.com';
//$config['website_domainname']    = $_SERVER["HTTP_HOST"];
//$hosturl['webroot'] = 'http://'.$_SERVER["HTTP_HOST"];

// 預設站台主語系
$config['default_lang'] = 'zh-CN';
// 預設站台地區(包含站台使用幣別)
$config['default_locate'] = 'zh_CN';
// 預設站台時區
$config['default_timezone'] = 'America/St_Thomas';

// -----------------------------------------------------
// CDN infomation
// 靜態樣板或是 JS 等靜態檔案，使用 CDN 加速，但是為了區隔專案的不同，把 CDN 設定為不同專案的目錄。
// -----------------------------------------------------
// 需要修改變換保護 cdn , 避免 http://cdn.baidu-cdn-hk.com/ 這個網址直接暴露, 被 GFW 封鎖
// 這段配合修改 nginx 設定檔，加入 proxy_pass 設定 , 當網址進入 nginx 後，會轉換成為實際的網址，但在外觀上看不出來實際的 cdn 位置。
// location /CdnRedirect {proxy_pass http://cdn.baidu-cdn-hk.com/; }
$config['cdn_baseurl']    = './';
$config['website_project_type']    = 'ui';

// 網站的型態：'ecshop' => 商城，'casino' => 娛樂城
// 需與前台的參數相同
$config['website_type'] = 'casino';

// CDN 檔案路徑設定
// 網站樣版的 THEME 路徑設定
$webtheme['themepath'] = 'gp02';
$cdnfullurl = $config['cdn_baseurl'].$config['website_project_type'].'/'.$webtheme['themepath'].'/';
$cdnrooturl = $config['cdn_baseurl'].$config['website_project_type'].'/';

// 網站的遊戲圖示路徑設定
$webtheme['gameiconpath'] = 'gamesicon';
$cdn4gamesicon = $config['cdn_baseurl'].$config['website_project_type'].'/'.$webtheme['gameiconpath'].'/';

// -----------------------------------------------------
// 專案代碼 , 每個網站都有一個獨一無二的專案代碼, 建立 casion 帳號時，以這個專案代碼當開頭。三碼為限。
// -----------------------------------------------------
// 建立帳號時以 prijectid + 流水號為主 , 對應到娛樂城的代碼, 才可以分辨不同的網站. kt1 為測試站台代碼
$config['projectid']                 = 'kt1';

// IG 總代理 hashcode, 與站台專案代碼為 1-1 關係
$config['ig_hashCode'] = 'tgpk2aa01_067e0a53-df18-4105-9b07-ad';

// -----------------------------------------------------
// 娛樂城轉帳設定   0: 測試環境，不進行轉帳    1: 正式環境，會正常進行轉帳作業
// -----------------------------------------------------
$config['casino_transfer_mode'] = '0';
//$config['casino_transfer_mode'] = '1';

// -----------------------------------------------------
// 金流設定   test: 測試金流    release: 正式金流
// -----------------------------------------------------
$config['payment_mode'] = 'test';
// $config['payment_mode'] = 'release';
// -----------------------------------------------------
// 業務測試站台開關參數
// -----------------------------------------------------
// 業務測試站台開關 businessDemo  ( 0 / 1 )
$config['businessDemo'] = 0;
// 業務測試站台例外娛樂城 businessDemo_skipCasino  (array)
$config['businessDemo_skipCasino'] = array();

// -------------------------------------------------------------------------
// 前台 + 後台 -- 每日統計報表用的變數
// 相關檔案名稱： statistics_daily_report_lib.php
// -------------------------------------------------------------------------
// 資料庫變數，紀錄投注紀錄專用的資料庫
// $stats_config['mg_bettingrecords_tables'] = 'gpk_mg_bettingrecords';
// 因為需要開發測試, 所以使用模擬的資料庫來做大量的資料,
// MG 投注紀錄表
$stats_config['mg_bettingrecords_tables'] = $config['projectid'].'_mg_bettingrecords';
// PT 投注紀錄表
$stats_config['pt_bettingrecords_tables'] = $config['projectid'].'_pt_bettingrecords';
// MEGA 投注紀錄表
$stats_config['mega_bettingrecords_tables'] = $config['projectid'].'_mega_bettingrecords';
// EC 投注紀錄表
$stats_config['ec_bettingrecords_tables'] = $config['projectid'].'_ec_bettingrecords';
// IG SST 投注紀錄表
$stats_config['igsst_bettingrecords_tables'] = $config['projectid'].'_igsst_bettingrecords';
// IG HKT 投注紀錄表
$stats_config['ighkt_bettingrecords_tables'] = $config['projectid'].'_ighkt_bettingrecords';

// 此設定配合到 config_betlog.php 檔案內的 DB 選擇, 配合 developer 及 release 模式測試使用.

// -------------------------------------------------------------------------
// 維運客服及站長帳號設定，用來做權限區分用
// $su['ops'] 維運客服帳號
// $su['master'] 維運客服帳號
// $su['superuser'] 所有特權帳號
// -------------------------------------------------------------------------
$su['ops'] = array("root","jigcs8");
$su['master'] = array("jigcs");
$su['superuser'] = array_merge($su['ops'],$su['master']);

/**
 * 訊息記錄層級：
 * debug：記錄所有系統的訊息，不分錯誤等級
 * warning：記錄到 warning 層級的訊息
 * error：只記錄出現錯誤的 error 層級的訊息
 *
 * $config['log_level'] 站台內會員操作記錄
 * $config['casino_transferlog_level'] 會員對娛樂城操作記錄
 */
$config['log_level'] = 'debug';
$config['casino_transferlog_level'] = 'debug';

// -------------------------------------------------------------------------
// 供CDN sftp 使用之帳密 與 (前台)CDN 網址
// -------------------------------------------------------------------------
$config['cdn_login'] =[
    'host'     => '103.82.131.226',
    'username' => 'cdntest',
    'password' => 'qwqw5678',
    'port'     => 33222,
    'base_path' => 'site/', //根目錄
    'url'        => 'https://cdn.playgt.com/', //cdnurl
];

// ip region
// 顯示IP所在區域
$config['ip_region_url'] = 'http://10.22.114.103/receive_ip_data.php';

// 銀行入款審核、現金取款、遊戲幣取款
// 來源帳號提款密碼 or 管理員登入的密碼
$config['withdrawal_pwd'] = 'tran5566';

// 人工現金轉代幣
$config['pwd_verify_sha1'] = '5566bypass';


// -----------------------------------------------------
// 使用錢幣預設的顯示
// -----------------------------------------------------
setlocale(LC_MONETARY, $config['default_locate']);
$config['currency_sign'] = localeconv()['int_curr_symbol'];

// 獨立變數檔案, 負責所以有前台的獨立變數。
require_once dirname(__FILE__) ."/system_config.php";
// -----------------------------------------------------------------------------
// END
