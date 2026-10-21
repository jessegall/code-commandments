# Expired reservations deleted out of the dict whose keys are being walked: the mapping changes size mid-walk
# and Python raises RuntimeError on the next key. Below it, the FIX: walk a list of the keys to drop.

EXPIRED = 0


def sweep(reservations: dict[str, int]) -> None:
    for code in reservations.keys():
        if reservations[code] == EXPIRED:
            # @sin CollectionMutatedWhileIterated
            del reservations[code]


# @fixed CollectionMutatedWhileIterated
def sweep_expired(reservations: dict[str, int]) -> None:
    expired = [code for code, left in reservations.items() if left == EXPIRED]
    for code in expired:
        del reservations[code]
