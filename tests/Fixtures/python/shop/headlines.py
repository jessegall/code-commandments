# A headline takes an optional strapline defaulted to the blank, then asks the blank whether it is
# there. Below it, the FIX: the absence said in the type and asked as `None`.


# @sin BlankStringDefault
def headline(heading: str, strapline: str = "") -> str:
    if strapline == "":
        return heading
    return f"{heading} — {strapline}"


# @fixed BlankStringDefault
def headline_for(heading: str, strapline: str | None = None) -> str:
    if strapline is None:
        return heading
    return f"{heading} — {strapline}"
