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

$loop = React\EventLoop\Factory::create();
//$loop = new React\EventLoop\StreamSelectLoop(); // We may need this if we implement logging (see proxy)

$factory = new Factory();
$dns = $factory->create('8.8.8.8:53', $loop);

$connector = new Connector($loop, $dns);

require_once __DIR__.'/funcs.php';

$capturedStream = null;

$options = array('hexdump' => true, 'line' => false, 'username'=>'', 'password'=>'', 'auto' => true);
$options = array_replace($options, json_decode(file_get_contents('./config-client.json'), true));

$character = new Character();

$connector->create($options['mud_ip'], $options['mud_port'])
    ->then(function ($stream) use (&$capturedStream, &$options, $options, &$character) {
        echo "Connected to {$options['mud_ip']}:{$options['mud_port']}\n";

        $capturedStream = $stream;

		$character   = new Character($capturedStream);
        $character->setStream($capturedStream);

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
    // Not working 100%. For now, just ignore

    //$capturedStream->write($data);
});

$input->on('char', function($char) {
    $ord = ord($char);

    if ($ord >= 0x20 && $ord <= 0x7E) {
        echo $char;
    }
});

$input->on('line', function($line) use (&$capturedStream, &$options, &$character, &$loop) {
    if ($line == '/auto') {
        $options['auto'] = !$options['auto'];

        return;
    }

    if ($line == '/hexdump') {
        $options['hexdump'] = !$options['hexdump'];

        return;
    }

    if ($line == '/line') {
        $options['line'] = !$options['line'];

        return;
    }

    if ($line == '/stats') {
        echo "\n";
        echo "Stats\n";
        echo "Earned exp: " . $character->earnedExp . "\n";
        echo "\n";

        return;
    }

    if ($line == '/forcequit') {
        echo "Quitting\n";
        $loop->stop();

        return;
    }

    if (null !== $capturedStream) {

        if ($line == "\n" || $line == "\r") {
            $capturedStream->write("\r\n");
        } else {
            for($i=0;$i<strlen($line);$i++)
                echo "\x08";
            $capturedStream->write($line."\r\n");
        }
    }
});

$loop->run();

