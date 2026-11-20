using System;
using System.Collections.Generic;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;

namespace Shop.Services;

public sealed class AccountStatementProjection {}
public sealed class StatementArchive {}

public sealed class AccountStatementReader
{
    private readonly IServiceProvider services;

    public AccountStatementReader(IServiceProvider services)
    {
        this.services = services;
    }

    public AccountStatementProjection Projection()
    {
        // @sin ContainerReach
        return this.services.GetRequiredService<AccountStatementProjection>();
    }

    public IDictionary<int, string> Read(DbSet<AccountStatementProjection> statements, IEnumerable<int> accounts)
    {
        var statementByAccount = new Dictionary<int, string>();
        foreach (var account in accounts)
        {
            if (statementByAccount.ContainsKey(account)) continue;
            // @sin QueryInLoop
            var statement = statements.Find(account);
            statementByAccount.Add(account, statement);
        }
        return statementByAccount;
    }
}

public static class AccountStatementServices
{
    public static void Register(IServiceCollection services, bool importStoredStatements)
    {
        if (importStoredStatements)
        {
            // @sin OrphanedBinding
            services.AddSingleton<StatementArchive>();
        }
        // @righteous OrphanedBinding
        services.AddSingleton<AccountStatementProjection>();
    }
}
