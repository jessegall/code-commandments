# The nightly stock report reads each warehouse's figures out of a Mapping.

from typing import Mapping


# @sin DictBag
def report_line(warehouse: Mapping[str, object]) -> str:
    name = warehouse["name"]
    return f"{name}: {warehouse['on_hand']} on hand, {warehouse['reserved']} reserved"
