<?php

// Autoloader
require_once __DIR__.'/../vendor/autoload.php';

use React\SocketClient\Connector;
use React\EventLoop\StreamSelectLoop;

use Evenement\EventEmitter;
use React\Http\Request;
use React\Http\Response;
use Matt\ConsoleColor2;
use Matt\MajorMUDProxy;

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__.'/../error_log');

////////////////////////////////////////////////////////////////
// Init
////////////////////////////////////////////////////////////////

// Load server config
$config = json_decode(file_get_contents('./config.json'), true);

//$loop = React\EventLoop\Factory::create();
// Libevent/epoll doesn't work well with regular file descriptors
// https://github.com/reactphp/react/issues/104

$loop = new React\EventLoop\StreamSelectLoop();

////////////////////////////////////////////////////////////////
// HTTP API
////////////////////////////////////////////////////////////////

$data = array('started' => time());
$fake_app = array('templ.cache_dir'=>'', 'templ.dir'=>__DIR__.'/../templates');

$templ = new Matt\Templates\Templates($fake_app);

$app = function (Request $request, $response) use (&$conns, &$data, &$templ) {
    $conns_data = array();

    // Get each proxy's extracted data
    foreach($conns as $conn) {
        if (!$conn->getRemoteAddress()) {
            // TODO: Fix
            continue;
        }

        $proxy = $conns[$conn];
        $conn_data = $proxy->getData();
        $conn_data['ip'] = $conn->getRemoteAddress();
        $conns_data[$proxy->getId()] = $conn_data;
    }

    echo "API Request: ".$request->getPath()." ".$request->getQuery()."\n";

    // Routes

    if ($request->getPath() == '/json') {
        $response->writeHead(200, array('Content-Type' => 'application/json'));


        $data['uptime'] = time() - $data['started'];

        $response->end(json_encode(array('result'=>true, 'data'=>$data, 'conns'=>$conns_data)));
    } elseif ($request->getPath() == '/') {
        $response->writeHead(200, array('Content-Type' => 'text/html'));
        $stats = array(
            'strength', 'health', 'agility', 'intellect', 'willpower', 'charm'
        );
        $secondary_stats = array(
            'magicres', 'perception', 'stealth', 'spellcasting', 'tracking', 'thievery', 'traps', 'picklocks', 'martial_arts'
        );
        $templ_data = array('data'=>$conns_data, 'stats'=>$stats, 'secondary_stats'=>$secondary_stats);
        var_dump($templ_data);
        $response->end($templ->render('characters.thtml', $templ_data));
    } else {
        $response->writeHead(404, array('Content-Type' => 'text/html'));
        $response->end('<html><body>Sorry, not found!</body></html>');
    }
};

$api_socket = new React\Socket\Server($loop);
$http = new React\Http\Server($api_socket, $loop);
$http->on('request', $app);

$api_socket->listen($config['server']['api_port'], $config['server']['api_interface']);
echo "API listening at http://{$config['server']['api_interface']}:{$config['server']['api_port']}\n";


////////////////////////////////////////////////////////////////
// Handle Incoming Connections
////////////////////////////////////////////////////////////////

$conns = new \SplObjectStorage(); // Map conn to proxy handler

$factory = new React\Dns\Resolver\Factory();
$dns = $factory->create('8.8.8.8:53', $loop);
$connector = new Connector($loop, $dns);
$socket = new React\Socket\Server($loop);

$next_client_id = 0;
$socket->on('connection', function ($client_conn) use (&$config, &$connector, &$log_factory, &$conns, &$next_client_id, &$loop ) {

    echo "Client ".$client_conn->getRemoteAddress()." connected ($next_client_id)\n";

    $log = new Starsteel\Logger(Starsteel\Util::filestream_factory('/tmp/majormud.'.$next_client_id.'.log', $loop));

    $proxy = new Starsteel\Proxy($next_client_id, $client_conn, $config['server']['mud_ip'], $config['server']['mud_port'], $connector, $log);
    $conns->attach($client_conn, $proxy);
    $proxy->on('end', function($conn) use (&$conns) {
        $conns->detach($conn);
    });

    $next_client_id++;
});

$socket->listen($config['server']['client_port'], $config['server']['client_interface']);
echo "Listening on {$config['server']['client_interface']}:{$config['server']['client_port']}\n";

////////////////////////////////////////////////////////////////
// Handle local input
////////////////////////////////////////////////////////////////

$input_log = Starsteel\Util::filestream_factory('/tmp/input.log', $loop);

$input = new Matt\InputHandler($loop);
$input->on('line', function($line) use (&$input_log) {

});

declare(ticks = 1);

// Keyboard interrupt handler
$int_handler = function($sig) use (&$conns, &$loop) {

    foreach($conns as $conn) {
        echo "Closing ".$conn->getRemoteAddress()."\n";
        $conn->close();
    }
    $loop->stop();

};

pcntl_signal(SIGINT,  $int_handler);
pcntl_signal(SIGTERM, $int_handler);
pcntl_signal(SIGHUP,  $int_handler);

////////////////////////////////////////////////////////////////
// Run the loop!
////////////////////////////////////////////////////////////////


$loop->run();


// For future things
//$t = 0;
//$loop->addPeriodicTimer(5, function ($timer) use (&$t) {
//echo "T>$t\n";
//if ($t++ == 5) $timer->cancel();
//});

