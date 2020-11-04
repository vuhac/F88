actor_management_view.php -->
<?php use_layout("template/beadmin.tmpl.php"); ?>

<!-- begin of extend_head -->
<?php begin_section('extend_head'); ?>
<!-- Jquery UI js+css  -->
<script src="in/jquery-ui.js"></script>
<link rel="stylesheet"  href="in/jquery-ui.css" >
<!-- Jquery blockUI js  -->
<script src="./in/jquery.blockUI.js"></script>
<!-- jquery datetimepicker js+css -->
<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
<!-- Datatables js+css  -->
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
<!-- 自訂css -->
<link rel="stylesheet" type="text/css" href="ui/style_seting.css">

<style type="text/css">
.input-group>.custom-select:not(:first-child), .input-group>.form-control:not(:first-child){
  border-radius:3px;
}
</style>

<?php end_section(); ?>
<!-- end of extend_head -->


<!-- begin of page_title -->
<?php begin_section('page_title'); ?>
<ol class="breadcrumb">
  <li><a href="home.php"><?php echo $tr['Home']; ?></a></li>
  <li><a href="#"><?php echo $tr['webmaster']; ?></a></li>
  <li class="active"><?php echo $function_title; ?></li>
</ol>
<?php end_section(); ?>
<!-- end of page_title -->

<!-- 主要內容  title -->
<!-- begin of paneltitle_content -->
<?php begin_section('paneltitle_content'); ?>
<i class="fas fa-user-lock"></i><?php echo $function_title; ?>
<?php end_section(); ?>
<!-- end of paneltitle_content -->

<!-- 主要內容 content -->
<!-- begin of panelbody_content -->
<?php begin_section('panelbody_content'); ?>
<form class="form form-inline"  id="form_main" autocomplete="Off" >
    <div class="input-group col-3.5">
        <span class=" form-control-lg"><?php echo $tr['account id']; ?></span>
        <input type="hidden" name="get" value="query_auth">
        <input type="text" name="account" class="form-control form-control-lg" placeholder="<?php echo $tr['account id'];?> <?php echo $tr['search']; ?>">
    </div>
    <div class="input-group col-3.5">
      <label><input class="input role ml-3 mr-1" type="checkbox" name="role[]" value="M"><?php echo $tr['member'];?></label>
      <label><input class="input role ml-3 mr-1" type="checkbox" name="role[]" value="A"><?php echo $tr['agent'];?></label>
      <label><input class="input role ml-3 mr-1" type="checkbox" name="role[]" value="R"><?php echo $tr['administrator'];?></label>
    </div>
  <div class="col-4 ml">
    <button class="btn btn-primary" id="submit_to_inquiry"><?php echo $tr['search']; ?></button>
  </div>
  <!-- <div class="col-1 ml-auto">
    <a class="btn btn-success float-right" href="./admin_management_create.php" role="button" >
      <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>新增管理员</a>
  </div> -->
</form>
<hr>

<div id="inquiry_result_area">
<table id="show_list"  class="display" cellspacing="0" width="100%" >
  <thead>
    <tr>
      <th><?php echo $tr['ID']; ?></th>
      <th><?php echo $tr['account id']; ?></th>
      <th><?php echo $tr['last update time']; ?></th>
      <th><?php echo $tr['two-factor authentication']; ?></th>
      <th><?php echo $tr['ip whitelisting']; ?></th>
      <th><?php echo $tr['edit']; ?></th>
    </tr>
  </thead>
  <tfoot>
    <tr>
      <th><?php echo $tr['ID']; ?></th>
      <th><?php echo $tr['account id']; ?></th>
      <th><?php echo $tr['last update time']; ?></th>
      <th><?php echo $tr['two-factor authentication']; ?></th>
      <th><?php echo $tr['ip whitelisting']; ?></th>
      <th><?php echo $tr['edit']; ?></th>
    </tr>
  </tfoot>
</table>
</div>
<br>
<div class="row">
  <div id="preview_result"></div>
</div>
<?php end_section(); ?>
<!-- end of panelbody_content -->



<!-- begin of extend_js -->
<?php begin_section('extend_js'); ?>
<script type="text/javascript" language="javascript">
function paginateScroll() { // auto scroll to top of page
  $("html, body").animate({
     scrollTop: 0
  }, 100);
}


