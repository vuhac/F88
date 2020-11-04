<?php
//線上支付 狀態
$select_status[0] = '关闭';
$select_status[1] = '开启';
$select_status[2] = '删除';
$select_status[3] = '<button type="button" class="btn btn-dark">停止支援</button>';


//代號編寫規則
//前面為支付商 後面為支付渠道或是唯一值即可 XXX_aaa
//前面支付商會直接影響到表格顯示名稱
//後面的支付渠道則是後續存入資料庫判斷渠道使用，一個代碼對應一個支付渠道
// 增加一個 $payment_service[ ^\d+$ ]['status'] 變數來控制金流商是否在可選擇列表中
// 狀態值對應 $select_status 的 index => value

//線上支付的代號
$payment_service[0]['status']   =  1;
$payment_service[0]['name']     =  '智付宝';
$payment_service[0]['code']     =  'spgateway';
$payment_service[0]['channel']  =  'spgateway';

$payment_service[1]['status']   =  1;
$payment_service[1]['name']     =  'Ping++ - 银联网关支付';
$payment_service[1]['code']     =  'pingpp_upacppc';
$payment_service[1]['channel']  =  'upacp_pc';

$payment_service[2]['status']   =  1;
$payment_service[2]['name']     =  'Ping++ - 银联手机网关支付';
$payment_service[2]['code']     =  'pingpp_upacpwap';
$payment_service[2]['channel']  =  'upacp_wap';

$payment_service[3]['status']   =  1;
$payment_service[3]['name']     =  'Ping++ - 银联APP支付';
$payment_service[3]['code']     =  'pingpp_upacp';
$payment_service[3]['channel']  =  'upacp';

$payment_service[4]['status']   =  1;
$payment_service[4]['name']     =  'Ping++ - 微信App';
$payment_service[4]['code']     =  'pingpp_wx';
$payment_service[4]['channel']  =  'wx';

$payment_service[5]['status']   =  1;
$payment_service[5]['name']     =  'Ping++ - 微信 WAP 支付';
$payment_service[5]['code']     =  'pingpp_wxwap';
$payment_service[5]['channel']  =  'wx_wap';

$payment_service[6]['status']   =  1;
$payment_service[6]['name']     =  'Ping++ - 支付宝 APP 支付';
$payment_service[6]['code']     =  'pingpp_alipay';
$payment_service[6]['channel']  =  'alipay';

$payment_service[7]['status']   =  1;
$payment_service[7]['name']     =  'Ping++ - 支付宝手机网页支付';
$payment_service[7]['code']     =  'pingpp_alipaywap';
$payment_service[7]['channel']  =  'alipay_wap';

$payment_service[8]['status']   =  1;
$payment_service[8]['name']     =  'Ping++ - 支付宝电脑网站支付';
$payment_service[8]['code']     =  'pingpp_alipaypcdirect';
$payment_service[8]['channel']  =  'alipay_pc_direct';

$payment_service[9]['status']     =  0;
$payment_service[9]['name']       =  '天工 - 微信支付';
$payment_service[9]['code']       =  'teegon_wxpaynative_pinganpay';
$payment_service[9]['channel']    =  'wxpaynative_pinganpay';

$payment_service[10]['status']     =  0;
$payment_service[10]['name']       =  '天工 - 支付宝';
$payment_service[10]['code']       =  'teegon_alipay_pinganpay';
$payment_service[10]['channel']    =  'alipay_pinganpay';

$payment_service[11]['status']     =  0;
$payment_service[11]['name']       =  '天工 - 京东支付';
$payment_service[11]['code']       =  'teegon_jdh5_pinganpay';
$payment_service[11]['channel']    =  'jdh5_pinganpay';

$payment_service[12]['status']     =  0;
$payment_service[12]['name']       =  '天下 - 网关支付';
$payment_service[12]['code']       =  'tianxia';
$payment_service[12]['channel']    =  '';

// 新碼 - 掃碼支付
$payment_service[13]['status']     =  0;
$payment_service[13]['name']       =  '新码 - 微信支付';
$payment_service[13]['code']       =  'xinma_wxpaynative_pinganpay';
$payment_service[13]['channel']    =  '10';

