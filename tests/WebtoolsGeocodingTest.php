<?php

declare(strict_types = 1);

/**
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace OpenEuropa\Provider\WebtoolsGeocoding\Tests;

use Geocoder\IntegrationTest\BaseTestCase;
use Geocoder\Location;
use Geocoder\Query\GeocodeQuery;
use OpenEuropa\Provider\WebtoolsGeocoding\WebtoolsGeocoding;

/**
 * @internal
 * @coversNothing
 */
final class WebtoolsGeocodingTest extends BaseTestCase
{
    protected function getCacheDir()
    {
        return __DIR__ . '/.cached_responses';
    }

    public function testGetName()
    {
        $provider = new WebtoolsGeocoding($this->getMockedHttpClient());
        static::assertEquals('webtools_geocoding', $provider->getName());
    }

    public function testGeocodeWithLocalhostIPv4()
    {
        $this->expectException(\Geocoder\Exception\UnsupportedOperation::class);
        $this->expectExceptionMessage("The WebtoolsGeocoding provider does not support IP addresses, only street addresses.");
        $provider = new WebtoolsGeocoding($this->getMockedHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('127.0.0.1'));
    }

    public function testGeocodeWithLocalhostIPv6()
    {
        $this->expectException(\Geocoder\Exception\UnsupportedOperation::class);
        $this->expectExceptionMessage("The WebtoolsGeocoding provider does not support IP addresses, only street addresses.");
        $provider = new WebtoolsGeocoding($this->getMockedHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('::1'));
    }

    public function testGeocodeWithRealAddress()
    {
        $provider = new WebtoolsGeocoding($this->getHttpClient());
        $results = $provider->geocodeQuery(GeocodeQuery::create('10 avenue Gambetta, Paris, France'));

        static::assertInstanceOf('Geocoder\Model\AddressCollection', $results);
        static::assertCount(1, $results);

        /** @var Location $result */
        $result = $results->first();
        static::assertInstanceOf('\Geocoder\Model\Address', $result);
        static::assertEquals(48.8631927, $result->getCoordinates()->getLatitude());
        static::assertEquals(2.3890894, $result->getCoordinates()->getLongitude());
        static::assertEquals(10, $result->getStreetNumber());
        static::assertEquals('Avenue Gambetta', $result->getStreetName());
        static::assertEquals(75020, $result->getPostalCode());
        static::assertEquals('Paris', $result->getLocality());
        static::assertEquals('Île-de-France', $result->getAdminLevels()->get(1)->getName());
        static::assertCount(5, $result->getAdminLevels());
        static::assertEquals('Paris', $result->getAdminLevels()->get(2)->getName());
    }

    /**
     * @dataProvider geocodeWithCityProvider
     */
    public function testGeocodeWithCity(string $level1, string $level2, string $country_code, float $longitude, float $latitude): void
    {
        $provider = new WebtoolsGeocoding($this->getHttpClient());
        $results = $provider->geocodeQuery(GeocodeQuery::create('London'));

        static::assertInstanceOf('Geocoder\Model\AddressCollection', $results);
        static::assertCount(5, $results);

        $result_found = false;
        /** @var Location $result */
        foreach ($results as $result) {
            if ($result->getAdminLevels()->get(1)->getName() === $level1) {
                $result_found = true;

                static::assertInstanceOf('\Geocoder\Model\Address', $result);
                static::assertEquals($longitude, $result->getCoordinates()->getLongitude());
                static::assertEquals($latitude, $result->getCoordinates()->getLatitude());
                static::assertNull($result->getStreetNumber());
                static::assertNull($result->getStreetName());
                static::assertNull($result->getPostalCode());
                static::assertCount(2, $result->getAdminLevels());
                static::assertEquals($level1, $result->getAdminLevels()->get(1)->getName());
                static::assertEquals($level2, $result->getAdminLevels()->get(2)->getName());
                static::assertEquals($country_code, $result->getCountry()->getCode());

                break;
            }
        }

        static::assertTrue($result_found);
    }

    /**
     * Data provider for ::testGeocodeWithCity().
     *
     * @return array
     *   An array of test data pertaining to the various cities called Hannover
     *   across the world.
     */
    public function geocodeWithCityProvider(): array
    {
        return [
            [
                'level1' => 'England',
                'level2' => 'Greater London',
                'country_code' => 'GB',
                'longitude' => -0.1276474,
                'latitude' => 51.5073219,
            ],
            [
                'level1' => 'Ontario',
                'level2' => 'Southwestern Ontario',
                'country_code' => 'CA',
                'longitude' => -81.2291529,
                'latitude' => 42.9537654,
            ],
        ];
    }

    public function testGeocodeWithRealIPv4()
    {
        $this->expectException(\Geocoder\Exception\UnsupportedOperation::class);
        $this->expectExceptionMessage("The WebtoolsGeocoding provider does not support IP addresses, only street addresses.");
        $provider = new WebtoolsGeocoding($this->getHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('88.188.221.14'));
    }

    public function testGeocodeWithRealIPv6()
    {
        $this->expectException(\Geocoder\Exception\UnsupportedOperation::class);
        $this->expectExceptionMessage("The WebtoolsGeocoding provider does not support IP addresses, only street addresses.");
        $provider = new WebtoolsGeocoding($this->getHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('::ffff:88.188.221.14'));
    }
}
