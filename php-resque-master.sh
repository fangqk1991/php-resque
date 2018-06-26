#!/bin/bash

__FILE__="$0"
REAL_FILE=`readlink "${__FILE__}"`
if [ ! -z "${REAL_FILE}" ]; then
    __FILE__="${REAL_FILE}"
fi

__DIR__=`cd "$(dirname "${__FILE__}")"; pwd`

runningDir="${__DIR__}/run.local"
pidFile="${runningDir}/php-resque.pid"
logFile="${runningDir}/php-resque.log"

get_pid() {
    if [ -f ${pidFile} ]; then
        cat ${pidFile}
    fi
}

start() {
    local pid=$(get_pid)
    if [ ! -z ${pid} ]; then
        # local num=$(ps -ef | grep php-resque | grep -v grep | awk '{print $2}' | wc -l)
        # echo "$num processes are running."
        echo "php-resque(${pid}) is running."
        echo "You should stop it before you start."
        return
    fi

    touch ${pidFile}

    echo "runningDir: ${runningDir}"
    echo "Starting php-resque..."
    nohup php "${__DIR__}/php-resque.php" >>"${logFile}" 2>&1 &
    echo $! > "${pidFile}"
}

stop() {
    local pid=$(get_pid)
    if [ -z ${pid} ]; then
        echo "php-resque is not running."
        return
    fi

    echo "Stopping php-resque..."
    get_pid | xargs kill -9
    rm -f ${pidFile}
}

status() {
    local pid=$(get_pid)
    if [ ! -z ${pid} ]; then
        echo "php-resque(${pid}) is running."
    else
        echo "php-resque is not running."
    fi
}

case "$1" in
    start)
        start
        ;;

    stop)
        stop
        ;;

    restart)
        stop
        start
        ;;

    status)
        status
        ;;

    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac

exit 0
