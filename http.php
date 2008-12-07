<?php
if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../') . '/');

require_once (DOKU_INC . 'inc/HTTPClient.php');

define('HTTP_NL', "\r\n");

/**
 * Modifies sendRequest. If max_bodysize_limit is set to true, the size of
 * the retrieved body is limited to the value set in max_bodysize.
 * 
 * Also, modifies get and post to allow response codes in the 200 range.
 *
 * @author Gina Haeussge <osd@foosel.net>
 */
class LinkbackHTTPClient extends DokuHTTPClient {

    var $max_bodysize_limit = false;

    function LinkbackHTTPClient() {
        $this->DokuHTTPClient();
    }

    /**
     * Simple function to do a GET request
     *
     * Returns the wanted page or false on an error;
     *
     * @param  string $url       The URL to fetch
     * @param  bool   $sloppy304 Return body on 304 not modified
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function get($url,$sloppy304=false){
        if(!$this->sendRequest($url)) return false;
        if($this->status == 304 && $sloppy304) return $this->resp_body;
        if($this->status < 200 || $this->status > 206) return false;
        return $this->resp_body;
    }
    
    /**
     * Simple function to do a POST request
     *
     * Returns the resulting page or false on an error;
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function post($url,$data){
        if(!$this->sendRequest($url,$data,'POST')) return false;
        if($this->status < 200 || $this->status > 206) return false;
        return $this->resp_body;
    }

    /**
     * Do an HTTP request
     *
     * @author Andreas Goetz <cpuidle@gmx.de>
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function sendRequest($url, $data = array (), $method = 'GET') {
        $this->error = '';
        $this->status = 0;

        // parse URL into bits
        $uri = parse_url($url);
        $server = $uri['host'];
        $path = $uri['path'];
        if (empty ($path))
            $path = '/';
        if (!empty ($uri['query']))
            $path .= '?' . $uri['query'];
        $port = $uri['port'];
        if ($uri['user'])
            $this->user = $uri['user'];
        if ($uri['pass'])
            $this->pass = $uri['pass'];

        // proxy setup
        if ($this->proxy_host) {
            $request_url = $url;
            $server = $this->proxy_host;
            $port = $this->proxy_port;
            if (empty ($port))
                $port = 8080;
        } else {
            $request_url = $path;
            $server = $server;
            if (empty ($port))
                $port = ($uri['scheme'] == 'https') ? 443 : 80;
        }

        // add SSL stream prefix if needed - needs SSL support in PHP
        if ($port == 443 || $this->proxy_ssl)
            $server = 'ssl://' . $server;

        // prepare headers
        $headers = $this->headers;
        $headers['Host'] = $uri['host'];
        $headers['User-Agent'] = $this->agent;
        $headers['Referer'] = $this->referer;
        $headers['Connection'] = 'Close';
        if ($method == 'POST') {
            $post = $this->_postEncode($data);
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            $headers['Content-Length'] = strlen($post);
        }
        if ($this->user) {
            $headers['Authorization'] = 'BASIC ' . base64_encode($this->user . ':' . $this->pass);
        }
        if ($this->proxy_user) {
            $headers['Proxy-Authorization'] = 'BASIC ' . base64_encode($this->proxy_user . ':' . $this->proxy_pass);
        }

        // stop time
        $start = time();

        // open socket
        $socket = @ fsockopen($server, $port, $errno, $errstr, $this->timeout);
        if (!$socket) {
            $resp->status = '-100';
            $this->error = "Could not connect to $server:$port\n$errstr ($errno)";
            return false;
        }
        //set non blocking
        stream_set_blocking($socket, 0);

        // build request
        $request = "$method $request_url HTTP/" . $this->http . HTTP_NL;
        $request .= $this->_buildHeaders($headers);
        $request .= $this->_getCookies();
        $request .= HTTP_NL;
        $request .= $post;

        $this->_debug('request', $request);

        // send request
        fputs($socket, $request);
        // read headers from socket
        $r_headers = '';
        do {
            if (time() - $start > $this->timeout) {
                $this->status = -100;
                $this->error = 'Timeout while reading headers';
                return false;
            }
            if (feof($socket)) {
                $this->error = 'Premature End of File (socket)';
                return false;
            }
            $r_headers .= fread($socket, 1); #FIXME read full lines here?
        } while (!preg_match('/\r\n\r\n$/', $r_headers));

        $this->_debug('response headers', $r_headers);

        // check if expected body size exceeds allowance
        if ($this->max_bodysize && preg_match('/\r\nContent-Length:\s*(\d+)\r\n/i', $r_headers, $match)) {
            if ($match[1] > $this->max_bodysize) {
                $this->error = 'Reported content length exceeds allowed response size';
                if (!$this->max_bodysize_limit)
                    return false;
            }
        }

        // get Status
        if (!preg_match('/^HTTP\/(\d\.\d)\s*(\d+).*?\n/', $r_headers, $m)) {
            $this->error = 'Server returned bad answer';
            return false;
        }
        $this->status = $m[2];

        // handle headers and cookies
        $this->resp_headers = $this->_parseHeaders($r_headers);
        if (isset ($this->resp_headers['set-cookie'])) {
            foreach ((array) $this->resp_headers['set-cookie'] as $c) {
                list ($key, $value, $foo) = split('=', $cookie);
                $this->cookies[$key] = $value;
            }
        }

        $this->_debug('Object headers', $this->resp_headers);

        // check server status code to follow redirect
        if ($this->status == 301 || $this->status == 302) {
            if (empty ($this->resp_headers['location'])) {
                $this->error = 'Redirect but no Location Header found';
                return false;
            }
            elseif ($this->redirect_count == $this->max_redirect) {
                $this->error = 'Maximum number of redirects exceeded';
                return false;
            } else {
                $this->redirect_count++;
                $this->referer = $url;
                if (!preg_match('/^http/i', $this->resp_headers['location'])) {
                    $this->resp_headers['location'] = $uri['scheme'] . '://' . $uri['host'] .
                    $this->resp_headers['location'];
                }
                // perform redirected request, always via GET (required by RFC)
                return $this->sendRequest($this->resp_headers['location'], array (), 'GET');
            }
        }

        // check if headers are as expected
        if ($this->header_regexp && !preg_match($this->header_regexp, $r_headers)) {
            $this->error = 'The received headers did not match the given regexp';
            return false;
        }

        //read body (with chunked encoding if needed)
        $r_body = '';
        if (preg_match('/transfer\-(en)?coding:\s*chunked\r\n/i', $r_header)) {
            do {
                unset ($chunk_size);
                do {
                    if (feof($socket)) {
                        $this->error = 'Premature End of File (socket)';
                        return false;
                    }
                    if (time() - $start > $this->timeout) {
                        $this->status = -100;
                        $this->error = 'Timeout while reading chunk';
                        return false;
                    }
                    $byte = fread($socket, 1);
                    $chunk_size .= $byte;
                } while (preg_match('/[a-zA-Z0-9]/', $byte)); // read chunksize including \r

                $byte = fread($socket, 1); // readtrailing \n
                $chunk_size = hexdec($chunk_size);
                $this_chunk = fread($socket, $chunk_size);
                $r_body .= $this_chunk;
                if ($chunk_size)
                    $byte = fread($socket, 2); // read trailing \r\n

                if ($this->max_bodysize && strlen($r_body) > $this->max_bodysize) {
                    $this->error = 'Allowed response size exceeded';
                    if ($this->max_bodysize_limit)
                        break;
                    else
                        return false;
                }
            }
            while ($chunk_size);
        } else {
            // read entire socket
            while (!feof($socket)) {
                if (time() - $start > $this->timeout) {
                    $this->status = -100;
                    $this->error = 'Timeout while reading response';
                    return false;
                }
                $r_body .= fread($socket, 4096);
                if ($this->max_bodysize && strlen($r_body) > $this->max_bodysize) {
                    $this->error = 'Allowed response size exceeded';
                    if ($this->max_bodysize_limit)
                        break;
                    else
                        return false;
                }
            }
        }

        // close socket
        $status = socket_get_status($socket);
        fclose($socket);

        // decode gzip if needed
        if ($this->resp_headers['content-encoding'] == 'gzip') {
            $this->resp_body = gzinflate(substr($r_body, 10));
        } else {
            $this->resp_body = $r_body;
        }

        $this->_debug('response body', $this->resp_body);
        $this->redirect_count = 0;
        return true;
    }

}
