<?php
// ----------------------------------------------------------------------------
// Features:    後台-- 優惠編輯器的動作處理
// File Name:    promotional_editor_action.php
// Author:
// Related:
// DB Table:
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

require_once dirname(__FILE__) . "/lib_promotional_management.php";
require_once dirname(__FILE__) . "/lib_cdnupload.php";

//$tr['Illegal test'] = '(x)不合法的測試。';
if (isset($_GET['a']) and $_SESSION['agent']->therole == 'R') {
    $action = $_GET['a'];
} else {
    die($tr['Illegal test']);
}
//var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);
//var_dump($_FILES);


if ($action == 'edit_offer' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $editor_data_remove_jstag     = preg_replace('/<.*script.*>/', '', $_POST['editor_data']);
    $editor_data_remove_iframetag = preg_replace('/<.*iframe.*>/', '', $editor_data_remove_jstag);
    $editor_data_encode           = trim(htmlspecialchars($editor_data_remove_iframetag, ENT_QUOTES));

    $promotional = [
        'id'                => filter_var($_POST['offer_id'], FILTER_SANITIZE_NUMBER_INT),
        'processingaccount' => $_SESSION['agent']->account,
        'name'              => filter_var($_POST['offer_name'], FILTER_SANITIZE_STRING),
        'classification'    => filter_var($_POST['offer_classification'], FILTER_SANITIZE_STRING),
        'bannerurl_effect'  => filter_var($_POST['offer_start_img'], FILTER_SANITIZE_STRING),
        'bannerurl_end'     => filter_var($_POST['offer_end_img'], FILTER_SANITIZE_STRING),
        // 'seq' => filter_var($_POST['offer_order'], FILTER_SANITIZE_STRING),
        'effecttime'        => filter_var($_POST['start_day'], FILTER_SANITIZE_STRING),
        'endtime'           => filter_var($_POST['end_day'], FILTER_SANITIZE_STRING),
        'status'            => filter_var($_POST['offer_isopen'], FILTER_SANITIZE_NUMBER_INT),
        'desktop_show'      => filter_var($_POST['offer_pc_isshow'], FILTER_SANITIZE_NUMBER_INT),
        'mobile_show'       => filter_var($_POST['offer_mobile_isshow'], FILTER_SANITIZE_NUMBER_INT),
        'content'           => $editor_data_encode,
        'show_promotion_activity' => filter_var($_POST['select_activity'],FILTER_SANITIZE_STRING),
        'classification_status' => 1
    ];
    // var_dump($promotional);die();

    $promotional_img                     = array();
    $promotional_img['bannerurl_effect'] = (isset($_POST['upload_offer_start_img'])) ? $_POST['upload_offer_start_img'] : null;
    $promotional_img['bannerurl_end']    = (isset($_POST['upload_offer_end_img'])) ? $_POST['upload_offer_end_img'] : null;

// $today = gmdate('Y/m/d',time() + $_SESSION['agent']->timezone * 3600);
    $today = gmdate('Y/m/d', time()+'-4' * 3600) . ' 00:00';
    $year  = date('Y/m/d', strtotime("$today + 3 month"));

    if ($promotional['effecttime'] != '') {
        $promotional['effecttime'] = $promotional['effecttime'];
    }

    $promotional['endtime'] = ($promotional['endtime'] == '') ? $year : $promotional['endtime'];
// if ($promotional['endtime'] == '') {
    //   $promotional['endtime'] = $promotional['endtime'].$year; // 結束時間 預設10年
    // } else {
    //   // 結束時間 按照使用者選的日期
    //   $promotional['endtime'] = $promotional['endtime'];
    // }

/*
if ($promotional['endtime'] != '') {
$promotional['endtime'] = $promotional['endtime']; // .' 23:59:59'
}
 */
    $domain_id    = $_POST['domain_id'] ?? '';
    $subdomain_id = $_POST['subdomain_id'] ?? '';

    $domain = get_desktop_mobile_domain($domain_id, $subdomain_id);

    if (!$domain['status']) {
        echo '<script>alert("' . $domain['result'] . '");</script>';
        die();
    }

    $promotional['desktop_domain'] = $domain['result']['desktop'];
    $promotional['mobile_domain']  = $domain['result']['mobile'];

// if ($promotional['name'] == '' || $promotional['bannerurl_effect'] == '' || $promotional['bannerurl_end'] == '' || $promotional['seq'] == '' || $promotional['content']  == '') {
    if ($promotional['name'] == '' || $promotional['content'] == '' || $promotional['classification'] == '') {
        //$tr['Please confirm promotion name, start picture, end picture, sort and preferential content are correctly'] = '請確認優惠名稱、開始圖片、結束圖片、排序及優惠內容是否正確填入。';
        $logger = $tr['Please confirm promotion name, start picture, end picture, sort and preferential content are correctly'];
        echo '<script>alert("' . $logger . '");</script>';
        die();
    }

    if (empty($promotional['bannerurl_effect']) && empty($promotional_img['bannerurl_effect']) && !isset($_FILES['upload_offer_start_img'])) {
        $logger = $tr['Please confirm promotion name, start picture, end picture, sort and preferential content are correctly'];
        echo '<script>alert("' . $logger . '");</script>';
        die();
    }
    if (empty($promotional['bannerurl_end']) && empty($promotional_img['bannerurl_end']) && !isset($_FILES['upload_offer_end_img'])) {
        $logger = $tr['Please confirm promotion name, start picture, end picture, sort and preferential content are correctly'];
        echo '<script>alert("' . $logger . '");</script>';
        die();
    }

    if ($promotional['effecttime'] == '' || $promotional['endtime'] == '' || $promotional['effecttime'] > $promotional['endtime']) {
        //$tr['Start time can not be greater than the end time, please select the time again'] = '開始時間不可大於結束時間，請重新選擇時間。';
        $logger = $tr['Start time can not be greater than the end time, please select the time again'];
        echo '<script>alert("' . $logger . '");</script>';
        die();
    }


//確認文件檔案類型合法性
    if (isset($_FILES['upload_offer_start_img']) && isset($_FILES['upload_offer_end_img'])) {
        $filecheck = CheckFile($_FILES['upload_offer_start_img']) & CheckFile($_FILES['upload_offer_end_img']);
        if ($filecheck == false) {
            echo '<script>alert("'.$tr['upload failed Format error'].'jpg,png,bmp");</script>';
            die();
        }
    }

//確認圖片URL合法性
    if (!empty($promotional_img['bannerurl_effect'])) {
        $check['bannerurl_effect'] = filter_var($promotional_img['bannerurl_effect'], FILTER_VALIDATE_URL);
        $path_parts                = pathinfo($check['bannerurl_effect']);
        if (preg_match('~'.$config['cdn_login']['url'].'~', $path_parts['dirname'])) {
            echo '<script>alert("优惠开始图片网址不合法");</script>';
            die();
        }
        if ($check['bannerurl_effect'] == false) {
            echo '<script>alert("请检查优惠开始图片网址(请完整包含http(s)://");</script>';
            die();
        } else {
            $del_res                         = DeleteCDNFile('upload/promotions/',$promotional['bannerurl_effect']);
            $promotional['bannerurl_effect'] = filter_var($promotional_img['bannerurl_effect'], FILTER_VALIDATE_URL);
        }
    }

    if (!empty($promotional_img['bannerurl_end'])) {
        $check['bannerurl_end'] = filter_var($promotional_img['bannerurl_end'], FILTER_VALIDATE_URL);
        $path_parts             = pathinfo($check['bannerurl_end']);
        if (preg_match('~'.$config['cdn_login']['url'].'~', $path_parts['dirname'])) {
            echo '<script>alert("优惠结束图片网址不合法");</script>';
            die();
        }
        if ($check['bannerurl_end'] == false) {
            echo '<script>alert("请检查优惠结束图片网址(请完整包含http(s)://");</script>';
            die();
        } else {
            $del_res                      = DeleteCDNFile('upload/promotions/',$promotional['bannerurl_end']);
            $promotional['bannerurl_end'] = filter_var($promotional_img['bannerurl_end'], FILTER_VALIDATE_URL);
        }
    }

//若有上傳圖片則做上傳動作 並覆蓋bannerurl
    if (isset($_FILES['upload_offer_start_img'])) {
        $promotional['bannerurl_effect'] = cdnupload($_FILES['upload_offer_start_img'], $promotional['bannerurl_effect']);
    }
    if (isset($_FILES['upload_offer_end_img'])) {
        $promotional['bannerurl_end'] = cdnupload($_FILES['upload_offer_end_img'], $promotional['bannerurl_end']);
    }
//
    $promotional_data = ($promotional['id'] != '') ? get_designatedid_promotional($promotional['id']) : false;

    if (!$promotional_data['status']) {
        $classification = get_promotion_classification_bydomain($promotional['desktop_domain'], $promotional['mobile_domain']);

        $promotional['sort'] = '1';
        if ($classification['status']) {
            $class = [];
            foreach ($classification['result'] as $v) {
                $class[$v->sort] = $v->classification;
            }

            $promotional['sort'] = (in_array($promotional['classification'], $class)) ? array_search($promotional['classification'], $class) : (array_search(end($class), $class)) + 1;
        }

        //$tr['Promotion add successfully'] = '優惠新增成功';
        //$tr['Promotion add failed'] = '優惠新增失敗。';
        $sql_result   = insert_promotional($promotional);
        $success_text = $tr['Promotion add successfully'];
        $failed_text  = $tr['Promotion add failed'];
    } else {
        //$tr['Promotion updated successfully'] = '優惠更新成功。';
        //$tr['Promotion update failed'] = '優惠更新失敗。';
        $sql_result   = update_promotional($promotional);
        $success_text = $tr['Promotion updated successfully'];
        $failed_text  = $tr['Promotion update failed'];
    }

    if (!$sql_result) {
        echo '<script>alert("' . $failed_text . '");</script>';
        die();
    }

//var_dump($_POST);
//
    echo '<script>alert("' . $success_text . '");location.href="offer_management.php";</script>';
// echo '<script>alert("'.$success_text.'");</script>';
    // ----------------------------------------------------------------------------

}elseif($action == 'add_category'){
    // 新增分類 到 root_promotions_classification
    $domain_name = filter_var($_POST['domain_name'],FILTER_SANITIZE_STRING);
    $sub_name = filter_var($_POST['sub_name'],FILTER_SANITIZE_STRING);
    $add_time = filter_var($_POST['time'],FILTER_SANITIZE_STRING);
    $name = filter_var($_POST['add_cat_name'],FILTER_SANITIZE_STRING);

    $sql=<<<SQL
      INSERT INTO root_promotions_classification ("classification_name","build_time","desktop_domain","mobile_domain","status")
      VALUES ('{$name}','{$add_time}','{$domain_name}','{$sub_name}',1)
SQL;

    $result = runSQL($sql);
    if($result != 0){
        $logger = true;
    }else{
        $logger = false;
    }

    $data = array(
        'logger'=> $logger
    );
    // echo '<script>alert("'.$logger.'");</script>';
    echo json_encode($data);
}elseif ($action == 'test') {
// ----------------------------------------------------------------------------
    // test developer
    // ----------------------------------------------------------------------------
    var_dump($_POST);
    echo 'ERROR';

}
