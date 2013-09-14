<?php

namespace Starsteel;

class Logger {
    private $stream;
    private $prefix;

    function __construct($stream = null, $prefix = '') {
        $this->stream = $stream;
        $this->prefix = $prefix;
    }

    function log($text) {
        if (null === $this->stream || !$this->stream->isWritable())
            return;

        $time = date("Y-m-d H:i:s");
        $this->stream->write($time.' '.$this->prefix.' '.$text."\n");
    }
}