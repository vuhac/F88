<?php function_exists('use_layout') or die()?>
<?php use_layout("template/s2col.tmpl.php");?>

<!-- 页首 CSS 与 JS -->
<?php begin_section('extend_head')?>
<style>
  /* 將 checkbox 堆疊成 switch 的 css */
  .material-switch>input[type="checkbox"] {
    visibility: hidden;
  }

  .material-switch>label {
    cursor: pointer;
    height: 0px;
    position: relative;
    margin-right: 1.25em
  }

  .material-switch>label::before {
    background: rgb(0, 0, 0);
    box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
    border-radius: 8px;
    content: '';
    height: 16px;
    margin-top: -8px;
    margin-left: -18px;
    position: absolute;
    opacity: 0.3;
    transition: all 0.4s ease-in-out;
    width: 30px;
  }

  .material-switch>label::after {
    background: rgb(255, 255, 255);
    border-radius: 16px;
    box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
    content: '';
    height: 16px;
    left: -4px;
    margin-top: -8px;
    margin-left: -18px;
    position: absolute;
    top: 0px;
    transition: all 0.3s ease-in-out;
    width: 16px;
  }

  .material-switch>input[type="checkbox"]:checked+label::before {
    background: inherit;
    opacity: 0.5;
  }

  .material-switch>input[type="checkbox"]:checked+label::after {
    background: inherit;
    left: 20px;
  }

  .div_show
  {
    display:block;
  }
  .div_hide
  {
    display:none;
  }

  .ck-button {
    margin:0px;
    overflow:auto;
    float:left;
    width: 33.33%;
  }
  .ck-button:hover {
      border-color: #007bffaa;
      background-color: #007bffaa;
      color: #fff;
  }

  .ck-button label {
      float:left;
      width: 100%;
      height: 100%;
      margin-bottom:0;
      background-color: transparent;
      transition: all 0.2s;
  }

  .ck-button label span {
      text-align:center;
      display:block;
      font-size: 15px;
      line-height: 38px;
  }

  .ck-button label input {
      position:absolute;
      z-index: -5;
  }

  .ck-button input:checked + span {
      border-color: #007bff;
      background-color: #007bff;
      color: #fff;
  }
  .ck-button:nth-child(3n+2) label{
      border:1px solid #D0D0D0;
      border-top-style: none;
      border-bottom-style: none;
  }
  </style>
</style>
<link rel="stylesheet" type="text/css" href="ui/style_seting.css">
<!-- 參考使用 datatables 顯示 -->
<!-- https://datatables.net/examples/styling/bootstrap.html -->
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript"
  src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript"
  src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>

<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>

<script type="text/javascript" language="javascript" class="init">
  var get_custom_search_data = function () {
    return {
      txno: $("#search_form [name=txno]").val(),
      account: $("#search_form [name=account]").val(),
      agent: $("#search_form [name=agent_account]").val(),
      sdate: $("#search_form [name=sdate]").val(),
      edate: $("#search_form [name=edate]").val(),
      samount: $("#search_form [name=samount]").val(),
      eamount: $("#search_form [name=eamount]").val(),
      ipaddr: $("#search_form [name=ipaddr]").val(),
      status: $("#search_form [name=status_sel]:checked").map((idx, row) => (row.value)).get().join(',')
    };
  };

  // 本日、昨日、本周、上周、上個月button
  function settimerange(alias,text) {
    _time = utils.getTime(alias);
    // 單號申請時間
    $("#search_form [name=sdate]").val(_time.start);
    $("#search_form [name=edate]").val(_time.end);
    //console.log(_time);

    //更換顯示到選單外 20200525新增
    var currentonclick = $('.'+text+'').attr('onclick');
    var currenttext = $('.'+text+'').text();

    //first change
    $('.application .first').removeClass('week month');
    $('.application .first').attr('onclick',currentonclick);
    $('.application .first').text(currenttext);
  }
  //   // 本日、昨日、本周、上周、上個月button
  //   function settimerange(alias) {
  //   _time = utils.getTime(alias)
  //   // 單號申請時間
  //   $("#search_form [name=sdate]").val(_time.start)
  //   $("#search_form [name=edate]").val(_time.end)
  // }
