from django.db import models


class StockMovement(models.Model):
    reference = models.CharField(max_length=120)


def represented_bins(bins: dict[str, list[str]]) -> int:
    total = 0
    for zone, references in bins.items():
        if not references:
            continue
        # @sin QueryInLoop
        count = StockMovement.objects.filter(reference__in=references).count()
        if zone.startswith("returns"):
            total -= count
        else:
            total += count
    return total


def load_stock_movements_together(references: list[str]) -> list[StockMovement]:
    return list(StockMovement.objects.filter(reference__in=references))
