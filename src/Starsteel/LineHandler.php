<?php

namespace Starsteel;

class LineHandler {

    function __construct(&$capturedStream, &$character, $options, &$log) {
        $this->capturedStream = $capturedStream;
        
        $this->loginTriggers = array(
            "Otherwise type \"new\":"  => $options['username'] . "\r\n",
            "Enter your password:"     => $options['password'] . "\r\n",
            "selection"                => "/go majormud\r\n",
            "[MAJORMUD]"               => "e\r\n"
        );

        $this->moreTriggers = array(
            "(N)onstop"                => "n\r\n"
        );

        $this->character = $character;

        $this->options = $options;

        $this->log = $log;
    }

    function main() {
        $this->log->log('Taking an action');

        // if we're at the end of a path and the end room is undefined, define it.

        if ($this->character->path && 
            $this->character->step == count($this->character->path->steps) && 
            $this->character->path->endUnique == "") {

            $unique = md5($this->character->room . $this->character->exits->unique());

            echo "\nRe-learning path step ({$unique})\n";

            $this->character->path->endUnique = $unique;
        }

        if ($this->character->maxhp == 1) {
            $this->capturedStream->write("st\r\n");
            return;
        }

        if ($this->character->roomChanged) {
            $this->log->log("Resetting roomChanged");
            $this->character->roomChanged = false;
            $this->capturedStream->write("\r\n");
            return;
        }

        // If monsters here, attack or run
        if (count($this->character->monstersInRoom) > 0) {
            // Reset no. of steps for safe escape
            // if (!isSneaking)
            $this->character->ranDistance = 0;

            if (!$this->character->auto) { // || IsConfused || IsParalysed || IsBlind || IsFollowing
                $this->character->fightMonsters();
            } else {
                if ($this->character->runHealth()) { // || MonsterCnt > MaxMonsters || IsRunPath
                    $this->character->move(true);
                } else {
                    $this->character->fightMonsters();
                }
            }
        } else {
            // No monsters, need to run further to
            // get a safe distance away?

            if ($this->character->auto && $this->character->ranDistance < $this->options['runDistance']) { // && !isFollowing
                $this->character->move(false);
            } else {
                if (!$this->character->auto) { // || IsFollowing
                    $this->character->healUp();
                } else {
                    if ($this->character->restHealth() || 
                        (!$this->character->fullHealth() && $this->character->getState() == Character::$STATE_RESTING))
                    {
                        $this->character->healUp();
                    } else {
                        // if (IsBlind || IsConfused || IsParalysed) { 
                        // $this->character->healUp();
                        // } else {
                        $this->character->move(false);
                        // }
                    }
                }
                // 
            }
        }
    }

