# Python dependency direction — imports point down the stack — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-namespace-cycle

two of the project's packages import each other — a cycle that makes them one package split under two names.

```py
----------[ Bad ]----------

# in shop/couriers/dispatch.py
# A courier dispatch that imports the tracking events module outright, while tracking imports the couriers.
# Beside it, a one-way import into a package that never imports back.
import shop.tracking.events
from ..pricing.rates import rate_for

def dispatch(parcel: str) -> str:
    shop.tracking.events.record(parcel)
    return f"{parcel} at {rate_for(parcel)}"

# in shop/tracking/events.py
# Tracking events, which know every courier and the dispatch step they came from.
from shop.couriers import dispatch
from shop.couriers.dispatch import dispatch as sent

def record(parcel: str) -> list[str]:
    return [parcel, dispatch.__name__, sent.__name__]

----------[ Good ]----------

# in shop/returns/desk.py
# The FIX for a cycle between returns and tracking: the returns desk imports tracking one way, and everything
# tracking needs about a return is declared in tracking.
from ..tracking.events import record

def book_return(parcel: str) -> list[str]:
    return record(f"return {parcel}")
```

### python-namespace-dependency

an import out of a declared layer into a package that layer did not declare it may use

```py
----------[ Bad ]----------

# in shop/layout/grid.py
# The layout's grid reaching up into the widgets for a card: the lowest layer depending on one above it.
from ..widgets.card import Card

def cell(title: str) -> str:
    return f"<td>{Card(title)}</td>"

----------[ Good ]----------

# in shop/layout/row.py
# The layout's row takes what it shows as an argument, so the bottom layer imports nothing above it: the
# widget layer renders the card and hands it down.
from html import escape

def row(content: str) -> str:
    return f"<tr>{escape(content)}</tr>"
```
