AMQP Bundle
===========

[![Build Status](https://github.com/FiveLab/AmqpBundle/workflows/Testing/badge.svg?branch=master)](https://github.com/FiveLab/AmqpBundle/actions)

Integrate the [AMQP](https://github.com/FiveLab/Amqp) library with you Symfony application.

Simple configuration
--------------------

```yaml
fivelab_amqp:
    driver: php_extension

    connections:
        default: 
            host: 127.0.0.1
            port: 5672
            vhost: /
            login: guest
            password: guest

    exchanges:
        primary:
            name: direct
            type: direct
            durable: true

    publishers:
        primary:
            exchange: primary

    queues:
        collect_reports:
            name: reports.collect
            connection: default
            bindings:
                - { exchange: primary, routing: customer.register }
                - { exchange: primary, routing: customer.activate }

    consumers:
        collect_reports:
            queue: collect_repots
            message_handlers: 'acme.service.collect_reports_message_handler'
```

Initialize
----------

After install and configure bundle, you can initialize all exchanges and queues:

```shell script
./bin/console event-broker:initialize:exchanges
./bin/console event-broker:initialize:queues
```

After initialize exchanges and queues you can run consumer:

```shell script
./bin/console event-broker:consumer:run collect_reports
```

For debug, you can use verbosity levels.

Development
-----------

For easy development you can use the `Docker`.

```bash
$ docker build -t amqp-bundle .
$ docker run -it \
    --name amqp-bundle \
    -v $(pwd):/code \
    amqp-bundle bash

``` 

After success run and attach to container you must install vendors:

```bash
$ composer install
```

Before create the PR or merge into develop, please run next commands for validate code:

```bash
$ ./bin/phpunit

$ ./bin/phpcs --config-set show_warnings 0
$ ./bin/phpcs --standard=vendor/escapestudios/symfony2-coding-standard/Symfony/ src/
$ ./bin/phpcs --standard=tests/phpcs-ruleset.xml tests/

```