</script>

<script type="text/javascript" src="./in/mqttws31.min.js"></script>
<script type="text/javascript">
  // setting from config.php
  var setting = {
    url: "<?=$config['mqtt_url']?>",
    username: "<?=$config['mqtt_username']?>",
    password: "<?=$config['mqtt_password']?>",
  }

  let client = new Paho.MQTT.Client(setting.url, "web_" + parseInt(Math.random() * 100, 10))

  client.onConnectionLost = function (responseObject) {
    if (responseObject.errorCode !== 0) {
      console.log("onConnectionLost:" + responseObject.errorMessage);
    }
  }

  client.onMessageArrived = function (message) {
    // console.log(message.payloadString)
    var data = {}

    try {
      // message is json format
      data = JSON.parse(message.payloadString)
    } catch (e) {
      // message is text
      data.message = message.payloadString
    }

    console.log(data)
    $dataTable.ajax.reload();
  }

  // MQTT broker
  client.connect({
    userName: setting.username,
    password: setting.password,
    keepAliveInterval: 10,
    onSuccess: function () {
      let channel = "<?=$message_reciever_channel?>" // get channel use lib_message.php
      client.subscribe(channel)
      console.log('Subscribe ' + channel + '.')
    },
    onFailure: function () {
      console.log('Connection failed!!')
    }
  })
</script>
<?php end_section()?>

<!-- position info -->
<?php begin_section('html_meta_title')?>
<?php echo $tr['Site Api Deposit Dashboard'] . '-' . $tr['host_name'] ?>
<?php end_section()?>

<?php begin_section('page_title')?>
<ol class="breadcrumb">
  <li><a href="home.php"><?php echo $tr['homepage'] ?></a></li>
  <li><a href="#"><?php echo $tr['Account Management'] ?></a></li>
  <li class="active"><?php echo $tr['Site Api Deposit Dashboard'] ?></li>
</ol>
<?php end_section()?>

<?php begin_section('indextitle_content')?>
<span class="glyphicon glyphicon-search" aria-hidden="true"></span>
<?=$tr['Search criteria'];?>
<?php end_section()?>

