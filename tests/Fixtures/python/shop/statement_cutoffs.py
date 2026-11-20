from django.db import models


class StatementCutoff(models.Model):
    account = models.CharField(max_length=100)
    archived = models.BooleanField(default=False)


def archive_overdue(batches: dict[int, set[str]], cutoff: int, already_archived: set[str]) -> int:
    changed = 0
    for month, accounts in sorted(batches.items()):
        if month >= cutoff:
            break
        pending = accounts.difference(already_archived)
        if not pending:
            continue
        # @sin QueryInLoop
        changed += StatementCutoff.objects.filter(account__in=pending).update(archived=True)
        already_archived.update(pending)
    return changed


# @fixed QueryInLoop
def archive_cutoffs(batches: dict[int, set[str]], cutoff: int, already_archived: set[str]) -> int:
    due = {account for month, accounts in batches.items() if month < cutoff for account in accounts}
    pending = due.difference(already_archived)
    changed = StatementCutoff.objects.filter(account__in=pending).update(archived=True)
    already_archived.update(pending)
    return changed
