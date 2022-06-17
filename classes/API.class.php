<?php
/*
 * Base class for mailer API's.
 * Request handling based on Drew McLellan's Mailchimp API class.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @author      Drew McLellan <drew.mclellan@gmail.com>
 * @copyright   Copyright (c) 2010-2021 Lee Garner <lee@leegarner.com>
 * @package     mailer
 * @version     v0.1.0
 * @license     http://opensource.org/licenses/MIT
 *              MIT License
 * @filesource
 *
 */
namespace Mailer;
use Mailer\Models\Subscriber;
use Mailer\Models\Campaign;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Base API class
 * @package mailer
 */
class API
{
    /** Collect errors.
     * @var array */
    protected $errors = array();

    /** List ID being used.
     * @var string */
    protected $list_id = '';

    /** Default list ID for each API provider.
     * @var string */
    protected $cfg_list_key = '';

    /** Endpoint URL.
     * @var string */
    protected $api_endpoint = '';

    /** Request timeout in seconds.
     * @const integer */
    const TIMEOUT = 10;

    /** Indicator of the request status.
     * @var boolean */
    private $request_successful = false;

    /** Last error encountered.
     * @var string */
    private $last_error = '';

    /** Last response received.
     * @var array */
    private $last_response = array();

    /** Details of last request.
     * @var array */
    private $last_request = array();

    /** HTTP Headers to send for authentication, etc.
     * @var array */
    private $http_headers = array();

    /** Attributes or Merge Fields.
     * @var array */
    protected $attributes = array();

    /** Provider name.
     * @var string */
    protected $name = '';

    /** Flag to indicate whether this API supportes test emails.
     * Default = false.
     * @var boolean */
    protected $supports_testing = false;


    /**
     * Get an instance of the API class.
     *
     * @param   string  $provider   Specific provider to instantiate
     * @return  object      API object
     */
    public static function getInstance(?string $provider=NULL) : self
    {
        static $api = NULL;
        if ($api === NULL) {
            $api = self::create($provider);
        }
        return $api;
    }


    /**
     * Create an instance of the API provider.
     *
     * @param   string  $api    API provider override
     * @return  object      API object
     */
    public static function create(?string $name=NULL) : self
    {
        if ($name === NULL) {
            $name = Config::get('provider');
        }

        $cls = '\\Mailer\\API\\' . $name . '\\API';
        if (class_exists($cls)) {
            $api = new $cls;
            $api->withName($name);
        } else {
            Log::write('system', Log::ERROR, __METHOD__ . ': Class $cls does not exist');
            $api = new self;
        }
        $api->withList();   // set the default list
        return $api;
    }


    /**
     * Set the name of the API provider.
     *
     * @param   string  $name   API provider name
     * @return  object  $this
     */
    protected function withName($name)
    {
        $this->name = $name;
        return $this;
    }


    /**
     * Get the name of the provider.
     *
     * @return  string      Provider name
     */
    public function getName() : string
    {
        return $this->name;
    }


    /**
     * Set the list ID for future operations.
     * Only affects third-party APIs.
     *
     * @param   string  $list_id    List ID to be used.
     * @return  object  $this
     */
    public function withList(?string $list_id=NULL) : self
    {
        if ($list_id === NULL) {
            $list_id = Config::get($this->cfg_list_key);
        }
        $this->list_id = $list_id;
        return $this;
    }


    /**
     * Validate a domain.
     * Checks for MX records and falls back to check for hostname.
     *
     * @param   string  $domain     Domain to check
     * @return  boolean     True for a valid domain, False if invalid
     */
    public static function isValidDomain(string $domain) : bool
    {
        $status = true;
        if (!@getmxrr($domain, $mxrecords)) {
            $list = gethostbynamel($domain);
            if (empty($list)) {
                $status = false;
            }
        }
        return $status;
    }


    /**
     * Check if the email address is valid.
     *
     * @return  boolean     True if valid, False if not
     */
    public function isValidEmail(string $email) : bool
    {
        $validator = new \EmailAddressValidator;
        return $validator->checkEmailAddress($email) ? true : false;
    }


