<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

/**
 * A hook whose answer can stop the tool call it is asked about — a refusal. It answers before the call runs.
 * Every other hook only advises, and the journal's hooks service asks it afterwards, in the background, so
 * no advice ever holds up a tool call.
 */
interface Gate {}
