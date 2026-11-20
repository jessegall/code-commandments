# Python pass the object — demand what you use, not an id and its container — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-computed-boolean-argument

a method taking only bools that every caller computes from the same object — the decision re-derived at each call site

```py
----------[ Bad ]----------

def pick(self, fragile: bool, express: bool) -> str:
    match (fragile, express):
        case (True, True):
            return "white-glove"
        case (True, False):
            return "careful"
        case (False, True):
            return "rush"
        case _:
            return "standard"

----------[ Good ]----------

def pick_for(self, consignment: Consignment) -> str:
    return self.pick(consignment.contents in ("glass", "ceramic"), consignment.service == "next-day")
```

### python-derived-argument

a call that hands over an object and a projection of it — `persist(request, request.channel_id)` — or an object in three pieces, where the function could read them itself

```py
----------[ Bad ]----------

def finish(session: KioskSession) -> str:
    return receipt(session.total, session.items(), session.paid_by_card())

----------[ Good ]----------

def receipt_of(session: KioskSession) -> str:
    return f"{session.items()} items, {session.total} ({'card' if session.paid_by_card() else 'cash'})"
```

### python-param-resolved-from-param

a function that takes a container and a key and first resolves one against the other — `def rename(workflow, node_id)` doing `workflow.graph.node(node_id)` — when it only wanted what the key names

```py
----------[ Bad ]----------

def relabel(plan: FloorPlan, bay_code: str, label: str) -> None:
    bay = plan.bay(bay_code)
    bay.label = label.upper()

----------[ Good ]----------

def relabel_bay(bay: Bay, label: str) -> None:
    bay.label = label.upper()
```
