#!/usr/bin/env python3
"""install — wire the journal into a project that has just downloaded it.

    .journal/install.py           wire the hooks, make things executable
    .journal/install.py --alias   also add a `journal` alias to your shell rc
    .journal/install.py --check   say what would change, write nothing
    .journal/install.py --from <path-to-.journal>   pull that checkout's package in first

WHAT THIS IS CAREFUL ABOUT, and why each one is a real way to lose somebody's work:

MERGE, NEVER OVERWRITE. `.claude/settings.json` is the user's file and this is a guest in
it. There may be other hooks in there, on these very events, that matter more than this
one. So the file is read, the journal's entries are added to whatever is already there, and
everything else is passed through untouched. A tool that writes its own config over yours
is a tool you cannot adopt incrementally.

IDEMPOTENT. Running it twice must not wire the hook twice — a duplicated Stop hook fires
twice per stop, holds twice, and reads like the check is broken rather than like the
install is. So an entry already pointing at `hook.py` counts as done.

IT REFUSES TO GUESS ABOUT MALFORMED JSON. If `settings.json` does not parse, this stops and
says so rather than starting from `{}`. Starting fresh would silently delete every hook the
user had, and the failure would look like an install that worked.

--from PULLS THE PACKAGE, NEVER THE DATA. The code, the tests and the skill come across;
the record, the settings and the runtime files are this project's and stay. And the
tests run on the pulled copy BEFORE it lands: a package that fails its own suites is
refused in a staging directory, so a consumer never holds a broken journal for even one
hook event. Until this existed every update to a consumer was an rsync by hand, and
"the consumer has the latest" was a belief.

AND IT DOES NOT CLAIM SUCCESS. The last thing it does is run `verify`, which reports WIRED
and FIRED as two separate facts. Installing can only ever prove the first one. The tool this
replaces sat wired and silent for seventeen hours, so `install` finishing is deliberately
not the same as the journal being in force.
"""
from __future__ import annotations

import filecmp
import json
import os
import shutil
import stat
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent
PROJECT = ROOT.parent
EVENTS = ("Stop", "SessionStart", "PostToolUse", "PreToolUse")
#: What goes in settings.json. `$CLAUDE_PROJECT_DIR` is quoted because a path with a space
#: in it otherwise splits into two arguments and the hook simply never runs.
COMMAND = '"$CLAUDE_PROJECT_DIR"/.journal/hook.py'
EXECUTABLE = ("hook.py", "journal.py", "install.py", "test_tracks.py", "test_gate.py",
              "test_state.py")

#: THE SKILL IS PART OF THE PACKAGE, and it has to be installed rather than committed.
#: It teaches the reasoning the injected block has no room for, so it belongs beside the
#: code that enforces those rules — but it has to LAND in `.claude/skills/`, which the
#: harness owns and which several projects gitignore. A skill that only exists where it was
#: first written is one that silently goes missing on the next clone, and nothing about a
#: missing skill looks broken: the agent simply never learns why any of this is here.
SKILL_SRC = "skill/SKILL.md"
SKILL_DST = ".claude/skills/journal/SKILL.md"

#: What belongs to THIS project and never comes across on a pull.
DATA = ("record.json", "record.json.lock", "settings.json", "state.json", "state.json.retired",
        "runtime", "todo", "__pycache__")


def _package_files(root: Path) -> list[Path]:
    """Every file of the package under `root`, relative — code, tests, skill, gitignore."""
    out = []
    for f in root.rglob("*"):
        rel = f.relative_to(root)
        if not f.is_file() or rel.parts[0] in DATA or f.suffix in (".tmp", ".pyc"):
            continue
        out.append(rel)
    return sorted(out)


