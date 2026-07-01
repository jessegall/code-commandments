<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages\Tags;

/**
 * Exemption tag: a framework CONTRACT method — a hook a subclass MUST declare whose shape/array
 * return the framework dictates (`rules`, `schema`, `casts`). Registered as `on(Base::class, …)`;
 * read by near-duplicate (the shared skeleton is inherent) and array-return-bag (the mandated array
 * isn't a bag).
 */
final class ContractMethod {}
