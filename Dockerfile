FROM php:8.1-cli

RUN pecl install redis \
    && docker-php-ext-enable redis

RUN apt-get update && apt-get install -y supervisor cron procps nano \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql

COPY . /app

COPY .env /app/.env

COPY cron.conf /etc/cron.d/jobs
RUN chmod 0644 /etc/cron.d/jobs
RUN crontab /etc/cron.d/jobs

RUN chmod +x /app/start.sh

WORKDIR /app

ENTRYPOINT []

CMD ["/app/start.sh"]
