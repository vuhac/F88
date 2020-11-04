<?php
$colname_arr = [
  'allow_login_passwordchg' => $tr['member login force to change pwd switch'],//'会员登入强制变更密码开关',
  'register_agenthelp_isopen' => $tr['agent assist member to register switch'],//'代理协助注册功能是否开启',
  'agent_transfer_isopen' => $tr['agent transfer switch'],//'代理转帐功能是否开启',
  'entire_website_deposit_close' => $tr['website deposit switch'],//'全站入款功能开关',
  'entire_website_withdrawal_close' => $tr['website withdraw switch'],//'全站取款功能开关',
  'registerfingerprinting_member_numberoftimes' => $tr['number of time to limit member register fingerprint code'],//'会员注册指纹码限制次数',
  'registerip_member_numberoftimes' => $tr['number of time to limit member register IP'],//'会员注册IP限制次数',
  'registerfingerprinting_agent_numberoftimes' => $tr['agent lead to register fingerprint limit times'],//'代理引导注册指纹码限制次数',
  'registerip_agent_numberoftimes' => $tr['agent lead to register IP limit times'],//'代理引导注册IP限制次数',
  'agency_registration_gcash' => $tr['cost to be agent'],//'申请成为代理商的费用',
  'registrationmoney_member_grade' => $tr['Register for the bonus to use the membership level setting'],//'注册送彩金使用会员等级设定',
  'member_register_review' => $tr['member_register_review_title'],//會員註冊 自动 / 手动 审核设定
  'agent_review_switch' => $tr['Agent Application Auto / Manual Audit Settings'],//'代理申请 自动 / 手动 审核设定',
  'becomeagent_datelimit' => $tr['Agent registration downline time limit (minutes)'],//'代理注册下线时间限制(分钟)',
  'becomemember_datelimit' => $tr['New member use full feature time limit (minutes)'],//'新会员使用完整功能时间限制(分钟)',
  'withdrawal_review_switch' => $tr['Withdrawal Request Auto / Manual Review Settings'],//'取款申请 自动 / 手动 审核设定',
  'onlinepay_review_switch' => $tr['Online payment Request Auto / Manual Review Settings'],//'線上入支付入款 自动 / 手动 审核设定',
  'front_payment' => $tr['front_payment'] ?? 'Front Payment', // 前台線上支付入款,
  'hide_gcash_mode' => $tr['hide_gcash_mode'],//'隐藏现金帐户',
  'member_deposit_currency' => $tr['member deposit account setting'],//'会员入款帐户设定',
  'member_deposit_currency_isshow' => $tr['display deposit account'],//'入款目的帐户显示',
  'agentbonus_calculation_isopen' => $tr['display agent franchise fee at frontstage'],//'前台代理加盟金显示',
  'national_agent_isopen' => $tr['Agency function switch setting'],//'全民代理功能开关',
  'agent_review_isopen' => $tr['Agent review application function switch setting'],//'代理审核申请功能开关设定',
  'member_register_isopen' => $tr['Member registration function switch setting'],//'会员注册注册功能开关设定',
  'commission_1_rate' => $tr['Guaranteed four layers of dividends - the first layer upstream'],//'保证四层分红-上游第一层',
  'commission_2_rate' => $tr['Guaranteed four layers of dividends - the second layer upstream'],//'保证四层分红-上游第二层',
  'commission_3_rate' => $tr['Guaranteed four layers of dividends - the third layer upstream'],//'保证四层分红-上游第三层',
  'commission_4_rate' => $tr['Guaranteed four layers of dividends - the fourth layer upstream'],//'保证四层分红-上游第四层',
  'commission_root_rate' => $tr['Guarantee four layers of dividends - company costs'],//'保证四层分红-公司成本',
  'income_commission_reviewperiod_days' => $tr['Income 1: Agent joining gold review period'],//'收入1:代理商加盟金审阅期',
  'amountperformance' => $tr['Income 2: Performance'],//'收入2:业绩量',
  'sale_bonus_rate' => $tr['Income 2: Business bonus bonus ratio'],//'收入2:营业奖金分红比例',
  'stats_bonus_days' => $tr['Income 2: Business Bonus Statistics Cycle'],//'收入2:营业奖金统计周期',
  'stats_comission_days' => $tr['Income 3: Company profit bonus statistics cycle'],//'收入3:公司营利奖金统计周期',

  'customer_service_online_weblink' => $tr['online Customer service information'],//'网页线上客服',
  // 'customer_service_qq' => $tr['Customer service information qq'],//'客服资讯QQ',
  'customer_service_email' => $tr['Customer service information email'],//'客服资讯Email',
  'customer_service_mobile_tel' => $tr['Customer service information Mobile Phone'],//'客服资讯 Mobile Phone',
  // 'customer_service_wechat_id' => $tr['Customer service information WeChat ID'],//'客服资讯 WeChat ID',
  // 'customer_service_wechat_qrcode' => $tr['Customer service information WeChat QRCode'],//'客服资讯 WeChat QRCode',
  // 'ios_appdownload_qrcode' => $tr['download ios qr code'],//'IOS App 下载 QRCode',
  // 'android_appdownload_qrcode' => $tr['download android qr code'],//'Android App 下载 QRCode',
  // 'quick_deposit_qrcode' => $tr['Quick deposit QRCode'],//'快速入款 QRCode',
  // 20200423
  'customer_service_customization_setting_1'=> $tr['sns1'], // line、zalo
  'customer_service_qrcode_1'=>'QRCode 1',
  'customer_service_customization_setting_2'=> $tr['sns2'], // line、zalo
  'customer_service_qrcode_2'=>'QRCode 2',

  'name' => $tr['agent review name'],//'姓名',
  'mobile' => $tr['Cell Phone'],//'手机',
  'mail' => $tr['Email'],//'信箱',
  'sex' => $tr['Gender'],//'性别',
  'birthday' => $tr['Birth'],//'生日',
  'wechat' => $tr['sns1'],//'微信',
  'qq' => $tr['sns2'],
  'bank_information' => $tr['Bank Information'],//银行资讯',
  'linkcode' => $tr['Invitation code'],//'邀请码',
  'realtime_reward_switch' => $tr['Shí shí bonus enabled or disabled'],//'时时反水开关设定',
  'realtime_reward_payout_sw' => $tr['Shí shí bonus setting of payout'],//'时时反水打到彩金池设定',
  'realtime_reward_bonus_type' => $tr['Shí shí bonus payout currency'],//'时时反水至彩金池币别',
  'realtime_reward_bonus_status' => $tr['Shí shí bonus payout status'],//'时时反水彩金状态',
  'realtime_reward_audit_name' => $tr['Shí shí bonus audit name'],//'时时反水彩金稽核名称',
  'realtime_reward_audit_type' => $tr['Shí shí bonus audit mode setting'],//'时时反水稽核方式设定',
  'realtime_reward_audit_amount_ratio' => $tr['The value of the bonus audit amount/audit multiple'],//'时时反水稽核金额/稽核倍数之值',
  'realtime_reward_time_frequency' => $tr['realtime_reward_time_frequency'],

  // created by Damocles at 2019/11/08
  'bonus_commision_divdendreference' => $tr['Radiation tissue bonus calculation'], // 放射線組織獎金計算
  'bonus_commision_profit' => $tr['Operating Profit Bonus'], // 放射線組織獎金計算 - 營運利潤獎金
  'radiationbonus_organization' => $tr['Agent Franchise Fee'], // 代理加盟金
  // end create of Damocles

  // 存款投注傭金 20191113
  'depositbet_calculation' => $tr['Deposit betting commission'],//'存款投注佣金'
  'custom_sns_rservice_1' => $tr['sns1'],//'社群服務1'
  'custom_sns_rservice_2' => $tr['sns2']//'社群服務2'
];
// 活動說明
$instructions_arr = [
  'agency_registration_gcash' => $tr['After the commission fee is activated, the agent will use the "Assist Account Opening" function to add an offline agent, and a fee will be charged'].'</br>'.$tr['Cannot add agency with invitation code'].'</br>'.$tr['Agent invitation codes that have been added and activated cannot be registered'],
  'becomeagent_datelimit' => $tr['How many minutes does it take to become an agent to register downline?'],//'成为代理后需要经过多少分钟才可注册下线',
  'becomemember_datelimit' => $tr['How many minutes does it take for newly registered members to use the full casino feature?'],//'新注册会员需要经过多少分钟才可使用完整娱乐城功能',
  'commission_1_rate' => $tr['Those who fail to achieve results are not included 1'],//'未达业绩者不列入分红计算，往上层保留层数，直到公司帐号',
  'commission_2_rate' => $tr['Those who fail to achieve results are not included 2'],//'未达业绩者不列入分红计算，往上层保留层数，直到公司帐号',
  'commission_3_rate' => $tr['Those who fail to achieve results are not included 3'],//'未达业绩者不列入分红计算，往上层保留层数，直到公司帐号',
  'commission_4_rate' => $tr['Those who fail to achieve results are not included 4'],//'未达业绩者不列入分红计算，往上层保留层数，直到公司帐号',
  'commission_root_rate' => $tr['Sum of the upstream four-layer percentage and the companys cost percentage must be 1'],//'上游四层百分比与公司成本百分比加总后需为1',
  'income_commission_reviewperiod_days' => $tr['how long to be an agent(day)'],//'(日)经过多久后才发予代理商',
  'amountperformance' => $tr['Each business site needs to achieve a performance amount before it can participate in dividends.'],//'每个营业点需要达成业绩量，才可参与分红',
  'sale_bonus_rate' => $tr['The profit from the bet amount is distributed, and the total amount of profit should be avoided to exceed 3% of the profit.'],//'投注量提拨的利润分红，与反水合计须避免超过利润3%。',
  'stats_bonus_days' => $tr['et date 1'],//'美东时间(日)',
  'stats_comission_days' => $tr['et date 2'],//'美东时间(日)',

  'customer_service_online_weblink' => $tr['Link to front page'],//'前端网页的连结',
  // 'customer_service_qq' => $tr['The floating frame and contact information of the front end will be presented 1'],//'前端的浮动框及联络资讯会呈现',
  'customer_service_email' => $tr['The floating frame and contact information of the front end will be presented 2'],//'前端的浮动框及联络资讯会呈现',
  'customer_service_mobile_tel' => $tr['contact'],//'联络电话资讯',
  // 'customer_service_wechat_id' => $tr['wechat number'],//'微信号码',
  // 'customer_service_wechat_qrcode' => 'WeChat QRCode',
  // 'ios_appdownload_qrcode' => $tr['download ios qr code'],//'IOS App Download QRCode',
  // 'android_appdownload_qrcode' => $tr['download android qr code'],//'Android App Download QRCode',
  // 'quick_deposit_qrcode' => $tr['Quick deposit QRCode'],//'Quick Deposit QRCode',
  // 20200423
  'customer_service_customization_setting_1'=> '服务名称1',
  'customer_service_qrcode_1'=>'QRCode 1',
  'customer_service_customization_setting_2'=> '服务名称2', // line、zalo
  'customer_service_qrcode_2'=>'QRCode 2',

  'member_register_name_show' => $tr['Whether the member register name is displayed'],//'会员注册真实姓名是否显示',
  'member_register_name_must' => $tr['Whether the member register name is required'],//'会员注册真实姓名是否必填',
  'member_register_mobile_show' => $tr['Whether the member registration phone is displayed'],//'会员注册手机是否显示',
  'member_register_mobile_must' => $tr['Whether the member register phone is required'],//'会员注册手机是否必填',
  'member_register_mobile_unique' => $tr['member register phone unique'],//'会员注册手机是否唯一',
  'member_register_mail_show' => $tr['Whether the member registration email is displayed'],//'会员注册信箱是否显示',
  'member_register_mail_must' => $tr['member register email unique'],//'会员注册信箱是否必填',
  'member_register_mail_unique' => $tr['member register email unique'],//'会员注册信箱是否唯一',
  'member_register_sex_show' => $tr['Whether the member registration gender is displayed'],//'会员注册性别是否显示',
  'member_register_sex_must' => $tr['Whether the member registration gender is displayed'],//'会员注册性别是否必填',
  'member_register_birthday_show' => $tr['Whether the member register birthday is displayed'],//'会员注册生日是否显示',
  'member_register_birthday_must' => $tr['Whether the member register birthday is required'],//'会员注册生日是否必填',
  'member_register_qq_show' => $tr['Whether the member register qq is displayed'],//'会员注册QQ是否显示',
  'member_register_qq_must' => $tr['Whether the member register qq is required'],//'会员注册QQ是否必填',
  'member_register_qq_unique' => $tr['member register qq unique'],//'会员注册QQ是否唯一',
  'member_register_wechat_show' => $tr['Whether the member register wechat is displayed'],//'会员注册wechat是否显示',
  'member_register_wechat_must' => $tr['Whether the member register wechat is required'],//'会员注册wechat是否必填',
  'member_register_wechat_unique' => $tr['member register wechat unique'],//'会员注册wechat是否唯一',
  'member_register_linkcode_must' => $tr['Whether the member registration invitation code is required'],//'会员注册邀请码是否必填',

  'agent_register_name_show' => $tr['Whether the agent registration name is displayed'],//'代理注册姓名是否显示',
  'agent_register_name_must' => $tr['Whether the agent register name is required'],//'代理注册姓名是否必填',
  'agent_register_mobile_show' => $tr['Whether the agent registration phone is displayed'],//'代理注册手机是否显示',
  'agent_register_mobile_must' => $tr['Whether the agent register phone is required'],//'代理注册手机是否必填',
  'agent_register_mobile_unique' => $tr['agent register phone unique'],//'代理注册手机是否唯一',
  'agent_register_mail_show' => $tr['Whether the agent registration email is displayed'],//'代理注册信箱是否显示',
  'agent_register_mail_must' => $tr['Whether the agent register email is required'],//'代理注册信箱是否必填',
  'agent_register_mail_unique' => $tr['agent register email unique'],//'代理注册信箱是否唯一',
  'agent_register_sex_show' => $tr['Whether the agent registration gender is displayed'],//'代理注册性别是否显示',
  'agent_register_sex_must' => $tr['Whether the agent register gender is required'],//'代理注册性别是否必填',
  'agent_register_birthday_show' => $tr['Whether the agent registration birthday is displayed'],//'代理注册生日是否显示',
  'agent_register_birthday_must' => $tr['Whether the agent register birthday is required'],//'代理注册生日是否必填',
  'agent_register_qq_show' => $tr['Whether the agent registration qq is displayed'],//'代理注册QQ是否显示',
  'agent_register_qq_must' => $tr['Whether the agent register qq is required'],//'代理注册QQ是否必填',
  'agent_register_qq_unique' => $tr['agent register qq unique'],//'代理注册QQ是否唯一',
  'agent_register_wechat_show' => $tr['Whether the agent registration wechat is displayed'],//'代理注册wechat是否显示',
  'agent_register_wechat_must' => $tr['Whether the agent register wechat is required'],//'代理注册wechat是否必填',
  'agent_register_wechat_unique' => $tr['agent register wechat unique'],//'代理注册wechat是否唯一',
  'agent_bank_information_show' => $tr['Whether the agent registration bank information is displayed'],//'代理注册银行资讯是否显示',
  'agent_bank_information_must' => $tr['Whether the agent registration bank information is required']//'代理注册银行资讯是否必填'
];

