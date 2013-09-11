<?php

namespace Starsteel;

define('INITIALIZING', 0);
define('WALKING',      1);
define('RESTING',      2);
define('FIGHTING',     3);
define('RUNNING',      4);

class Character {
    public $exits;
    public $room;
    public $loggedIn;
    public $hp = 1;
    public $maxhp = 1;
    public $ma = 1;
    public $maxma = 1;
    public $path = array('d', 'u');
    public $step = 0;
    public $state = INITIALIZING;
    public $attack = "a";
    public $monstersInRoom = array();
    public $earnedExp = 0;

    function __construct(&$capturedStream) {
        $this->loggedIn = false;

        $this->capturedStream = $capturedStream;
    }

    function takeStep() {
        $this->capturedStream->write($this->path[$this->step] . "\r\n");
        $this->step++;

        if ($this->step >= count($this->path)) $this->step = 0;
    }

    function fullHealth() {
        return ($this->hp / $this->maxhp) > 0.95;
    }

    function runHealth() {
        return ($this->hp / $this->maxhp) < 0.50;
    }

    function hangHealth() {
        return ($this->hp / $this->maxhp) < 0.20;
    }

    function fightMonsters() {
        if (count($this->monstersInRoom) > 0) {
            $monster = $this->monstersInRoom[0];

            $this->capturedStream->write($this->attack . " " . $monster . "\r\n");

            return true;
        }

        return false;
    }
}

?>
