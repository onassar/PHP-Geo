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
         * @var     null|string
         * @access  protected
         * @static
         */
        protected static $_ip = null;

        /**
         * _cache
         * 
         * IP lookup caches.
         * 
         * @var     array (default: array())
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
        public static function __callStatic(string $name, array $arguments)
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
        protected static function _cache(string $key, $value): void
        {
            $key = self::_getIP() . ' / ' . ($key);
            self::$_cache[$key] = $value;
        }

        /**
         * _format
         * 
         * @access  protected
         * @static
         * @param   mixed $value
         * @return  false|string
         */
        protected static function _format($value)
        {
            if ($value === false) {
                return false;
            }
            if ($value === null) {
                return false;
            }
            $encoded = utf8_encode($value);
            return $encoded;
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
         * @return  false|array raw 'geo' details for the request
         */
        protected static function _getRecord()
        {
            $record = self::_lookup('record');
            if ($record === null) {
                $ip = self::_getIP();
                $record = geoip_record_by_name($ip);
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
        protected static function _lookup(string $key)
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
            $formatted = self::_format($areaCode);
            return $formatted;
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
            $formatted = self::_format($city);
            return $formatted;
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
                $ip = self::_getIP();
                $continentCode = geoip_continent_code_by_name($ip);
                if ($continentCode === '') {
                    $continentCode = false;
                }
                self::_cache($key, $continentCode);
            }
            $formatted = self::_format($continentCode);
            return $formatted;
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
                $ip = self::_getIP();
                $country = geoip_country_name_by_name($ip);
                if ($country === '') {
                    $country = false;
                }
                self::_cache('country', $country);
            }
            $formatted = self::_format($country);
            return $formatted;
        }

        /**
         * getCountryCode
         * 
         * Returns the country code for the IP.
         * 
         * @access  protected
         * @static
         * @param   int $letters. (default: 3) number of letters the country
         *          code should be formatted to (eg. USA vs US)
         * @return  false|string
         */
        protected static function getCountryCode(int $letters = 3)
        {
            $key = 'countryCode.' . ($letters);
            $countryCode = self::_lookup($key);
            if ($countryCode === null) {
                $ip = self::_getIP();
                if ($letters === 3) {
                    $countryCode = geoip_country_code3_by_name($ip);
                } else {
                    $countryCode = geoip_country_code_by_name($ip);
                }
                if ($countryCode === '') {
                    $countryCode = false;
                };
                self::_cache($key, $countryCode);
            }
            $formatted = self::_format($countryCode);
            return $formatted;
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
            $formatted = self::_format($latitude);
            return $formatted;
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
            $formatted = self::_format($longitude);
            return $formatted;
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
            $formatted = self::_format($postalCode);
            return $formatted;
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
            $formatted = self::_format($region);
            return $formatted;
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
            $formatted = self::_format($region);
            return $formatted;
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
            $formatted = self::_format($timezone);
            return $formatted;
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
        public static function setIP(string $ip)
        {
            self::$_ip = $ip;
        }
    }