$payment_service[14]['status']     =  0;
$payment_service[14]['name']       =  '新码 - 支付宝';
$payment_service[14]['code']       =  'xinma_alipay_pinganpay';
$payment_service[14]['channel']    =  '20';

$payment_service[15]['status']     =  1;
$payment_service[15]['name']       =  '新码 - 京东支付';
$payment_service[15]['code']       =  'xinma_jdh5_pinganpay';
$payment_service[15]['channel']    =  '40';

$payment_service[16]['status']     =  0;
$payment_service[16]['name']       =  '新码 - QQ 支付';
$payment_service[16]['code']       =  'xinma_QQ_pinganpay';
$payment_service[16]['channel']    =  '50';

$payment_service[17]['status']     =  0;
$payment_service[17]['name']       =  '新码 - 银联支付';
$payment_service[17]['code']       =  'xinma_unionpay_pinganpay';
$payment_service[17]['channel']    =  '70';

// 新碼 WAP
$payment_service[18] = [
    'status'    =>  0,
    'name'      =>  '新码 WAP - 微信',
    'code'      =>  'xinma_wxwap',
    'channel'   =>  61
];

$payment_service[19] = [
    'status'    =>  0,
    'name'      =>  '新码 WAP - 支付宝',
    'code'      =>  'xinma_alipaywap',
    'channel'   =>  62
];

$payment_service[20] = [
    'status'    =>  0,
    'name'      =>  '新码 WAP - QQ 钱包',
    'code'      =>  'xinma_QQwap',
    'channel'   =>  63
];

$payment_service[21] = [
    'status'    =>  0,
    'name'      =>  '新码 WAP - 京东钱包',
    'code'      =>  'xinma_jdh5wap',
    'channel'   =>  64
];

// 新碼網關：银行序号与对应简码未整理
$payment_service[22] = [
    'status'    =>  0,
    'name'      =>  '新码银联',
    'code'      =>  'xinma_unionpay',
    'channel'   =>  30
];

// 环讯 人民币借记卡
$payment_service[23] = [
    'status'    =>  0,
    'name'      =>  '环讯 - 人民币借记卡',
    'code'      =>  'ips_unionpay',
    'channel'   =>  01
];

// 环讯 信用卡支付
$payment_service[24] = [
    'status'    =>  0,
    'name'      =>  '环讯 - 信用卡支付',
    'code'      =>  'ips_creditpay',
    'channel'   =>  128
];

// 环讯 IPS帐户支付
$payment_service[25] = [
    'status'    =>  0,
    'name'      =>  '环讯 - IPS帐户支付',
    'code'      =>  'ips_accountpay',
    'channel'   =>  04
];

// 环讯 电话支付
$payment_service[26] = [
    'status'    =>  0,
    'name'      =>  '环讯 - 电话支付',
    'code'      =>  'ips_telepay',
    'channel'   =>  16
];

// 环讯 手机支付
$payment_service[27] = [
    'status'    =>  0,
    'name'      =>  '环讯 - 手机支付',
    'code'      =>  'ips_mobilepay',
    'channel'   =>  32
];

// 环讯 手机语音支付
$payment_service[28] = [
    'status'    =>  0,
    'name'      =>  '环讯 - 手机语音支付',
    'code'      =>  'ips_mobile_voicepay',
    'channel'   =>  1024
];

//表格名稱
$payment_form_name['spgateway']['name']                 = '智付宝';
$payment_form_name['spgateway']['payname']              = '支付名称';
$payment_form_name['spgateway']['payment_service']      = '支付商';
$payment_form_name['spgateway']['merchantid']           = '商店代号';
$payment_form_name['spgateway']['merchantname']         = '商店名称';
$payment_form_name['spgateway']['hashiv']               = '商户HashIV';
$payment_form_name['spgateway']['hashkey']              = '商户HashKey';
$payment_form_name['spgateway']['gradename_id']         = '会员等级';
$payment_form_name['spgateway']['cashfeerate']          = '手续费(%)';
$payment_form_name['spgateway']['status']               = '状态';
$payment_form_name['spgateway']['singledepositlimits']  = '单次存款上限';
$payment_form_name['spgateway']['depositlimits']        = '累积总存款上限';
$payment_form_name['spgateway']['notes']                = '其他线上支付资讯';
$payment_form_name['spgateway']['receiptaccount']       = '转出收款帐号资讯';
$payment_form_name['spgateway']['receiptbank']          = '转出收款银行资讯';
$payment_form_name['spgateway']['receiptname']          = '转出款帐号名称';
$payment_form_name['spgateway']['effectiveseconds']     = '交易有效秒数';


