#!/bin/bash
# --------------------------------------------------
# 檔名：realtime_reward_cmd.sh
# 工作：
#
# --------------------------------------------------
source $(dirname "$0")/shellvar

echo "${PHP7} ${BINPATH}realtime_reward_cmd.php run sql 0 >> ${LOGPATH}realtime_reward.log "
echo "${PHP7} ${BINPATH}realtime_reward_cmd.php run sql 0 >> ${LOGPATH}realtime_reward.log " | sh
