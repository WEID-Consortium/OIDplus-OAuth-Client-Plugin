<?php
namespace Webfan\OAuth\Provider;

class Google extends GenericProvider
{
    public function __construct(array $options)
    {
        $options = array_merge([
            'urlAuthorize'            => 'https://accounts.google.com/o/oauth2/v2/auth',
            'urlAccessToken'          => 'https://oauth2.googleapis.com/token',
            'urlResourceOwnerDetails' => 'https://www.googleapis.com/oauth2/v1/userinfo?alt=json',
            'scope'                   => 'openid email profile',
        ], $options);
        parent::__construct($options);
    }
}
