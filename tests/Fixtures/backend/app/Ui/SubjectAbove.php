<?php

namespace Shop\Ui;

use JesseGall\CodeCommandments\Sins\Backend\NonCountingFor;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Finds the nearest captioned panel above a widget. The sinful walk hides the whole climb in
 * the `for` header — the bound, the branch and the stop sentinel all in the step. The
 * righteous twin (`nearestWhile`) walks with a `while`, and `depth` shows a real counted
 * `for`, which is what the form is for.
 */
final class SubjectAbove
{
    #[Sinful(NonCountingFor::class)]
    public function nearest(object $widget): string
    {
        for ($one = $widget; $one !== null; $one = $one->stacked ? $one->above : null) {
            if ($one->caption !== '') {
                return $one->caption;
            }
        }

        return 'untitled';
    }

    #[Righteous(NonCountingFor::class)]
    public function nearestWhile(object $widget): string
    {
        $one = $widget;

        while ($one !== null) {
            if ($one->caption !== '') {
                return $one->caption;
            }

            $one = $one->stacked ? $one->above : null;
        }

        return 'untitled';
    }

    /**
     * @param  array<int, object>  $panels
     */
    public function deepest(array $panels): int
    {
        $deepest = 0;

        for ($i = 0; $i < count($panels); $i++) {
            $deepest = max($deepest, $panels[$i]->depth);
        }

        return $deepest;
    }
}
