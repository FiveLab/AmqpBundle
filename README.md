AMQP Bundle
===========

Integrate the [AMQP](https://github.com/FiveLab/Amqp) library with you Symfony application.

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
