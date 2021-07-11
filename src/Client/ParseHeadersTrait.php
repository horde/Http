<?php
namespace Horde\Http\Client;
use \Horde_Support_CaseInsensitiveArray as CaseInsensitiveArray;

trait ParseHeadersTrait
{

    /**
     * Catch a parsed http return code
     * 
     * Some clients like fopen rely on this.
     */
    private $parsedCode = null;
    private $parsedHttpVersion = null;

    /**
     * Parse a string or array of strings into a set of headers
     * 
     * We might move this to a trait
     */
    private function parseHeaders($headers)
    {
        $this->parsedCode = null;

        if (!is_array($headers)) {
            $headers = preg_split("/\r?\n/", $headers);
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
                $this->parsedCode = (int)$httpMatches[2];
                $bucket = new CaseInsensitiveArray();
                $lastHeader = null;
            }

            $headerLine = trim($headerLine, "\r\n");
            if ($headerLine == '') {
                break;
            }
            if (preg_match('|^([\w-]+):\s+(.+)|', $headerLine, $m)) {
                $headerName = $m[1];
                $headerValue = $m[2];

                if ($tmp = $bucket[$headerName]) {
                    if (!is_array($tmp)) {
                        $tmp = array($tmp);
                    }
                    $tmp[] = $headerValue;
                    $headerValue = $tmp;
                }

                $bucket[$headerName] = $headerValue;
                $lastHeader = $headerName;
            } elseif (preg_match("|^\s+(.+)$|", $headerLine, $m) &&
                      !is_null($lastHeader)) {
                if (is_array($bucket[$lastHeader])) {
                    $tmp = $bucket[$lastHeader];
                    end($tmp);
                    $tmp[key($tmp)] .= $m[1];
                    $bucket[$lastHeader] = $tmp;
                } else {
                    $bucket[$lastHeader] .= $m[1];
                }
            }
        }
        return $bucket;
    }

}