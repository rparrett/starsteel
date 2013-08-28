<?php

namespace Matt;

class ConsoleColor2
{
    protected $codes = array();

    public function __construct()
    {
        $this->codes['color'] = array(
            'black' => 30,
            'red' => 31,
            'green' => 32,
            'brown' => 33,
            'blue' => 34,
            'purple' => 35,
            'cyan' => 36,
            'grey' => 37,
            'yellow' => 33
        );
        $this->codes['style'] = array(
            'normal' => 0,
            'bold' => 1,
            'light' => 1,
            'underscore' => 4,
            'underline' => 4,
            'blink' => 5,
            'inverse' => 6,
            'hidden' => 8,
            'concealed' => 8
        );
        $this->conversions = array(
            '%y' => array('color' => 'yellow'),
            '%g' => array('color' => 'green'),
            '%b' => array('color' => 'blue'),
            '%r' => array('color' => 'red'),
            '%p' => array('color' => 'purple'),
            '%m' => array('color' => 'purple'),
            '%c' => array('color' => 'cyan'),
            '%w' => array('color' => 'grey'),
            '%k' => array('color' => 'black'),
            '%n' => array('color' => 'reset'),
            '%Y' => array('color' => 'yellow', 'style' => 'light'),
            '%G' => array('color' => 'green', 'style' => 'light'),
            '%B' => array('color' => 'blue', 'style' => 'light'),
            '%R' => array('color' => 'red', 'style' => 'light'),
            '%P' => array('color' => 'purple', 'style' => 'light'),
            '%M' => array('color' => 'purple', 'style' => 'light'),
            '%C' => array('color' => 'cyan', 'style' => 'light'),
            '%W' => array('color' => 'grey', 'style' => 'light'),
            '%K' => array('color' => 'black', 'style' => 'light'),
            '%N' => array('color' => 'reset', 'style' => 'light'),
            '%3' => array('background' => 'yellow'),
            '%2' => array('background' => 'green'),
            '%4' => array('background' => 'blue'),
            '%1' => array('background' => 'red'),
            '%5' => array('background' => 'purple'),
            '%6' => array('background' => 'cyan'),
            '%7' => array('background' => 'grey'),
            '%0' => array('background' => 'black'),
            '%F' => array('style' => 'blink'),
            '%U' => array('style' => 'underline'),
            '%8' => array('style' => 'inverse'),
            '%9' => array('style' => 'bold'),
            '%_' => array('style' => 'bold')
        );
    }

    public function color($color = null, $style = null, $background = null)
    {

        if (is_array($color)) {
            $style = isset($color['style']) ? $color['style'] : null;
            $background = isset($color['background']) ? $color['background'] : null;
            $color = isset($color['color']) ? $color['color'] : null;
        }

        if ($color == 'reset') {
            return "\033[0m";
        }

        $code = array();
        if ($color !== NULL) {
            $code[] = $this->fgcode($color);
        }

        if ($style !== NULL) {
            $code[] = $this->style($style);
        }

        if ($background !== NULL) {
            $code[] = $this->bgcode($background);
        }

        if (empty($code)) {
            $code[] = 0;
        }

        $code = implode(';', $code);
        return "\033[{$code}m";
    }

    public function style($name)
    {
        if (!isset($this->codes['style'][$name])) return NULL;
        return $this->codes['style'][$name];
    }

    public function fgcode($name)
    {
        if (!isset($this->codes['color'][$name])) return NULL;
        return $this->codes['color'][$name];
    }

    public function bgcode($name)
    {
        $fg = $this->fgcode($name);
        if ($fg === NULL) return $fg;
        return $fg + 10;
    }

    public function bgcolor($name)
    {
        return "\033[" . $this->bgcode($name) . 'm';
    }

    public function fgcolor($name)
    {
        return "\033[" . $this->fgcode($name) . 'm';
    }

    public function convert($string, $colored = true)
    {
        if ($colored) {
            $string = str_replace('%%', '% ', $string);
            foreach ($this->conversions as $key => $value) {
                $string = str_replace($key, $this->color($value), $string);
            }
            $string = str_replace('% ', '%', $string);
        } else {
            $string = preg_replace('/%((%)|.)/', '$2', $string);
        }

        return $string;
    }

    public function escape($string)
    {
        return str_replace('%', '%%', $string);
    }

    public function strip($string)
    {
        return preg_replace('/\033\[[\d;]+m/', '', $string);
    }

}

