<?php

    // dependecy checks
    if (!in_array('geoip', get_loaded_extensions())) {
        throw new Exception('GeoIP extension needs to be installed.');
    }

    /**
     * Geo
     * 
     * Wrapper for GeoIP lookup service (using binary file).
     * 
     * @author   Oliver Nassar <onassar@gmail.com
     * @abstract
     * @notes    The GeoIP PHP extension handles errors in a bizarre way (see:
     *           http://pecl.php.net/bugs/bug.php?id=22691). While the functions
     *           do have return values, notices are thrown if the IP being
     *           checked cannot be found. Thus, the magic method __callStatic is
     *           used as a wrapper for all getters. Therefore looking at the
     *           class signature, getters are set as protected. All classes that
     *           do *not* lead with an underscore are therefore safe to access,
     *           regardless of their defined scope.
     * @example
     * <code>
     *     // dependency
     *     require_once APP . '/vendors/PHP-Geo/Geo.class.php';
     * 
     *     // display city and country
     *     echo Geo::getCity() . ', ' . Geo::getCountry();
     *     exit(0);
     * </code>
     */
    abstract class Geo
    {
        /**
         * _ip
         * 
         * @var    string
         * @access protected
         * @static
         */
        protected static $_ip;

        /**
         * _record
         * 
         * Raw record details
         * 
         * @var    array
         * @access protected
         * @static
         */
        protected static $_record;

        /**
         * __callStatic
         * 
         * @access public
         * @static
         * @param  string $name
         * @param  array $arguments
         * @return void
         */
        public static function __callStatic($name, $arguments)
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
            $response = call_user_func_array(array('self', $name), $arguments);
            restore_error_handler();
            return $response;
        }

        /**
         * _getDetail
         * 
         * Accessor method for raw details of geo-lookup.
         * 
         * @access protected
         * @static
         * @param  string $name raw-record detail to retrieve
         * @return mixed
         */
        protected static function _getDetail($name)
        {
            $record = self::_getRecord();
            return $record[$name];
        }

        /**
         * _getIP
         * 
         * Returns the IP address that the Geo lookup should be based on.
         * Defaults to the remote address for the request.
         * 
         * @access protected
         * @static
         * @return string
         */
        protected static function _getIP()
        {
            // ip address manually set
            if (!is_null(self::$_ip)) {
                return self::$_ip;
            }

            // load balancer check
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
            // native check
            if (isset($_SERVER['REMOTE_ADDR'])) {
                return $_SERVER['REMOTE_ADDR'];
            }
            // couldn't be found
            return '(unknown)';
        }

        /**
         * _getRecord
         * 
         * Returns raw GeoIP record.
         * 
         * @access protected
         * @static
         * @return array raw 'geo' details for the request
         */
        protected static function _getRecord()
        {
            if (is_null(self::$_record)) {
                self::$_record = geoip_record_by_name(self::_getIP());
            }
            return self::$_record;
        }

        /**
         * getAreaCode
         * 
         * @access protected
         * @static
         * @return integer
         */
        protected static function getAreaCode()
        {
            return self::_getDetail('area_code');
        }

        /**
         * getCity
         * 
         * @access protected
         * @static
         * @return string
         */
        protected static function getCity()
        {
            return self::_getDetail('city');
        }

        /**
         * getContinentCode
         * 
         * Continent shortform code of set IP.
         * 
         * @access protected
         * @static
         * @return string
         */
        protected static function getContinentCode()
        {
            return geoip_continent_code_by_name(self::_getIP());
        }

        /**
         * getCoordinates
         * 
         * Returns latitude/longitude array of coordinates.
         * 
         * @access protected
         * @static
         * @return array
         */
        protected static function getCoordinates()
        {
            return array(
                self::getLat(),
                self::getLong()
            );
        }

        /**
         * getCountry
         * 
         * Returns the country of the set IP.
         * 
         * @access protected
         * @static
         * @return string
         */
        protected static function getCountry()
        {
            return geoip_country_name_by_name(self::_getIP());
        }

        /**
         * getCountryCode
         * 
         * Returns the country code for the IP.
         * 
         * @access protected
         * @static
         * @param  integer $letters. (default: 3) number of letters the country
         *         code should be formatted to (eg. USA vs US)
         * @return string
         */
        protected static function getCountryCode($letters = 3)
        {
            if ($letters === 3) {
                return geoip_country_code3_by_name(self::_getIP());
            }
            return geoip_country_code_by_name(self::_getIP());
        }

        /**
         * getLat
         * 
         * Returns the latitude coordinate for the IP.
         * 
         * @access protected
         * @static
         * @return float
         */
        protected static function getLat()
        {
            return self::_getDetail('latitude');
        }

        /**
         * getLong
         * 
         * Returns the latitude coordinate for the IP.
         * 
         * @access protected
         * @static
         * @return float
         */
        protected static function getLong()
        {
            return self::_getDetail('longitude');
        }

        /**
         * getPostalCode
         * 
         * Returns the postal/zip code, closest to the IP.
         * 
         * @access protected
         * @static
         * @return string
         */
        protected static function getPostalCode()
        {
            return self::_getDetail('postal_code');
        }

        /**
         * getProvince
         * 
         * Alias of self::getRegion.
         * 
         * @access protected
         * @static
         * @return string returns the province/region for the set IP
         */
        protected static function getProvince()
        {
            return self::getRegion();
        }

        /**
         * getRegion
         * 
         * Returns the region for the set IP, such as Quebec or California.
         * 
         * @notes  will only act as a province/state lookup for certain regions
         *         (eg. Canada & US)
         * @access protected
         * @static
         * @return string
         */
        protected static function getRegion()
        {
            return geoip_region_name_by_code(
                self::getCountryCode(2),
                self::_getDetail('region')
            );
        }

        /**
         * getState
         * 
         * Alias of self::getRegion.
         * 
         * @access protected
         * @static
         * @return string
         */
        protected static function getState()
        {
            return self::getRegion();
        }

        /**
         * getTimezone
         * 
         * Returns the timezone for the set IP.
         * 
         * @access protected
         * @static
         * @return string
         */
        protected static function getTimezone()
        {
            return geoip_time_zone_by_country_and_region(
                self::getCountryCode(2),
                self::_getDetail('region')
            );
        }

        /**
         * getZip
         * 
         * Alias of self::getZipCode
         * 
         * @access protected
         * @static
         * @return string zip code of the originating request
         */
        protected static function getZip()
        {
            return self::getZipCode();
        }

        /**
         * getZipCode
         * 
         * Returns the zip code of the set IP.
         * 
         * @access protected
         * @static
         * @return string
         */
        protected static function getZipCode()
        {
            return self::getPostalCode();
        }

        /**
         * setIP
         * 
         * Sets the IP address that the geo-lookups should be based off of.
         * 
         * @access public
         * @static
         * @param  string $ip
         * @return void
         */
        public static function setIP($ip)
        {
            self::$_ip = $ip;
        }
    }
