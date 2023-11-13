# k8 test task

## Prerequisites
- MySQL Server (tested on version 8.0.31)
- Redis Server (tested on version 7.2.3)
- Supervisord (tested on version 4.2.5) - optional, only required if you're running without Docker

## Configure

### Environment
Create `.env` file in app directory and fill with your settings

```
DB_HOST=dbhost
DB_NAME=dbname
DB_USER=dbuser
DB_PASS=dbpass
REDIS_HOST=redishost
REDIS_PORT=redisport
BATCH_OFFSET_SECONDS=3600
EMAIL_NOTIFICATION_FROM=service@example.net
```

### MySQL

Depending on the number of consumers, you should adjust the `max_connections` setting in your MySQL configuration.

In the current `supervisord.conf`, there are 180 consumer processes.
Therefore, you need to increase your `max_connections` by 180.

## Cron

If you are testing and prefer not to wait for cron execution, you can manually run cron jobs to populate the queues by entering the Docker container after it run and executing the cron jobs.
Alternatively, you can adjust the `cron.conf` to run cron jobs every minute.

## Importing test data
If you want to test the application, you can create a test MySQL database and then import its schema:

`mysql -u dbuser -p dbname < schema.sql`

After that, populate it with test data:

`php dumbdata.php`

## Running the Application

### Using docker

#### Building the Docker Container

`docker build -t k8app .`

#### Running the Docker Container

If your MySQL and Redis are installed locally:
 - Linux: `docker run --network="host" -d k8app` (not tested on linux but should work)
 - MacOS: Use `host.docker.internal` instead of `localhost` in `.env`, then run `docker run -d k8app`

In other cases:
 - Simply run `docker run -d k8app`

### Manually

- Install cron jobs from `cron.conf` into your crontab.
- Install Supervisor if it's not already installed.
- Configure Supervisor as specified in `supervisord.conf`.
- Start Supervisor tasks to run consumers.
