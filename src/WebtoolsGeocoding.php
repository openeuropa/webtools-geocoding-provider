<?php

declare(strict_types=1);

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
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Webtools Geocoding provider for Geocoder PHP.
 */
class WebtoolsGeocoding extends AbstractHttpProvider
{
    /**
     * @var string
     */
    private const ENDPOINT_URL = 'https://gisco-services.ec.europa.eu/api?q=%s&limit=%s';

    /**
     * @var string[]
     */
    private const PROPERTY_MAPPING = [
        'streetName' => 'street',
        'streetNumber' => 'housenumber',
        'locality' => 'city',
        'subLocality' => 'locality',
        'postalCode' => 'postcode',
        'countryCode' => 'countrycode',
        'country' => 'country',
    ];

    /**
     * @var int
     */
    private const ADMIN_LEVELS = [
        'state' => 1,
        'county' => 2,
        'city' => 3,
        'district' => 4,
        'locality' => 5,
    ];

    /**
     * Optional referer.
     *
     * @var string
     */
    private $referer;

    /**
     * Constructs a WebtoolsGeocoding provider.
     */
    public function __construct(ClientInterface $client, ?string $referer = null) {
        parent::__construct($client);
        $this->referer = $referer;
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
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $address = $query->getText();
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The WebtoolsGeocoding provider does not support IP addresses, only street addresses.');
        }

        // Save a request if no valid address entered
        if (empty($address)) {
            throw new InvalidArgument('Address cannot be empty.');
        }

        $url = sprintf(self::ENDPOINT_URL, urlencode($address), $query->getLimit());
        $content = json_decode($this->getUrlContents($url));

        if (empty($content)) {
            throw InvalidServerResponse::create($url);
        }

        if (empty($content->features)) {
            return new AddressCollection([]);
        }

        $results = [];
        foreach ($content->features as $feature) {
            if ($addressData = $this->getAddressData($feature)) {
                $results[] = Address::createFromArray($addressData);
            }
        }

        return new AddressCollection($results);
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
     * @return array|null
     *   An array of estimated address data, with the following keys:
     *   - streetNumber: the street number.
     *   - streetName: the street name.
     *   - locality: the locality.
     *   - subLocality: the sub-locality.
     *   - postalCode: the postal code.
     *   - countryCode: the country code.
     *   - country: the coutry name.
     *   - longitude: the longitude.
     *   - latitude: the latitude.
     *   - adminLevels: an array of administration levels.
     */
    protected function getAddressData(\stdClass $feature): ?array
    {
        $addressData = ['providedBy' => $this->getName()];
        if (!isset($feature->geometry->coordinates[0])
            || !is_float($feature->geometry->coordinates[0])
            || !isset($feature->geometry->coordinates[1])
            || !is_float($feature->geometry->coordinates[1])
        ) {
            return null;
        }
        $addressData['longitude'] = $feature->geometry->coordinates[0];
        $addressData['latitude'] = $feature->geometry->coordinates[1];

        if (isset($feature->properties->extent) && is_array($feature->properties->extent) && count($feature->properties->extent) === 4) {
            $addressData['bounds'] = [
                'south' => $feature->properties->extent[3],
                'west' => $feature->properties->extent[0],
                'north' => $feature->properties->extent[1],
                'east' => $feature->properties->extent[2],
            ];
        }

        foreach (static::PROPERTY_MAPPING as $addressPart => $propertyId) {
            $addressData[$addressPart] = $feature->properties->{$propertyId} ?? null;
        }

        $addressData['adminLevels'] = [];
        foreach (static::ADMIN_LEVELS as $propertyId => $level) {
            if (isset($feature->properties->{$propertyId})) {
                $addressData['adminLevels'][] = [
                    'name' => $feature->properties->{$propertyId},
                    'level' => $level,
                ];
            }
        }

        return $addressData;
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
