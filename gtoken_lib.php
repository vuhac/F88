<?php
// ----------------------------------------------------------------------------
// Features:	後台代幣操作處理專用用函式, 將處理代幣得函式集中統一處理.
// File Name:	gtoken_lib.php
// Author:		Barkley/yaoyuan
// Related:
// Log:
// 2017.5.8  v0.1 by Barkley
// ----------------------------------------------------------------------------
/*
使用方式：在需要得 action 再引入使用即可, 無須每個檔案都載入。
// 引用代幣處理函式庫
require_once dirname(__FILE__) ."/gtoken_lib.php";

*/



// ----------------------------------------------------------------------------
// Features:
//   會員間, 代幣轉帳功能函式
// Usage:
//   member_depositgtoken($member_id, $source_transferaccount, $destination_transferaccount, $transaction_money, $password_verify_sha1, $summary, $transaction_category, $realcash, $auditmode_select, $auditmode_amount, $system_note_input=NULL, $debug=0)
// Input:
//   $member_id --> 會員ID 同時也是操作者 ID 也是轉帳人員
//   $source_transferaccount --> 指定轉帳帳號
//   $destination_transferaccount --> 目的帳號 , 有檢查是否被 casino 鎖定
//   $transaction_money --> 轉帳金額
//   $password_verify_sha1 --> 來源或管理員帳號的密碼驗證，驗證後才可以轉帳
//   $summary --> 摘要資訊
//   $transaction_category --> 交易類別
//   $realcash --> 實際存提
//   $auditmode_select --> 稽核模式，三種：免稽核freeaudit、存款稽核depositaudit、優惠存款稽核 shippingaudit
//   $system_note_input --> 系統轉帳文字資訊
//   debug = 1 --> 進入除錯模式 , debug = 0 --> 關閉除錯
// Return:
//   code = 1  --> 成功
//   code != 1  --> 其他原因導致失敗
// Releated:
//   後台 member_depositgtoken.php
//   前台
//   使用到這個 lib , 如果修正的話, 需要一起修正
// Log:
//   by barkley 2017.5.7
// ----------------------------------------------------------------------------
// 後台遊戲幣取款審查，退回。
// 前台公司入款到遊戲幣 107.06.01
function member_gtoken_transfer(
    $member_id,
    $source_transferaccount,
    $destination_transferaccount,
    $transaction_money,
    $password_verify_sha1,
    $summary,
    $transaction_category,
    $realcash,
    $auditmode_select,
    $auditmode_amount,
    $system_note_input = NULL,
    $debug = 0,
    $d_transaction_id = '',
    $operator = NULL
) {
    global $config;
    global $tr;
    // 組合參數
    $d = [
        'member_id' => $member_id, // 會員ID 同時也是操作者 ID 也是轉帳人員
        'source_transferaccount' => $source_transferaccount, // 轉出帳號
        'destination_transferaccount' => $destination_transferaccount, // 轉入帳號
        'transaction_money' => $transaction_money, // 轉帳金額
        'auditmode_amount' => $auditmode_amount, // 稽核金額
        'summary' => $summary, // 摘要資訊
        'transaction_category' => $transaction_category, // 交易類別
        'realcash' => $realcash, // 實際存提
        'auditmode_select' => $auditmode_select, // 稽核模式，三種：免稽核freeaudit、存款稽核depositaudit、優惠存款稽核 shippingaudit
        'password_verify_sha1' => $password_verify_sha1, // 來源或管理員帳號的密碼驗證，驗證後才可以轉帳
        'system_note_input' => $system_note_input, // 系統轉帳文字資訊
        'd_transaction_id' => $d_transaction_id // 公司入款交易單號
    ];

    //  $debug = 1;
    if ($debug == 1) {
        echo '輸入的資訊';
        var_dump($d);
    }

    $auditmode_amount = (int)$auditmode_amount;
    // 轉帳金額及稽核金額，需要為浮點數型態或是整數型態，才可以繼續. 浮點數取到小數點第二位。
    if (!filter_var($transaction_money, FILTER_VALIDATE_FLOAT) === false) {
        // 取得管理員的帳號資料, 確認沒有被 lock 或是有效帳號
        $member_acc = runSQLall(<<<SQL
            SELECT *
            FROM "root_member"
            JOIN "root_member_wallets"
                ON "root_member"."id" = "root_member_wallets"."id"
            WHERE ("root_member"."id" = '{$d["member_id"]}')
            AND ("root_member"."status" = '1');
        SQL);

        if ($debug == 1) {
            echo '取得管理員的帳號資料';
            var_dump($member_acc);
        }

        // 取得轉帳來源的帳號資料, 確認沒有被 lock 或是有效帳號
        $source_acc = runSQLall(<<<SQL
            SELECT *
            FROM "root_member"
            JOIN "root_member_wallets"
                ON "root_member"."id" = "root_member_wallets"."id"
            WHERE ("root_member"."account" = '{$d["source_transferaccount"]}')
            AND ("root_member"."status" = '1');
        SQL);

        if ($debug == 1) {
            echo '取得轉帳來源的帳號資料';
            var_dump($source_acc);
        }

        // 取得目標帳號的資料, 確認沒有被 lock 或是有效帳號
        $check_acc = runSQLall(<<<SQL
            SELECT *
            FROM "root_member"
            JOIN "root_member_wallets"
                ON "root_member"."id" = "root_member_wallets"."id"
            WHERE "root_member"."account" = '{$d["destination_transferaccount"]}'
            AND ("root_member"."status" = '1');
        SQL);

        if ($debug == 1) {
            echo '取得目標帳號的資料';
            var_dump($check_acc);
        }

        // 三個帳號資料, 有資料
        if ( ($check_acc[0] == 1) && ($member_acc[0] == 1) && ($source_acc[0] ==1) ) {
            // 帳號正確
            $error['code'] = '1';
            $error['messages'] = '目標帳號, 來源帳號 及操作員帳號存在';

            // check 轉帳密碼是否正確 , 密碼須為管理員, 或是來源帳號的密碼
            // 檢查轉帳密碼是否和來源帳號密碼一樣 , 也就是來源者需要同意才轉帳
            // 如果是管理員操作轉帳, 預設填入 tran5566 的密碼. 因為管理員不會知道會員的密碼, 所以給個固定值
            if ( ($d['password_verify_sha1'] == $source_acc[1]->withdrawalspassword) || ($d['password_verify_sha1'] == 'tran5566') ) {
                // correct
                $error['code'] = '1';
                $error['messages'] = '轉帳密碼正確';

                // 轉帳 gtoken 的動作

                // 0. 取得目的端使用者完整的資料
                $destination_transferaccount_result = $check_acc;
                if ($destination_transferaccount_result[0] == 1) {
                    // 1. 取得來源端使用者完整的資料
                    $error['code'] = '1';
                    $error['messages'] = '取得來源端使用者完整的資料';

                    $source_transferaccount_result = $source_acc;
                    if ($source_transferaccount_result[0] == 1) {
                        // 2. 檢查帳戶 $source_transferaccount 是否有錢,且大於 $transaction_money , 成立才工作,否則結束
                        if ($source_transferaccount_result[1]->gtoken_balance >= $d['transaction_money']) {
                            $error['code'] = '1';
                            $error['messages'] = "{$d['source_transferaccount']}有餘額，且大於轉帳金額{$d['transaction_money']}";

                            // 稽核判斷寫入 notes 的文字 , and 控制稽核金額
                            if ($d['auditmode_select'] == 'depositaudit') {
                                $audit_notes = "存款稽核{$d['auditmode_amount']}元";
                            } else if ($d['auditmode_select'] == 'shippingaudit') {
                                $audit_notes = "优惠稽核{$d['auditmode_amount']}元";
                            } else {
                                if ($d['transaction_category'] == 'tokenpreferential') {
                                    // 反水稽核 10 倍
                                    $d['auditmode_amount'] = ($d['transaction_money'] * 0);
                                    $audit_notes = "反水稽核{$d['auditmode_amount']}元";
                                } else if ($d['transaction_category'] == 'tokenpay') {
                                    $d['auditmode_amount'] = 0;
                                    $audit_notes = "派彩免稽核{$d['auditmode_amount']}元";
                                } else {
                                    $audit_notes = "免稽核{$d['auditmode_amount']}元";
                                }
                            }

                            if ($debug == 1) {
                                var_dump($d);
                                var_dump($audit_notes);
                            }

                            // 操作：root_member_wallets
                            $transaction_money_sql = <<<SQL
                                -- 交易開始
                                BEGIN;
                                -- 轉出帳號餘額扣掉交易金額(transaction_money)
                                UPDATE "root_member_wallets"
                                SET "changetime" = NOW(),
                                    "gtoken_balance" = (
                                        SELECT ("gtoken_balance" - {$d['transaction_money']}) as "amount"
                                        FROM "root_member_wallets"
                                        WHERE ("id" = '{$source_transferaccount_result[1]->id}')
                                    )
                                WHERE ("id" = '{$source_transferaccount_result[1]->id}');
                                -- 轉入帳號餘額加上交易金額(transaction_money)
                                UPDATE "root_member_wallets"
                                SET "changetime" = NOW(),
                                    "gtoken_balance" = (
                                        SELECT ("gtoken_balance" + {$d['transaction_money']}) as "amount"
                                        FROM "root_member_wallets"
                                        WHERE ("id" = '{$destination_transferaccount_result[1]->id}')
                                    )
                                WHERE ("id" = '{$destination_transferaccount_result[1]->id}');
                            SQL;

                            // 判斷轉入帳號是否為第一次儲值，是的話要更新root_member的first_deposite_date
                            if ( is_null($check_acc[1]->first_deposite_date) || ($check_acc[1]->first_deposite_date == null) || empty($check_acc[1]->first_deposite_date) ) {
                                $transaction_money_sql .= <<<SQL
                                    UPDATE "root_member"
                                    SET "first_deposite_date" = NOW()
                                    WHERE ("id" = '{$check_acc[1]->id}');
                                SQL;
                            }

                            // 操作：root_member_gtokenpassbook
                            // PGSQL 新增 1 筆紀錄 帳號 source_transferaccount 轉帳到 destination_transferaccount 金額 transaction_money
                            $source_notes = "(管理员{$d['source_transferaccount']} 因使用者申請{$d['summary']}，取款到 {$d['destination_transferaccount']}帐号, {$audit_notes})";
                            $transaction_money_sql .= <<<SQL
                                INSERT INTO "root_member_gtokenpassbook" (
                                    "transaction_time",
                                    "deposit",
                                    "withdrawal",
                                    "system_note",
                                    "member_id",
                                    "currency",
                                    "summary",
                                    "source_transferaccount",
                                    "auditmode",
                                    "auditmodeamount",
                                    "realcash",
                                    "destination_transferaccount",
                                    "transaction_category",
                                    "balance",
                                    "transaction_id",
                                    "operator"
                                ) VALUES (
                                    'now()',
                                    '0',
                                    '{$d["transaction_money"]}',
                                    '{$source_notes}',
                                    '{$member_acc[1]->id}',
                                    '{$config["currency_sign"]}',
                                    '{$d["summary"]}',
                                    '{$d["source_transferaccount"]}',
                                    '{$d["auditmode_select"]}',
                                    '{$d["auditmode_amount"]}',
                                    '{$d["realcash"]}',
                                    '{$d["destination_transferaccount"]}',
                                    '{$d["transaction_category"]}', (
                                        SELECT "gtoken_balance"
                                        FROM "root_member_wallets"
                                        WHERE "id" = '{$source_transferaccount_result[1]->id}'
                                    ),'{$d_transaction_id}',
                                    '{$operator}'
                                );
                            SQL;

                            // PGSQL 新增 1 筆紀錄 帳號 destination_transferaccount 收到來自 source_transferaccount 金額 transaction_money
                            $destination_notes = "({$d['destination_transferaccount']}申請{$d['summary']}，收到管理員{$d['source_transferaccount']}的金額, {$audit_notes})";
                            $transaction_money_sql .= <<<SQL
                                INSERT INTO "root_member_gtokenpassbook" (
                                    "transaction_time",
                                    "deposit",
                                    "withdrawal",
                                    "system_note",
                                    "member_id",
                                    "currency",
                                    "summary",
                                    "source_transferaccount",
                                    "auditmode",
                                    "auditmodeamount",
                                    "realcash",
                                    "destination_transferaccount",
                                    "transaction_category",
                                    "balance",
                                    "transaction_id",
                                    "operator"
                                ) VALUES (
                                    'now()',
                                    '{$d["transaction_money"]}',
                                    '0',
                                    '{$destination_notes}',
                                    '{$member_acc[1]->id}',
                                    '{$config["currency_sign"]}',
                                    '{$d["summary"]}',
                                    '{$d["destination_transferaccount"]}',
                                    '{$d["auditmode_select"]}',
                                    '{$d["auditmode_amount"]}',
                                    '{$d["realcash"]}',
                                    '{$d["source_transferaccount"]}',
                                    '{$d["transaction_category"]}', (
                                        SELECT "gtoken_balance"
                                        FROM "root_member_wallets"
                                        WHERE "id" = '{$destination_transferaccount_result[1]->id}'
                                    ),'{$d_transaction_id}',
                                    '{$operator}'
                                );
                            SQL;

                            // commit 提交
                            $transaction_money_sql .= <<<SQL
                                COMMIT;
                            SQL;

                            if ($debug == 1) {
                                echo '<pre>',var_dump($transaction_money_sql), '</pre>';
                            }

                            // 執行 transaction sql
                            $transaction_money_result = runSQLtransactions($transaction_money_sql);
                            if ($transaction_money_result) {
                                $error['code'] = '1';
                                $transaction_money_html = money_format('%i', $d['transaction_money']);
                                $error['messages'] = "成功转帐从{$d['source_transferaccount']}到{$d['destination_transferaccount']}金额:{$transaction_money_html}";
                            } else {
                                $error['code'] = '7';
                                $error['messages'] = "SQL转帐失败从{$d['source_transferaccount']}到{$d['destination_transferaccount']}金额{$d['transaction_money']}";
                            }
                        } else {
                            $error['code'] = '6';
                            $error['messages'] = $d['source_transferaccount'].'余额不足'.$d['transaction_money'];
                        }
                    } else {
                        $error['code'] = '4';
                        $error['messages'] = '查不到来源端的使用者'.$d['source_transferaccount'].'资料。';
                    }
                } else {
                    $error['code'] = '5';
                    $error['messages'] = '查不到目的端的使用者'.$d['destination_transferaccount'].'资料。';
                }
            } else {
                // incorrect
                $error['code'] = '3';
                $error['messages'] = '管理员或是来源帐号确认的密码不正确';
            }
        } else {
          $destination_acc_sql     = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$destination_transferaccount."' AND root_member.status = '2';";
          $destination_acc_result  = runSQLall($destination_acc_sql);
          if ($destination_acc_result[0] == 1) {
            $error['code'] = '2';
            $error['messages'] = $tr['This account has been frozen, you need to unlock the wallet first to operate deposits and withdrawals'];
          } else {
            $error['code'] = '21';
            $tr['Member information query failed'] = '會員資訊查詢失敗。';
            $error['messages'] = $tr['this feature is currently unavailable for member status'];
          }
          
        }
    } else {
        $error['code'] = '521';
        $error['messages'] = '稽核金額 OR 轉帳金額 , 非整數或是浮點數金額';
        return($error);
    }

    if ($debug == 1) {
        var_dump($error);
    }

    return($error);
}
// ----------------------------------------------------------------------------
// 代幣轉帳函式 end
// ----------------------------------------------------------------------------

