from django.db import models


class ParcelSnapshot(models.Model):
    reference = models.CharField(max_length=120)


def send_recent_parcels(queue: list[str], transport, remaining: int) -> int:
    sent = 0
    while queue and remaining > sent:
        reference = queue.pop()
        # @sin QueryInLoop
        parcel = ParcelSnapshot.objects.get(reference=reference)
        transport(parcel)
        sent += 1
    return sent


def load_parcel_snapshots_together(references: list[str]) -> list[ParcelSnapshot]:
    return list(ParcelSnapshot.objects.filter(reference__in=references))
