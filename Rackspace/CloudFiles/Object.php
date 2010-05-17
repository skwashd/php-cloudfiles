<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Storage Object Operations
 * 
 * An Object is analogous to a file on a conventional filesystem. You can
 * read data from, or write data to your Objects. You can also associate 
 * arbitrary metadata with them.
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
 * Storage Object Operations
 * 
 * An Object is analogous to a file on a conventional filesystem. You can
 * read data from, or write data to your Objects. You can also associate 
 * arbitrary metadata with them.
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
class Rackspace_CloudFiles_Object
{
    public $container;
    public $name;
    public $last_modified;
    public $content_type;
    public $content_length;
    public $metadata;
    protected $etag;
    /**
     * Class constructor
     *
     * @param obj $container CF_Container instance
     * @param string $name name of Object
     * @param boolean $force_exists if set, throw an error if Object doesn't exist
     */
    public function __construct (Rackspace_CloudFiles_Container $container, $name, $force_exists = False, $dohead = True)
    {
        if ($name[0] == "/") {
            $r = "Object name '{$name}' cannot contain begin with a '/' character.";
            throw new Rackspace_CloudFiles_SyntaxException($r);
        }

        if (strlen($name) > Rackspace_CloudFiles::MAX_OBJECT_NAME_LEN) {
            throw new Rackspace_CloudFiles_SyntaxException("Object name exceeds maximum allowed length.");
        }

        $this->container = $container;
        $this->name = $name;
        $this->etag = NULL;
        $this->_etag_override = False;
        $this->last_modified = NULL;
        $this->content_type = NULL;
        $this->content_length = 0;
        $this->metadata = array();
        if ($dohead) {
            if (! $this->_initialize() && $force_exists) {
                throw new Rackspace_CloudFiles_NoSuchObjectException("No such object '{$name}'");
            }
        }
    }
    /**
     * String representation of Object
     *
     * Pretty print the Object's location and name
     *
     * @return string Object information
     */
    public function __toString ()
    {
        return $this->container->name . "/" . $this->name;
    }

    /**
     * Internal check to get the proper mimetype.
     *
     * This function would go over the available PHP methods to get
     * the MIME type.
     *
     * By default it will try to use the PHP fileinfo library which is
     * available from PHP 5.3 or as an PECL extension
     * (http://pecl.php.net/package/Fileinfo).
     *
     * It will get the magic file by default from the system wide file
     * which is usually available in /usr/share/magic on Unix or try
     * to use the file specified in the source directory of the API
     * (share directory).
     *
     * if fileinfo is not available it will try to use the internal
     * mime_content_type function.
     * 
     * @param string $handle name of file or buffer to guess the type from
     * @return boolean <kbd>True</kbd> if successful
     * @throws BadContentTypeException
     */
    public function _guess_content_type ($handle)
    {
        if ($this->content_type)
            return;
        if (function_exists("finfo_open")) {
            $local_magic = dirname(
            __FILE__) . "/share/magic";
            $finfo = @finfo_open(
            FILEINFO_MIME, 
            $local_magic);
            if (! $finfo)
                $finfo = @finfo_open(
                FILEINFO_MIME);
            if ($finfo) {
                if (is_file(
                (string) $handle))
                    $ct = @finfo_file(
                    $finfo, 
                    $handle);
                else
                    $ct = @finfo_buffer(
                    $finfo, 
                    $handle);
                    /* PHP 5.3 fileinfo display extra information like
                   charset so we remove everything after the ; since
                   we are not into that stuff */
                if ($ct) {
                    $extra_content_type_info = strpos(
                    $ct, 
                    "; ");
                    if ($extra_content_type_info)
                        $ct = substr(
                        $ct, 
                        0, 
                        $extra_content_type_info);
                }
                if ($ct &&
                 $ct !=
                 'application/octet-stream')
                    $this->content_type = $ct;
                @finfo_close(
                $finfo);
            }
        }
        if (! $this->content_type && (string) is_file(
        $handle) && function_exists(
        "mime_content_type")) {
            $this->content_type = @mime_content_type(
            $handle);
        }
        if (! $this->content_type) {
            throw new Rackspace_CloudFiles_BadContentTypeException(
            "Required Content-Type not set");
        }
        return True;
    }
    /**
     * String representation of the Object's public URI
     *
     * A string representing the Object's public URI assuming that it's
     * parent Container is CDN-enabled.
     *
     * Example:
     * <code>
     * # ... authentication/connection/container code excluded
     * # ... see previous examples
     *
     * # Print out the Object's CDN URI (if it has one) in an HTML img-tag
     * #
     * print "<img src='$pic->public_uri()' />\n";
     * </code>
     *
     * @return string Object's public URI or NULL
     */
    public function public_uri ()
    {
        if ($this->container->cdn_enabled) {
            return $this->container->cdn_uri .
             "/" . $this->name;
        }
        return NULL;
    }
    /**
     * Read the remote Object's data
     *
     * Returns the Object's data.  This is useful for smaller Objects such
     * as images or office documents.  Object's with larger content should use
     * the stream() method below.
     *
     * Pass in $hdrs array to set specific custom HTTP headers such as
     * If-Match, If-None-Match, If-Modified-Since, Range, etc.
     *
     * Example:
     * <code>
     * # ... authentication/connection/container code excluded
     * # ... see previous examples
     *
     * $my_docs = $conn->get_container("documents");
     * $doc = $my_docs->get_object("README");
     * $data = $doc->read(); # read image content into a string variable
     * print $data;
     *
     * # Or see stream() below for a different example.
     * #
     * </code>
     *
     * @param array $hdrs user-defined headers (Range, If-Match, etc.)
     * @return string Object's data
     * @throws InvalidResponseException unexpected response
     */
    public function read ($hdrs = array())
    {
        list ($status, $reason, $data) = $this->container->cfs_http->get_object_to_string(
        $this, $hdrs);
        #if ($status == 401 && $this->_re_auth()) {
        #    return $this->read($hdrs);
        #}
        if (($status <
         200) || ($status > 299 && $status != 412 &&
         $status != 304)) {
            throw new Rackspace_CloudFiles_InvalidResponseException(
            "Invalid response (" .
             $status . "): " . $this->container->cfs_http->get_error());
        }
        return $data;
    }
    /**
     * Streaming read of Object's data
     *
     * Given an open PHP resource (see PHP's fopen() method), fetch the Object's
     * data and write it to the open resource handle.  This is useful for
     * streaming an Object's content to the browser (videos, images) or for
     * fetching content to a local file.
     *
     * Pass in $hdrs array to set specific custom HTTP headers such as
     * If-Match, If-None-Match, If-Modified-Since, Range, etc.
     *
     * Example:
     * <code>
     * # ... authentication/connection/container code excluded
     * # ... see previous examples
     *
     * # Assuming this is a web script to display the README to the
     * # user's browser:
     * #
     * <?php
     * // grab README from storage system
     * //
     * $my_docs = $conn->get_container("documents");
     * $doc = $my_docs->get_object("README");
     *
     * // Hand it back to user's browser with appropriate content-type
     * //
     * header("Content-Type: " . $doc->content_type);
     * $output = fopen("php://output", "w");
     * $doc->stream($output); # stream object content to PHP's output buffer
     * fclose($output);
     * ?>
     *
     * # See read() above for a more simple example.
     * #
     * </code>
     *
     * @param resource $fp open resource for writing data to
     * @param array $hdrs user-defined headers (Range, If-Match, etc.)
     * @return string Object's data
     * @throws InvalidResponseException unexpected response
     */
    public function stream (&$fp, $hdrs = array())
    {
        list ($status, $reason) = $this->container->cfs_http->get_object_to_stream(
        $this, $fp, $hdrs);
        #if ($status == 401 && $this->_re_auth()) {
        #    return $this->stream($fp, $hdrs);
        #}
        if (($status <
         200) || ($status > 299 && $status != 412 &&
         $status != 304)) {
            throw new Rackspace_CloudFiles_InvalidResponseException(
            "Invalid response (" .
             $status . "): " . $reason);
        }
        return True;
    }
    /**
     * Store new Object metadata
     *
     * Write's an Object's metadata to the remote Object.  This will overwrite
     * an prior Object metadata.
     *
     * Example:
     * <code>
     * # ... authentication/connection/container code excluded
     * # ... see previous examples
     *
     * $my_docs = $conn->get_container("documents");
     * $doc = $my_docs->get_object("README");
     *
     * # Define new metadata for the object
     * #
     * $doc->metadata = array(
     * "Author" => "EJ",
     * "Subject" => "How to use the PHP tests",
     * "Version" => "1.2.2"
     * );
     *
     * # Push the new metadata up to the storage system
     * #
     * $doc->sync_metadata();
     * </code>
     *
     * @return boolean <kbd>True</kbd> if successful, <kbd>False</kbd> otherwise
     * @throws InvalidResponseException unexpected response
     */
    public function sync_metadata ()
    {
        if (! empty($this->metadata)) {
            $status = $this->container->cfs_http->update_object(
            $this);
            #if ($status == 401 && $this->_re_auth()) {
            #    return $this->sync_metadata();
            #}
            if ($status !=
             202) {
                throw new Rackspace_CloudFiles_InvalidResponseException(
                "Invalid response (" .
                 $status .
                 "): " .
                 $this->container->cfs_http->get_error());
            }
            return True;
        }
        return False;
    }
    /**
     * Upload Object's data to Cloud Files
     *
     * Write data to the remote Object.  The $data argument can either be a
     * PHP resource open for reading (see PHP's fopen() method) or an in-memory
     * variable.  If passing in a PHP resource, you must also include the $bytes
     * parameter.
     *
     * Example:
     * <code>
     * # ... authentication/connection/container code excluded
     * # ... see previous examples
     *
     * $my_docs = $conn->get_container("documents");
     * $doc = $my_docs->get_object("README");
     *
     * # Upload placeholder text in my README
     * #
     * $doc->write("This is just placeholder text for now...");
     * </code>
     *
     * @param string|resource $data string or open resource
     * @param float $bytes amount of data to upload (required for resources)
     * @param boolean $verify generate, send, and compare MD5 checksums
     * @return boolean <kbd>True</kbd> when data uploaded successfully
     * @throws SyntaxException missing required parameters
     * @throws BadContentTypeException if no Content-Type was/could be set
     * @throws MisMatchedChecksumException $verify is set and checksums unequal
     * @throws InvalidResponseException unexpected response
     */
    public function write ($data = NULL, $bytes = 0, $verify = True)
    {
        if (! $data) {
            throw new Rackspace_CloudFiles_SyntaxException(
            "Missing data source.");
        }
        if ($bytes > Rackspace_CloudFiles::MAX_OBJECT_SIZE) {
            throw new Rackspace_CloudFiles_SyntaxException(
            "Bytes exceeds maximum object size.");
        }
        if ($verify) {
            if (! $this->_etag_override) {
                $this->etag = $this->compute_md5sum(
                $data);
            }
        } else {
            $this->etag = NULL;
        }
        $close_fh = False;
        if (! is_resource($data)) {
            # A hack to treat string data as a file handle.  php://memory feels
            # like a better option, but it seems to break on Windows so use
            # a temporary file instead.
            #
            $fp = fopen(
            "php://temp", "wb+");
            #$fp = fopen("php://memory", "wb+");
            fwrite(
            $fp, $data, 
            strlen($data));
            rewind($fp);
            $close_fh = True;
            $this->content_length = (float) strlen(
            $data);
            if ($this->content_length >
             Rackspace_CloudFiles::MAX_OBJECT_SIZE) {
                throw new Rackspace_CloudFiles_SyntaxException(
                "Data exceeds maximum object size");
            }
            $ct_data = substr(
            $data, 0, 64);
        } else {
            $this->content_length = $bytes;
            $fp = $data;
            $ct_data = fread(
            $data, 64);
            rewind($data);
        }
        $this->_guess_content_type($ct_data);
        list ($status, $reason, $etag) = $this->container->cfs_http->put_object(
        $this, $fp);
        #if ($status == 401 && $this->_re_auth()) {
        #    return $this->write($data, $bytes, $verify);
        #}
        if ($status ==
         412) {
            if ($close_fh) {
                fclose(
                $fp);
            }
            throw new Rackspace_CloudFiles_SyntaxException(
            "Missing Content-Type header");
        }
        if ($status == 422) {
            if ($close_fh) {
                fclose(
                $fp);
            }
            throw new Rackspace_CloudFiles_MisMatchedChecksumException(
            "Supplied and computed checksums do not match.");
        }
        if ($status != 201) {
            if ($close_fh) {
                fclose(
                $fp);
            }
            throw new Rackspace_CloudFiles_InvalidResponseException(
            "Invalid response (" .
             $status . "): " . $this->container->cfs_http->get_error());
        }
        if (! $verify) {
            $this->etag = $etag;
        }
        if ($close_fh) {
            fclose($fp);
        }
        return True;
    }
    /**
     * Upload Object data from local filename
     *
     * This is a convenience function to upload the data from a local file.  A
     * True value for $verify will cause the method to compute the Object's MD5
     * checksum prior to uploading.
     *
     * Example:
     * <code>
     * # ... authentication/connection/container code excluded
     * # ... see previous examples
     *
     * $my_docs = $conn->get_container("documents");
     * $doc = $my_docs->get_object("README");
     *
     * # Upload my local README's content
     * #
     * $doc->load_from_filename("/home/ej/cloudfiles/readme");
     * </code>
     *
     * @param string $filename full path to local file
     * @param boolean $verify enable local/remote MD5 checksum validation
     * @return boolean <kbd>True</kbd> if data uploaded successfully
     * @throws SyntaxException missing required parameters
     * @throws BadContentTypeException if no Content-Type was/could be set
     * @throws MisMatchedChecksumException $verify is set and checksums unequal
     * @throws InvalidResponseException unexpected response
     * @throws IOException error opening file
     */
    public function load_from_filename ($filename, $verify = True)
    {
        $fp = @fopen($filename, "r");
        if (! $fp) {
            throw new Rackspace_CloudFiles_IOException(
            "Could not open file for reading: " .
             $filename);
        }
        clearstatcache();
        $size = (float) sprintf("%u", 
        filesize($filename));
        if ($size > Rackspace_CloudFiles::MAX_OBJECT_SIZE) {
            throw new Rackspace_CloudFiles_SyntaxException(
            "File size exceeds maximum object size.");
        }
        $this->_guess_content_type($filename);
        $this->write($fp, $size, $verify);
        fclose($fp);
        return True;
    }
    /**
     * Save Object's data to local filename
     *
     * Given a local filename, the Object's data will be written to the newly
     * created file.
     *
     * Example:
     * <code>
     * # ... authentication/connection/container code excluded
     * # ... see previous examples
     *
     * # Whoops!  I deleted my local README, let me download/save it
     * #
     * $my_docs = $conn->get_container("documents");
     * $doc = $my_docs->get_object("README");
     *
     * $doc->save_to_filename("/home/ej/cloudfiles/readme.restored");
     * </code>
     *
     * @param string $filename name of local file to write data to
     * @return boolean <kbd>True</kbd> if successful
     * @throws IOException error opening file
     * @throws InvalidResponseException unexpected response
     */
    public function save_to_filename ($filename)
    {
        $fp = @fopen($filename, "wb");
        if (! $fp) {
            throw new Rackspace_CloudFiles_IOException(
            "Could not open file for writing: " .
             $filename);
        }
        $result = $this->stream($fp);
        fclose($fp);
        return $result;
    }
    /**
     * Set Object's MD5 checksum
     *
     * Manually set the Object's ETag.  Including the ETag is mandatory for
     * Cloud Files to perform end-to-end verification.  Omitting the ETag forces
     * the user to handle any data integrity checks.
     *
     * @param string $etag MD5 checksum hexidecimal string
     */
    public function set_etag ($etag)
    {
        $this->etag = $etag;
        $this->_etag_override = True;
    }
    /**
     * Object's MD5 checksum
     *
     * Accessor method for reading Object's private ETag attribute.
     *
     * @return string MD5 checksum hexidecimal string
     */
    public function getETag ()
    {
        return $this->etag;
    }
    /**
     * Compute the MD5 checksum
     *
     * Calculate the MD5 checksum on either a PHP resource or data.  The argument
     * may either be a local filename, open resource for reading, or a string.
     *
     * <b>WARNING:</b> if you are uploading a big file over a stream
     * it could get very slow to compute the md5 you probably want to
     * set the $verify parameter to False in the write() method and
     * compute yourself the md5 before if you have it.
     *
     * @param filename|obj|string $data filename, open resource, or string
     * @return string MD5 checksum hexidecimal string
     */
    public function compute_md5sum (&$data)
    {
        if (function_exists("hash_init") && is_resource(
        $data)) {
            $ctx = hash_init(
            'md5');
            while (! feof($data)) {
                $buffer = fgets(
                $data, 
                65536);
                hash_update(
                $ctx, 
                $buffer);
            }
            $md5 = hash_final(
            $ctx, false);
            rewind($data);
        } elseif ((string) is_file($data)) {
            $md5 = md5_file(
            $data);
        } else {
            $md5 = md5($data);
        }
        return $md5;
    }
    /**
     * PRIVATE: fetch information about the remote Object if it exists
     */
    protected function _initialize ()
    {
        list ($status, $reason, $etag, $last_modified, $content_type, $content_length, $metadata) = $this->container->cfs_http->head_object(
        $this);
        #if ($status == 401 && $this->_re_auth()) {
        #    return $this->_initialize();
        #}
        if ($status ==
         404) {
            return False;
        }
        if ($status < 200 || $status > 299) {
            throw new Rackspace_CloudFiles_InvalidResponseException(
            "Invalid response (" .
             $status . "): " . $this->container->cfs_http->get_error());
        }
        $this->etag = $etag;
        $this->last_modified = $last_modified;
        $this->content_type = $content_type;
        $this->content_length = $content_length;
        $this->metadata = $metadata;
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