    function handle($line) {
        $this->log->log("handle: " . rtrim($line));

        if (!$this->character->loggedIn) {
            $triggered = $this->triggers($this->loginTriggers, $line);
            if (isset($triggered["[MAJORMUD]"])) {
                $this->character->loggedIn = true;
            }

            return;
        }

        if (preg_match('/.*?\[HP=(\d+)(?:\/(?:MA|KAI)=(\d+))?(?:(?:\]: \((?:Meditating|Resting)\) )|(?: \((?:Meditating|Resting)\) \]:)|\]:)$/', $line, $matches)) {
            $this->log->log("Detected a prompt");

            $this->character->hp = (double) $matches[1];

            if (isset($matches[2])) {
                $this->character->ma = (double) $matches[2];
            }

            if ($this->character->hangHealth() && $this->character->auto) {
                echo "\n\nHealth low! Hanging up!\n\n";
                $this->capturedStream->end();
            }

            // Mudwalk seems to distinguish between
            // "prompts without more stuff"
            // and "prompts with stuff"
            // and only takes actions if there's not stuff.

            if (strlen($line) == strlen($matches[0])) {
                $this->main();
            }
        }
            
        if (preg_match('/^Hits:\s+(\d+)\/(\d+)/', $line, $matches)) {
            $this->character->hp = (double) $matches[1];
            $this->character->maxhp = (double) $matches[2];
        } 

        if (preg_match('/^Mana:\s+(\d+)\/(\d+)/', $line, $matches)) {
            $this->character->ma = (double) $matches[1];
            $this->character->maxma = (double) $matches[2];
        }

        if (preg_match('/^Also here: (.*)\./', $line, $matches)) {
            $here = explode(',', $matches[1]);

            $monsters = array(
                'giant rat' => true, 
                'acid slime' => true, 
                'kobold thief' => true, 
                'carrion beast' => true,
                'lashworm' => true,
                'filthbug' => true,
                'orc rogue' => true,
                'orc sentry' => true,
                'orc lieutenant' => true,
                'thug' => true,
                'mercenary' => true,
                'dark cultist' => true,
                'half-ogre bodyguard' => true,
                'skeleton' => true,
                'zombie' => true,
                'giant bat' => true,
                'shade' => true,
                'ghoul' => true,
                'wight' => true,
                'mummy' => true
            );

            $attackables = 0;

            $this->character->monstersInRoom = array();

            foreach ($here as $monster) {
                $basename = preg_replace('/(thin|fat|small|big|large|nasty|angry|fierce|short|tall) /', '', $monster);

                if (isset($monsters[$basename])) {
                    $this->character->monstersInRoom[] = $monster;
                }
            } 
        }
        
        if (preg_match('/Combat Off/', $line, $matches)) {
            $this->character->setState(Character::$STATE_NOTHING);
        } 
        
        if (preg_match('/\*Combat Engaged\*/', $line, $matches)) {
            // if (not backstabbing or casting)
            $this->character->setState(Character::$STATE_ATTACKING);
        }
        
        if (preg_match('/(in|into) (the room )?from/', $line, $matches)) {
            $this->character->roomChanged = true;
        }

        if (preg_match('/materializes in the room./', $line, $matches)) {
            $this->character->roomChanged = true;
        }

        if (preg_match('/You bashed the door open./', $line, $matches)) {
            $this->character->roomChanged = true;
        }
       
        if (preg_match('/The door is already open./', $line, $matches)) {
            $this->character->roomChanged = true;
        }

        if ($line == "You say \"" . $this->character->lastAttackCmd . "\"\r\n") {
            $this->log->log('Whiffed, resetting state and re-checking room');

            $this->character->roomChanged = true;
            $this->character->setState(Character::$STATE_NOTHING);
        }

        if (preg_match('/You gain (\d+) experience\./', $line, $matches))  {
            $this->character->expEarned += (int) $matches[1];
            $this->character->monstersKilled += 1;

            array_shift($this->character->monstersInRoom);

            $this->character->roomChanged = true;
        }

        if (preg_match('/Please finish up and log off/', $line, $matches)) {
            $this->character->cleanupTime = true;
        }

        if (preg_match('/You notice (.*?) here./', $line, $matches)) {
            $items = explode(',', $matches[1]);
            
            foreach ($items as $item) {
                if (preg_match('/(\d+) (copper farthing|silver noble|gold crown|platinum piece|runic coin)s?/', $item, $m)) {
                    if ($m[2] == 'copper farthing' && !$this->options['lootCopper']) break;
                    if ($m[2] == 'silver noble'    && !$this->options['lootSilver']) break;
                    if ($m[2] == 'gold crown'      && !$this->options['lootGold']) break;
                    if ($m[2] == 'platinum piece'  && !$this->options['lootPlatinum']) break;
                    if ($m[2] == 'runic coin'      && !$this->options['lootRunic']) break;

                    $this->character->itemsInRoom[] = $m[1] . " " . $m[2];
                }
            }
        }

        // You will exit after a period of silent meditation.
        // Your character has been saved. If you have any comments or suggestions, please
        // Your meditation has been interrupted - you may not exit now!

        $this->triggers($this->moreTriggers, $line);
    }

    function handleAnsi($line) {
        if (preg_match('/\x1b\[1;36m(.*?)\r\n$/', $line, $matches)) {
            $room = $matches[1];

            $this->log->log("In new room: {$room}");

            $this->character->room = $room;
            $this->character->roomChanged = false;
            $this->character->monstersInRoom = array();
            $this->character->itemsInRoom = array();
        }

        if (preg_match('/\x1b\[0;32mObvious exits: (.*?)\r\n$/', $line, $matches)) {
            $matches[1] = preg_replace('/.\x08/', '', $matches[1]);
            
            $this->character->exits->clear();

            $exits = explode(',', $matches[1]);

            foreach ($exits as $exit) {
                if (preg_match('/(closed|open)? ?(door|trap door|gate|secret passage)? ?(NONE!|northeast|northwest|southeast|southwest|north|south|east|west|up|down|above|below)/i', $exit, $submatch)) 
                {
                    $this->log->log(print_r($submatch, true));

                    if ($submatch[1] == "closed") {
                        $this->character->exits[$submatch[3]] = Exits::$closed_door;
                    } else if (strtolower($submatch[2]) == "secret passage") {
                        $this->character->exits[$submatch[3]] = Exits::$secret;
                    } else if ($submatch[2]) {
                        $this->character->exits[$submatch[3]] = Exits::$open_door;
                    } else {
                        $this->character->exits[$submatch[3]] = Exits::$normal;
                    }

                    $this->log->log($this->character->exits->unique());
                }
            }
        }
    }

    function triggers($triggers, $line) {
        $triggered = array();

        $i = 0;

        while ($i < strlen($line)) {
            foreach ($triggers as $trigger => $action) {
                $original_i = $i;
                $j = 0;
                
                while ($j < strlen($trigger) && $i < strlen($line)) {
                    if ($line[$i] == $trigger[$j]) {
                        if ($j == strlen($trigger) - 1) {
                            $this->capturedStream->write($action);
                            $triggered[$trigger] = true;

                            break;
                        } else {
                            $j++;
                            $i++;
                        }
                    } else {
                        break;
                    }
                }

                $i = $original_i;
            }

            $i++;
        }

        return $triggered;
    }
}
