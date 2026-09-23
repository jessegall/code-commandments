"""Shelf labels on the shop's floor plan."""

from dataclasses import dataclass, field


@dataclass
class Bay:
    label: str = ""


@dataclass
class FloorPlan:
    bays: dict[str, Bay] = field(default_factory=dict)

    def bay(self, code: str) -> Bay:
        return self.bays[code]


# @sin ParamResolvedFromParam
def relabel(plan: FloorPlan, bay_code: str, label: str) -> None:
    bay = plan.bay(bay_code)
    bay.label = label.upper()


# @fixed ParamResolvedFromParam
def relabel_bay(bay: Bay, label: str) -> None:
    bay.label = label.upper()


# @righteous ParamResolvedFromParam
def bay_or_fail(plan: FloorPlan, bay_code: str) -> Bay:
    bay = plan.bays.get(bay_code)
    if bay is None:
        raise KeyError(bay_code)
    return bay
