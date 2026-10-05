<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memakai header CF-Connecting-IP sebagai IP klien, tetapi hanya bila permintaan
 * datang dari proxy lokal (cloudflared/nginx). Header dari klien lain diabaikan.
 */
class CloudflareIp
{
    private const PROXY_LOKAL = ['127.0.0.0/8', '::1/128', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'];

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->headers->get('CF-Connecting-IP');
        $asal = $request->server->get('REMOTE_ADDR');

        if ($ip && $asal && filter_var($ip, FILTER_VALIDATE_IP) && IpUtils::checkIp($asal, self::PROXY_LOKAL)) {
            $request->server->set('REMOTE_ADDR', $ip);
        }

        return $next($request);
    }
}
