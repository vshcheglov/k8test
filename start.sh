#!/bin/sh
cron -f &
supervisord -n -c /app/supervisord.conf
