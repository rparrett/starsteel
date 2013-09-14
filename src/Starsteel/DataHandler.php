<?php

namespace Starsteel;


class DataHandler {
    const STATE_COLLECT_LINE = 0;
    const STATE_COLLECT_ANSI = 1;
    const STATE_PASSTHRU = 2;

    function __construct(&$capturedStream, &$lineHandler) {
        $this->capturedStream = $capturedStream;
        $this->lineHandler = $lineHandler;

        $this->line  = "";
        $this->aline = "";
        $this->escape = "";
        $this->state = self::STATE_COLLECT_LINE;
    }

    function handle($data) {
        if ($this->state == self::STATE_PASSTHRU) {
            echo $data;
            return;
        }

        for ($i = 0; $i < strlen($data); $i++) {
            $chr = $data[$i];
            $ord = ord($chr);

            switch ($this->state) {
                case self::STATE_COLLECT_LINE:
                    if ($chr == "\x1b") {
                        $this->state = self::STATE_COLLECT_ANSI;
                    } elseif ($chr == "\n") {
                        $this->line  .= $chr;
                        $this->aline .= $chr;

                        $this->lineHandler->handle($this->line);
                        $this->lineHandler->handleAnsi($this->aline);

                        $this->line  = "";
                        $this->aline = "";
                    } else {
                        $this->line  .= $chr;
                        $this->aline .= $chr;
                    }

                    break;
                case self::STATE_COLLECT_ANSI:
                    $this->escape .= $chr;

                    if ($chr == "[") {

                    } elseif ($ord >= 64 && $ord <= 126) {
                        $this->state = self::STATE_COLLECT_LINE;

                        // execute the collected sequence

                        if ($this->escape == "[6n") {
                            // this gets sent during "auto sensing"
                            // send the expected reply

                            $this->capturedStream->write("\x1b[0,0R");
                        } elseif (preg_match('/m$/', $this->escape)) {
                            // just pass colors through

                            // $this->aline .= "\x1b" . $this->escape;
                            $this->aline .= "\x1b" . $this->escape;
                        } else {
                            $this->aline .= "\x1b" . $this->escape;
                        }

                        $this->escape = "";
                    }
                
                    break;
            }
        }

        // Prompt detection
        if (preg_match('/[:?]\s*(( \((Meditating|Resting)\) )?(\]:)?)?\s*$/', $this->line, $matches)) {
            $this->lineHandler->handle($this->line);
            $this->line = "";
            $this->lineHandler->handleAnsi($this->aline);
            $this->aline = "";
        }
    }
}