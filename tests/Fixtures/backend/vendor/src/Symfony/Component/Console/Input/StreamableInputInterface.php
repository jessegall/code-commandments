<?php

namespace Symfony\Component\Console\Input;

interface StreamableInputInterface extends \Symfony\Component\Console\Input\InputInterface, \Stringable
{
    public function setStream($stream): void;
    public function getStream();
}
