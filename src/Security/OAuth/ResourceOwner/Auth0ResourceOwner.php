<?php

namespace App\Security\OAuth\ResourceOwner;

use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\GenericOAuth2ResourceOwner;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function Symfony\Component\String\u;

class Auth0ResourceOwner extends GenericOAuth2ResourceOwner
{
    protected array $paths = [
        'identifier' => 'user_id',
        'realname' => 'user_name',
        'email' => 'user_name',
    ];

    /**
     * @param string $redirectUri
     * @param array<string,mixed> $extraParameters
     * @return string
     */
    public function getAuthorizationUrl($redirectUri, array $extraParameters = [])
    {
        $parameters = array_merge([
            'audience' => $this->options['audience'],
        ], $extraParameters);

        return parent::getAuthorizationUrl($redirectUri, $parameters);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'authorization_url' => '{base_url}/authorize',
            'access_token_url' => '{base_url}/oauth/token',
            'infos_url' => '{base_url}/userinfo',
            'audience' => '{base_url}/userinfo',
        ]);

        $resolver->setRequired([
            'base_url',
        ]);

        $normalizer = function (Options $options, $value) {
            if (false === is_string($options['base_url'])) {
                throw new \InvalidArgumentException('base_url is not a string');
            }
            return u($value)->replace('{base_url}', $options['base_url']);
        };

        $resolver->setNormalizer('authorization_url', $normalizer);
        $resolver->setNormalizer('access_token_url', $normalizer);
        $resolver->setNormalizer('infos_url', $normalizer);
        $resolver->setNormalizer('audience', $normalizer);
    }
}