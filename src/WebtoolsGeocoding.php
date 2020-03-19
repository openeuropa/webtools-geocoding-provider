<?php

declare(strict_types = 1);

namespace OpenEuropa\Provider\WebtoolsGeocoding;

use Geocoder\Collection;
use Geocoder\Exception\InvalidArgument;
use Geocoder\Exception\InvalidServerResponse;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Http\Provider\AbstractHttpProvider;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Http\Client\HttpClient;
use Psr\Http\Message\RequestInterface;

/**
 * Webtools Geocoding provider for Geocoder PHP.
 */
class WebtoolsGeocoding extends AbstractHttpProvider
{
    /**
     * @var string
     */
    const ENDPOINT_URL = 'https://europa.eu/webtools/rest/geocoding/?f=json&text=%s&maxLocations=%d&outFields=*';

    /**
     * Optional referer.
     *
     * @var string
     */
    private $referer;

    /**
     * Constructs a WebtoolsGeocoding provider.
     */
    public function __construct(HttpClient $client, ?string $referer = null)
    {
        parent::__construct($client);
        $this->referer = $referer;
    }

    /**
     * {@inheritdoc}
     */
    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $address = $query->getText();
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation(
                'The WebtoolsGeocoding provider does not support IP addresses, only street addresses.'
            );
        }

        // Save a request if no valid address entered
        if (empty($address)) {
            throw new InvalidArgument('Address cannot be empty.');
        }

        $url = sprintf(self::ENDPOINT_URL, urlencode($address), $query->getLimit());
        $json = $this->getUrlContents($url);
        $content = json_decode($json);

        if (empty($content)) {
            throw InvalidServerResponse::create($url);
        }

        if (empty($content->locations)) {
            return new AddressCollection([]);
        }

        $results = [];
        foreach ($content->locations as $location) {
            if (empty($location->feature)) {
                continue;
            }

            $address_data = $this->getAddressData($location->feature);
            $address_data['providedBy'] = $this->getName();

            $results[] = Address::createFromArray($address_data);
        }

        return new AddressCollection($results);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'webtools_geocoding';
    }

    /**
     * {@inheritdoc}
     */
    public function reverseQuery(ReverseQuery $query): Collection
    {
        throw new UnsupportedOperation('The Webtools Geocoding provider does not support reverse geocoding.');
    }

    /**
     * Returns discrete location fields from the supplied data.
     *
     * @param \stdClass $feature
     *   The location feature JSON object, as returned by the Webtools Geocoding
     *   REST API.
     *
     * @return array
     *   An array of estimated address data, with the following keys:
     *   - streetNumber: the street number.
     *   - streetName: the street name.
     *   - locality: the locality.
     *   - postalCode: the postal code.
     *   - countryCode: the country code.
     *   - longitude: the longitude.
     *   - latitude: the latitude.
     *   - adminLevels: an array of administration levels.
     */
    protected function getAddressData(\stdClass $feature): array
    {
        $attributes = $feature->attributes ?? null;
        $geometry = $feature->geometry ?? null;

        $address_data = [];

        $address_data['latitude'] = $geometry->y ?? null;
        $address_data['longitude'] = $geometry->x ?? null;

        $mapping = [
            'streetName' => 'StAddr',
            'streetNumber' => 'AddNum',
            'locality' => 'City',
            'postalCode' => 'Postal',
            'countryCode' => 'Country',
        ];

        foreach ($mapping as $address_part => $attribute_id) {
            $address_data[$address_part] = !empty($attributes->{$attribute_id}) ? $attributes->{$attribute_id} : null;
        }

        $admin_levels = [];
        foreach (['Region', 'Subregion'] as $i => $attribute_id) {
            if (!empty($attributes->{$attribute_id})) {
                $admin_levels[] = [
                    'name' => $attributes->{$attribute_id},
                    'level' => $i + 1,
                ];
            }
        }

        $address_data['adminLevels'] = $admin_levels;

        return $address_data;
    }

    /**
     * {@inheritdoc}
     */
    protected function getRequest(string $url): RequestInterface
    {
        $request = parent::getRequest($url);
        if (!empty($this->referer)) {
            $request = $request->withHeader('Referer', $this->referer);
        }

        return $request;
    }
}