def pull(src: Path, check: bool) -> list[str]:
    """Bring another checkout's package here. Tests first, in staging; then the files."""
    src = src.resolve()
    if src.name != ".journal" and (src / ".journal").is_dir():
        src = src / ".journal"
    if not (src / "hook.py").is_file() or not (src / "journal.py").is_file():
        raise SystemExit(f"  ! {src} is not a journal package (no hook.py / journal.py)")
    if src == ROOT:
        raise SystemExit("  ! --from names this very checkout; nothing to pull")

    stage = Path(tempfile.mkdtemp()) / ".journal"
    shutil.copytree(src, stage, ignore=shutil.ignore_patterns(*DATA, "*.tmp", "*.pyc"))
    out = [f"  · pulling from {src}"]
    for t in sorted(stage.glob("test_*.py")):
        p = subprocess.run([sys.executable, str(t)], capture_output=True, text=True)
        last = (p.stdout.strip().splitlines() or ["(no output)"])[-1]
        out.append(f"  {'=' if p.returncode == 0 else '!'} {t.name}: {last}")
        if p.returncode != 0:
            raise SystemExit("\n".join(out) + "\n  ! the pulled package fails its own tests "
                             "— nothing was copied. Fix it at the source first.")

    theirs = _package_files(stage)
    mine = set(_package_files(ROOT))
    changed = [rel for rel in theirs
               if not (ROOT / rel).is_file() or not filecmp.cmp(stage / rel, ROOT / rel, shallow=False)]
    gone = sorted(mine - set(theirs))
    for rel in changed:
        if not check:
            (ROOT / rel).parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(stage / rel, ROOT / rel)
        out.append(f"  + {rel}" + (" (would update)" if check else ""))
    for rel in gone:
        # PACKAGE OUTPUT ONLY. Anything under DATA never reaches this list, so what is
        # removed is code or skill the source no longer ships — and it is said, by name.
        if not check:
            (ROOT / rel).unlink()
        out.append(f"  - {rel} (no longer in the package)")
    if not changed and not gone:
        out.append("  = already at the source's version")
    shutil.rmtree(stage.parent, ignore_errors=True)
    return out


ALIAS_MARK = "# journal — added by .journal/install.py"
#: `git rev-parse` rather than a fixed path, so the alias still resolves from a
#: subdirectory of the repo. A relative path would break the moment you `cd src`.
ALIAS = """%s
alias journal='"$(git rev-parse --show-toplevel)"/.journal/journal.py'""" % ALIAS_MARK


def _settings_path() -> Path:
    return PROJECT / ".claude" / "settings.json"


def wire(check: bool) -> list[str]:
    """Add the journal's hooks to `.claude/settings.json`, keeping everything else."""
    f = _settings_path()
    data: dict = {}
    if f.is_file():
        try:
            data = json.loads(f.read_text() or "{}")
        except ValueError as e:
            # STOP. See the module docstring: an unreadable config is not an empty one,
            # and treating it as one deletes hooks the user is relying on.
            raise SystemExit(
                f"  ! {f} does not parse as JSON: {e}\n"
                "    Fix it by hand — starting from scratch here would delete whatever "
                "else you have wired."
            )
    if not isinstance(data.get("hooks"), dict):
        data["hooks"] = {} if "hooks" not in data else data["hooks"]
    if not isinstance(data["hooks"], dict):
        raise SystemExit(f"  ! {f}: `hooks` is not an object, refusing to touch it")

    done: list[str] = []
    for ev in EVENTS:
        blocks = data["hooks"].setdefault(ev, [])
        if not isinstance(blocks, list):
            raise SystemExit(f"  ! {f}: hooks.{ev} is not a list, refusing to touch it")
        already = any(
            "hook.py" in str(h.get("command", ""))
            for b in blocks
            if isinstance(b, dict)
            for h in (b.get("hooks") or [])
            if isinstance(h, dict)
        )
        if already:
            done.append(f"  = {ev} already wired")
            continue
        blocks.append({"hooks": [{"type": "command", "command": COMMAND}]})
        done.append(f"  + {ev} wired")

    if not check and any(d.startswith("  +") for d in done):
        f.parent.mkdir(parents=True, exist_ok=True)
        f.write_text(json.dumps(data, indent=2) + "\n")
    return done


