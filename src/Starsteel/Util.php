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
}
