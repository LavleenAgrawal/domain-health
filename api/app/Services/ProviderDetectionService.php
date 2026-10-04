<?php

declare(strict_types=1);

namespace App\Services;

class ProviderDetectionService
{
    public function detect(array $mx): array
    {
        if (($mx['status'] ?? '') !== 'NOERROR') {
            return ['provider' => 'Not Detected', 'status' => 'unknown', 'reason' => 'MX lookup '.$mx['status'], 'evidence' => []];
        }
        $values = $mx['values'] ?? [];
        if (count($values) === 1 && ($values[0]['host'] ?? '') === '') {
            return ['provider' => 'Not Detected', 'status' => 'detected', 'reason' => 'Null MX: domain explicitly does not accept email.', 'evidence' => $values];
        }
        if ($values === []) {
            return ['provider' => 'Not Detected', 'status' => 'detected', 'reason' => 'No MX records found. This does not prove the domain cannot receive mail.', 'evidence' => []];
        }
        $providers = [];
        foreach ($values as $mxRecord) {
            $host = strtolower(rtrim((string) ($mxRecord['host'] ?? ''), '.'));
            $providers[$this->providerForHost($host)][] = $mxRecord;
        }
        unset($providers['Other']);
        if (count($providers) > 1) {
            return ['provider' => 'Other', 'status' => 'detected', 'reason' => 'Conflicting Google and Microsoft MX evidence.', 'evidence' => $values];
        }
        if (count($providers) === 1) {
            $provider = array_key_first($providers);

            return ['provider' => $provider, 'status' => 'detected', 'reason' => 'MX hostname matches documented '.$provider.' boundary.', 'evidence' => $values];
        }

        return ['provider' => 'Other', 'status' => 'detected', 'reason' => 'MX records exist but do not match Google Workspace or Microsoft 365 patterns; a gateway may obscure the underlying provider.', 'evidence' => $values];
    }

    private function providerForHost(string $host): string
    {
        if (preg_match('/^(?:(?:alt[1-4]\.)?aspmx|(?:alt[1-4]\.)?gmail-smtp-in)\.l\.google\.com$/', $host)) {
            return 'Google Workspace';
        }
        if (preg_match('/(^|\.)mail\.protection\.outlook\.com$/', $host)) {
            return 'Microsoft 365';
        }

        return 'Other';
    }
}
