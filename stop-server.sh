#!/bin/sh
pids=`ps -eaf | grep "php task_queue" | grep -v "grep"| awk '{print $2}'`

for pid in $pids
do
	echo $pid
	kill -9 $pid
done

# 等待1秒钟查看进程是否关闭成功
sleep 1
count=`ps -fe |grep "task_queue" | grep -v "grep" | grep "Main" | wc -l`

if [ $count -eq 1 ];
  then
        echo " close failed!"
  else
        echo " close ok!"         
fi
