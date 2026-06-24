<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Profiles;

/**
 * GENERATES a profile's `stop-hook.sh` from its {@see ProfileBehaviour} — the
 * package no longer ships per-profile Stop scripts; it builds them. So the hook
 * can never drift from the declared cadence:
 *  - AtEnd  (grind)            → plan-driven, NEVER judges (reckon = pre-push gate);
 *  - EachPhase (phased/sins)   → keep-going gate over the current changes (judge --git);
 *  - UntilClean (penance)      → keep-going gate over the whole codebase (judge);
 * and the message tells the agent whether judge/test are deferred to the end or
 * run each phase, straight from `behaviour`.
 *
 * Output is framework-agnostic `sh` (keyed off `git rev-parse --show-toplevel`).
 */
final class StopHookBuilder
{
    private const CAP = 200;

    /**
     * The generated Stop script for $profile, or null when the profile has no
     * Stop hook (disabled / judge: Never).
     */
    public static function build(string $profile, ProfileOptions $opts): ?string
    {
        $b = $opts->behaviour;

        if (! $b->hasStopHook()) {
            return null;
        }

        return $b->stopHookJudges()
            ? self::judgingHook($profile, $opts)
            : self::planOnlyHook($profile, $b);
    }

    /**
     * AtEnd profiles (grind): drive the plan to completion, NEVER judge — the
     * reckon is the pre-push gate. With no active plan there is nothing to keep
     * going for, so the Stop is allowed.
     */
    private static function planOnlyHook(string $profile, ProfileBehaviour $b): string
    {
        $cap = self::CAP;
        $defer = self::deferredActivities($b);
        $ask = self::shEscape($b->askGuidance());
        $msg = sprintf(
            'The approved plan is not finished (%s, auto-continue $count/%d) — implement the next phase now and commit it. Do NOT run %s between phases: %s defers the reckon to the single pre-push gate. A long turn or context compaction is not a blocker. %s',
            $profile,
            $cap,
            $defer,
            $profile,
            $ask,
        );
        $fallback = sprintf(
            'The approved plan is not finished (%s %%s/%%s). Implement + commit the next phase; do NOT run %s between phases (the pre-push gate is the only reckon). Release only for a genuine blocker via sh .claude/hooks/plan-release.sh.',
            $profile,
            $defer,
        );

        return self::header($profile, $b)
            . <<<SH
            root=\$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "\${CLAUDE_PROJECT_DIR:-.}")
            marker="\$root/.claude/plan-active"

            # No active plan for this worktree -> allow the stop (and never judge).
            [ -f "\$marker" ] || exit 0

