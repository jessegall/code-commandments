namespace Microsoft.Extensions.DependencyInjection
{
    public interface IServiceCollection
    {
    }

    public static class ServiceCollectionServiceExtensions
    {
        public static void AddSingleton<T>(this IServiceCollection services)
        {
            System.GC.KeepAlive(services);
        }
    }

    public static class ServiceProviderServiceExtensions
    {
        public static T GetRequiredService<T>(this System.IServiceProvider services) where T : new()
        {
            return new T();
        }
    }
}

namespace Microsoft.EntityFrameworkCore
{
    public sealed class DbSet<T>
    {
        public string Find(int identifier)
        {
            return identifier.ToString();
        }
    }
}