// 後台人工存入遊戲幣2018.05.29
// 後台人工提出遊戲幣2018.05.31

function member_gtoken_transfer_sql($data) {

  $sql = '';
  if (!isset($data->operator)){
    $data->operator=$_SESSION['agent']->account;
  }
  // 稽核判斷寫入 notes 的文字 , and 控制稽核金額
  // 這段要再改寫
  switch ($data->auditmode_select) {
    case 'depositaudit':
      $audit_notes = '存款稽核'.$data->auditmode_amount;
      break;
    case 'tokenpreferential':
      $audit_notes = '優惠稽核'.$data->auditmode_amount;
      break;
    default:
      if( $data->transaction_category == 'tokenpreferential') {
        // 反水稽核 10 倍，20180529泰哥說，反水不用稽核
        $auditmode_amount = $data->transaction_money * 0;
        $audit_notes = '反水稽核'.$auditmode_amount;
      } elseif( $data->transaction_category == 'tokenpay') {
        $auditmode_amount = 0;
        $audit_notes = '派彩免稽核'.$auditmode_amount;
      } else {
        $audit_notes = '免稽核'.$data->auditmode_amount;
      }
      break;
  }
// var_dump($data);die();
  // 操作：root_member_wallets
  // 來源帳號餘額刪除 transaction_money
  $sql .= <<<SQL
  UPDATE root_member_wallets
  SET changetime = NOW(),
      gtoken_balance = (SELECT (gtoken_balance-'{$data->transaction_money}') as amount
                        FROM root_member_wallets
                        WHERE id = '{$data->source_transferaccid}')
  WHERE id = '{$data->source_transferaccid}';
SQL;
  // 目的帳號加入上 transaction_money 餘額
  $sql .= <<<SQL
  UPDATE root_member_wallets
  SET changetime = NOW(),
      gtoken_balance = (SELECT (gtoken_balance+'{$data->transaction_money}') as amount
                        FROM root_member_wallets
                        WHERE id = '{$data->destination_transferaccid}')
  WHERE id = '{$data->destination_transferaccid}';
SQL;

    // 操作：root_member_gtokenpassbook
    // PGSQL 新增 1 筆紀錄 帳號 source_transferaccount 轉帳到 destination_transferaccount 金額 transaction_money
    $source_notes = "(管理员将{$data->source_transferaccount}帳號{$data->summary}到 {$data->destination_transferaccount}帐号, {$audit_notes}){$data->system_note}";

    $sql .= <<<SQL
        INSERT INTO root_member_gtokenpassbook (
            transaction_time,
            deposit,
            withdrawal,
            system_note,
            member_id,
            currency,
            summary,
            source_transferaccount,
            auditmode,
            auditmodeamount,
            realcash,
            destination_transferaccount,
            transaction_category,
            balance,
            transaction_id,
            operator
        ) VALUES (
            'now()',
            '0',
            '{$data->transaction_money}',
            '{$source_notes}',
            '{$data->member_id}',
            '{$data->currency_sign}',
            '{$data->summary}',
            '{$data->source_transferaccount}',
            '{$data->auditmode_select}',
            '{$data->auditmode_amount}',
            '{$data->realcash}',
            '{$data->destination_transferaccount}',
            '{$data->transaction_category}',
            (SELECT gtoken_balance FROM root_member_wallets WHERE id = '{$data->source_transferaccid}'),
            '{$data->transaction_id}',
            '{$data->operator}'
        );
    SQL;

    // PGSQL 新增 1 筆紀錄 帳號 destination_transferaccount 收到來自 source_transferaccount 金額 transaction_money
    $destination_notes = "(管理员存款到{$data->destination_transferaccount}帐号, {$audit_notes}){$data->system_note}";
    $sql .= <<<SQL
        INSERT INTO root_member_gtokenpassbook (
            transaction_time,
            deposit,
            withdrawal,
            system_note,
            member_id,
            currency,
            summary,
            source_transferaccount,
            auditmode,
            auditmodeamount,
            realcash,
            destination_transferaccount,
            transaction_category,
            balance,
            transaction_id,
            operator
        ) VALUES (
            'now()',
            '{$data->transaction_money}',
            '0',
            '{$destination_notes}',
            '{$data->member_id}',
            '{$data->currency_sign}',
            '{$data->summary}',
            '{$data->destination_transferaccount}',
            '{$data->auditmode_select}',
            '{$data->auditmode_amount}',
            '{$data->realcash}',
            '{$data->source_transferaccount}',
            '{$data->transaction_category}',
            (SELECT gtoken_balance FROM root_member_wallets WHERE id = '{$data->destination_transferaccid}'),
            '{$data->transaction_id}',
            '{$data->operator}'
        );
    SQL;

  if($data->debug==1) {
    echo '<pre>';
    print_r($sql);
    echo '</pre>';
  }

  return $sql;
}


