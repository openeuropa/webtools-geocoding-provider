<?php

declare(strict_types=1);

namespace OpenEuropa\Provider\WebtoolsGeocoding\Tests;

use Geocoder\IntegrationTest\ProviderIntegrationTest;
use OpenEuropa\Provider\WebtoolsGeocoding\WebtoolsGeocoding;
use Http\Client\HttpClient;

/**
 * Integration test for the WebtoolsGeocoding provider.
 */
class IntegrationTest extends ProviderIntegrationTest
{
    /**
     * {@inheritdoc}
     */
    protected $testIpv4 = false;

    /**
     * {@inheritdoc}
     */
    protected $testIpv6 = false;

    /**
     * {@inheritdoc}
     */
    protected $testReverse = false;

    /**
     * {@inheritdoc}
     */
    protected function createProvider(HttpClient $httpClient)
    {
        return new WebtoolsGeocoding($httpClient);
    }

    /**
     * {@inheritdoc}
     */
    protected function getCacheDir()
    {
        return __DIR__.'/.cached_responses';
    }

    /**
     * {@inheritdoc}
     */
    protected function getApiKey()
    {
        return null;
    }
}
