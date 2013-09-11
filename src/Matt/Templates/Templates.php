<?php

namespace Matt\Templates;

/**
 * Simple PHP templates for Silex.
 *
 * @author Matt Parret <matt.parrett@gmail.com>
 */
class Templates
{
    var $app;
    var $cacheTTL = 30;

    function __construct($app)
    {
        $this->app = $app;
    }

    function cache($template, $output)
    {
        file_put_contents($this->app['templ.cache_dir'] . '/' . $template, $output);
    }

    function render($template, $vars = array(), $allow_extend = true)
    {
        //if (file_exists($this->app['templ.cache_dir'].'/'.$template) && (time() - filemtime($this->app['templ.cache_dir'].'/'.$template)) < $this->cacheTTL) {
        //    return file_get_contents($this->app['templ.cache_dir'].'/'.$template);
        //}

        if (!file_exists($this->app['templ.dir'] . '/' . $template)) {
            throw new \Exception("Template not found: " . $this->app['templ.dir'].'/'.$template);
        }

        // Experimental

        /* Return a variable with default */
        $var_get = function ($key, $default = '') use ($vars) {
            return isset($vars[$key]) ? $vars[$key] : $default;
        };

        /* Echo a variable with default */
        $var_echo = function ($key, $default = '') use ($var_get) {
            echo $var_get($key, $default);
        };

        $app = $this->app;
        $is_active = function ($request_uri) use ($app) {
            return $app['request']->getRequestUri() == $request_uri;
        };

        $app = $this->app;
        $active = function () use ($app) {
            return $app['request']->getRequestUri();
        };


        // Could use $this in templates

        // Extract $app
        $app = $this->app;

        // Default title
        $title = '';

        // Extract all vars passed to render
        extract($vars);

        // Render the template
        ob_start();
        require $this->app['templ.dir'] . '/' . $template;
        $child_content = ob_get_clean();

        if (!isset($extends) || !$allow_extend) {
            return $child_content;
        }

        // If extends, render that
        ob_start();
        require $this->app['templ.dir'] . '/' . $extends;

        $out = ob_get_clean();

        // Cache it
        //$this->cache($template, $out);

        return $out;

    }

}