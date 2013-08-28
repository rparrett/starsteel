<?php

function hex_dump($data, $newline = "\n", $width_bytes = 16)
{
    if ($width_bytes == 0)
        throw new Exception("Width must be > 0");

    static $from = '';
    static $to = '';

    //static $width = 16; # number of bytes per line

    static $pad = '.'; # padding for non-visible characters

    if ($from === '') {
        for ($i = 0; $i <= 0xFF; $i++) {
            $from .= chr($i);
            $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
        }
    }

    $hex = str_split(bin2hex($data), $width_bytes  * 2);
    $chars = str_split(strtr($data, $from, $to), $width_bytes);

    $offset = 0;
    foreach ($hex as $i => $line) {
        echo sprintf('%6X', $offset) . ' : ' . implode(' ', str_split($line, 2)) . ' [' . $chars[$i] . ']' . $newline;
        $offset += $width_bytes;
    }
}

function strip_ansi($text)
{
    $text = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', "", $text);
    //$text = preg_replace( '/[^[:print:]]/', '', $text);
    //$text = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $text);
    //$text = preg_replace('/\e\[[^m]*m/', '', $text);
    //$text = preg_replace('/\x1b[^m]*m/', '', $text);
    //$text = preg_replace( '/[^[:print:]]/', '', $text);
    return $text;
}