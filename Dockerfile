FROM php:8.1-cli

RUN pecl install redis \
    && docker-php-ext-enable redis

RUN apt-get update && apt-get install -y supervisor cron \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql

COPY . /app

COPY .env /app/.env

COPY cron.conf /etc/cron.d/jobs
RUN chmod 0644 /etc/cron.d/jobs
RUN crontab /etc/cron.d/jobs

WORKDIR /app

ENTRYPOINT []

CMD ["cron", "-f"]
CMD ["supervisord", "-n", "-c", "/app/supervisord.conf"]
