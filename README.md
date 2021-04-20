# SwooleNeo

```
Async task base on Swoole, build in route and SQL builder
```

## Feature

```
Swoole Async task
Neo with Explain
Docker deploy
```

## Table

```
Create Table: CREATE TABLE `products` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
```

## Run

```
composer install && composer dumpauto
change database confg in /app/Config/database.php
touch log files in /runtime/log/info & error
docker-compose build
docker-compose start
```

## Demo

```
curl http://127.0.0.1:9501/api/demo/api
curl http://127.0.0.1:9501/api/demo/api?explain=1
curl http://127.0.0.1:9501/api/demo/exception
curl http://127.0.0.1:9501/task/demo/task
```