// 全站開關
$entire_website_close = [
	'entire_website_deposit_close' => [
    'companydeposit_switch',
    'companydeposit_offline_desc'
  ],
	'entire_website_withdrawal_close' => [
    'withdrawalapply_switch',
    'withdrawalapply_offline_desc'
  ]
];
$entire_website_close_textarea = [
  'companydeposit_offline_desc',
  'withdrawalapply_offline_desc'
];

// 會員及代理註冊限制.申請代理費用
$limit_count_fee = [
  'registerfingerprinting_member_numberoftimes',
  'registerip_member_numberoftimes',
  'becomemember_datelimit',
  'registerfingerprinting_agent_numberoftimes',
  'registerip_agent_numberoftimes',
  'becomeagent_datelimit',
  'agency_registration_gcash'
];

// all checkbox
$deposit_currency_isshow_option = [
  'off' => $tr['do not show'],//'不显示',
  'on' => $tr['display']//'显示'
];
$review_option = [
  'manual' => $tr['Manual'],//'手动',
  'automatic' => $tr['auto']//'自动'
];
$review_option2 = [
  'off' => $tr['auto'],//'自动'
  'on' => $tr['Manual']//'手动',
];
$currency = [
  'gtoken' => $tr['Gtoken'],//'游戏币',
  'gcash' => $tr['Franchise'] //'现金'
];
$isopen_option = [
  'off' => $tr['off'], //關閉
  'on' => $tr['open'] //開啟
];
$remarks_text = '新加入代理商需經過多少時間才能開始註冊下線，以通過 / 成為代理商身分時間起算';
$bonus_status_option= [
    '0' => $tr['Cancel'],//'取消',
    '1' => $tr['Can receive'],//'可领取',
    '2' => $tr['time out'] //'暂停',
];
$audit_name_option = [
    'freeaudit'     => $tr['freeaudit'],//'免稽核',
    'depositaudit'  => $tr['Deposit audit'],//'存款稽核',
    'shippingaudit' => $tr['Preferential deposit audit']//'优惠稽核',
];
$audit_type_option = [
    'audit_amount' => $tr['audit amount'],//'稽核金额',
    'audit_ratio'  => $tr['audit multiple']//'稽核倍数',
];
$time_frequency_option = [
    // '10'  => '10分钟',
    // '20'  => '20分钟',
    // '30'  => '30分钟',
    '60'  => '1'.$tr['hours'],
    // '120' => '二小时',
    // '240' => '四小时',
];



