<?php

namespace Starsteel;

class Analyzer {

    function extract_stats($str, &$stats)
    {

        preg_match_all('/(\w+[ \/]?\w+):[\s*]+(\d+\/?\d*)/', $str, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($match[1] == 'Lives/CP') {
                $parts = explode('/', $match[2]);
                $stats['lives'] = $parts[0];
                $stats['cp'] = $parts[1];
            } else {
                $key = strtolower(str_replace(' ', '_', $match[1]));

                // current/min types
                if ($key == 'hits' || $key == 'encumbrance' || $key == 'mana' || $key == 'kai') {
                    $parts = explode('/', $match[2]);
                    $stats[$key] = array('current' => $parts[0], 'max' => $parts[1]);
                } else {
                    $stats[$key] = $match[2];
                }
            }
        }
        preg_match_all('/(Name|Race|Class): (\w+)/', $str, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key = strtolower(str_replace(' ', '_', $match[1]));
            $stats[$key] = $match[2];
        }

        // Check out the statline
        preg_match_all('/\[HP=([\d-]+)\/(MA|KAI)=(\d+)\]/', $str, $matches, PREG_SET_ORDER);
        //print_r($matches);

        foreach ($matches as $match) {

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
}