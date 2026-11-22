<?php

namespace Symfony\Component\HttpFoundation;

class Request implements \Stringable
{
    public const HEADER_FORWARDED = 1;
    public const HEADER_X_FORWARDED_FOR = 2;
    public const HEADER_X_FORWARDED_HOST = 4;
    public const HEADER_X_FORWARDED_PROTO = 8;
    public const HEADER_X_FORWARDED_PORT = 16;
    public const HEADER_X_FORWARDED_PREFIX = 32;
    public const HEADER_X_FORWARDED_AWS_ELB = 26;
    public const HEADER_X_FORWARDED_TRAEFIK = 62;
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_PURGE = 'PURGE';
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_TRACE = 'TRACE';
    public const METHOD_CONNECT = 'CONNECT';
    public const METHOD_QUERY = 'QUERY';
    private const FORWARDED_PARAMS = null;
    private const TRUSTED_HEADERS = null;
    private const STRUCTURED_SUFFIX_FORMATS = null;
    protected static array $trustedProxies = array (
);
    protected static array $trustedHostPatterns = array (
);
    protected static array $trustedHosts = array (
);
    protected static bool $httpMethodParameterOverride = false;
    protected static ?array $allowedHttpMethodOverride = NULL;
    public \Symfony\Component\HttpFoundation\ParameterBag $attributes;
    public \Symfony\Component\HttpFoundation\InputBag $request;
    public \Symfony\Component\HttpFoundation\InputBag $query;
    public \Symfony\Component\HttpFoundation\ServerBag $server;
    public \Symfony\Component\HttpFoundation\FileBag $files;
    public \Symfony\Component\HttpFoundation\InputBag $cookies;
    public \Symfony\Component\HttpFoundation\HeaderBag $headers;
    protected $content = NULL;
    protected ?array $languages = NULL;
    protected ?array $charsets = NULL;
    protected ?array $encodings = NULL;
    protected ?array $acceptableContentTypes = NULL;
    protected ?string $pathInfo = NULL;
    protected ?string $requestUri = NULL;
    protected ?string $baseUrl = NULL;
    protected ?string $basePath = NULL;
    protected ?string $method = NULL;
    protected ?string $format = NULL;
    protected \Symfony\Component\HttpFoundation\Session\SessionInterface|\Closure|null $session = NULL;
    protected ?string $locale = NULL;
    protected string $defaultLocale = 'en';
    protected static ?array $formats = NULL;
    protected static ?\Closure $requestFactory = NULL;
    private ?string $preferredFormat = NULL;
    private bool $isHostValid = true;
    private bool $isForwardedValid = true;
    private bool $isSafeContentPreferred;
    private array $trustedValuesCache = array (
);
    private static int $trustedHeaderSet = -1;
    private bool $isIisRewrite = false;
    public function __construct(array $query = array (
), array $request = array (
), array $attributes = array (
), array $cookies = array (
), array $files = array (
), array $server = array (
), $content = NULL) {}
    public function initialize(array $query = array (
), array $request = array (
), array $attributes = array (
), array $cookies = array (
), array $files = array (
), array $server = array (
), $content = NULL): void {}
    public static function createFromGlobals(): static {}
    public static function create(string $uri, string $method = 'GET', array $parameters = array (
), array $cookies = array (
), array $files = array (
), array $server = array (
), $content = NULL): static {}
    public static function setFactory(?callable $callable): void {}
    public function duplicate(?array $query = NULL, ?array $request = NULL, ?array $attributes = NULL, ?array $cookies = NULL, ?array $files = NULL, ?array $server = NULL): static {}
    public function __clone() {}
    public function __toString(): string {}
    public function overrideGlobals(): void {}
    public static function setTrustedProxies(array $proxies, int $trustedHeaderSet): void {}
    public static function getTrustedProxies(): array {}
    public static function getTrustedHeaderSet(): int {}
    public static function setTrustedHosts(array $hostPatterns): void {}
    public static function getTrustedHosts(): array {}
    public static function normalizeQueryString(?string $qs): string {}
    public static function enableHttpMethodParameterOverride(): void {}
    public static function getHttpMethodParameterOverride(): bool {}
    public static function setAllowedHttpMethodOverride(?array $methods): void {}
    public static function getAllowedHttpMethodOverride(): ?array {}
    public function get(string $key, mixed $default = NULL): mixed {}
    public function getSession(): \Symfony\Component\HttpFoundation\Session\SessionInterface {}
    public function hasPreviousSession(): bool {}
    public function hasSession(bool $skipIfUninitialized = false): bool {}
    public function setSession(\Symfony\Component\HttpFoundation\Session\SessionInterface $session): void {}
    public function setSessionFactory(callable $factory): void {}
    public function getClientIps(): array {}
    public function getClientIp(): ?string {}
    public function getScriptName(): string {}
    public function getPathInfo(): string {}
    public function getBasePath(): string {}
    public function getBaseUrl(): string {}
    private function getBaseUrlReal(): string {}
    public function getScheme(): string {}
    public function getPort(): string|int|null {}
    public function getUser(): ?string {}
    public function getPassword(): ?string {}
    public function getUserInfo(): ?string {}
    public function getHttpHost(): string {}
    public function getRequestUri(): string {}
    public function getSchemeAndHttpHost(): string {}
    public function getUri(): string {}
    public function getUriForPath(string $path): string {}
    public function getRelativeUriForPath(string $path): string {}
    public function getQueryString(): ?string {}
    public function isSecure(): bool {}
    public function getHost(): string {}
    public function setMethod(string $method): void {}
    public function getMethod(): string {}
    public function getRealMethod(): string {}
    public function getMimeType(string $format): ?string {}
    public static function getMimeTypes(string $format): array {}
    public function getFormat(?string $mimeType): ?string {}
    public function setFormat(?string $format, array|string $mimeTypes): void {}
    public function getRequestFormat(?string $default = 'html'): ?string {}
    public function setRequestFormat(?string $format): void {}
    public function getContentTypeFormat(): ?string {}
    public function setDefaultLocale(string $locale): void {}
    public function getDefaultLocale(): string {}
    public function setLocale(string $locale): void {}
    public function getLocale(): string {}
    public function isMethod(string $method): bool {}
    public function isMethodSafe(): bool {}
    public function isMethodIdempotent(): bool {}
    public function isMethodCacheable(): bool {}
    public function getProtocolVersion(): ?string {}
    /** @return string|resource */
    public function getContent(bool $asResource = false) {}
    public function getPayload(): \Symfony\Component\HttpFoundation\InputBag {}
    public function toArray(): array {}
    public function getETags(): array {}
    public function isNoCache(): bool {}
    public function getPreferredFormat(?string $default = 'html'): ?string {}
    public function getPreferredLanguage(?array $locales = NULL): ?string {}
    public function getLanguages(): array {}
    private static function formatLocale(string $locale): string {}
    private static function getLanguageCombinations(string $locale): array {}
    private static function getLanguageComponents(string $locale): array {}
    public function getCharsets(): array {}
    public function getEncodings(): array {}
    public function getAcceptableContentTypes(): array {}
    public function isXmlHttpRequest(): bool {}
    public function preferSafeContent(): bool {}
    protected function prepareRequestUri(): string {}
    protected function prepareBaseUrl(): string {}
    protected function prepareBasePath(): string {}
    protected function preparePathInfo(): string {}
    protected static function initializeFormats(): void {}
    private function setPhpDefaultLocale(string $locale): void {}
    private function getUrlencodedPrefix(string $string, string $prefix): ?string {}
    private static function createRequestFromFactory(array $query = array (
), array $request = array (
), array $attributes = array (
), array $cookies = array (
), array $files = array (
), array $server = array (
), $content = NULL): static {}
    public function isFromTrustedProxy(): bool {}
    private function getTrustedValues(int $type, ?string $ip = NULL): array {}
    private function normalizeAndFilterClientIps(array $clientIps, string $ip): array {}
    private function isIisRewrite(): bool {}
    private static function isHostValid(string $host): bool {}
}
