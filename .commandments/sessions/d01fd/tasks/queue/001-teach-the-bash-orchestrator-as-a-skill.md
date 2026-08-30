# teach the bash orchestrator as a skill

Once /Users/jessegall/projects/orchestrator is built and proven: write a skill for it USING the skill-creator skill, with a references/ folder beside SKILL.md carrying the config format, the condition language, the hook protocol and the action list. The skill teaches WHEN to reach for each command; the references hold the detail a reader looks up. Blocked until the build lands — a skill written ahead of the implementation documents a design rather than a tool.

- queued 2026-08-30 16:28

**Ruling (Jesse):** the skill ships INSIDE the orchestrator repo and is injected into that session's own skills — `skills/orchestrator/SKILL.md` plus `references/`, copied by `setup` like every other file. No dependency on code-commandments, and no new publishing mechanism: a skill folder is just more files in the slate.
