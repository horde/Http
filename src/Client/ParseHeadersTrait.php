<?php

namespace Horde\Http\Client;

use Horde_Support_CaseInsensitiveArray as CaseInsensitiveArray;

trait ParseHeadersTrait
{
    /**
     * The HTTP status code parsed from the response head. `0` before
     * parseHeaders() runs — callers gate on the presence of a HTTP/N.M
     * status line.
     */
    private int $parsedCode = 0;

    /**
     * The HTTP protocol version parsed from the response head (e.g.
     * "1.1"). Empty string before parseHeaders() runs.
     */
    private string $parsedHttpVersion = '';

    /**
     * Parse a string or array of strings into a set of headers.
     *
     * Accepts either the raw block emitted by fopen()/curl_exec() or a
     * pre-split array of lines (as `stream_get_meta_data()['wrapper_data']`
     * hands back). The result is a case-insensitive bag so callers can
     * lookup headers without matching the on-wire case.
     *
     * @param string|list<string> $headers Header block or list of header lines.
     * @return CaseInsensitiveArray
     */
    private function parseHeaders(string|array $headers): CaseInsensitiveArray
    {
        $this->parsedCode = 0;

        if (is_string($headers)) {
            $split = preg_split("/\r?\n/", $headers);
            $headers = $split === false ? [] : $split;
        }

        $bucket = new CaseInsensitiveArray();

        $lastHeader = null;
        foreach ($headers as $headerLine) {
            // stream_get_meta returns all headers generated while processing
            // a request, including ones for redirects before an eventually
            // successful request. We just want the last one, so whenever we
            // hit a new HTTP header, throw out anything parsed previously and
            // start over.
            if (preg_match('/^HTTP\/(\d.\d) (\d{3})/', $headerLine, $httpMatches)) {
                $this->parsedHttpVersion = $httpMatches[1];
                $this->parsedCode = (int) $httpMatches[2];
                $bucket = new CaseInsensitiveArray();
                $lastHeader = null;
            }

            $headerLine = trim($headerLine, "\r\n");
            if ($headerLine === '') {
                break;
            }
            if (preg_match('|^([\w-]+):\s+(.+)|', $headerLine, $m)) {
                $headerName = $m[1];
                $headerValue = $m[2];

                $existing = $bucket[$headerName] ?? null;
                if ($existing !== null && $existing !== '') {
                    if (!is_array($existing)) {
                        $existing = [$existing];
                    }
                    $existing[] = $headerValue;
                    $headerValue = $existing;
                }

                $bucket[$headerName] = $headerValue;
                $lastHeader = $headerName;
            } elseif (
                $lastHeader !== null
                && preg_match("|^\s+(.+)$|", $headerLine, $m)
            ) {
                $current = $bucket[$lastHeader] ?? '';
                if (is_array($current)) {
                    end($current);
                    $key = key($current);
                    if ($key !== null) {
                        $existing = $current[$key];
                        $current[$key] = (is_scalar($existing) ? (string) $existing : '') . $m[1];
                    }
                    $bucket[$lastHeader] = $current;
                } else {
                    $bucket[$lastHeader] = (is_scalar($current) ? (string) $current : '') . $m[1];
                }
            }
        }
        return $bucket;
    }
}
