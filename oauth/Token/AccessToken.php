<?php
namespace Webfan\OAuth\Token;

class AccessToken {
    protected ?string $token;
    protected ?string $refreshToken;
    protected ?int $expires;

    public function __construct(array $data) {
        $this->token        = $data['access_token'] ?? null;
        $this->refreshToken = $data['refresh_token'] ?? null;
        $this->expires      = isset($data['expires_in']) ? time() + (int)$data['expires_in'] : null;
    }
    public function getToken(): ?string { return $this->token; }
    public function getRefreshToken(): ?string { return $this->refreshToken; }
    public function getExpires(): ?int { return $this->expires; }
    public function hasExpired(): bool { return $this->expires !== null && time() > $this->expires; }
}
