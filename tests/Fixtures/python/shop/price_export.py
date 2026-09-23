"""Writes the price list out as a CSV file the tills pick up."""

import csv
import os
import shutil


# @sin DivergentTwin
def export_prices(folder: str, rows: list[list[str]]) -> None:
    os.makedirs(folder, exist_ok=True)
    shutil.copy2(f"{folder}/prices.csv", f"{folder}/prices.csv.bak")
    with open(f"{folder}/prices.tmp", "w", newline="") as handle:
        csv.writer(handle).writerows(rows)
    os.replace(f"{folder}/prices.tmp", f"{folder}/prices.csv")
