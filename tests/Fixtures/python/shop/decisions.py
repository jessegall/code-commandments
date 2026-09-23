# Look-alikes that are ordinary decisions, not dispatch: three rungs are a choice, not a table; a
# chain whose rungs ask different questions has no single subject to dispatch on.


# @righteous SubjectLadder
def size_band(parcel) -> str:
    if parcel.weight == 0:
        return "letter"
    elif parcel.weight == 1:
        return "small"
    elif parcel.weight == 2:
        return "medium"
    return "large"


# @righteous SubjectLadder
def route(order) -> str:
    if order.country == "NL":
        return "domestic"
    elif order.region == "EU":
        return "union"
    elif order.express == True:
        return "air"
    elif order.country == "CH":
        return "customs"
    return "sea"
