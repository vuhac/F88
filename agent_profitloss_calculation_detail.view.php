<?php use_layout("template/beadmin.tmpl.php"); ?>

<!-- begin of page_title -->
<?php begin_section('page_title'); ?>
<ol class="breadcrumb">
  <li><a href="home.php"><?php echo $tr['Home'] ?></a></li>
  <li><a href="#"><?php echo $tr['profit and promotion'] ?></a></li>
  <li class="active"><?php echo $tr['Agent profit and loss calculation'] ?></li>
</ol>
<?php end_section(); ?>
<!-- end of paneltitle_content -->

<!-- begin of paneltitle_content -->
<?php begin_section('paneltitle_content'); ?>
<?php echo $tr['Agent Income Summary'] ?>
<?php end_section(); ?>
<!-- end of paneltitle_content -->

<!-- begin of panelbody_content -->
<?php begin_section('panelbody_content'); ?>
<br>
<div class="row">
  <div class="col-12">
    <a class="btn btn-primary" onclick="window.history.back();" href="agent_profitloss_calculation.php"><?php echo $tr['return'];?></a>
  </div>
</div>
<br>
<div class="row">
  <div class="col-12 mb-2">
    <ul class="list-group">
      <li class="list-group-item"><?php echo $tr['Current inqury member account'];?> <?php echo $commission_detail->member_account; ?>  </li>
      <li class="list-group-item"> <?php echo $tr['Current query interval'];?> <?php echo $commission_detail->dailydate; ?> ~ <?php echo $commission_detail->end_date; ?> </li>
      <li class="list-group-item list-group-item-success"> <?php echo $tr['today total commission'];?><?php echo $commission_detail->agent_commission; ?>  </li>
    </ul>
  </div>
  <div class="col-12">

    <!-- Nav tabs -->
    <ul class="nav nav-tabs" role="tablist">
      <li role="presentation" class="active"><a href="#preferential_detail" aria-controls="preferential_detail" role="tab" data-toggle="tab"><?php echo $tr['Commission income source'];?></a></li>
      <li role="presentation"><a href="#distribute_detail" aria-controls="distribute_detail" role="tab" data-toggle="tab"><?php echo $tr['Member profit and loss distribution list'];?></a></li>
    </ul>
    <br>

    <!-- Tab panes -->
    <div class="tab-content">
      <!-- 佣金收入來源 -->
      <div role="tabpanel" class="tab-pane active" id="preferential_detail">
        <!-- plateform_cost -->
        <?php if ( count($commission_detail->commission_detail['all_profitloss_amount_detail']['plateform_cost']) > 0 ): ?>
          <p class="alert alert-warning">
            <?php echo $tr['platform cost'];?>
          </p>
          <ul class="list-group">
            <?php foreach ($commission_detail->commission_detail['all_profitloss_amount_detail']['plateform_cost'] as $plateform_cost_row): ?>
              <li class="list-group-item">
                <?php echo (get_plateform_cost_name($plateform_cost_row['type'])); ?>：
                <?php echo $plateform_cost_row['cost_base'] . ' X ' . (100 * $plateform_cost_row['cost_rate']) .  '% = ' . $plateform_cost_row['cost']; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <!-- end of plateform_cost -->

        <?php if ($has_no_commission_from_successor): ?>
          <p class="alert alert-warning">
            <?php echo $tr['No commission from the downline'];?>
          </p>
        <?php else: ?>
          <p class="alert alert-info">
            <?php echo $tr['commission from downline'];?>
          </p>

          <!-- show preferential detail list -->
          <table class="table">
            <tr>
              <th><?php echo $tr['commission account'];?></th>
              <th><?php echo $tr['commission the cardinal number'];?></th>
            </tr>
            <?php foreach ($commission_detail->commission_detail['all_profitloss_amount_detail']['level_distribute'] as $list): ?>
              <tr>
                <td>
                  <a href="<?php
                      echo (
                        'agent_profitloss_calculation_detail.php?member_account=' . $list['from_account']
                        . '&dailydate_start=' . $dailydate_start
                        . '&dailydate_end=' . $dailydate_end
                      );
                    ?>"
                  >
                    <?php echo $list['from_account']; ?>
                  </a>
                  <?php if (isset($list['is_rest']) && $list['is_rest']): ?>
                    <span class='label label-warning pull-right'><?php echo $tr['Undivided profit or loss'];?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php echo $list['base_profitloss'] . ' X ' . (100 * $list['from_profitloss_rate']) .  '% = ' . $list['from_profitloss']; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
          <!-- end of show preferential detail list -->
        <?php endif; ?>
      </div>
      <!-- end of 佣金收入來源 -->
      <!-- 會員損益分配列表 -->
      <div role="tabpanel" class="tab-pane" id="distribute_detail">
        <?php if ($has_no_self_profitloss): ?>
          <p class="alert alert-warning">
           <?php echo $tr['no profit and loss'];?>
          </p>
        <?php else: ?>
          <!-- bet detail table -->
          <div class="row">
            <?php foreach ($commission_detail->commission_detail['profitloss_distribute']['total_bets_detail'] as $casino => $category_bet): ?>
              <div class="col-12 col-sm-6">
                <h4>
                  <?php echo $tr[strtoupper($casino)]; ?>
                </h4>

                <table class="table table-borded">
                  <tr>
                    <th>分类</th>
                    <th class="text-right">损益</th>
                    <th class="text-right">分类损益比</th>
                    <th class="text-right">分类损益</th>
                  </tr>
                  <?php foreach ($category_bet as $category => $bet): ?>
                    <tr>
                      <td><?php echo $tr[$category]; ?></td>
                      <td class="text-right">
                        <?php echo $bet; ?>
                      </td>
                      <td class="text-right">
                        <?php
                          echo ( ($commission_detail->commission_detail['profitloss_distribute']['casino_profitlossrates'][$casino][$category]) * 100 ) . ' %';
                        ?>
                      </td>
                      <td class="text-right">
                        <?php
                          echo ($bet * $commission_detail->commission_detail['profitloss_distribute']['casino_profitlossrates'][$casino][$category]);
                        ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </table>

              </div>
            <?php endforeach; ?>
          </div>
          <!-- end of bet detail table -->

          <p class="alert alert-info">
            <?php echo $tr['the cardinal number of the member profit and loss'];?> <?php echo $commission_detail->commission_detail['profitloss_distribute']['total_profitloss']; ?> (<?php echo $tr['sum of profit and loss'];?>)<br>
          </p>

          <!-- show preferential distribute list -->
          <ul class="list-group">
            <?php foreach ($commission_detail->commission_detail['profitloss_distribute']['level_distribute'] as $list): ?>
              <li class="list-group-item">
                <button class="btn btn-default">
                  <span class="glyphicon glyphicon-arrow-down" aria-hidden="true"></span>
                  <br>
                  <?php echo $list['to_account']; ?> ( <?php echo (100 * $list['to_profitloss_rate']); ?> %)
                </button>
                <?php echo $list['base_profitloss'] . ' X ' . (100 * $list['to_profitloss_rate']) .  '% = ' . $list['to_profitloss']; ?>
              </li>
            <?php endforeach; ?>
            <?php
              if ( isset( $commission_detail->commission_detail['profitloss_distribute']['rest_distribute'] )
                && !empty( $commission_detail->commission_detail['profitloss_distribute']['rest_distribute'] )
              ):
                $rest_distribute = $commission_detail->commission_detail['profitloss_distribute']['rest_distribute'];
            ?>
              <li class="list-group-item list-group-item-warning">
                <?php echo $tr['Undivided profit or loss'];?>
                <?php echo $list['base_profitloss'] . ' X ' . (100 * $rest_distribute['to_profitloss_rate']) .  '% = ' . $rest_distribute['to_profitloss']; ?>
              </li>
            <?php endif; ?>
          </ul>
          <!-- end of show preferential distribute list -->
        <?php endif; ?>
      </div>
      <!-- end of 會員損益分配列表 -->
    </div>
    <!-- end of Tab panes -->
  </div>

</div>

<?php end_section(); ?>
<!-- end of panelbody_content -->
