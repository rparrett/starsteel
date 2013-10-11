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

$opts = getopt("l::", array('log::'));

$logfile = null;
if (isset($opts['l']))
    $logfile = $opts['l'];
elseif (isset($opts['log']))
    $logfile = $opts['log'];

if (null !== $logfile && !$logfile) {
    $logfile = __DIR__.'/../logs/client.log';
}

define('LOGGING_ENABLE', null !== $logfile);

if (LOGGING_ENABLE) {
    echo "Logging enabled: ".$logfile."\n";

    // epoll does not play nicely with file streams, so use StreamSelect
    // https://github.com/reactphp/react/issues/104
    $loop = new React\EventLoop\StreamSelectLoop();
    $log = new Logger(Util::filestream_factory($logfile, $loop));
} else {
    $loop = React\EventLoop\Factory::create();
    $log = new Logger();
}

$options = array(
	"host" => "mud.mud.com",
	"port" => 23,
	"username" => "user",
	"password" => "pass",
    "reconnectDelay" => 300,

    "lootCopper" => false,
    "lootSilver" => false,
    "lootGold" => true,
    "lootPlatinum" => true,
    "lootRunic" => true,

    "runDistance" => 4,

    "fullHealth" => 0.95,
    "restHealth" => 0.80,
    "runHealth" => 0.50,
    "hangHealth" => 0.20
);

$options = array_replace($options, json_decode(file_get_contents('./config-client.json'), true));

$factory = new Factory();
$dns = $factory->create('8.8.8.8:53', $loop);

$connector = new Connector($loop, $dns);

$client = new Client($loop, $connector, $options, $log);
$client->connect();

////////////////////////////////////////////////////////////////
// HTTP API
////////////////////////////////////////////////////////////////

$api_options = array('api_port' => 1337, 'api_interface' => '0.0.0.0');
$template_config = array('templ.cache_dir'=>'', 'templ.dir'=>__DIR__.'/../templates');

$templ = new \Matt\Templates\Templates($template_config);
$http_api = new Starsteel\API\ClientRequestHandler($client, $templ, __DIR__.'/../web');

$api_socket = new React\Socket\Server($loop);
$http = new React\Http\Server($api_socket, $loop);
$http->on('request', array($http_api, 'handle'));
$api_socket->listen($api_options['api_port'], $api_options['api_interface']);

echo "API listening at http://{$api_options['api_interface']}:{$api_options['api_port']}\n";


////////////////////////////////////////////////////////////////
// INPUT
////////////////////////////////////////////////////////////////


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
