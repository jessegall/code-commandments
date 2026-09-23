# The nightly stock report reads each warehouse's figures out of a Mapping.

from typing import Mapping


def report_line(warehouse: Mapping[str, object]) -> str:
    # @sin DictBag
    name = warehouse["name"]
    # @sin DictBag
    return f"{name}: {warehouse['on_hand']} on hand, {warehouse['reserved']} reserved"
