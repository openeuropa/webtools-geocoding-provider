<?php

declare(strict_types=1);

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace OpenEuropa\Provider\WebtoolsGeocoding\Tests;

use Geocoder\Collection;
use Geocoder\IntegrationTest\BaseTestCase;
use Geocoder\Location;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use OpenEuropa\Provider\WebtoolsGeocoding\WebtoolsGeocoding;

class WebtoolsGeocodingTest extends BaseTestCase
{
    protected function getCacheDir()
    {
        return __DIR__.'/.cached_responses';
    }

    public function testGetName()
    {
        $provider = new WebtoolsGeocoding($this->getMockedHttpClient());
        $this->assertEquals('webtools_geocoding', $provider->getName());
    }

    /**
     * @expectedException \Geocoder\Exception\UnsupportedOperation
     * @expectedExceptionMessage The WebtoolsGeocoding provider does not support IP addresses, only street addresses.
     */
    public function testGeocodeWithLocalhostIPv4()
    {
        $provider = new WebtoolsGeocoding($this->getMockedHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('127.0.0.1'));
    }

    /**
     * @expectedException \Geocoder\Exception\UnsupportedOperation
     * @expectedExceptionMessage The WebtoolsGeocoding provider does not support IP addresses, only street addresses.
     */
    public function testGeocodeWithLocalhostIPv6()
    {
        $provider = new WebtoolsGeocoding($this->getMockedHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('::1'));
    }

    public function testGeocodeWithRealAddress()
    {
        $provider = new WebtoolsGeocoding($this->getHttpClient());
        $results = $provider->geocodeQuery(GeocodeQuery::create('10 avenue Gambetta, Paris, France'));

        $this->assertInstanceOf('Geocoder\Model\AddressCollection', $results);
        $this->assertCount(5, $results);

        /** @var Location $result */
        $result = $results->first();
        $this->assertInstanceOf('\Geocoder\Model\Address', $result);
        $this->assertEquals(48.865195518466, $result->getCoordinates()->getLatitude(), '', 0.0001);
        $this->assertEquals(2.3987030416067, $result->getCoordinates()->getLongitude(), '', 0.0001);
        $this->assertEquals(10, $result->getStreetNumber());
        $this->assertEquals('Avenue Gambetta', $result->getStreetName());
        $this->assertEquals(75020, $result->getPostalCode());
        $this->assertEquals('Paris', $result->getLocality());

        // The following data is not returned yet in the current implementation
        // of the Webtools Geocoding API.
        TRUE || $this->assertCount(2, $result->getAdminLevels());
        TRUE || $this->assertEquals('Île-de-France', $result->getAdminLevels()->get(1)->getName());
        TRUE || $this->assertEquals('Paris', $result->getAdminLevels()->get(2)->getName());
        TRUE || $this->assertEquals('FRA', $result->getCountry()->getCode());
    }

    /**
     * @dataProvider geocodeWithCityProvider
     */
    public function testGeocodeWithCity(string $region, float $longitude, float $latitude)
    {
        $provider = new WebtoolsGeocoding($this->getHttpClient());
        $results = $provider->geocodeQuery(GeocodeQuery::create('Hannover'));

        $this->assertInstanceOf('Geocoder\Model\AddressCollection', $results);
        $this->assertCount(5, $results);

        $result_found = FALSE;
        /** @var Location $result */
        foreach ($results as $result) {
            if ($result->getAdminLevels()->get(1)->getName() === $region) {
                $result_found = TRUE;

                $this->assertInstanceOf('\Geocoder\Model\Address', $result);
                $this->assertEquals($longitude, $result->getCoordinates()->getLongitude(), '', 0.0001);
                $this->assertEquals($latitude, $result->getCoordinates()->getLatitude(), '', 0.0001);
                $this->assertNull($result->getStreetNumber());
                $this->assertNull($result->getStreetName());
                $this->assertNull($result->getPostalCode());
                $this->assertEquals('Hannover', $result->getLocality());
                $this->assertCount(1, $result->getAdminLevels());
                $this->assertEquals($region, $result->getAdminLevels()->get(1)->getName());

                // The following data is not returned yet in the current implementation
                // of the Webtools Geocoding API.
                TRUE || $this->assertEquals('DEU', $result->getCountry()->getCode());
                TRUE || $this->assertEquals('Germany', $result->getCountry()->getName());
                break;
            }
        }

        $this->assertTrue($result_found);
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
                'region' => 'Niedersachsen',
                'longitude' => 9.738150,
                'latitude' => 52.37227,
            ],
            [
                'region' => 'Maryland',
                'longitude' => -77.44026,
                'latitude' => 39.39177,
            ],
            [
                'region' => 'North Dakota',
                'longitude' => -101.42143,
                'latitude' => 47.11129,
            ],
            [
                'region' => 'Mississippi',
                'longitude' => -90.06299,
                'latitude' => 32.51879,
            ],
        ];
    }

    /**
     * @expectedException \Geocoder\Exception\UnsupportedOperation
     * @expectedExceptionMessage The WebtoolsGeocoding provider does not support IP addresses, only street addresses.
     */
    public function testGeocodeWithRealIPv4()
    {
        $provider = new WebtoolsGeocoding($this->getHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('88.188.221.14'));
    }

    /**
     * @expectedException \Geocoder\Exception\UnsupportedOperation
     * @expectedExceptionMessage The WebtoolsGeocoding provider does not support IP addresses, only street addresses.
     */
    public function testGeocodeWithRealIPv6()
    {
        $provider = new WebtoolsGeocoding($this->getHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('::ffff:88.188.221.14'));
    }
}