$payment_form_name['pingpp']['name']                 = 'Ping++';
$payment_form_name['pingpp']['payname']              = '支付名称';
$payment_form_name['pingpp']['payment_service']      = '支付商';
$payment_form_name['pingpp']['merchantid']           = 'APP ID';
$payment_form_name['pingpp']['merchantname']         = 'APP KEY';
$payment_form_name['pingpp']['hashiv']               = 'Ping++ Public Key';
$payment_form_name['pingpp']['hashkey']              = 'Private Key';
$payment_form_name['pingpp']['gradename_id']         = '会员等级';
$payment_form_name['pingpp']['cashfeerate']          = '手续费(%)';
$payment_form_name['pingpp']['status']               = '状态';
$payment_form_name['pingpp']['singledepositlimits']  = '单次存款上限';
$payment_form_name['pingpp']['depositlimits']        = '累积总存款上限';
$payment_form_name['pingpp']['notes']                = '其他线上支付资讯';
$payment_form_name['pingpp']['receiptaccount']       = '转出收款帐号资讯';
$payment_form_name['pingpp']['receiptbank']          = '转出收款银行资讯';
$payment_form_name['pingpp']['receiptname']          = '转出款帐号名称';
$payment_form_name['pingpp']['effectiveseconds']     = '交易有效秒数';


$payment_form_name['teegon']['name']                 = '天工';
$payment_form_name['teegon']['payname']              = '支付名称';
$payment_form_name['teegon']['payment_service']      = '支付商';
$payment_form_name['teegon']['merchantid']           = 'APP KEY';
$payment_form_name['teegon']['merchantname']         = 'APP SECRET';
$payment_form_name['teegon']['hashiv']               = '保留栏位';
$payment_form_name['teegon']['hashkey']              = '保留栏位';
$payment_form_name['teegon']['gradename_id']         = '会员等级';
$payment_form_name['teegon']['cashfeerate']          = '手续费(%)';
$payment_form_name['teegon']['status']               = '状态';
$payment_form_name['teegon']['singledepositlimits']  = '单次存款上限';
$payment_form_name['teegon']['depositlimits']        = '累积总存款上限';
$payment_form_name['teegon']['notes']                = '其他线上支付资讯';
$payment_form_name['teegon']['receiptaccount']       = '转出收款帐号资讯';
$payment_form_name['teegon']['receiptbank']          = '转出收款银行资讯';
$payment_form_name['teegon']['receiptname']          = '转出款帐号名称';
$payment_form_name['teegon']['effectiveseconds']     = '交易有效秒数';

$payment_form_name['tianxia']['name']                 = '天下 - 网关支付';
$payment_form_name['tianxia']['payname']              = '支付名称';
$payment_form_name['tianxia']['payment_service']      = '支付商';
$payment_form_name['tianxia']['merchantid']           = 'merCode';
$payment_form_name['tianxia']['merchantname']         = 'merKey';
$payment_form_name['tianxia']['hashiv']               = '保留栏位';
$payment_form_name['tianxia']['hashkey']              = '保留栏位';
$payment_form_name['tianxia']['gradename_id']         = '会员等级';
$payment_form_name['tianxia']['cashfeerate']          = '手续费(%)';
$payment_form_name['tianxia']['status']               = '状态';
$payment_form_name['tianxia']['singledepositlimits']  = '单次存款上限';
$payment_form_name['tianxia']['depositlimits']        = '累积总存款上限';
$payment_form_name['tianxia']['notes']                = '其他线上支付资讯';
$payment_form_name['tianxia']['receiptaccount']       = '转出收款帐号资讯';
$payment_form_name['tianxia']['receiptbank']          = '转出收款银行资讯';
$payment_form_name['tianxia']['receiptname']          = '转出款帐号名称';
$payment_form_name['tianxia']['effectiveseconds']     = '交易有效秒数';

