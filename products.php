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
        logError("getProductCatalog() - HTTP Error: " . $http_code . " | Response: " . substr($response, 0, 500));

        return false;
    }

    // Save raw JSON response to file
    $filepath = __DIR__ . '/whcc_catalog.json';
    $saveResult = file_put_contents($filepath, $response);

    if ($saveResult === false)
    {
        logError("getProductCatalog() - Failed to save catalog JSON to file: " . $filepath);
    }
    else
    {
        echo "Catalog JSON saved to: " . $filepath . "\n";
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE)
    {
        logError("getProductCatalog() - JSON Error: " . json_last_error_msg() . " | Response: " . substr($response, 0, 500));

        return false;
    }

    return $data;
}


/**
 * Display categories and products in browser
 *
 * @param string $filename The catalog file to read
 *
 * @return void
 */
function displayBrowserView($filename = 'whcc_catalog.json')
{
    $filepath = __DIR__ . '/' . $filename;

    if (!file_exists($filepath))
    {
        echo "<!DOCTYPE html>\n";
        echo "<html><head><title>WHCC Catalog - Error</title></head><body>\n";
        echo "<h1>Error</h1>\n";
        echo "<p>Catalog file not found. <a href='?action=pull'>Click here to pull catalog from API</a></p>\n";
        echo "</body></html>\n";
        exit(1);
    }

    $content = file_get_contents($filepath);
    $catalog = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE)
    {
        echo "<!DOCTYPE html>\n";
        echo "<html><head><title>WHCC Catalog - Error</title></head><body>\n";
        echo "<h1>Error</h1>\n";
        echo "<p>Failed to parse catalog JSON: " . json_last_error_msg() . "</p>\n";
        echo "</body></html>\n";
        exit(1);
    }

    // Group products by category
    $categorizedProducts = [];
    $totalProducts = 0;

    if (isset($catalog['Categories']) && !empty($catalog['Categories']))
    {
        foreach ($catalog['Categories'] as $category)
        {
            $categoryName = $category['Name'] ?? 'Uncategorized';

            if (isset($category['ProductList']) && !empty($category['ProductList']))
            {
                $categorizedProducts[$categoryName] = $category['ProductList'];
                $totalProducts += count($category['ProductList']);
            }

            if (isset($category['Categories']) && !empty($category['Categories']))
            {
                foreach ($category['Categories'] as $subCategory)
                {
                    $subCategoryName = $subCategory['Name'] ?? 'Uncategorized';

                    if (isset($subCategory['ProductList']) && !empty($subCategory['ProductList']))
                    {
                        $fullCategoryName = $categoryName . ' > ' . $subCategoryName;
                        $categorizedProducts[$fullCategoryName] = $subCategory['ProductList'];
                        $totalProducts += count($subCategory['ProductList']);
                    }
                }
            }
        }
    }

    ksort($categorizedProducts);

    // Display HTML
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>WHCC Product Catalog</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 20px;
                line-height: 1.6;
            }

            .container {
                max-width: 1400px;
                margin: 0 auto;
                background: white;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                overflow: hidden;
            }

            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }

            .header h1 {
                font-size: 32px;
                margin-bottom: 10px;
            }

            .header .stats {
                display: flex;
                justify-content: center;
                gap: 40px;
                margin-top: 20px;
                font-size: 18px;
            }

            .header .stats div {
                background: rgba(255,255,255,0.2);
                padding: 10px 20px;
                border-radius: 5px;
            }

            .header .stats strong {
                font-size: 24px;
                display: block;
            }

            .toolbar {
                background: #f8f9fa;
                padding: 15px 30px;
                border-bottom: 1px solid #dee2e6;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .toolbar input {
                flex: 1;
                max-width: 400px;
                padding: 10px 15px;
                border: 2px solid #dee2e6;
                border-radius: 5px;
                font-size: 14px;
            }

            .toolbar input:focus {
                outline: none;
                border-color: #667eea;
            }

            .toolbar .actions {
                display: flex;
                gap: 10px;
            }

            .toolbar button, .toolbar a {
                padding: 10px 20px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.3s;
            }

            .toolbar button:hover, .toolbar a:hover {
                background: #5568d3;
            }

            .content {
                padding: 30px;
            }

            .category {
                margin-bottom: 40px;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                overflow: hidden;
            }

            .category-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                cursor: pointer;
                user-select: none;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .category-header:hover {
                background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
            }

            .category-header h2 {
                font-size: 24px;
            }

            .category-header .count {
                background: rgba(255,255,255,0.3);
                padding: 5px 15px;
                border-radius: 20px;
                font-size: 14px;
            }

            .category-header::before {
                content: '▼';
                margin-right: 10px;
                transition: transform 0.3s;
            }

            .category-header.collapsed::before {
                transform: rotate(-90deg);
            }

            .products {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
                padding: 20px;
                background: #f8f9fa;
            }

            .products.hidden {
                display: none;
            }

            .product {
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 20px;
                transition: all 0.3s;
            }

            .product:hover {
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                transform: translateY(-2px);
            }

            .product h3 {
                color: #333;
                font-size: 18px;
                margin-bottom: 10px;
            }

            .product .sku {
                color: #667eea;
                font-size: 12px;
                font-weight: bold;
                margin-bottom: 10px;
                display: inline-block;
                background: #f0f0ff;
                padding: 3px 8px;
                border-radius: 3px;
            }

            .product .description {
                color: #666;
                font-size: 14px;
                line-height: 1.5;
                margin-bottom: 10px;
            }

            .product .meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #dee2e6;
                font-size: 13px;
            }

            .product .price {
                color: #28a745;
                font-weight: bold;
                font-size: 16px;
            }

            .product .availability {
                color: #6c757d;
            }

            .product .availability.in-stock {
                color: #28a745;
            }

            .product .attributes {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #dee2e6;
            }

            .product .attributes h4 {
                font-size: 14px;
                color: #667eea;
                margin-bottom: 10px;
                font-weight: 600;
            }

            .product .attribute-list {
                display: grid;
                gap: 8px;
            }

            .product .attribute-item {
                display: flex;
                font-size: 13px;
                line-height: 1.4;
            }

            .product .attribute-key {
                font-weight: 600;
                color: #495057;
                min-width: 120px;
                flex-shrink: 0;
            }

            .product .attribute-value {
                color: #6c757d;
                word-break: break-word;
            }

            .product .attribute-value.array {
                font-style: italic;
            }

            .product .attribute-nested {
                margin-left: 0;
                margin-top: 5px;
                padding-left: 15px;
                border-left: 3px solid #667eea;
            }

            .product .attribute-nested-item {
                padding: 5px 0;
                font-size: 12px;
            }

            .product .attribute-nested-key {
                font-weight: 600;
                color: #667eea;
            }

            .product .attribute-nested-value {
                color: #495057;
                margin-left: 5px;
            }

            .no-products {
                text-align: center;
                padding: 40px;
                color: #6c757d;
            }

            .last-updated {
                text-align: center;
                padding: 20px;
                color: #6c757d;
                font-size: 14px;
                border-top: 1px solid #dee2e6;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>WHCC Product Catalog</h1>
                <div class="stats">
                    <div>
                        <strong><?php echo count($categorizedProducts); ?></strong>
                        Categories
                    </div>
                    <div>
                        <strong><?php echo $totalProducts; ?></strong>
                        Products
                    </div>
                </div>
            </div>

            <div class="toolbar">
                <input type="text" id="searchInput" placeholder="Search products or categories..." onkeyup="searchProducts()">
                <div class="actions">
                    <button onclick="expandAll()">Expand All</button>
                    <button onclick="collapseAll()">Collapse All</button>
                    <a href="?action=pull">Refresh Catalog</a>
                </div>
            </div>

            <div class="content">
                <?php if (empty($categorizedProducts)): ?>
                    <div class="no-products">
                        <h2>No products found</h2>
                        <p>The catalog appears to be empty. Try refreshing the catalog from the API.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($categorizedProducts as $categoryName => $products): ?>
                        <div class="category">
                            <div class="category-header" onclick="toggleCategory(this)">
                                <h2><?php echo htmlspecialchars($categoryName); ?></h2>
                                <span class="count"><?php echo count($products); ?> products</span>
                            </div>
                            <div class="products">
                                <?php foreach ($products as $product): ?>
                                    <div class="product">
                                        <h3><?php echo htmlspecialchars($product['Name'] ?? 'Unknown Product'); ?></h3>
                                        <?php if (isset($product['SKU']) || isset($product['ProductID'])): ?>
                                            <span class="sku">SKU: <?php echo htmlspecialchars($product['SKU'] ?? $product['ProductID']); ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($product['Description'])): ?>
                                            <div class="description"><?php echo htmlspecialchars($product['Description']); ?></div>
                                        <?php endif; ?>
                                        <div class="meta">
                                            <?php if (isset($product['Price'])): ?>
                                                <span class="price">$<?php echo number_format($product['Price'], 2); ?></span>
                                            <?php endif; ?>
                                            <?php if (isset($product['Available'])): ?>
                                                <span class="availability <?php echo $product['Available'] ? 'in-stock' : ''; ?>">
                                                    <?php echo $product['Available'] ? 'In Stock' : 'Out of Stock'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php
                                        // Display all product attributes
                                        $displayedKeys = ['Name', 'SKU', 'ProductID', 'Description', 'Price', 'Available'];
                                        $attributes = array_diff_key($product, array_flip($displayedKeys));

                                        if (!empty($attributes)):
                                        ?>
                                        <div class="attributes">
                                            <h4>Product Attributes</h4>
                                            <div class="attribute-list">
                                                <?php foreach ($attributes as $key => $value): ?>
                                                    <div class="attribute-item">
                                                        <span class="attribute-key"><?php echo htmlspecialchars($key); ?>:</span>
                                                        <span class="attribute-value <?php echo is_array($value) ? 'array' : ''; ?>">
                                                            <?php
                                                            // Special handling for specific attributes
                                                            if (in_array($key, ['ProductNodes', 'attributenodes', 'Attributenodes']) && is_array($value)) {
                                                                if (empty($value)) {
                                                                    echo '<span style="color: #999;">No nodes</span>';
                                                                } else {
                                                                    echo '<div class="attribute-nested">';
                                                                    foreach ($value as $node) {
                                                                        echo '<div class="attribute-nested-item">';
                                                                        if (is_array($node)) {
                                                                            // Display all keys in the node
                                                                            $nodeParts = [];
                                                                            foreach ($node as $nodeKey => $nodeValue) {
                                                                                if (!is_array($nodeValue)) {
                                                                                    $nodeParts[] = '<span class="attribute-nested-key">' . htmlspecialchars($nodeKey) . ':</span> <span class="attribute-nested-value">' . htmlspecialchars($nodeValue) . '</span>';
                                                                                }
                                                                            }
                                                                            echo implode(' | ', $nodeParts);
                                                                        } elseif (is_string($node)) {
                                                                            echo '<span class="attribute-nested-value">' . htmlspecialchars($node) . '</span>';
                                                                        } else {
                                                                            echo '<span class="attribute-nested-value">' . htmlspecialchars(json_encode($node)) . '</span>';
                                                                        }
                                                                        echo '</div>';
                                                                    }
                                                                    echo '</div>';
                                                                }
                                                            } elseif (in_array($key, ['AttributeCategories', 'attributeCategories']) && is_array($value)) {
                                                                if (empty($value)) {
                                                                    echo '<span style="color: #999;">No categories</span>';
                                                                } else {
                                                                    echo '<div class="attribute-nested">';
                                                                    foreach ($value as $category) {
                                                                        echo '<div class="attribute-nested-item">';
                                                                        if (is_array($category)) {
                                                                            // Display all keys in the category
                                                                            foreach ($category as $catKey => $catValue) {
                                                                                if ($catKey === 'Name' || $catKey === 'name') {
                                                                                    echo '<strong class="attribute-nested-key">' . htmlspecialchars($catValue) . '</strong><br>';
                                                                                } elseif ($catKey === 'Attributes' && is_array($catValue)) {
                                                                                    echo '<div style="margin-left: 15px; margin-top: 5px;">';
                                                                                    foreach ($catValue as $attr) {
                                                                                        echo '<div style="padding: 3px 0;">';
                                                                                        if (is_array($attr)) {
                                                                                            $attrParts = [];
                                                                                            foreach ($attr as $attrKey => $attrVal) {
                                                                                                if (!is_array($attrVal)) {
                                                                                                    $attrParts[] = htmlspecialchars($attrKey) . ': ' . htmlspecialchars($attrVal);
                                                                                                }
                                                                                            }
                                                                                            echo '<span class="attribute-nested-value">' . implode(', ', $attrParts) . '</span>';
                                                                                        } else {
                                                                                            echo '<span class="attribute-nested-value">' . htmlspecialchars($attr) . '</span>';
                                                                                        }
                                                                                        echo '</div>';
                                                                                    }
                                                                                    echo '</div>';
                                                                                } elseif (!is_array($catValue)) {
                                                                                    echo '<div style="margin-left: 15px;"><span class="attribute-nested-key">' . htmlspecialchars($catKey) . ':</span> <span class="attribute-nested-value">' . htmlspecialchars($catValue) . '</span></div>';
                                                                                }
                                                                            }
                                                                        } elseif (is_string($category)) {
                                                                            echo '<span class="attribute-nested-value">' . htmlspecialchars($category) . '</span>';
                                                                        } else {
                                                                            echo '<span class="attribute-nested-value">' . htmlspecialchars(json_encode($category)) . '</span>';
                                                                        }
                                                                        echo '</div>';
                                                                    }
                                                                    echo '</div>';
                                                                }
                                                            } elseif (in_array($key, ['bookAttributes', 'BookAttributes']) && is_array($value)) {
                                                                if (empty($value)) {
                                                                    echo '<span style="color: #999;">No book attributes</span>';
                                                                } else {
                                                                    echo '<div class="attribute-nested">';
                                                                    foreach ($value as $attrKey => $attrValue) {
                                                                        echo '<div class="attribute-nested-item">';
                                                                        echo '<span class="attribute-nested-key">' . htmlspecialchars($attrKey) . ':</span> ';
                                                                        if (is_array($attrValue)) {
                                                                            echo '<span class="attribute-nested-value">' . htmlspecialchars(json_encode($attrValue)) . '</span>';
                                                                        } elseif (is_bool($attrValue)) {
                                                                            echo '<span class="attribute-nested-value">' . ($attrValue ? 'Yes' : 'No') . '</span>';
                                                                        } elseif (is_null($attrValue)) {
                                                                            echo '<span class="attribute-nested-value" style="color: #999;">N/A</span>';
                                                                        } else {
                                                                            echo '<span class="attribute-nested-value">' . htmlspecialchars($attrValue) . '</span>';
                                                                        }
                                                                        echo '</div>';
                                                                    }
                                                                    echo '</div>';
                                                                }
                                                            } elseif (is_array($value)) {
                                                                // Generic array handling for other attributes
                                                                echo htmlspecialchars(json_encode($value));
                                                            } elseif (is_bool($value)) {
                                                                echo $value ? 'Yes' : 'No';
                                                            } elseif (is_null($value)) {
                                                                echo 'N/A';
                                                            } else {
                                                                echo htmlspecialchars($value);
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="last-updated">
                Last updated: <?php echo date('Y-m-d H:i:s', filemtime($filepath)); ?>
            </div>
        </div>

        <script>
            function toggleCategory(element) {
                element.classList.toggle('collapsed');
                const products = element.nextElementSibling;
                products.classList.toggle('hidden');
            }

            function expandAll() {
                document.querySelectorAll('.category-header').forEach(header => {
                    header.classList.remove('collapsed');
                    header.nextElementSibling.classList.remove('hidden');
                });
            }

            function collapseAll() {
                document.querySelectorAll('.category-header').forEach(header => {
                    header.classList.add('collapsed');
                    header.nextElementSibling.classList.add('hidden');
                });
            }

            function searchProducts() {
                const input = document.getElementById('searchInput');
                const filter = input.value.toLowerCase();
                const categories = document.querySelectorAll('.category');

                categories.forEach(category => {
                    const categoryName = category.querySelector('h2').textContent.toLowerCase();
                    const products = category.querySelectorAll('.product');
                    let categoryHasMatch = categoryName.includes(filter);
                    let visibleProducts = 0;

                    products.forEach(product => {
                        const productName = product.querySelector('h3').textContent.toLowerCase();
                        const sku = product.querySelector('.sku')?.textContent.toLowerCase() || '';
                        const description = product.querySelector('.description')?.textContent.toLowerCase() || '';

                        if (productName.includes(filter) || sku.includes(filter) || description.includes(filter) || filter === '') {
                            product.style.display = '';
                            visibleProducts++;
                            categoryHasMatch = true;
                        } else {
                            product.style.display = 'none';
                        }
                    });

                    if (categoryHasMatch && visibleProducts > 0) {
                        category.style.display = '';
                        // Auto-expand category if searching
                        if (filter) {
                            category.querySelector('.category-header').classList.remove('collapsed');
                            category.querySelector('.products').classList.remove('hidden');
                        }
                    } else {
                        category.style.display = filter ? 'none' : '';
                    }
                });
            }
        </script>
    </body>
    </html>
    <?php
}

