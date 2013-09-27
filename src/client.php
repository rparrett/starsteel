<?php

// Save existing tty configuration
$term = trim(`stty -g`);

register_shutdown_function(function() use ($term) {
    echo "Shutting down\n";

    // Fix ansi color spillage
    echo "\x1b[0m";

    // Reset the tty back to the original configuration
    // OR you could use: stty sane
    system("stty '" . $term . "'");
});

// Unbuffered stdin
system("stty -icanon -echo");

// Autoloader
require_once __DIR__.'/../vendor/autoload.php';

use React\SocketClient\Connector;
use React\EventLoop\StreamSelectLoop;
use React\Dns\Resolver\Factory;

use Starsteel\Util;
use Starsteel\Logger;
use Starsteel\Client;

define('LOGGING_ENABLE', true);

if (LOGGING_ENABLE) {
    // epoll does not play nicely with file streams, so use StreamSelect
    // https://github.com/reactphp/react/issues/104
    $loop = new React\EventLoop\StreamSelectLoop();
    $log = new Logger(Util::filestream_factory(__DIR__.'/../logs/client.log', $loop));
} else {
    $loop = React\EventLoop\Factory::create();
    $log = new Logger();
}

$options = array('hexdump' => true, 'line' => false, 'username'=>'', 'password'=>'', 'auto' => true);
$options = array_replace($options, json_decode(file_get_contents('./config-client.json'), true));

$factory = new Factory();
$dns = $factory->create('8.8.8.8:53', $loop);

$connector = new Connector($loop, $dns);

$client = new Client($loop, $connector, $options, $log);
$client->connect();

declare(ticks = 1);

// Keyboard interrupt handler
$int_handler = function($sig) use (&$loop) {
    echo "Signal detected\n";
    $loop->stop();
    echo "Exiting\n";
    exit;
};

pcntl_signal(SIGINT,  $int_handler);
pcntl_signal(SIGTERM, $int_handler);
pcntl_signal(SIGHUP,  $int_handler);

$loop->run();
