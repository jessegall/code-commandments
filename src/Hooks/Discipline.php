<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

/**
 * A hook that speaks to ANYBODY writing code, a dispatched worker included. The disciplines are about
 * the code in front of you — the cardinal rule, the skill for the subject, the gate that makes you
 * declare your work — and a worker has LESS context than the orchestrator that sent it, so they are
 * worth more there rather than less.
 *
 * A hook without this marker reaches the orchestrator alone, which is right for anything about running
 * a build: a worker holds no board, dispatches nobody, and cannot act on being told to.
 *
 * Both mistakes are silent, which is why it is stated on the class line rather than buried in a method:
 * a discipline that stops short leaves every worker unguided, and an orchestration hook that carries too
 * far holds a worker at its stop for work it is not doing.
 */
interface Discipline {}
