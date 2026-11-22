<?php

namespace Symfony\Component\HttpFoundation\Session;

interface SessionInterface
{
    public function start(): bool;
    public function getId(): string;
    public function setId(string $id): void;
    public function getName(): string;
    public function setName(string $name): void;
    public function invalidate(?int $lifetime = NULL): bool;
    public function migrate(bool $destroy = false, ?int $lifetime = NULL): bool;
    public function save(): void;
    public function has(string $name): bool;
    public function get(string $name, mixed $default = NULL): mixed;
    public function set(string $name, mixed $value): void;
    public function all(): array;
    public function replace(array $attributes): void;
    public function remove(string $name): mixed;
    public function clear(): void;
    public function isStarted(): bool;
    public function registerBag(\Symfony\Component\HttpFoundation\Session\SessionBagInterface $bag): void;
    public function getBag(string $name): \Symfony\Component\HttpFoundation\Session\SessionBagInterface;
    public function getMetadataBag(): \Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
}
