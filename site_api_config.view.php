<?php function_exists('use_layout') or die() ?>
<?php use_layout("template/beadmin.tmpl.php") ?>

<!-- 页首 CSS 与 JS -->
<?php begin_section('extend_head') ?>
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
<!-- 新版 highchart, 只是先用來展示 UI -->
<!-- ref: https://www.highcharts.com/stock/demo/intraday-breaks -->
<script src="in/highcharts/stock/highstock.js"></script>
<script src="in/highcharts/stock/modules/data.js"></script>
<script src="in/highcharts/stock/modules/exporting.js"></script>
<script src="in/highcharts/stock/modules/export-data.js"></script>

<script>
  var configSet = <?php echo json_encode($api_configs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
  var state_map = ['<?php echo $tr['Enabled']?>', '<?php echo $tr['off'];?>', '<?php echo $tr['Maintenance'];?>'];
  // var state_map = ['启用', '关闭', '维护中'];
  var currency_type = [
    { title: "<?php echo $tr['Franchise'];?>", codename: "gcash" },
    { title: "<?php echo $tr['Gtoken'];?>", codename: "gtoken" }
  ];
  var member_grade_rows = <?php echo json_encode($member_grade_rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
  var payment_flow_control_check = <?=var_export($payment_flow_control_check)?>

  $(document).ready(function() {
    $("#show_list").DataTable( {
        colReorder: true,
        searching: false,
        columnDefs: [
          {
            targets: 7, orderable: false
          },
        ],
        columns: [
          { title: "ID", data: "id", className: "dt-center" },
          { title: "<?php echo $tr['set account name'];?>", data: "account_name" },
          // { title: "<?php echo $tr['api account']; ?>", data: "api_account" },
          { title: "<?php echo $tr['available services'];?>",

          //   { title: "<?php echo $tr['account name'] = '*设定名称'?>", data: "account_name" },
          // // { title: "<?php echo $tr['api account'] = '接口帐户'?>", data: "api_account" },
          // { title: "<?php echo $tr['available services'] = '可用服务'?>",

            data: function (row) {
              // return JSON.parse(row.available_services).join(", <br/>");
              var codename_list = JSON.parse(row.available_services);
              var title_list = [], tmp = [];
              for (var i in codename_list) {
                tmp = currency_type.filter(item => item.codename == codename_list[i])[0]
                title_list.push( tmp.title )
              }

              return title_list.join(", <br/>")
          }},
          { title: "<?php echo $tr['Member Level'] ?>",
            data: function (row) {
              var grade_text = [], gradename, gradelist;
              gradelist = row.available_member_grade.split(',')

              for (const key in gradelist) {
                if (gradelist[key] in member_grade_rows) {
                  gradename = member_grade_rows[gradelist[key]].gradename;
                  grade_text.push(
                    $("<a/>", {
                      href: "member_grade_config_detail.php?a=" + gradelist[key],
                      class: "btn btn-info btn-xs",
                      role: "button",
                      html: gradename
                    }).prop("outerHTML")
                  );
                } else {
                  gradename = "<?php echo $tr['not set'];?>";
                  grade_text.push(gradename);
                }
              }

              return grade_text.join('<br>')
            }
          },
          { title: "<?php echo $tr['per amount upper bound'] ?>", data: function (row) {
            return "$" + row.per_transaction_limit
            }, className: "dt-right" },
          { title: "<?php echo $tr['daily amount upper bound'] ?>", data: function (row) {
            return "$" + row.daily_transaction_limit
            }, className: "dt-right"},
          { title: "<?php echo $tr['monthly amount upper bound'] ?>", data: function (row) {
            return "$" + row.monthly_transaction_limit
            }, className: "dt-right"},
          { title: "<?php echo $tr['ip whitelisting'];?>",
          // { title: "<?php echo $tr['ip white list'] = 'IP 白名单'?>",
            data: function (row) {
              var array = JSON.parse(row.ip_white_list)
              return array && array.length > 0 ? JSON.parse(row.ip_white_list).join(', <br/>') : '<?php echo $tr['allow all'];?>'
            }, className: "dt-center"
          } ,
          { title: "<?php echo $tr['cash flow fee'];?>", data: function (row) { return row.fee_rate + ' %' }, className: "dt-right" },
          //  { title: "<?php echo '金流', $tr['Fee'], '(%)'?>", data: function (row) { return row.fee_rate + ' %' }, className: "dt-right" },
          { title: "<?php echo $tr['State']?>",
            data: function(row, type, set, meta) {
              return state_map[row.status];
            }
          },
          { title: "<?php echo $tr['description / action']?>", width: "72px", defaultContent: "",
            createdCell: function (td, cellData, rowData, row, col) {

                if (!payment_flow_control_check) {
                  $(td).append(
                    $('<a/>', {
                      class: "btn btn-primary",
                      href: "site_api_config.php?a=edit&cfgid=" + rowData.id,
                      title: "<?php echo $tr['description / action']?>",
                      html: '<span class="glyphicon glyphicon glyphicon-eye-open" aria-hidden="true"></span>',
                      style: "margin-left: 3px"
                    }).on('click', function() {
                      console.log('click edit works!');
                    })
                  );
                  return
                }

                $(td).append(
                  $('<button/>', {
                    class: "btn btn-danger",
                    type: "button",
                    html: '<span class="glyphicon glyphicon-trash" aria-hidden="true"></span>'
                  }).on('click', function() {
                    if (confirm("确认要删除" + rowData.account_name + "设定吗？")) {
                      var url = location.origin + location.pathname
                      var settings = {
                        "async": true,
                        "crossDomain": false,
                        "url": url + '?a=delete',
                        "method": "DELETE",
                        "headers": {
                          "content-type": "application/json",
                          "cache-control": "no-cache",
                        },
                        "data": JSON.stringify({
                          "id": rowData.id,
                          "api_account": rowData.api_account
                        }),
                        "dataType": "json"
                      }

                      $.ajax(settings)

                      .done(function (response) {
                        if (response.success == "Y") {
                          location.reload()
                        } else {
                          alert("<?php echo $tr['fail message is'];?>" + response.description)
                        }
                      })
                      .fail(function (err) {
                        console.log(err);
                      })
                    }
                  })
                );
                $(td).append(
                  $('<a/>', {
                    class: "btn btn-primary",
                    href: "site_api_config.php?a=edit&cfgid=" + rowData.id,
                    title: "<?php echo $tr['edit']?>",
                    html: '<span class="glyphicon glyphicon-pencil" aria-hidden="true"></span>',
                    style: "margin-left: 3px"
                  }).on('click', function() {
                    console.log('click edit works!');
                  })
                );
            },
          },
        ],
        data: configSet
      });
    });
</script>
<?=$extend_head_alert?>
<?php end_section() ?>

<!-- position info -->
<?php begin_section('html_meta_title') ?>
<?php echo $tr['Site Api Account Management'].'-'.$tr['host_name'] ?>
<?php end_section() ?>

<?php begin_section('page_title') ?>
<ol class="breadcrumb">
  <li><a href="home.php"><?php echo $tr['homepage']?></a></li>
  <li><a href="#"><?php echo $tr['System Management']?></a></li>
  <li class="active"><?php echo $tr['Site Api Account Management'] ?? '站台接口帐户管理'?></li>
</ol>
<?php end_section() ?>

<!-- main title -->
<?php begin_section('paneltitle_content')  ?>
<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span><?php echo $tr['Site Api Account Management'] ?? '站台接口帐户管理'?>
<?php end_section() ?>

<!-- main content -->
<?php begin_section('panelbody_content') ?>

<div class="row">
    <div class="col-12 tab">
      <ul class="nav nav-tabs">
        <li class="nav-item active"><a class="nav-link" href="#merchant_info" data-toggle="tab">商戶號資訊</a></li>
        <li class="nav-item"><a class="nav-link <?=count($payment_methods->data) === 0 ? 'disabled' : ''?>" href="#payments" data-toggle="tab">可用金流商</a></li>
      </ul>
    </div>
</div>

<div class="row my-2">
  <div class="col-12 col-md-12 tab-content">
    <div role="tabpanel" class="tab-pane" id="inbox_View">
    <?php if ($payment_flow_control_check): ?>
      <a href="site_api_config.php?a=add" + rowData.api_account>
        <button type="button" class="btn btn-success" style="display:inline-block;float:right;margin-right: 5px;">
          <span class="glyphicon glyphicon-plus" aria-hidden="true"></span><?php echo $tr['add account'];?></button>
      </a>
    <?php endif?>
      <form id="show_list_form" action="POST">
        <table id="show_list"  class="display" cellspacing="0" width="100%">
        <thead>
          <tr><th colspan="5"></th><th colspan="3" class="well text-center"><?php echo $tr['Limit of credit'];?></th><th colspan="4"></th></tr>
          <tr>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
          </tr>
        </thead>
        <tfoot>
        </tfoot>
        </table>
      </form>
    </div>

    <?php // 商戶號資訊?>
    <div class="tab-pane active" id="merchant_info">
      <div class="row">
        <div class="col-4">
          <div class="card h-100">
            <div class="card-body align-items-bottom">
              <h4 class="card-title mt-2"><?=$tr['merchant number']?>： <?=$agentinfo['account']?></h4>
              <p class="card-text">
                <h6><?=$tr['store code']?>： <?=$agentinfo['agent_code']?></h6>
                <!-- <h6><?=$tr['Account Balance']?>： <?=$agentinfo['deposit_limits']?> <?=$currency?></h6> -->
                <h6><?=$tr['Limit of credit'] . $tr['warming']?>： <?=$agentinfo['deposit_alerts']?> <?=$currency?></h6>
                <h6><?=$tr['single deposit limit']?>： <?=$agentinfo['single_deposit_limits']?> <?=$currency?></h6>
                <h6><?=$tr['Current accumulation']?>： <?=$agentinfo['accumulated_amount']?> <?=$currency?></h6>
                <h6><?=$tr['State']?>： <?=$agentinfo['status']?></h6>
                <h6><?=$tr['effective date']?>： <?=$agentinfo['expired_at']?> (<?=$agentinfo['remain_days']?>)</h6>
              </p>
              <a href="<?=$agentinfo['office_url'] ?: '#'?>" target="_blank">
                <button type="button" class="btn btn-primary mt-1" style="display:inline-block;" <?=!$agentinfo['office_url'] ? 'disabled' : ''?>>
                <span class="glyphicon glyphicon-link" aria-hidden="true" class=""></span><?=$tr['go to cash flow management backstage'];?></button>
              </a>
            </div>

          </div>
        </div>
        <div class="col-8">
          <div id="intraday_volume"></div>
        </div>
      </div>
    </div>
    <?php // END 商戶號資訊?>

    <?php // 可用金流商?>
    <div class="tab-pane" id="payments">
        <div class="row">
            <div class="col-4">
                <div class="list-group" id="list-tab" role="tablist" style="height:495px; overflow-y:scroll;">
                    <style>
                        /* === (左邊)支付方式的css === */
                        /* 左邊選單(支付方式)-啟用 */
                        .list-group-item > span.enabled{
                            margin-left:10px;
                            color:#fff;
                            background-color:#28a745;
                            float:right;
                        }
                        /* 左邊選單(支付方式)-停用 */
                        .list-group-item > span.disabled{
                            margin-left:10px;
                            color:#fff;
                            background-color:#dc3545;
                            float:right;
                        }
                        /* 左邊選單(支付方式)-異常 */
                        .list-group-item > span.error{
                            margin-left:10px;
                            color:#212529;
                            background-color:#ffc107;
                            float:right;
                        }
                        /* 左邊選單(支付方式)-總計 */
                        .list-group-item > span.count{
                            margin-left:5px;
                            color:#fff;
                            background-color:#17a2b8;
                            float:right;
                        }
                    </style>
                    <?php
                        // 印出支付方式
                        if( isset($payment_methods) && ( count($payment_methods->data) > 0 ) ){
                            foreach($payment_methods->data as $key=>$val){
                                echo <<<HTML
                                    <a class="list-group-item list-group-item-action" id="{$val->codename}" data-toggle="list" href="#list-{$val->codename}" role="tab" aria-controls="{$val->codename}">
                                        <b>{$val->title}</b>
                                HTML;
                                // 印出支付方式狀態
                                if($val->status == 1){
                                    echo <<<HTML
                                        <span class="badge badge-pill badge-success enabled">啟用</span>
                                    HTML;
                                }
                                else if($val->status == 0){
                                    echo <<<HTML
                                        <span class="badge badge-pill badge-danger disabled">停用</span>
                                    HTML;
                                }
                                else{
                                    echo <<<HTML
                                        <span class="badge badge-pill badge-danger error">異常</span>
                                    HTML;
                                }
                                // 印出支付方式底下子分類總計 & <a>結尾
                                echo <<<HTML
                                        <!-- 測試時用 -->
                                        <!-- <span class="badge badge-pill badge-danger disabled">停用</span>
                                        <span class="badge badge-pill badge-danger error">異常</span> -->
                                        <span class="badge badge-light count">{$val->available_payment_methods_count}</span>
                                    </a>
                                HTML;
                            } // end foreach
                        }
                    ?>
                </div>
            </div>

            <div class="col-8">
                <style>
                    /* === (右邊)支付方式子項目的css === */
                    /* 右邊選單(支付方式子項目)-啟用 */
                    .justify-content-between > div > span.enabled{
                        padding: 5px 7px;
                        color: #fff;
                        background-color: #28a745;
                    }
                    /* 右邊選單(支付方式子項目)-停用 */
                    .justify-content-between > div > span.disabled{
                        padding: 5px 7px;
                        color: #fff;
                        background-color: #dc3545;
                    }
                    /* 右邊選單(支付方式子項目)-異常 */
                    .justify-content-between > div > span.error{
                        padding: 5px 7px;
                        color:#212529;
                        background-color:#ffc107;
                    }
                    /* 右邊選單(支付方式子項目)-建立測試訂單游標 */
                    .justify-content-between > div > span.generate_test_order{
                        padding: 5px 7px;
                        color: #fff;
                        background-color: #007bff;
                        cursor: pointer;
                    }
                    /* 右邊選單(支付方式子項目)-未設定商戶號 */
                    .justify-content-between > div > span.unsetting{
                        color:#212529;
                        background-color:#ffc107;
                        padding:6px 7px;
                    }
                    /* 右邊選單(支付方式子項目)-<a>游標 */
                    div.tab-pane.fade.show.active > a.list-group-item{
                        cursor: default;
                    }
                </style>
                <div class="tab-content" id="nav-tabContent" style="height:495px; overflow-y:scroll;">
                    <?php
                        // 印出支付方式子項目
                        if( isset($payment_methods) && ( count($payment_methods->data) > 0 ) ){
                            foreach($payment_methods->data as $key=>$val){
                                echo <<<HTML
                                    <div class="tab-pane fade show" id="list-{$val->codename}" role="tabpanel" aria-labelledby="list-{$val->codename}-list">
                                HTML;

                                // 可使用的支付方式
                                if( isset($val->available_payment_methods) && (count($val->available_payment_methods) > 0) ){
                                    foreach($val->available_payment_methods as $key_outer=>$val_outer){
                                        echo <<<HTML
                                            <a href="#" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><b>{$val_outer->title}</b>({$val_outer->payment})</h5>
                                                    <div>
                                        HTML;
                                                if($val_outer->status == 0){
                                                    echo <<<HTML
                                                        <!-- <span class="badge badge-pill badge-warning unsetting">未設定商戶號</span> -->
                                                        <span class="badge badge-pill badge-danger disabled">停用</span>
                                                    HTML;
                                                }
                                                else if($val_outer->status == 1){
                                                    if( $isset_order_tester_account && isset($config['website_domainname']) ){
                                                        $min_amount = ( isset($val->allowed_amount->$currency->min) && ($val->allowed_amount->$currency->min != null) ? $val->allowed_amount->$currency->min : 100 );
                                                        printf(<<<HTML
                                                            <span
                                                              class="badge badge-primary generate_test_order"
                                                              data-payservice="{$val->codename}"
                                                              data-provider="{$val_outer->payment}"
                                                              data-amount="{$min_amount}"
                                                              data-provider="{$val_outer->title}({$val_outer->payment})"
                                                              data-is_bank_required=%s
                                                              data-support_banks="%s"
                                                              data-service_title="{$val->title}"
                                                              data-provider_title="{$val_outer->title}"
                                                            >建立測試訂單</span>
                                                          HTML,
                                                          json_encode($val_outer->is_bank_required),
                                                          base64_encode(json_encode($val_outer->support_banks))
                                                        );
                                                    }
                                                    echo <<<HTML
                                                        <span class="badge badge-pill badge-success enabled">啟用</span>
                                                    HTML;
                                                }
                                                else{
                                                    echo <<<HTML
                                                        <span class="badge badge-pill badge-danger error">異常</span>
                                                    HTML;
                                                }
                                        echo <<<HTML
                                                    </div>
                                                </div>
                                                <small>幣別：{$val_outer->currency}</small>
                                                <p class="mb-1">{$val_outer->description}</p>
                                            </a>
                                        HTML;
                                    } // end available paymentway foreach
                                }

                                // 不可使用的支付方式
                                if( isset($val->not_available_payment_methods) && (count($val->not_available_payment_methods) > 0) ){
                                    foreach($val->not_available_payment_methods as $key_outer=>$val_outer){
                                        echo <<<HTML
                                            <a href="#"
                                            class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1"><b>{$val_outer->title}</b>({$val_outer->payment})</h5>
                                                    <div>
                                                        <span class="badge badge-pill badge-warning unsetting">未設定商戶號</span>
                                        HTML;
                                                if($val_outer->status == 0){
                                                    echo <<<HTML
                                                        <!-- <span class="badge badge-pill badge-warning unsetting">未設定商戶號</span> -->
                                                        <span class="badge badge-pill badge-danger disabled">停用</span>
                                                    HTML;
                                                }
                                                else if($val_outer->status == 1){
                                                    echo <<<HTML
                                                        <span class="badge badge-pill badge-success enabled">啟用</span>
                                                    HTML;
                                                }
                                                else{
                                                    echo <<<HTML
                                                        <span class="badge badge-pill badge-danger error">異常</span>
                                                    HTML;
                                                }
                                        echo <<<HTML
                                                    </div>
                                                </div>
                                                <small>幣別：{$val_outer->currency}</small>
                                                <p class="mb-1">{$val_outer->description}</p>
                                            </a>
                                        HTML;
                                    } // end available paymentway foreach
                                }

                                echo <<<HTML
                                    </div>
                                HTML;
                            }
                        }
                    ?>
                  <div id="orderinfoModal" class="modal fade" role="dialog">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h4 class="modal-title">建立測試訂單</h4>
                          <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                          <form class="form-horizontal" action="deposit_action.php?a=get_pay_link" method="POST" target="_blank" id="test_order" name="test_order">
                            <div class="row form-group">
                              <label for="order_payservice" class="control-label col-sm-3 mb-2"><?=$tr['Service']?></label>
                              <div class="col-sm-9 mb-2">
                                <input class="form-control" type="text" name="payservice" value="" id="order_payservice" required readonly>
                                <small for="order_payservice"></small>
                              </div>
                              <label for="order_provider" class="control-label col-sm-3 mb-2"><?=$tr['payment method']?></label>
                              <div class="col-sm-9 mb-2">
                                <input class="form-control" type="text" name="provider" value="" id="order_provider" required readonly>
                                <small for="order_provider"></small>
                              </div>
                              <label for="order_amount" class="control-label col-sm-3 mb-2"><?=$tr['amount']?></label>
                              <div class="col-sm-9 mb-2">
                                <input class="form-control" type="text" name="amount" value="" id="order_amount" required>
                              </div>
                              <div class="row px-3" for="order_bank" style="display: none;">
                                <label for="order_bank" class="control-label col-sm-3 mb-2"><?=$tr['bank']?></label>
                                <div class="col-sm-9 mb-2">
                                  <select class="form-control" name="bank" id="order_bank"></select>
                                </div>
                              </div>
                              <input type="hidden" name="csrftoken" value="<?=csrf_token_make()?>">
                            </div>
                          </form>
                        </div>
                        <div class="modal-footer">
                          <button type="submit" id="test_order" class="btn btn-success" form="test_order" data-dismisss="modal"><?=$tr['Submit']?></button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
            </div>
        </div>
        <!-- <div class="row">
            <div class="col-6">
                <div class="alert alert-info alert-dismissible">第三方金流商<button type="button" class="close" data-dismiss="alert">&times;</button></div>
                <ul class="list-group">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <dt>Linepay</dt>
                    <dd style="color:red">請先設定商戶號</dd>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <dt>Pingpp</dt>
                    <dd>可用商戶號 <span class="badge badge-primary badge-pill" title="acc1, acc2, acc3">3</span></dd>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Yeepay
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    JDPay
                </li>
                </ul>
            </div>
            <div class="col-6">
                <div class="alert alert-info alert-dismissible">區塊鏈出入金<button type="button" class="close" data-dismiss="alert">&times;</button></div>
                <ul class="list-group">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Ethernum
                </li>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    DDM/DDMX
                </li>
                </ul>
            </div>
        </div> -->
    </div>
    <?php // END 可用金流商?>

    <div class="tab-pane" id="payments_info"></div>
  </div>
</div>

<div class="row">
  <div id="preview"></div>
  <div id="preview_result"></div>
</div>
<!-- <form id="test_order" action="deposit_action.php?a=get_pay_link" method="post" target="_blank" style="display:none;">
    <input type="text" name="payservice" value="">
    <input type="text" name="provider" value="">
    <input type="text" name="amount" value="">
</form> -->
<?php end_section() ?>

<!-- main content -->
<?php begin_section('extend_js') ?>
<script>

<?php // 舊版當日交易額度圖表-區域圖 ?>
/* Highcharts.getJSON('https://cdn.jsdelivr.net/gh/highcharts/highcharts@v7.0.0/samples/data/new-intraday.json', function (data) {
    console.log(data);
    console.log( Date.UTC(2011, 9, 6, 16) );
    console.log( Date.UTC(2011, 9, 7, 8) );
    // create the chart
    Highcharts.stockChart('intraday_volume', {
        // 標題
        title: {
            text: '(DEMO) 當日交易額度'
        },
        // 副標題
        subtitle: {
            text: 'Using explicit breaks for nights and weekends'
        },
        // X軸
        xAxis: {
            breaks: [{ // Nights
            from: Date.UTC(2011, 9, 6, 16),
            to: Date.UTC(2011, 9, 7, 8),
            repeat: 24 * 36e5
            }, { // Weekends
            from: Date.UTC(2011, 9, 7, 16),
            to: Date.UTC(2011, 9, 10, 8),
            repeat: 7 * 24 * 36e5
            }]
        },
        // 區間選擇
        rangeSelector: {
            buttons: [{
                type: 'hour',
                count: 1,
                text: '1h'
            }, {
                type: 'day',
                count: 1,
                text: '1D'
            }, {
                type: 'all',
                count: 1,
                text: 'All'
            }],
            selected: 1,
            inputEnabled: false
        },

        series: [{
            name: 'AAPL',
            type: 'area',
            data: data, // 從JSON檔案來的資料
            gapSize: 5,
            tooltip: {
                valueDecimals: 2
            },
            fillColor: {
                linearGradient: {
                    x1: 0,
                    y1: 0,
                    x2: 0,
                    y2: 1
                },
                stops: [
                    [0, Highcharts.getOptions().colors[0]],
                    [1, Highcharts.Color(Highcharts.getOptions().colors[0]).setOpacity(0).get('rgba')]
                ]
            },
            threshold: null
        }]
    });
}); */

<?php // 當日交易額度圖表-區域圖 (made by Damocles) ?>
var data_all = "[" + <?php echo $data_all; ?> + "]";
    data_all = JSON.parse( data_all );
var data_completed = "[" + <?php echo $data_completed; ?> + "]";
    data_completed = JSON.parse( data_completed );

Highcharts.chart('intraday_volume', {
    chart: {
        type: 'area'
    },
    accessibility: {
        description: ''
    },
    title: {
        text: '當日交易額度'
    },
    subtitle: {
        text: '<?php echo $today; ?>'
    },
    // 因為規劃是顯示當天的交易紀錄，所以這邊顯示00:00~24:00
    xAxis: {
        categories: [
            '00:00', '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00',
            '13:00', '14:00','15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00', '24:00'
        ]
    },
    yAxis: {
        title: {
            text: '交易額度'
        },
        plotLines: [{
            value: 0,
            width: 1,
            color: '#808080'
        }]
    },
    series: [
        {
            name: 'All',
            data: data_all
        },
        {
            name: 'Completed',
            data: data_completed
        }
    ]
}); // end chart

<?php
    // config.php 內有設定測試訂單的帳號(帳號跟前台的config.php一樣) 跟 前台位置的資訊才會顯示以下function
    if( $isset_order_tester_account && isset($config['website_domainname']) ){
        echo <<<JS
            $("body").on("click", "span.generate_test_order", function(){
                $('#orderinfoModal').modal('show')
                $('#order_payservice').val($(this).data("payservice"));
                $('small[for=order_payservice').html(
                  $('<div>', {
                    class: "text-right",
                    html: $(this).data("service_title")
                  })
                )
                $("#order_provider").val($(this).data("provider"));
                $('small[for=order_provider').html(
                  $('<div>', {
                    class: "text-right",
                    html: $(this).data("provider_title")
                  })
                )
                $("#order_amount").val($(this).data("amount"));
                /* 銀行選項 */
                var is_bank_required = $(this).data("is_bank_required");
                console.log(is_bank_required)
                var support_banks = [];
                var bank_option_html = '';
                $('#order_bank').attr('required', is_bank_required)
                $('div[for="order_bank"]').hide()
                if (is_bank_required) {
                  support_banks = JSON.parse(atob($(this).data("support_banks")))
                  for (key in support_banks) {
                      bank_option_html += `<option value="\${support_banks[key].swift_code}">\${support_banks[key].bank}</option>`
                  }
                  $('#order_bank').html(bank_option_html)
                  $('div[for="order_bank"]').show()
                }
            }); // end on
        JS;
    }
?>

$(function() {
  window.compinfo = <?=json_encode($compinfo)?>;
  compinfo.onlinepay.code !== 0 && alert(compinfo.onlinepay.desc);
})
</script>
<?php end_section() ?>
