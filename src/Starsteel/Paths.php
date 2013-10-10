<?php

namespace Starsteel;

use Starsteel\Path;

class Paths {
    private $startUniques = array();
    private $categories = array();

    function __construct() {
        $filenames = glob("../paths/*.path");

        foreach ($filenames as $filename) {
            $path = new Path();
            $result = $path->load($filename);

            if (!$result) continue;

            if (!isset($this->startUniques[$path->startUnique]))
                $this->startUniques[$path->startUnique] = array();

            $this->startUniques[$path->startUnique][] = $path;

            foreach ($path->categories as $category) {            
                if (!isset($this->categories[$category]))
                    $this->categories[$category] = array();

                $this->categories[$category][] = $path;
            }
        }
    }

    function getStartUnique($startUnique) {
        if (!isset($this->startUniques[$startUnique]))
            return array();

        return $this->startUniques[$startUnique];
    }

    function getName($name) {
        $matches = array();

        foreach ($this->startUniques as $paths) {
            foreach ($paths as $path) {
                if (preg_match('/' . $name . '/i',  $path->name)) {
                    $matches[] = $path;
                }
            }
        }

        return $matches;
    }
}
