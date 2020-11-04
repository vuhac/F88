#!/bin/bash
# --------------------------------------------------
# 檔名：test_stat_cmd.sh
# 工作：
#
# --------------------------------------------------
source $(dirname "$0")/shellvar

echo "${PHP7} ${BINPATH}bonus_commission_sale_cmd.php run >> ${LOGPATH}2_sale_cmd.log "
echo "${PHP7} ${BINPATH}bonus_commission_sale_cmd.php run >> ${LOGPATH}2_sale_cmd.log " | sh