// ----------------------------------------------------------------------------
// 代幣轉帳審核通過通知函式 start
// ----------------------------------------------------------------------------

/**
 * Undocumented function
 *
 * @param [type] $member_id - 操作者 id
 * @param [type] $source_transferaccount - 轉帳來源帳號
 * @param [type] $destination_transferaccount - 轉帳目標帳號
 * @param [type] $transaction_money - 轉帳金額
 * @param [type] $password_verify_sha1 - 來源或管理員帳號的密碼驗證
 * @param [type] $summary - 轉帳摘要
 * @param [type] $transaction_category - 交易類別
 * @param [type] $realcash - 實際提存
 * @param [type] $auditmode_select - 稽核模式 : 免稽核freeaudit、存款稽核depositaudit、優惠存款稽核 shippingaudit
 * @param [type] $auditmode_amount - 稽核金額
 * @param [type] $system_note_input - 系統轉帳文字資訊(補充)
 * @param int $debug - 1 --> 進入除錯模式, 0 --> 關閉除錯
 * @return array
 */
// 後台遊戲幣取款審核通知同意
function member_gtoken_notice($member_id, $source_transferaccount, $destination_transferaccount, $transaction_money, $password_verify_sha1, $summary, $transaction_category, $realcash, $auditmode_select, $auditmode_amount, $system_note_input=NULL, $debug=0,$transaction_id='',$operator=NULL) {
  global $config;
  global $tr;
  // 會員ID 同時也是操作者 ID 也是轉帳人員
  $d['member_id']                    = $member_id;
  // 指定轉帳帳號
  $d['source_transferaccount']       = $source_transferaccount;
  // 目的帳號
  $d['destination_transferaccount']  = $destination_transferaccount;
  // 轉帳金額
  $d['transaction_money']            = $transaction_money;
  // 稽核金額
  $d['auditmode_amount']            = $auditmode_amount;

  // 摘要資訊
  $d['summary']                     = $summary;
  // 交易類別
  $d['transaction_category']        = $transaction_category;
  // 實際存提
  $d['realcash']                    = $realcash;
  // 稽核模式，三種：免稽核freeaudit、存款稽核depositaudit、優惠存款稽核 shippingaudit
  $d['auditmode_select']            = $auditmode_select;
  // 來源或管理員帳號的密碼驗證，驗證後才可以轉帳
  $d['password_verify_sha1']        = $password_verify_sha1;
  // 系統轉帳文字資訊
  $d['system_note_input']           = $system_note_input;
  // 存取款單號
  $d['transaction_id']              = $transaction_id;


  // 取得管理員的帳號資料, 確認沒有被 lock 或是有效帳號
  $member_acc_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = '".$d['member_id']."' AND root_member.status = '1';";
  $member_acc = runSQLall($member_acc_sql);
  if($debug == 1) {
    echo '取得管理員的帳號資料';
    var_dump($member_acc);
  }

  // 取得轉帳來源的帳號資料, 確認沒有被 lock 或是有效帳號
  $source_acc_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$d['source_transferaccount']."' AND root_member.status = '1';";
  $source_acc = runSQLall($source_acc_sql);
  if($debug == 1) {
    echo '取得轉帳來源的帳號資料';
    var_dump($source_acc);
  }


  // 取得目標帳號的資料, 確認沒有被 lock 或是有效帳號
  $check_acc_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$d['destination_transferaccount']."' AND root_member.status = '1';";
  $check_acc = runSQLall($check_acc_sql);
  if($debug == 1) {
    echo '取得目標帳號的資料';
    var_dump($check_acc);
  }

  if($check_acc[0] == 1 AND $member_acc[0] == 1 AND $source_acc[0] ==1 ){
    $destination_transferaccount_result = $check_acc;
    $source_transferaccount_result = $source_acc;

    // 稽核判斷寫入 notes 的文字 , and 控制稽核金額
    if($d['auditmode_select'] == 'depositaudit'){
      $audit_notes = '存款稽核'.$d['auditmode_amount'];
    }elseif($d['auditmode_select'] == 'shippingaudit'){
      $audit_notes = '優惠稽核'.$d['auditmode_amount'];
    }else{
      if( $d['transaction_category'] == 'tokenpreferential'){
        // 反水稽核 0 倍
        $d['auditmode_amount'] = $d['transaction_money']*0;
        $audit_notes = '反水稽核'.$d['auditmode_amount'];
      }elseif( $d['transaction_category'] == 'tokenpay'){
        $d['auditmode_amount'] = 0;
        $audit_notes = '派彩免稽核'.$d['auditmode_amount'];
      }else{
        $audit_notes = '免稽核'.$d['auditmode_amount'];
      }
    }

    if($debug == 1) {
      var_dump($d);
      var_dump($audit_notes);
    }


    /*
    代幣如果在娛樂城, 要先回收代幣才能進行代幣轉帳動作
    */
    // if (gtoken_ststus($source_acc[1]->gtoken_lock) == 'casino_used') {
    //   $error['code'] = '8';
    //   $error['messages'] = '帳號 '.$source_acc[1]->account.' 代幣正在 '.$source_acc[1]->gtoken_lock.' 使用，請使用者回收代幣後再進行代幣取款。';
    // } elseif (gtoken_ststus($check_acc[1]->gtoken_lock) == 'casino_used') {
    //   $error['code'] = '8';
    //   $error['messages'] = '帳號 '.$check_acc[1]->account.' 代幣正在 '.$check_acc[1]->gtoken_lock.' 使用，請使用者回收代幣後再進行代幣取款。';
    // } else {
      // 交易開始
      $transaction_money_sql = 'BEGIN;';

      // 操作：root_member_wallets
      // 來源帳號餘額刪除 transaction_money
      $transaction_money_sql = $transaction_money_sql.
      'UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (SELECT (gtoken_balance-'.$d['transaction_money'].') as amount FROM root_member_wallets WHERE id = '.$source_transferaccount_result[1]->id.') WHERE id = '.$source_transferaccount_result[1]->id.';';
      // 目的帳號加入上 transaction_money 餘額
      $transaction_money_sql = $transaction_money_sql.
      'UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (SELECT (gtoken_balance+'.$d['transaction_money'].') as amount FROM root_member_wallets WHERE id = '.$destination_transferaccount_result[1]->id.') WHERE id = '.$destination_transferaccount_result[1]->id.';';

      // 操作：root_member_gtokenpassbook
      $source_notes = "(".$d['source_transferaccount'].' 帳號收到審核同意通知訊息，'.$audit_notes.'。)'.$d['system_note_input'];
      // $source_notes = "(管理員".$member_acc[1]->account." 帳號同意 ".$d['source_transferaccount'].' 帳號，'.$audit_notes.'。)'.$d['system_note_input'];
      $transaction_money_sql = $transaction_money_sql.
      'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance","transaction_id","operator")'.
      "VALUES ('now()', '0', '".$d['transaction_money']."', '".$source_notes."', '".$member_acc[1]->id."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['source_transferaccount']."', '".$d['auditmode_select']."', '".$d['auditmode_amount']."', '".$d['realcash']."', '".$d['destination_transferaccount']."', '".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$source_transferaccount_result[1]->id."),'".$transaction_id."','".$operator."');";

      // $destination_notes = "(".$d['source_transferaccount'].' 帳號收到審核同意通知訊息，'.$audit_notes.'。)'.$d['system_note_input'];
      $destination_notes = "(管理員".$member_acc[1]->account." 帳號同意 ".$d['source_transferaccount'].' 帳號，'.$audit_notes.'。)'.$d['system_note_input'];
      $transaction_money_sql = $transaction_money_sql.
        'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance","transaction_id","operator")'.
      "VALUES ('now()', '".$d['transaction_money']."', '0', '".$destination_notes."', '".$member_acc[1]->id."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['destination_transferaccount']."', '".$d['auditmode_select']."', '".$d['auditmode_amount']."', '".$d['realcash']."', '".$d['source_transferaccount']."', '".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$destination_transferaccount_result[1]->id."),'".$transaction_id."','".$operator."');";

        // commit 提交
      $transaction_money_sql = $transaction_money_sql.'COMMIT;';
      // echo '<p>'.$transaction_money_sql.'</p>';

        if($debug==1) {
          echo '<pre>';
          print_r($transaction_money_sql);
          echo '</pre>';
        }

        // 執行 transaction sql
      $transaction_money_result = runSQLtransactions($transaction_money_sql);
      if($transaction_money_result){
        $error['code'] = '1';
        $transaction_money_html = money_format('%i', $d['transaction_money']);
        $error['messages'] = '成功转帐从'.$d['source_transferaccount'].'到'.$d['destination_transferaccount'].'金额:'.$transaction_money_html;
      }else{
        $error['code'] = '7';
        $error['messages'] = 'SQL转帐失败从'.$d['source_transferaccount'].'到'.$d['destination_transferaccount'].'金额'.$d['transaction_money'];;
      }
    // }
  } else {
    $source_acc_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$d['source_transferaccount']."' AND root_member.status = '2';";
    $source_acc = runSQLall($source_acc_sql);
    if ($source_acc[0] == 1) {
      $error['code'] = '2';
      $error['messages'] = $tr['This account has been frozen, you need to unlock the wallet first to operate deposits and withdrawals'];
    } else {
      $error['code'] = '3';
      $tr['Member information query failed'] = '會員資訊查詢失敗。';
      $error['messages'] = $tr['this feature is currently unavailable for member status'];
    }
    
  }

  return($error);
}