// 會員及代理註冊設定
$register_table_setting = [
  'name' => [
    'member_register_name_show',
    'member_register_name_must'
  ],
  'mobile' => [
    'member_register_mobile_show',
    'member_register_mobile_must',
    'member_register_mobile_unique'
  ],
  'mail' => [
    'member_register_mail_show',
    'member_register_mail_must',
    'member_register_mail_unique'
  ],
  'sex' => [
    'member_register_sex_show',
    'member_register_sex_must'
  ],
  'birthday' => [
    'member_register_birthday_show',
    'member_register_birthday_must'
  ],
  'wechat' => [
    'member_register_wechat_show',
    'member_register_wechat_must',
    'member_register_wechat_unique'
  ],
  'qq' => [
    'member_register_qq_show',
    'member_register_qq_must',
    'member_register_qq_unique'
  ],
  'linkcode' => [
    'member_register_linkcode_must'
  ]
];
$agent_register_table_setting = [
  'name' => [
    'agent_register_name_show',
    'agent_register_name_must'
  ],
  'mobile' => [
    'agent_register_mobile_show',
    'agent_register_mobile_must',
    'agent_register_mobile_unique'
  ],
  'mail' => [
    'agent_register_mail_show',
    'agent_register_mail_must',
    'agent_register_mail_unique'
  ],
  'sex' => [
    'agent_register_sex_show',
    'agent_register_sex_must'
  ],
  'birthday' => [
    'agent_register_birthday_show',
    'agent_register_birthday_must'
  ],
  'wechat' => [
    'agent_register_wechat_show',
    'agent_register_wechat_must',
    'agent_register_wechat_unique'
  ],
  'qq' => [
    'agent_register_qq_show',
    'agent_register_qq_must',
    'agent_register_qq_unique'
  ],
  'bank_information' => [
    'agent_bank_information_show',
    'agent_bank_information_must'
  ]
];

