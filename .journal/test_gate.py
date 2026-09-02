#!/usr/bin/env python3
"""What the write gate must and must not stop.

    .journal/test_gate.py

THIS SUITE EXISTS BECAUSE THE GATE WAS WRONG THREE TIMES IN ONE DAY, and every one of the
three stopped a READ:

  `cat …; echo "=== useDispatch ==="; cat …`   `useDis` + `patch ` matched as a substring
  `python3 - <<'PY' … if n >= 6 … PY`          a heredoc body read as shell
  `./test.py 2>&1 | grep FAIL`                 a file-descriptor dup read as a redirect

Each fired during discovery — the one moment nobody can yet name the work, because the
reading is what tells them what the work is. A gate that interrupts reading teaches that it
is an obstacle to route around, and then the write it was built to catch is routed around
too. So the cases below are kept as regressions, and new ones go here before the fix does.

The bias is deliberate and stated: MISSING a write is cheaper than blocking a read. A false
deny stops real work and gets the gate switched off within the hour; a miss costs one
unfiled edit.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import hook  # noqa: E402

READS = [
    # the three regressions, first
    'cat resources/js/view/triggers.ts; echo "=== useDispatch ==="; cat resources/js/view/useDispatch.ts',
    "python3 - <<'PY'\nif shown >= 6: print(1)\nPY",
    './.journal/test_tracks.py 2>&1 | grep -A3 FAIL',
    # ordinary reading
    'grep -rn "confirm" src | head -20',
    'ls -la; wc -l *.py',
    'git status --short && git log --oneline -3',
    'git diff --stat; git show HEAD:file.py',
    'python3 -c "print(1)" 2>/dev/null',
    'echo "a > b"',
    "echo 'committee formed'",
    'sed -n "1,40p" file.py',
    'find . -name "*.php" | head',
    'cmd >&2',
    'diff <(cat a) <(cat b)',
    './journal.py start "x"',
    '.journal/journal.py remember "y"',
    'journal todo "park this" --brief <<\'EOF\'\nwhy and where\nEOF',
    'journal todo "park this" && journal open && git status',
]

WRITES = [
    'cat > foo.py <<EOF\nx\nEOF',
    'echo hi > notes.txt',
    'echo hi >> notes.txt',
    'sed -i "" s/a/b/ f.py',
    'rm -rf build',
    'mv a b',
    'cd /tmp && cp a b',
    'mkdir -p a/b',
    'touch new.txt',
    'chmod +x script.sh',
    'git commit -m "x"',
    'git checkout main',
    'git apply patch.diff',
    'FOO=1 tee out.txt',
    'sudo rm /etc/thing',
    'tee -a log < input',
    'ln -s a b',
    'patch -p1 < fix.diff',
    # A JOURNAL COMMAND EXEMPTS ITSELF, NOT THE LINE. These were waved through entirely.
    'journal todo "x" && rm -rf build',
    '.journal/journal.py start "w" && git commit -m "x"',
    'git add -A && journal end "w"',
]

ok = fail = 0
for want, group, label in ((False, READS, "read "), (True, WRITES, "write")):
    for cmd in group:
        got = hook._is_write({"tool_name": "Bash", "tool_input": {"command": cmd}})
        if got == want:
            ok += 1
        else:
            fail += 1
            print(f"  FAIL expected {label}: {cmd.splitlines()[0][:70]}")

for name in ("Write", "Edit", "MultiEdit", "NotebookEdit"):
    got = hook._is_write({"tool_name": name, "tool_input": {}})
    ok, fail = (ok + 1, fail) if got else (ok, fail + 1)
    if not got:
        print(f"  FAIL the {name} tool must always be a write")

for name in ("Read", "Grep", "Glob", "WebFetch"):
    got = hook._is_write({"tool_name": name, "tool_input": {}})
    ok, fail = (ok + 1, fail) if not got else (ok, fail + 1)
    if got:
        print(f"  FAIL the {name} tool must never be a write")

# THE RUNG GATE: journal-only lines pass, a line that decides first passes, the rest do not
for cmd, want in (
    ('journal search thing', True),
    ('.journal/journal.py --back=1 | head -40', True),
    ('journal remember "a claim" && git commit -m x', True),
    ('journal nothing "only reads happened" ; ls', True),
    ('journal rule "r" && journal todo "t" && make', True),
    ('ls && journal nothing "late"', False),
    ('journal todo "t" && make', False),
    ('cat file', False),
):
    got = hook._is_journal({"tool_name": "Bash", "tool_input": {"command": cmd}})
    if got == want:
        ok += 1
    else:
        fail += 1
        print(f"  FAIL rung gate expected {'pass' if want else 'deny'}: {cmd[:60]}")

# THE PIN CAP IS A DENIAL, NOT AN EXIT CODE. The command's own refusal is a stderr line
# after the fact; the gate says it before the command runs, off the same words.
LONG = "x" * 400
for cmd, want in (
    (f'journal remember "{LONG}"', True),
    (f'.journal/journal.py remember "{LONG}" --supersedes=3', True),
    (f'cd proj && ./.journal/journal.py remember "{LONG}" && journal pins', True),
    ('journal remember "a claim that fits"', False),
    (f'journal search remember; echo "{LONG}"', False),   # `remember` is the search term
    (f'echo "remember {LONG}"', False),                    # not the journal at all
    ('journal remember "unterminated', False),             # unparseable: left to the CLI
    # THE PATCH THAT GOT DENIED: a heredoc body mentioning the command is data, not a call
    ("python3 - <<'PY'\ns = '.journal/journal.py remember \"" + LONG + "\"'\nPY", False),
    (f'journal remember "{LONG}" <<EOF\nbody\nEOF', True),  # the opener's line still counts
    ('journal remember "' + "x" * 298 + '" 2>&1 | tail -1', False),  # the redirect is not claim
    ('journal remember "the report is at scratchpad/report.md"', True),  # cites a session path
    ('journal remember "see /tmp/out.txt for the numbers"', True),
    ('journal rule "never cite the scratchpad in a pin"', True),  # the word alone is enough: refuse
):
    got = hook._pin_overflow({"tool_name": "Bash", "tool_input": {"command": cmd}}, 300)
    if bool(got) == want:
        ok += 1
    else:
        fail += 1
        print(f"  FAIL pin gate expected {'deny' if want else 'pass'}: {cmd[:60]}")
got = hook._pin_overflow({"tool_name": "Bash", "tool_input": {"command": f'journal remember "{LONG}"'}}, 0)
ok, fail = (ok + 1, fail) if got is None else (ok, fail + 1)
if got is not None:
    print("  FAIL a cap of 0 must not gate on length")

print(f"\n{ok} passed, {fail} failed")
sys.exit(1 if fail else 0)
