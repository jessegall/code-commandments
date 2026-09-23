# A build forgotten only when it was built, the two conditions strung before the work with `and`.
# Below it, the FIX: both conditions at the door, the work in the body.


def forget(node, registry) -> None:
    # @sin ShortCircuitStatement
    node.built and node.stale and registry.forget(node.name)


# @fixed ShortCircuitStatement
def forget_stale(node, registry) -> None:
    if node.built and node.stale:
        registry.forget(node.name)
