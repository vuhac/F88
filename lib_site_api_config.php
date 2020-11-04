<?php
namespace Api\SiteDeposit;
// ----------------------------------------------------------------------------
// Features : 後台 -- site api config LIB
// File Name: lib_site_api_config.php
// Author   : Webb
// Related  : site_api_config.php, site_api_config_detail.php
// Log      :
// ----------------------------------------------------------------------------
// table: root_site_api_account
// 相關的檔案
// 功能說明

class SiteApiConfig
{
    public $id;
    public $account_name;
    public $api_account;
    public $api_key;
    // 帳號狀態 0: 啟用, 1: 停用, 2: 維護中, 3: 刪除(封存))
    public $status;
    public $available_services;
    public $available_member_grade;
    public $ip_white_list;
    public $change_time;
    public $per_transaction_limit;
    public $daily_transaction_limit;
    public $monthly_transaction_limit;
    public $fee_rate;
    public $notes;
    public $transaction_timeout;
    public $transaction_category;

    public function __construct($params = ['jsonData' => null])
    {
        if (!empty(array_filter($params))) {
            $this->init($params);
        }
    }

    public function init($params)
    {
        extract($params);
        $projectid = $systemConfig['projectid'] ?? 'undefined';
        $uid = strtoupper(uniqid());
        $this->api_account = $projectid. '_'. $uid;
        $this->api_key = strtoupper(hash('sha256', $uid. strrev($uid)));

        foreach ($this as $key => $value) {
            if (isset($jsonData->$key)) {
                $this->$key = $jsonData->$key;
            }
        }
    }

    public function create()
    {
        $now = date('Y-m-d H:i:s.u+08', time());
        $sql = "INSERT INTO root_site_api_account (%s) VALUES ('%s') RETURNING id, change_time";

        $tmp = [];
        foreach ($this as $key => $value) {
            if ($key == 'id' or empty($value)) {
                continue;
            }
            $value = is_array($value) ? json_encode($value) : $value;

            if ($key == 'change_time') {
                $value = $now;
            }
            $tmp[$key] = $value;
        }
        $sql = sprintf($sql, implode(',', array_keys($tmp)), implode("','", array_values($tmp)));

        $result = runSQLall($sql)[1];
        foreach ($result as $key => $value) {
            $this->$key = $result->$key;
        }
    }

    /**
     * 取得特定 id 與 api_key 的設定值
     *
     * @param array $Arr 包含 id 或 apikey 的陣列
     *
     * @return object
     */
    public static function read(array $Arr = ['id' => 0, 'api_key' => '', 'api_account' => ''])
    {
        $where = implode(" AND ", array_map(function ($value, $key) {
            return "$key = :$key";
        }, $Arr, array_keys($Arr)));

        $sql = "SELECT * FROM root_site_api_account WHERE " . $where;
        $result = runSQLall_prepared($sql, $Arr, __CLASS__, 0, 'r')[0] ?? null;

        if (!is_null($result)) {
            $result->available_services = json_decode($result->available_services);
            $result->ip_white_list = json_decode($result->ip_white_list);
        }

        return $result;
    }

    /**
     * 取得全部的 api 設定值
     *
     * @return array
     */
    public static function readAll($filter=null, $callback=null)
    {
        $sql = "SELECT * FROM root_site_api_account";
        $result = runSQLall_prepared($sql, [], __CLASS__, 0, 'r');

        if (is_callable($filter)) {
            $result = array_values(array_filter($result, $filter));
        }

        if (\is_callable($callback)) {
            $callback($result);
        }

        return $result;
    }

    /**
     * 將設定實例的值更新到 table 中對應列
     *
     * @return void
     */
    public function update()
    {
        $now = date('Y-m-d H:i:s.u+08', time());
        $keys = $values = [];
        foreach ($this as $key => $value) {
            $value = is_array($value) ? json_encode(array_filter($value)) : $value;
            if ($key == 'change_time') {
                $value = $now;
            }

            $keys[] = "$key=:$key";
            $values[] = $value;
        }

        $sql = "UPDATE root_site_api_account SET ". implode(',', $keys). " WHERE id = :id";
        runSQLall_prepared($sql, $values);

        $this->change_time = $now;
    }

    /**
     * 刪除特定列 By id | api_key | api_account
     *
     * @return void
     */
    public static function delete(array $Arr = ['id' => 0, 'api_key' => '', 'api_account' => ''])
    {
        $tmp = [];
        foreach ($Arr as $key => $value) {
            $tmp[] = "$key='$value'";
        }

        $where = implode(" AND ", $tmp);
        $sql = "DELETE FROM root_site_api_account WHERE $where";
        runSQLall($sql);
    }

    /**
     * 進行更新前應比對修改時間資訊，避免資料舊蓋新
     *
     * @param object $cmpObj 輸入要比較的資料
     *
     * @return boolean
     */
    public function isConflicted($cmpObj, $debug = 1)
    {
        if ($debug == 1) {
            // 輸出衝突的欄位；至於用戶提示訊息應該另外處理
        }

        return strtotime($cmpObj->change_time) < strtotime($this->change_time);
    }

    /**
     * 取得全域 config
     *
     * @return $config
     */
    private function getSysConfig()
    {
        global $config;
        return $config;
    }
}
