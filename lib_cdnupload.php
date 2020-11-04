<?php
// ----------------------------------------------------------------------------
// Features:  CDN檔案交換
// File Name:
// Author:     orange
// Related:
// DB Table:
// Log:
// ----------------------------------------------------------------------------
// 使用:
// 1. $cdn = new CDNConnection($file); //放入$_FILE[id] 的資訊(包含name,tmp_name...等)
// 2. $cdn->CheckFile(array('jpg', 'png', 'bmp')) //判斷檔案類型合法性(預設'jpg', 'png', 'bmp')
//    不合法將回傳false 
//
// 3. $res = $cdn->UploatFile('upload/XXX/'); //開始上傳檔案(並帶入目的地目錄)
//
// 上傳結果會回傳 array()↓
// res: 1  //1為成功 0為失敗
// msg: "上传成功!"
// file: "d8bbe570-1b22-8094-2c51-de2b67220a1a.png" //回傳新檔名 若失敗則不回傳此項
// url: "完整圖片url" //若失敗則不回傳此項
//
// 刪除檔案:
// function DeleteCDNFile($base_dir, $file="")
//
// DeleteCDNFile('upload/XXX/','http://cdn.shopeebuy.com/site/upload/XXX/XXXX.png'); //刪除舊檔
// 會先檢查 $file 網址是否為CDN網域 再進行刪除動作。
// res: 
// 0: 刪除錯誤(CDN中無此檔案)
// 1: 刪除成功
// 2: 檔案domain與cdn網域不相符或未傳入$file值

class CDNConnection
{
    public function __construct($file)
    {
        global $config;
        $this->cdn_conf = $config['cdn_login'];
        $this->file     = $file;
    }

    public function CheckFile($fileextension = array('jpg', 'png', 'bmp'))
    {
        $extension = pathinfo($this->file['name'], PATHINFO_EXTENSION);

        if (in_array($extension, $fileextension)) {
            return true;
        } else {
            //echo '不允許該檔案格式';
            return false;
        }
    }

    //uuid
    public function uuid()
    {
        //mt_srand((double) microtime() * 10000); //optional for php 4.2.0 and up.
        $charid = md5(uniqid(rand(), true));
        $hyphen = chr(45); // "-"
        $uuid   = substr($charid, 0, 8) . $hyphen
        . substr($charid, 8, 4) . $hyphen
        . substr($charid, 12, 4) . $hyphen
        . substr($charid, 16, 4) . $hyphen
        . substr($charid, 20, 12);
        return $uuid;
    }

    public function FileRename()
    {
        $extension = pathinfo($this->file['name'], PATHINFO_EXTENSION);
        return $this->uuid() . '.' . $extension;
    }

    public function UploatFile($dir)
    {
        $connection = ssh2_connect($this->cdn_conf['host'], $this->cdn_conf['port']);
        ssh2_auth_password($connection, $this->cdn_conf['username'], $this->cdn_conf['password']);
        $sftp = ssh2_sftp($connection);

        $newfilename = $this->FileRename($this->file['name']);
        $dir         = $this->cdn_conf['base_path'] . $dir;

        //上傳指定的文件
        $res_code = copy($this->file['tmp_name'], 'ssh2.sftp://' . intval($sftp) . '/' . $dir . $newfilename);

        // 關閉聯接
        ssh2_exec($connection, 'exit');

        // 判斷是否正確上傳 並回傳值
        if ($res_code == 1) {
            return array('res' => 1, 'msg' => '上传成功!', 'file' => $newfilename ,'url' => $this->cdn_conf['url'].$dir.$newfilename);
        } else {
            return array('res' => 0, 'msg' => '上传错误!');
        }

    }
}

function DeleteCDNFile($base_dir, $file="")
{
    global $config;
    if ($file == "") {
        return array('res' => 2, 'msg' => '未輸入檔案');
    }
    $dir        = $config['cdn_login']['base_path'] . $base_dir;
    $path_parts = pathinfo($file);

    //若檔案沒有在我們CDN中...
    if (!preg_match('~'.$config['cdn_login']['url'] . $dir.'~', $file)) {
        return array('res' => 2, 'msg' => '未找到檔案!');
    }
    $connection = ssh2_connect($config['cdn_login']['host'], $config['cdn_login']['port']);
    ssh2_auth_password($connection, $config['cdn_login']['username'], $config['cdn_login']['password']);
    $sftp = ssh2_sftp($connection);

    //刪除指定的文件
    $res_code = unlink('ssh2.sftp://' . intval($sftp) . '/' . $dir . basename($file));

    // 關閉聯接
    ssh2_exec($connection, 'exit');

    // 判斷是否正確上傳 並回傳值
    if ($res_code == 1) {
        return array('res' => 1, 'msg' => '删除成功!');
    } else {
        return array('res' => 0, 'msg' => '删除错误!');
    }
}
