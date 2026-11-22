<?php

namespace Symfony\Component\Console\Helper;

class HelperSet implements \IteratorAggregate, \Traversable
{
    private array $helpers = array (
);
    public function __construct(array $helpers = array (
)) {}
    public function set(\Symfony\Component\Console\Helper\HelperInterface $helper, ?string $alias = NULL): void {}
    public function has(string $name): bool {}
    public function get(string $name): \Symfony\Component\Console\Helper\HelperInterface {}
    public function getIterator(): \Traversable {}
}
