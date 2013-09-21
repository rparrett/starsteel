<?php

namespace Starsteel;

use Starsteel\PathStep;

class Path {
    public $filename = null;

    public $steps = array();

    public $startUnique = null;
    public $endUnique = null;

    public $categories = array();

    function __construct() {
    }

    function load($filename) {
        $contents = file_get_contents($filename);
        if ($contents === false)
            return false;

        $this->filename = $filename;

        $contents = json_decode($contents);
        if ($contents === null)
            return false;

        $this->name = $contents->name;
        $this->startUnique = $contents->startUnique;
        $this->endUnique = $contents->endUnique;
        $this->categories = $contents->categories;

        foreach ($contents->steps as $step) {
            $this->steps[] = new PathStep($step->unique, $step->command);
        }
    }

    function save() {

    }

    function isLoop() {
        return ($this->startUnique == $this->endUnique);
    }
}

