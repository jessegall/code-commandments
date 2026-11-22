<?php

namespace Symfony\Component\HttpFoundation;

class RedirectResponse extends \Symfony\Component\HttpFoundation\Response
{
    protected string $targetUrl;
    public function __construct(string $url, int $status = 302, array $headers = array (
)) {}
    public function getTargetUrl(): string {}
    public function setTargetUrl(string $url): static {}
}
