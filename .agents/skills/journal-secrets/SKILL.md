---
name: journal-secrets
description: A secret names a key or a login, says what it is for and how to use it, and lists the commands it may be given to. Its values live in a file in your home folder, outside the project, and never in the journal. It runs by itself; load it to read how it works.
keywords: secrets, secret
commands: secrets
---

# Secrets

A secret names a key or a login, says what it is for and how to use it, and lists the commands it may be given to. Its values live in a file in your home folder, outside the project, and never in the journal.

When a task needs a key or a login you do not have, ask for it with journal secret request "<name>" "<why>" [--kind "api key"|login|custom]: it waits on the Secrets page until the user fills it in. Never ask the user to paste a value into the chat, and never read the file that holds the values.

Use a secret with journal secret run <name> -- <command>: the command gets the hidden value on its standard input, a home folder of its own so it keeps no login behind, and its output comes back with every form of the value replaced by [secret <name>]. --stdin "<text>" sends that text instead, with {<field>} filled in, such as --stdin "Authorization: Bearer {key}" for curl -H @-; --env gives the fields as environment variables instead. A secret never goes to a shell, an interpreter or a build tool, only to the programs it lists, to none while it lists none, and to helpers and subagents only when the user shared it. journal secret all lists the secrets with their descriptions, instructions and fields; journal secret read <n> shows one. A value you made yourself, such as a generated password, is written to a file and moved into the secret with journal secret store <n> <field> <file>, which deletes the file.

The values live in one file per project under your home folder, owner-only, never in git, the attic, a worktree or a backup of the record; journal secret where prints its path. A deleted secret keeps its values for 30 days, then they are removed from the file.

For a site you use in a browser, never type a password into a page: ask with journal secret request "<site>" "<why>" --kind "browser login" --url <address>. The chat shows the user a Log in button; only they press it, a browser opens on the address, they log in and close it, and the card says they are logged in. The session is saved beside the values file, and the agent's browser tools start logged in from their next start: Claude Code's Playwright server in the project's .mcp.json (the one already there, or one the journal adds), any other Playwright tool Claude Code starts, such as a Playwright plugin, through the agent's environment, and Codex's Playwright server in the project's .codex/config.toml. Once the user lets you log in to a site on its own, or switched on Agents log in on their own, the same request logs in at once with no button.

These reach your subagents too: the guard Refuse secrets file.
