<?php

namespace Symfony\Component\Console\Helper;

interface HelperInterface
{
    public function setHelperSet(?\Symfony\Component\Console\Helper\HelperSet $helperSet): void;
    public function getHelperSet(): ?\Symfony\Component\Console\Helper\HelperSet;
    public function getName(): string;
}
