<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\DnsResolver;

final class SystemDnsResolver implements DnsResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array
    {
        $addresses = [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);

            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ip']) && is_string($record['ip']) && $record['ip'] !== '') {
                        $addresses[] = $record['ip'];
                    }

                    if (isset($record['ipv6']) && is_string($record['ipv6']) && $record['ipv6'] !== '') {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }
        }

        if ($addresses === []) {
            $fallback = @gethostbynamel($host);

            if (is_array($fallback)) {
                foreach ($fallback as $address) {
                    if ($address !== '') {
                        $addresses[] = $address;
                    }
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
