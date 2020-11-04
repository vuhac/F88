<?php use_layout("template/s2col.tmpl.php");?>

<?php begin_section('html_meta_title')?>
<?php echo $tr['Transaction Statistics report'] . '-' . $tr['host_name'] ?>
<?php end_section()?>

<?php begin_section('page_title');?>
<ol class="breadcrumb">
  <li>
    <a href="home.php"><?=$tr['Home'];?></a>
  </li>
  <li>
    <a href="#"><?=$tr['Account Management'];?></a>
  </li>
  <li class="active"><?=$tr['Transaction Statistics report'];?></li>
</ol>
<?php end_section();?>

<?php begin_section('extend_head');?>
<!-- 參考使用 datatables 顯示 -->
<!-- https://datatables.net/examples/styling/bootstrap.html -->
<link
  rel="stylesheet"
  type="text/css"
  href="./in/datatables/css/jquery.dataTables.min.css?v=180612"
/>

<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet" />
<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>

<script
  type="text/javascript"
  language="javascript"
  src="./in/datatables/js/jquery.dataTables.min.js?v=180612"
></script>
<script
  type="text/javascript"
  language="javascript"
  src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"
></script>

<script type="text/javascript" language="javascript" class="init">
  var utils = {
    downloadXls: function ({ data, url, success }) {
      var xhr = new XMLHttpRequest();
      xhr.open("POST", url, true);
      xhr.responseType = "blob";
      xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      xhr.onload = function () {
        console.log(this.response);
        success(this.response, this.status, this);
      };
      xhr.send($.param(data));
    },
    getTime: function (alias) {
      var timezone = "America/St_Thomas";
      var _now = moment().tz(timezone);
      var _moment = _now.clone();
      var scheme = "YYYY-MM-DD";
      var start, end;

      var week_today = _moment.format(scheme);

      switch (alias) {
        case "now": 
          scheme = "YYYY-MM-DD HH:mm:ss";
          start = _moment.format(scheme);
          end = _moment.format(scheme);
          break;
        case "today":
          start = _moment.format(scheme);
          end = _moment.format(scheme);
          break;
        case "yesterday":
          _moment.add(-1, "d");
          start = _moment.format(scheme);
          end = _moment.format(scheme);
          break;
        case "thisweek":
          start = _moment.day(0).format(scheme);
          end = week_today;// 到今天

          // start = _moment.day(0).format(scheme);
          // end = _moment.day(6).format(scheme);
          break;
        case "thismonth":
          start = _moment.date(1).format(scheme);
          end = week_today;

          // end = _moment.add(1, "M").add(-1, "d").format(scheme);


          break;
        case "lastmonth":
          end = _moment.date(1).add(-1, "d").format(scheme);
          start = _moment.date(1).format(scheme);
          break;
        default:
      }
      return {
        _now,
        start,
        end,
        breakpoint: _now.format(scheme),
      };
    },
  };

  var get_custom_search_data = function () {
    return {
      account: $("#dl_transaction_csv [name=account]").val(),
      account_type: $("#dl_transaction_csv [name^=account_type]:checked")
        .map(function () {
          return $(this).val();
        })
        .toArray()
        .join(","),
      agent: $("#dl_transaction_csv [name=agent_account]").val(),
      sdate: $("#dl_transaction_csv [name=sdate]").val(),
      edate: $("#dl_transaction_csv [name=edate]").val(),
      need_filter: $("#dl_transaction_csv [name=need_filter]").prop("checked"),
    };
  };
</script>
<?php end_section();?>

