<?php
// ----------------------------------------------------------------------------
// Features:    ui後台， 動作的處理
// File Name:    uisetting_action.php
// Author:        orange
// Related:   uisetting.php
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

require_once dirname(__FILE__) . "/lib_cdnupload.php";

//有登入且為superuser通行
if (!isset($_SESSION['agent']) || !in_array($_SESSION['agent']->account, $su['superuser'])) {
    header('Location:./home.php');
    die();
}

// ----------------------------------
// 本程式使用的 function
// ----------------------------------
function data_filter(&$input){
    foreach ($input as $key => $value) {
        if(is_array($input[$key])){
            data_filter($input[$key]);
        }else{
            $input[$key] = urlencode(strip_tags($value, "<a><p><span><strong><em><u><img><table><tbody><tr><td><th><thead>"));
            if(in_array($key, ['switch','closeable'],true)){
                $input[$key] =(int)$input[$key];
            }
        }
    }
}
function json_ouput_filter(&$input){
    foreach ($input as $key => $value) {
        if(is_array($input[$key])){
            json_ouput_filter($input[$key]);
        }else{
            $input[$key] = str_replace(array("\r", "\n", "\r\n", "\n\r","\t"), '',urldecode($value));
            if(in_array($key, ['switch','closeable'],true))
                $input[$key] =(int)$input[$key];
        }
    }
}
//取得行為呼叫
if (isset($_GET['act']) and isset($_GET['cid'])) {
    $action = $_GET['act'];
    $cid    = $_GET['cid'];
    $cdn    = $_GET['cdn'] ?? null;
} else {
    die("ERROR");
}
//取得資料
if ($action == 'get') {
    $site_stylesetting_sql    = "SELECT jsondata FROM site_stylesetting WHERE id = " . $cid . ";";
    $site_stylesetting_result = runSQLall($site_stylesetting_sql);
    $output = json_decode($site_stylesetting_result[1]->jsondata,true);
    json_ouput_filter($output);
    echo json_encode($output);
}
//覆蓋更新
if ($action == 'update') {
    $index = json_encode($_POST["index"], JSON_NUMERIC_CHECK);
    $index = trim($index, "[]");
    if (isset($_POST["data"])) {
        $temp = $_POST["data"];
        data_filter($temp);
        $data = json_encode($temp);

    } else {
        $data = "[]";
    }

    $sql = "UPDATE site_stylesetting
			SET jsondata = jsonb_set( jsondata, '{" . $index . "}','" . $data . "' )
			WHERE id=" . $cid . ";";
    $site_result = runSQLall($sql);
    // print_r($temp);
    echo "保存成功！";
}
//局部更新
if ($action == 'trigger') {
    $index     = json_encode($_POST["index"], JSON_NUMERIC_CHECK);
    $index     = trim($index, "[]");
    $sql_index = str_replace(",", "->", $index);
    $sql_index = str_replace('"', "'", $sql_index);

    if (isset($_POST["data"])) {
        $temp = $_POST["data"];
        data_filter($temp);

        $data = json_encode($temp);
    } else {
        $data = "[]";
    }
    $sql = "UPDATE site_stylesetting
			SET jsondata = jsonb_set( jsondata, '{" . $index . "}', jsondata -> " . $sql_index . "||'" . $data . "' )
			WHERE id=" . $cid . ";";
    $site_result = runSQLall($sql);
    // print_r($temp);
    echo "修改成功！";
}
//刪除指定
if ($action == 'delete') {
    $index = json_encode($_POST["index"], JSON_NUMERIC_CHECK);
    if ($cdn == 1) {
        $delindex                  = trim($index, "[]");
        $delindex                  = str_replace(",", "->", $delindex);
        $delindex                  = str_replace('"', "'", $delindex);
        $select_del_img_sql        = "SELECT jsondata->" . $delindex . "->>'img' as url FROM site_stylesetting WHERE id = " . $cid . ";";
        $select_del_img_sql_result = runSQLall($select_del_img_sql);
        $del_img_url               = $select_del_img_sql_result[1]->url;
        $del_res                   = DeleteCDNFile('upload/uisetting/', $del_img_url);
    }    
    $index = trim($index, "[]");
    $sql   = "UPDATE site_stylesetting
			SET jsondata = jsondata #- '{" . $index . "}'
			WHERE id=" . $cid . ";";
    $site_result = runSQLall($sql);
    //print_r($sql);
    //var_dump($del_res);
    echo "删除成功！";
}

if ($action == 'update_copy') {
    $index     = json_encode($_POST["index"], JSON_NUMERIC_CHECK);
    $index     = trim($index, "[]");
    $sql_index = str_replace(",", "->", $index);
    $sql_index = str_replace('"', "'", $sql_index);
    if (isset($_POST["data"])) {
        foreach ($_POST["data"] as $key => $value) {
            $data[$key] = urlencode(strip_tags($value, "<a><p><span><strong><em><u><img><table><tbody><tr><td><th><thead>"));
        }
        $data = json_encode($data, JSON_NUMERIC_CHECK);
    } else {
        $data = "[]";
    }

    $sql = "UPDATE site_stylesetting
            SET jsondata = jsonb_set( jsondata, '{" . $index . "}', jsondata -> " . $sql_index . "||'" . $data . "' )
            WHERE id=" . $cid . ";";
    $site_result = runSQLall($sql);
    //print_r($sql);
    echo "修改成功！";

}
//局部更新
if ($action == 'trigger_back') {
    $index     = json_encode($_POST["index"], JSON_NUMERIC_CHECK);
    $index     = trim($index, "[]");
    $sql_index = str_replace(",", "->", $index);
    $sql_index = str_replace('"', "'", $sql_index);
    if (isset($_POST["data"])) {
        foreach ($_POST["data"] as $key => $value) {
            if (is_string($_POST["data"][$key])) {
                $data[$key] = strip_tags($value, "<a><p><span><strong><em><u><img><table><tbody><tr><td><th><thead>");
            } else {
                $data[$key] = $value;
            }
            //$data[$key] = urlencode($data[$key]);
        }
        $data = (isset($data)) ? json_encode($data, JSON_NUMERIC_CHECK) : json_encode($_POST["data"], JSON_NUMERIC_CHECK);
    } else {
        $data = "[]";
    }

    $sql = "UPDATE site_stylesetting
            SET jsondata = jsonb_set( jsondata, '{" . $index . "}', jsondata -> " . $sql_index . "||'" . $data . "' )
            WHERE id=" . $cid . ";";
    $site_result = runSQLall($sql);
    //print_r($sql);
    echo "修改成功！";
}