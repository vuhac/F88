<?php
// ----------------------------------------------------------------------------
// Features: 後台 -- 首儲統計報表功能
// File Name: first_store_report_action.php
// Author: Damocles
// Related: first_store_report_*.php
// Log:
// ----------------------------------------------------------------------------
@session_start();
require_once dirname(__FILE__) ."/config.php";
require_once dirname(__FILE__) ."/lib.php";
require_once dirname(__FILE__) ."/first_store_report_lib.php";


// 判斷必要的環境參數是否有設定
if ( empty($config['default_timezone']) ) {
    die('config-default_timezone is not defined.');
}

if ( empty($config['default_locate']) ) {
    die('config-default_locate is not defined.');
}

if ( empty($config['currency_sign']) ) {
    die('config-currency_sign is not defined.');
}

if ( empty($protalsetting['member_deposit_currency']) ) {
    die('protalsetting-member_deposit_currency is not defined.');
} else { // 判斷參數設定是否在預期的值內
    $protalsetting['member_deposit_currency'] = strtolower($protalsetting['member_deposit_currency']);
    if ( ($protalsetting['member_deposit_currency'] != 'gtoken') && ($protalsetting['member_deposit_currency'] != 'gcash') ) {
        die('the value of protalsetting-member_deposit_currency is unexpected.');
    }
}

$system_attr = [ // 系統環境變數(為了避免塞參數給函式時塞太長，統一由這邊組合再塞給函式)
    'default_timezone' => $config['default_timezone'],
    'default_locate' => $config['default_locate'],
    'currency_sign' => $config['currency_sign'],
    'member_deposit_currency' => $protalsetting['member_deposit_currency']
];

$allow_columns = [ // 允許前台傳來的參數
    'member' => [
        'type',
        'search_store_account',
        'search_agent',
        'store_min_value',
        'store_max_value',
        'start_datetime',
        'end_datedatetime'
    ],
    'agent' => [
        'agent',
        'min_store_amount',
        'max_store_amount',
        'start_datetime',
        'end_datetime',
    ]
];

$search_details = [ // 初始化基礎搜尋條件
    'start' => ( isset($_GET['start']) ? (int)$_GET['start'] : 0 ),
    'length' => ( isset($_GET['length']) ? (int)$_GET['length'] : 10 )
];

if ( !empty($_GET['search']['value']) ) { // 有傳送搜尋條件
    $temp = (array)json_decode($_GET['search']['value']);
    if ( !empty($temp['type']) ) {
        // 過濾與組合搜尋參數
        foreach ($allow_columns['member'] as $val) {
            $search_details[$val] = ( ( !empty($temp[$val]) ) ? $temp[$val] : null );
        }
        return generateData($search_details, $system_attr);
    }
} else if ( !empty($_GET['type']) ) { // 初次載入
    switch ($_GET['type']) {
        case 'member':
            // 過濾與組合搜尋參數
            foreach ($allow_columns['member'] as $val) {
                $search_details[$val] = null;
            }
            $search_details['type'] = 'member';
            $now_date = date("Y-m-d");
            $search_details['start_datetime'] = $now_date.' 00:00:00';
            $search_details['end_datedatetime'] = $now_date.' 23:59:59';

            return generateData($search_details, $system_attr);
            break;
        case 'agent':
            // 過濾與組合搜尋參數，如果未設定參數則給予空值('')
            foreach ($allow_columns['agent'] as $val) {
                $search_details[$val] = ( ( !isset($_GET[$val]) ) ? '' : $_GET[$val] );
            }

            // 回傳結果
            $result = [
                'count' => 0, // 所有符合條件資料的總數
                'data' => [], // 指定頁面的資料
                'msg' => ''
            ];

            // 取得所有符合條件的資料
            $first_store_data = main($system_attr['member_deposit_currency'], $system_attr['default_timezone'], $search_details);

            // 判斷回傳是陣列還是字串(錯誤訊息)
            if ( is_array($first_store_data) ) {
                // 判斷有沒有帳號搜尋條件
                if ( !empty($search_details['agent']) ) {
                    foreach ($first_store_data as $key=>$val) {
                        if ($key != $search_details['agent']) {
                            unset($first_store_data[$key]);
                        }
                    }
                }

                // 更新所有符合條件資料的總數
                $result['count'] = count($first_store_data);

                // 在有資料的情況下，取出指定頁數的資料
                if ($result['count'] > 0) {
                    $round = 0;
                    foreach ($first_store_data as $key=>$val) {
                        $round++;
                        if ( ($search_details['start'] < $round) && ($round <= ($search_details['start'] + $search_details['length'])) ) {
                            $val['under_line_people_count'] = number_format($val['under_line_people_count']);
                            $val['under_line_amount_total'] = transCurrencySign($val['under_line_amount_total'], $system_attr['default_locate'], $system_attr['currency_sign']);
                            $val['agent_line_people_count'] = number_format($val['agent_line_people_count']);
                            $val['agent_line_amount_total'] = transCurrencySign($val['agent_line_amount_total'], $system_attr['default_locate'], $system_attr['currency_sign']);
                            $val['account'] = $key;
                            array_push($result['data'], $val);
                        }
                    }
                }
            } else {
                $result['msg'] = $first_store_data;
            }

            echo json_encode($result);
            break;
        default:
            //
    }
}

?>