def executable(check: bool) -> list[str]:
    """A hook that is not executable fails silently — the harness just gets nothing."""
    out = []
    for name in EXECUTABLE:
        p = ROOT / name
        if not p.is_file():
            out.append(f"  ! {name} is missing")
            continue
        if os.access(p, os.X_OK):
            out.append(f"  = {name} already executable")
            continue
        if not check:
            p.chmod(p.stat().st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)
        out.append(f"  + {name} made executable")
    return out


# `baseline()` LIVED HERE. It drew a line under pre-journal history by writing a line
# number into project-wide state — for a transcript it guessed by mtime, to protect a
# session it assumed would fire SessionStart before its next Stop. Hooks are picked up
# live, so that session fires a Stop first, and with two terminals open the guess was the
# other one. The hook now writes a `floor` into the transcript's own runtime file the first
# time any event sees it, which covers install, resume, fork and clear alike. Install
# writes no runtime state at all, so it can no longer forge the evidence `verify` reads.


def skill(check: bool) -> list[str]:
    """Copy the packaged skill into place, and keep it current on every re-run.

    IT OVERWRITES, DELIBERATELY, and only when the content differs. The file is package
    output, not a place to keep notes: an edited copy would drift away from the rules the
    hooks actually enforce, and a skill that describes a tool inaccurately is worse than no
    skill, because it is believed. Anything worth changing belongs in `skill/SKILL.md`,
    where the next install will carry it everywhere.
    """
    src = ROOT / SKILL_SRC
    if not src.is_file():
        return [f"  ! {SKILL_SRC} is missing — no skill to install"]
    dst = PROJECT / SKILL_DST
    want = src.read_text()
    if dst.is_file() and dst.read_text() == want:
        return ["  = skill already current"]
    verb = "updated" if dst.is_file() else "installed"
    if not check:
        dst.parent.mkdir(parents=True, exist_ok=True)
        dst.write_text(want)
    return [f"  + skill {verb} at {SKILL_DST}"]


def _rc() -> Path | None:
    """The rc file for the shell actually in use, not a guess about which one that is."""
    shell = Path(os.environ.get("SHELL", "")).name
    for name in ({"zsh": ".zshrc", "bash": ".bashrc"}.get(shell), ".zshrc", ".bashrc"):
        if name and (Path.home() / name).is_file():
            return Path.home() / name
    return None


def alias(check: bool) -> list[str]:
    rc = _rc()
    if rc is None:
        return ["  ! found no ~/.zshrc or ~/.bashrc — add the alias by hand:\n" + ALIAS]
    body = rc.read_text()
    if ALIAS_MARK in body:
        return [f"  = alias already in {rc.name}"]
    if not check:
        with rc.open("a") as fh:
            fh.write(("" if body.endswith("\n") else "\n") + "\n" + ALIAS + "\n")
    return [f"  + alias added to {rc.name} — `source ~/{rc.name}` or open a new shell"]


def main(argv: list[str]) -> int:
    if any(a in ("-h", "--help", "help") for a in argv):
        print(__doc__)
        return 0
    check = "--check" in argv
    print(f"# installing the journal into {PROJECT}"
          + ("  (--check: nothing will be written)" if check else "") + "\n")
    src = None
    for i, a in enumerate(argv):
        if a == "--from" and i + 1 < len(argv):
            src = Path(argv[i + 1])
        elif a.startswith("--from="):
            src = Path(a.split("=", 1)[1])
    if src is not None:
        for line in pull(src, check):
            print(line)
    for line in executable(check) + wire(check) + skill(check):
        print(line)
    if "--alias" in argv:
        for line in alias(check):
            print(line)
    else:
        print("  · no shell alias (pass --alias to add one)")

    # WIRED IS NOT FIRED, so the install refuses to be the thing that says it worked.
    print("\n" + "-" * 60)
    sys.path.insert(0, str(ROOT))
    import verify
    body, ok = verify.render(ROOT)
    print(body)
    if ok:
        print("\n  Wired. It is not proven live until a hook has actually run — stop once\n"
              "  with an untagged message and see whether it holds you.")
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
