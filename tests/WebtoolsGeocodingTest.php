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
        static::assertEquals(48.8631927, $result->getCoordinates()->getLatitude(), '', 0.0001);
        static::assertEquals(2.3890894, $result->getCoordinates()->getLongitude(), '', 0.0001);
        static::assertEquals(10, $result->getStreetNumber());
        static::assertEquals('Avenue Gambetta', $result->getStreetName());
        static::assertEquals(75020, $result->getPostalCode());
        static::assertEquals('Paris', $result->getLocality());

        // The following data is not returned yet in the current implementation
        // of the Webtools Geocoding API.
        true || static::assertEquals('Île-de-France', $result->getAdminLevels()->get(1)->getName());
        true || static::assertCount(2, $result->getAdminLevels());
        true || static::assertEquals('Paris', $result->getAdminLevels()->get(2)->getName());
    }

    /**
     * @dataProvider geocodeWithCityProvider
     */
    public function testGeocodeWithCity(int $delta, float $longitude, float $latitude): void
    {
        $provider = new WebtoolsGeocoding($this->getHttpClient());
        $results = $provider->geocodeQuery(GeocodeQuery::create('Hannover'));

        static::assertInstanceOf('Geocoder\Model\AddressCollection', $results);
        static::assertCount(10, $results);

        $result_found = false;
        /** @var Location $result */
        foreach ($results as $index => $result) {
            if ($index === $delta) {
                $result_found = true;

                static::assertInstanceOf('\Geocoder\Model\Address', $result);
                static::assertEquals($longitude, $result->getCoordinates()->getLongitude()/*, '', 0.0001*/);
                static::assertEquals($latitude, $result->getCoordinates()->getLatitude(), '', 0.0001);
                static::assertNull($result->getStreetNumber());
                static::assertNull($result->getStreetName());
                static::assertNull($result->getPostalCode());

                // The following data is not returned yet in the current implementation
                // of the Webtools Geocoding API.
                true || static::assertEquals('Germany', $result->getCountry()->getName());
                true || static::assertCount(2, $result->getAdminLevels());
                true || static::assertEquals($region, $result->getAdminLevels()->get(1)->getName());
                true || static::assertEquals($sub_region, $result->getAdminLevels()->get(2)->getName());
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
                'delta' => 0,
                'longitude' => 9.738150000000076,
                'latitude' => 52.37227000000007,
            ],
            [
                'delta' => 2,
                'longitude' => -101.42142999999999,
                'latitude' => 47.111290000000054,
            ],
            [
                'delta' => 4,
                'longitude' => 9.740000000000066,
                'latitude' => 52.44889000000006,
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
