"""Is any of this actually in force?

THE QUESTION THIS ANSWERS IS THE ONE YOU CANNOT ASK FROM THE INSIDE. A hook that is wired,
listed as registered, and never fires looks exactly like a hook everybody is obeying — and
in the tool this replaces that shape ran for seventeen hours, captured nothing, and was
found only when somebody went looking for an unrelated bug.

So this never asks "is it configured". It asks "has it RUN", and it reports the two
answers differently, because *armed and never invoked* and *never armed* have opposite
fixes and one silence.
"""
from __future__ import annotations

import json
import os
import re
from pathlib import Path

import settings as settings_mod
import state
import transcript

WANT_EVENTS = ("Stop", "SessionStart", "PostToolUse", "PreToolUse")
#: Keys ONLY EVER WRITTEN BY A HOOK THAT REALLY RAN, in a transcript's own runtime file.
#: Their presence is the evidence, so nothing else in this package may write one. Install
#: used to write a baseline into project-wide state and thereby forged the proof; it now
#: writes no runtime state at all, and the floor is drawn by the hook on first sight.
FIRED = ("held_at", "floor", "taught_vocabulary", "warned_at", "held_work",
         "session_started", "biggest_result")


def _ignored(project: Path, rel: str) -> bool | None:
    """Is `rel` gitignored? None when there is no git or no repository to ask."""
    import subprocess
    try:
        if subprocess.run(["git", "rev-parse", "--git-dir"], cwd=project,
                          capture_output=True).returncode != 0:
            return None
        return subprocess.run(["git", "check-ignore", "-q", rel], cwd=project,
                              capture_output=True).returncode == 0
    except OSError:
        return None


def check(root: Path) -> tuple[list[tuple[str, bool, str]], bool]:
    out: list[tuple[str, bool, str]] = []
    project = root.parent

    hook = root / "hook.py"
    out.append(("hook.py present", hook.is_file(), str(hook)))
    out.append((
        "hook.py executable",
        hook.is_file() and os.access(hook, os.X_OK),
        "chmod +x .journal/hook.py" if hook.is_file() and not os.access(hook, os.X_OK) else "",
    ))

    f = project / ".claude" / "settings.json"
    wired: set[str] = set()
    if not f.is_file():
        out.append(("wired in .claude/settings.json", False, f"{f} does not exist"))
    else:
        try:
            data = json.loads(f.read_text())
            for event, blocks in (data.get("hooks") or {}).items():
                for b in blocks or []:
                    for h in b.get("hooks") or []:
                        if "hook.py" in str(h.get("command", "")):
                            wired.add(event)
            for ev in WANT_EVENTS:
                out.append((f"wired: {ev}", ev in wired, "" if ev in wired else "not in settings.json"))
        except ValueError as e:
            out.append(("`.claude/settings.json` parses", False, str(e)))

    conf, problems = settings_mod.load(root)
    out.append((
        "settings.json clean",
        not problems,
        "; ".join(problems),
    ))

    path = transcript.newest_session(project)
    out.append((
        "transcript findable",
        path is not None,
        str(path) if path else f"nothing under {transcript.project_dir(project)}",
    ))

    # WIRED, FIRED, AND *ACCEPTED* ARE THREE FACTS. A hook may run, exit 0, write its
    # state — and have its payload thrown away by schema validation. That happened here:
    # PreCompact was emitting `additionalContext`, which the harness does not accept for
    # that event, and every green light above stayed green. So the events a handler talks
    # THROUGH are checked against the events the harness will listen ON.
    try:
        import hook as hook_mod
        emits = set(re.findall(r'_context\(\s*"([A-Za-z]+)"', (root / "hook.py").read_text()))
        deaf = sorted(emits - hook_mod.DELIVERS_CONTEXT)
        out.append((
            "every injected context targets an event that accepts it",
            not deaf,
            "" if not deaf else (
                f"{', '.join(deaf)} cannot carry additionalContext — the harness rejects "
                "the payload while the hook still exits 0"
            ),
        ))
    except Exception as e:  # never let the checker be the thing that breaks
        out.append(("context targets checkable", False, str(e)))

    # THE ONE THAT MATTERS. Configuration is a claim; a written key is a fact. And it is
    # TWO facts: a hook that fired in some transcript months ago says nothing about whether
    # it fires now, so the transcript this process belongs to is checked on its own.
    files = state.runtime_files(root)
    fired = [(stem, d) for stem, d in files if any(k in d for k in FIRED)]
    out.append((
        f"the hook has ACTUALLY FIRED — in {len(fired)} transcript(s) on this machine",
        bool(fired),
        "" if fired else (
            "wired but never invoked — no transcript carries a mark. Either nothing has "
            "happened since it was wired, or it is not reaching the harness. Stop once with "
            "an untagged message: if nothing happens, it is not live."
        ),
    ))
    sid = os.environ.get(transcript.SESSION_ENV, "")
    if sid:
        mine = dict(files).get(sid, {})
        here = any(k in mine for k in FIRED)
        out.append((
            f"the hook has fired in THIS session ({sid[:8]}…)",
            here,
            "" if here else "this session has no runtime file — nothing has reached the hook here",
        ))
    else:
        out.append(("this session's transcript is known", False,
                    f"{transcript.SESSION_ENV} is not set — run this from inside a session "
                    "to check the current one"))

    # A COMMITTED RUNTIME FOLDER FORGES THE EVIDENCE ABOVE on every clone. Checked, not
    # assumed; and only where there is a repository to ask.
    ign = _ignored(project, ".journal/runtime/x.json")
    if ign is not None:
        out.append(("runtime/ is gitignored", ign,
                    "" if ign else "add `runtime/` to .journal/.gitignore — a clone would "
                                   "report FIRED where nothing ran"))

    # A LADDER WITH NO WINDOW IS SILENT, and silence is the shape this file exists to
    # report. Not a failure — the setting is a disagreement with a default — but said.
    if not conf.get("context_window"):
        out.append(("context window known", False,
                    "context_window is unset, so the context ladder stays silent until "
                    "the session's peak rules out every window but one. "
                    '`"context_window": 1000000` in .journal/settings.json if you know it.'))

    return out, all(ok for _, ok, _ in out)


def render(root: Path) -> tuple[str, bool]:
    rows, ok = check(root)
    lines = ["# is the journal in force?\n"]
    for name, good, note in rows:
        lines.append(f"  {'✓' if good else '✗'} {name}" + (f"\n      {note}" if note else ""))
    lines.append(
        "\n  All green means it is WIRED and has RUN. Those are different facts and this "
        "reports both,\n  because a mechanism that is configured and silent is the one "
        "failure nobody can point at."
    )
    return "\n".join(lines), ok
