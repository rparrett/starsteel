<?php

namespace Matt;

use Evenement\EventEmitter;

class InputHandler extends EventEmitter
{
    var $loop;

    function __construct($loop)
    {
        $this->loop = $loop;
        $inputStream = new \React\Stream\Stream(STDIN, $loop);
        $that = $this;
        $buf = '';
        $inputStream->on('data', function ($data) use (&$that, &$buf) {
            /*echo "\ninputdata=\n";
            print_r($data);
            echo "\n";*/
            $buf .= $data;
            if (strpos($buf, "\n") !== FALSE) {
                $that->emit('input', array(trim($buf)));
                $buf = '';
            }
        });
    }
}