// 放射線組織-獎勵分紅辦法
$reward_table_setting = [
  'commission_1_rate',
  'commission_2_rate',
  'commission_3_rate',
  'commission_4_rate',
  'commission_root_rate',
  'income_commission_reviewperiod_days',
  'amountperformance',
  'sale_bonus_rate',
  'stats_bonus_days',
  'stats_comission_days'
];

// 客服資訊
$customer_service_setting = [
  'customer_service_online_weblink',
  // 'customer_service_qq',
  'customer_service_email',
  'customer_service_mobile_tel',
  // 'customer_service_wechat_id',
];

// 社群服务名稱
$custom_sns_rservice_setting = [
  'custom_sns_rservice_1',
  'custom_sns_rservice_2',
];
// -------------------
// 20200424
// 由user自行彈性設定
$customer_service_custom_setting_1 =[
  'customer_service_customization_setting_1' // 客服资讯名稱
];

$customer_service_custom_setting_2=[
  'customer_service_customization_setting_2'
];
$qr_code_service_1 = [
  'customer_service_qrcode_1', // qrcode
];
$qr_code_service_2 = [
  'customer_service_qrcode_2'
];
// -------------------

// qrcode
// $qrcode = [
  // 'customer_service_wechat_qrcode',
  // 'ios_appdownload_qrcode',
  // 'android_appdownload_qrcode',
  // 'quick_deposit_qrcode'
// ];

// 时时反水更新時，如果為輸入欄位，則顯示更新成功
$realtime_reward_number_setting = [
  'realtime_reward_audit_amount_ratio',
];

