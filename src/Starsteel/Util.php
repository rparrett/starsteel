<?php

namespace Starsteel;

class Util {
    static function formatTimeInterval($time) {
        $seconds = floor($time % 60);
        
        $time -= $seconds;

        $minutes = floor(($time % 3600) / 60);

        $time -= $minutes;

        $hours = floor($time / 3600);

        return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
    }

    static function formatExp($xp) {
        if ($xp > 10000) 
            return number_format(round($xp / 1000.0)) . "k";
        else 
            return number_format($xp);

    }

    ////////////////////////////////////////////////////////////////
    // React-logging (this might be silly)
    ////////////////////////////////////////////////////////////////

    static function filestream_factory($filename, $loop) {
        echo "Logging to $filename\n";

        $fd = fopen($filename, 'a');

        if (false === $fd) {
            throw new Exception("Couldn't open log!");
        }

        stream_set_blocking($fd, 0);

        $stream = new \React\Stream\Stream($fd, $loop);
        $stream->on('error', function($err, $s) {
            throw new Exception("Log died?");
        });

        return $stream;
    }

    static function hex_dump($data, $newline = "\n", $width_bytes = 16, $echo = false)
    {
        if ($width_bytes == 0)
            throw new Exception("Width must be > 0");

        $from = '';
        $to = '';

        //static $width = 16; # number of bytes per line

        $pad = '.'; # padding for non-visible characters

        if ($from === '') {
            for ($i = 0; $i <= 0xFF; $i++) {
                $from .= chr($i);
                $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
            }
        }

        $hex = str_split(bin2hex($data), $width_bytes  * 2);
        $chars = str_split(strtr($data, $from, $to), $width_bytes);

        $offset = 0;
        $ret = '';
        foreach ($hex as $i => $line) {
            $ret .= sprintf('%6X', $offset) . ' : ' . implode(' ', str_split($line, 2)) . ' [' . $chars[$i] . ']' . $newline;
            $offset += $width_bytes;
        }

        if ($echo)
            echo $ret;
        return $ret;
    }

    static function strip_ansi($text)
    {
        $text = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', "", $text);
        //$text = preg_replace( '/[^[:print:]]/', '', $text);
        //$text = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $text);
        //$text = preg_replace('/\e\[[^m]*m/', '', $text);
        //$text = preg_replace('/\x1b[^m]*m/', '', $text);
        //$text = preg_replace( '/[^[:print:]]/', '', $text);
        return $text;
    }
}
