ps -ef | grep dq-master| grep -v grep | head -n 1 | awk '{print $2}' | xargs kill -USR2

