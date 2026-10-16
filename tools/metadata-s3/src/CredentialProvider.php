<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

use Aws\Credentials\CredentialProvider;
use Aws\Credentials\Credentials;
use GuzzleHttp\Promise\PromiseInterface;

/** @return callable(): PromiseInterface */
function documentCredentialProvider(S3Settings $configuration): callable
{
    $configuration->verify(time());
    $memoizedExplicitCredentialProvider = CredentialProvider::memoize(CredentialProvider::fromCredentials(
        new Credentials($configuration->accessKey, $configuration->secret, $configuration->token, $configuration->expires),
    ));
    return $memoizedExplicitCredentialProvider;
}
