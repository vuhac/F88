#!/bin/bash
#CRONPATH="/home/testgpk2demo/web/begpk2/cron/"
CRONPATH="/usr/share/nginx/html/cron/"
PHP7="/usr/bin/php70"

echo '' > ${CRONPATH}mg_totalegame_getspinbyspindata.log
echo '' > ${CRONPATH}stat_cmd.log
echo '' > ${CRONPATH}test_mg_logger.log
echo '' > ${CRONPATH}test_mg_totalegame_getspinbyspindata.log
ls ${CRONPATH}*.log -la
