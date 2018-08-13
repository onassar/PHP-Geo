<?php

    // dependecy checks
    if (in_array('geoip', get_loaded_extensions()) === false) {
        throw new Exception('GeoIP extension needs to be installed.');
    }

    /**
     * Geo
     * 
     * Wrapper for GeoIP lookup service (using binary file).
     * 
     * @abstract
     * @note    The GeoIP PHP extension handles errors in a bizarre way (see:
     *          http://pecl.php.net/bugs/bug.php?id=22691). While the functions
     *          do have return values, notices are thrown if the IP being
     *          checked cannot be found. Thus, the magic method __callStatic is
     *          used as a wrapper for all getters. Therefore looking at the
     *          class signature, getters are set as protected. All methods that
     *          do *not* lead with an underscore are therefore safe to access,
     *          regardless of their defined scope.
     * @example
     * <code>
     *     // dependency
     *     require_once APP . '/vendors/PHP-Geo/Geo.class.php';
     * 
     *     // display city and country
     *     echo Geo::getCity() . ', ' . Geo::getCountry();
     *     exit(0);
     * </code>
     * @link    https://github.com/onassar/PHP-Geo
     * @author  Oliver Nassar <onassar@gmail.com>
     */
    abstract class Geo
    {
        /**
         * _ip
         * 
         * @var     string
         * @access  protected
         * @static
         */
        protected static $_ip;

        /**
         * _cache
         * 
         * IP lookup caches.
         * 
         * @var     array
         * @access  protected
         * @static
         */
        protected static $_cache = array();

        /**
         * __callStatic
         * 
         * @access  public
         * @static
         * @param   string $name
         * @param   array $arguments
         * @return  void
         */
        public static function __callStatic($name, array $arguments)
        {
            // ensure proper check
            if (preg_match('/^_/', $name)) {
                throw new Exception('Invalid method call.');
            }

            /**
             * Since the GeoIP PHP extension throws notices for unfound IP
             * addresses, setting an empty error_handler prevents any other
             * error handling from kicking in. The previously (if any) set error
             * handler is then restored.
             */
            set_error_handler(function() {});
            $callback = array('self', $name);
            $response = call_user_func_array($callback, $arguments);
            restore_error_handler();
            return $response;
        }

        /**
         * _cache
         * 
         * @access  protected
         * @static
         * @param   string $key
         * @param   mixed $value
         * @return  void
         */
        protected static function _cache($key, $value)
        {
            $key = self::_getIP() . ' / ' . ($key);
            self::$_cache[$key] = $value;
        }

        /**
         * _getDetail
         * 
         * Accessor method for raw details of geo-lookup.
         * 
         * @access  protected
         * @static
         * @param   string $name raw-record detail to retrieve
         * @return  false|mixed
         */
        protected static function _getDetail($name)
        {
            $record = self::_getRecord();
            if ($record === false) {
                return false;
            }
            if ($record[$name] === '') {
                return false;
            }
            return $record[$name];
        }

        /**
         * _getIP
         * 
         * Returns the IP address that the Geo lookup should be based on.
         * Defaults to the remote address for the request.
         * 
         * @access  protected
         * @static
         * @return  string|false
         */
        protected static function _getIP()
        {
            if (is_null(self::$_ip) === false) {
                return self::$_ip;
            }
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) === true) {
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
            if (isset($_SERVER['REMOTE_ADDR']) === true) {
                return $_SERVER['REMOTE_ADDR'];
            }
            return false;
        }

        /**
         * _getRecord
         * 
         * Returns raw GeoIP record.
         * 
         * @access  protected
         * @static
         * @return  array raw 'geo' details for the request
         */
        protected static function _getRecord()
        {
            $record = self::_lookup('record');
            if ($record === null) {
                $record = geoip_record_by_name(self::_getIP());
                if (empty($record) === true) {
                    $record = false;
                }
                self::_cache('record', $record);
            }
            return $record;
        }

        /**
         * _lookup
         * 
         * @access  protected
         * @static
         * @param   string $key
         * @return  mixed|null
         */
        protected static function _lookup($key)
        {
            $key = self::_getIP() . ' / ' . ($key);
            if (isset(self::$_cache[$key]) === true) {
                return self::$_cache[$key];
            }
            return null;
        }

        /**
         * getAreaCode
         * 
         * @access  protected
         * @static
         * @return  false|integer
         */
        protected static function getAreaCode()
        {
            $areaCode = self::_getDetail('area_code');
            if ($areaCode === false) {
                return false;
            }
            return $areaCode;
        }

        /**
         * getCity
         * 
         * @access  protected
         * @static
         * @return  false|string
         */
        protected static function getCity()
        {
            $city = self::_getDetail('city');
            if ($city === false) {
                return false;
            }
            $encoded = utf8_encode($city);
            return $encoded;
        }

        /**
         * getContinentCode
         * 
         * Continent shortform code of set IP.
         * 
         * @access  protected
         * @static
         * @return  false|string
         */
        protected static function getContinentCode()
        {
            $key = 'continentCode';
            $continentCode = self::_lookup($key);
            if ($continentCode === null) {
                $continentCode = geoip_continent_code_by_name(self::_getIP());
                if ($continentCode === '') {
                    $continentCode = false;
                }
                self::_cache($key, $continentCode);
            }
            if ($continentCode === false) {
                return false;
            }
            $encoded = utf8_encode($continentCode);
            return $encoded;
        }

        /**
         * getCoordinates
         * 
         * Returns latitude/longitude array of coordinates.
         * 
         * @access  protected
         * @static
         * @return  array
         */
        protected static function getCoordinates()
        {
            $latitude = self::getLat();
            if ($latitude === false) {
                return array(false, false);
            }
            $longitude = self::getLong();
            if ($latitude === false) {
                return array(false, false);
            }
            return array($latitude, $longitude);
        }

        /**
         * getCountry
         * 
         * Returns the country of the set IP.
         * 
         * @access  protected
         * @static
         * @return  false|string
         */
        protected static function getCountry()
        {
            $country = self::_lookup('country');
            if ($country === null) {
                $country = geoip_country_name_by_name(self::_getIP());
                if ($country === '') {
                    $country = false;
                }
                self::_cache('country', $country);
            }
            if ($country === false) {
                return false;
            }
            $encoded = utf8_encode($country);
            return $encoded;
        }

        /**
         * getCountryCode
         * 
         * Returns the country code for the IP.
         * 
         * @access  protected
         * @static
         * @param   integer $letters. (default: 3) number of letters the country
         *          code should be formatted to (eg. USA vs US)
         * @return  false|string
         */
        protected static function getCountryCode($letters = 3)
        {
            $key = 'countryCode.' . ($letters);
            $countryCode = self::_lookup($key);
            if ($countryCode === null) {
                if ($letters === 3) {
                    $countryCode = geoip_country_code3_by_name(self::_getIP());
                } else {
                    $countryCode = geoip_country_code_by_name(self::_getIP());
                }
                if ($countryCode === '') {
                    $countryCode = false;
                };
                self::_cache($key, $countryCode);
            }
            if ($countryCode === false) {
                return false;
            }
            $encoded = utf8_encode($countryCode);
            return $encoded;
        }

        /**
         * getFormatted
         * 
         * Returns a formatted string for UI presentation. Examples include:
         * - Toronto, Ontatio
         * - London, England
         * - Egypt
         * - Miami, Florida
         * 
         * @access  protected
         * @static
         * @return  string
         */
        protected static function getFormatted()
        {
            $pieces = array();
            $city = self::getCity();
            $region = self::getRegion();
            $country = self::getCountry();
            $countryCode = self::getCountryCode(2);
            $countryCode = strtoupper($countryCode);
            if ($countryCode === 'CA' || $countryCode === 'US') {
                if ($city !== false && $city !== '') {
                    array_push($pieces, $city);
                    if ($region !== false && $region !== '') {
                        array_push($pieces, $region);
                        return implode(', ', $pieces);
                    }
                    if ($country !== false && $country !== '') {
                        array_push($pieces, $country);
                        return implode(', ', $pieces);
                    }
                    array_push($pieces, $countryCode);
                    return implode(', ', $pieces);
                }
                if ($region !== false && $region !== '') {
                    array_push($pieces, $region);
                    if ($country !== false && $country !== '') {
                        array_push($pieces, $country);
                        return implode(', ', $pieces);
                    }
                    array_push($pieces, $countryCode);
                    return implode(', ', $pieces);
                }
                if ($country !== false && $country !== '') {
                    return $country;
                }
                return $countryCode;
            }
            if ($city !== false && $city !== '') {
                array_push($pieces, $city);
                if ($country !== false && $country !== '') {
                    array_push($pieces, $country);
                    return implode(', ', $pieces);
                }
                if ($countryCode !== false && $countryCode !== '') {
                    array_push($pieces, $countryCode);
                    return implode(', ', $pieces);
                }
                return $city;
            }
            if ($country !== false && $country !== '') {
                return $country;
            }
            return '';
        }

        /**
         * getLat
         * 
         * Returns the latitude coordinate for the IP.
         * 
         * @access  protected
         * @static
         * @return  false|float
         */
        protected static function getLat()
        {
            $latitude = self::_getDetail('latitude');
            if ($latitude === false) {
                return false;
            }
            $encoded = utf8_encode($latitude);
            return $encoded;
        }

        /**
         * getLong
         * 
         * Returns the latitude coordinate for the IP.
         * 
         * @access  protected
         * @static
         * @return  false|float
         */
        protected static function getLong()
        {
            $longitude = self::_getDetail('longitude');
            if ($longitude === false) {
                return false;
            }
            $encoded = utf8_encode($longitude);
            return $encoded;
        }

        /**
         * getPostalCode
         * 
         * Returns the postal/zip code, closest to the IP.
         * 
         * @access  protected
         * @static
         * @return  false|string
         */
        protected static function getPostalCode()
        {
            $postalCode = self::_getDetail('postal_code');
            if ($postalCode === false) {
                return false;
            }
            $encoded = utf8_encode($postalCode);
            return $encoded;
        }

        /**
         * getProvince
         * 
         * Alias of self::getRegion.
         * 
         * @access  protected
         * @static
         * @return  false|string
         */
        protected static function getProvince()
        {
            $region = self::getRegion();
            return $region;
        }

        /**
         * getRegion
         * 
         * Returns the region for the set IP, such as Quebec or California.
         * 
         * @note    will only act as a province/state lookup for certain regions
         *          (eg. Canada & US)
         * @access  protected
         * @static
         * @return  false|string
         */
        protected static function getRegion()
        {
            $region = self::_lookup('region');
            if ($region === null) {
                $region = geoip_region_name_by_code(
                    self::getCountryCode(2),
                    self::getRegionCode()
                );
                if ($region === '') {
                    $region = false;
                }
                self::_cache('region', $region);
            }
            if ($region === false) {
                return false;
            }
            $encoded = utf8_encode($region);
            return $encoded;
        }

        /**
         * getRegionCode
         * 
         * @access  protected
         * @static
         * @return  false|string
         */
        protected static function getRegionCode()
        {
            $region = self::_getDetail('region');
            if ($region === false) {
                return false;
            }
            $encoded = utf8_encode($region);
            return $encoded;
        }

        /**
         * getState
         * 
         * Alias of self::getRegion.
         * 
         * @access  protected
         * @static
         * @return  false|string
         */
        protected static function getState()
        {
            $region = self::getRegion();
            return $region;
        }

        /**
         * getTimezone
         * 
         * Returns the timezone for the set IP.
         * 
         * @access  protected
         * @static
         * @return  false|string
         */
        protected static function getTimezone()
        {
            $timezone = self::_lookup('timezone');
            if ($timezone === null) {
                $timezone = geoip_time_zone_by_country_and_region(
                    self::getCountryCode(2),
                    self::getRegionCode()
                );
                if ($timezone === '') {
                    $timezone = false;
                }
                self::_cache('timezone', $timezone);
            }
            if ($timezone === false) {
                return false;
            }
            $encoded = utf8_encode($timezone);
            return $encoded;
        }

        /**
         * getZip
         * 
         * Alias of self::getZipCode
         * 
         * @access  protected
         * @static
         * @return  false|string
         */
        protected static function getZip()
        {
            $zipCode = self::getZipCode();
            return $zipCode;
        }

        /**
         * getZipCode
         * 
         * Alias of self::getPostalCode.
         * 
         * @access  protected
         * @static
         * @return  false|string
         */
        protected static function getZipCode()
        {
            $postalCode = self::getPostalCode();
            return $postalCode;
        }

        /**
         * setIP
         * 
         * Sets the IP address that the geo-lookups should be based off of.
         * 
         * @access  public
         * @static
         * @param   string $ip
         * @return  void
         */
        public static function setIP($ip)
        {
            self::$_ip = $ip;
        }
    }
