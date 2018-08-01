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
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

/**
 * Webtools Geocoding provider for Geocoder PHP.
 */
class WebtoolsGeocoding extends AbstractHttpProvider implements Provider
{

    /**
     * @var string
     */
    const ENDPOINT_URL = 'http://europa.eu/webtools/rest/geocoding/?address=%s&mode=%d&locale=%s';

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
        if (\filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The WebtoolsGeocoding provider does not support IP addresses, only street addresses.');
        }

        // Save a request if no valid address entered
        if (empty($address)) {
            throw new InvalidArgument('Address cannot be empty.');
        }

        $url = sprintf(self::ENDPOINT_URL, urlencode($address), $query->getLimit(), $query->getLocale());
        $content = $this->getUrlContents($url);
        $json = \json_decode($content);

        if (empty($json)) {
            throw InvalidServerResponse::create($url);
        }

        if (empty($json->geocodingRequestsCollection)) {
            return new AddressCollection([]);
        }

        $results = [];
        foreach ($json->geocodingRequestsCollection as $collection) {
            if (empty($collection->result->features)) {
                continue;
            }

            foreach ($collection->result->features as $feature) {
                $address_data = $this->deduceAddressData($feature);

                $results[] = Address::createFromArray([
                    'providedBy' => $this->getName(),
                    'latitude' => $address_data['latitude'],
                    'longitude' => $address_data['longitude'],
                    'streetNumber' => $address_data['streetNumber'],
                    'streetName' => $address_data['streetName'],
                    'locality' => $address_data['locality'],
                    'postalCode' => $address_data['postalCode'],
                    'adminLevels' => $address_data['adminLevels'],
                    // The country is not currently returned by the Webtools
                    // Geocoding API.
                    'countryCode' => null,
                ]);
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
     * Attempts to deduce some address fields from the supplied data.
     *
     * The Webtools Geocoding API currently does not return address data in
     * discrete fields. Instead it returns a 'formatted address' which seems to
     * adhere to country specific formats. Unfortunately the country is not
     * included in the address so it is practically impossible to deduce which
     * format was used.
     *
     * This makes some basic assumptions like the street address being in the
     * first section.
     *
     * This is not to be relied upon for production use. A request has been
     * filed with the Webtools team to make the discrete data available.
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
     *   - longitude: the longitude.
     *   - latitude: the latitude.
     *   - adminLevels: an array of administration levels.
     */
    protected function deduceAddressData(\stdClass $feature): array
    {
        $address_data = [
            'streetNumber' => null,
            'streetName' => null,
            'locality' => null,
            'postalCode' => null,
            'longitude' => null,
            'latitude' => null,
        ];

        // Populate the geographical coordinates.
        if (!empty($feature->geometry->coordinates)) {
            list($longitude, $latitude) = $feature->geometry->coordinates;
            $address_data['longitude'] = $longitude;
            $address_data['latitude'] = $latitude;
        }

        // The 'formatted address' is the best chance we have at retrieving the
        // data. If this is not present then don't even bother.
        if (empty($feature->properties->formattedAddress)) {
            return $address_data;
        }

        // Split up the comma separated field into discrete parts.
        $parts = explode(',', $feature->properties->formattedAddress);
        $parts = array_map('trim', $parts);

        // We're assuming that if the first part contains both numbers and
        // letters then it is the street name and number. If it doesn't then we
        // are assuming the street is not included in the data.
        if ($this->containsLetter($parts[0]) && $this->containsNumber($parts[0])) {
            $street = array_shift($parts);

            // We're assuming that the street number will contain at least 1 number
            // and will be located either at the start or the end.
            $street_parts = array_map('trim', explode(' ', $street));

            if ($this->containsNumber(reset($street_parts))) {
                $address_data['streetNumber'] = array_shift($street_parts);
            }
            elseif ($this->containsNumber(end($street_parts))) {
                $address_data['streetNumber'] = array_pop($street_parts);
            }

            // Use the remainder of the street as the street name.
            if (!empty($street_parts)) {
                $address_data['streetName'] = implode(' ', $street_parts);
            }
        }

        // Try to detect the postal code with different filters.
        $postal_code_filters = [
             // First check if any of the parts consists entirely of numbers.
             function (string $value): bool {
                 return preg_match('/^\d+$/', $value) === 1;
             },

             // Check if any of the parts consists predominantly of numbers.
             function (string $value): bool {
                 $number_count = preg_match_all('/\d/', $value);
                 $other_count = preg_match_all('/[^\d]/', $value);
                 return $number_count > $other_count;
             },

             // Finally, we are happy if we can even find a part that contains
             // any number.
             function (string $value): bool {
                 return preg_match('/\d/', $value) === 1;
             },
        ];

        foreach ($postal_code_filters as $postal_code_filter) {
            $filtered_parts = array_filter($parts, $postal_code_filter);
            if (!empty($filtered_parts)) {
                // Set the first result as the postal code and remove it from
                // the set of parts.
                $address_data['postalCode'] = reset($filtered_parts);
                unset($parts[key($filtered_parts)]);
                break;
            }
        }

        // For the locality, assume that the first part that doesn't contain any
        // numbers is the locality. This is just a best guess but it will at
        // least filter out data like floor numbers, office numbers etc.
        foreach ($parts as $key => $part) {
            if (!$this->containsNumber($part)) {
                $address_data['locality'] = $part;
                unset($parts[$key]);
                break;
            }
        }

        // Expose all that remains as admin levels.
        $admin_levels = [];
        foreach (array_values($parts) as $i => $part) {
            if (!empty($part) && !$this->containsNumber($part)) {
                $admin_levels[] = ['name' => $part, 'level' => $i + 1];
            }
        }

        $address_data['adminLevels'] = $admin_levels;

        return $address_data;
    }

    protected function containsNumber(string $string): bool
    {
        return preg_match('/\d/', $string) === 1;
    }

    protected function containsLetter(string $string): bool
    {
        return preg_match('/\p{L}/', $string) === 1;
    }

}
