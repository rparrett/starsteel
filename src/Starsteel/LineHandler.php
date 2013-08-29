<?php

namespace Starsteel;

class LineHandler {

    function __construct(&$capturedStream, &$character, $options) {
        $this->capturedStream = $capturedStream;
        
        $this->loginTriggers = array(
            "Otherwise type \"new\":"  => $options['username'] . "\r\n",
            "Enter your password"      => $options['password'] . "\r\n",
            "selection"                => "/go majormud\r\n",
            "[MAJORMUD]"               => "e\r\n",
            "(N)onstop"                => "n\r\n"
        );

        $this->character = $character;
    }

    function handle($line) {
        if (!$this->character->loggedIn) {
            $this->triggers($this->loginTriggers, $line);
        }
    }

    function handleAnsi($line) {
        echo $line;
    }

    function triggers($triggers, $line) {
        $i = 0;

        while ($i < strlen($line)) {
            foreach ($triggers as $trigger => $action) {
                $original_i = $i;
                $j = 0;
                
                while ($j < strlen($trigger) && $i < strlen($line)) {
                    if ($line[$i] == $trigger[$j]) {
                        if ($j == strlen($trigger) - 1) {
                            $this->capturedStream->write($action);

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
/*
        foreach ($triggers as $trigger => $action) {
            if (preg_match("/{$trigger}/", $line)) {
                $this->capturedStream->write($action . "\r\n");
            }
        }*/
    }
}