// 會員端設定input數值上下限
$protalsetting_input_upper_lower_limits = (object)[
  'becomemember_datelimit' => (object)[
    'upper' => '999999',
    'lower' => '0',
    'type' => 'integer'
  ],
  'becomeagent_datelimit' => (object)[
    'upper' => '999999',
    'lower' => '0',
    'type' => 'integer'
  ],

  'registerfingerprinting_member_numberoftimes' => (object)[
    'upper' => '99',
    'lower' => '0',
    'type' => 'integer'
  ],
  'registerip_member_numberoftimes' => (object)[
    'upper' => '99',
    'lower' => '0',
    'type' => 'integer'
  ],
  'registerfingerprinting_agent_numberoftimes' => (object)[
    'upper' => '9999',
    'lower' => '0',
    'type' => 'integer'
  ],
  'registerip_agent_numberoftimes' => (object)[
    'upper' => '9999',
    'lower' => '0',
    'type' => 'integer'
  ],
  'agency_registration_gcash' => (object)[
    'upper' => '999999',
    'lower' => '0',
    'type' => 'integer'
  ],
  'commission_1_rate' => (object)[
    'upper' => '100',
    'lower' => '0',
    'type' => 'float'
  ],
  'commission_2_rate' => (object)[
    'upper' => '100',
    'lower' => '0',
    'type' => 'float'
  ],
  'commission_3_rate' => (object)[
    'upper' => '100',
    'lower' => '0',
    'type' => 'float'
  ],
  'commission_4_rate' => (object)[
    'upper' => '100',
    'lower' => '0',
    'type' => 'float'
  ],
  'commission_root_rate' => (object)[
    'upper' => '100',
    'lower' => '0',
    'type' => 'float'
  ],
  'income_commission_reviewperiod_days' => (object)[
    'upper' => '30',
    'lower' => '0',
    'type' => 'integer'
  ],
  'amountperformance' => (object)[
    'upper' => '99999999',
    'lower' => '0',
    'type' => 'integer'
  ],
  'sale_bonus_rate' => (object)[
    'upper' => '3',
    'lower' => '0',
    'type' => 'float'
  ],
  'stats_bonus_days' => (object)[
    'upper' => '7',
    'lower' => '0',
    'type' => 'integer'
  ],
  'stats_comission_days' => (object)[
    'upper' => '30',
    'lower' => '0',
    'type' => 'integer'
  ],
  'realtime_reward_audit_amount_ratio' => (object)[
    'upper' => '99999999',
    'lower' => '0',
    'type' => 'float'
  ]


];

function get_all_member_grade()
{
  $member_grade = [];

  $sql = "SELECT id, gradename FROM root_member_grade ORDER BY id";
  $result = runSQLall($sql);

  if (empty($result[0])) {
    return array('status'=>false, 'data'=>'会员等级查询失败');
  }

  unset($result[0]);
  foreach ($result as $key => $value) {
    $member_grade[$value->id] = $value->gradename;
  }

  return array('status' => true, 'result' => $member_grade);
}

function get_checkbox_switch_html($id, $ischecked)
{
    $html = <<<HTML
    <div class="col-12 material-switch pull-left">
        <input id="{$id}" name="{$id}" class="checkbox_switch" value="{$id}" type="checkbox" {$ischecked}/>
        <label for="{$id}" class="label-success"></label>
    </div>
    HTML;

    return $html;
}

function get_input_html($id, $placeholder, $custom_submit_class=null)
{
    $btn_class = $custom_submit_class ?? 'input_btn';
    // 储存设定
    global $tr;
    $html = <<<HTML
        <div class="form-inline">
            <input type="text" class="form-control mr-2" id="{$id}" placeholder="{$tr['current setting']}{$placeholder}">
            <button class="btn btn-default {$btn_class}" type="submit" id="{$id}_btn">{$tr['Save']}</button>
        </div>
    HTML;

    return $html;
}

function get_checkbox_html($id, $value, $ischecked, $option_text)
{
  if ($value == 'gcash') {
    $html = '
    <label class="radio-inline">
      <input type="radio" class="review_currency_radio" name="'.$id.'" id="'.$id.'" value="'.$value.'" '.$ischecked.' style = "display:none"> '.$option_text=''.'
    </label>
    ';

   return $html;
} else {
  $html = '
  <label class="radio-inline">
    <input type="radio" class="review_currency_radio" name="'.$id.'" id="'.$id.'" value="'.$value.'" '.$ischecked.'> '.$option_text.'
  </label>
  ';

  return $html;
}
}

function get_textarea_html($id, $rows, $cols, $placeholder)
{
  // 储存设定
  global $tr;
  $html = '
  <div class="form-inline">
    <textarea rows="'.$rows.'" cols="'.$cols.'" class="form-control mr-2" id="'.$id.'" placeholder="'.$tr['current setting'].$placeholder.'"></textarea>
    <button class="btn btn-default input_btn" type="submit" id="'.$id.'_btn">'.$tr['Save'].'</button>
  </div>
  ';

  return $html;
}

function get_qrcode_html($id, $img_src)
{
  global $tr;
  $html = '
  <img id="qrcode_img" height="200" src="'.$img_src.'" tyle="display:none"><br><br>
  <!-- Button trigger modal -->
  <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#'.$id.'Modal">'.$tr['uploading'].'</button>
  <button type="button" class="btn btn-danger btn-sm qrcode_del_btn" id="'.$id.'_del_btn">'.$tr['delete'].'</button>

  <!-- Modal -->
  <div class="modal fade bs-example-modal-sm" id="'.$id.'Modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
      <div class="modal-content">

        <div class="modal-header">
          <h4 class="modal-title" id="myModalLabel">'.$tr['upload image 245'].'</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>

        <div class="modal-body">
          <p>'.$tr['preview image'].'</p>
          <img id="'.$id.'_output" height="200" style="display:none"><br><br>
          <input type="file" id="'.$id.'_img" onchange="openFile(event)">
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">'.$tr['Cancel'].'</button>
          <button type="button" class="btn btn-primary" id="'.$id.'" onclick="convert_img_todatauri_upload(this.id)">'.$tr['upload'].'</button>
        </div>

      </div>
    </div>
  </div>
  ';

  return $html;
}

