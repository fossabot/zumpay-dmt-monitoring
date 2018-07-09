<?php
/**
 * Copyright Serhii Borodai (c) 2018.
 */

use Daemon\TransactionAnnouncer;
use Psr\Container\ContainerInterface;


/**
 * Created by Serhii Borodai <clarifying@gmail.com>
 */

require __DIR__ . '/../vendor/autoload.php';

(function () {
    /** @var ContainerInterface $container */
    $container = require __DIR__ . '/../config/container.php';
    $transactionReader = $container->get(TransactionAnnouncer::class);

    $transactionReader->process();
})();