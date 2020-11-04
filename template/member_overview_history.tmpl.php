	<?php
		// ----------------------------------------------------------------------------
		// Features:	後台--樣本檔 1 欄位形式
		// File Name:	member_overview_history.tmpl.php
		// Author:		shiuan
		// Related:
		// Log: 2020.03.17 fix, 1 欄位格式樣板。
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
				<div class="col-12">				
					<ol class="breadcrumb">
						<li><a href="home.php"><?php echo $tr['Home']; ?></a></li>
						<li><a href="member_overview.php">會員總覽</a></li>
						<li class="active">歷程記錄</li>
					</ol>
				</div>
				<div class="col-12">
					<div class="panel panel-default">
						<div class="panel-heading">
							<h3 class="panel-title d-flex align-items-center">
							<a href="member_overview.php" class="btn btn-outline-secondary btn-sm mr-2 rounded-pill"><i class="fas fa-reply mr-2"></i>返回</a>
								<?php
								// 工作區標題
								echo $_GET['a'].' 歷程記錄';
								?>
								<div id="csv"  style="float:right;margin-bottom:auto"></div>
							</h3>
						</div>

						<div class="panel-body">						
							<div class="row">
								<div id="ls" class="col-12 col-md-3 col-lg-2">
									<div class="panel panel-default">
									<div class="panel-heading d-flex justify-content-between" data-toggle="collapse" data-target="#searchindex">
										<div id="collapsetitle" class="panel-title">
											<span class="glyphicon glyphicon-search" aria-hidden="true"></span>查询条件</div>
										<div id="collapsebutton">
											<i class="fas fa-angle-up mr-2" data-toggle="collapse" data-target="#searchindex"></i>
											<!-- <button class="btn btn-secondary btn-sm" data-toggle="collapse" data-target="#searchindex" >收合</button> -->
										</div>
										</div>
										<div id="searchindex" class="collapse show">
											<div class="panel-body">	
											<?php
												// 查询索引条件 content
												echo (isset($tmpl['indexbody_content']))?$tmpl['indexbody_content']:$tmpl['panelbody_filtercategory'];
												?>
												<?php
												// 查询進階索引条件 content
												if(isset($tmpl['indexbody_advanced_content'])&& $tmpl['indexbody_advanced_content']!==''){
													echo '
												<div class="accordion my-2">
														<div class="px-3" data-toggle="collapse" data-target="#collapseadvanced">
															<h5 class="mb-0">
																<i class="fas fa-angle-down mr-2"></i>
																'.$tr['Advanced Search'].'
															</h5>
															<hr/>
														</div>

														<div id="collapseadvanced" class="container collapse">
																'.$tmpl['indexbody_advanced_content'].'
														</div>
												</div>';
												}
												?>
												<?php
												// 查询索引条件submit content
												if(isset($tmpl['indexbody_submit'])&& $tmpl['indexbody_submit']!==''){
													echo $tmpl['indexbody_submit'];
												}
												?>
											</div>
										</div>
									</div>											
								</div>
								<div class="col-12 col-md">
									<ul class="nav nav-tabs">
									<?php
										$nav_item = ['member_betlog'=>'投注紀錄','transaction_query'=>'交易紀錄','member_log'=>'登入紀錄',
										// 'token_auditorial'=>'稽核紀錄'
									];
										foreach ($nav_item as $key => $value) {
											$active = ($key == $page)? ' active':'';
											echo '<li class="nav-item">
											<a class="nav-link'.$active.'" href="./'.$key.'.php?m&a='.$account_query.'">'.$value.'</a>
											</li>';
										}												
									?>
									</ul>
									<div class="tab-content border border-top-0 p-4">
										<?php
											// 工作區內容
											echo $tmpl['panelbody_content'];
										?>	
									</div>					
								</div>
							</div>
							</div>							
						</div>
					</div>
				</div>

				<div class="col-12">
					<div class="panel panel-default">
						<div class="panel-footer">
							<?php
							// 頁腳顯示
							echo page_footer();
							?>
						</div>
				</div>				
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
	</body>
	</html>