<?php begin_section('extend_js');?>
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
      timepicker: false,
      format: "Y-m-d",
      step: 1,
      minDate:"<?php echo gmdate('Y-m-d',strtotime('- 2 month'));?>",
      maxDate:"<?php echo gmdate('Y-m-d',time()+ -4*3600);?>",
    });

    $("#csvDownloadBtn").on("click", function (e) {
      utils.downloadXls({
        url: "transaction_statistics_action.php?a=fetch&format=xls",
        data: {
          custom_search: get_custom_search_data(),
        },
        success: function (response, status, xhr) {
          var content_disposition = xhr.getResponseHeader(
            "Content-Disposition"
          );
          var _filename = content_disposition.replace(
            /.*filename="(.*)".*/,
            "$1"
          );
          var _blobUrl = URL.createObjectURL(xhr.response);
          var _fileLink = document.createElement("a");
          _fileLink.href = _blobUrl;
          _fileLink.download = _filename;
          $("#preview_result").html($(_fileLink));
          _fileLink.click();
        },
      });
    });
  });

  // 本日、昨日、本周、上周、上個月button
  function settimerange(alias,text) {
    _time = utils.getTime(alias);
    // 單號申請時間
    $("#dl_transaction_csv [name=sdate]").val(_time.start);
    $("#dl_transaction_csv [name=edate]").val(_time.end);
    //更換顯示到選單外 20200525新增
    var currentonclick = $('.'+text+'').attr('onclick');
    var currenttext = $('.'+text+'').text();
		//first change
    $('.application .first').removeClass('week month');
    $('.application .first').attr('onclick',currentonclick);
    $('.application .first').text(currenttext); 
  }

  $(function () {
    window.$dataTable = $("#show_list").DataTable({
      pageLength: 10,
      order: [0, "desc"],
      bProcessing: true,
      bServerSide: true,
      bRetrieve: true,
      searching: false,
      ajax: {
        url: "transaction_statistics_action.php?a=fetch&format=json",
        type: "POST",
        // data: {
        //   custom_search: function () { return JSON.stringify(get_custom_search_data()) }
        // },
        data: function (d) {
          return $.extend({}, d, {
            custom_search: get_custom_search_data(),
          });
        },
      },
      columns: [
        {
          className: "dt-right",
          name: "account",
          width: "7.5%",
          data: function (row) {
            return $("<a/>", {
              target: "_BLANK",
              href: "member_account.php?a=" + row.member_id,
              title: "<?php echo $tr['Check membership details'] ?>",
              html: row.account,
            }).prop("outerHTML");
          },
        },
        {
          className: "dt-center",
          data: function (row) {
            return $("<a/>", {
              class: "btn btn-info btn-xs",
              role: "button",
              href: "javascript:void(0)",
              html: row.therole,
            }).prop("outerHTML");
          },
          name: "therole",
        },
        {
          className: "dt-center",
          name: "company_deposits_count",
          data: "company_deposits_count",
        },
        {
          className: "dt-center",
          name: "company_deposits_amount",
          data: "company_deposits_amount",
        },
        {
          className: "dt-center",
          name: "api_deposits_count",
          data: "api_deposits_count",
        },
        {
          className: "dt-center",
          name: "api_deposits_amount",
          data: "api_deposits_amount",
        },
        // {
        //   className: "dt-center",
        //   name: "cashtransfer_count",
        //   data: "cashtransfer_count"
        // },
        // {
        //   className: "dt-center",
        //   name: "cashtransfer_amount",
        //   data: "cashtransfer_amount"
        // },
        // {
        //   className: "dt-center",
        //   name: "cashadministrationfees_count",
        //   data: "cashadministrationfees_count"
        // },
        {
          className: "dt-center",
          name: "cashadministrationfees_amount",
          data: "cashadministrationfees_amount",
        },
        {
          className: "dt-center",
          name: "cashwithdrawal_count",
          data: "cashwithdrawal_count",
        },
        {
          className: "dt-center",
          name: "cashwithdrawal_amount",
          data: "cashwithdrawal_amount",
        },
        // {
        //   className: "dt-center",
        //   name: "apicashwithdrawal_count",
        //   data: "apicashwithdrawal_count"
        // },
        // {
        //   className: "dt-center",
        //   name: "apicashwithdrawal_amount",
        //   data: "apicashwithdrawal_amount"
        // },
        // {
        //   className: "dt-center",
        //   name: "cashgtoken_count",
        //   data: "cashgtoken_count"
        // },
        // {
        //   className: "dt-center",
        //   name: "cashgtoken_amount",
        //   data: "cashgtoken_amount"
        // },
        {
          className: "dt-center",
          name: "tokendeposit_count",
          data: "tokendeposit_count",
        },
        {
          className: "dt-center",
          name: "tokendeposit_amount",
          data: "tokendeposit_amount",
        },
        // {
        //   className: "dt-center",
        //   name: "tokenfavorable_count",
        //   data: "tokenfavorable_count"
        // },
        {
          className: "dt-center",
          name: "tokenfavorable_amount",
          data: "tokenfavorable_amount",
        },
        // {
        //   className: "dt-center",
        //   name: "tokenpreferential_count",
        //   data: "tokenpreferential_count"
        // },
        {
          className: "dt-center",
          name: "tokenpreferential_amount",
          data: "tokenpreferential_amount",
        },
        // {
        //   className: "dt-center",
        //   name: "tokenpay_count",
        //   data: "tokenpay_count"
        // },
        {
          className: "dt-center",
          name: "tokenpay_amount",
          data: "tokenpay_amount",
        },
        {
          className: "dt-center",
          name: "tokengcash_amount",
          data: "tokengcash_amount",
        },
        {
          className: "dt-center",
          name: "tokenadministrationfees_count",
          data: "tokenadministrationfees_count",
        },
        {
          className: "dt-center",
          name: "tokenadministrationfees_amount",
          data: "tokenadministrationfees_amount",
        },
        {
          className: "dt-center",
          name: "deposit_summary",
          data: "deposit_summary",
        },
        {
          className: "dt-center",
          name: "withdrawal_summary",
          data: "withdrawal_summary",
        },
        {
          className: "dt-center",
          name: "diff_summary",
          data: "diff_summary",
        },
      ],
    });
    $("#submit_to_inquiry").on("click", function () {
      $dataTable.ajax.reload();
    });
  });
</script>
<?php end_section();?>

<?php begin_section('indextitle_content');?>
<span class="glyphicon glyphicon-search" aria-hidden="true"></span>
<?=$tr['Search criteria'];?>
<?php end_section();?>

<?php begin_section('indexbody_content');?>
<form
  id="dl_transaction_csv"
  name="dl_transaction_csv"
  action="transaction_statistics_action.php?a=fetch&format=xls"
  method="post"
>
  <div class="row">
    <div class="col-12">
      <label for="account"><?=$tr['Account'];?></label>
    </div>
    <div class="col-12 form-group">
      <input
          type="text"
          class="form-control"
          name="account"
          id="account"
          placeholder="<?=$tr['Account'];?>"
        />
    </div>
  </div>
  <div class="row">
    <div class="col-12">
      <label for="account_type"><?=$tr['Type of account'];?></label>
    </div>
    <div class="col-12 form-group">
      <div class="form-check form-check-inline">
        <input type="checkbox" name="account_type[]" value="M" checked />
        <span><?=$tr['member'];?></span>
      </div>
      <div class="form-check form-check-inline">
      <input type="checkbox" name="account_type[]" value="A" checked />
      <span><?=$tr['agent'];?></span>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <label for="account_query"
        ><?=$tr['agent'];?>
        <span
          class="glyphicon glyphicon-info-sign"
          title="<?=$tr['Statistics by agent when inquiring include the statistics of agents themselves and their level off-line'];?>"
        >
        </span>
      </label>
    </div>
    <div class="col-12 form-group">
      <input
        type="text"
        class="form-control"
        name="agent_account"
        id="agent_account"
        placeholder="<?=$tr['agent'];?>"
      />
    </div>
  </div>

  <!-- <div class="row pb-3">
    <div class="col-12">
      <label><?=$tr['Starting time'];?></label>
    </div>
    <div class="col-12">
      <input type="text" class="form-control" name="sdate" placeholder="ex:2017-01-20" required data-type="dateYMD" autocomplete="off"/>
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <label><?=$tr['End time'];?></label>
    </div>
    <div class="col-12">
      <input type="text" class="form-control" name="edate" placeholder="ex:2017-01-20" required data-type="dateYMD" autocomplete="off"/>
    </div>
  </div>
  <hr /> -->

  <div class="row">
    <div class="col-12 d-flex">
      <label><?=$tr['application time']?></label>
      <div
        class="btn-group btn-group-sm ml-auto application"
        role="group"
        aria-label="Button group with nested dropdown"
      >
        <button
          type="button"
          class="btn btn-secondary first"
        >
          <?=$tr['grade default'];?>
        </button>

        <div class="btn-group btn-group-sm" role="group">
          <button
            id="btnGroupDrop1"
            type="button"
            class="btn btn-secondary dropdown-toggle"
            data-toggle="dropdown"
            aria-haspopup="true"
            aria-expanded="false"
          ></button>
          <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
            <a class="dropdown-item week" onclick="settimerange('thisweek','week')"
              ><?=$tr['This week'];?>
            </a>
            <a class="dropdown-item month" onclick="settimerange('thismonth','month')"
              ><?=$tr['this month'];?>
            </a>
            <a class="dropdown-item today" onclick="settimerange('today','today')"
              ><?=$tr['Today'];?>
            </a>
            <a class="dropdown-item yesterday" onclick="settimerange('yesterday','yesterday')"
              ><?=$tr['yesterday'];?></a
            >
            <a class="dropdown-item lastmonth" onclick="settimerange('lastmonth','lastmonth')"
              ><?=$tr['last month'];?></a
            >
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 form-group rwd_doublerow_time">
      <div class="input-group">
        <div class="input-group-prepend">
					<span class="input-group-text"><?=$tr['start'];?></span>
				</div>
        <input
          type="text"
          class="form-control"
          name="sdate"
          placeholder="ex:2017-01-20"
          required
          data-type="dateYMD"
          autocomplete="off"
          value="<?=gmdate('Y-m-d', strtotime('- 7 days'))?>"
        />
      </div>
      <div class="input-group">
        <div class="input-group-prepend">
						<span class="input-group-text"><?=$tr['end'];?></span>
				</div>
        <input
          type="text"
          class="form-control"
          name="edate"
          placeholder="ex:2017-01-20"
          required
          data-type="dateYMD"
          autocomplete="off"
          value="<?=gmdate('Y-m-d', time()+-4 * 3600)?>"
        />
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <label><?=$tr['option'];?></label>
    </div>
    <div class="col-12">
      <label class="ml-3">
        <input type="checkbox" name="need_filter" value="true" checked />
        <?=$tr['Filter out users who have no deposits and withdrawals'];?>
      </label>
    </div>
  </div>
  <input type="hidden" name="action" value="getCSV" />
