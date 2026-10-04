<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class DomainNormalizer
{
    public function normalize(string $input): string
    {
        $value = rtrim(mb_strtolower(trim($input)), '.');
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $value = substr(strrchr($value, '@') ?: '', 1);
        }
        if (str_contains($value, '://') || str_contains($value, '/') || filter_var($value, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Enter a domain or email address, not a URL or IP address.');
        }
        $ascii = idn_to_ascii($value, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($ascii === false || strlen($ascii) > 253 || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/', $ascii)) {
            throw new InvalidArgumentException('The domain or email address is malformed.');
        }

        return $ascii;
    }

    public function selector(?string $selector): ?string
    {
        $selector = $selector === null ? null : strtolower(trim($selector));
        if ($selector === null || $selector === '') {
            return null;
        }
        if (! preg_match('/^[a-z0-9](?:[a-z0-9._-]{0,61}[a-z0-9])?$/', $selector)) {
            throw new InvalidArgumentException('The DKIM selector is malformed.');
        }

        return $selector;
    }
}
