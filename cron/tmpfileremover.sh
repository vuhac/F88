#!/bin/bash
# --------------------------------------------------
# 檔名：realtime_reward_cmd.sh
# 工作：
#
# --------------------------------------------------
source $(dirname "$0")/shellvar

find ${BINPATH}/tmp_dl -mtime +1 -name "*.*" -exec rm -Rf {} \;
find ${BINPATH}/tmp_jsondata -mtime +1 -name "*.*" -exec rm -Rf {} \;
