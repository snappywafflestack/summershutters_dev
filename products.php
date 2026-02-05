<?php
/**
 * WHCC Order API Integration
 *
 * This script integrates with the WHCC (White House Custom Colour) Order Submit API
 * to request an access token and retrieve the full product catalog.
 *
 * API Documentation: https://developer.whcc.com/pages/order-submit-api/
 */

// Configuration
define('WHCC_SANDBOX_URL', 'https://sandbox.apps.whcc.com');
define('WHCC_PRODUCTION_URL', 'https://apps.whcc.com/api');
define('WHCC_CONSUMER_KEY', '04A2246C17215BAD7297');
define('WHCC_CONSUMER_SECRET', '98G80xqWaWg=');
define('USE_SANDBOX', true);
define('ERROR_LOG_FILE', __DIR__ . '/errors.log');

// Determine which URL to use
$api_url = USE_SANDBOX ? WHCC_SANDBOX_URL : WHCC_PRODUCTION_URL;

/**
 * Log error to file
 *
 * @param string $message The error message to log
 *
 * @return void
 */
function logError($message)
{
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents(ERROR_LOG_FILE, $logMessage, FILE_APPEND);
}

/**
 * Request Access Token
 * https://developer.whcc.com/pages/order-submit-api/request-access-token/
 *
 * @return string|false The token ID or false on failure
 */
function requestAccessToken()
{
    $url = WHCC_SANDBOX_URL . '/api/AccessToken';

    $postData = [
        'grant_type' => 'consumer_credentials',
        'consumer_key' => WHCC_CONSUMER_KEY,
        'consumer_secret' => WHCC_CONSUMER_SECRET
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch))
    {
        $error = curl_error($ch);
        curl_close($ch);
        logError("requestAccessToken() - cURL Error: " . $error);

        return false;
    }

    curl_close($ch);

    if ($http_code !== 200)
    {
        logError("requestAccessToken() - HTTP Error Code: " . $http_code . " | Response: " . substr($response, 0, 500));

        return false;
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE)
    {
        logError("requestAccessToken() - JSON Error: " . json_last_error_msg() . " | Response: " . substr($response, 0, 500));

        return false;
    }

    // Extract token ID from JSON response
    if (isset($data['Token']))
    {
        return $data['Token'];
    }

    // Log the response structure to understand what we received
    logError("requestAccessToken() - No token found in response. Response structure: " . print_r($data, true));

    return false;
}

/**
 * Grab Full Product Catalog
 * https://developer.whcc.com/pages/order-submit-api/request-catalog/
 *
 * @param string $token The bearer token for authentication
 *
 * @return array|false The catalog data or false on failure
 */
function getProductCatalog($token)
{
    $url = (USE_SANDBOX ? WHCC_SANDBOX_URL : WHCC_PRODUCTION_URL) . '/api/catalog/';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch))
    {
        $error = curl_error($ch);
        curl_close($ch);
        logError("WHCC API Error: " . $error);

        return false;
    }

    curl_close($ch);

    if ($http_code !== 200)
    {
        logError("WHCC API HTTP Error: " . $http_code);

        return false;
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE)
    {
        logError("WHCC API JSON Error: " . json_last_error_msg());

        return false;
    }

    return $data;
}

/**
 * Save catalog to file using print_r
 *
 * @param array  $catalog  The catalog data
 * @param string $filename The filename to save to
 *
 * @return bool Success status
 */
function saveCatalogToFile($catalog, $filename = 'whcc_catalog.txt')
{
    $output = print_r($catalog, true);
    $filepath = __DIR__ . '/' . $filename;
    
    $result = file_put_contents($filepath, $output);
    
    if ($result === false)
    {
        logError("Failed to save catalog to file: " . $filepath);

        return false;
    }
    
    echo "Catalog saved to: " . $filepath . "\n";
    
    return true;
}

// Main execution
if (php_sapi_name() === 'cli' || !empty($_GET['run']))
{
    echo "WHCC Order API Integration\n";
    echo "==========================\n\n";
    
    // Step 1: Request Access Token
    echo "Step 1: Requesting access token...\n";
    $token = requestAccessToken();
    
    if (!$token)
    {
        echo "Error: Failed to retrieve access token.\n";
        exit(1);
    }
    
    echo "Token retrieved successfully.\n\n";
    
    // Step 2: Grab Full Product Catalog
    echo "Step 2: Fetching product catalog...\n";
    $catalog = getProductCatalog($token);
    
    if (!$catalog)
    {
        echo "Error: Failed to retrieve product catalog.\n";
        exit(1);
    }
    
    echo "Catalog retrieved successfully.\n\n";
    
    // Step 3: Save to file
    echo "Step 3: Saving catalog to file...\n";
    $success = saveCatalogToFile($catalog);
    
    if ($success)
    {
        echo "\nProcess completed successfully!\n";
        echo "Total products: " . (isset($catalog['products']) ? count($catalog['products']) : 'N/A') . "\n";
    }
    else
    {
        echo "Error: Failed to save catalog.\n";
        exit(1);
    }
}
else
{
    echo "<!DOCTYPE html>\n";
    echo "<html><body>\n";
    echo "<h1>WHCC Product Catalog Integration</h1>\n";
    echo "<p>To run this script, either:</p>\n";
    echo "<ul>\n";
    echo "<li>Run from command line: <code>php products.php</code></li>\n";
    echo "<li>Add ?run=1 to the URL: <a href='?run=1'>Click here to run</a></li>\n";
    echo "</ul>\n";
    echo "</body></html>\n";
}
