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
    const ENDPOINT_URL = 'https://europa.eu/webtools/rest/geocoding/?address=%s&mode=10&outFields=*';

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
    public function getName(): string
    {
        return 'webtools_geocoding';
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

        $url = sprintf(self::ENDPOINT_URL, urlencode($address));
        $content = json_decode($this->getUrlContents($url));

        if (empty($content)) {
            throw InvalidServerResponse::create($url);
        }

        if (empty($content->addressesFound)) {
            return new AddressCollection([]);
        }

        $results = [];
        foreach ($content->geocodingRequestsCollection as $request) {
            if (
                empty($request->foundCount)
                || $request->responseMessage !== 'OK'
                || $request->responseCode !== 200
                || empty($request->result->features)
            ) {
                continue;
            }
            foreach ($request->result->features as $feature) {
                if ($addressData = $this->getAddressData($feature)) {
                    $results[] = Address::createFromArray($addressData);
                }
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
     *   - postalCode: the postal code.
     *   - country: the country name.
     *   - countryCode: the country code.
     *   - longitude: the longitude.
     *   - latitude: the latitude.
     *   - adminLevels: an array of administration levels.
     *   If an address cannot be determined, it returns null.
     */
    protected function getAddressData(\stdClass $feature): ?array
    {
        $addressData = ['providedBy' => $this->getName()];

        if (
            !isset($feature->geometry->coordinates[0])
            || !is_float($feature->geometry->coordinates[0])
            || !isset($feature->geometry->coordinates[1])
            || !is_float($feature->geometry->coordinates[1])
        ) {
            return null;
        }
        $addressData['longitude'] = $feature->geometry->coordinates[0];
        $addressData['latitude'] = $feature->geometry->coordinates[1];

        $mapping = [
            'streetName' => 'street',
            'streetNumber' => 'housenumber',
            'locality' => 'city',
            'postalCode' => 'postcode',
            'country' => 'country',
            'countryCode' => 'countrycode'
        ];

        foreach ($mapping as $addressPart => $propertyId) {
            $addressData[$addressPart] = !empty($feature->properties->{$propertyId}) ? $feature->properties->{$propertyId} : null;
        }

        $adminLevels = [];
        foreach (['Region', 'Subregion'] as $i => $propertyId) {
            if (!empty($feature->properties->{$propertyId})) {
                $adminLevels[] = [
                    'name' => $feature->properties->{$propertyId},
                    'level' => $i + 1,
                ];
            }
        }

        $addressData['adminLevels'] = $adminLevels;

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
