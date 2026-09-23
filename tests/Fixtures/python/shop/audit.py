# An audit line written only when verbose — the other side of the conditional an empty `None` that
# exists only to finish the expression. Below it, the FIX: an `if` with no `else` at all. And a
# conditional whose value IS read, left alone.


def audit(log, verbose: bool, event: str) -> None:
    # @sin ConditionalStatement
    log.write(f"audit: {event}") if verbose else None
    log.flush()


# @fixed ConditionalStatement
def audit_event(log, verbose: bool, event: str) -> None:
    if verbose:
        log.write(f"audit: {event}")
    log.flush()


# @righteous ConditionalStatement
def audit_level(verbose: bool) -> str:
    level = "debug" if verbose else "info"
    return level
