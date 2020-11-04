#!/bin/bash
# --------------------------------------------------
# 檔名：get_cacert.sh
# 工作：用來更新SSL的CA憑證用
# 排程寫法：
#  for www.gpk17.com and be.gpk17.com betting log
# */5 * * * * /home/deployer/cron/get_cacert.sh
# --------------------------------------------------

source $(dirname "$0")/shellvar

cd /tmp
wget https://curl.haxx.se/ca/cacert.pem
cp cacert.pem  ${BINPATH}
rm -Rf /tmp/cacert.pem
