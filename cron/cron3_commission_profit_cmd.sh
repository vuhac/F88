#!/bin/bash
# --------------------------------------------------
# 檔名：test_stat_cmd.sh
# 工作：
#
# --------------------------------------------------
source $(dirname "$0")/shellvar

echo "${PHP7} ${BINPATH}bonus_commission_profit_cmd.php run >> ${LOGPATH}3_profit_cmd.log "
echo "${PHP7} ${BINPATH}bonus_commission_profit_cmd.php run >> ${LOGPATH}3_profit_cmd.log " | sh
