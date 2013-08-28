<?php

// Autoloader
require_once __DIR__.'/../vendor/autoload.php';

//$loop = React\EventLoop\Factory::create();
$loop = new React\EventLoop\StreamSelectLoop();
print_r($loop);

$log_factory = function($filename, $mode = 'a+') use (&$loop) {
    echo "Made a log\n";
    $fd = fopen($filename, $mode);
    if (false === $fd) {
        throw new Exception("Couldn't open log!");
    }
    stream_set_blocking($fd, 0);
    $stream = new React\Stream\Stream($fd, $loop);
    $stream->on('error', function($err, $s) {
        throw new Exception("Log died?");
    });
    return $stream;
};

$input = new InputHandler($loop);
$i = 0;
$input->on('input', function($line) use (&$loop, &$log_factory, &$i) {

    $log = $log_factory('/tmp/test'.($i++).'.log', 'a+');

    echo "Yay $line\n";

    $log->write('Test '.$line."\n");
});

echo "Loopin!\n";

//$log->end('Test');
$loop->run();

return;

$str = <<<EOT


Name: Matt Matt                        Lives/CP:      9/100
Race: Kang        Exp: 0               Perception:     33
Class: Warrior    Level: 1             Stealth:         0
Hits:    35/35    Armour Class:   5/1  Thievery:        0
Kai:  *    4/7                         Traps:           0
                                       Picklocks:       0
Strength:  55     Agility: 30          Tracking:        0
Intellect: 30     Health:  50          Martial Arts:   10
Willpower: 45     Charm:   30          MagicRes:       41

[HP=-50/MA=30]:
[HP=-50/KAI=30]:

EOT;

echo $str;

/*
preg_match_all('/Name: (\w+ \w+)/', $str, $matches, PREG_SET_ORDER);
preg_match_all('/Race: (\w+)/', $str, $matches, PREG_SET_ORDER);
preg_match_all('/Class: (\w+)/', $str, $matches, PREG_SET_ORDER);
preg_match_all('/Level: (\d+)/', $str, $matches, PREG_SET_ORDER);
preg_match_all('/Exp: (\d+)/', $str, $matches, PREG_SET_ORDER);
preg_match_all('/Strength: (\d+)/', $str, $matches, PREG_SET_ORDER);
preg_match_all('/Hits: (\d+)/(\d+)/', $str, $matches, PREG_SET_ORDER);
preg_match_all('/Armour Class:\s+(\d+)/(\d+)/', $str, $matches, PREG_SET_ORDER);
 */

/*
preg_match_all('/(\w+ ?\w+):\s+(\d+\/?\d*)/', $str, $matches, PREG_SET_ORDER);
foreach($matches as $match) {
	$stats[$match[1]] = $match[2];
}
preg_match_all('/(Name|Race|Class): (\w+)/', $str, $matches, PREG_SET_ORDER);
foreach($matches as $match) {
	$stats[$match[1]] = $match[2];
}
*/


$globals = array();
$stats = array();

function extract_stats($str, &$stats) {

    preg_match_all('/(\w+[ \/]?\w+):[\s*]+(\d+\/?\d*)/', $str, $matches, PREG_SET_ORDER);
    foreach($matches as $match) {
        if ($match[1] == 'Lives/CP') {
            $parts = explode('/', $match[2]);
            $stats['lives'] = $parts[0];
            $stats['cp'] = $parts[1];
        } else {
            $key = strtolower(str_replace(' ', '_', $match[1]));

            // current/min types
            if ($key == 'hits' || $key == 'encumberance' || $key == 'mana' || $key == 'kai') {
                $parts = explode('/', $match[2]);
                $stats[$key] = array('current'=>$parts[0], 'max'=>$parts[1]);
            } else {
                $stats[$key] = $match[2];
            }
        }
    }
    preg_match_all('/(Name|Race|Class): (\w+)/', $str, $matches, PREG_SET_ORDER);
    foreach($matches as $match) {
        $key = strtolower(str_replace(' ', '_', $match[1]));
        $stats[$key] = $match[2];
    }

    // Check out the statline
    preg_match_all('/\[HP=([\d-]+)\/(MA|KAI)=(\d+)\]/', $str, $matches, PREG_SET_ORDER);
    print_r($matches);

    foreach($matches as $match) {

        $current_hp = $match[1];
        $mana_type = $match[2];
        $current_mana = $match[3];

        $stats['hits']['current'] = $current_hp;
        if ($mana_type == 'MA') {
            $stats['mana']['current'] = $current_mana;
        } else if ($mana_type == 'KAI') {
            $stats['kai']['current'] = $current_mana;
        }

    }
}

var_dump($stats);
