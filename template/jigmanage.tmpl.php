<?php
// ----------------------------------------------------------------------------
// Features:	後台--樣本檔 2 欄位形式
// File Name:	s2col.tmpl.php
// Author:		Barkley
// Related:
// Log: 2016.11.23 fix, 2 欄位格式樣板。
//
// ----------------------------------------------------------------------------


// 有變數才執行，沒有變數就是不正常進入此 tmpl 檔案
if(isset($tmpl)) {
	// 正常
}else{
	die('ERROR tmpl');
}


?>


<!DOCTYPE html>
<html lang="en">
  <head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="shortcut icon" href="favicon.ico">
	<meta name="description" content="<?php echo $tmpl['html_meta_description']; ?>">
	<meta name="author" content="<?php echo $tmpl['html_meta_author']; ?>" >
	<title><?php echo $tmpl['html_meta_title']; ?></title>

	<?php echo assets_include(); ?>

	<?php echo $tmpl['extend_head']; ?>
	<style>		
		@media (min-width: 768px) {
			.searchcollapsed{
				writing-mode: vertical-lr;
				-webkit-writing-mode: vertical-lr;
				white-space: nowrap;
				-webkit-white-space: nowrap;
			}
		}
		
	</style>

	</head>

	<body>
	<div class="nav_content d-lg-block d-none">
		<div class="container">
		<div class="d-flex bd-highlight">
			<div class="d-flex align-items-center bd-highlight">
				<?php
				// 語言切換 -- 靠右 in language.php
				echo menu_language_choice();
				// 時間
				echo agent_menutop_time();
				?>
			</div>
			<div class="ml-auto d-flex bd-highlight">
			<ul class="nav_right_content">
				<?php
					//站內訊息公告
					echo agent_menumessage_member();
					//線上人數
					echo agent_menutop_people();

					// 切換使用者
					echo agent_menutop_member();
				?>
			</ul>
			</div>
		</div>
		</div>
	</div>
	<nav class="navbar navbar-expand-lg navbar-dark navbar_list">
  	<div class="container">
         	<a class="navbar-brand logo" href="home.php" title="<?php echo $tr['Console']; ?>">
				<?php
				echo $config['hostname'];
				?>
     		</a>
          <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
            <span class="sr-only">Toggle navigation</span>
            <span class="navbar-toggler-icon"></span>
          </button>

						<div id="navbar" class="collapse navbar-collapse">
		        <?php
		        // 代理商選單 - 靠左 in lib.php
						echo agent_menu();
						?>
						<div class="nav navbar-nav phone_menu_style d-lg-none">
						<?php
							// 語言切換 -- 靠右 in language.php
							echo menu_language_choice();
						?>
						</div>
						<ul class="nav navbar-nav phone_user_style d-lg-none">
						<?php
							// 切換使用者
							echo agent_menutop_member();
						?>
						</ul>
					</div>
    </div>
  </nav>

	<div class="container-fluid">

		<div class="row">
			<div class="col-12 col-md-12">
				<?php
				// title 頁面大標題及說明文字
				echo $tmpl['page_title']
				?>
			</div>
		</div>
		<div class="row">
			<div class="col-12 col-md-12">
				<div id="app"></div>
				<!-- built files will be auto injected -->
				
			</div>
		</div>

		<div class="panel panel-default">
			<div class="panel-footer">
				<?php
				// 頁腳顯示
				echo page_footer();
				?>
			</div>
		</div>

	</div>
	<?php
	// Javascript
	echo $tmpl['extend_js']; ?>
	<script>
		$( document ).ready(function() {
			$('#searchindex').on('hidden.bs.collapse', function (e) {
				if($(e.target).attr('id')=='searchindex'){
					$('#ls').removeClass("col-md-3 col-lg-2");
					$('#ls').addClass("col-md-auto");
					$('#collapsetitle').addClass("searchcollapsed");
					$('#collapsebutton').hide();
				}
			})
			$('#searchindex').on('show.bs.collapse', function (e) {
				if($(e.target).attr('id')=='searchindex'){
					$('#ls').removeClass("col-md-auto");
					$('#ls').addClass("col-md-3 col-lg-2");
					$('#collapsetitle').removeClass("searchcollapsed");
			   		$('#collapsebutton').show();
				}			   
			})
		});
		
	</script>

	<?php echo footer_include(); ?>
</body>
</html>
