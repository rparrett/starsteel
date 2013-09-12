<?php

// Save existing tty configuration
$term = trim(`stty -g`);

register_shutdown_function(function() use ($term) {
    echo "Shutting down\n";

    // Reset the tty back to the original configuration
    // OR you could use: stty sane

    system("stty '" . $term . "'");

    // Fix ansi color spillage
    echo "\x1b[0m";
});

// Unbuffered stdin
system("stty -icanon -echo");

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

		$character   = new Character($capturedStream);
        $lineHandler = new LineHandler($capturedStream, $character, $options);
        $dataHandler = new DataHandler($capturedStream, $lineHandler);

        $capturedStream->on('data', function($data) use (&$options, &$capturedStream, $dataHandler) {
            $dataHandler->handle($data);
		});
    }, function($e) {
        echo "Could not connect to mud.\nError ".$e->getMessage()."\n";
    });

$input = new Matt\InputHandler($loop);

$input->on('ansi', function($data) use (&$capturedStream, &$options) {

    // Pass-through ansi sequences from terminal
    $capturedStream->write($data);
});

$input->on('char', function($char) use (&$capturedStream, &$options, &$loop) {
    $ord = ord($char);

    if ($ord >= 0x20 && $ord <= 0x7E) {
        echo $char;
    }
});

$input->on('line', function($line) use (&$capturedStream, &$options, &$loop) {

    if ($line == 'auto') {
        $options['auto'] = !$options['auto'];
    }

    if ($line == 'hexdump') {
        $options['hexdump'] = !$options['hexdump'];
    }

    if ($line == 'line') {
        $options['line'] = !$options['line'];
    }

    if ($line == 'exit') {
        $loop->stop();
    }

    if (null !== $capturedStream) {

        for($i=0;$i<strlen($line)-1;$i++)
            echo "\x08";

        if ($line == "\n" || $line == "\r")
            $capturedStream->write("\r\n");
        else
            $capturedStream->write($line."\r\n");
    }
});

$loop->run();