<?php begin_section('indexbody_content');?>
<form id="search_form" name="search_form" action="#" method="post">
  <div class="row">
    <div class="col-12">
      <label for="txno"><?=$tr['Entry Number']?></label>
    </div>
    <div class="col-12 form-group">
      <input type="text" class="form-control" name="txno" id="txno" placeholder="<?=$tr['Entry Number'];?>" />
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <label for="account"><?=$tr['Account'];?></label>
    </div>
    <div class="col-12 form-group">
      <input type="text" class="form-control" name="account" id="account" placeholder="<?=$tr['Account'];?>" />
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <label for="account_query">
        <?=$tr['Affiliated agent'];?>
        <span class="glyphicon glyphicon-info-sign"
          title="<?=$tr['Statistics by agent when inquiring include the statistics of agents themselves and their level off-line'];?>">
        </span>
      </label>
    </div>
    <div class="col-12 form-group">
      <input type="text" class="form-control" name="agent_account" id="agent_account"
        placeholder="<?=$tr['Affiliated agent'];?>" />
    </div>
  </div>

  <div class="row">
    <div class="col-12 d-flex">
      <label><?=$tr['application time']?></label>
      <div class="btn-group btn-group-sm ml-auto application" role="group" aria-label="Button group with nested dropdown">
        <button type="button" class="btn btn-secondary first"><?=$tr['grade default'];?></button>

        <div class="btn-group btn-group-sm" role="group">
          <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          </button>
          <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
            <a class="dropdown-item week" onclick="settimerange('thisweek','week')"><?=$tr['This week'];?></a>
            <a class="dropdown-item month" onclick="settimerange('thismonth','month')"><?=$tr['this month'];?></a>
            <a class="dropdown-item today" onclick="settimerange('today', 'today')"><?=$tr['Today'];?></a>
            <a class="dropdown-item yesterday" onclick="settimerange('yesterday','yesterday')"><?=$tr['yesterday'];?></a>
            <a class="dropdown-item lastmonth" onclick="settimerange('lastmonth','lastmonth')"><?=$tr['last month'];?></a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 form-group rwd_doublerow_time">
      <div class="input-group">
        <div class="input-group-prepend">
          <span class="input-group-text"><?=$tr['start']?></span>
        </div>
        <input type="text" class="form-control" name="sdate" placeholder="ex:2017-01-20" required data-type="dateYMD"
          autocomplete="off" value="<?=gmdate('Y-m-d',strtotime('- 91 days')).' 00:00'?>"/>
      </div>
      <div class="input-group">
        <div class="input-group-prepend">
          <span class="input-group-text"><?=$tr['end']?></span>
        </div>
        <input type="text" class="form-control" name="edate" placeholder="ex:2017-01-20" required data-type="dateYMD"
          autocomplete="off" value="<?=gmdate('Y-m-d',time() + -4*3600).' 23:59';?>"/>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <label for="betvalid_lower"><?=$tr['amount']?></label>
    </div>
    <div class="col-12 form-group">
      <div class="input-group">
        <input type="number" class="form-control" step=".01" placeholder="<?=$tr['Lower limit'];?>" id="samount"
          name="samount" min="0.01">
        <span class="input-group-addon" id="basic-addon1">~</span>
        <input type="number" class="form-control" step=".01" placeholder="<?=$tr['Upper limit'];?>" id="eamount"
          name="eamount" min="0.01">
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <label for="ipaddr"><?=$tr['ip address']?></label>
    </div>
    <div class="col-12 form-group">
      <input type="text" class="form-control" id="ipaddr" name="ipaddr" placeholder="<?=$tr['ip address']?>">
    </div>
  </div>

  <div clss="row">
    <div class="col-12">
      <div class="row border">
        <h6 class="betlog_h6 text-center"><?=$tr['Approval Status']?></h6>
      </div>
    </div>
    <div class="col-12">
      <div class="row border">
        <div class="ck-button">
          <label>
            <input type="checkbox" id="status_sel_2" name="status_sel" value="2">
            <span class="status_sel_0"><?=$tr['Be Cancel']?></span>
          </label>
        </div>
        <div class="ck-button">
          <label>
            <input type="checkbox" id="status_sel_1" name="status_sel" value="1">
            <span class="status_sel_1"><?=$tr['Qualified']?></span>
          </label>
        </div>
        <div class="ck-button">
          <label>
            <input type="checkbox" id="status_sel_0" name="status_sel" value="0" checked>
            <span class="status_sel_2"><?=$tr['Unreviewed']?></span>
          </label>
        </div>
      </div>
    </div>
  </div>

</form>
<hr />
<div class="row">
  <div class="col-12">
    <button id="submit_to_inquiry" class="btn btn-success btn-block" type="submit"><?=$tr['Inquiry']?>
    </button>
  </div>
</div>
<?php end_section();?>

<!-- main title -->
<?php begin_section('paneltitle_content')?>
<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span><?php echo $tr['Site Api Deposit Dashboard'] ?>
<p id="onlinepay_mq" class="mb-0 ml-auto float-right" style="color: #dc3545; display: none;"></p>
<?php end_section()?>

