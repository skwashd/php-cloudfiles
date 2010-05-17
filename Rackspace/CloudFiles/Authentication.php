<?php
/**
 * CloudFiles Authentication
 *
 * PHP Version 5
 *
 *  Copyright (C) 2008 Rackspace US, Inc.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * Except as contained in this notice, the name of Rackspace US, Inc. shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Rackspace US, Inc. 
 *
 * @category   Rackspace
 * @package    Rackspace_CloudFiles
 * @author     Eric "EJ" Johnson <ej@racklabs.com>
 * @author     Dave Hall <me@davehall.com.au>
 * @copyright  Copyright (c) 2008, Rackspace US, Inc.
 * @copyright  Copyright (c) 2010, Dave Hall Consulting http://davehall.com.au
 * @license    http://opensource.org/licenses/mit-license.php
 * @link       http://www.rackspacecloud.com/cloud_hosting_products/servers/api
 */

/**
 * CloudFiles Authentication
 * 
 * Class for handling Cloud Files Authentication, call it's {@link authenticate()}
 * method to obtain authorized service urls and an authentication token.
 *
 * Example:
 * <code>
 * # Create the authentication instance
 * #
 * $auth = new Rackspace_CloudFiles_Authentication("username", "api_key");
 *
 * # NOTE: Some versions of cURL include an outdated certificate authority (CA)
 * #       file.  This API ships with a newer version obtained directly from
 * #       cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
 * #       call the CF_Authentication instance's 'ssl_use_cabundle()' method.
 * #
 * # $auth->ssl_use_cabundle(); # bypass cURL's old CA bundle
 *
 * # Perform authentication request
 * #
 * $auth->authenticate();
 * </code>
 *
 * @category   Rackspace
 * @package    Rackspace_CloudFiles
 * @author     Eric "EJ" Johnson <ej@racklabs.com>
 * @author     Dave Hall <me@davehall.com.au>
 * @copyright  Copyright (c) 2008, Rackspace US, Inc.
 * @copyright  Copyright (c) 2010, Dave Hall Consulting http://davehall.com.au
 * @license    http://opensource.org/licenses/mit-license.php
 * @link       http://www.rackspacecloud.com/cloud_hosting_products/servers/api
 */
class Rackspace_CloudFiles_Authentication
{
    public $dbug;
    public $username;
    public $api_key;
    public $auth_host;
    public $account;

    /**
     * Instance variables that are set after successful authentication
     */
    public $storage_url;
    public $cdnm_url;
    public $auth_token;

    /**
     * Class constructor (PHP 5 syntax)
     *
     * @param string $username Mosso username
     * @param string $api_key Mosso API Access Key
     * @param string $account <b>Deprecated</b> <i>Account name</i>
     * @param string $auth_host <b>Deprecated</b> <i>Authentication service URI</i>
     */
    public function __construct($username=NULL, $api_key=NULL, $account=NULL, $auth_host=NULL)
    {

        $this->dbug = False;
        $this->username = $username;
        $this->api_key = $api_key;
        $this->account_name = $account;
        $this->auth_host = $auth_host;

        $this->storage_url = NULL;
        $this->cdnm_url = NULL;
        $this->auth_token = NULL;

        $this->cfs_http = new Rackspace_CloudFiles_Http(Rackspace_CloudFiles::DEFAULT_API_VERSION);
    }

    /**
     * Use the Certificate Authority bundle included with this API
     *
     * Most versions of PHP with cURL support include an outdated Certificate
     * Authority (CA) bundle (the file that lists all valid certificate
     * signing authorities).  The SSL certificates used by the Cloud Files
     * storage system are perfectly valid but have been created/signed by
     * a CA not listed in these outdated cURL distributions.
     *
     * As a work-around, we've included an updated CA bundle obtained
     * directly from cURL's web site (http://curl.haxx.se).  You can direct
     * the API to use this CA bundle by calling this method prior to making
     * any remote calls.  The best place to use this method is right after
     * the CF_Authentication instance has been instantiated.
     *
     * You can specify your own CA bundle by passing in the full pathname
     * to the bundle.  You can use the included CA bundle by leaving the
     * argument blank.
     *
     * @param string $path Specify path to CA bundle (default to included)
     */
    public function ssl_use_cabundle($path=NULL)
    {
        $this->cfs_http->ssl_use_cabundle($path);
    }

