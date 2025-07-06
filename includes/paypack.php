<?php

class PaypackService
{
    private $baseUrl = 'https://payments.paypack.rw/api';
    private $clientId;
    private $clientSecret;
    private $accessToken;
    private $refreshToken;
    private $tokenExpires;

    public function __construct()
    {
        // Load Paypack credentials from environment variables
        $this->clientId = $_ENV['PAYPACK_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['PAYPACK_CLIENT_SECRET'] ?? '';

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('Paypack credentials not configured. Please set PAYPACK_CLIENT_ID and PAYPACK_CLIENT_SECRET in your .env file.');
        }
    }

    /**
     * Authenticate with Paypack and get access token
     */
    public function authenticate()
    {
        $url = $this->baseUrl . '/auth/agents/authorize';

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];

        $response = $this->makeRequest($url, 'POST', $data, $headers);

        // Debug: Log the raw response
        error_log("Paypack auth response: " . json_encode($response));

        if (isset($response['access'])) {
            $this->accessToken = $response['access'];
            $this->refreshToken = $response['refresh'];
            $this->tokenExpires = $response['expires'];

            // Store token in session for reuse
            $_SESSION['paypack_access_token'] = $this->accessToken;
            $_SESSION['paypack_refresh_token'] = $this->refreshToken;
            $_SESSION['paypack_token_expires'] = $this->tokenExpires;

            error_log("Paypack authentication successful. Token expires: " . $this->tokenExpires);
            return true;
        } else {
            error_log("Paypack authentication failed - unexpected response: " . json_encode($response));
            throw new Exception('Failed to authenticate with Paypack: Unexpected response format. Expected "access" token but got: ' . json_encode($response));
        }
    }

    /**
     * Get access token (authenticate if needed)
     */
    public function getAccessToken()
    {
        // Check if we have a valid token in session
        if (isset($_SESSION['paypack_access_token']) && isset($_SESSION['paypack_token_expires'])) {
            $this->accessToken = $_SESSION['paypack_access_token'];
            $this->refreshToken = $_SESSION['paypack_refresh_token'];
            $this->tokenExpires = $_SESSION['paypack_token_expires'];

            // Check if token is still valid (with 5 minute buffer)
            if (time() < ($this->tokenExpires - 300)) {
                return $this->accessToken;
            } else {
                // Token expired, try to refresh
                if ($this->refreshToken()) {
                    return $this->accessToken;
                }
            }
        }

        // No valid token, authenticate
        $this->authenticate();
        return $this->accessToken;
    }

    /**
     * Refresh the access token using refresh token
     */
    private function refreshToken()
    {
        // Note: Paypack documentation doesn't show refresh endpoint
        // For now, we'll re-authenticate
        try {
            $this->authenticate();
            return true;
        } catch (Exception $e) {
            error_log("Failed to refresh Paypack token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Make HTTP request to Paypack API
     */
    private function makeRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                $jsonData = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                error_log("Paypack request data: " . $jsonData);
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        // Debug: Log the raw HTTP response and code
        error_log("Paypack HTTP $httpCode response: $response");

        if ($error) {
            error_log("Paypack cURL error: " . $error);
            throw new Exception('cURL error: ' . $error);
        }

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            error_log("Paypack API error (HTTP $httpCode): " . $response);
            throw new Exception('Paypack API error: ' . ($decodedResponse['message'] ?? 'HTTP ' . $httpCode));
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Paypack JSON decode error: " . json_last_error_msg());
            throw new Exception('Invalid JSON response from Paypack: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }

    /**
     * Make authenticated request to Paypack API
     */
    private function makeAuthenticatedRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        $accessToken = $this->getAccessToken();

        $headers[] = 'Authorization: Bearer ' . $accessToken;

        return $this->makeRequest($url, $method, $data, $headers);
    }

    /**
     * Test the connection to Paypack
     */
    public function testConnection()
    {
        try {
            $accessToken = $this->getAccessToken();
            if ($accessToken && $this->tokenExpires) {
                return [
                    'success' => true,
                    'message' => 'Successfully authenticated with Paypack',
                    'token_expires' => $this->tokenExpires,
                    'token_length' => strlen($accessToken)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to get valid access token'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to connect to Paypack: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get current token information
     */
    public function getTokenInfo()
    {
        return [
            'has_token' => !empty($this->accessToken),
            'expires' => $this->tokenExpires,
            'expires_in' => $this->tokenExpires ? ($this->tokenExpires - time()) : 0,
            'is_valid' => $this->tokenExpires ? (time() < $this->tokenExpires) : false
        ];
    }

    /**
     * Perform a cashin (deposit) transaction
     * @param float $amount The amount to deposit
     * @param string $number The customer's phone number
     * @param string|null $idempotencyKey Optional unique key for idempotency
     * @return array Paypack API response
     * @throws Exception on error
     */
    public function cashin($amount, $number, $idempotencyKey = null)
    {
        $url = $this->baseUrl . '/transactions/cashin';
        $data = [
            'amount' => $amount,
            'number' => $number
        ];
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        if ($idempotencyKey) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        // Use authenticated request (adds Authorization header)
        $response = $this->makeAuthenticatedRequest($url, 'POST', $data, $headers);

        // Handle different response formats
        if (isset($response['count'])) {
            // Response contains a count, which might indicate a list of transactions
            error_log("Paypack cashin response contains count: " . json_encode($response));

            // If there's a count of 1, try to extract the transaction details
            if ($response['count'] == '1' || $response['count'] == 1) {
                // Look for transaction data in the response
                if (isset($response['transactions']) && is_array($response['transactions']) && count($response['transactions']) > 0) {
                    $transaction = $response['transactions'][0];
                    error_log("Extracted transaction from list: " . json_encode($transaction));
                    return $transaction;
                } elseif (isset($response['data']) && is_array($response['data']) && count($response['data']) > 0) {
                    $transaction = $response['data'][0];
                    error_log("Extracted transaction from data: " . json_encode($transaction));
                    return $transaction;
                } else {
                    // Try to find the transaction by phone and amount
                    error_log("Attempting to find transaction by phone and amount after cashin");
                    try {
                        $findResult = $this->findTransactionsByPhoneAndAmount($number, $amount, 'CASHIN', 1);
                        if (isset($findResult['transactions']) && count($findResult['transactions']) > 0) {
                            $mostRecentTransaction = $findResult['transactions'][0];
                            $extractedRef = $mostRecentTransaction['data']['ref'] ?? null;
                            if ($extractedRef) {
                                error_log("Found transaction reference from events: " . $extractedRef);
                                // Return a response with the found reference
                                return [
                                    'ref' => $extractedRef,
                                    'status' => $mostRecentTransaction['data']['status'] ?? 'pending',
                                    'amount' => $amount,
                                    'number' => $number,
                                    'found_via_events' => true,
                                    'original_response' => $response
                                ];
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Failed to find transaction via events: " . $e->getMessage());
                    }

                    // Return the original response but add a note about the format
                    $response['_note'] = 'Response contains count but no transaction details found. Try finding by phone and amount.';
                    return $response;
                }
            } else {
                // Multiple transactions or unexpected count
                $response['_note'] = 'Unexpected count in response: ' . $response['count'];
                return $response;
            }
        }

        // Standard response format (should contain ref)
        if (isset($response['ref'])) {
            error_log("Paypack cashin successful with ref: " . $response['ref']);
            return $response;
        }

        // If no ref found, log the response and return as-is
        error_log("Paypack cashin response without ref: " . json_encode($response));
        $response['_note'] = 'No transaction reference found in response';
        return $response;
    }

    /**
     * Find a transaction by reference
     * @param string $ref The transaction reference
     * @return array Paypack API response
     * @throws Exception on error
     */
    public function findTransaction($ref)
    {
        $url = $this->baseUrl . '/transactions/find/' . urlencode($ref);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        return $this->makeAuthenticatedRequest($url, 'GET', null, $headers);
    }

    /**
     * Get transaction events by reference, kind, client, and status
     * @param string $ref
     * @param string $kind (CASHIN or CASHOUT)
     * @param string $client (phone number)
     * @param string|null $status (pending, failed, successful)
     * @return array
     */
    public function getTransactionEvents($ref, $kind = 'CASHIN', $client = '', $status = null)
    {
        $url = $this->baseUrl . '/events/transactions?ref=' . urlencode($ref) . '&kind=' . urlencode($kind);
        if ($client)
            $url .= '&client=' . urlencode($client);
        if ($status)
            $url .= '&status=' . urlencode($status);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        return $this->makeAuthenticatedRequest($url, 'GET', null, $headers);
    }

    /**
     * Find recent transactions by phone number and amount (when no reference is available)
     * @param string $phoneNumber The phone number
     * @param float $amount The amount
     * @param string $kind (CASHIN or CASHOUT)
     * @param int $limit Maximum number of transactions to return
     * @return array
     */
    public function findTransactionsByPhoneAndAmount($phoneNumber, $amount, $kind = 'CASHIN', $limit = 10)
    {
        $url = $this->baseUrl . '/events/transactions?kind=' . urlencode($kind) . '&client=' . urlencode($phoneNumber) . '&limit=' . $limit;
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];

        $response = $this->makeAuthenticatedRequest($url, 'GET', null, $headers);

        // Filter transactions by amount if response contains transactions
        if (isset($response['transactions']) && is_array($response['transactions'])) {
            $filteredTransactions = array_filter($response['transactions'], function ($transaction) use ($amount) {
                return isset($transaction['data']['amount']) && abs($transaction['data']['amount'] - $amount) < 0.01;
            });
            $response['transactions'] = array_values($filteredTransactions);
            $response['count'] = count($filteredTransactions);
        }

        return $response;
    }

    /**
     * Extract transaction reference from various response formats
     * @param array $response The API response
     * @return string|null The transaction reference or null if not found
     */
    public function extractTransactionReference($response)
    {
        // Direct ref in response
        if (isset($response['ref'])) {
            return $response['ref'];
        }

        // Check if response contains transactions array
        if (isset($response['transactions']) && is_array($response['transactions']) && count($response['transactions']) > 0) {
            $transaction = $response['transactions'][0];
            if (isset($transaction['ref'])) {
                return $transaction['ref'];
            }
            if (isset($transaction['data']['ref'])) {
                return $transaction['data']['ref'];
            }
        }

        // Check if response contains data array
        if (isset($response['data']) && is_array($response['data']) && count($response['data']) > 0) {
            $transaction = $response['data'][0];
            if (isset($transaction['ref'])) {
                return $transaction['ref'];
            }
        }

        // Check for reference in nested structures
        if (isset($response['result']['ref'])) {
            return $response['result']['ref'];
        }

        return null;
    }

    /**
     * Check if a response indicates a successful transaction initiation
     * @param array $response The API response
     * @return bool True if the response indicates success
     */
    public function isTransactionInitiated($response)
    {
        // Check for direct success indicators
        if (isset($response['status']) && in_array(strtolower($response['status']), ['success', 'successful', 'pending', 'processing'])) {
            return true;
        }

        // Check for count indicating a transaction was created
        if (isset($response['count']) && ($response['count'] == '1' || $response['count'] == 1)) {
            return true;
        }

        // Check for ref indicating a transaction was created
        if (isset($response['ref'])) {
            return true;
        }

        // Check for transactions array with at least one transaction
        if (isset($response['transactions']) && is_array($response['transactions']) && count($response['transactions']) > 0) {
            return true;
        }

        return false;
    }
}

// Helper function to get Paypack service instance
function getPaypackService()
{
    static $paypackService = null;

    if ($paypackService === null) {
        try {
            $paypackService = new PaypackService();
        } catch (Exception $e) {
            error_log("Failed to initialize Paypack service: " . $e->getMessage());
            return null;
        }
    }

    return $paypackService;
}

?>