// Main execution - Browser only
if (isset($_GET['action']) && $_GET['action'] === 'pull')
    {
        // Pull catalog from API
        echo "<!DOCTYPE html>\n";
        echo "<html><head><title>WHCC Catalog - Fetching</title>";
        echo "<style>body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }";
        echo ".status { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }";
        echo ".status h1 { color: #667eea; margin-bottom: 20px; }";
        echo ".status p { margin: 10px 0; line-height: 1.6; }";
        echo ".success { color: #28a745; font-weight: bold; }";
        echo ".error { color: #dc3545; font-weight: bold; }";
        echo "</style></head><body>\n";
        echo "<div class='status'>\n";
        echo "<h1>WHCC Order API Integration</h1>\n";

        // Step 1: Request Access Token
        echo "<p>Step 1: Requesting access token...</p>\n";
        flush();

        $token = requestAccessToken();

        if (!$token)
        {
            echo "<p class='error'>Error: Failed to retrieve access token.</p>\n";
            echo "<p><a href='products.php'>Back to Catalog</a></p>\n";
            echo "</div></body></html>\n";
            exit(1);
        }

        echo "<p class='success'>✓ Token retrieved successfully.</p>\n";
        flush();

        // Step 2: Grab Full Product Catalog
        echo "<p>Step 2: Fetching product catalog...</p>\n";
        flush();

        $catalog = getProductCatalog($token);

        if (!$catalog)
        {
            echo "<p class='error'>Error: Failed to retrieve product catalog.</p>\n";
            echo "<p><a href='products.php'>Back to Catalog</a></p>\n";
            echo "</div></body></html>\n";
            exit(1);
        }

        echo "<p class='success'>✓ Catalog retrieved successfully.</p>\n";

        // Count products
        $productCount = 0;
        if (isset($catalog['Categories']))
        {
            foreach ($catalog['Categories'] as $category)
            {
                if (isset($category['ProductList']))
                {
                    $productCount += count($category['ProductList']);
                }
                if (isset($category['Categories']))
                {
                    foreach ($category['Categories'] as $subCategory)
                    {
                        if (isset($subCategory['ProductList']))
                        {
                            $productCount += count($subCategory['ProductList']);
                        }
                    }
                }
            }
        }

        echo "<p class='success'>✓ Process completed successfully!</p>\n";
        echo "<p>Total products: <strong>{$productCount}</strong></p>\n";
        echo "<p style='margin-top: 20px;'><a href='products.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Catalog</a></p>\n";
        echo "</div></body></html>\n";
}
else
{
    // Display catalog in browser
    displayBrowserView();
}
