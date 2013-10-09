<?php

namespace Starsteel;

class PathStep {
    public $unique = "";
    public $command;

    function __construct($unique, $command) {
        $this->unique = $unique;
        $this->command = $command;
    }

    function toArray() {
        return array('unique' => $this->unique, 'command' => $this->command);
    }
}
