# The couriers registry: the one module above the handover forms that names them all, so every feature asks it
# instead of naming a form's own module. Importing a module of its own sub-package is an arrow INSIDE couriers,
# not a cycle with it — the sub-package's own import of dispatch is what makes the pair look mutual.
# @righteous NamespaceCycle
from shop.couriers.handover.receipt import receipt_for


def handover_forms() -> list[str]:
    return [receipt_for("parcel-1")]