    /**
     * Attempt to validate Username/API Access Key
     *
     * Attempts to validate credentials with the authentication service.  It
     * either returns <kbd>True</kbd> or throws an Exception.  Accepts a single
     * (optional) argument for the storage system API version.
     *
     * Example:
     * <code>
     * # Create the authentication instance
     * #
     * $auth = new CF_Authentication("username", "api_key");
     *
     * # Perform authentication request
     * #
     * $auth->authenticate();
     * </code>
     *
     * @param string $version API version for Auth service (optional)
     * @return boolean <kbd>True</kbd> if successfully authenticated
     * @throws AuthenticationException invalid credentials
     * @throws InvalidResponseException invalid response
     */
    public function authenticate($version=Rackspace_CloudFiles::DEFAULT_API_VERSION)
    {
        list($status,$reason,$surl,$curl,$atoken) = 
                $this->cfs_http->authenticate($this->username, $this->api_key,
                $this->account_name, $this->auth_host);

        if ($status == 401) {
            throw new Rackspace_CloudFiles_AuthenticationException("Invalid username or access key.");
        }
        if ($status != 204) {
            throw new Rackspace_CloudFiles_InvalidResponseException(
                "Unexpected response (".$status."): ".$reason);
        }

        if (!($surl || $curl) || !$atoken) {
            throw new Rackspace_CloudFiles_InvalidResponseException(
                "Expected headers missing from auth service.");
        }
        $this->storage_url = $surl;
        $this->cdnm_url = $curl;
        $this->auth_token = $atoken;
        return True;
    }
        /**
         * Use Cached Token and Storage URL's rather then grabbing from the Auth System
         *
         * Example:
          * <code>
         * #Create an Auth instance
         * $auth = new CF_Authentication();
         * #Pass Cached URL's and Token as Args
         * $auth->load_cached_credentials("auth_token", "storage_url", "cdn_management_url");
         * </code>
         * 
         * @param string $auth_token A Cloud Files Auth Token (Required)
         * @param string $storage_url The Cloud Files Storage URL (Required)
         * @param string $cdnm_url CDN Management URL (Required)
         * @return boolean <kbd>True</kbd> if successful 
         * @throws Rackspace_CloudFiles_SyntaxException If any of the Required Arguments are missing
         */
        public function load_cached_credentials($auth_token, $storage_url, $cdnm_url)
    {
        if(!$storage_url || !$cdnm_url)
        {
                throw new Rackspace_CloudFiles_SyntaxException("Missing Required Interface URL's!");
                return False;
        }
        if(!$auth_token)
        {
                throw new Rackspace_CloudFiles_SyntaxException("Missing Auth Token!");
                return False;
        }

        $this->storage_url = $storage_url;
        $this->cdnm_url    = $cdnm_url;
        $this->auth_token  = $auth_token;
        return True;
    }
        /**
         * Grab Cloud Files info to be Cached for later use with the load_cached_credentials method.
         *
         * Example:
         * <code>
         * #Create an Auth instance
         * $auth = new CF_Authentication("UserName","API_Key");
         * $auth->authenticate();
         * $array = $auth->export_credentials();
         * </code>
         * 
         * @return array of url's and an auth token.
         */
    public function export_credentials()
    {
        $arr = array();
        $arr['storage_url'] = $this->storage_url;
        $arr['cdnm_url']    = $this->cdnm_url;
        $arr['auth_token']  = $this->auth_token;

        return $arr;
    }


    /**
     * Make sure the CF_Authentication instance has authenticated.
     *
     * Ensures that the instance variables necessary to communicate with
     * Cloud Files have been set from a previous authenticate() call.
     *
     * @return boolean <kbd>True</kbd> if successfully authenticated
     */
    public function authenticated()
    {
        if (!($this->storage_url || $this->cdnm_url) || !$this->auth_token) {
            return False;
        }
        return True;
    }

    /**
     * Toggle debugging - set cURL verbose flag
     */
    public function setDebug($bool)
    {
        $this->dbug = $bool;
        $this->cfs_http->setDebug($bool);
    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */