<?php use_layout("template/beadmin_fluid.tmpl.php"); ?>

<!-- begin of extend_head -->
<?php begin_section('extend_head'); ?>
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
<script src="./in/jquery.blockUI.js"></script>
<?php end_section(); ?>
<!-- end of extend_head -->


<!-- begin of page_title -->
<?php begin_section('page_title'); ?>
<ol class="breadcrumb">
  <li><a href="home.php"><?php echo $tr['Home']; ?></a></li>
  <li><a href="#"><?php echo $tr['profit and promotion']; ?></a></li>
  <li class="active"><?php echo $function_title; ?></li>
</ol>
<?php end_section(); ?>
<!-- end of page_title -->


<!-- begin of panelbody_content -->
<?php begin_section('panelbody_content'); ?>

<div class="row">

  <!-- show tips -->
  <div class="col-12 col-md-12">
    <div class="alert alert-default">
    <p>* <?php echo $tr['The current query date is'].' '.$current_datepicker_start.' ~ '.$current_datepicker.' '.$tr['The bonus report for the US East Time'].'(UTC -04)，'.$tr['Daily settlement time range is'].' '.$current_datepicker.' 00:00:00 -04 ~ '.$current_datepicker.' 23:59:59 -04'; ?> </p>
    <p>* <?php echo $tr['correspond Taiwan Standard Time'].'(UTC +08)'.$tr['The range is'].'：'.date( "Y-m-d", strtotime( "$current_datepicker -1 day")).' 13:00:00+08 ~ '.$current_datepicker.' 12:59:59+08'; ?></p>
    <p>* <?php echo $tr['If you need to update the data, you need to be more'].'<a href="statistics_daily_report.php" target="_BLANK">'.$tr['Daily Revenue Statement'].'</a>，'.$tr['Followed by the order'].'(1)'.$tr['Settlement date information update'].'(2)'.$tr['Individual bonus commission update,Can get the latest information'].'。'; ?></p>
    <p><?php echo $radiation_bonus_rule_html; ?></p>
    </div>
  </div>

  <!-- show dateselector -->
  <div class="col-12 col-md-12">
    <form class="form-inline" method="get">
      <div class="form-group">
        <div class="input-group">
          <div class="input-group-addon"><?php echo $tr['specified trial interval'];?></div>
          <input type="text" class="form-control" placeholder="<?php echo $tr['Starting time']; ?>" aria-describedby="basic-addon1" id="date_start_time" value="<?php echo $current_datepicker_start; ?>">
          <span class="input-group-addon" id="basic-addon1">~</span>
          <input type="text" class="form-control" placeholder="<?php echo $tr['End time']; ?>" aria-describedby="basic-addon1" id="date_end_time" value="<?php echo $current_datepicker_end; ?>">
        </div>
      </div>
      <button id="calculate_franchise_bonus" class="btn btn-primary ml-2"><?php echo $tr['trial calculation'];?></button>
      <button id="download_summary_btn" class="btn btn-success invisible ml-2"><?php echo  $tr['download'];$tr['summary table'];?></button>
      <button id="download_detail_btn" class="btn btn-success invisible ml-2"><?php echo $tr['download detail'];?></button>
    </form>
    <hr>
    <div id="downloader"></div>
  </div>

  <!-- show summary -->
  <div id="show_summary" class="col-xs-6 col-md-3">
  </div>

  <!-- show list -->
	<div class="col-12 col-md-12">
	</div>

</div>
<br>
<div class="row">
	<div id="preview_result"></div>
</div>

<?php end_section(); ?>
<!-- end of panelbody_content -->


<!-- begin of extend_js -->
<?php begin_section('extend_js'); ?>
<script>
function summaryTmpl(summary) {
  return `
  <table class="table table-bordered">
    <tr class="active">
      <th>区间</th>
      <th class="text-right">${summary.date_range}</th>
    </tr>
    <tr>
      <td><?php echo $tr['The total number of'];?></td>
      <td align="right">${summary.total_count}</td>
    </tr>
    <tr>
      <td>代理加盟奖金合计</td>
      <td align="right">$${summary.total_franchise_bonus}</td>
    </tr>
  </table>
  `
}

