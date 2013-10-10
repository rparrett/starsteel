<?php

namespace Starsteel;

use Starsteel\Character;
use Starsteel\LineHandler;
use Starsteel\DataHandler;
use Starsteel\Path;
use Matt\InputHandler;

class Client {
    public $character;
    public $stream;
    public $paths;

    public $input;

    function __construct($loop, $connector, $options, $log) {
        $this->loop = $loop;
        $this->connector = $connector;
        $this->options = $options;
        $this->log = $log;

        $this->input = new InputHandler($loop);
        $this->input->on('ansi', array($this, 'onAnsiInput'));
        $this->input->on('char', array($this, 'onCharInput'));
        $this->input->on('line', array($this, 'onLineInput'));

        $this->character = new Character($log, $options);
        
        $this->paths = new Paths();

        $this->pathsMenu = array();
    }

    function connect() {
        return $this->connector->create(
            $this->options['host'],
            $this->options['port']
        )->then(
                array($this, 'onConnect'),
                array($this, 'onConnectFail')
            );
    }

    function onConnect($stream) {
        echo "Connected to {$this->options['host']}:{$this->options['port']}\n";

        $this->stream = $stream;
        $this->character->setStream($stream);

        $this->character->timeConnect = time();

        $lineHandler = new LineHandler($stream, $this->character, $this->options, $this->log);
        $dataHandler = new DataHandler($stream, $lineHandler, $this->log);

        $stream->on('data', array($dataHandler, 'handle'));
        $stream->on('close', array($this, 'onConnectionClose'));
    }

    function onConnectFail() {
        echo "\nonConnectFail\n";
    }

    function onConnectionClose() {
        echo "\nConnection closed.\n";

        $this->character->loggedIn = false;
        $this->character->cleanupTime = false;
        $this->character->setState(Character::$STATE_NOTHING);

        for($i = 0; $i < $this->options['reconnectDelay']; $i++) {
            if ($i % 30 == 0) {
                echo "Waiting " . ($this->options['reconnectDelay'] - $i) . " seconds to reconnect.\n";
            }
            sleep(1);
        }

        $this->connect();
    }

    function write($data) {
        $this->stream->write($data);
    }

    public function onAnsiInput($data) {
        $this->log->log('ANSI Input: '.$data);
        // Pass-through ansi sequences from terminal
        // Not working 100%. For now, just ignore

        //$this->stream->write($data);
    }

    public function onCharInput($char) {
        $ord = ord($char);

        if ($ord >= 0x20 && $ord <= 0x7E) {
            echo $char;
        }
    }

    public function showPathsMenu() {
        if (count($this->pathsMenu) == 0) {
            echo "\n\nNo paths found\n\n";
            return;
        }
        
        echo "\n\nSelect a path:\n";

        for ($i = 0; $i < count($this->pathsMenu); $i++) {
            echo "-> /path " . ($i + 1) . " -> " . $this->pathsMenu[$i]->name . "\n";
        }

        echo "\n";
    }

    public function onLineInput($line) {
        if ($line == '/auto') {
            if ($this->character->path === null) {
                echo "\nNo path loaded.\n";
                return;
            }

            $this->character->auto = !$this->character->auto;

            if ($this->character->auto) {
                $this->character->timeAuto = time();

                echo "\n\n";
                echo "--> Auto on\n";
                echo "\n";

                $this->stream->write("\r\n");
            } else {
                echo "\n\n";
                echo "--> Auto off\n";
                echo "\n";
            }

            return;
        }

        if ($line == '/savepath') {
            $this->character->path->save();
            return;
        }

        if (substr($line, 0, 6) == '/paths') {
            $search = substr($line, 8);

            if ($search) { 
                $this->pathsMenu = $this->paths->getName($search);
            } else {
                $unique = md5($this->character->room . $this->character->exits->unique());

                $this->pathsMenu = $this->paths->getStartUnique($unique);
            }

            $this->showPathsMenu();

            return;
        }

        if (substr($line, 0, 5) == '/path') {
            $selection = (int) substr($line, 6);
            
            $selection -= 1;

            if (!isset($this->pathsMenu[$selection])) {
                echo "\n\nInvalid Selection\n\n";
                return;
            }

            echo "\n\nSelected path: " . $this->pathsMenu[$selection]->name;

            $this->character->path = $this->pathsMenu[$selection];
            $this->character->step = 0;
            $this->character->lap = 1;

            return;
        }

        /*        if ($line == '/passthru') {
                    if ($dataHandler->state == $dataHandler::STATE_PASSTHRU) {
                        $dataHandler->state = $dataHandler::STATE_COLLECT_LINE;
                        echo "Passthru off";
                    } else {
                        $dataHandler->state = $dataHandler::STATE_PASSTHRU;
                        echo "Passthru on";
                    }
                    return;
        }*/

        if ($line == '/stats') {
            $auto   = is_null($this->character->timeAuto) ? 0 : time() - $this->character->timeAuto;
            $online = time() - $this->character->timeConnect;

            $xphr = $auto <= 0 ? 0 : $this->character->expEarned / $auto * 60 * 60;

            echo "\n\n";
            printf("--> Online          %10s\n", Util::formatTimeInterval($online));
            printf("--> Auto            %10s\n", Util::formatTimeInterval($auto));
            printf("--> Lap             %10s\n", $this->character->lap);
            printf("--> Monsters Killed %10s\n", number_format($this->character->monstersKilled));
            printf("--> Exp Earned      %10s\n", Util::formatExp($this->character->expEarned));
            printf("--> Exp Per Hour    %10s\n", Util::formatExp($xphr));
            echo "\n";

            return;
        }

        if ($line == '/forcequit') {
            echo "Quitting\n";
            $this->loop->stop();

            return;
        }

        if (null !== $this->stream) {
            if ($line == "\n" || $line == "\r") {
                $this->stream->write("\r\n");
            } else {
                for($i=0;$i<strlen($line);$i++)
                    echo "\x08";
                $this->stream->write($line."\r\n");
            }
        }
    }
}
