<?php
// ----------------------------------------------------------------------------
// Features: 後台-- 會員端設定管理詳細
// File Name: protal_setting_deltail.php
// Author: Yuan
// Editor： Damocles
// Related: 對應 protal_setting.php
// DB Table: root_protalsetting
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";

// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";

// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/protal_setting_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title = $tr['Members client settings'];

// 擴充 head 內的 css or js
$extend_head = '';

// 放在結尾的 js
$extend_js = '';

// body 內的主要內容
$indexbody_content = '';

// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = <<<HTML
    <ol class="breadcrumb">
        <li><a href="home.php">{$tr['Home']}</a></li>
        <li><a href="#">{$tr['System Management']}</a></li>
        <li class="active">{$function_title}</li>
    </ol>
HTML;
// ----------------------------------------------------------------------------

if ( isset($_GET['sn']) && ($_SESSION['agent']->therole == 'R') ) {
    $action = filter_var($_GET['sn'], FILTER_SANITIZE_STRING);
} else {
    die('(x)不合法的測試');
}

// 有登入，且身份為管理員 R 才可以使用這個功能。
if ( isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ) {
    $protalsetting_list = get_protalsetting_list($action);

    if ($protalsetting_list) {

        // 會員設定 html
        $protal_setting_html = '';

        // 放射線組織-獎勵分紅辦法 html
        $bonus_commission_html = '';

        // 客服資訊 html
        $customer_service_html = '';

        $entire_website_close_html = get_entire_website_close_html($protalsetting_list['result']);

        // 註冊送彩金
        $switch_status = ( isset($protalsetting['registered_offer_switch_status']) && ($protalsetting['registered_offer_switch_status'] == 'on') ) ? $tr['open'] : $tr['off'];
        $gift_amount = (isset($protalsetting['registered_offer_gift_amount'])) ? $protalsetting['registered_offer_gift_amount'] : '0';
        $review_amount = (isset($protalsetting['registered_offer_review_amount'])) ? $protalsetting['registered_offer_review_amount'] : '0';

        $registered_bonus = <<<HTML
            <div class="row">
                <div class="col-12 col-md-3">
                    <strong>{$tr['register send bonus']}</strong>
                </div>
                <div class="col-12 col-md-9">
                    <p class="mb-1">
                        <span id="switch_status">{$switch_status}</span>
                        <a href="registered_offer_settings.php" class="ml-3">({$tr['Click here to go to modify the settings']})</a>
                    </p>
                    <div class="text-secondary giftreview_content">
                        <span class="gift_content">赠送金额 <i id="gift_amount"> $ {$gift_amount} </i>,</span>
                        <span class="review_content">稽核金额 <i id="review_amount"> $ {$review_amount} </i></span>
                    </div>
                </div>
            </div><br><br>
        HTML;

        // 会员注册指纹码限制次数
        $memberRegisterFingerprintingHtml = get_member_setting_input_html($protalsetting_list['result'], 'registerfingerprinting_member_numberoftimes');
        // 会员注册IP限制次数
        $memberRegisterIpHtml = get_member_setting_input_html($protalsetting_list['result'], 'registerip_member_numberoftimes');
        // 新会员使用完整功能时间限制(分钟)
        $becomeMemberDateLimitHtml = get_member_setting_input_html($protalsetting_list['result'], 'becomemember_datelimit');
        // 会员注册 自动 / 手动 审核设定
        $member_register_review_switch_radio_html = get_member_setting_checkbox_html($protalsetting_list['result'], $review_option2, 'member_register_review');

        // 代理引导注册指纹码限制次数
        $agentRegisterFingerprintingHtml = get_member_setting_input_html($protalsetting_list['result'], 'registerfingerprinting_agent_numberoftimes');
        // 代理引导注册IP限制次数
        $agentRegisterIpHtml = get_member_setting_input_html($protalsetting_list['result'], 'registerip_agent_numberoftimes');
        // 代理注册下线时间限制(分钟)
        $becomeAgentDateLimitHtml = get_member_setting_input_html($protalsetting_list['result'], 'becomeagent_datelimit');
        // 申请成为代理商的费用
        $agentRegisterGcashHtml = get_member_setting_input_html($protalsetting_list['result'], 'agency_registration_gcash');


        // 代理申请 自动 / 手动 审核设定
        $agent_review_switch_radio_html = get_member_setting_checkbox_html($protalsetting_list['result'], $review_option, 'agent_review_switch');
        // 取款申请 自动 / 手动 审核设定
        // $withdrawal_review_switch_radio_html = get_member_setting_checkbox_html($protalsetting_list['result'], $review_option, 'withdrawal_review_switch');
        // 線上支付入款 申请 自动 / 手动 审核设定
        $onlinepay_review_switch_radio_html = get_member_setting_checkbox_html($protalsetting_list['result'], $review_option, 'onlinepay_review_switch');
        // 前台線上支付入款(By Damocels)
        $front_payment = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'front_payment');
        // 隐藏现金帐户
        $hide_gcash_mode = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'hide_gcash_mode');
        // 会员入款帐户设定
        $member_deposit_currency_radio_html = get_member_setting_checkbox_html($protalsetting_list['result'], $currency, 'member_deposit_currency');
        // 入款目的帐户显示
        $member_deposit_currency_isshow_html = get_member_setting_checkbox_html($protalsetting_list['result'], $deposit_currency_isshow_option, 'member_deposit_currency_isshow');
        // 前台代理加盟金显示
        $agentbonus_calculation_isopen_html = get_member_setting_checkbox_html($protalsetting_list['result'], $deposit_currency_isshow_option, 'agentbonus_calculation_isopen');

        // 全民代理功能開關
        $national_agent_isopen_html = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'national_agent_isopen');
        // 代理审核申请功能开关设定 (2020-08-07 棄用 https://proj.jutainet.com/issues/4332)
        // $agent_review_isopen_html = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'agent_review_isopen');
        // // 会员注册注册功能开关设定(2020-08-14 棄用 https://proj.jutainet.com/issues/4437)
        // $member_register_isopen_html = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'member_register_isopen');
        // 会员登入强制变更密码开关
        $allow_login_passwordchg_isopen_html = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'allow_login_passwordchg');
        // 代理协助注册功能是否开启
        $register_agenthelp_isopen_html = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'register_agenthelp_isopen');
        // 代理转帐功能是否开启
        $agent_transfer_isopen_html = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'agent_transfer_isopen');


        // 會員/代理註冊欄位設定表
        $register_table_html = get_register_table_html($protalsetting_list['result'], $register_table_setting, 'member');
        $agent_register_table_html = get_register_table_html($protalsetting_list['result'], $agent_register_table_setting, 'agent');

        // 前台會員註冊功能開關
        $member_register_switch_ischecked = ($protalsetting_list['result']['member_register_switch'] == 'on') ? 'checked' : '';
        $agent_register_switch_ischecked = ($protalsetting_list['result']['agent_register_switch'] == 'on') ? 'checked' : '';

        // === 放射線組織 ===
        // 放射線組織獎金計算
        $bonus_commission_dividendreference = get_member_setting_checkbox_html( $protalsetting_list['result'], $isopen_option, 'bonus_commision_divdendreference' ); // echo '<pre>', var_dump($bonus_commission_dividendreference), '</pre>'; exit();

        // 營運利潤獎金
        $bonus_commision_profit = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'bonus_commision_profit');

        // 代理加盟金
        $radiationbonus_organization = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'radiationbonus_organization');

        $reward_table_html = get_reward_table_html($protalsetting_list['result']);

        $bonus_commission_html = <<<HTML
            <div>{$bonus_commission_dividendreference}</div>
            <div>{$bonus_commision_profit}</div>
            <div>{$radiationbonus_organization}</div>
            <table class="table table-hover">
                <thead>
                    <th width="25%">{$tr['field']}</th>
                    <th width="35%">{$tr['content']}</th>
                    <th width="40%">{$tr['description']}</th>
                </thead>
                <tbody>{$reward_table_html}</tbody>
            </table>
        HTML;

        // -----佣金開關-------
        // 存款投注佣金計算
        $depositbet_calculation = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'depositbet_calculation');
        // 时时反水开关on/off. on:打到时时反水资料表-
        $realtime_reward_switch_html = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'realtime_reward_switch');
        // 时时反水打到彩金池开关on/off. on:打到彩金池
        $realtime_reward_payout_sw_html = get_member_setting_checkbox_html($protalsetting_list['result'], $isopen_option, 'realtime_reward_payout_sw');
        // 时时反水至彩金池币别：gcash/gtoken realtime_reward_bonus_type
        // $realtime_reward_bonus_type_html = get_member_setting_checkbox_html($protalsetting_list['result'], $currency, 'realtime_reward_bonus_type');
        $realtime_reward_bonus_type_html = get_bonus_type_html($protalsetting_list['result'], $currency, 'realtime_reward_bonus_type');
        // 时时反水彩金状态(0=取消1=可领取2=暂停3=已领取)
        $realtime_reward_bonus_status_html=get_select_option_html($protalsetting_list['result'], $bonus_status_option, 'realtime_reward_bonus_status');
        // 时时反水彩金稽核名称：免稽核freeaudit，存款稽核depositaudit，优惠稽核shippingaudit
        $realtime_reward_audit_name_html=get_realtime_reward_audit_name_html($protalsetting_list['result'], $audit_name_option, 'realtime_reward_audit_name');


        // 时时反水稽核方式：稽核金额 audit_amount，稽核倍数 audit_ratio
        $realtime_reward_audit_type_html = get_member_setting_checkbox_html($protalsetting_list['result'], $audit_type_option, 'realtime_reward_audit_type');
        // 时时反水稽核金额/稽核倍数之值
        $realtime_reward_audit_amount_ratio_Html = get_member_setting_input_html($protalsetting_list['result'], 'realtime_reward_audit_amount_ratio');
        // 时时反水Time frequency
        $realtime_reward_time_frequency_html = get_select_option_html($protalsetting_list['result'], $time_frequency_option, 'realtime_reward_time_frequency');

        //維運設定
        $opsonly_setting_navi_tab = '';
        $opsonly_setting_content = '';
        $opsonly_setting_js = '';

        if ( in_array($_SESSION['agent']->account, $su['ops']) ) {
            $opsonly_setting_navi_tab = <<<HTML
                <li class="nav-item">
                    <a class="nav-link" id="rebatesetting-tab" data-toggle="tab" href="#opsOnlySetting" role="tab" aria-controls="rebatesetting" aria-selected="false">{$tr['operations setting']}</a>
                </li>
            HTML;

            //自定社群名稱
            $custom_sns_rservice_html_1 = get_member_setting_input_html($protalsetting_list['result'], 'custom_sns_rservice_1','custom_sns');
            $custom_sns_rservice_html_2 = get_member_setting_input_html($protalsetting_list['result'], 'custom_sns_rservice_2','custom_sns');

            $opsonly_setting_content = <<<HTML
                <div class="tab-pane fade" id="opsOnlySetting" role="tabpanel" aria-labelledby="agentSetting-tab"><br>
                    {$custom_sns_rservice_html_1}
                    {$custom_sns_rservice_html_2}
                </div>
            HTML;

            $opsonly_setting_js = <<<HTML
                <script>
                    $('.custom_sns').click(function(){
                        var name = $(this).attr('id').split('_btn');
                        var value  = $('#' + name[0]).val();
                        if (!value) {
                            alert("{$tr['please enter correct content']}");
                            return
                        }
                        var r = confirm("{$tr['custom_sns_rservice_warning']}");
                        if (r == true) {
                            $.post('protal_setting_deltail_action.php?a=edit', {
                                name: name[0],
                                value: value,
                            }, function(result) {
                                var result = JSON.parse(e);
                                alert(result.msg);
                            });
                        }
                    });
                </script>
            HTML;
        }

        // 客服設定
        $customerservice_html = get_customer_service_html($protalsetting_list['result']);
        $customerservice_custom_html = first_get_customization_service_html($protalsetting_list['result']);
        $customerservice_custom_html_2 = second_get_customization_service_html($protalsetting_list['result']);

        $customer_service_html = <<<HTML
            <table class="table table-hover">
                <thead>
                    <th width="25%">{$tr['field']}</th>
                    <th width="30%">{$tr['content']}</th>
                    <th width="45%">{$tr['description']}</th>
                </thead>
                <tbody>
                    {$customerservice_html}
                    {$customerservice_custom_html}
                    {$customerservice_custom_html_2}
                </tbody>
            </table>
        HTML;

        // 會員註冊設定與代理註冊設定表格列名
        $table_colname_html = <<<HTML
            <th width="15%">{$tr['field']}</th>
            <th width="15%">{$tr['display']}</th>
            <th width="15%">{$tr['required']}</th>
            <th width="15%">{$tr['only']}</th>
            <th width="50%">{$tr['description']}</th>
        HTML;

        // 主要內容生成
        $member_register_switch = get_checkbox_switch_html('member_register_switch', $member_register_switch_ischecked);
        $agent_register_switch = get_checkbox_switch_html('agent_register_switch', $agent_register_switch_ischecked);
        $protal_setting_html = <<<HTML
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#registerSetting" role="tab" aria-controls="home" aria-selected="true">{$tr['register setting']}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="profile-tab" data-toggle="tab" href="#agentSetting" role="tab" aria-controls="profile" aria-selected="false">{$tr['agent setting']}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="accountsetting-tab" data-toggle="tab" href="#accountingSetting" role="tab" aria-controls="accountsetting" aria-selected="false">{$tr['Account setting']}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="contact-tab" data-toggle="tab" href="#customerServiceSetting" role="tab" aria-controls="contact" aria-selected="false">{$tr['customer service setting']}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="rebatesetting-tab" data-toggle="tab" href="#realtimeRewardSetting" role="tab" aria-controls="rebatesetting" aria-selected="false">{$tr['rebate setting']}</a>
                </li>
                {$opsonly_setting_navi_tab}
            </ul>
            <div class="tab-content" id="myTabContent">
                <!-- 註冊設定 -->
                <div class="tab-pane fade show active" id="registerSetting" role="tabpanel" aria-labelledby="home-tab"><br>
                    <div class="row">
                        <div class="col-12 col-md-2">
                            <!-- 会员注册设定 -->
                            <strong>{$tr['member register setting']}</strong>
                        </div>
                        <div class="col-12 col-md-10">
                            {$member_register_switch}
                        </div>
                    </div><br>
                    <!-- 如必填选项开启但无开启显示，该必填选项无效果 -->
                    <div class="alert alert-warning" role="alert">
                        {$tr['If the required option is on but not on, the required option has no effect.']}
                    </div>
                    <table class="table table-striped">
                        <thead>
                            {$table_colname_html}
                        </thead>
                        <tbody>
                            {$register_table_html}
                        </tbody>
                    </table><br><br>
                    {$member_register_review_switch_radio_html}
                    {$allow_login_passwordchg_isopen_html}
                    {$memberRegisterFingerprintingHtml}
                    {$memberRegisterIpHtml}
                    {$registered_bonus}
                    <br><hr><br>
                    <div class="row">
                        <div class="col-12 col-md-2">
                            <strong>{$tr['Member application agent review setting']}</strong>
                        </div>
                        <div class="col-12 col-md-10">
                            {$agent_register_switch}
                        </div>
                    </div><br>
                    <div class="alert alert-warning" role="alert">
                        {$tr['If the required option is on but not on, the required option has no effect.']}
                    </div>
                    <table class="table table-striped">
                        <thead>
                            {$table_colname_html}
                        </thead>
                        <tbody>
                            {$agent_register_table_html}
                        </tbody>
                    </table>
                    {$national_agent_isopen_html}
                    {$agent_review_switch_radio_html}
                    {$agentRegisterGcashHtml}
                </div>
                <!-- 代理設定 -->
                <div class="tab-pane fade" id="agentSetting" role="tabpanel" aria-labelledby="agentSetting-tab"><br>
                    {$register_agenthelp_isopen_html}
                    {$agent_transfer_isopen_html}
                    {$agentbonus_calculation_isopen_html}
                    {$agentRegisterFingerprintingHtml}
                    {$agentRegisterIpHtml}
                    {$becomeAgentDateLimitHtml}
                </div>
                <!-- 帳務設定 -->
                <div class="tab-pane fade" id="accountingSetting" role="tabpanel" aria-labelledby="accountingSetting-tab"><br>
                    {$depositbet_calculation}
                    {$hide_gcash_mode}
                    {$member_deposit_currency_radio_html}
                    {$member_deposit_currency_isshow_html}
                    {$onlinepay_review_switch_radio_html}
                    {$front_payment}
                    {$entire_website_close_html}
                </div>
                <!-- 組織分紅設定 -->
                <div class="tab-pane fade" id="bonusCommissionSetting" role="tabpanel" aria-labelledby="bonusCommissionSetting-tab"><br>
                    {$bonus_commission_html}
                </div>
                <!-- 客服設定 -->
                <div class="tab-pane fade" id="customerServiceSetting" role="tabpanel" aria-labelledby="customerServiceSetting-tab"><br>
                    {$customer_service_html}
                </div>
                <!-- 时时反水设定 -->
                <div class="tab-pane fade" id="realtimeRewardSetting" role="tabpanel" aria-labelledby="customerServiceSetting-tab"><br>
                    {$realtime_reward_switch_html}
                    {$realtime_reward_payout_sw_html}
                    {$realtime_reward_bonus_type_html}
                    {$realtime_reward_bonus_status_html}
                    {$realtime_reward_audit_name_html}
                    {$realtime_reward_audit_type_html}
                    {$realtime_reward_audit_amount_ratio_Html}
                    {$realtime_reward_time_frequency_html}
                </div>
                <!-- 維運設定 -->
                {$opsonly_setting_content}
            </div>
        HTML;

        // 將 checkbox 堆疊成 switch 的 css
        $extend_head .= <<<HTML
            <style>
                .material-switch > input[type="checkbox"] {
                    visibility:hidden;
                }
                .material-switch > label {
                    cursor: pointer;
                    height: 0px;
                    position: relative;
                    width: 40px;
                }
                .material-switch > label::before {
                    background: rgb(0, 0, 0);
                    box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
                    border-radius: 8px;
                    content: '';
                    height: 16px;
                    margin-top: -8px;
                    margin-left: -30px;
                    position:absolute;
                    opacity: 0.3;
                    transition: all 0.4s ease-in-out;
                    width: 30px;
                }
                .material-switch > label::after {
                    background: rgb(255, 255, 255);
                    border-radius: 16px;
                    box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
                    content: '';
                    height: 16px;
                    left: -4px;
                    margin-top: -8px;
                    margin-left: -30px;
                    position: absolute;
                    top: 0px;
                    transition: all 0.3s ease-in-out;
                    width: 16px;
                }
                .material-switch > input[type="checkbox"]:checked + label::before {
                    background: inherit;
                    opacity: 0.5;
                }
                .material-switch > input[type="checkbox"]:checked + label::after {
                    background: inherit;
                    left: 20px;
                }
            </style>
        HTML;

        $extend_js = <<<HTML
            <script>
                $(document).ready(function(){
                    //暫時 时时反水计算频率区间 關閉選擇
                    $('#realtime_reward_time_frequency').attr('disabled', true);
                    var realtime_reward_bonus_type = $('#realtime_reward_bonus_type').val();
                    if (realtime_reward_bonus_type == 'gcash') {
                        bonus_type_isgcash();
                    }
                    var audit_name = $('#realtime_reward_audit_name').val();
                    if (audit_name == 'freeaudit') {
                        audit_name_isfreeaudit();
                    }
                    $('input[name=realtime_reward_audit_type]').click(function(){
                        $('#realtime_reward_audit_amount_ratio').prop('value', '0'); // 欄位填0
                    });
                    // radio點選(改變狀態)時
                    $('.review_currency_radio').click(function(){
                        var pk = $(this).attr('id');
                        var name = $(this).attr('name');
                        var value = $(this).val();
                        if ($.trim(value) != '') {
                            $.post('protal_setting_deltail_action.php?a=edit', {
                                value: value,
                                pk: pk,
                                name: name,
                            }, function(e){
                                var result = JSON.parse(e);
                                switch (name) {
                                    case 'national_agent_isopen': // 全民代理開關
                                        if ( (value === 'on') && (result.status === 'fail') ) {
                                            $('#national_agent_isopen[value="off"]').prop('checked', true);
                                            $('#national_agent_isopen[value="on"]').prop('checked', false);
                                        }
                                        break;
                                    case 'agent_review_switch': // 代理申請自動/手動開關
                                        if ( (value !== 'automatic') && (result.status === 'fail') ) {
                                            $('#agent_review_switch[value="manual"]').prop('checked', false);
                                            $('#agent_review_switch[value="automatic"]').prop('checked', true);
                                        }
                                        break;
                                    default:
                                        // do nothing
                                }
                                // 預設執行
                                alert(result.msg);
                            });
                        } else {
                            alert('(x)不合法的測試。');
                        }
                    });
                    // 彈性設定 客服資訊名稱、ID，要json_encode存在value
                    // 社群1
                    $('.custom_input_btn').click(function(){
                        var pk = $(this).attr('id').split('_btn');
                        var custom_data = $('input[name=customization_input]').map(function(){
                            return $(this).val();
                        }).get();
                        // 開關(暫時隱藏)
                        var value_c = 'on';
                        $.post('protal_setting_deltail_action.php?a=custom_setting_edit', {
                            custom_data:custom_data,
                            value_c:value_c
                        }, function(result){
                            $('#preview_result').html(result);
                        });
                    });
                    // 社群服务2
                    $('.custom_2_input_btn').click(function(){
                        var pk = $(this).attr('id').split('_btn');
                        // 開關(暫時隱藏)
                        var value_c = 'on';
                        var custom_data_2 = $('input[name=customization_input_2]').map(function(){
                            return $(this).val();
                        }).get();
                        $.post('protal_setting_deltail_action.php?a=custom_setting_edit', {
                            custom_data_2:custom_data_2,
                            value_c:value_c
                        }, function(result){
                            $('#preview_result').html(result);
                        });
                    });
                    // 線上客服、email、mobile
                    $('.default_input_btn').click(function(){
                        var title = $(this).attr('id').split('_btn');
                        var input_value = $('#' + title[0]).val();
                        // 開關(暫時隱藏)
                        var value_type = $('input:radio:checked[name=' + title[0] + ']').val();
                        if (value_type == '') {
                            var value = 'on';
                        }
                        $.post('protal_setting_deltail_action.php?a=custom_setting_edit', {
                            title : title[0],
                            input_value: input_value,
                            value: value
                        }, function(result){
                            $('#preview_result').html(result);
                        });
                    });
                    // input按下送出
                    $('.input_btn').click(function(){
                        var name = $(this).attr('id').split('_btn');
                        var dom = $('#' + name[0]);
                        var value  = dom.val();
                        $.post('protal_setting_deltail_action.php?a=edit', {
                            name: name[0],
                            value: value,
                        }, function(e){
                            var result = JSON.parse(e);
                            switch (name[0]) {
                                case 'agency_registration_gcash': // 申請成為代理商的費用無法修改時，值要清空
                                    if (result.status === 'fail') {
                                        dom.val('');
                                    }
                                    break;
                                default:
                                    // do nothing
                            }
                            alert(result.msg);
                        });
                    });
                    // checkbox點選(改變狀態)時
                    $('.checkbox_switch').click(function(){
                        var name = $(this).val();
                        var dom = $('#' + name);
                        var value = ( ( dom.is(':checked') ) ? 'on' : 'off' );
                        if ($.trim(name) !== '') {
                            $.post('protal_setting_deltail_action.php?a=edit', {
                                name: name,
                                value: value
                            }, function(e){
                                var result = JSON.parse(e);
                                switch (name) {
                                    case 'agent_register_switch': // 代理商申請功能
                                        if (result.status === 'fail') {
                                            if (value === 'on') {
                                                dom.prop('checked', false);
                                            } else {
                                                dom.prop('checked', true);
                                            }
                                        }
                                        break;
                                    default:
                                        // do nothing
                                }
                                alert(result.msg);
                            });
                        } else {
                            alert('(x)不合法的測試。');
                        }
                    });
                    // 刪qr code
                    $('.qrcode_del_btn').click(function(){
                        var name = $(this).attr('id').split('_del_btn');
                        if ($.trim(name[0]) != '') {
                            $.post('protal_setting_deltail_action.php?a=del_qrcode', {
                                name: name[0]
                            }, function(result){
                                $('#preview_result').html(result);
                            });
                        } else {
                            alert('(x)不合法的測試。');
                        }
                    });
                });
            </script>
        HTML;

        $extend_js .= <<<HTML
            <script>
                // 圖片預覽
                function openFile(event){
                    var input = event.target;
                    var output_id = input.id.split('_img');
                    var reader = new FileReader();
                    reader.readAsDataURL(input.files[0]);
                    reader.onload = function(){
                        var dataURL = reader.result;
                        $('#' + output_id[0] + '_output').attr('src', dataURL).show();
                    };
                }
                // 將圖片轉為data uri並上傳
                function convert_img_todatauri_upload(id){
                    var id = id.split('_input');
                    var fileUploader = document.getElementById(id[0] + '_img');
                    var file = fileUploader.files[0];
                    if (!(/\.(png|jpeg|jpg|gif)$/i).test(file.name)) {
                        alert('上傳的檔案非圖片，請重新選擇');
                        return;
                    }
                    if ( file.size > 102400) {
                        alert('圖片大於100kb，請重新選擇圖片');
                        return;
                    }
                    var reader = new FileReader();
                    reader.onload = function(){
                        var datauri = reader.result;
                        var image = new Image();
                        image.onload = function(){
                            var width = image.width;
                            var height = image.height;
                            if (width > 245 || height > 245) {
                                alert('圖片超過長寬245*245限制');
                                return;
                            }
                            uploading(id[0], datauri);
                        };
                        image.src= datauri;
                    }
                    reader.readAsDataURL(file);
                };
                // ajax 送出 post
                function uploading(name, value){
                    $.ajax ({
                        url: 'protal_setting_deltail_action.php?a=edit',
                        type: 'POST',
                        data: ({
                            name: name,
                            value: value
                        }), success: function(data){
                            var result = JSON.parse(e);
                            alert(result.msg);
                        }, error: function (errorinfo){
                            console.log(errorinfo);
                        },
                    });
                };
            </script>
        HTML;

        $extend_js .= <<<HTML
            <script>
                //抓取 http:// 開啟指定tab
                $(document).ready(function(){
                    //進來的 #tab link 防止往下滾另外加_tab
                    var tabid = location.hash;
                    //點擊其他tab取消 #tab link
                    if ( tabid != '' ) {
                        var tabidsplit = tabid.split('_');
                        $('' + tabidsplit[0] + '').tab('show');
                        $('#myTab li a').on('click',function(e){
                            var newtabid = e.target.id;
                            if (newtabid == 'home-tab') {
                                window.location.href = "protal_setting_deltail.php" + location.search;
                            } else {
                                window.location.href = "protal_setting_deltail.php" + location.search + '#' + newtabid + '_tab';
                            }
                        });
                    }
                });
                function radio_check(){
                    var audit_name = $("#realtime_reward_audit_name").val();
                    if (audit_name == 'freeaudit') {
                        audit_name_isfreeaudit();
                        $('#realtime_reward_audit_amount_ratio_btn').click(); // 金額或倍數儲存
                    } else {
                        $("#realtime_reward_audit_amount_ratio").prop('disabled', false); // 稽核金额/稽核倍数之值 disable
                        $("[name=realtime_reward_audit_type]").prop('disabled', false); // 稽核方式设定 disable
                    }
                }
                function audit_name_isfreeaudit(){
                    $("#realtime_reward_audit_amount_ratio").prop('disabled', true); // 稽核金额/稽核倍数之值 disable
                    $("#realtime_reward_audit_amount_ratio").prop('value', '0'); // 欄位填0
                    $("[name=realtime_reward_audit_type]").prop('disabled', true); // 稽核方式设定 disable
                }
                function bonus_type_isgcash(){
                    $("#realtime_reward_audit_name").prop('disabled', true); // 稽核名称 disable
                    $('#realtime_reward_audit_name').prop('value','freeaudit'); // 稽核名称 變免稽核
                    audit_name_isfreeaudit();
                }
                function auditsetting(){
                    var bonustype = $("#realtime_reward_bonus_type").val();
                    if (bonustype == 'gtoken') {
                        $("#realtime_reward_audit_name").prop('disabled', false); // 彩金稽核名称 可選
                        $("[name=realtime_reward_audit_type]").prop('disabled', false); // 稽核方式设定 可選
                        $("#realtime_reward_audit_amount_ratio").prop('disabled', false); // 稽核金额/稽核倍数之值 可填
                    } else {
                        bonus_type_isgcash();
                        $('#realtime_reward_audit_name').click(); // 稽核名称 儲存
                        $('#realtime_reward_audit_amount_ratio_btn').click(); // 金額或倍數儲存
                    }
                }
            </script>
        HTML;

        $extend_js .= $opsonly_setting_js;

        // 條列設定資訊 , 整理成為要輸出的格式
        $indexbody_content .= <<<HTML
            <div class="row">
                <div class="col-12 col-md-1"></div>
                <div class="col-12 col-md-10">
                    {$protal_setting_html}
                </div>
                <div class="col-12 col-md-1"></div>
            </div>.
            <div class="row">
                <div id="preview_result"></div>
            </div>
        HTML;
    } else {
        $show_transaction_list_html = '會員端設定資料查詢失敗';

        // 切成 1 欄版面
        $indexbody_content = '';
        $indexbody_content .= <<<HTML
            <div class="row">
                <div class="col-12 col-md-12">
                    {$show_transaction_list_html}
                </div>
            </div><br>
            <div class="row">
                <div id="preview_result"></div>
            </div>
        HTML;
    }
} else {
    // 沒有登入的顯示提示俊息
    $show_transaction_list_html = '(x) 只有管理員或有權限的會員才可以登入觀看。';

    // 切成 1 欄版面
    $indexbody_content = '';
    $indexbody_content .= <<<HTML
        <div class="row">
            <div class="col-12 col-md-12">
                {$show_transaction_list_html}
            </div>
        </div><br>
        <div class="row">
            <div id="preview_result"></div>
        </div>
    HTML;
}
// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content'] = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");