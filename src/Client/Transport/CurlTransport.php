<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Client\Transport;

use BradSearch\SyncSdk\Exceptions\TransportException;

final class CurlTransport implements Transport
{
    public function send(HttpRequest $request): HttpResponse
    {
        $curl = curl_init();

        if ($curl === false) {
            throw new TransportException('Failed to initialize cURL');
        }

        // No curl_close(): a no-op since PHP 8.0 and deprecated in 8.5; the handle is freed with the object.
        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $request->timeout,
            CURLOPT_CONNECTTIMEOUT => $request->connectTimeout,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $request->headers,
            CURLOPT_SSL_VERIFYPEER => $request->verifySSL,
            CURLOPT_SSL_VERIFYHOST => $request->verifySSL ? 2 : 0,
        ];

        if ($request->body !== null) {
            $options[CURLOPT_POSTFIELDS] = $request->body;
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);

        if ($response === false) {
            $errno = curl_errno($curl);
            $connectTime = (float) curl_getinfo($curl, CURLINFO_CONNECT_TIME);

            throw new TransportException(
                'cURL error: ' . curl_error($curl),
                $errno,
                TransportException::isConnectionFailure($errno, $connectTime)
            );
        }

        if (!is_string($response)) {
            throw new TransportException('Invalid response from server');
        }

        return new HttpResponse(curl_getinfo($curl, CURLINFO_HTTP_CODE), $response);
    }
}
