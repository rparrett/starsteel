<?php

namespace Starsteel;

use Starsteel\Exits;

define('NOTHING',      0);
define('BACKSTABBING', 1);
define('CASTING',      2);
define('ATTACKING',    3);
define('RESTING',      4);
define('SNEAKING',     5);
define('MEDITATING',   6);
define('RELOGGING',    7);
define('RELOGGED',     8);

class Character {
    public $stream;

    public $exits;
    public $room;
    public $loggedIn;
    public $hp = 1;
    public $maxhp = 1;
    public $ma = 1;
    public $maxma = 1;
    public $state = NOTHING;
    public $attack = "a";
    public $monstersInRoom = array();
    public $auto = false;
    public $runDistance = 1;
    public $ranDistance = 0;
    public $roomChanged = false;
    
    public $expEarned = 0;
    public $monstersKilled = 0;
    public $timeAuto = null;
    public $timeConnect = null;
    
    public $path = null;
    public $step = 0;
    public $lap = 1;

    function __construct() {
        $this->loggedIn = false;
        $this->exits = new Exits();
    }

    function setStream(&$stream) {
        $this->stream = $stream;
    }

    function fullHealth() {
        return ($this->hp / $this->maxhp) > 0.95;
    }

    function restHealth() {
        return ($this->hp / $this->maxhp) < 0.80;
    }

    function runHealth() {
        return ($this->hp / $this->maxhp) < 0.50;
    }

    function hangHealth() {
        return ($this->hp / $this->maxhp) < 0.20;
    }

    function fightMonsters() {
        if (count($this->monstersInRoom) <= 0) {
            return;
        }

        // Already attacking?
        if ($this->state == ATTACKING)
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

        $this->state = ATTACKING;
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
                $this->state = NOTHING;
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

        $this->state = NOTHING;
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

            $this->state = NOTHING;
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

        if ($this->state == RESTING)
            return;

        // If poisoned, you can't rest

        // if ($this->poisoned)
        //     return

        // If following, tell the other guy to wait
        //

        $this->stream->write("rest\r\n");
        $this->state = RESTING;

        // IsSneaking = 0
        // IsHiding = 0
        // IsCasting = 0
    }
}

?>
