#!/bin/bash

__FILE__="$0"
REAL_FILE=`readlink "${__FILE__}"`
if [ ! -z "${REAL_FILE}" ]; then
    __FILE__="${REAL_FILE}"
fi

__DIR__=`cd "$(dirname "${__FILE__}")"; pwd`

RESQUE_LIB_DIR="${__DIR__}/vendor/chrisboulton/php-resque"
CONFIG_FILE="${__DIR__}/config.local/resque.main.config"
JOBS_FILE="${__DIR__}/config.local/ResqueMainJobs.php"
RUN_DIR="${__DIR__}/run.local"

if [ ! -d "${RESQUE_LIB_DIR}" ]; then
    echo 'missing php-resque library!'
    exit
fi

if [ ! -f "${CONFIG_FILE}" ]; then
    echo 'missing configuration file!'
    exit
fi

if [ ! -f "${JOBS_FILE}" ]; then
    echo 'missing jobs file'
    exit
fi

. "${CONFIG_FILE}"

if [ -z "${RESQUE_HOST}" ] || [ -z "${RESQUE_PORT}" ]; then
    echo 'configuration file error'
    exit
fi

# export VVERBOSE=1 # for debugging
export APP_INCLUDE="${JOBS_FILE}"
export QUEUE="${RESQUE_QUEUE}"
export COUNT=1
export REDIS_BACKEND="${RESQUE_HOST}:${RESQUE_PORT}"
export BLOCKING=TRUE
export INTERVAL=0
export PIDFILE="${RUN_DIR}/php-resque.pid"

LOGFILE="${RUN_DIR}/php-resque.log"

get_pid() {
    if [ -f ${PIDFILE} ]; then
        cat ${PIDFILE}
    fi
}

start() {
    local PID=$(get_pid)
    if [ ! -z ${PID} ]; then
        # local num=$(ps -ef | grep php-resque | grep -v grep | awk '{print $2}' | wc -l)
        # echo "$num processes are running."
        echo "php-resque(${PID}) is running."
        echo "You should stop it before you start."
        return
    fi

    touch ${PIDFILE}

    echo "RUN_DIR: ${RUN_DIR}"
    echo "REDIS_BACKEND: ${REDIS_BACKEND}"
    echo "Starting php-resque..."
    nohup php "${RESQUE_LIB_DIR}/resque.php" >>"${LOGFILE}" 2>&1 &
    #php ${RESQUE_LIB_DIR}/bin/resque
}

stop() {
    local PID=$(get_pid)
    if [ -z ${PID} ]; then
        echo "php-resque is not running."
        return
    fi

    echo "Stopping php-resque..."
    get_pid | xargs kill -9
    rm -f ${PIDFILE}
}

status() {
    local PID=$(get_pid)
    if [ ! -z ${PID} ]; then
        echo "php-resque(${PID}) is running."
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
