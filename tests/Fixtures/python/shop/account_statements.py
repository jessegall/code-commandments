from django.db import models


class AccountStatement(models.Model):
    reference = models.CharField(max_length=120)


def lookup_unfiled(accounts: list[str], published: set[str]) -> dict[str, AccountStatement]:
    result = {}
    for account in accounts:
        if account in published:
            continue
        # @sin QueryInLoop
        statement = AccountStatement.objects.get(reference=account)
        result[account] = statement
        published.add(account)
    return result


def load_account_statements_together(references: list[str]) -> list[AccountStatement]:
    return list(AccountStatement.objects.filter(reference__in=references))
