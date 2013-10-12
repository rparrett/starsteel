<?php

namespace Starsteel;

use Starsteel\PathStep;

class Path {
    public $filename = null;

    public $steps = array();

    public $startUnique = "";
    public $endUnique = "";

    public $loop = false;

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

        if (isset($contents->name))
            $this->name = $contents->name;

        if (isset($contents->endUnique))
            $this->startUnique = $contents->startUnique;

        if (isset($contents->endUnique))
            $this->endUnique = $contents->endUnique;

        if (isset($contents->categories))
            $this->categories = $contents->categories;

        if (isset($contents->loop))
            $this->loop = $contents->loop;

        foreach ($contents->steps as $step) {
            $this->steps[] = new PathStep($step->unique, $step->command);
        }

        return true;
    }

    function save() {
        $contents = array(
            'name' => $this->name,
            'loop' => $this->loop,
            'startUnique' => $this->startUnique,
            'endUnique' => $this->endUnique,
            'categories' => $this->categories,
            'steps' => array()
        );

        foreach ($this->steps as $step) {
            $contents['steps'][] = $step->toArray();
        }

        file_put_contents($this->filename, json_encode($contents));
    }
}