// 對DB查詢設定項目清單
function get_protalsetting_list($setttingname){
    $protalsetting_arr = [];

    $sql = <<<SQL
        SELECT DISTINCT(setttingname) as setttingname,
               id,
               name,
               value,
               status
        FROM root_protalsetting
        WHERE setttingname = '{$setttingname}'
            AND status = '1'
        ORDER BY id;
    SQL;
    $result = runSQLall($sql);

    if( empty($result[0]) ){
        $error_msg = '会员端设定查询失败';
        return array('status' => false, 'result' => $error_msg);
    }

    unset($result[0]);
    foreach ($result as $key => $value) {
        $protalsetting_arr[$value->name] = $value->value;
    }

    return array('status' => true, 'result' => $protalsetting_arr);
} // end get_protalsetting_list

function get_entire_website_close_html($protalsetting)
{
  global $entire_website_close;
  global $colname_arr;

  $switch_html = '';

  $html = '';
  foreach ($entire_website_close as $key => $value) {
    $col_name = '
    <div class="col-12 col-md-2">
      <strong>'.$colname_arr[$key].'</strong>
    </div>
    ';
    foreach ($value as $deposit_close_element) {
      $current_value = '';
      if (isset($protalsetting[$deposit_close_element]) AND !empty($protalsetting[$deposit_close_element])) {
        $current_value = $protalsetting[$deposit_close_element];
        if ($deposit_close_element == 'companydeposit_switch' || $deposit_close_element == 'withdrawalapply_switch') {
          $ischecked = ($protalsetting[$deposit_close_element] == 'on') ? 'checked' : '';

          $switch_html = get_checkbox_switch_html($deposit_close_element, $ischecked);
        }
      }
    }

    $html = $html.'
    <div class="row">
      '.$col_name.'
      <div class="col-12 col-md-10 row">
        <div class="col-12 col-md-2">
          '.$switch_html.'
        </div>
        <div class="col-12 col-md-8">
          '.get_textarea_html($deposit_close_element, 3, 40, $current_value).'
        </div>
      </div>
    </div>
    <br><br>
    ';
  }

  return $html;
}

function get_member_setting_input_html($protalsetting, $id, $custom_submit_class=null)
{
    global $colname_arr;
    global $instructions_arr;

    $current_value = ( !empty($protalsetting[$id]) ? $protalsetting[$id] : '' );
    $instructions = (array_key_exists($id, $instructions_arr)) ? $instructions_arr[$id] : '';
    $_get_input_html = get_input_html($id, $current_value, $custom_submit_class);

    $html = <<<HTML
        <div class="row">
            <div class="col-12 col-md-3">
                <strong>{$colname_arr[$id]}</strong>
            </div>
            <div class="col-12 col-md-4">{$_get_input_html}</div>
            <div class="col-12 col-md-5">{$instructions}</div>
        </div>
        <br><br>
    HTML;

    return $html;
}

function get_select_option_html($protalsetting, $option_arr, $id)
{
    global $colname_arr;
    $select_option_html = '';

    foreach ($option_arr as $key=>$value) {
        $isselected = ( ( isset($protalsetting[$id]) && ($protalsetting[$id] == $key) ) ? 'selected' : '' );
        $select_option_html .= <<<HTML
            <option value="{$key}" {$isselected}>{$value}</option>
        HTML;
    }

    $return_html = <<<HTML
        <div class="row">
            <div class="col-12 col-md-3">
                <strong>{$colname_arr[$id]}</strong>
            </div>
            <div class="col-12 col-md-5">
                <select class="form-control review_currency_radio" name="{$id}" id="{$id}">
                    {$select_option_html}
                </select>
            </div>
            <div class="col-12 col-md-4"></div>
        </div><br><br>
    HTML;

    return $return_html;
}

function get_realtime_reward_audit_name_html($protalsetting, $option_arr, $id){
  global $colname_arr;
  $select_option_html='';
  // var_dump($protalsetting,$option_arr,$id);die();
  foreach ($option_arr as $key => $value) {
      $isselected = (isset($protalsetting[$id]) && $protalsetting[$id] == $key) ? 'selected' : '';
      $select_option_html .= '<option value="'.$key.'" '.$isselected.'>'.$value.'</option>';
  }
  $return_html='
    <div class="row">
        <div class="col-12 col-md-3">
          <strong>'.$colname_arr[$id].'</strong>
        </div>
        <div class="col-12 col-md-5">
          <select class="form-control review_currency_radio" name="'.$id.'" id="'.$id.'" onchange="radio_check();" >
            '.$select_option_html.'
          </select>
        </div>
        <div class="col-12 col-md-4"></div>
    </div><br><br>';
    return $return_html;
}

function get_bonus_type_html($protalsetting, $option_arr, $id)
{
    global $colname_arr;
    $select_option_html = '';
    // var_dump($protalsetting,$option_arr,$id);die();
    foreach ($option_arr as $key => $value) {
        if ($key == 'gcash') {
          $select_option_html .= '<span option value="' . $key . '" ' . $isselected . ' style="display:none">' . $value . '</option>';
        } else {
        $isselected = (isset($protalsetting[$id]) && $protalsetting[$id] == $key) ? 'selected' : '';
        $select_option_html .= '<option value="' . $key . '" ' . $isselected . '>' . $value . '</option>';
        }
    }
    $return_html = '
    <div class="row">
        <div class="col-12 col-md-3">
          <strong>' . $colname_arr[$id] . '</strong>
        </div>
        <div class="col-12 col-md-5">
          <select class="form-control review_currency_radio"  name="'.$id.'" id="'.$id.'"  onchange="auditsetting();" >
            ' . $select_option_html . '
          </select>
        </div>
        <div class="col-12 col-md-4"></div>
    </div><br><br>';
    return $return_html;
}

