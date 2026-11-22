<?php

namespace Illuminate\Console\Concerns;

trait InteractsWithIO
{
    protected $components = NULL;
    protected $input = NULL;
    protected $output = NULL;
    protected $verbosity = 32;
    protected $verbosityMap;
    public function hasArgument($name) {}
    public function argument($key = NULL) {}
    public function arguments() {}
    public function hasOption($name) {}
    public function option($key = NULL) {}
    public function options() {}
    public function confirm($question, $default = false) {}
    public function ask($question, $default = NULL) {}
    public function anticipate($question, $choices, $default = NULL) {}
    public function askWithCompletion($question, $choices, $default = NULL) {}
    public function secret($question, $fallback = true) {}
    public function choice($question, array $choices, $default = NULL, $attempts = NULL, $multiple = false) {}
    public function table($headers, $rows, $tableStyle = 'default', array $columnStyles = array (
)) {}
    public function withProgressBar($totalSteps, \Closure $callback) {}
    public function info($string, $verbosity = NULL) {}
    public function line($string, $style = NULL, $verbosity = NULL) {}
    public function comment($string, $verbosity = NULL) {}
    public function question($string, $verbosity = NULL) {}
    public function error($string, $verbosity = NULL) {}
    public function warn($string, $verbosity = NULL) {}
    public function alert($string, $verbosity = NULL) {}
    public function newLine($count = 1) {}
    public function setInput(\Symfony\Component\Console\Input\InputInterface $input) {}
    public function setOutput(\Illuminate\Console\OutputStyle $output) {}
    protected function setVerbosity($level) {}
    protected function parseVerbosity($level = NULL) {}
    public function getOutput() {}
    public function outputComponents() {}
}
