# Python exceptions — fail loud, named, at the source — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-message-string-raise

`raise Exception/RuntimeError("…")` — a failure that names nothing, described in prose at the raise site

```py
----------[ Bad ]----------

def basket(context):
    if context.session is None:
        raise RuntimeError("There is no active session in this context.")
    return context.session.basket

----------[ Good ]----------

# in sessions.py
class NoActiveSession(LookupError):
    @classmethod
    def reading(cls, what: str) -> "NoActiveSession":
        return cls(f"there is no active session to read the {what} from")

# in sessions.py
def current_basket(context):
    if context.session is None:
        raise NoActiveSession.reading("basket")
    return context.session.basket
```

### python-swallowed-exception

A bare `except:` or `except Exception` whose body only passes, continues or returns nothing — every failure, expected or not, made to vanish

```py
----------[ Bad ]----------

def load_catalog(path: Path) -> dict:
    try:
        return json.loads(path.read_text())
    except Exception:
        return {}

----------[ Good ]----------

# in catalog_file.py
class CatalogUnreadable(Exception):
    @classmethod
    def at(cls, path: Path) -> "CatalogUnreadable":
        return cls(f"the catalog at {path} is not valid JSON")

# in catalog_file.py
def read_catalog(path: Path) -> dict:
    try:
        return json.loads(path.read_text())
    except json.JSONDecodeError as error:
        raise CatalogUnreadable.at(path) from error
```