</form>
<hr />
<div class="row">
  <div class="col-12 col-md-12">
    <button
      id="submit_to_inquiry"
      class="btn btn-success btn-block"
      type="submit"
    >
      <?=$tr['Inquiry']?>
    </button>
  </div>
</div>
<?php end_section();?>

<?php begin_section('paneltitle_content');?>
<span class="glyphicon glyphicon-list" aria-hidden="true"></span>
<?=$tr['Query results'];?>
<div id="csv" style="float: right; margin-bottom: auto;">
  <a
    href="#"
    class="btn btn-success btn-sm"
    id="csvDownloadBtn"
    role="button"
    aria-pressed="true"
    target="_SELF"
    ><?=$tr['Export Excel'];?></a
  >
</div>
<?php end_section();?>

<?php begin_section('panelbody_content');?>
<div style="overflow: auto; max-width: 1523px;">
  <div class="row">
    <div class="col-12">
      <table id="show_list" class="display" cellspacing="0" width="100%">
        <thead>
          <tr>
            <?php foreach ($tx_statistics_cols as $tx_statistics_col): ;?>
            <th>
              <?=$tx_statistics_col['title'];?>
            </th>
            <?php endforeach;?>
          </tr>
        </thead>
        <tfoot>
          <tr>
            <?php foreach ($tx_statistics_cols as $tx_statistics_col): ;?>
            <th>
              <?=$tx_statistics_col['title'];?>
            </th>
            <?php endforeach;?>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
<br />
<div class="row">
  <div id="preview_result"></div>
</div>
<?php end_section();?>
