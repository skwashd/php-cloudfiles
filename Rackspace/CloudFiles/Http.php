<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * CloudFiles HTTP Client
 * 
 * This is an HTTP client class for Cloud Files.  It uses PHP's cURL module
 * to handle the actual HTTP request/response.  This is NOT a generic HTTP
 * client class and is only used to abstract out the HTTP communication for
 * the PHP Cloud Files API.
 *
 * This module was designed to re-use existing HTTP(S) connections between
 * subsequent operations.  For example, performing multiple PUT operations
 * will re-use the same connection.
 *
 * This modules also provides support for streaming content into and out
 * of Cloud Files.  The majority (all?) of the PHP HTTP client modules expect
 * to read the server's response into a string variable.  This will not work
 * with large files without killing your server.  Methods like,
 * get_object_to_stream() and put_object() take an open filehandle
 * argument for streaming data out of or into Cloud Files.
 *
 * PHP Version 5
 *
 * Copyright (C) 2008 Rackspace US, Inc.
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
 * Current version of the package
 */
define("PHP_CF_VERSION", "1.7.0");
/**
 * User agent to supply
 */
define("USER_AGENT", sprintf("PHP-CloudFiles/%s", PHP_CF_VERSION));
/**
 * Account container count HTTP header
 */
define("ACCOUNT_CONTAINER_COUNT", "X-Account-Container-Count");
/**
 * Bytes used HTTP header
 */
define("ACCOUNT_BYTES_USED", "X-Account-Bytes-Used");
/**
 * Container object count HTTP header
 */
define("CONTAINER_OBJ_COUNT", "X-Container-Object-Count");
/**
 * Container bytes used HTTP header
 */
define("CONTAINER_BYTES_USED", "X-Container-Bytes-Used");
/**
 * Metadata HTTP header
 */
define("METADATA_HEADER", "X-Object-Meta-");
/**
 * CDN URI HTTP header
 */
define("CDN_URI", "X-CDN-URI");
/**
 * CDN enabled HTTP header
 */
define("CDN_ENABLED", "X-CDN-Enabled");
/**
 * CDN log retention enabled HTTP header
 */
define("CDN_LOG_RETENTION", "X-Log-Retention");
/**
 * CDN ACL user agent HTTP header
 */
define("CDN_ACL_USER_AGENT", "X-User-Agent-ACL");
/**
 * CDN ACL referrer HTTP header
 */
define("CDN_ACL_REFERRER", "X-Referrer-ACL");
/**
 * CDN TTL HTTP header
 */
define("CDN_TTL", "X-TTL");
/**
 * CDN Management URI HTTP header
 */
define("CDNM_URL", "X-CDN-Management-Url");
/**
 * CloudFiles storage URI HTTP header
 */
define("STORAGE_URL", "X-Storage-Url");
/**
 * Authentication session token HTTP header
 */
define("AUTH_TOKEN", "X-Auth-Token");
/**
 * Authentication user HTTP header
 */
define("AUTH_USER_HEADER", "X-Auth-User");
/**
 * Authentication account key HTTP header
 */
define("AUTH_KEY_HEADER", "X-Auth-Key");
/**
 * Account username HTTP header (legacy)
 * @deprecated
 */
define("AUTH_USER_HEADER_LEGACY", "X-Storage-User");
/**
 * Account password HTTP header (legacy)
 * @deprecated
 */
define("AUTH_KEY_HEADER_LEGACY", "X-Storage-Pass");
/**
 * Authentication session token HTTP header (legacy)
 * @deprecated
 */
