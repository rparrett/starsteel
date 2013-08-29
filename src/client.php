<?php

register_shutdown_function(function() {
    echo "\x1b[0m";
});


// Autoloader
require_once __DIR__.'/../vendor/autoload.php';

use React\SocketClient\Connector;
use React\EventLoop\StreamSelectLoop;
use React\Dns\Resolver\Factory;

use Starsteel\Character;
use Starsteel\LineHandler;
use Starsteel\DataHandler;

//$loop = React\EventLoop\Factory::create();
$loop = new React\EventLoop\StreamSelectLoop();

$factory = new Factory();
$dns = $factory->create('8.8.8.8:53', $loop);

$connector = new Connector($loop, $dns);

require_once __DIR__.'/funcs.php';

$capturedStream = null;

$options = array('hexdump' => true, 'line' => false, 'username'=>'', 'password'=>'', 'auto' => true);
$options = array_replace($options, json_decode(file_get_contents('./config-client.json'), true));


$connector->create($options['mud_ip'], $options['mud_port'])
    ->then(function ($stream) use (&$capturedStream, &$options, $options) {
        echo "Connected to {$options['mud_ip']}:{$options['mud_port']}\n";

        $capturedStream = $stream;
        
        $character   = new Character();
        $lineHandler = new LineHandler($capturedStream, $character, $options);
        $dataHandler = new DataHandler($capturedStream, $lineHandler);

        $capturedStream->on('data', function($data) use (&$options, &$capturedStream, $dataHandler) {
            $dataHandler->handle($data);
        });
    }, function() {
        print "Hi2";
    });

$input = new Matt\InputHandler($loop);
$input->on('input', function($line) use (&$capturedStream, &$options) {
    if ($line == 'auto') {
        $options['auto'] = !$options['auto'];
    }

    if ($line == 'hexdump') {
        $options['hexdump'] = !$options['hexdump'];
    }

    if ($line == 'line') {
        $options['line'] = !$options['line'];
    }

    if (null !== $capturedStream) {
        echo "Writing $line\n";
        $capturedStream->write($line."\r\n");
    }
});

$loop->run();

