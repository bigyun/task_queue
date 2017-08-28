#!/usr/bin/env bash
nohup php /www/wwwroot/task_queue/server.php &
# bash /home/wwwroot/default/fruit/check-remind.sh
# 等待1秒钟查看进程是否启动成功
sleep 1
count=`ps -fe |grep "task_queue" | grep -v "grep" | grep "Main" | wc -l`
 
if [ $count -eq 1 ]; 
  then
	echo " start ok!"
  else 
	echo " failed to start!"	 
fi