function exportCSV(filename, data) {
  var a = document.createElement('a');
  var blob = new Blob([data], {type: "text/csv"});
  var url = window.URL.createObjectURL(blob);

  a.href = url;
  a.download = filename;
  // for firefox
  $('#downloader').html($(a));
  a.click();

  window.URL.revokeObjectURL(url)
}

function downloadDetailCSV(date_begin, date_end) {
  $.ajax({
    method : 'POST',
    dataType: 'json',
    data: {date_begin: date_begin, date_end: date_end},
    url: 'radiationbonus_organization_action.php?a=download_detail_csv'
  }).success(function(data) {
    exportCSV(data.file_name, data.csv_string);
  });
}

function bindDownloadCSVEvents(date_begin, date_end, data) {
  $("#download_summary_btn")
    .removeClass('invisible')
    .off('click.download-csv')
    .on('click.download-csv', function(e) {
      e.preventDefault();
      exportCSV(data.summary_file_name, data.summary_csv_string);
    });

  $("#download_detail_btn")
    .removeClass('invisible')
    .off('click.download-csv')
    .on('click.download-csv', function(e) {
      e.preventDefault();
      downloadDetailCSV(date_begin, date_end);
    });
}

$(document).ready(function() {
  $( "#date_start_time, #date_end_time, #franchise_update_time" ).datepicker({
    maxDate: "+0d",
    minDate: "-13w",
    showButtonPanel: true,
    dateFormat: "yy-mm-dd",
    changeMonth: true,
    changeYear: true
  });

  $('.js-franchise-update').on('click', function(e) {
    e.preventDefault();
    $(e.currentTarget).prop('disable', true);

    var update_dailydate = $('#franchise_update_time').val();
    var confirm_text ='你确定要产生/更新日期为'+update_dailydate+'的个人分红佣金?';
    var r = confirm(confirm_text);
    if (r === true) {
      var gotourl   = 'radiationbonus_organization_action.php?a=cmdrun&d='+update_dailydate;
      var win_title = '更新生成个人分红佣金报表';
      var wait_text = '<div style="width: 100%;		height: 100vh;		display: flex;		justify-content: center;		align-items: center;		overflow: hidden;">执行中，1000笔纪录约需60秒。请勿关闭视窗.<img src="./ui/loading.gif"></div>';
      myWindow = window.open('', win_title, 'status=yes,resizable=yes,top=0,left=0,height=600,width=800', false);
      myWindow.document.write(wait_text);
      myWindow.moveTo(0,0);
      myWindow = window.open(gotourl, win_title, 'status=yes,resizable=yes,top=0,left=0,height=600,width=800', false);
      myWindow.focus();
    }else{
      // user cancel
    }

    $(e.currentTarget).prop('disable', false);

  });

  $("#calculate_franchise_bonus").on('click', function(e) {
    e.preventDefault();
    $(e.currentTarget).prop('disable', true);
    var date_begin = $('#date_start_time').val();
    var date_end = $('#date_end_time').val();

    $.ajax({
      method : 'POST',
      dataType: 'json',
      data: {date_begin: date_begin, date_end: date_end},
      url: 'radiationbonus_organization_action.php?a=calculate_franchise_bonus'
    }).success(function(data) {
      $("#show_summary").html(summaryTmpl(data.summary));
      $(e.currentTarget).prop('disable', false);
      if(data.summary.total_count <= 0) {
        $("#download_summary_btn, #download_detail_btn")
          .addClass('invisible')
          .off('click.download-csv');
        return;
      }
      bindDownloadCSVEvents(date_begin, date_end, data);
    });

  });

});

</script>
<?php end_section(); ?>
<!-- end of extend_js -->
