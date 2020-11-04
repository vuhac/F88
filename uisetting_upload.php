<?php
// ----------------------------------------------------------------------------
// Features:  後台 -- ui設定-上傳功能function
// File Name:
// Author:     orange
// Related: uisetting
// DB Table:
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
//
require_once dirname(__FILE__) . "/lib_cdnupload.php";

// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

//有登入且為superuser通行
if (!isset($_SESSION['agent']) || !in_array($_SESSION['agent']->account, $su['superuser'])) {
    header('Location:./home.php');
    die();
}

// ----------------------------------
// 本程式使用的 function
// ----------------------------------

//取得行為呼叫
if (isset($_GET['cid'])) {
    $cid    = $_GET['cid'];
    $action = (isset($_GET['a'])) ? $_GET['a'] : null;
} else {
    die("ERROR");
}
//var_dump($_GET);
//var_dump($_FILES);
//var_dump($_POST);
$post_data = $_POST;

if (isset($post_data['img']) && $post_data['img'] == 'undefined') {
    unset($post_data['img']);
}

if (isset($post_data['img']) && filter_var($post_data['img'], FILTER_VALIDATE_URL) == true) {
    $path_parts = pathinfo($post_data['img']);
    if (preg_match('~' . $config['cdn_login']['url'] . '~', $path_parts['dirname'])) {
        $logger = $tr['Image URL is illegal'];
        echo '<script>alert("'.$logger.'");</script>';
        die();
    }
}

if ($action == 'crs' && !isset($_FILES['img']) && !isset($post_data['img'])) {
    $logger = $tr['Picture please do not blank'];
    echo '<script>alert("'.$logger.'");</script>';
    die();
}

foreach ($post_data as $key => $value) {
    $post_data[$key] = strip_tags($value, "<a><p><span><strong><em><u><img><table><tbody><tr><td><th><thead>");
}

//刪除舊有檔案
function DelImg()
{
    global $post_data;
    global $cid;

    $delindex                  = trim($post_data['index'], "[]");
    $delindex                  = str_replace(",", "->", $delindex);
    $delindex                  = str_replace('"', "'", $delindex);
    $select_del_img_sql        = "SELECT jsondata->" . $delindex . "->>'img' as url FROM site_stylesetting WHERE id = " . $cid . ";";
    $select_del_img_sql_result = runSQLall($select_del_img_sql);
    $del_img_url               = $select_del_img_sql_result[1]->url;
    $del_res                   = DeleteCDNFile('upload/uisetting/', $del_img_url);
    if ($del_res['res'] != 1) {
        //echo '<script>alert("删除旧档失败");</script>';
        //die();
    }
}
if (isset($post_data['img']) && $action == 'cpn') {
    DelImg();
}

if (isset($_FILES['img'])) {
    $cdn = new CDNConnection($_FILES['img']);
    if ($cdn->CheckFile(array('jpg', 'png', 'bmp')) != true) {
        echo '<script>alert("'.$tr['upload failed Format error'].'jpg,png,bmp");</script>';
        die();
    }
    //上傳檔案
    $res = $cdn->UploatFile('upload/uisetting/');
    if ($res['res'] != 1) {
        echo '<script>alert("' . $up_file['name'] .$tr['upload failed'] .'");</script>';
        die();
    }
    //刪除舊有檔案
    if ($action == 'cpn') {
        DelImg();
    }

    $post_data['img'] = $res['url'];
}

if (isset($post_data['img']) && filter_var($post_data['img'], FILTER_VALIDATE_URL) == false) {
    echo '<script>alert("'.$tr['enter image url'].$tr['Data is error'].'");</script>';
    die();
}

$index     = $post_data['index'];
$index     = trim($index, "[]");
$sql_index = str_replace(",", "->", $index);
$sql_index = str_replace('"', "'", $sql_index);

unset($post_data['index']);
$data = json_encode($post_data);

$sql = "UPDATE site_stylesetting
        SET jsondata = jsonb_set( jsondata, '{" . $index . "}', jsondata -> " . $sql_index . "||'" . $data . "' )
        WHERE id=" . $cid . ";";
$site_result = runSQLall($sql);
//var_dump($index);
//var_dump($data);
//var_dump($sql);
echo '<script>alert("'.$tr['Change successful'].'!");</script>';
