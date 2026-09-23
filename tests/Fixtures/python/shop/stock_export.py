"""Writes the stock count out as a CSV file the warehouse picks up."""

import csv
import os
import shutil


# @sin DivergentTwin
def export_stock(folder: str, rows: list[list[str]]) -> None:
    os.makedirs(folder, exist_ok=True)
    shutil.copy2(f"{folder}/stock.csv", f"{folder}/stock.csv.bak")
    with open(f"{folder}/stock.tmp", "w", newline="") as handle:
        csv.writer(handle).writerows(rows)
        handle.flush()
        os.fsync(handle.fileno())
    os.replace(f"{folder}/stock.tmp", f"{folder}/stock.csv")
