# nothing tells you how to start the ORCHESTRATOR

The tool prints a paste-ready command for a WORKER (agent <name> gives 'claude --directory <lane> ...'), but there is no equivalent for the orchestrator itself. Today the answer is 'cd into the session and start it there', which is correct and written down nowhere. SPEC 1a/6 also want it isolated — its own hooks, none of the project's — and that is specced but unwired. Wants: setup ends by printing the command that starts the orchestrator in its own world, the way agent does for a worker.

- queued 2026-08-30 20:40
