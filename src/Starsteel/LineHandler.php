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

    function main() {
        if ($this->character->roomChanged) {
            $this->character->roomChanged = false;
            $this->capturedStream->write("l\r\n");
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
                    $this->character->takeStep(true);
                } else {
                    $this->character->fightMonsters();
                }
            }
        } else {
            // No monsters, need to run further to
            // get a safe distance away?

            if ($this->character->auto && $this->character->ranDistance < $this->character->runDistance) { // && !isFollowing
                $this->character->takeStep(false);
            } else {
                if (!$this->character->auto) { // || IsFollowing
                    $this->character->healUp();
                } else {
                    if ($this->character->restHealth() || 
                        (!$this->character->fullHealth() && $this->character->state == RESTING))
                    {
                        $this->character->healUp();
                    } else {
                        // if (IsBlind || IsConfused || IsParalysed) { 
                        // $this->character->healUp();
                        // } else {
                        $this->character->takeStep(false);
                        // }
                    }
                }
                // 
            }
        }
    }

    function handle($line) {
        if (!$this->character->loggedIn) {
            $triggered = $this->triggers($this->loginTriggers, $line);
            if (isset($triggered["[MAJORMUD]"])) {
                $this->character->loggedIn = true;

                $this->capturedStream->write("st\r\n");
            }
        }

        if (preg_match('/^\[HP=(\d+)(?:\/(?:MA|KAI)=(\d+))?(?:\]:)?(?: \((?:Meditating|Resting)\) )?(?:\]:)?$/', $line, $matches)) {

            $this->character->hp = (double) $matches[1];

            if (isset($matches[2])) {
                $this->character->ma = (double) $matches[2];
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
                'filthbug' => true
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
            $this->character->state = NOTHING;
        } 
        
        if (preg_match('/\*Combat Engaged\*/', $line, $matches)) {
            // if (not backstabbing or casting)
            $this->character->state = ATTACKING;
        }
        
        if (preg_match('/(in|into) (the room )?from/', $line, $matches)) {
            $this->character->roomChanged = true;
        }

        if (preg_match('/You gain (\d+) experience\./', $line, $matches))  {
            $this->character->expEarned += (int) $matches[1];
            $this->character->monstersKilled += 1;

            array_shift($this->character->monstersInRoom);

            $this->character->roomChanged = true;
        }

        $this->triggers($this->moreTriggers, $line);
    }

    function handleAnsi($line) {
        if (preg_match('/\x1b\[1;36m(.*?)\r\n$/', $line, $matches)) {
            $room = $matches[1];

            $this->character->room = $room;
            $this->character->roomChanged = false;
            $this->character->monstersInRoom = array();
        }

        if (preg_match('/\x1b\[0;32mObvious exits: (.*?)\r\n$/', $line, $matches)) {
            $exits = preg_replace('/.\x08/', '', $matches[1]);
            $exits = str_replace(' ', '', $exits);
            $exits = explode(',', $exits);

            $this->character->exits = $exits;
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
