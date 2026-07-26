<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

/**
 * A fix that must run AFTER every other one — a normaliser that reshapes code the other rules have
 * already accepted. Marking a {@see Repentable} with this moves its step to the end of the
 * {@see \JesseGall\CodeCommandments\Scribes\ScribeChain}, so it never reformats something a content
 * rule is about to rewrite or delete: shape is settled last, when the substance is done.
 */
interface RunsLast {}
