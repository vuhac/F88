#!/bin/bash
# --------------------------------------------------
# 檔名：test_0_preferential_cmd.sh
# 工作：
#
# --------------------------------------------------
source $(dirname "$0")/shellvar
DATENOW=`date --date='1 days ago' "+%Y-%m-%d"`

echo "${PHP7} ${BINPATH}preferential_calculation_cmd.php run ${DATENOW} >> ${LOGPATH}0_pref_cmd.log "
echo "${PHP7} ${BINPATH}preferential_calculation_cmd.php run ${DATENOW} >> ${LOGPATH}0_pref_cmd.log " | sh
