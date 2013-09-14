<?php

namespace Matt;

use Evenement\EventEmitter;

class InputHandler extends EventEmitter
{
    var $loop;
    var $state;
    var $ansi = '';
    var $line = '';

    const STATE_COLLECT_ANSI = 0;
    const STATE_COLLECT_LINE = 1;

    function __construct($loop)
    {
        $this->state = self::STATE_COLLECT_LINE;

        $this->loop = $loop;

        $in =  fopen("/dev/tty", "r");

        stream_set_read_buffer($in, 0);
        stream_set_blocking($in, 0);

        $inputStream = new \React\Stream\Stream($in, $loop);
        $inputStream->bufferSize = 1;

        $that = $this;
        $buf = '';
        $inputStream->on('data', function ($data) use (&$that, &$buf) {

            // Probably just a single character since we're using a buffer size of 1
            $len = strlen($data);

            for($i = 0; $i < $len; $i++) {
                $chr = $data[$i];

                if ($that->state == $that::STATE_COLLECT_ANSI) {
                    $that->ansi .= $chr;

                    $ord = ord($chr);

                    if ($chr == '[') {
                        continue;
                    }

                    if ($ord >= 64 && $ord <= 126) {
                        $that->state = $that::STATE_COLLECT_LINE;
                        $that->emit('ansi', array($that->ansi));
                        $that->ansi = '';
                        continue;
                    }
                } else {
                    if ($chr == "\x1b") {
                        $that->ansi .= "\x1b";
                        $that->state = $that::STATE_COLLECT_ANSI;
                        continue;
                    }

                    if ($chr == "\r" || $chr == "\n") {
                        $that->emit('line', array($that->line));
                        $that->line = '';
                        continue;
                    }

                    $that->line .= $chr;

                    $that->emit('char', array($chr));
                }
            }
        });
    }
}
