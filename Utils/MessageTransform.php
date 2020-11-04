<?php

/*
使用方法

$msg = MessageTransform::getInstance();
$mq->notifyMsg('CompanyDeposit', 'aaa', $date);
*/

class MessageTransform
{
    private static $instance = null;

    private function __construct()
    {
        //
    }

    public static function getInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __destruct()
    {
        self::$instance = null;
    }

    /**
     * 通知訊息格式轉換
     *
     * From :
     * OnlinePay - 線上支付看版
     * CompanyDeposit - 公司入款審核
     * TokenWithdrawal - 遊戲幣取款審核
     * CashWithdrawal - 現金取款審核
     * AgentReview - 代理商審核
     * MemberRegister - 會員註冊
     * SiteAnnouncement - 系統公告
     * StationMail - 站內信
     * OtherNotify - 其它通知
     * 
     * @param string $from - 發出通知來源
     * @param string $account - 帳號
     * @param string $date - 時間(Y-m-d H:i:s)
     * @return array
     */
    public function notifyMsg(string $from, string $account, string $date)
    {
        return [
            'from' => $from,
            'account' => $account,
            'date' => $date
        ];
    }
}
