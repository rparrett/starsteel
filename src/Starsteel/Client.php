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

    public $input;

    function __construct($loop, $connector, $options, $log) {
        $this->loop = $loop;
        $this->connector = $connector;
        $this->options = $options;
        $this->log = $log;
        
        $input = new InputHandler($loop);
        $input->on('ansi', array($this, 'onAnsiInput'));
        $input->on('char', array($this, 'onCharInput'));
        $input->on('line', array($this, 'onLineInput'));
        
        $this->character = new Character();
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
        $this->character->state = 0;

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

    public function onLineInput($line) {
        if ($line == '/auto') {
            $this->character->auto = !$this->character->auto;

            if ($this->character->auto) {
                $this->character->timeAuto = time();

                if ($this->character->path === null) {
                    $path = new Path();
                    $result = $path->load('../paths/slums.path');
                    if ($result === false) {
                        echo "\nError loading path. Aborting.\n";

                        $this->character->auto = false;

                        return;
                    }

                    $this->character->path = $path;
                    $this->character->step = 0;
                    $this->character->lap = 1;
                }

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

        if (substr($line, 0, 9) == '/loadpath') {
            $filename = "../paths/" . substr($line, 10) . ".path";

            $path = new Path();
            $result = $path->load($filename);
            if ($result === false) {
                echo "\nError loading path. Aborting.\n";

                $this->character->auto = false;

                return;
            } else {
                echo "\nPath loaded\n";
            }

            $this->character->path = $path;
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

        if ($line == '/hexdump') {
            $this->options['hexdump'] = !$this->options['hexdump'];
            if ($this->options['hexdump']) {
                echo "Hexdump on";
            } else {
                echo "Hexdump off";
            }
            return;
        }

        if ($line == '/line') {
            $this->options['line'] = !$this->options['line'];

            return;
        }

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