    /**
     * Clear the Errors array. Used between operations.
     *
     * @retur   object  $this
     */
    protected function resetErrors() : self
    {
        $this->errors = array();
        return $this;
    }


    /**
     * Add an error message to the Errors array.
     *
     * @param   string  $msg    Error message
     * @return  object  $this
     */
    protected function addError(string $msg) : self
    {
        $this->errors[] = $msg;
        return $this;
    }


    /**
     * Get the contents of the Errors array.
     *
     * @return  array       Error messages
     */
    public function getErrors() : array
    {
        return $this->errors;
    }


    /**
     * Queue email to be sent.
     * For a public provider, this sends the message.
     *
     * @param   object  $Mlr    Mailer object
     * @param   array   $emails Email addresses (not used)
     * @return  integer     Status from sendEmail()
     */
    public function queueEmail(Campaign $Mlr, ?array $emails=NULL) : bool
    {
        return $this->createAndSend($Mlr);
    }


    /**
     * Create and immediately send a campaign.
     *
     * @param   object  $Mlr    Campaign object
     * @return  boolean     Status from sending
     */
    public function createAndSend($Mlr) : bool
    {
        $status = false;
        $id = $this->createCampaign($Mlr);
        if ($id) {
            $status = $this->sendCampaign($id);
        }
        return $status;
    }


