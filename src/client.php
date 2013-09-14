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

use Starsteel\Character;
use Starsteel\LineHandler;
use Starsteel\DataHandler;
use Starsteel\Util;
use Starsteel\Logger;

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

$factory = new Factory();
$dns = $factory->create('8.8.8.8:53', $loop);

$connector = new Connector($loop, $dns);

$capturedStream = null;

$options = array('hexdump' => true, 'line' => false, 'username'=>'', 'password'=>'', 'auto' => true);
$options = array_replace($options, json_decode(file_get_contents('./config-client.json'), true));

$timeStart = time();

$dataHandler = null;

$connector->create($options['mud_ip'], $options['mud_port'])
    ->then(function ($stream) use (&$capturedStream, &$options, $options, &$character, &$dataHandler, &$log) {
        echo "Connected to {$options['mud_ip']}:{$options['mud_port']}\n";

        $capturedStream = $stream;

		$character = new Character($capturedStream);
        $character->setStream($capturedStream);

        $character->timeConnect = time();

        $lineHandler = new LineHandler($capturedStream, $character, $options);
        $dataHandler = new DataHandler($capturedStream, $lineHandler, $log);

        $capturedStream->on('data', function($data) use (&$options, &$capturedStream, $dataHandler) {
            $dataHandler->handle($data);
		});
    }, function($e) {
        echo "Could not connect to mud.\nError ".$e->getMessage()."\n";
    });

$input = new Matt\InputHandler($loop);

$input->on('ansi', function($data) use (&$capturedStream, &$options, &$log) {

    $log->log('ANSI Input: '.$data);
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

$input->on('line', function($line) use (&$capturedStream, &$options, &$character, &$loop, &$dataHandler) {
    if ($line == '/auto') {
        $character->auto = !$character->auto;

        if ($character->auto) {
            $character->timeAuto = time();

            echo "\n\n";
            echo "--> Auto on\n";
            echo "\n";

            $capturedStream->write("l\r\n");
        } else {
            echo "\n\n";
            echo "--> Auto off\n";
            echo "\n";
        }

        return;
    }

    if ($line == '/passthru') {

        if ($dataHandler->state == $dataHandler::STATE_PASSTHRU) {
            $dataHandler->state = $dataHandler::STATE_COLLECT_LINE;
            echo "Passthru off";
        } else {
            $dataHandler->state = $dataHandler::STATE_PASSTHRU;
            echo "Passthru on";
        }
        return;
    }


    if ($line == '/hexdump') {
        $options['hexdump'] = !$options['hexdump'];
        if ($options['hexdump']) {
            echo "Hexdump on";
        } else {
            echo "Hexdump off";
        }
        return;
    }

    if ($line == '/line') {
        $options['line'] = !$options['line'];

        return;
    }

    if ($line == '/stats') {
        $auto   = is_null($character->timeAuto) ? 0 : time() - $character->timeAuto;
        $online = time() - $character->timeConnect;

        $xphr = $auto <= 0 ? 0 : $character->expEarned / $auto * 60 * 60;

        echo "\n\n";
        printf("--> Online          %10s\n", Util::formatTimeInterval($online));
        printf("--> Auto            %10s\n", Util::formatTimeInterval($auto));
        printf("--> Monsters Killed %10s\n", number_format($character->monstersKilled));
        printf("--> Exp Earned      %10s\n", Util::formatExp($character->expEarned));
        printf("--> Exp Per Hour    %10s\n", Util::formatExp($xphr));
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