function get_member_setting_checkbox_html($protalsetting, $option_arr, $id){
    global $tr;
    global $colname_arr;

    $radio_html = '';
    $member_grade_set_htm = '';

    foreach($option_arr as $key => $value){
        if( $id=='registrationmoney_member_grade' ){
            $checkbox_value = $key;
            $ischecked = (isset($protalsetting[$id]) && $protalsetting[$id] == $key) ? 'checked' : '';
            $option_translation = $value;
            $member_grade_set_htm = '<a href="member_grade_config.php">('.$tr['Click here to go to modify the settings'].')</a>';
        }
        else {
            $checkbox_value = $key;
            $ischecked = (isset($protalsetting[$id]) && $protalsetting[$id] == $key) ? 'checked' : '';
            $option_translation = $value;
        }

        $radio_html = $radio_html.get_checkbox_html($id, $checkbox_value, $ischecked, $option_translation);
    } // end foreach

    $html = <<<HTML
        <div class="row">
            <div class="col-12 col-md-3">
            <strong>{$colname_arr[$id]}</strong>
            </div>
            <div class="col-12 col-md-5">{$radio_html}</div>
            <div class="col-12 col-md-4">{$member_grade_set_htm}</div>
        </div>
        <br><br>
    HTML;

    return $html;
} // end get_member_setting_checkbox_html

function get_register_table_html($protalsetting, $table_setting, $type)
{
  global $tr;
  global $colname_arr;

  $html = '';
  $actionList = ['show', 'must', 'unique'];

  foreach ($table_setting as $key => $switch_arr) {

    switch ($key) {
      case 'wechat':
        $colname = $protalsetting["custom_sns_rservice_1"]??$tr['sns1'];
        break;
      case 'qq':
        $colname = $protalsetting["custom_sns_rservice_2"]??$tr['sns2'];
        break;
      default:
        $colname = $colname_arr[$key];
        break;
    }
    $switch_html = '<td>'.$colname.'</td>';

    foreach ($actionList as $action) {
      $colName = ($key == 'bank_information') ? $type.'_'.$key.'_'.$action : $type.'_register_'.$key.'_'.$action;

      if (in_array($colName, $switch_arr)) {
        $ischecked = '';
        if (!empty($protalsetting[$colName]) AND $protalsetting[$colName] == 'on') {
          $ischecked = 'checked';
        }

        $switch_html = $switch_html.'<td>'.get_checkbox_switch_html($colName, $ischecked).'</td>';
      } else {
        $switch_html = $switch_html.'<td></td>';
      }

    }

    $switch_html = $switch_html.'<td>'.$tr['When member register'].' '.$colname.''.$tr['Display and limit function switch'].'</td>';

    $html = $html.'
    <tr>
      '.$switch_html.'
    </tr>
    ';

    unset($switch_html);
  }

  return $html;
}

function get_reward_table_html($protalsetting)
{
    global $reward_table_setting;
    global $colname_arr;
    global $instructions_arr;

    $html = '';
    foreach ($reward_table_setting as $key => $value) {
        $current_value = ( ( !empty($protalsetting[$value]) ) ? $protalsetting[$value] : '' );
        $_get_input_html = get_input_html($value, $current_value);
        $html .= <<<HTML
            <tr>
                <td>{$colname_arr[$value]}</td>
                <td>{$_get_input_html}</td>
                <td>{$instructions_arr[$value]}</td>
            </tr>
        HTML;
    }

    return $html;
}

//---------------
// 20200424
// user彈性設定:會員客服資訊APP名稱、ID
// 存成json
// 客服設定開關
function customer_radio_html($id,$value,$ischecked,$option_text){

  $html =<<<HTML
  <label class="radio-inline">
    <input type="radio" class="customer_radio" name="{$id}" id="check_{$id}" value="{$value}" {$ischecked}> {$option_text}
  </label>
HTML;
  return $html;
}


function get_customer_checkbox_html($protalsetting,$switch_array,$id){

  $radio_html = '';
  $html ='';

  $protal =[];
  $sql=<<<SQL
  select name,value from root_protalsetting where name = '{$id}'
SQL;
  $result = runSQLall($sql);

  for($i=1;$i<=$result[0];$i++) {
    $protal[$result[$i]->name] = $result[$i]->value;
    $decode= json_decode($protal[$result[$i]->name]);
  }

  foreach($switch_array as $key => $value){
    $checkbox_value = $key;
    $ischecked = (isset($decode->status) && $decode->status == $key) ? 'checked' : '';
    $option_translation = $value;

    $radio_html = $radio_html.customer_radio_html($id, $checkbox_value, $ischecked, $option_translation);
  }
  // $html.=<<<HTML
  //     <label class="radio-inline">
  //       <input type="radio" class="customer_radio" name="{$id}" id="{$id}" value="{$checkbox_value}" {$ischecked}> {$option_translation}
  //     </label>
  // HTML;
  $html.=<<<HTML
     {$radio_html}
HTML;

  return $radio_html;

}

