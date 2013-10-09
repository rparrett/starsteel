<?php

namespace Starsteel;

use Starsteel\Exits;

class Character {
    public $stream;

    public $exits;
    public $room;
    public $loggedIn;
    public $hp = 1;
    public $maxhp = 1;
    public $ma = 1;
    public $maxma = 1;
    private $state = "nothing";
    public $attack = "a";
    public $monstersInRoom = array();
    public $itemsInRoom = array();
    public $auto = false;
    public $ranDistance = 0;
    public $roomChanged = false;

    public $expEarned = 0;
    public $monstersKilled = 0;
    public $timeAuto = null;
    public $timeConnect = null;

    public $reconnectDelay = 300;
    public $cleanupTime = false;

    public $path = null;
    public $step = 0;
    public $lap = 1;

    public static $STATE_NOTHING        = 'nothing';
    public static $STATE_BACKSTABBING   = 'backstabbing';
    public static $STATE_CASTING        = 'casting';
    public static $STATE_ATTACKING      = 'attacking';
    public static $STATE_RESTING        = 'resting';
    public static $STATE_SNEAKING       = 'sneaking';
    public static $STATE_MEDITATING     = 'meditating';
    public static $STATE_RELOGGING      = 'relogging';
    public static $STATE_RELOGGED       = 'relogged';

    function __construct(&$log, $options) {
        $this->loggedIn = false;
        $this->exits = new Exits();
        $this->log = $log;
        $this->options = $options;
    }

    function setStream(&$stream) {
        $this->stream = $stream;
    }

    function fullHealth() {
        return ($this->hp / $this->maxhp) > $this->options['fullHealth'];
    }

    function restHealth() {
        return ($this->hp / $this->maxhp) < $this->options['restHealth'];
    }

    function runHealth() {
        return ($this->hp / $this->maxhp) < $this->options['runHealth'];
    }

    function hangHealth() {
        return ($this->hp / $this->maxhp) < $this->options['hangHealth'];
    }

    function fightMonsters() {
        if (count($this->monstersInRoom) <= 0) {
            return;
        }

        // Already attacking?
        if ($this->state == self::$STATE_ATTACKING)
            return;

        // If player is sneaking, back-stab first

        // Previously back-stabbing?
        //  // Done back-stab yet?
        //
        //  // Do we need to run if it didn't work?
        //
        // Attack spell?
        //
        // Need to change weapons?


        // Attack the monster

        $monster = $this->monstersInRoom[0];

        $this->stream->write($this->attack . " " . $monster . "\r\n");

        $this->setState(self::$STATE_ATTACKING);
    }

    function getState() {
        return $this->state;
    }

    function setState($state) {
        $this->log->log('State Change: ' . $this->state . ' -> ' . $state);

        $this->state = $state;
    }

    function move($running) {
        // Bless self
        //
        // Following another player or
        // waiting for a following player?
        //
        // If (IsFollowing)

        if (!$this->auto) // || IsWaiting
            return;

        while (count($this->itemsInRoom) > 0) {
            $item = array_pop($this->itemsInRoom);
            $this->stream->write("get " . $item . "\r\n");
            return;
        }

        if ($this->cleanupTime && !$running) {
            $this->stream->write("quit\r\n");
            return;
        }

        // If not running, can we sneak?

        // Is the path not a loop and also are we done walking it?

        // Save last command
        // 
        // Are we running out of necessity?
        // Display reason why

        // TODO Picklock instead of bash?

        if (substr($this->path->steps[$this->step]->command, 0, 4) == "bash") {
            $dir = substr($this->path->steps[$this->step]->command, 5);

            if ($this->exits[$dir] == Exits::$closed_door) {
                $this->stream->write("bash "  . $dir . "\r\n");

                // TODO: modify exits when we see "bashed the door open"
                // so that we don't have to re-examine the room

                $this->stream->write("\r\n");
                $this->setState(self::$STATE_NOTHING);
                return;
            } else {
                $this->skipStep();
            }
        }

        if (substr($this->path->steps[$this->step]->command, 0, 6) == "search") {
            $dir = substr($this->path->steps[$this->step]->command, 7);

            if ($this->exits[$dir] !== Exits::$normal) {
                $this->stream->write("search "  . $dir . "\r\n");

                // TODO: modify exits when we see "found an exit"
                // so that we don't have to re-examine the room

                $this->stream->write("\r\n");
                $this->setState(self::$STATE_NOTHING);
                return;
            } else {
                $this->skipStep();
            }
        }

        // Issue next command

        if ($running) {
            // If we're running and not resting, we are closer to our goal
            $this->ranDistance++;

            if ($this->path->steps[$this->step]->command == "wait") {
                $this->skipStep();
            }
        } else {
            $this->ranDistance = 999;

            if ($this->path->steps[$this->step]->command == "wait") {
                return;
            }
        }

        $this->takeStep();

        $this->setState(self::$STATE_NOTHING);
    }

    function skipStep() {
        $this->step++;

        // We are on a loop
        if ($this->step >= count($this->path->steps)) {
            if ($this->path->isLoop()) {
                $this->step = 0;
            } else {
                // We have arrived at our destination.

                $this->auto = false;
            }
        }
    }

    function takeStep() {
        if ($this->path->steps[$this->step]->unique == "") {
            echo "\nRe-learning path step\n";

            $unique = md5($this->room . $this->exits->unique());

            echo "\n\nUnique: {$unique}\n\n";

            $this->path->steps[$this->step]->unique = $unique;

            if ($this->step == 0 && $this->path->startUnique == "")
                $this->path->startUnique = $unique;
        }

        $this->stream->write($this->path->steps[$this->step]->command . "\r\n");
        $this->step++;

        // We are on a loop
        if ($this->step >= count($this->path->steps)) {
            if ($this->path->isLoop()) {
                $this->step = 0;
                $this->lap++;
            } else {
                // We have arrived at our destination

                $this->auto = false;
            }
        }
    }

    function healUp() {
        // Heal complete?
        if ($this->fullHealth()) { // fullMana, !IsBlind, !isPoisoned, !isConfsued, !isParalysed
            // If following, tell the other guy it's ok now

            // if CanSneak && !IsHiding, hide
            //

            $this->setState(self::$STATE_NOTHING);
            return;
        }

        // Blind?
        //
        // Paralysed?
        //
        // Poisoned?
        //
        // Healing Spell
        //

        if ($this->state == self::$STATE_RESTING)
            return;

        // If poisoned, you can't rest

        // if ($this->poisoned)
        //     return

        // If following, tell the other guy to wait
        //

        $this->stream->write("rest\r\n");
        $this->setState(self::$STATE_RESTING);

        // IsSneaking = 0
        // IsHiding = 0
        // IsCasting = 0
    }
}

?>