<!-- main content -->
<?php begin_section('panelbody_content')?>
<div class="row">
  <div class="col-12">
    <div class="float-right">
      <span class="material-switch">
        <input id="autoreview_switch" class="checkbox_switch" value="1" type="checkbox" />
        <label for="autoreview_switch" class="label-success"></label>
      </span><?=$tr['Auto-audit onlinepay switch']?>
    </div>
  </div>

  <div class="col-12" style="overflow:auto">
    <table id="show_list" class="display" cellspacing="0" width="100%">
      <thead>
        <tr>
          <th><?=$tr['ID']?></th>
          <th><?=$tr['Affiliated agent']?></th>
          <th><?=$tr['Account']?></th>
          <th><?=$tr['Entry Number']?></th>
          <!-- <th><?php //echo $tr['Member Level'] ?></th> -->
          <th><?=$tr['amount']?></th>
          <th><?=$tr['Fee']?></th>
          <!-- <th><?=$tr['total']?></th> -->
          <th><?=$tr['application time']?></th>
          <!-- <th><?php //echo $tr['Currency Type'] ?></th> -->
          <th><?=$tr['State']?></th>
          <th><?=$tr['Reconciliation information']?></th>
          <th><?=$tr['ip address']?></th>
          <th><?=$tr['audit']?></th>
        </tr>
      </thead>
      <tfoot>
        <tr>
          <th><?=$tr['ID']?></th>
          <th><?=$tr['Affiliated agent']?></th>
          <th><?=$tr['Account']?></th>
          <th><?=$tr['Entry Number']?></th>
          <!-- <th><?php //echo $tr['Member Level'] ?></th> -->
          <th><?=$tr['amount']?></th>
          <th><?=$tr['Fee']?></th>
          <!-- <th><?=$tr['total']?></th> -->
          <th><?=$tr['application time']?></th>
          <!-- <th><?php //echo $tr['Currency Type'] ?></th> -->
          <th><?=$tr['State']?></th>
          <th><?=$tr['Reconciliation information']?></th>
          <th><?=$tr['ip address']?></th>
          <th><?=$tr['audit']?></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="modal fade" id="api_query" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLongTitle"><?php echo $tr['order status']; ?></h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <img class="mr-2" height="20px" src="./ui/loading_hourglass.gif" alt=""><?php echo $tr['loading']; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $tr['off']; ?></button>
        </div>
      </div>
    </div>
  </div>
  <div id="preview_result"></div>
</div>
<?php end_section()?>