            cap={$cap}
            count=\$(cat "\$marker" 2>/dev/null)
            case "\$count" in ''|*[!0-9]*) count=0 ;; esac

            if [ "\$count" -ge "\$cap" ]; then
                rm -f "\$marker"
                printf '{"hookSpecificOutput":{"hookEventName":"Stop","additionalContext":"Plan auto-continue guard reached %s continuations — yielding. If the plan is not finished, run sh .claude/hooks/plan-start.sh to resume."}}' "\$cap"
                exit 0
            fi

            count=\$((count + 1))
            printf '%s' "\$count" > "\$marker"

            msg="{$msg}"
            if command -v jq >/dev/null 2>&1; then
                jq -nc --arg r "\$msg" '{decision:"block",reason:\$r}'
            else
                printf '{"decision":"block","reason":"{$fallback}"}' "\$count" "\$cap"
            fi
            exit 0

            SH;
    }

    /**
     * EachPhase / UntilClean profiles: drive an approved plan to completion, then
     * keep going until the judge gate (over the profile's scope) is clean.
     */
    private static function judgingHook(string $profile, ProfileOptions $opts): string
    {
        $b = $opts->behaviour;
        $cap = self::CAP;
        $scopeFlag = $b->judge === Phase::EachPhase ? ' --git' : '';
        $noun = $opts->allowWarnings ? 'findings' : 'sins';
        $where = $b->judge === Phase::EachPhase ? 'your changes' : 'the codebase';
        $phaseGate = $b->test === Phase::EachPhase
            ? 'run the gate (${run}judge --git) and the test suite, resolve every finding, and commit'
            : 'run the gate (${run}judge --git), resolve every finding, and commit';

        $ask = self::shEscape($b->askGuidance());
        $planMsg = sprintf(
            'The approved plan is not finished (%s, auto-continue $count/%d) — implement the next phase now. After each phase %s. A long turn or context compaction is not a blocker. %s',
            $profile,
            $cap,
            $phaseGate,
            $ask,
        );
        $planFallback = sprintf(
            'Plan not finished (%s %%s/%%s). Implement the next phase; %s. Release only for a genuine blocker via sh .claude/hooks/plan-release.sh.',
            $profile,
            $phaseGate,
        );
        $gateMsg = sprintf(
            '%s remain in %s (%s) — keep going, do NOT stop. Walk them with ${run}pilgrimage then ${run}next: it shows ONE prophet at a time with its full scripture and EVERY location. READ EACH OUTPUT IN FULL — never head/tail/truncate it, or you miss locations. Fix or absolve each (a genuine false positive: absolve/report it); `next` re-checks before advancing and never loops back. This hook releases automatically once the gate is clean.',
            ucfirst($noun),
            $where,
            $profile,
        );

        return self::header($profile, $b)
            . <<<SH
            root=\$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "\${CLAUDE_PROJECT_DIR:-.}")
            if [ -f "\$root/artisan" ]; then run="php artisan commandments:"; else run="vendor/bin/commandments "; fi
            marker="\$root/.claude/plan-active"
            mkdir -p "\$root/.commandments" 2>/dev/null
            cap={$cap}

            # 1) Plan drive — while an approved plan is active, keep implementing.
            if [ -f "\$marker" ]; then
                count=\$(cat "\$marker" 2>/dev/null)
                case "\$count" in ''|*[!0-9]*) count=0 ;; esac

                if [ "\$count" -ge "\$cap" ]; then
                    rm -f "\$marker"
                    printf '{"hookSpecificOutput":{"hookEventName":"Stop","additionalContext":"Plan auto-continue guard reached %s continuations — yielding. If the plan is not finished, run sh .claude/hooks/plan-start.sh to resume."}}' "\$cap"
                    exit 0
                fi

                count=\$((count + 1))
                printf '%s' "\$count" > "\$marker"

                msg="{$planMsg}"
                if command -v jq >/dev/null 2>&1; then
                    jq -nc --arg r "\$msg" '{decision:"block",reason:\$r}'
                else
                    printf '{"decision":"block","reason":"{$planFallback}"}' "\$count" "\$cap"
                fi
                exit 0
            fi

            # 2) Profile gate — keep going until the judge scope is finding-free.
            ( cd "\$root" && eval "\${run}judge --next{$scopeFlag} --no-cache" ) >/dev/null 2>&1
            status=\$?

            countfile="\$root/.commandments/keep-going-count"

            if [ "\$status" -eq 0 ]; then
                rm -f "\$countfile"
                exit 0
            fi

            count=\$(cat "\$countfile" 2>/dev/null)
            case "\$count" in ''|*[!0-9]*) count=0 ;; esac

            if [ "\$count" -ge "\$cap" ]; then
                rm -f "\$countfile"
                printf '{"hookSpecificOutput":{"hookEventName":"Stop","additionalContext":"Keep-going cap (%s) reached for the {$profile} profile — yielding. Findings may still remain; run %sjudge to check."}}' "\$cap" "\$run"
                exit 0
            fi

            count=\$((count + 1))
            printf '%s' "\$count" > "\$countfile"

            reason=\$(printf '{$gateMsg}' "\$run")
            printf '{"decision":"block","reason":"%s"}' "\$reason"
            exit 0

            SH;
    }

    private static function header(string $profile, ProfileBehaviour $b): string
    {
        return sprintf(
            "#!/usr/bin/env sh\n"
            . "# Stop hook — %s profile. GENERATED from the profile's behaviour\n"
            . "# (judge: %s, test: %s) by StopHookBuilder — do NOT hand-edit; switch\n"
            . "# profiles or update the package to regenerate.\n"
            . "cat >/dev/null 2>&1   # drain the Stop payload\n\n",
            $profile,
            $b->judge->value,
            $b->test->value,
        );
    }

    /** "judge or tests" / "judge" — what an AtEnd profile defers off each phase. */
    private static function deferredActivities(ProfileBehaviour $b): string
    {
        return $b->test === Phase::AtEnd ? 'judge or tests' : 'judge';
    }

    /** Escape `"` for embedding inside a double-quoted `sh` string (`msg="…"`). */
    private static function shEscape(string $text): string
    {
        return str_replace('"', '\\"', $text);
    }
}
