<?php

namespace App\Domain\Shared\ValueObjects;

final readonly class CurrencyCode
{
    private function __construct(public string $value)
    {
    }

    public static function fromString(mixed $value): self
    {
        $code = is_scalar($value) ? (string) $value : '';

        return new self(strtoupper(trim($code)));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