// ----------------------------------------------------------------------------
// 代幣轉帳審核通過通知函式 end
// ----------------------------------------------------------------------------
// 公司入款至遊戲幣，取消函式
function member_gtoken_cancel_notice($transaction_category_index, $summary, $member_id, $source_transferaccount, $destination_transferaccount, $withdrawal_password, $transaction_money, $realcash, $system_note, $debug=0,$d_transaction_id='',$operator=NULL) {
    global $config;
    global $tr;

    // 取得管理員的帳號資料
    $member_acc_sql     = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = '".$member_id."' AND root_member.status = '1';";
    $member_acc_result  = runSQLall($member_acc_sql);
    // var_dump($member_acc_result);die();
    if($debug == 1) {
     echo '取得管理員的帳號';
     var_dump($member_acc_result);
    }

    // 取得轉帳來源帳號
    $source_acc_sql     = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$source_transferaccount."' AND root_member.status = '1';";
    $source_acc_result  = runSQLall($source_acc_sql);
    if($debug == 1) {
     echo '取得轉帳來源帳號';
     var_dump($source_acc_result);
    }

    // 轉帳目標帳號
    $destination_acc_sql     = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$destination_transferaccount."' AND root_member.status = '1';";
    $destination_acc_result  = runSQLall($destination_acc_sql);
    if($debug == 1) {
     echo '轉帳目標帳號';
     var_dump($destination_acc_result);
    }

    if($member_acc_result[0] == 1 AND $source_acc_result[0] == 1 AND $destination_acc_result[0] == 1) {

      // 操作 table： root_member_gtokenpassbook 遊戲幣存簿
      //提款交易訊息(來源帳號) -- 提款
      // $d_gtoken_notes = "(".$source_acc_result[1]->account."帳號收到審核不同意通知訊息。)".$system_note;
      $d_gtoken_notes = "(".$operator."不同意".$destination_acc_result[1]->account."的公司存款。)";//.$system_note;
      $source_account_insert_sql = 'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "withdrawal", "system_note", "member_id", "currency", "realcash"
      , "summary", "source_transferaccount","auditmode","auditmodeamount","destination_transferaccount", "balance", "transaction_category",
      "transaction_id","operator")'.
      "VALUES ('now()', '".$transaction_money."', '".$d_gtoken_notes."', '".$member_acc_result[1]->id."', '".$config['currency_sign']."', '".$realcash."'
      ,'".$summary."', '".$source_acc_result[1]->account."','freeaudit','0','".$destination_acc_result[1]->account."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = '".$source_acc_result[1]->id."'), '".$transaction_category_index."','".$d_transaction_id."','".$operator."');";
      // var_dump($source_account_insert_sql);die();

      //存款交易訊息(目標帳號) -- 存款
      // $d_gtoken_notes = "(".$member_acc_result[1]->account."帳號同意".$source_acc_result[1]->account."帳號。)".$system_note;
      $d_gtoken_notes = "(".$destination_acc_result[1]->account."帐号收到审核不同意通知讯息。)";//.$system_note;
      $destination_account_insert_sql = 'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "system_note", "member_id", "currency", "realcash"
        , "summary", "source_transferaccount", "destination_transferaccount", "auditmode","auditmodeamount","balance", "transaction_category"
        ,"transaction_id","operator")'.
      "VALUES ('now()', '".$transaction_money."', '".$d_gtoken_notes."', '".$member_acc_result[1]->id."', '".$config['currency_sign']."', '".$realcash."'
        , '".$summary."', '".$destination_acc_result[1]->account."','".$source_acc_result[1]->account."','freeaudit','0', (SELECT gtoken_balance FROM root_member_wallets where id = '".$destination_acc_result[1]->id."'), '".$transaction_category_index."','".$d_transaction_id."','".$operator."');";
      // var_dump($source_account_insert_sql);die();


      // 最後資料輸入動態
      $transaction_money_sql = 'BEGIN;'
          // .$cash_review_update_status_sql
          .$source_account_insert_sql
          .$destination_account_insert_sql
          .'COMMIT;';

      $transaction_money_result = runSQLtransactions($transaction_money_sql);
      // var_dump($transaction_money_result);die();

      // 最終是否正確執行取款同意資料
      if($transaction_money_result == 1){
        // $logger = '成功同意取款。';
        // memberlog2db($_SESSION['agent']->account,'withdrawal','info', "$logger");

        $error['code'] = '1';
        $transaction_money_html = money_format('%i', $transaction_money);
        $error['messages'] = '成功转帐从'.$source_acc_result[1]->account.'到'.$destination_acc_result[1]->account.'金额:'.$transaction_money_html;
      }else{
        // $logger = "(x)資料處理錯誤，請聯絡維護人員處理。";
        // memberlog2db($_SESSION['agent']->account,'withdrawal','error', "$logger");

        $error['code'] = '0';
        $transaction_money_html = money_format('%i', $transaction_money);
        $error['messages'] = '转帐失败从'.$source_acc_result[1]->account.'到'.$destination_acc_result[1]->account.'金额'.$transaction_money_html;
      }

    } else {
      $destination_acc_sql     = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$destination_transferaccount."' AND root_member.status = '2';";
      $destination_acc_result  = runSQLall($destination_acc_sql);
      if ($destination_acc_result[0] == 1) {
        $error['code'] = '2';
        $error['messages'] = $tr['This account has been frozen, you need to unlock the wallet first to operate deposits and withdrawals'];
      } else {
        $error['code'] = '3';
        $tr['Member information query failed'] = '會員資訊查詢失敗。';
        $error['messages'] = $tr['this feature is currently unavailable for member status'];
      }
      
    }

    return($error);
}
// ----------------------------------------------------------------------------
// 代幣目前是否在娛樂城使用中判斷函式 start
// ----------------------------------------------------------------------------

/**
 * @param [type] $gtoken_lock - 代幣目前狀態
 * @return string
 */
function gtoken_ststus($gtoken_lock) {
  // 目前錢包所在哪裡？ NULL 等於沒有鎖定
  if($gtoken_lock == NULL OR $gtoken_lock == '') {
    //代幣錢包未使用
    $gtoken_status = 'unused';
  }else{
    $gtoken_status = 'casino_used';
  }

  return $gtoken_status;
}

// ----------------------------------------------------------------------------
// 代幣目前是否在娛樂城使用中判斷函式 end
// ----------------------------------------------------------------------------
?>
