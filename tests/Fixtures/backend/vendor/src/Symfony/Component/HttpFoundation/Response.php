<?php

namespace Symfony\Component\HttpFoundation;

class Response implements \Stringable
{
    public const HTTP_CONTINUE = 100;
    public const HTTP_SWITCHING_PROTOCOLS = 101;
    public const HTTP_PROCESSING = 102;
    public const HTTP_EARLY_HINTS = 103;
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_ACCEPTED = 202;
    public const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_RESET_CONTENT = 205;
    public const HTTP_PARTIAL_CONTENT = 206;
    public const HTTP_MULTI_STATUS = 207;
    public const HTTP_ALREADY_REPORTED = 208;
    public const HTTP_IM_USED = 226;
    public const HTTP_MULTIPLE_CHOICES = 300;
    public const HTTP_MOVED_PERMANENTLY = 301;
    public const HTTP_FOUND = 302;
    public const HTTP_SEE_OTHER = 303;
    public const HTTP_NOT_MODIFIED = 304;
    public const HTTP_USE_PROXY = 305;
    public const HTTP_RESERVED = 306;
    public const HTTP_TEMPORARY_REDIRECT = 307;
    public const HTTP_PERMANENTLY_REDIRECT = 308;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_PAYMENT_REQUIRED = 402;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_NOT_ACCEPTABLE = 406;
    public const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    public const HTTP_REQUEST_TIMEOUT = 408;
    public const HTTP_CONFLICT = 409;
    public const HTTP_GONE = 410;
    public const HTTP_LENGTH_REQUIRED = 411;
    public const HTTP_PRECONDITION_FAILED = 412;
    public const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    public const HTTP_REQUEST_URI_TOO_LONG = 414;
    public const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    public const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    public const HTTP_EXPECTATION_FAILED = 417;
    public const HTTP_I_AM_A_TEAPOT = 418;
    public const HTTP_MISDIRECTED_REQUEST = 421;
    public const HTTP_UNPROCESSABLE_ENTITY = 422;
    public const HTTP_LOCKED = 423;
    public const HTTP_FAILED_DEPENDENCY = 424;
    public const HTTP_TOO_EARLY = 425;
    public const HTTP_UPGRADE_REQUIRED = 426;
    public const HTTP_PRECONDITION_REQUIRED = 428;
    public const HTTP_TOO_MANY_REQUESTS = 429;
    public const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    public const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_NOT_IMPLEMENTED = 501;
    public const HTTP_BAD_GATEWAY = 502;
    public const HTTP_SERVICE_UNAVAILABLE = 503;
    public const HTTP_GATEWAY_TIMEOUT = 504;
    public const HTTP_VERSION_NOT_SUPPORTED = 505;
    public const HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL = 506;
    public const HTTP_INSUFFICIENT_STORAGE = 507;
    public const HTTP_LOOP_DETECTED = 508;
    public const HTTP_NOT_EXTENDED = 510;
    public const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 511;
    private const HTTP_RESPONSE_CACHE_CONTROL_DIRECTIVES = null;
    public \Symfony\Component\HttpFoundation\ResponseHeaderBag $headers;
    protected string $content;
    protected string $version;
    protected int $statusCode;
    protected string $statusText;
    protected ?string $charset = NULL;
    public static array $statusTexts;
    private array $sentHeaders;
    public function __construct(?string $content = '', int $status = 200, array $headers = array (
)) {}
    public function __toString(): string {}
    public function __clone() {}
    public function prepare(\Symfony\Component\HttpFoundation\Request $request): static {}
    public function sendHeaders(?int $statusCode = NULL): static {}
    public function sendContent(): static {}
    public function send(bool $flush = true): static {}
    public function setContent(?string $content): static {}
    public function getContent(): string|false {}
    public function setProtocolVersion(string $version): static {}
    public function getProtocolVersion(): string {}
    public function setStatusCode(int $code, ?string $text = NULL): static {}
    public function getStatusCode(): int {}
    public function setCharset(string $charset): static {}
    public function getCharset(): ?string {}
    public function isCacheable(): bool {}
    public function isFresh(): bool {}
    public function isValidateable(): bool {}
    public function setPrivate(): static {}
    public function setPublic(): static {}
    public function setImmutable(bool $immutable = true): static {}
    public function isImmutable(): bool {}
    public function mustRevalidate(): bool {}
    public function getDate(): ?\DateTimeImmutable {}
    public function setDate(\DateTimeInterface $date): static {}
    public function getAge(): int {}
    public function expire(): static {}
    public function getExpires(): ?\DateTimeImmutable {}
    public function setExpires(?\DateTimeInterface $date): static {}
    public function getMaxAge(): ?int {}
    public function setMaxAge(int $value): static {}
    public function setStaleIfError(int $value): static {}
    public function setStaleWhileRevalidate(int $value): static {}
    public function setSharedMaxAge(int $value): static {}
    public function getTtl(): ?int {}
    public function setTtl(int $seconds): static {}
    public function setClientTtl(int $seconds): static {}
    public function getLastModified(): ?\DateTimeImmutable {}
    public function setLastModified(?\DateTimeInterface $date): static {}
    public function getEtag(): ?string {}
    public function setEtag(?string $etag, bool $weak = false): static {}
    public function setCache(array $options): static {}
    public function setNotModified(): static {}
    public function hasVary(): bool {}
    public function getVary(): array {}
    public function setVary(array|string $headers, bool $replace = true): static {}
    public function isNotModified(\Symfony\Component\HttpFoundation\Request $request): bool {}
    public function isInvalid(): bool {}
    public function isInformational(): bool {}
    public function isSuccessful(): bool {}
    public function isRedirection(): bool {}
    public function isClientError(): bool {}
    public function isServerError(): bool {}
    public function isOk(): bool {}
    public function isForbidden(): bool {}
    public function isNotFound(): bool {}
    public function isRedirect(?string $location = NULL): bool {}
    public function isEmpty(): bool {}
    public static function closeOutputBuffers(int $targetLevel, bool $flush): void {}
    public function setContentSafe(bool $safe = true): void {}
    protected function ensureIEOverSSLCompatibility(\Symfony\Component\HttpFoundation\Request $request): void {}
}