$payment_form_name['xinma']['name']                 = '新码';
$payment_form_name['xinma']['payname']              = '支付名称';
$payment_form_name['xinma']['payment_service']      = '支付商';
$payment_form_name['xinma']['merchantid']           = '商户号';
$payment_form_name['xinma']['merchantname']         = '商户密钥';
$payment_form_name['xinma']['hashiv']               = '保留栏位';
$payment_form_name['xinma']['hashkey']              = '保留栏位';
$payment_form_name['xinma']['gradename_id']         = '会员等级';
$payment_form_name['xinma']['cashfeerate']          = '手续费(%)';
$payment_form_name['xinma']['status']               = '状态';
$payment_form_name['xinma']['singledepositlimits']  = '单次存款上限';
$payment_form_name['xinma']['depositlimits']        = '累积总存款上限';
$payment_form_name['xinma']['notes']                = '其他线上支付资讯';
$payment_form_name['xinma']['receiptaccount']       = '转出收款帐号资讯';
$payment_form_name['xinma']['receiptbank']          = '转出收款银行资讯';
$payment_form_name['xinma']['receiptname']          = '转出款帐号名称';
$payment_form_name['xinma']['effectiveseconds']     = '交易有效秒数';

$payment_form_name['ips']['name']                 = '环讯';
$payment_form_name['ips']['payname']              = '支付名称';
$payment_form_name['ips']['payment_service']      = '支付商';
$payment_form_name['ips']['merchantid']           = '商户号';
$payment_form_name['ips']['merchantname']         = '商户密钥';
$payment_form_name['ips']['hashiv']               = '保留栏位';
$payment_form_name['ips']['hashkey']              = '保留栏位';
$payment_form_name['ips']['gradename_id']         = '会员等级';
$payment_form_name['ips']['cashfeerate']          = '手续费(%)';
$payment_form_name['ips']['status']               = '状态';
$payment_form_name['ips']['singledepositlimits']  = '单次存款上限';
$payment_form_name['ips']['depositlimits']        = '累积总存款上限';
$payment_form_name['ips']['notes']                = '其他线上支付资讯';
$payment_form_name['ips']['receiptaccount']       = '转出收款帐号资讯';
$payment_form_name['ips']['receiptbank']          = '转出收款银行资讯';
$payment_form_name['ips']['receiptname']          = '转出款帐号名称';
$payment_form_name['ips']['effectiveseconds']     = '交易有效秒数';

// 以下为范例；设计问题：必填栏位中的多馀栏位勿留白，避免 ajax 送出时的错误判断
$payment_form_name['custom2']['name']              = '支付2';
$payment_form_name['custom2']['payname']              = 'custom2 支付名称';
$payment_form_name['custom2']['payment_service']      = 'custom2 支付商';
$payment_form_name['custom2']['merchantid']           = 'custom2 商店代号';
$payment_form_name['custom2']['merchantname']         = 'custom2 商店名称';
$payment_form_name['custom2']['hashiv']               = 'custom2 商户HashIV';
$payment_form_name['custom2']['hashkey']              = 'custom2 商户HashKey';
$payment_form_name['custom2']['gradename_id']         = 'custom2 会员等级';
$payment_form_name['custom2']['cashfeerate']          = 'custom2 手续费(%)';
$payment_form_name['custom2']['status']               = 'custom2状态';
$payment_form_name['custom2']['singledepositlimits']  = '单次存款上限';
$payment_form_name['custom2']['depositlimits']        = '累积总存款上限';
$payment_form_name['custom2']['notes']                = '其他线上支付资讯';
$payment_form_name['custom2']['receiptaccount']       = '转出收款帐号资讯';
$payment_form_name['custom2']['receiptbank']          = '转出收款银行资讯';
$payment_form_name['custom2']['receiptname']          = '转出款帐号名称';
$payment_form_name['custom2']['effectiveseconds']     = '交易有效秒数';

$payment_service = array_filter($payment_service, function ($value) {
    return isset($value['status']) && $value['status'] == 1;
});
?>
