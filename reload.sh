#!/usr/bin/env bash

pids=`ps -eaf | grep "task_queue Main" | grep -v "grep"| awk '{print $2}'`

for pid in $pids
do
    echo $pid
	kill -USR1 $pid
done
