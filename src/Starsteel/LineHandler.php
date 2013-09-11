<?php

namespace Starsteel;

class LineHandler {

    function __construct(&$capturedStream, &$character, $options) {
        $this->capturedStream = $capturedStream;
        
        $this->loginTriggers = array(
            "Otherwise type \"new\":"  => $options['username'] . "\r\n",
            "Enter your password"      => $options['password'] . "\r\n",
            "selection"                => "/go majormud\r\n",
            "[MAJORMUD]"               => "e\r\n"
        );

        $this->moreTriggers = array(
            "(N)onstop"                => "n\r\n"
        );

        $this->character = $character;
    }

    function handle($line) {
        if (!$this->character->loggedIn) {
            $triggered = $this->triggers($this->loginTriggers, $line);
            if (isset($triggered["[MAJORMUD]"])) {
                $this->character->loggedIn = true;

                $this->capturedStream->write("st\r\n");
            }
        }

        if (preg_match('/^\[HP=(\d+)\/MA=(\d+)\]/', $line, $matches)) {
            $this->character->hp = (double) $matches[1];
            $this->character->ma = (double) $matches[2];

            if ($this->character->state == INITIALIZING) {
                $this->character->state = WALKING;
            }

            if ($this->character->runHealth()) {
                if ($this->character->state == FIGHTING) {
                    $this->character->state = RUNNING;

                    $this->character->takeStep();
                }
            } else if ($this->character->fullHealth()) {
                if ($this->character->state == RESTING) {
                    $this->character->state = WALKING;

                    $this->capturedStream->write("l\r\n");
                }
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
                'filthbug' => true
            );

            $attackables = 0;

            $this->character->monsters_in_room = array();

            foreach ($here as $monster) {
                $basename = preg_replace('/(thin|fat|small|big|large|nasty|angry|fierce|short|tall) /', '', $monster);

                if (isset($monsters[$basename])) {
                    $this->character->monsters_in_room[] = $monster;
                }
            } 
        }
        
        if (preg_match('/Combat Off/', $line, $matches)) {
            $this->character->state = RESTING;
        } 
        
        if (preg_match('/\*Combat Engaged\*/', $line, $matches)) {
            $this->character->state = FIGHTING;
        }
        
        if (preg_match('/(in|into) (the room )?from/', $line, $matches)) {
            $this->capturedStream->write("l\r\n");
        }

        $this->triggers($this->moreTriggers, $line);
    }

    function handleAnsi($line) {
        if (preg_match('/\x1b\[1;36m(.*?)\r\n$/', $line, $matches)) {
            $room = $matches[1];

            $this->character->room = $room;
        }

        if (preg_match('/\x1b\[0;32mObvious exits: (.*?)\r\n$/', $line, $matches)) {
            $exits = preg_replace('/.\x08/', '', $matches[1]);
            $exits = str_replace(' ', '', $exits);
            $exits = explode(',', $exits);

            $this->character->exits = $exits;

            if ($this->character->state == INITIALIZING) {
            } else if ($this->character->state == RESTING) {
                $this->character->fightMonsters();
            } else if ($this->character->state == WALKING) {
                 $this->character->fightMonsters() || $this->character->takeStep();
            } else if ($this->character->state == RUNNING) {
                if (count($this->character->monsters_in_room) > 0) {
                    $this->character->takeStep();
                } else {
                    $this->character->state = RESTING;

                    $this->capturedStream->write("rest\r\n");
                }
            }

            $this->character->monsters_in_room = array();
        }

        //hex_dump($line, "\n", 48);
        echo $line;
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
