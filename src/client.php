<?php

// Autoloader
require_once __DIR__.'/../vendor/autoload.php';

use React\SocketClient\Connector;
use React\EventLoop\StreamSelectLoop;
use React\Dns\Resolver\Factory;

//$loop = React\EventLoop\Factory::create();
$loop = new React\EventLoop\StreamSelectLoop();

$factory = new Factory();
$dns = $factory->create('8.8.8.8:53', $loop);

$connector = new Connector($loop, $dns);

require_once __DIR__.'/funcs.php';

$capturedStream = null;

$options = array('hexdump' => false, 'line' => false, 'username'=>'', 'password'=>'', 'auto' => true);
$options = array_replace($options, json_decode(file_get_contents('./config-client.json'), true));

$connector->create($options['mud_ip'], $options['mud_port'])
    ->then(function ($stream) use (&$capturedStream, &$options, $options) {
        echo "Connected to {$options['mud_ip']}:{$options['mud_port']}\n";

        $capturedStream = $stream;
        $capturedStream->on('data', function($data) use (&$options, &$capturedStream) {

            // Todo: Invalid UTF-8 sequence

            $lines = explode("\r\n", $data);

            foreach($lines as $i => $line) {
                if ($line == '')
                    continue;

                $stripped_line = strip_ansi($line);

                if ($options['hexdump']) {
                    $print_line = hex_dump($line, "\n", strlen($line));
                } else {
                    $print_line = $stripped_line;
                }

                if ($options['line']) {
                    $print_line = "Line: $i".$print_line;
                }

                echo $print_line."\n";

                if ($options['auto']) {
                    if (trim($stripped_line) == 'Otherwise type "new":') {
                        echo "Trying to login\n";

                        $capturedStream->write($options['username']."\r\n");
                    }

                    if (trim($stripped_line) == 'Enter your password:') {
                        echo "Trying pass\n";

                        $capturedStream->write($options['password']."\r\n");
                    }

                    if (preg_match('/Make your selection/', trim($stripped_line))) {
                        $capturedStream->write("/go majormud" . "\r\n");
                    }

                    if (trim($stripped_line) == '[MAJORMUD]:') {
                        $capturedStream->write("e" . "\r\n");
                    }

                    /*
                     * Last time you were on, you disconnected while playing. The gods have punished you appropriately.
                     */
                    if (trim($stripped_line) == '(N)onstop, (Q)uit, or (C)ontinue?') {
                        $capturedStream->write("c" . "\r\n");
                    }
                }
/*
                Name: Matt Matt                        Lives/CP:      9/100
Race: Kang        Exp: 0               Perception:     33
Class: Warrior    Level: 1             Stealth:         0
Hits:    35/35    Armour Class:   5/1  Thievery:        0
                                       Traps:           0
                                       Picklocks:       0
Strength:  55     Agility: 30          Tracking:        0
Intellect: 30     Health:  50          Martial Arts:   10
Willpower: 45     Charm:   30          MagicRes:       41

                */

                /*

                Newhaven, Village Entrance
    Welcome to Newhaven! You are standing at the crude wooden gates of the
village entrance, in the middle of a dusty path. A low wooden palisade
surrounds the village, to protect against creatures of the night and random
raiders. You can see small buildings to the north and south, and a dusty path
that leads to the west and southeast.
                You notice large sign, newbie manual here.
                Obvious exits: north, south, west, southeast
*/
                preg_match_all('/\[HP=(\d+)\]/', $stripped_line, $matches, PREG_SET_ORDER);
                if (count($matches) > 0)
                    $hp = $matches[0][1];

                //Encumbrance: 0/2640 - None [0%]
                //print_r($matches);

            }
        });
    }, function() {
        print "Hi2";
    });

$input = new InputHandler($loop);
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

