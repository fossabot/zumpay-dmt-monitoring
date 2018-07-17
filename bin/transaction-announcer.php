#!/usr/local/bin/php
<?php
/**
 * Copyright Serhii Borodai (c) 2018.
 */

use Peth\Daemon\TransactionAnnouncer;
use Psr\Container\ContainerInterface;


/**
 * Created by Serhii Borodai <clarifying@gmail.com>
 */

// Setup/verify autoloading
if (file_exists($a = getcwd() . '/vendor/autoload.php')) {
    require $a;
} elseif (file_exists($a = __DIR__ . '/../../../autoload.php')) {
    require $a;
} elseif (file_exists($a = __DIR__ . '/../vendor/autoload.php')) {
    require $a;
} else {
    fwrite(STDERR, 'Cannot locate autoloader; please run "composer install"' . PHP_EOL);
    exit(1);
}

(function () {
    /** @var ContainerInterface $container */
    $container = require __DIR__ . '/../config/container.php';
    $transactionAnnouncer = $container->get(TransactionAnnouncer::class);

    $transactionAnnouncer->process();
})();