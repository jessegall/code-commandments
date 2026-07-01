<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages\Tags;

/**
 * Exemption tag: a framework ENTRY POINT — an HTTP/RPC request, the place raw input crosses into
 * your domain. A class registered under it is read by feature-envy (don't move behaviour onto it)
 * and pass-the-object (a method taking it is a boundary, allowed to unpack input).
 */
final class Boundary {}