// 社群媒體1
function first_get_customization_service_html($protalsetting){
  global $customer_service_custom_setting_1;
  global $colname_arr;
  global $instructions_arr;

  global $qr_code_service_1;
  global $isopen_option;
  global $tr;

  $html = '';
  // qrcode
  foreach ($qr_code_service_1 as $key => $v) {
    $qr_value = '';
    if (isset($protalsetting[$v]) AND !empty($protalsetting[$v])) {
      $qr_value = $protalsetting[$v];
    }
  }

  // 社群媒體
  foreach($customer_service_custom_setting_1 as $key => $value){
    $current_value = '';
    if (isset($protalsetting[$value]) AND !empty($protalsetting[$value])) {
      $current_value = json_decode($protalsetting[$value],true);
      // var_dump($current_value);die();
    }

    $app_name = isset($current_value['contact_app_name']) ? $current_value['contact_app_name'] : ''; // 通訊軟體名稱
    $app_id = isset($current_value['contact_app_id']) ? $current_value['contact_app_id'] :  ''; // 使用的ID

    // 開關(暫時隱藏)
    $switch_online_weblink = get_customer_checkbox_html($protalsetting,$isopen_option,$value);

    // qrcode
    $qrcode_area = get_qrcode_html($v, $qr_value);

    $html .=<<<HTML
    <tr>
      <td>{$colname_arr[$value]}</td>
      <td>
        <div class="form-inline">
        <input type="text" class="form-control mr-2" name="customization_input" id="{$value}_appname" placeholder="{$tr['current setting']}{$app_name}" value="{$app_name}">

          <button class="btn btn-default custom_input_btn" type="submit" id="{$value}_btn">{$tr['Save']}</button>
          <input type="text" class="form-control mr-2" name="customization_input" id="{$value}_id" placeholder="{$tr['current setting']}{$app_id}" value="{$app_id}">
        </div>
        {$qrcode_area}
      </td>
      <!-- <td>'.$instructions_arr[$value].'</td> -->
      <td>
        {$tr['service name']}<br>
        {$tr['Account']}ID<br>
        {$tr['upload qr code']}
      </td>
    </tr>
HTML;
  }

  return $html;
}
// 社群2
function second_get_customization_service_html($protalsetting){
  global $customer_service_custom_setting_2;
  global $colname_arr;
  global $instructions_arr;
  global $qr_code_service_2;
  global $isopen_option;
  global $tr;

  $html = '';

  // qrcode
  foreach ($qr_code_service_2 as $key => $v) {
    $qr_value = '';
    if (isset($protalsetting[$v]) AND !empty($protalsetting[$v])) {
      $qr_value = $protalsetting[$v];
    }
  }

  // 社群媒體
  foreach($customer_service_custom_setting_2 as $key => $value){
    $current_value = '';
    if (isset($protalsetting[$value]) AND !empty($protalsetting[$value])) {
      $current_value = json_decode($protalsetting[$value],true);
    }

    $app_name = isset($current_value['contact_app_name']) ? $current_value['contact_app_name'] : ''; // 通訊軟體名稱
    $app_id = isset($current_value['contact_app_id']) ? $current_value['contact_app_id'] :  ''; // 使用的ID

    // 開關(暫時隱藏)
    $switch_online_weblink = get_customer_checkbox_html($protalsetting,$isopen_option,$value);
    // qrcode
    $qrcode_area = get_qrcode_html($v, $qr_value);

    $html =<<<HTML
    <tr>
      <td>{$colname_arr[$value]}</td>
      <td>
      <div class="form-inline">
        <input type="text" class="form-control mr-2" name="customization_input_2" id="{$value}_appname" placeholder="{$tr['current setting']}{$app_name}" value="{$app_name}">

        <button class="btn btn-default custom_2_input_btn" type="submit" id="{$value}_btn">{$tr['Save']}</button>
        <input type="text" class="form-control mr-2" name="customization_input_2" id="{$value}_id" placeholder="{$tr['current setting']}{$app_id}" value="{$app_id}">
      </div>
        {$qrcode_area}
      </td>
      <!-- <td>{$instructions_arr[$value]}</td> -->
      <td>
        {$tr['service name']}<br>
        {$tr['Account']}ID<br>
        {$tr['upload qr code']}
      </td>
    </tr>
HTML;

    // $html = $html.'
    // <tr>
    //   <td>'.$colname_arr[$value].'</td>
    //   <td>
    //     '.get_customization_input_html($value, $current_value).'

    //     '.get_qrcode_html($v, $qr_value).'
    //   </td>
    //   <td>'.$instructions_arr[$value].'</td>
    // </tr>
    // ';
  }

  return $html;
}
//--------------------------

function get_customer_service_html($protalsetting){
  global $tr;
  global $customer_service_setting;
  global $colname_arr;
  global $instructions_arr;
  global $isopen_option;

  $html = '';

  foreach ($customer_service_setting as $key => $value) {

    $current_value = '';
    if (isset($protalsetting[$value]) AND !empty($protalsetting[$value])) {
      $current_value = json_decode($protalsetting[$value],true);
    }

    $contact_info = isset($current_value['contact']) ? $current_value['contact'] : '';

    // 開關(暫時隱藏)
    $switch_online_weblink = get_customer_checkbox_html($protalsetting,$isopen_option,$value);


    $html.=<<<HTML
    <tr>
      <td>{$colname_arr[$value]}</td>
      <td>
        <div class="form-inline">
          <input type="text" class="form-control mr-2" id="{$value}" placeholder="{$tr['current setting']}{$contact_info}" value="{$contact_info}">
        <button class="btn btn-default default_input_btn" type="submit" id="{$value}_btn">{$tr['Save']}</button>
      </div>
      </td>
      <td>{$instructions_arr[$value]}</td>
    </tr>
  HTML;
  }

  return $html;
}

function get_customer_service_qrcode_html($protalsetting)
{
  global $qrcode;
  global $colname_arr;
  global $instructions_arr;

  $html = '';
  foreach ($qrcode as $key => $value) {
    $current_value = '';
    if (isset($protalsetting[$value]) AND !empty($protalsetting[$value])) {
      $current_value = $protalsetting[$value];
    }

    $html = $html.'
    <tr>
      <td>'.$colname_arr[$value].'</td>
      <td>
        '.get_qrcode_html($value, $current_value).'
      </td>
      <td>'.$instructions_arr[$value].'</td>
    </tr>
    ';
  }

  return $html;
}
