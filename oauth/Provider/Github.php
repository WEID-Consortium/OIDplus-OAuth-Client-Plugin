<?php
namespace Webfan\OAuth\Provider;

class Github extends GenericProvider
{
    public function __construct(array $options)
    {
        $options = array_merge([
            'urlAuthorize'            => 'https://github.com/login/oauth/authorize',
            'urlAccessToken'          => 'https://github.com/login/oauth/access_token',
            'urlResourceOwnerDetails' => 'https://api.github.com/user',
            'scope'                   => 'user:email',
        ], $options);
        parent::__construct($options);
    }
}
