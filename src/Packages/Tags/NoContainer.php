<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages\Tags;

/**
 * Exemption tag: a type the framework instantiates ITSELF with no container/DI (an Eloquent cast).
 * There's nothing to inject, so a loose array/primitive parameter is the framework's calling
 * convention — read by array-bag.
 */
final class NoContainer {}
