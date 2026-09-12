<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TrustedProxies extends TrustProxies
{
    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        $ips = config('security.trusted_proxies', []);
        foreach ($ips as $ip) {
            $parts = explode('/', $ip);
            $address = $parts[0];
            $valid = filter_var($address, FILTER_VALIDATE_IP) !== false;
            if (count($parts) > 1) {
                $valid = $valid && count($parts) === 2 && ctype_digit($parts[1])
                    && (int) $parts[1] <= (str_contains($address, ':') ? 128 : 32)
                    && (int) $parts[1] > 0;
            }
            if (! $valid) {
                throw new InvalidArgumentException('SECURITY_TRUSTED_PROXIES must contain explicit IP addresses or CIDRs.');
            }
        }
        // No automatic trust based on a caller-controlled Host or platform suffix.
        $this->setTrustedProxyIpAddressesToSpecificIps($request, $ips);
    }
}
