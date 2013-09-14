<?php

namespace Starsteel;

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
    public $path = array('d', 'wait', 'u');
    public $step = 0;
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

    function __construct() {
        $this->loggedIn = false;
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
    
    function takeStep($running) {
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
        
        // Picklock instead of bash?

        // Issue next command
        
        if ($running) {
            // If we're running and not resting, we are closer to our goal
            $this->ranDistance++;

            if ($this->path[$this->step] == "wait") {
                $this->step++;
                if ($this->step >= count($this->path)) $this->step = 0;
            }
        } else {
            $this->ranDistance = 999;

            if ($this->path[$this->step] == "wait") {
                return;
            }
        }
        
        $this->stream->write($this->path[$this->step] . "\r\n");
        $this->step++;

        if ($this->step >= count($this->path)) $this->step = 0;

        $this->state = NOTHING;
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
