## Russia has become a terrorist state.

<div style="font-size: 2em; color: #d0d7de;">
    <span style="background-color: #54aeff">&nbsp;#StandWith</span><span style="background-color: #d4a72c">Ukraine&nbsp;</span>
</div>


AMQP Bundle
===========

[![Build Status](https://github.com/FiveLab/AmqpBundle/workflows/Testing/badge.svg?branch=master)](https://github.com/FiveLab/AmqpBundle/actions)

Integrate the [AMQP](https://github.com/FiveLab/Amqp) library with you Symfony application.

> Note: bundle now support only php_extension driver (amqp extension).

Sample configuration
--------------------

```yaml
fivelab_amqp:
    connections:
        default:
            dsn: 'amqp://guest:guest@127.0.0.1:15672/%2f?read_timeout=30&other_parameter=some'

    exchanges:
        primary:
            connection: default
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

Publish messages
----------------

For publish messages to broker, we use `Publisher` system. You can configure more publishers.

```php
<?php

namespace Acme\Controller;

use FiveLab\Component\Amqp\Publisher\PublisherInterface;
use FiveLab\Component\Amqp\Message\Message;
use FiveLab\Component\Amqp\Message\Payload;

class MyController 
{
    public function __construct(private PublisherInterface $publisher)
    {
        $this->publisher = $publisher;
    }

    public function handleAction(): void
    {
        $payload = new Payload('hello world');
        $message = new Message($payload);
        
        $this->publisher->publish($message, 'customer.register');
    }   
}
```


### Transactional

RabbitMQ supports transactional layer, and we implement it. For use transactional layer you must create new channel for
transactional layer and use this channel in you publisher:

```yaml
fivelab_amqp:
    # Configure connections, etc...
    channels:
        transactional:
            connection: default 

    publishers:
        # Publishers
        primary_transactional:
            exchange: primary
            channel: transactional
            savepoint: false
```

> Note: you should start/commit/rollback transaction directly in you application (on middleware layers on command bus as an example).
> See `FiveLab\Component\Amqp\Channel\ChannelInterface::(start|commit|rollback)Transaction` methods.

If you want to use savepoint, you can set `true` for `savepoint`. We implement this functionality 
(`\FiveLab\Component\Amqp\Publisher\SavepointPublisherDecorator`). 

Development
-----------

For easy development you can use the `Docker`.

```bash
docker build -t amqp-bundle .
docker run -it \
    --name amqp-bundle \
    -v $(pwd):/code \
    amqp-bundle bash

``` 

After success run and attach to container you must install vendors:

```bash
composer install
```

Before create the PR or merge into develop, please run next commands for validate code:

```bash
./bin/phpunit

./bin/phpcs --config-set show_warnings 0
./bin/phpcs --standard=vendor/escapestudios/symfony2-coding-standard/Symfony/ src/
./bin/phpcs --standard=tests/phpcs-ruleset.xml tests/

```
