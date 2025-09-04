<?php

declare(strict_types = 1);

namespace OpenEuropa\Provider\WebtoolsGeocoding\Tests;

use Geocoder\IntegrationTest\ProviderIntegrationTest;
use OpenEuropa\Provider\WebtoolsGeocoding\WebtoolsGeocoding;
use Psr\Http\Client\ClientInterface;

/**
 * Integration test for the WebtoolsGeocoding provider.
 *
 * @internal
 * @coversNothing
 */
final class IntegrationTest extends ProviderIntegrationTest
{
    /**
     * {@inheritdoc}
     */
    protected bool $testIpv4 = false;

    /**
     * {@inheritdoc}
     */
    protected bool $testIpv6 = false;

    /**
     * {@inheritdoc}
     */
    protected bool $testReverse = false;

    /**
     * {@inheritdoc}
     */
    protected function createProvider(ClientInterface $httpClient)
    {
        return new WebtoolsGeocoding($httpClient);
    }

    /**
     * {@inheritdoc}
     */
    protected function getCacheDir(): string
    {
        return __DIR__ . '/.cached_responses';
    }

    /**
     * {@inheritdoc}
     */
    protected function getApiKey(): string
    {
        return '';
    }
}
