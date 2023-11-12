# k8 test task

## Prerequisites
- mysql server v8.0.31
- redis server v7.2.3

## Configure
Create `.env` file in app directory and fill with your settings

```
DB_HOST=dbhost
DB_NAME=dbname
DB_USER=dbuser
DB_PASS=dbpass
REDIS_HOST=redishost
REDIS_PORT=redisport
SEND_FROM=service@example.net
BATCH_OFFSET_TIME=3600
```

## Import test data
If you want to test the app, you can create test mysql database and then import its schema

`mysql -u dbuser -p dbname < schema.sql`

Then fill it with test data

`php dumbdata.php`

## Run the app

### Using docker

#### Build docker container

`docker build -t k8app .`

#### Run docker container

If your db and redis installed locally
 - linux: `docker run --network="host" -d k8app` (not tested on linux but should work)
 - osx: use `host.docker.internal` instead of `localhost` in `.env`, then `docker run -d k8app`

In other cases:
 - just `docker run -d k8app`

### Manually

- Install cron jobs from cron.conf to your crontab
- Install supervisor if not installed and add configuration like in supervisord.conf
- Start supervisor tasks to run consumers

## After run

If you don't want to wait for cron execution, you can exec into docker container and execute cron jobs manually to populate queues