<!-- main content -->
<?php begin_section('extend_js')?>
<script>
  $(function () {
    // 表單中的 input 初始化
    // $('input[data-type="dateYMD"]')
    //   .datepicker({
    //     showButtonPanel: true,
    //     dateFormat: "yy-mm-dd",
    //     changeMonth: true,
    //     changeYear: true
    //   })
    //   .datepicker(
    //     "setDate",
    //     moment()
    //       .tz("Etc/GMT+4")
    //       .format("YYYY-MM-DD HH:mm:ss")
    //   );

    $('input[data-type="dateYMD"]').datetimepicker({
      showButtonPanel: true,
      changeMonth: true,
      changeYear: true,
      timepicker: true,
      format: "Y-m-d H:i",
      step:1
    });

  })
  $(function () {
    // 初始化表格資料
    window.auto_review = <?=var_export($onlinepay_review_switch == 'automatic')?>;
    window.recordStatusDesc = <?=json_encode(ApiDepositOrder::recordStatusDesc())?>;
    window.auditStatusMap = <?=json_encode(ApiDepositOrder::auditStatusMap())?>;
    window.auditStatusDesc = <?=json_encode(ApiDepositOrder::auditStatusDesc())?>;
    window.managers = <?=json_encode($managers)?>;
    window.testers = <?=json_encode(($testers))?>;
    autoreview_switch.checked = auto_review;

    window.$dataTable = $("#show_list").DataTable({
      pageLength: 10,
      order: [0, "desc"],
      "bProcessing": true,
      "bServerSide": true,
      "bRetrieve": true,
      "searching": false,
      ajax: {
        "url": "depositing_siteapi_audit.php?a=apideposit_orders_json",
        "type": "POST",
        data: function (d) {
          return $.extend({}, d, {
            custom_search: get_custom_search_data()
          });
        }
      },
      columns: [
        {
          className: "dt-center", data: function (row) {
            return $("<a/>", {
              class: "btn btn-default btn-xs",
              role: "button",
              onclick: function () { return false },
              html: row.id
            }).prop("outerHTML");
          }, name: "id"
        },
        {
          className: "dt-center",
          name: "parent_account",
          data: function (row) {
            if (managers.includes(row.parent_account)) return '-';
            return $("<a/>", {
              target: "_BLANK",
              href: "member_account.php?a=" + row.parent_id,
              title: "<?php echo $tr['Check membership details'] ?>",
              html: row.parent_account
            }).prop("outerHTML")
          }
        },
        {
          className: "dt-center",
          name: "account",
          data: function (row) {
            return $("<a/>", {
              target: "_BLANK",
              href: "member_account.php?a=" + row.member_id,
              title: "<?php echo $tr['Check membership details'] ?>",
              html: row.account
            }).prop("outerHTML")
          }
        },
        {
          className: "dt-center",
          name: "custom_transaction_id",
          data: function (row) {
            var copyicon = $("<button/>", {
              class: 'glyphicon',
              html: '&#xe205;',
              onclick: `utils.clip2board($(this).prev()[0])`
            }).prop("outerHTML")

            return $("<a/>", {
              target: "_BLANK",
              "data-orderno": row.custom_transaction_id,
              "data-toggle": "modal",
              "data-target": "#api_query",
              href: "#",
              title: "对金流服务接口做查找",
              html: row.custom_transaction_id
            }).prop("outerHTML") + " " + copyicon;
          },
        },
        // {
        //   className:"dt-center", data: function (row) {
        //     return $("<a/>", {
        //       class: "btn btn-info btn-xs",
        //       role: "button",
        //       href: "member_grade_config_detail.php?a=" + row.grade_id,
        //       html: row.gradename
        //     }).prop("outerHTML")
        //   }, name: "account"
        // },
        {
          className: "dt-right",
          name: "amount",
          data: function (row) {
            return '$' + row.amount
          }
        },
        {
          className: "dt-right", data: function (row) {
            return '$' + row.api_transaction_fee
          }, name: "api_transaction_fee"
        },
        {
          className: "dt-right",
          name: "request_time",
          data: function (row) {
            moment.locale('<?=$_SESSION['lang']?>')
            return moment(row.request_time).tz('Etc/GMT+4').format('YYYY/MM/DD(dd) HH:mm:ss')
          }
        },
        {
          className: "dt-center", data: function (row) {
            var status_description, btn_color, status_html;

            if (auditStatusMap[1].includes(row.status)) {
              btn_color = 'btn-success'
              status_description = auditStatusDesc[1]
            } else if (auditStatusMap[2].includes(row.status)) {
              btn_color = 'btn-danger'
              status_description = auditStatusDesc[2]
            } else if (auditStatusMap[0].includes(row.status)) {
              btn_color = 'btn-warning'
              status_description = auditStatusDesc[0]
            }

            status_html = $("<a/>", {
              class: "btn btn-xs " + btn_color,
              role: "button",
              onclick: function () { return false },
              html: status_description,
              title: recordStatusDesc[row.status]
            }).prop("outerHTML")

            return status_html;
          }, name: "status"
        },
        // { className: "dt-right", data: "site_account_name", name: "site_account_name" },
        // { className:"dt-right", data: function (row) {
        //     return $("<a/>", {
        //       target: "_BLANK",
        //       href: "site_api_config.php?a=edit&cfgid=" + (row.api_account_id || ''),
        //       title: "前往入款帐户设置",
        //       html: row.api_account_title
        //     }).prop("outerHTML")
        //   }, name: "account"
        // },
        {
          className: "dt-right",
          name: "reviewinfo",
          orderable: false,
          data: function (row) {
            let notification, reviewinfohtml
            try {
              // console.log(row.notification_json)
              notification = JSON.parse(row.notification_json)
              if (!notification) {
                throw "notification not exist!";
              }

              let account, account_note, provider, provider_order_no, amount
              account = typeof notification.account === 'undefined' ? '<?=$tr['no']?>' : notification.account
              account_note = testers.includes(account) ? '(<?=$tr['for test']?>)' : '';
              provider = typeof notification.provider === 'object' ? notification.provider : {
                codename: '<?=$tr['no']?>',
                order_id: '<?=$tr['no']?>',
              }
              provider_codename = typeof provider.codename === 'undefined' ? '<?=$tr['no']?>' : provider.codename
              provider_order_id = typeof provider.order_id === 'undefined' ? '<?=$tr['no']?>' : provider.order_id
              amount = typeof notification.amount === 'undefined' ? '<?=$tr['no']?>' : notification.amount

              reviewinfohtml = `
              <a href="javascript:utils.toggleText(reviewinfo_${row.id})">${account},${provider_codename},${provider_order_id},${amount}<span class="glyphicon glyphicon-eye-open"></a>
              <div id="reviewinfo_${row.id}" style="display:none">
                <p class="reconciliation_info">
                  <table class="table table-bordered">
                    <tr>
                      <td><?=$tr['Account']?></td>
                      <td>${account}${account_note}</td>
                    </tr>
                    <tr>
                      <td><?=$tr['cash flow name']?></td>
                      <td>${provider_codename}</td>
                    </tr>
                    <tr>
                      <td><?=$tr['provider order number']?></td>
                      <td>${provider_order_id}</td>
                    </tr>
                    <tr>
                      <td><?=$tr['amount']?></td>
                      <td>${amount}</td>
                    </tr>
                  </table>
                </p>
              </div>
            `
            } catch (error) {
              // console.log(error)
              // notification && console.log(notification)
              reviewinfohtml = '<?=$tr['no data in this field']?>'
            }
            return reviewinfohtml
          }
        },
        {
          className: "dt-right",
          name: "ip_addr",
          orderable: false,
          data: "agent_ip",
        },
        {
          className: "dt-right",
          name: "ops_action",
          orderable: false,
          data: function (row) {
            var confirm_btn, cancel_btn;

            confirm_btn = $("<button/>", {
              class: "btn btn-xs btn-primary",
              role: "button",
              html: '<?=$tr['agree']?>',
              "data-review-id": row.id,
              "data-review-action": "agree",
              onclick: "review_order(this)"
            }).prop("outerHTML")

            cancel_btn = $("<button/>", {
              class: "btn btn-xs btn-danger",
              role: "button",
              html: '<?=$tr['disagree']?>',
              "data-review-id": row.id,
              "data-review-action": "disagree",
              onclick: "review_order(this)"
            }).prop("outerHTML")

            processed_btn = $("<button/>", {
              class: "btn btn-xs btn btn-success",
              role: "button",
              onclick: 'return false',
              html: row.status == 1 ? '<?=$tr['Approved']?>': '<?=$tr['application reject']?>',
            }).prop("outerHTML")

            //
            action_html = row.is_archived == false ? confirm_btn + cancel_btn : processed_btn

            // '確認|取消' or '已處理'
            return action_html
          },
        },
      ]
    });

    // $dataTable.column(-1).visible(!auto_review)

    $("#submit_to_inquiry").on("click", function () {
      $dataTable.ajax.reload();
    });

    //api 自動審核(入賬)開關
    $('#autoreview_switch').on('click', function (e) {
      let action = e.target.checked == true ? '<?=$tr['open']?>' : '<?=$tr['off']?>';
      let switch_to = e.target.checked == true ? 'automatic' : 'manual';
      if (confirm(`${action}-<?=$tr['Auto-audit onlinepay switch']?>?`)) {
        siteapi
          .ops_toggle_review_swtich(switch_to)
          .done(function (data) {
            alert(`<?=$tr['Success.']?> (${action})`)
          })
          .fail(function (error) {
            alert(`<?=$tr['error, please contact the developer for processing.']?>`)
            e.target.checked = !e.target.checked
          })
      } else {
        e.target.checked = !e.target.checked
      }
      auto_review = e.target.checked
      // $dataTable.column(-1).visible(!auto_review)
    });

    //api 手動審核訂單
    window.review_order = function (dom) {
      let target = $(dom)
      let id = target.data("review-id"),
        action = target.data("review-action")

      if (confirm(`<?=$tr['Whether to confirm the audit consent']?>?`)) {
        siteapi
          .ops_review(action, id)
          .done(function (data) {

            switch (action) {
              case 'agree':
                alert(`<?=$tr['seq examination passed']?> (${data.data.custom_transaction_id})`)
                break;
              case 'disagree':
                alert(`<?=$tr['application reject']?> (${data.data.custom_transaction_id})`)
            }
            $dataTable.ajax.reload();
          })
          .fail(function (error) {
            alert(`<?=$tr['error, please contact the developer for processing.']?>`)
          })
      }
    }

    //api audit
    window.siteapi = {
      ops_review: function (action, id) {
        return $.ajax({
          type: 'POST',
          url: `./depositing_siteapi_audit_action.php?a=review_order`,
          data: {
            action,
            id
          }
        })
      },
      ops_toggle_review_swtich: function (value) {
        return $.ajax({
          type: 'POST',
          url: './protal_setting_deltail_action.php?a=edit',
          data: {
            pk: 'onlinepay_review_switch',
            name: 'onlinepay_review_switch',
            value
          }
        })
      }
    }

    window.utils = {
      clip2board: function (target) {
        var TextRange = document.createRange();
        TextRange.selectNode(target);
        sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(TextRange);

        document.execCommand("copy") ? alert('<?=$tr['Success.']?>') : alert('<?=$tr['fail']?>');
      },
      toggleText: function (target) {
        target.style.display = target.style.display === 'none' ? 'block' : 'none'
      },
      // depends on moment.js
      getTime: function (alias) {
        var timezone = 'America/St_Thomas'
        var _now = moment().tz(timezone)
        var _moment = _now.clone()
        var scheme = 'YYYY-MM-DD'
        var start, end

        var week_today = _moment.format(scheme);
        switch (alias) {
          case 'now':
            scheme = 'YYYY-MM-DD HH:mm'
            start = _moment.format(scheme)
            end = _moment.format(scheme)
            break;
          case 'today':
            start = `${_moment.format(scheme)} 00:00`
            end = `${_moment.format(scheme)} 23:59`
            break
          case 'yesterday':
            _moment.add(-1, 'd')
            start = `${_moment.format(scheme)} 00:00`
            end = `${_moment.format(scheme)} 23:59`
            break;
          case 'thisweek':
            start = `${_moment.day(0).format(scheme)} 00:00`
            end = `${week_today} 23:59`

            break;
          case 'thismonth':
            start = `${_moment.date(1).format(scheme)} 00:00`
            end = `${week_today} 23:59`

            break;
          case 'lastmonth':
            end = `${_moment.date(1).add(-1, 'd').format(scheme)} 23:59`
            start = `${_moment.date(1).format(scheme)} 00:00`
            break;
          default:
        }
        return {
          _now,
          start,
          end,
          breakpoint: _now.format(scheme)
        }
      }
    }

    //api 訂單查詢
    $('#api_query').on('show.bs.modal', function (e) {
      ajax_api_query = $.ajax({
        url: './depositing_siteapi_audit_action.php',
        data: {
          'api_query': $(e.relatedTarget).attr("data-orderno"),
          a: 'api_query_order'
        },
        success: function (res) {
          $('#api_query .modal-body').html(res);
        },
        error: function (xhr) {
          $('#api_query .modal-body').html('(x)<?=$tr['error, please contact the developer for processing.']?>');
        },
      });
    });

    $('#api_query').on('hidden.bs.modal', function (e) {
      if (ajax_api_query) { ajax_api_query.abort(); }
      $('#api_query .modal-body').html('<img class="mr-2" height="20px" src="./ui/loading_hourglass.gif" alt=""><?=$tr['loading']?>');
    });

  })
</script>
<?php end_section()?>
