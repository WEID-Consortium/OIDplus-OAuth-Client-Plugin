<?php
namespace Webfan\OAuth\Provider;

use Webfan\OAuth\Token\AccessToken;

abstract class AbstractProvider
{
    protected array $options = [];

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    abstract public function getAuthorizationUrl(): string;
    abstract public function getState(): string;
    abstract public function getAccessToken(string $grant, array $params = []): AccessToken;
    abstract public function getResourceOwner(AccessToken $accessToken);
}
