# Python dependency direction — imports point down the stack — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-namespace-cycle

two of the project's packages import each other — a cycle that makes them one package wearing two names

```py
----------[ Bad ]----------

def results_page(query: str) -> str:
    from shop.search import engine
    return engine.render(query)

----------[ Good ]----------

from ..tracking.events import record
```

### python-namespace-dependency

an import out of a declared layer into a package that layer did not declare it may use

```py
----------[ Bad ]----------

from ..widgets.card import Card

----------[ Good ]----------

from ..layout.grid import cell
```
