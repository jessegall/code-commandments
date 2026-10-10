# A user's machine as the journal plugin must be able to install on: python3, git and ps for the journal, curl for the
# plugin's fetch, and nothing else — no Go, no PHP, no composer, no Docker, no .NET (scripts/plugin/prove).
FROM python:3.12-slim-bookworm

# The proof script prunes the image this one replaces by this label.
LABEL code-commandments.proof=true

RUN apt-get update \
 && apt-get install -y --no-install-recommends git curl ca-certificates procps \
 && rm -rf /var/lib/apt/lists/*

COPY inside /usr/local/bin/plugin-proof
