# The capped dev image every agent Go build, test, vet and run goes through (scripts/dev).
# It carries what the Go tests shell out to: PHP and composer for the PHP tool, node for the
# frontend bridge, python3 with the pinned mypy for the Python bridge, git, and the docker CLI
# that starts the Roslyn bridge beside it through the host's socket.
FROM php:8.5-cli-bookworm

ARG MYPY

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/* \
 && curl -LsSf https://astral.sh/uv/install.sh | env UV_INSTALL_DIR=/usr/local/bin UV_NO_MODIFY_PATH=1 sh \
 && UV_PYTHON_INSTALL_DIR=/opt/python uv python install 3.14 \
 && ln -s "$(UV_PYTHON_INSTALL_DIR=/opt/python uv python find 3.14)" /usr/local/bin/python3 \
 && python3 -m venv /opt/mypy \
 && uv pip install --no-cache --python /opt/mypy/bin/python "${MYPY}" \
 && git config --system --add safe.directory '*'

COPY --from=golang:1.26-bookworm /usr/local/go /usr/local/go
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=docker:29-cli /usr/local/bin/docker /usr/local/bin/docker

ENV PATH=/usr/local/go/bin:/root/go/bin:$PATH \
    GOPATH=/root/go \
    GOTOOLCHAIN=local \
    COMMANDMENTS_MYPY_PYTHON=/opt/mypy/bin/python
