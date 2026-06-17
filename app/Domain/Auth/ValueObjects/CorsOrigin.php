<?php

namespace App\Domain\Auth\ValueObjects;

use InvalidArgumentException;

final readonly class CorsOrigin
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('The allowed CORS origin field is required.');
        }

        $parts = parse_url($value);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('The allowed CORS origin must be a valid URL origin.');
        }

        if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
            throw new InvalidArgumentException('The allowed CORS origin must not include a path.');
        }

        if (isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('The allowed CORS origin must only include scheme, host, and optional port.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('The allowed CORS origin must use HTTP or HTTPS.');
        }

        $host = strtolower($parts['host']);

        if ($scheme === 'http' && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new InvalidArgumentException('HTTP CORS origins are only allowed for local development hosts.');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException('The allowed CORS origin port is invalid.');
        }

        return new self($scheme.'://'.$host.($port !== null ? ':'.$port : ''));
    }

    public function value(): string
    {
        return $this->value;
    }
}
