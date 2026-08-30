# status prints raw state instead of what needs the orchestrator first

FOUND BY DOGFOODING and independently by storybook 2 (story 15). On a fresh session status prints tool_use=0, stops_held=0 and 'ready to fire' twice. That is the machine's state, not an answer to 'what needs me?' — which SPEC's master test says is the only thing worth an orchestrator's attention. Related to 015 (nothing prunes state/.agents).

- queued 2026-08-30 17:59
- done 2026-08-30 18:04 — status prints WAITING ON YOU then RUNNING, and one sentence when neither has anything; added report so the waiting moment is recorded at all; fixed merge running git in the session folder; the brief no longer makes a fresh lane read dirty
