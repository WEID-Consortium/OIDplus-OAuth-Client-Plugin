<?php
namespace Webfan\OAuth\Provider;

use Psr\Http\Message\ResponseInterface;
use Webfan\OAuth\Token\AccessToken;
use Webfan\OAuth\ResourceOwner\GenericResourceOwner;
use Webfan\Psr7\MemoryStream;

class GenericProvider extends AbstractProvider
{
    protected string $state;

    public function getAuthorizationUrl(): string
    {
        $this->state = bin2hex(random_bytes(16));
        $url = $this->options['urlAuthorize'] . '?' . http_build_query([
            'client_id'     => $this->options['clientId'],
            'redirect_uri'  => $this->options['redirectUri'],
            'response_type' => 'code',
            'scope'         => $this->options['scope'] ?? '',
            'state'         => $this->state,
        ]);
        return $url;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getAccessToken(string $grant, array $params = []): AccessToken
    {
        $response = $this->httpRequest('POST', $this->options['urlAccessToken'], [
            'grant_type'    => $grant,
            'client_id'     => $this->options['clientId'],
            'client_secret' => $this->options['clientSecret'],
            'redirect_uri'  => $this->options['redirectUri'],
            'code'          => $params['code'] ?? null,
        ]);

        $data = json_decode((string)$response->getBody(), true);
        return new AccessToken($data);
    }

    public function getResourceOwner(AccessToken $accessToken)
    {
        $response = $this->httpRequest('GET', $this->options['urlResourceOwnerDetails'], [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken->getToken()
            ]
        ]);
        $data = json_decode((string)$response->getBody(), true);
        return new GenericResourceOwner($data);
    }

    /**
     * Very lightweight HTTP request wrapper returning a PSR-7 ResponseInterface
     */
    protected function httpRequest(string $method, string $url, array $params = []): ResponseInterface
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];

        // Merge in custom headers (e.g. Authorization)
        if (isset($params['headers']) && is_array($params['headers'])) {
            foreach ($params['headers'] as $name => $value) {
                $headers[] = $name . ': ' . $value;
            }
            unset($params['headers']);
        }

        $content = '';
        if ($method === 'POST') {
            $content = http_build_query($params);
        } elseif ($method === 'GET' && !empty($params)) {
            $query = http_build_query($params);
            $url .= (str_contains($url, '?') ? '&' : '?') . $query;
        }

        $opts = [
            'http' => [
                'method'  => $method,
                'header'  => implode("\r\n", $headers) . "\r\n",
                'content' => $content,
                'ignore_errors' => true
            ]
        ];
        $context  = stream_context_create($opts);
        $body     = file_get_contents($url, false, $context) ?: '';

        // Determine status code from $http_response_header if possible
        $statusCode = 200;
        $reasonPhrase = 'OK';
        if (isset($http_response_header[0])) {
            if (preg_match('#HTTP/\S+\s+(\d+)\s*(.*)#', $http_response_header[0], $m)) {
                $statusCode = (int)$m[1];
                $reasonPhrase = trim($m[2]) ?: $reasonPhrase;
            }
        }

        return new class($body, $statusCode, $reasonPhrase) implements ResponseInterface {
            private string $protocolVersion = '1.1';
            private array $headers = [];
            private \Psr\Http\Message\StreamInterface $body;
            private int $statusCode;
            private string $reasonPhrase;

            public function __construct(string $body, int $statusCode = 200, string $reasonPhrase = 'OK') {
                $this->body = new MemoryStream($body);
                $this->statusCode = $statusCode;
                $this->reasonPhrase = $reasonPhrase;
            }

            // MessageInterface
            public function getProtocolVersion(): string { return $this->protocolVersion; }
            public function withProtocolVersion($version): static { $clone=clone $this; $clone->protocolVersion=$version; return $clone; }
            public function getHeaders(): array { return $this->headers; }
            public function hasHeader($name): bool { return isset($this->headers[strtolower($name)]); }
            public function getHeader($name): array { return $this->headers[strtolower($name)] ?? []; }
            public function getHeaderLine($name): string { return implode(', ', $this->getHeader($name)); }
            public function withHeader($name, $value): static { $clone=clone $this; $clone->headers[strtolower($name)] = (array)$value; return $clone; }
            public function withAddedHeader($name, $value): static { $clone=clone $this; $clone->headers[strtolower($name)] = array_merge($clone->headers[strtolower($name)]??[],(array)$value); return $clone; }
            public function withoutHeader($name): static { $clone=clone $this; unset($clone->headers[strtolower($name)]); return $clone; }
            public function getBody(): \Psr\Http\Message\StreamInterface { return $this->body; }
            public function withBody(\Psr\Http\Message\StreamInterface $body): static { $clone=clone $this; $clone->body=$body; return $clone; }

            // ResponseInterface
            public function getStatusCode(): int { return $this->statusCode; }
            public function withStatus($code, $reasonPhrase = ''): static { $clone=clone $this; $clone->statusCode=$code; $clone->reasonPhrase=$reasonPhrase; return $clone; }
            public function getReasonPhrase(): string { return $this->reasonPhrase; }
        };
    }
}
