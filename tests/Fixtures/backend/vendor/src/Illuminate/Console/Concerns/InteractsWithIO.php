<?php

namespace Illuminate\Console\Concerns;

trait InteractsWithIO
{
    protected $components = NULL;
    protected $input = NULL;
    protected $output = NULL;
    protected $verbosity = 32;
    protected $verbosityMap;
    /** @return bool */
    public function hasArgument($name) {}
    /** @return ($key */
    public function argument($key = NULL) {}
    /** @return array<array|string|float|int|bool|null> */
    public function arguments() {}
    /** @return bool */
    public function hasOption($name) {}
    /** @return ($key */
    public function option($key = NULL) {}
    /** @return array<array|string|float|int|bool|null> */
    public function options() {}
    /** @return bool */
    public function confirm($question, $default = false) {}
    /** @return mixed */
    public function ask($question, $default = NULL) {}
    /** @return mixed */
    public function anticipate($question, $choices, $default = NULL) {}
    /** @return mixed */
    public function askWithCompletion($question, $choices, $default = NULL) {}
    /** @return mixed */
    public function secret($question, $fallback = true) {}
    /** @return string|array */
    public function choice($question, array $choices, $default = NULL, $attempts = NULL, $multiple = false) {}
    /** @return void */
    public function table($headers, $rows, $tableStyle = 'default', array $columnStyles = array (
)) {}
    /** @return ($totalSteps */
    public function withProgressBar($totalSteps, \Closure $callback) {}
    /** @return void */
    public function info($string, $verbosity = NULL) {}
    /** @return void */
    public function line($string, $style = NULL, $verbosity = NULL) {}
    /** @return void */
    public function comment($string, $verbosity = NULL) {}
    /** @return void */
    public function question($string, $verbosity = NULL) {}
    /** @return void */
    public function error($string, $verbosity = NULL) {}
    /** @return void */
    public function warn($string, $verbosity = NULL) {}
    /** @return void */
    public function alert($string, $verbosity = NULL) {}
    /** @return $this */
    public function newLine($count = 1) {}
    /** @return void */
    public function setInput(\Symfony\Component\Console\Input\InputInterface $input) {}
    /** @return void */
    public function setOutput(\Illuminate\Console\OutputStyle $output) {}
    /** @return void */
    protected function setVerbosity($level) {}
    /** @return int */
    protected function parseVerbosity($level = NULL) {}
    /** @return \Illuminate\Console\OutputStyle */
    public function getOutput() {}
    /** @return \Illuminate\Console\View\Components\Factory */
    public function outputComponents() {}
}