$(function() {
  $("#show_list").DataTable({
      "bProcessing": true,
      "bServerSide": true,
      "bRetrieve": true,
      "searching": false,
      "aaSorting": [[ 2, "desc" ]],
      "ajax": "member_authentication_action.php?get=query_auth<?php echo $query_sql; ?>",
			// "dom": "<'row'<'col'lf>>rtip",
      "oLanguage": {
        "sSearch": "<?php echo $tr['Account'] ;?>",//"会员帐号:",
        "sEmptyTable": "<?php echo $tr['no data'];?>",//"目前没有资料!",
        "sLengthMenu":  "<?php echo $tr['each page'];?> _MENU_ <?php echo $tr['Count'];?>",//"页 _MENU_ 笔",
        "sZeroRecords": "<?php echo $tr['no data'];?>",//"目前没有资料",
        "sInfo": "<?php echo $tr['now at'];?> _PAGE_ <?php echo $tr['total'];?> _PAGES_ <?php echo $tr['page'];?>",//"目前在第 _PAGE_ 页，共 _PAGES_ 页",
        "sInfoEmpty": "<?php echo $tr['no data'];?>",//"目前没有资料",
        "sInfoFiltered": "(<?php echo $tr['from'];?> _MAX_ <?php echo $tr['filtering in data'];?>)",//"(从 _MAX_ 笔资料中过滤)",
        "oPaginate": {
          "sPrevious": "<?php echo $tr['previous'];?>",//"上一页",
          "sNext": "<?php echo $tr['next'];?>"//"下一页"
        }
      },
      "columnDefs": [
          {className: "dt-center", "targets": [0,1,2,3,4,5] },
          {"orderable": false, "targets": 5 }
      ],
      "columns": [
        { "data": "id"},
        { "data": "account"},
        { "data": "update_time"},
        { "data": "2fa"},
        { "data": "ipwhitelist"},
        { "data": "opt"}
      ]
  });


  $("#submit_to_inquiry").click(function(e){
    e.preventDefault();
    // var account_query = $("#account_query").val();
    // var query_date_start_datepicker = $("#query_date_start_datepicker").val();
    // var query_date_end_datepicker = $("#query_date_end_datepicker").val();
    // var gc_query = $("#gc_query").val();
    // var casino_query = $("#casino_query").val();
    // var updating_str = `
    // <h5 align="center">
    //   <?php echo $tr['Data query']; ?>...<img width="30px" height="30px" src="ui/loading.gif" />
    // </h5>
    // `;
    var q_str=$("#form_main").serialize();
    console.log(q_str);
    // var query_str = "&a="+account_query+"&sdate="+query_date_start_datepicker+"&edate="+query_date_end_datepicker+"&gc="+gc_query+"&casino="+casino_query+status_query;

    // $('.modal-btn').attr('disabled','disabled');

    // $("#show_summary").html(updating_str);
    // $.get(
    //   "member_betlog_action.php?get=query_summary"+query_str,
    //   function(result){
    //     if(!result.logger){
    //       $("#show_summary").html(summary_tmpl(result));
    //       $("#csv").html(csv_download_tmpl(result));
    //     }else{
    //       alert(result.logger);
    //     }
    //   },
    //   'json'
    // );

    $("#show_list")
      .DataTable()
      .ajax.url("member_authentication_action.php?"+q_str)
      .load();

    paginateScroll();
  });

  //  // 刪除
  // $('#show_list').on('click', '.delete_btn', function() {
  //   // 使用 ajax 送出 post
  //   var id = $(this).val();

  //   if(id != '') {
  //     if(confirm("<?php echo $tr['OK to delete']; ?> ?") == true) {
  //       $.ajax ({
  //         url: 'actor_management_action.php?post=delete',
  //         type: 'POST',
  //         data: ({
  //           id: id
  //         }),
  //         success: function(response){
  //           $('#preview_result').html(response);
  //         },
  //         error: function (error) {
  //           $('#preview_result').html(error);
  //         },
  //       });
  //     }
  //   }else{
  //     alert("<?php echo $tr['Illegal test']; ?>");
  //   }
  // });

  //  // 修改狀態
  // $("#show_list").on('click', '.checkbox_switch', function() {
  //   // 使用 ajax 送出 post
  //   var id = $(this).val();
  //   if(id != '') {

  //     if($("#actor_switch"+id).prop('checked')) {
  //       var status = 1;
  //     }else{
  //       var status = 0;
  //     }

  //     $.ajax ({
  //       url: "admin_management_action.php?post=edit_status",
  //       type: "POST",
  //       data: ({
  //         id: id,
  //         status: status
  //       }),
  //       success: function(response){
  //         $('#preview_result').html(response);
  //         // window.location.href="actor_management.php";
  //       },
  //       error: function (error) {
  //         $("#preview_result").html(error);
  //       },
        
  //     });
  //   }
  // });

  $(document).keydown(function(e) {
    if(!$('.modal').hasClass('show')){
     switch(e.which) {
         case 13: // enter key
             $("#submit_to_inquiry").trigger("click");
         break;
     }
   }
  });


});

</script>
<?php end_section(); ?>
<!-- end of extend_js