<?php

namespace Starsteel;

class Exits implements \arrayaccess {
    public static $none = 0;
    public static $normal = 1;
    public static $closed_door = 2;
    public static $open_door = 3;
    public static $secret = 4;
    
    private static $longToShort = array(
        'up'    => 'u',
        'above' => 'u',
        'down'  => 'd',
        'below' => 'd',
        'north' => 'n',
        'south' => 's',
        'east'  => 'e',
        'west'  => 'w',
        'northeast' => 'ne',
        'northwest' => 'nw',
        'southeast' => 'se',
        'southwest' => 'sw',
    );

    private $container = array();

    public function __construct() {
    }

    public function clear() {
        $this->container = array();
    }

    public function offsetSet($offset, $value) {
        if (isset(Exits::$longToShort[$offset])) $offset = Exits::$longToShort[$offset];

        $this->container[$offset] = $value;
    }

    public function offsetExists($offset) {
        return true;
    }

    public function offsetUnset($offset) {
        unset($this->container[$offset]);
    }

    public function offsetGet($offset) {
        if (isset(Exits::$longToShort[$offset])) $offset = Exits::$longToShort[$offset];

        if (isset($this->container[$offset])) return $this->container[$offset];

        return 0;
    }

    public function unique() {
        $unique = "";

        foreach ($this->container as $key => $value) {
            if ($value == Exits::$secret) continue;

            if ($value == Exits::$open_door || $value == Exits::$closed_door) {
                $unique .= "door";
            }

            $unique .= $key;
        }

        return $unique;
    }
}