define("AUTH_TOKEN_LEGACY", "X-Storage-Token");
/**
 * CloudFiles HTTP Client
 * 
 * This is an HTTP client class for Cloud Files.  It uses PHP's cURL module
 * to handle the actual HTTP request/response.  This is NOT a generic HTTP
 * client class and is only used to abstract out the HTTP communication for
 * the PHP Cloud Files API.
 *
 * This class should not be used directly.  It's only purpose is to abstract
 * out the HTTP communication from the main API.
 *
 * This module was designed to re-use existing HTTP(S) connections between
 * subsequent operations.  For example, performing multiple PUT operations
 * will re-use the same connection.
 *
 * This modules also provides support for streaming content into and out
 * of Cloud Files.  The majority (all?) of the PHP HTTP client modules expect
 * to read the server's response into a string variable.  This will not work
 * with large files without killing your server.  Methods like,
 * get_object_to_stream() and put_object() take an open filehandle
 * argument for streaming data out of or into Cloud Files.
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
class Rackspace_CloudFiles_Http
{
    protected $error_str;
    protected $dbug;
    protected $cabundle_path;
    protected $api_version;
    # Authentication instance variables
    #
    protected $storage_url;
    protected $cdnm_url;
    protected $auth_token;
    # Request/response variables
    #
    protected $response_status;
    protected $response_reason;
    protected $connections;
    # Variables used for content/header callbacks
    #
    protected $_user_read_progress_callback_func;
    protected $_user_write_progress_callback_func;
    protected $_write_callback_type;
    protected $_text_list;
    protected $_account_container_count;
    protected $_account_bytes_used;
    protected $_container_object_count;
    protected $_container_bytes_used;
    protected $_obj_etag;
    protected $_obj_last_modified;
    protected $_obj_content_type;
    protected $_obj_content_length;
    protected $_obj_metadata;
    protected $_obj_write_resource;
    protected $_obj_write_string;
    protected $_cdn_enabled;
    protected $_cdn_uri;
    protected $_cdn_ttl;
    protected $_cdn_log_retention;
    protected $_cdn_acl_user_agent;
    protected $_cdn_acl_referrer;

    public function __construct ($api_version)
    {
        $this->dbug = False;
        $this->cabundle_path = NULL;
        $this->api_version = $api_version;
        $this->error_str = NULL;
        $this->storage_url = NULL;
        $this->cdnm_url = NULL;
        $this->auth_token = NULL;
        $this->response_status = NULL;
        $this->response_reason = NULL;
        
        /*
         * Curl connections array - since there is no way to "re-set" the
         * connection paramaters for a cURL handle, we keep an array of
         * the unique use-cases and funnel all of those same type
         * requests through the appropriate curl connection.
         */
        $this->connections = array(
	        "GET_CALL" => NULL,    // GET objects/containers/lists
			"PUT_OBJ" => NULL,     // PUT object
	        "HEAD" => NULL,        // HEAD requests
	        "PUT_CONT" => NULL,    // PUT container
	        "DEL_POST" => NULL     // DELETE containers/objects, POST objects
        );

        $this->_user_read_progress_callback_func = NULL;
        $this->_user_write_progress_callback_func = NULL;
        $this->_write_callback_type = NULL;
        $this->_text_list = array();
        $this->_return_list = NULL;
        $this->_account_container_count = 0;
        $this->_account_bytes_used = 0;
        $this->_container_object_count = 0;
        $this->_container_bytes_used = 0;
        $this->_obj_write_resource = NULL;
        $this->_obj_write_string = "";
        $this->_obj_etag = NULL;
        $this->_obj_last_modified = NULL;
        $this->_obj_content_type = NULL;
        $this->_obj_content_length = NULL;
        $this->_obj_metadata = array();
        $this->_cdn_enabled = NULL;
        $this->_cdn_uri = NULL;
        $this->_cdn_ttl = NULL;
        $this->_cdn_log_retention = NULL;
        $this->_cdn_acl_user_agent = NULL;
        $this->_cdn_acl_referrer = NULL;
        
        /*
         * The OS list with a PHP without an updated CA File for CURL to
         * connect to SSL Websites. It is the first 3 letters of the PHP_OS
         * variable.
         */
        $OS_CAFILE_NONUPDATED = array("win");
        if (in_array((strtolower(substr(PHP_OS, 0, 3))), $OS_CAFILE_NONUPDATED)) {
            $this->ssl_use_cabundle();
        }
    }

    public function ssl_use_cabundle ($path = NULL)
    {
        if ($path) {
            $this->cabundle_path = $path;
        } else {
            $this->cabundle_path = dirname(__FILE__) . "/share/cacert.pem";
        }

        if (! file_exists($this->cabundle_path)) {
            throw new Rackspace_CloudFiles_IOException("Could not use CA bundle: {$this->cabundle_path}");
        }
    }

    # Uses separate cURL connection to authenticate
    #
    public function authenticate ($user, $pass, $acct = NULL, $host = NULL)
    {
        $path = array();
        if (isset($acct) || isset($host)) {
            $headers = array(
                sprintf("%s: %s", AUTH_USER_HEADER_LEGACY, $user), 
                sprintf("%s: %s", AUTH_KEY_HEADER_LEGACY, $pass)
            );
            
            $path[] = $host;
            $path[] = rawurlencode(sprintf("v%d",$this->api_version));
            $path[] = rawurlencode($acct);
        } else {
            $headers = array(
                sprintf("%s: %s", AUTH_USER_HEADER, $user),
                sprintf("%s: %s", AUTH_KEY_HEADER, $pass)
            );
            $path[] = "https://auth.api.rackspacecloud.com";
        }

        $path[] = "v1.0";
        $url = implode("/", $path);
        $curl_ch = curl_init();
        if (! is_null($this->cabundle_path)) {
            curl_setopt($curl_ch, CURLOPT_SSL_VERIFYPEER, True);
            curl_setopt($curl_ch, CURLOPT_CAINFO, $this->cabundle_path);
        }
        
        curl_setopt($curl_ch, CURLOPT_VERBOSE, $this->dbug);
        curl_setopt($curl_ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl_ch, CURLOPT_MAXREDIRS, 4);
        curl_setopt($curl_ch, CURLOPT_HEADER, 0);
        curl_setopt($curl_ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl_ch, CURLOPT_USERAGENT, USER_AGENT);
        curl_setopt($curl_ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl_ch, CURLOPT_HEADERFUNCTION, array(&$this, 'auth_hdr_cb'));
        curl_setopt($curl_ch, CURLOPT_URL, $url);
        curl_exec($curl_ch);
        curl_close($curl_ch);
        
        return array(
            $this->response_status, 
            $this->response_reason, 
            $this->storage_url, 
            $this->cdnm_url, 
            $this->auth_token
        );
    }

    # (CDN) GET /v1/Account
    #
    public function list_cdn_containers ()
    {
        $container_name = NULL;
        $conn_type = "GET_CALL";
        $url_path = $this->make_path("CDN", $container_name);
        $this->_write_callback_type = "TEXT_LIST";
        $return_code = $this->send_request($conn_type, $url_path);

        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            array(0, $this->error_str, array());
        }

        switch ( $return_code ) {
            case 200:
                $this->create_array();
                return array($return_code, $this->response_reason, $this->_text_list);

            case 204:
                return array($return_code, "Account has no CDN enabled Containers.", array());
                
            case 401:
                return array($return_code, "Unauthorized", array());
                
            case 404:
                return array($return_code, "Account not found.", array());
                
            default:
		        $this->error_str = "Unexpected HTTP response: {$this->response_reason}";
		        return array($return_code,  $this->error_str, array());
        }
    }

    # (CDN) POST /v1/Account/Container
    #
    public function update_cdn_container (
    $container_name, $ttl = 86400, $cdn_log_retention = False, 
    $cdn_acl_user_agent = "", $cdn_acl_referrer)
    {
        if ($container_name == "")
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        if ($container_name != "0" and ! isset(
        $container_name))
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        $url_path = $this->make_path("CDN", 
        $container_name);
        $hdrs = array(CDN_ENABLED => "True", 
        CDN_TTL => $ttl, 
        CDN_LOG_RETENTION => $cdn_log_retention ? "True" : "False", 
        CDN_ACL_USER_AGENT => $cdn_acl_user_agent, 
        CDN_ACL_REFERRER => $cdn_acl_referrer);
        $return_code = $this->send_request(
        "DEL_POST", $url_path, $hdrs, "POST");
        if ($return_code == 401) {
            $this->error_str = "Unauthorized";
            return array(
            $return_code, 
            $this->error_str, 
            NULL);
        }
        if ($return_code == 404) {
            $this->error_str = "Container not found.";
            return array(
            $return_code, 
            $this->error_str, 
            NULL);
        }
        if ($return_code != 202) {
            $this->error_str = "Unexpected HTTP response: " .
             $this->response_reason;
            return array(
            $return_code, 
            $this->error_str, 
            NULL);
        }
        return array($return_code, "Accepted", 
        $this->_cdn_uri);
    }
    # (CDN) PUT /v1/Account/Container
    #
    public function add_cdn_container (
    $container_name, $ttl = 86400)
    {
        if ($container_name == "")
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        if ($container_name != "0" and ! isset(
        $container_name))
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        $url_path = $this->make_path("CDN", 
        $container_name);
        $hdrs = array(CDN_ENABLED => "True", 
        CDN_TTL => $ttl);
        $return_code = $this->send_request(
        "PUT_CONT", $url_path, $hdrs);
        if ($return_code == 401) {
            $this->error_str = "Unauthorized";
            return array(
            $return_code, 
            $this->response_reason, 
            False);
        }
        if (! in_array($return_code, 
        array(201, 202))) {
            $this->error_str = "Unexpected HTTP response: " .
             $this->response_reason;
            return array(
            $return_code, 
            $this->response_reason, 
            False);
        }
        return array($return_code, 
        $this->response_reason, $this->_cdn_uri);
    }
    # (CDN) POST /v1/Account/Container
    #
    public function remove_cdn_container (
    $container_name)
    {
        if ($container_name == "")
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        if ($container_name != "0" and ! isset(
        $container_name))
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        $url_path = $this->make_path("CDN", 
        $container_name);
        $hdrs = array(CDN_ENABLED => "False");
        $return_code = $this->send_request(
        "DEL_POST", $url_path, $hdrs, "POST");
        if ($return_code == 401) {
            $this->error_str = "Unauthorized";
            return array(
            $return_code, 
            $this->error_str);
        }
        if ($return_code == 404) {
            $this->error_str = "Container not found.";
            return array(
            $return_code, 
            $this->error_str);
        }
        if ($return_code != 202) {
            $this->error_str = "Unexpected HTTP response: " .
             $this->response_reason;
            return array(
            $return_code, 
            $this->error_str);
        }
        return array($return_code, "Accepted");
    }
    # (CDN) HEAD /v1/Account
    #
    public function head_cdn_container (
    $container_name)
    {
        if ($container_name == "")
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        if ($container_name != "0" and ! isset(
        $container_name))
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        $conn_type = "HEAD";
        $url_path = $this->make_path("CDN", 
        $container_name);
        $return_code = $this->send_request(
        $conn_type, $url_path);
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return array(0, 
            $this->error_str, 
            NULL, NULL, NULL, 
            NULL, NULL, NULL);
        }
        if ($return_code == 401) {
            return array(
            $return_code, 
            "Unauthorized", NULL, 
            NULL, NULL, NULL, 
            NULL, NULL);
        }
        if ($return_code == 404) {
            return array(
            $return_code, 
            "Account not found.", 
            NULL, NULL, NULL, 
            NULL, NULL, NULL);
        }
        if ($return_code == 204) {
            return array(
            $return_code, 
            $this->response_reason, 
            $this->_cdn_enabled, 
            $this->_cdn_uri, 
            $this->_cdn_ttl, 
            $this->_cdn_log_retention, 
            $this->_cdn_acl_user_agent, 
            $this->_cdn_acl_referrer);
        }
        return array($return_code, 
        $this->response_reason, NULL, NULL, NULL, 
        $this->_cdn_log_retention, 
        $this->_cdn_acl_user_agent, 
        $this->_cdn_acl_referrer);
    }
    # GET /v1/Account
    #
    public function list_containers (
    $limit = 0, $marker = NULL)
    {
        $conn_type = "GET_CALL";
        $url_path = $this->make_path();
        $limit = intval($limit);
        $params = array();
        if ($limit > 0) {
            $params[] = "limit=$limit";
        }
        if ($marker) {
            $params[] = "marker=" .
             rawurlencode(
            $marker);
        }
        if (! empty($params)) {
            $url_path .= "?" . implode(
            "&", $params);
        }
        $this->_write_callback_type = "TEXT_LIST";
        $return_code = $this->send_request(
        $conn_type, $url_path);
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return array(0, 
            $this->error_str, 
            array());
        }
        if ($return_code == 204) {
            return array(
            $return_code, 
            "Account has no containers.", 
            array());
        }
        if ($return_code == 404) {
            $this->error_str = "Invalid account name for authentication token.";
            return array(
            $return_code, 
            $this->error_str, 
            array());
        }
        if ($return_code == 200) {
            $this->create_array();
            return array(
            $return_code, 
            $this->response_reason, 
            $this->_text_list);
        }
        $this->error_str = "Unexpected HTTP response: " .
         $this->response_reason;
        return array($return_code, 
        $this->error_str, array());
    }
    # GET /v1/Account?format=json
    #
    public function list_containers_info (
    $limit = 0, $marker = NULL)
    {
        $conn_type = "GET_CALL";
        $url_path = $this->make_path() . "?format=json";
        $limit = intval($limit);
        $params = array();
        if ($limit > 0) {
            $params[] = "limit=$limit";
        }
        if ($marker) {
            $params[] = "marker=" .
             rawurlencode(
            $marker);
        }
        if (! empty($params)) {
            $url_path .= "&" . implode(
            "&", $params);
        }
        $this->_write_callback_type = "OBJECT_STRING";
        $return_code = $this->send_request(
        $conn_type, $url_path);
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return array(0, 
            $this->error_str, 
            array());
        }
        if ($return_code == 204) {
            return array(
            $return_code, 
            "Account has no containers.", 
            array());
        }
        if ($return_code == 404) {
            $this->error_str = "Invalid account name for authentication token.";
            return array(
            $return_code, 
            $this->error_str, 
            array());
        }
        if ($return_code == 200) {
            $json_body = json_decode(
            $this->_obj_write_string, 
            True);
            return array(
            $return_code, 
            $this->response_reason, 
            $json_body);
        }
        $this->error_str = "Unexpected HTTP response: " .
         $this->response_reason;
        return array($return_code, 
        $this->error_str, array());
    }
    # HEAD /v1/Account
    #
    public function head_account ()
    {
        $conn_type = "HEAD";
        $url_path = $this->make_path();
        $return_code = $this->send_request(
        $conn_type, $url_path);
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            array(0, 
            $this->error_str, 0, 
            0);
        }
        if ($return_code == 404) {
            return array(
            $return_code, 
            "Account not found.", 
            0, 0);
        }
        if ($return_code == 204) {
            return array(
            $return_code, 
            $this->response_reason, 
            $this->_account_container_count, 
            $this->_account_bytes_used);
        }
        return array($return_code, 
        $this->response_reason, 0, 0);
    }
    # PUT /v1/Account/Container
    #
    public function create_container (
    $container_name)
    {
        if ($container_name == "")
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        if ($container_name != "0" and ! isset(
        $container_name))
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        $url_path = $this->make_path("STORAGE", 
        $container_name);
        $return_code = $this->send_request(
        "PUT_CONT", $url_path);
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return False;
        }
        return $return_code;
    }
    # DELETE /v1/Account/Container
    #
    public function delete_container (
    $container_name)
    {
        if ($container_name == "")
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        if ($container_name != "0" and ! isset(
        $container_name))
            throw new Rackspace_CloudFiles_SyntaxException(
            "Container name not set.");
        $url_path = $this->make_path("STORAGE", 
        $container_name);
        $return_code = $this->send_request(
        "DEL_POST", $url_path, array(), "DELETE");
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
        }
        if ($return_code == 409) {
            $this->error_str = "Container must be empty prior to removing it.";
        }
        if ($return_code == 404) {
            $this->error_str = "Specified container did not exist to delete.";
        }
        if ($return_code != 204) {
            $this->error_str = "Unexpected HTTP return code: $return_code.";
        }
        return $return_code;
    }
    # GET /v1/Account/Container
    #
    public function list_objects (
    $cname, $limit = 0, $marker = NULL, $prefix = NULL, $path = NULL)
    {
        if (! $cname) {
            $this->error_str = "Container name not set.";
            return array(0, 
            $this->error_str, 
            array());
        }
        $url_path = $this->make_path("STORAGE", 
        $cname);
        $limit = intval($limit);
        $params = array();
        if ($limit > 0) {
            $params[] = "limit=$limit";
        }
        if ($marker) {
            $params[] = "marker=" .
             rawurlencode(
            $marker);
        }
        if ($prefix) {
            $params[] = "prefix=" .
             rawurlencode(
            $prefix);
        }
        if ($path) {
            $params[] = "path=" .
             rawurlencode($path);
        }
        if (! empty($params)) {
            $url_path .= "?" . implode(
            "&", $params);
        }
        $conn_type = "GET_CALL";
        $this->_write_callback_type = "TEXT_LIST";
        $return_code = $this->send_request(
        $conn_type, $url_path);
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return array(0, 
            $this->error_str, 
            array());
        }
        if ($return_code == 204) {
            $this->error_str = "Container has no Objects.";
            return array(
            $return_code, 
            $this->error_str, 
            array());
        }
        if ($return_code == 404) {
            $this->error_str = "Container has no Objects.";
            return array(
            $return_code, 
            $this->error_str, 
            array());
        }
        if ($return_code == 200) {
            $this->create_array();
            return array(
            $return_code, 
            $this->response_reason, 
            $this->_text_list);
        }
        $this->error_str = "Unexpected HTTP response code: $return_code";
        return array(0, $this->error_str, 
        array());
    }
    # GET /v1/Account/Container?format=json
    #
    public function get_objects (
    $cname, $limit = 0, $marker = NULL, $prefix = NULL, $path = NULL)
    {
        if (! $cname) {
            $this->error_str = "Container name not set.";
            return array(0, 
            $this->error_str, 
            array());
        }
        $url_path = $this->make_path("STORAGE", 
        $cname);
        $limit = intval($limit);
        $params = array();
        $params[] = "format=json";
        if ($limit > 0) {
            $params[] = "limit=$limit";
        }
        if ($marker) {
            $params[] = "marker=" .
             rawurlencode(
            $marker);
        }
        if ($prefix) {
            $params[] = "prefix=" .
             rawurlencode(
            $prefix);
        }
        if ($path) {
            $params[] = "path=" .
             rawurlencode($path);
        }
        if (! empty($params)) {
            $url_path .= "?" . implode(
            "&", $params);
        }
        $conn_type = "GET_CALL";
        $this->_write_callback_type = "OBJECT_STRING";
        $return_code = $this->send_request(
        $conn_type, $url_path);
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return array(0, 
            $this->error_str, 
            array());
        }
        if ($return_code == 204) {
            $this->error_str = "Container has no Objects.";
            return array(
            $return_code, 
            $this->error_str, 
            array());
        }
        if ($return_code == 404) {
            $this->error_str = "Container has no Objects.";
            return array(
            $return_code, 
            $this->error_str, 
            array());
        }
        if ($return_code == 200) {
            $json_body = json_decode(
            $this->_obj_write_string, 
            True);
            return array(
            $return_code, 
            $this->response_reason, 
            $json_body);
        }
        $this->error_str = "Unexpected HTTP response code: $return_code";
        return array(0, $this->error_str, 
        array());
    }
    # HEAD /v1/Account/Container
    #
    public function head_container (
    $container_name)
    {
        if ($container_name == "") {
            $this->error_str = "Container name not set.";
            return False;
        }
        if ($container_name != "0" and ! isset(
        $container_name)) {
            $this->error_str = "Container name not set.";
            return False;
        }
        $conn_type = "HEAD";
        $url_path = $this->make_path("STORAGE", 
        $container_name);
        $return_code = $this->send_request(
        $conn_type, $url_path);
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            array(0, 
            $this->error_str, 0, 
            0);
        }
        if ($return_code == 404) {
            return array(
            $return_code, 
            "Container not found.", 
            0, 0);
        }
        if ($return_code == 204 or 200) {
            return array(
            $return_code, 
            $this->response_reason, 
            $this->_container_object_count, 
            $this->_container_bytes_used);
        }
        return array($return_code, 
        $this->response_reason, 0, 0);
    }
    # GET /v1/Account/Container/Object
    #
    public function get_object_to_string(Rackspace_CloudFiles_Object $obj, $hdrs = array())
    {
        if ( !is_object($obj) || !($obj instanceof Rackspace_CloudFiles_Object)) {
            throw new Rackspace_CloudFiles_SyntaxException("Method argument is not a valid Rackspace_CloudFiles_Object.");
        }

        $conn_type = "GET_CALL";
        $url_path = $this->make_path("STORAGE", 
        $obj->container->name, $obj->name);
        $this->_write_callback_type = "OBJECT_STRING";
        $return_code = $this->send_request($conn_type, $url_path, $hdrs);

        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return array($return_code, $this->error_str, NULL);
        }

        if ($return_code == 404) {
            $this->error_str = "Object not found.";
            return array($return_code, $this->error_str, NULL);
        }
        if (($return_code < 200) || ($return_code > 299 
            && $return_code != 412 && $return_code != 304)) {
            $this->error_str = "Unexpected HTTP return code: $return_code";
            return array($return_code, $this->error_str, NULL);
        }
        
        return array(
            $return_code,
            $this->response_reason,
            $this->_obj_write_string
        );
    }
    # GET /v1/Account/Container/Object
    #
    public function get_object_to_stream (Rackspace_CloudFiles_Object $obj, &$resource = NULL, $hdrs = array())
    {
        if (! is_object($obj) || !($obj instanceof Rackspace_CloudFiles_Object)) {
            throw new Rackspace_CloudFiles_SyntaxException("Method argument is not a valid Rackspace_CloudFiles_Object.");
        }

        if ( !is_resource($resource) ) {
            throw new Rackspace_CloudFiles_SyntaxException("Resource argument not a valid PHP resource.");
        }

        $conn_type = "GET_CALL";
        $url_path = $this->make_path("STORAGE", 
        $obj->container->name, $obj->name);
        $this->_obj_write_resource = $resource;
        $this->_write_callback_type = "OBJECT_STREAM";
        $return_code = $this->send_request($conn_type, $url_path, $hdrs);

        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return array($return_code, $this->error_str);
        }

        if ($return_code == 404) {
            $this->error_str = "Object not found.";
            return array($return_code, $this->error_str);
        }

        if ( ($return_code < 200) || ($return_code > 299 && $return_code != 412 && $return_code != 304)) {
            $this->error_str = "Unexpected HTTP return code: $return_code";
            return array($return_code, $this->error_str);
        }
        return array(
            $return_code,
            $this->response_reason
        );
    }
    # PUT /v1/Account/Container/Object
    #
    public function put_object (Rackspace_CloudFiles_Object $obj, &$fp)
    {
        if ( !is_object($obj) || !($obj instanceof Rackspace_CloudFiles_Object)) {
            throw new Rackspace_CloudFiles_SyntaxException("Method argument is not a valid Rackspace_CloudFiles_Object.");
        }

        if (! is_resource($fp)) {
            throw new Rackspace_CloudFiles_SyntaxException("File pointer argument is not a valid resource.");
        }

        $conn_type = "PUT_OBJ";
        $url_path = $this->make_path("STORAGE", 
        $obj->container->name, $obj->name);
        $hdrs = $this->metadata_headers($obj);
        $etag = $obj->getETag();
        if (isset($etag)) {
            $hdrs[] = "ETag: " . $etag;
        }
        if (! $obj->content_type) {
            $hdrs[] = "Content-Type: application/octet-stream";
        } else {
            $hdrs[] = "Content-Type: " .
             $obj->content_type;
        }
        $this->init($conn_type);
        curl_setopt(
        $this->connections[$conn_type], 
        CURLOPT_INFILE, $fp);
        if (! $obj->content_length) {
            # We don''t know the Content-Length, so assumed "chunked" PUT
            #
            curl_setopt(
            $this->connections[$conn_type], 
            CURLOPT_UPLOAD, True);
            $hdrs[] = 'Transfer-Encoding: chunked';
        } else {
            # We know the Content-Length, so use regular transfer
            #
            curl_setopt(
            $this->connections[$conn_type], 
            CURLOPT_INFILESIZE, 
            $obj->content_length);
        }
        $return_code = $this->send_request(
        $conn_type, $url_path, $hdrs);
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return array(0, 
            $this->error_str, 
            NULL);
        }
        if ($return_code == 412) {
            $this->error_str = "Missing Content-Type header";
            return array(
            $return_code, 
            $this->error_str, 
            NULL);
        }
        if ($return_code == 422) {
            $this->error_str = "Derived and computed checksums do not match.";
            return array(
            $return_code, 
            $this->error_str, 
            NULL);
        }
        if ($return_code != 201) {
            $this->error_str = "Unexpected HTTP return code: $return_code";
            return array(
            $return_code, 
            $this->error_str, 
            NULL);
        }
        return array($return_code, 
        $this->response_reason, $this->_obj_etag);
    }
    # POST /v1/Account/Container/Object
    #
    public function update_object (Rackspace_CloudFiles_Object $obj)
    {
        if (! is_object($obj) || !($obj instanceof Rackspace_CloudFiles_Object)) {
            throw new Rackspace_CloudFiles_SyntaxException("Method argument is not a valid Rackspace_CloudFiles_Object.");
        }
        if (! is_array($obj->metadata) || empty(
        $obj->metadata)) {
            $this->error_str = "Metadata array is empty.";
            return 0;
        }
        $url_path = $this->make_path("STORAGE", 
        $obj->container->name, $obj->name);
        $hdrs = $this->metadata_headers($obj);
        $return_code = $this->send_request("DEL_POST", $url_path, $hdrs, "POST");
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return 0;
        }
        if ($return_code == 404) {
            $this->error_str = "Account, Container, or Object not found.";
        }
        if ($return_code != 202) {
            $this->error_str = "Unexpected HTTP return code: $return_code";
        }
        return $return_code;
    }
    # HEAD /v1/Account/Container/Object
    #
    public function head_object (Rackspace_CloudFiles_Object $obj)
    {
        if (! is_object($obj) || !($obj instanceof Rackspace_CloudFiles_Object)) {
            throw new Rackspace_CloudFiles_SyntaxException("Method argument is not a valid Rackspace_CloudFiles_Object.");
        }

        $conn_type = "HEAD";
        $url_path = $this->make_path("STORAGE", $obj->container->name, $obj->name);

        $return_code = $this->send_request($conn_type, $url_path);
        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return array(
                0,
                "{$this->error_str} {$this->response_reason}",
                NULL,
                NULL,
                NULL,
                NULL,
                array()
            );
        }

        if ($return_code == 404) {
            return array(
                $return_code, 
                $this->response_reason,
                NULL,
                NULL,
                NULL,
                NULL,
                array()
            );
        }

        if ($return_code == 204 or 200) {
            return array(
            $return_code, 
            $this->response_reason, 
            $this->_obj_etag, 
            $this->_obj_last_modified, 
            $this->_obj_content_type, 
            $this->_obj_content_length, 
            $this->_obj_metadata);
        }

        $this->error_str = "Unexpected HTTP return code: $return_code";
        return array(
            $return_code,
            "{$this->error_str} {$this->response_reason}",
            NULL,
            NULL,
            NULL,
            NULL,
            array()
        );
    }

    # DELETE /v1/Account/Container/Object
    #
    public function delete_object ($container_name, $object_name)
    {
        if ($container_name == "") {
            $this->error_str = "Container name not set.";
            return 0;
        }
        if ($container_name != "0" and ! isset(
        $container_name)) {
            $this->error_str = "Container name not set.";
            return 0;
        }
        if (! $object_name) {
            $this->error_str = "Object name not set.";
            return 0;
        }
        
        $url_path = $this->make_path("STORAGE", $container_name, $object_name);
        $return_code = $this->send_request("DEL_POST", $url_path, NULL, "DELETE");

        if (! $return_code) {
            $this->error_str .= ": Failed to obtain valid HTTP response.";
            return 0;
        }
        
        if ($return_code == 404) {
            $this->error_str = "Specified container did not exist to delete.";
        }
        
        if ($return_code != 204) {
            $this->error_str = "Unexpected HTTP return code: $return_code.";
        }
        return $return_code;
    }
    public function get_error ()
    {
        return $this->error_str;
    }
    public function setDebug ($bool)
    {
        $this->dbug = $bool;
        foreach ($this->connections as $k => $v) {
            if (! is_null($v)) {
                curl_setopt($this->connections[$k], CURLOPT_VERBOSE, $this->dbug);
            }
        }
    }
    public function getCDNMUrl ()
    {
        return $this->cdnm_url;
    }
    public function getStorageUrl ()
    {
        return $this->storage_url;
    }
    public function getAuthToken ()
    {
        return $this->auth_token;
    }

    public function setCFAuth ($cfs_auth, $servicenet = False)
    {
        if ($servicenet) {
            $this->storage_url = "https://snet-" . substr($cfs_auth->storage_url, 8);
        } else {
            $this->storage_url = $cfs_auth->storage_url;
        }

        $this->auth_token = $cfs_auth->auth_token;
        $this->cdnm_url = $cfs_auth->cdnm_url;
    }

    public function setReadProgressFunc ($func_name)
    {
        $this->_user_read_progress_callback_func = $func_name;
    }

    public function setWriteProgressFunc ($func_name)
    {
        $this->_user_write_progress_callback_func = $func_name;
    }

    protected function header_cb ($ch, $header)
    {
        preg_match('/^HTTP\/1\.[01] (\d{3}) (.*)/', $header, $matches);
        if (isset($matches[1])) {
            $this->response_status = $matches[1];
        }

        if (isset($matches[2])) {
            $this->response_reason = $matches[2];
        }

        if (stripos($header, CDN_ENABLED) === 0) {
            $val = trim(substr($header, strlen(CDN_ENABLED) + 1));
            if (strtolower($val) == "true") {
                $this->_cdn_enabled = True;
            } elseif (strtolower($val) == "false") {
                $this->_cdn_enabled = False;
            } else {
                $this->_cdn_enabled = NULL;
            }

            return strlen($header);
        }
        if (stripos($header, CDN_URI) === 0) {
            $this->_cdn_uri = trim(substr($header, strlen(CDN_URI) + 1));
            return strlen($header);
        }
        if (stripos($header, CDN_TTL) === 0) {
            $this->_cdn_ttl = trim(substr($header, strlen(CDN_TTL) + 1)) + 0;
            return strlen($header);
        }
        if (stripos($header, CDN_LOG_RETENTION) === 0) {
            $this->_cdn_log_retention = (trim(substr($header, strlen(CDN_LOG_RETENTION) + 1)) == "True");
            return strlen($header);
        }

        if (stripos($header, CDN_ACL_USER_AGENT) === 0) {
            $this->_cdn_acl_user_agent = trim(substr($header, strlen(CDN_ACL_USER_AGENT) + 1));
            return strlen($header);
        }

        if (stripos($header, CDN_ACL_REFERRER) === 0) {
            $this->_cdn_acl_referrer = trim(substr($header, strlen(CDN_ACL_REFERRER) + 1));
            return strlen($header);
        }

        if (stripos($header, ACCOUNT_CONTAINER_COUNT) === 0) {
            $this->_account_container_count = (float) trim(substr($header, strlen(ACCOUNT_CONTAINER_COUNT) + 1)) + 0;
            return strlen($header);
        }
        if (stripos($header, ACCOUNT_BYTES_USED) === 0) {
            $this->_account_bytes_used = (float) trim(substr($header, strlen(ACCOUNT_BYTES_USED) + 1)) + 0;
            return strlen($header);
        }
        if (stripos($header, CONTAINER_OBJ_COUNT) === 0) {
            $this->_container_object_count = (float) trim(substr($header, strlen(CONTAINER_OBJ_COUNT) + 1)) + 0;
            return strlen($header);
        }

        if (stripos($header, CONTAINER_BYTES_USED) === 0) {
            $this->_container_bytes_used = (float) trim(substr($header, strlen(CONTAINER_BYTES_USED) + 1)) + 0;
            return strlen($header);
        }

        if (stripos($header, METADATA_HEADER) === 0) {
            $temp = substr($header, strlen(METADATA_HEADER));
            $parts = explode(":", $temp);
            $val = substr(strstr($temp, ":"), 1);
            $this->_obj_metadata[$parts[0]] = trim($val);
            return strlen($header);
        }

        if (stripos($header, "ETag:") === 0) {
            $val = substr(strstr($header, ":"), 1);
            $this->_obj_etag = trim($val);
            return strlen($header);
        }

        if (stripos($header, "Last-Modified:") === 0) {
            $val = substr(strstr($header, ":"), 1);
            $this->_obj_last_modified = trim($val);
            return strlen($header);
        }

        if (stripos($header, "Content-Type:") === 0) {
            $val = substr(strstr($header, ":"), 1);
            $this->_obj_content_type = trim($val);
            return strlen($header);
        }

        if (stripos($header, "Content-Length:") === 0) {
            $val = substr(strstr($header, ":"), 1);
            $this->_obj_content_length = (float) trim($val) + 0;
            return strlen($header);
        }

        return strlen($header);
    }

    protected function read_cb ($ch, $fd, $length)
    {
        $data = fread($fd, $length);
        $len = strlen($data);
        if (isset(
        $this->_user_write_progress_callback_func)) {
            call_user_func($this->_user_write_progress_callback_func, $len);
        }
        return $data;
    }

    protected function write_cb ($ch, $data)
    {
        $dlen = strlen($data);
        switch ($this->_write_callback_type) {
            case "TEXT_LIST":
                $this->_return_list = $this->_return_list . $data;
                break;
            case "OBJECT_STREAM":
                fwrite($this->_obj_write_resource, $data, $dlen);
                break;
            case "OBJECT_STRING":
                $this->_obj_write_string .= $data;
                break;
        }

        if ( isset($this->_user_read_progress_callback_func) ) {
            call_user_func($this->_user_read_progress_callback_func, $dlen);
        }

        return $dlen;
    }

    protected function auth_hdr_cb ($ch, $header)
    {
        preg_match('/^HTTP\/1\.[01] (\d{3}) (.*)/', $header, $matches);

        if (isset($matches[1])) {
            $this->response_status = $matches[1];
        }

        if (isset($matches[2])) {
            $this->response_reason = $matches[2];
        }

        if (stripos($header, STORAGE_URL) === 0) {
            $this->storage_url = trim(substr($header, strlen(STORAGE_URL) +1));
        }

        if (stripos($header, CDNM_URL) === 0) {
            $this->cdnm_url = trim(substr($header, strlen(CDNM_URL) + 1));
        }

        if (stripos($header, AUTH_TOKEN) === 0) {
            $this->auth_token = trim(substr($header, strlen(AUTH_TOKEN) + 1));
        }

        if (stripos($header, AUTH_TOKEN_LEGACY) === 0) {
            $this->auth_token = trim(substr($header, strlen(AUTH_TOKEN_LEGACY) + 1));
        }

        return strlen($header);
    }
    protected function make_headers ($hdrs = NULL)
    {
        $new_headers = array();
        $has_stoken = False;
        $has_uagent = False;
        if (is_array($hdrs)) {
            foreach ($hdrs as $h => $v) {
                if ( is_int($h) ) {
                    $parts = explode(':', $v);
                    $header = $parts[0];
                    $value = trim(substr(strstr($v,":"),1));
                } else {
                    $header = $h;
                    $value = trim($v);
                }

                if (stripos($header, AUTH_TOKEN) === 0) {
                    $has_stoken = True;
                }

                if (stripos($header, "user-agent") === 0) {
                    $has_uagent = True;
                }

                $new_headers[] = "{$header}: {$value}";
            }
        }
        if ( !$has_stoken) {
            $new_headers[] = AUTH_TOKEN . ": {$this->auth_token}";
        }
        if ( !$has_uagent) {
            $new_headers[] = "User-Agent: " . USER_AGENT;
        }

        return $new_headers;
    }

    protected function init ($conn_type, $force_new = False)
    {
        if ( !array_key_exists($conn_type, $this->connections) ) {
            // TODO throw a Rackspace_CloudFiles_UnsupportedConnectionTypeException?
            $this->error_str = "Invalid CURL_XXX connection type: {$conn_type}";
            return False;
        }

        if (is_null($this->connections[$conn_type]) || $force_new) {
            $ch = curl_init();
        } else {
            return;
        }

        if ($this->dbug) {
            curl_setopt($ch, 
            CURLOPT_VERBOSE, 1);
        }

        if (! is_null($this->cabundle_path)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, True);
            curl_setopt($ch, CURLOPT_CAINFO, 
            $this->cabundle_path);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, True);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION,array(&$this, 'header_cb'));

        switch ($conn_type) {
            default:
                continue;

 			case 'GET_CALL':
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, array(&$this, 'write_cb'));
                break;

 			case 'PUT_OBJ':
	            curl_setopt($ch, 
	            CURLOPT_PUT, 1);
	            curl_setopt($ch, 
	            CURLOPT_READFUNCTION, array(&$this, 'read_cb'));
	            break;

 			case 'HEAD':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
                curl_setopt($ch, CURLOPT_NOBODY, 1);
                break;
                
            case 'PUT_CONT':
	            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	            curl_setopt($ch, CURLOPT_INFILESIZE, 0);
	            curl_setopt($ch, CURLOPT_NOBODY, 1);
	            break;

            case 'DEL_POST':
                curl_setopt($ch, CURLOPT_NOBODY, 1);
                break;
        }

        $this->connections[$conn_type] = $ch;
        return;
    }

    protected function reset_callback_vars ()
    {
        $this->_text_list = array();
        $this->_return_list = NULL;
        $this->_account_container_count = 0;
        $this->_account_bytes_used = 0;
        $this->_container_object_count = 0;
        $this->_container_bytes_used = 0;
        $this->_obj_etag = NULL;
        $this->_obj_last_modified = NULL;
        $this->_obj_content_type = NULL;
        $this->_obj_content_length = NULL;
        $this->_obj_metadata = array();
        $this->_obj_write_string = "";
        $this->_cdn_enabled = NULL;
        $this->_cdn_uri = NULL;
        $this->_cdn_ttl = NULL;
        $this->response_status = 0;
        $this->response_reason = "";
    }

    protected function make_path ($t = "STORAGE", $c = NULL, $o = NULL)
    {
        $path = array();
        switch ($t) {
            case "STORAGE":
                $path[] = $this->storage_url;
                break;
            case "CDN":
                $path[] = $this->cdnm_url;
                break;
        }

        if ($c == "0") {
            $path[] = rawurlencode($c);
        }
        
        if ($c) {
            $path[] = rawurlencode($c);
        }
        
        if ($o) {
            # mimic Python''s urllib.quote() feature of a "safe" '/' character
            #
            $path[] = str_replace("%2F", "/", rawurlencode($o));
        }

        return implode("/", $path);
    }

    protected function metadata_headers (&$obj)
    {
        $hdrs = array();
        foreach ($obj->metadata as $k => $v) {
            if (strpos($k, ":") !== False) {
                throw new Rackspace_CloudFiles_SyntaxException("Metadata keys cannot contain a ':' character.");
            }

            $k = trim($k);
            $key = sprintf("%s%s", METADATA_HEADER, $k);
            if ( !array_key_exists($key, $hdrs)) {
                if (strlen($k) >128 || strlen($v) > 256) {
                    $this->error_str = "Metadata key or value exceeds maximum length: ($k: $v)";
                    return 0;
                }

                $hdrs[] = sprintf("%s%s: %s", METADATA_HEADER, $k, trim($v));
            }
        }
        return $hdrs;
    }

    protected function send_request ($conn_type, $url_path, $hdrs = NULL, $method = "GET")
    {
        $this->init($conn_type);
        $this->reset_callback_vars();
        $headers = $this->make_headers($hdrs);

        if ( gettype($this->connections[$conn_type]) == "unknown type" ) {
            throw new Rackspace_CloudFiles_ConnectionNotOpenException("Connection is not open.");
        }

        switch ($method) {
            default:
                break;
            case "DELETE":
                curl_setopt($this->connections[$conn_type], CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            case "POST":
                curl_setopt($this->connections[$conn_type], CURLOPT_CUSTOMREQUEST, "POST");
        }
        
        curl_setopt($this->connections[$conn_type], CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->connections[$conn_type], CURLOPT_URL, $url_path);
        if ( !curl_exec($this->connections[$conn_type]) ) {
            $this->error_str = '(curl error: '
                . curl_errno($this->connections[$conn_type]) . ') '
                . curl_error($this->connections[$conn_type]);
            return False;
        }
        return curl_getinfo($this->connections[$conn_type], CURLINFO_HTTP_CODE);
    }

    public function close ()
    {
        foreach ($this->connections as $key => $cnx) {
            if ( !is_null($cnx) ) {
                curl_close($cnx);
                $this->connections[$key] = NULL;
            }
        }
    }
    protected function create_array ()
    {
        $this->_text_list = explode("\n", 
        rtrim($this->_return_list, "\n\x0B"));
        return True;
    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */