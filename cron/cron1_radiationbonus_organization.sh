#!/bin/bash
# --------------------------------------------------
# 檔名：test_stat_cmd.sh
# 工作：
#
# --------------------------------------------------
source $(dirname "$0")/shellvar

echo "${PHP7} ${BINPATH}radiationbonus_organization_cmd.php run >> ${LOGPATH}1_radiationbonus_organization_cmd.log "
echo "${PHP7} ${BINPATH}radiationbonus_organization_cmd.php run >> ${LOGPATH}1_radiationbonus_organization_cmd.log " | sh
