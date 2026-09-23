# Python duplication — one behaviour, one home — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### duplicate-python-function

Copy-pasted code — two+ Python functions or methods with an identical body, formatting, comments and docstrings aside

```py
----------[ Bad ]----------

# in events.py
def on_event(self, event) -> None:
    if event.kind == "order" and event.action in ("created", "updated"):
        self.handle(event)
        event.acknowledge(self.__class__.__name__)

# in events.py
def on_event(self, event) -> None:
    if event.kind == "order" and event.action in ("created", "updated"):
        self.handle(event)
        event.acknowledge(self.__class__.__name__)

# in documents.py
def rows(self, lines: list[Line]) -> list[str]:
    rows = []
    for line in lines:
        if line.quantity <= 0:
            continue
        rows.append(f"{line.quantity} x {line.sku}: {line.total() / 100:.2f}")
    return rows

# in documents.py
def line_items(self, lines: list[Line]) -> list[str]:
    rows = []
    for line in lines:
        if line.quantity <= 0:
            continue
        rows.append(f"{line.quantity} x {line.sku}: {line.total() / 100:.2f}")
    return rows

# in cli.py
def load_config(path: Path) -> dict:
    if not path.is_file():
        raise SettingsMissing(str(path))
    settings = json.loads(path.read_text())
    return {key.lower(): value for key, value in settings.items()}

# in settings.py
def read_settings(path: Path) -> dict:
    if not path.is_file():
        raise SettingsMissing(str(path))
    settings = json.loads(path.read_text())
    return {key.lower(): value for key, value in settings.items()}

----------[ Good ]----------

# in layout.py
def describe_lines(lines: list[Line]) -> list[str]:
    return [f"{line.quantity} x {line.sku}: {line.total() / 100:.2f}" for line in lines if line.quantity > 0]

# in layout.py
def rows(self, lines: list[Line]) -> list[str]:
    return describe_lines(lines)

# in layout.py
def line_items(self, lines: list[Line]) -> list[str]:
    return describe_lines(lines)
```

### near-duplicate-python-function

A near-copy — two+ Python functions or methods with one control-flow skeleton that differ only in their local names or the literals they use (a path, a key, a message)

```py
----------[ Bad ]----------

# in alerts.py
def register(bus) -> None:
    bus.intercept(WatchKeyword("alerts", whole_words=True))
    bus.handle(RepeatStanding("alerts", every=25))
    bus.handle(MentionInChat("alerts", limit=3))
    bus.log.info("registered %s", "alerts")

# in holds.py
def hold_order(warehouse, number: str) -> dict:
    found = warehouse.locate(number)
    queued = warehouse.queue(number, "Put on hold", site=found["site"], action=HOLD)
    return {key: value for key, value in queued.items() if key != "line"} | {"queued": True}

# in holds.py
def release_order(warehouse, number: str) -> dict:
    found = warehouse.locate(number)
    queued = warehouse.queue(number, "Release to picking", site=found["site"], action=RELEASE)
    return {key: value for key, value in queued.items() if key != "line"} | {"queued": True}

# in encoders.py
def encode_text(text: str) -> tuple[dict[str, str], ByteStream]:
    body = text.encode("utf-8")
    length = str(len(body))
    kind = "text/plain; charset=utf-8"
    headers = {"Content-Length": length, "Content-Type": kind}
    return headers, ByteStream(body)

# in encoders.py
def encode_html(html: str) -> tuple[dict[str, str], ByteStream]:
    body = html.encode("utf-8")
    length = str(len(body))
    kind = "text/html; charset=utf-8"
    headers = {"Content-Length": length, "Content-Type": kind}
    return headers, ByteStream(body)

# in digests.py
def register(bus) -> None:
    bus.intercept(WatchKeyword("digests", whole_words=True))
    bus.handle(RepeatStanding("digests", every=50))
    bus.handle(MentionInChat("digests", limit=1))
    bus.log.info("registered %s", "digests")

----------[ Good ]----------

# in encoding.py
def encode(text: str, kind: str) -> tuple[dict[str, str], ByteStream]:
    body = text.encode("utf-8")
    headers = {"Content-Length": str(len(body)), "Content-Type": f"{kind}; charset=utf-8"}
    return headers, ByteStream(body)

# in encoding.py
def encode_text(text: str) -> tuple[dict[str, str], ByteStream]:
    return encode(text, "text/plain")

# in encoding.py
def encode_html(html: str) -> tuple[dict[str, str], ByteStream]:
    return encode(html, "text/html")
```
