<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Rackspace CloudFiles API 
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
 * Rackspace CloudFiles API 
 *
 * Example Code
 * <code>
 * # Authenticate to Cloud Files.  The default is to automatically try
 * # to re-authenticate if an authentication token expires.
 * #
 * # NOTE: Some versions of cURL include an outdated certificate authority (CA)
 * #       file.  This API ships with a newer version obtained directly from
 * #       cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
 * #       call the Rackspace_CloudFiles_Authentication instance's 'ssl_use_cabundle()' method.
 * #
 * $auth = new Rackspace_CloudFiles_Authentication($username, $api_key);
 * # $auth->ssl_use_cabundle();  # bypass cURL's old CA bundle
 * $auth->authenticate();
 *
 * # Establish a connection to the storage system
 * #
 * # NOTE: Some versions of cURL include an outdated certificate authority (CA)
 * #       file.  This API ships with a newer version obtained directly from
 * #       cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
 * #       call the Rackspace_CloudFiles_Connection instance's 'ssl_use_cabundle()' method.
 * #
 * $conn = new Rackspace_CloudFiles_Connection($auth);
 * # $conn->ssl_use_cabundle();  # bypass cURL's old CA bundle
 *
 * # Create a remote Container and storage Object
 * #
 * $images = $conn->create_container("photos");
 * $bday = $images->create_object("first_birthday.jpg");
 *
 * # Upload content from a local file by streaming it.  Note that we use
 * # a "float" for the file size to overcome PHP's 32-bit integer limit for
 * # very large files.
 * #
 * $fname = "/home/user/photos/birthdays/birthday1.jpg";  # filename to upload
 * $size = (float) sprintf("%u", filesize($fname));
 * $fp = open($fname, "r");
 * $bday->write($fp, $size);
 *
 * # Or... use a convenience function instead
 * #
 * $bday->load_from_filename("/home/user/photos/birthdays/birthday1.jpg");
 *
 * # Now, publish the "photos" container to serve the images by CDN.
 * # Use the "$uri" value to put in your web pages or send the link in an
 * # email message, etc.
 * #
 * $uri = $images->make_public();
 *
 * # Or... print out the Object's public URI
 * #
 * print $bday->public_uri();
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
class Rackspace_CloudFiles
{
    /**
     * @var float Default CloudFiles API version 
     */
    const DEFAULT_API_VERSION = 1;
    /**
     * @var int MAX_CONTAINER_NAME_LEN Maximum length of a container name 
     */
    const MAX_CONTAINER_NAME_LEN = 256;
    /**
     * @var int MAX_OBJECT_NAME_LEN Maximum length of a storage object name
     */
    const MAX_OBJECT_NAME_LEN = 1024;
    /**
     * @var int MAX_OBJECT SIZE Maximum possible size of an object to be stored
     */
    const MAX_OBJECT_SIZE = 5368709121; // (5*1024*1024*1024)+ 1 - bigger than S3! ;-)
    /**
     * Autoload Rackspace CloudFiles classes
     * 
     * Instead of filling your code with require_once statements
     * you can use the following code to "automagically" include
     * classes as they are needed.
     * 
     * <code>
     * require_once '/path/to/Rackspace/CloudFiles.php';
     * spl_autoload_register(array('Rackspace_CloudFiles', 'autoload'));
     * </code>
     * 
     * This isn't needed if you are already using a PEAR (compatiable) autoloader.
     * 
     * @param string $className The name of the class to load
     */
    public static function autoload ($className)
    {
        static $path = null;
        if (is_null($path)) {
            $path = realpath(
            dirname(__FILE__) . '/../');
        }
        $file = /* "{$path}/" . */ preg_replace('/_/', '/', $className) . '.php';
        if (! is_file($file)) {
            return False;
        }
        require_once $file;
    }
}