    /**
     * Send the campaign.
     * A wrapper for each API's sending method, uses the return status to
     * show and log messages.
     *
     * @param   array   $emails     Email addresses (optional)
     * @param   string  $token      Token (not used)
     * @return  boolean     Result code
     */
    public function sendCampaign(Campaign $Mlr, ?array $emails=NULL, ?string $token=NULL) : bool
    {
        global $LANG_MLR;

        $status = $this->_sendCampaign($Mlr, $emails, $token);
        if (!$status) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $this->getLastResponse()['body']);
            COM_setMsg($this->getLastResponse()['body'], 'error', true);
        } else {
            COM_setMsg($LANG_MLR['success']);
        }
        return $status;
    }


    /**
     * Check if this mailer supports sending a test email.
     *
     * @return  boolean     True if supported, False if not.
     */
    public function supportsTesting() : bool
    {
        return $this->supports_testing;
    }


    /**
     * Send a test email. Not all APIs support this.
     *
     * @return  boolean     Status from sending email
     */
    public function sendTest(string $campaign_id) : bool
    {
        if ($this->supportsTesting()) {
            $status = $this->_sendTest($campaign_id);
            if (!$status) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $this->getLastResponse()['body']);
                COM_setMsg($this->getLastResponse()['body'], 'error', true);
            } else {
                COM_setMsg($LANG_MLR['success']);
            }
        } else {
            COM_setMsg($LANG_MLR['not_supported']);
        }
        return $status;
    }


    /**
     * Delete an email campaign.
     * Default to no-op as this must be provided by each API.
     *
     * @param   object  $Mlr    Campaign object
     * @return  boolean     Status from deletion request
     */
    public function deleteCampaign(Campaign $Mlr) : bool
    {
        return true;
    }


    /**
     * Convert an email address into a 'hash'.
     *
     * @param   string  $email  The subscriber's email address
     * @return  string          Hashed version of the input
     */
    public function subscriberHash(string $email) : string
    {
        return md5(strtolower($email));
    }


    /**
     * Get supported administrative features to show as menu options.
     * Possibilities are `campaigns`, `subscribers` and `queue`.
     * Third-party APIs may support campaigns and subscriber management.
     * The Internal API supports all three.
     *
     * @return  array       Array of supported feature keys
     */
    public function getFeatures() : array
    {
        return array('campaigns', 'subscribers');
    }


    /**
     * Send a double opt-in notification to a subscriber.
     * Only the Internal API actually sends notifications, the others rely
     * on the provider.
     *
     * @param   object  $Subscriber     Subscriber object
     * @return  boolean     True on success, False on error
     */
    public static function sendDoubleOptin(Subscriber $Sub) : bool
    {
        return true;
    }


    /**
     * Check if the API supports synchronizing the local table.
     * Actual providers do support this, the Internal provider does not.
     *
     * @return  boolean     True if synchronization is supported
     */
    public function supportsSync() : bool
    {
        return true;
    }


    /**
     * Record the mailer ID and provider's campaign ID after creation.
     *
     * @param   object  $Mlr    Campaign ID
     * @param   string  $provider_id    List provider's campaign ID
     * @param   boolean $tested     True if tested, False if not
     * @return  object  $this
     */
    protected function saveCampaignInfo(Campaign $Mlr, string $provider_id, int $tested=0) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->insert(
                $_TABLES['mailer_provider_campaigns'],
                array(
                    'mlr_id' => $Mlr->getID(),
                    'provider' => $this->name,
                    'provider_mlr_id' => $provider_id,
                ),
                array(Database::STRING, Database::STRING, Database::STRING)
            );
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
            try {
                $db->conn->update(
                    $_TABLES['mailer_provider_campaigns'],
                    array('provider_mlr_id' => $provider_id),
                    array('mlr_id' => $Mlr->getID()),
                    array(Database::STRING, Database::STRING)
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Make an HTTP GET request - for retrieving data.
     *
     * @param   string  $method     URL of the API request method
     * @param   array   $args       Assoc array of arguments (usually your data)
     * @param   integer $timeout    Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function get($method, $args = array(), $timeout = self::TIMEOUT)
    {
        return $this->makeRequest('get', $method, $args, $timeout);
    }


    /**
     * Make an HTTP PATCH request - for performing partial updates.
     *
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function patch($method, $args = array(), $timeout = self::TIMEOUT)
    {
        return $this->makeRequest('patch', $method, $args, $timeout);
    }


    /**
     * Make an HTTP POST request - for creating and updating items.
     *
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function post($method, $args = array(), $timeout = self::TIMEOUT)
    {
        return $this->makeRequest('post', $method, $args, $timeout);
    }

    /**
     * Make an HTTP PUT request - for creating new items.
     *
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function put($method, $args = array(), $timeout = self::TIMEOUT)
    {
        return $this->makeRequest('put', $method, $args, $timeout);
    }


    /**
     * Make an HTTP DELETE request - for deleting data.
     *
     * @param   string  $method     URL of the API request method
     * @param   array   $args       Assoc array of arguments (if any)
     * @param   integer $timeout    Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
    public function delete($method, $args = array(), $timeout = self::TIMEOUT)
    {
        return $this->makeRequest('delete', $method, $args, $timeout);
    }


    /**
     * Call an API method.
     * Every request needs the API key, so that is added automatically -- you don't need to pass it in.
     *
     * @param  string   $method The API method to call, e.g. 'lists/list'
     * @param  array    $args   An array of arguments to pass to the method. Will be json-encoded for you.
     * @return array            Associative array of json decoded API response.
     */
    public function call($method, $args=array())
    {
        return $this->makeRequest('get', $method, $args);
    }


    /**
     * Set the API-specific headers to send along with requests.
     *
     * @param   array   $headers    Array of header strings.
     * @return  object  $this
     */
    protected function setHeaders($headers)
    {
        $this->http_headers = $headers;
        return $this;
    }


    /**
     * Performs the underlying HTTP request. Not very exciting.
     *
     * @param   string  $http_verb  The HTTP verb to use: get, post, put, patch, delete
     * @param   string  $method     The API method to be called
     * @param   array   $args       Assoc array of parameters to be passed
     * @param   integer $timeout    Request timeout
     * @return  array|false Assoc array of decoded result
     * @throws  \Exception
     */
    private function makeRequest($http_verb, $method, $args = array(), $timeout = self::TIMEOUT)
    {
        if (!function_exists('curl_init') || !function_exists('curl_setopt')) {
            throw new \Exception("cURL support is required, but can't be found.");
        }

        $url = $this->api_endpoint . '/' . $method;
        Logger::Debug(__CLASS__ . '::' . __FUNCTION__ . "making request via $http_verb, method $method, to $url");
        Logger::Debug("Arguments: " . print_r($args,true));
        $response = $this->prepareStateForRequest($http_verb, $method, $url, $timeout);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->http_headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'glFusion/Mailer/' . Config::get('pi_version'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        switch ($http_verb) {
        case 'post':
                curl_setopt($ch, CURLOPT_POST, true);
                $this->attachRequestPayload($ch, $args);
                break;

            case 'get':
                $query = http_build_query($args, '', '&');
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
                break;

            case 'delete':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;

            case 'patch':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                $this->attachRequestPayload($ch, $args);
                break;

            case 'put':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                $this->attachRequestPayload($ch, $args);
                break;
        }

        $responseContent     = curl_exec($ch);
        $response['headers'] = curl_getinfo($ch);
        $response            = $this->setResponseState($response, $responseContent, $ch);
        $formattedResponse   = $this->formatResponse($response);
        curl_close($ch);

        return $this->determineSuccess($response, $formattedResponse, $timeout);
    }


    /**
     * Prepare local variables to receive data from the HTTP request.
     *
     * @param   string  $http_verb  HTTP verb (GET, POST, etc.)
     * @param   string  $method     API method being called
     * @param   string  $url        API Endpoint url, including $method
     * @param   integer $timeout    Request timeout in seconds
     */
    private function prepareStateForRequest($http_verb, $method, $url, $timeout)
    {
        $this->last_error = '';

        $this->request_successful = false;

        $this->last_response = array(
            'headers'     => null, // array of details from curl_getinfo()
            'httpHeaders' => null, // array of HTTP headers
            'body'        => null // content of the response
        );

        $this->last_request = array(
            'method'  => $http_verb,
            'path'    => $method,
            'url'     => $url,
            'body'    => '',
            'timeout' => $timeout,
        );

        return $this->last_response;
    }


    /**
     * Get the HTTP headers as an array of header-name => header-value pairs.
     *
     * The "Link" header is parsed into an associative array based on the
     * rel names it contains. The original value is available under
     * the "_raw" key.
     *
     * @param   string  $headersAsString    Header string
     * @return  array       Headers as an associative array
     */
    private function getHeadersAsArray($headersAsString)
    {
        $headers = array();

        foreach (explode("\r\n", $headersAsString) as $i => $line) {
            if ($i === 0) { // HTTP code
                continue;
            }

            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            list($key, $value) = explode(': ', $line);

            if ($key == 'Link') {
                $value = array_merge(
                    array('_raw' => $value),
                    $this->getLinkHeaderAsArray($value)
                );
            }

            $headers[$key] = $value;
        }

        return $headers;
    }


    /**
     * Extract all rel => URL pairs from the provided Link header value.
     *
     * Mailchimp only implements the URI reference and relation type from
     * RFC 5988, so the value of the header is something like this:
     *
     * 'https://us13.api.mailchimp.com/schema/3.0/Lists/Instance.json; rel="describedBy", <https://us13.admin.mailchimp.com/lists/members/?id=XXXX>; rel="dashboard"'
     *
     * @param   string  $linkHeaderAsString     Rel-type header as a string
     * @return  array       Associative array of the Rel header
     */
    private function getLinkHeaderAsArray($linkHeaderAsString)
    {
        $urls = array();

        if (preg_match_all('/<(.*?)>\s*;\s*rel="(.*?)"\s*/', $linkHeaderAsString, $matches)) {
            foreach ($matches[2] as $i => $relName) {
                $urls[$relName] = $matches[1][$i];
            }
        }

        return $urls;
    }


    /**
     * Encode the data and attach it to the request.
     *
     * @param   resource    $ch     cURL session handle, used by reference
     * @param   array       $data   Assoc array of data to attach
     */
    private function attachRequestPayload(&$ch, $data)
    {
        $encoded = json_encode($data);
        $this->last_request['body'] = $encoded;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
    }


    /**
     * Decode the response and format any error messages for debugging.
     *
     * @param   array   $response   The response from the curl request
     * @return  array|NULL      The JSON decoded into an array
     */
    public function formatResponse($response) : ?array
    {
        $this->last_response = $response;

        if (isset($response['body']) && !empty($response['body'])) {
            return json_decode($response['body'], true);
        } else {
            return NULL;
        }
    }


    /**
     * Do post-request formatting and setting state from the response.
     *
     * @param   array   $response       The response from the curl request
     * @param   string  $responseContent The body of the response from the curl request
     * @param   reference   $ch         Curl handler
     * @return  array    The modified response
     */
    private function setResponseState($response, $responseContent, $ch)
    {
        if ($responseContent === false) {
            $this->last_error = curl_error($ch);
        } else {

            $headerSize = $response['headers']['header_size'];

            $response['httpHeaders'] = $this->getHeadersAsArray(substr($responseContent, 0, $headerSize));
            $response['body'] = substr($responseContent, $headerSize);

            if (isset($response['headers']['request_header'])) {
                $this->last_request['headers'] = $response['headers']['request_header'];
            }
        }

        return $response;
    }


    /**
     * Check if the response was successful or a failure. If it failed, store the error.
     *
     * @param   array   $response   The response from the curl request
     * @param   array|false $formattedResponse  The response body payload from the curl request
     * @param   integer $timeout    The timeout supplied to the curl request.
     * @return  boolean     If the request was successful
     */
    private function determineSuccess($response, $formattedResponse, $timeout) : bool
    {
        if (empty($formattedResponse)) {
            $formattedResponse = NULL;
        }
        $status = $this->findHTTPStatus($response, $formattedResponse);

        if ($status >= 200 && $status <= 299) {
            $this->request_successful = true;
            return true;
        }

        if (isset($formattedResponse['detail'])) {
            $this->last_error = sprintf('%d: %s', $formattedResponse['status'], $formattedResponse['detail']);
            return false;
        }

        if( $timeout > 0 && $response['headers'] && $response['headers']['total_time'] >= $timeout ) {
            $this->last_error = sprintf('Request timed out after %f seconds.', $response['headers']['total_time'] );
            return false;
        }

        $this->last_error = 'Unknown error, call getLastResponse() to find out what happened.';
        return false;
    }


    /**
     * Find the HTTP status code from the headers or API response body.
     *
     * @param   array   $response   The response from the curl request
     * @param   array|NULL  $formattedResponse  The response body payload from the curl request
     * @return  integer     HTTP status code
     */
    private function findHTTPStatus(array $response, ?array $formattedResponse=NULL) : int
    {
        if (!$formattedResponse) {
            $formattedResponse = array();
        }

        if (!empty($response['headers']) && isset($response['headers']['http_code'])) {
            return (int)$response['headers']['http_code'];
        }

        if (!empty($response['body']) && isset($formattedResponse['status'])) {
            return (int)$formattedResponse['status'];
        }

        return 418;
    }


    /**
     * Get an array containing the HTTP headers and the body of the API response.
     *
     * @return  array  Assoc array with keys 'headers' and 'body'
     */
    public function getLastResponse() : array
    {
        return $this->last_response;
    }

    /**
     * Get an array containing the HTTP headers and the body of the API request.
     *
     * @return  array  Assoc array
     */
    public function getLastRequest() : array
    {
        return $this->last_request;
    }


    /**
     * Was the last request successful?
     *
     * @return  boolean     True for success, false for failure
     */
    public function success() : bool
    {
        return $this->request_successful;
    }


    /**
     * Get the last error returned by either the network transport, or by the API.
     * If something didn't work, this should contain the string describing the problem.
     *
     * @return  string|false  Describing the error
     */
    public function getLastError() : ?string
    {
        return $this->last_error ?: NULL;
    }


    /**
     * Add links to the menu help to perform actions for the gateway.
     *
     * @return  string      HTML for links, buttons, etc.
     */
    public function getMenuHelp() : string
    {
        return '';
    }


    /**
     * Handle API-specific actions requested through the admin page.
     */
    public function handleActions($opts) : void
    {
    }


    /**
     * List members in the mailing list.
     *
     * @param   string  $list_id    Mailing list ID
     * @param   array   $opts       Additional opts to consider
     * @return  array   Empty array
     */
    public function listMembers(string $list_id=NULL, array $opts=array()) : array
    {
        return array();
    }


    /**
     * Check if this API is configured.
     *
     * return   boolean     True if configured, False if not
     */
    public function isConfigured() : bool
    {
        return true;
    }

}
