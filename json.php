<?php
/**
 * JSON Catalog Viewer
 *
 * Displays the whcc_catalog.json file in a human-readable format in the browser
 */

$filepath = __DIR__ . '/whcc_catalog.json';

// Check if file exists
if (!file_exists($filepath))
{
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>JSON Viewer - Error</title></head><body>\n";
    echo "<h1>Error</h1>\n";
    echo "<p>Catalog file not found at: <code>{$filepath}</code></p>\n";
    echo "<p>Please run <code>php products.php</code> first to fetch the catalog.</p>\n";
    echo "</body></html>\n";
    exit(1);
}

// Read the JSON file
$content = file_get_contents($filepath);
$catalog = json_decode($content, true);

// Check for JSON errors
if (json_last_error() !== JSON_ERROR_NONE)
{
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>JSON Viewer - Error</title></head><body>\n";
    echo "<h1>Error</h1>\n";
    echo "<p>Failed to parse JSON: " . json_last_error_msg() . "</p>\n";
    echo "</body></html>\n";
    exit(1);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHCC Catalog JSON Viewer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .file-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }

        .file-info strong {
            color: #333;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab {
            padding: 10px 20px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-radius: 5px 5px 0 0;
            transition: all 0.3s;
        }

        .tab:hover {
            background: #e9ecef;
        }

        .tab.active {
            background: #007bff;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .json-structure {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .json-structure h2 {
            color: #007bff;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .json-tree {
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .json-key {
            color: #d73a49;
            font-weight: bold;
        }

        .json-string {
            color: #032f62;
        }

        .json-number {
            color: #005cc5;
        }

        .json-boolean {
            color: #d73a49;
        }

        .json-null {
            color: #6f42c1;
        }

        .json-bracket {
            color: #24292e;
            font-weight: bold;
        }

        .indent {
            margin-left: 20px;
        }

        .collapsible {
            cursor: pointer;
            user-select: none;
        }

        .collapsible:hover {
            background: #e9ecef;
        }

        .collapsible::before {
            content: '▼ ';
            display: inline-block;
            margin-right: 5px;
        }

        .collapsible.collapsed::before {
            content: '▶ ';
        }

        .collapsed-content {
            display: none;
        }

        pre {
            background: #f6f8fa;
            padding: 20px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.5;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-box h3 {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-box .number {
            font-size: 32px;
            font-weight: bold;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 16px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #007bff;
        }

        .highlight {
            background-color: yellow;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>WHCC Catalog JSON Viewer</h1>

        <div class="file-info">
            <strong>File:</strong> whcc_catalog.json<br>
            <strong>Size:</strong> <?php echo number_format(filesize($filepath)); ?> bytes<br>
            <strong>Last Modified:</strong> <?php echo date('Y-m-d H:i:s', filemtime($filepath)); ?>
        </div>

        <div class="stats">
            <?php
            // Calculate statistics
            $stats = [
                'Root Keys' => count(array_keys($catalog)),
                'Total Arrays' => 0,
                'Total Objects' => 0,
                'Max Depth' => 0
            ];

            function countElements($data, $depth = 0, &$stats)
            {
                if ($depth > $stats['Max Depth']) {
                    $stats['Max Depth'] = $depth;
                }

                if (is_array($data)) {
                    $stats['Total Arrays']++;
                    foreach ($data as $value) {
                        if (is_array($value)) {
                            countElements($value, $depth + 1, $stats);
                        }
                    }
                }
            }

            countElements($catalog, 0, $stats);

            foreach ($stats as $label => $value) {
                echo "<div class='stat-box'>";
                echo "<h3>{$label}</h3>";
                echo "<div class='number'>{$value}</div>";
                echo "</div>";
            }
            ?>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="showTab('structure')">Structure Overview</button>
            <button class="tab" onclick="showTab('formatted')">Formatted JSON</button>
            <button class="tab" onclick="showTab('raw')">Raw JSON</button>
        </div>

        <div id="structure" class="tab-content active">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search keys..." onkeyup="searchKeys()">
            </div>

            <div class="json-structure">
                <h2>JSON Structure</h2>
                <div class="json-tree" id="jsonTree">
                    <?php
                    function displayStructure($data, $key = 'root', $level = 0, $parentKey = '')
                    {
                        $indent = str_repeat('  ', $level);
                        $type = gettype($data);

                        if (is_array($data)) {
                            if (empty($data)) {
                                // Handle empty arrays
                                echo "<div>";
                                echo "<span class='json-key'>\"{$key}\"</span>: ";
                                echo "<span class='json-bracket'>[</span><span class='json-bracket'>]</span> ";
                                echo "<span style='color: #999; font-size: 11px;'>[empty]</span>";
                                echo "</div>";
                            } else {
                            $isAssoc = array_keys($data) !== range(0, count($data) - 1);

                            if ($isAssoc) {
                                echo "<div class='collapsible' onclick='toggleCollapse(this)'>";
                                echo "<span class='json-key'>\"{$key}\"</span>: ";
                                echo "<span class='json-bracket'>{</span> ";
                                echo "<span style='color: #6a737d;'>(" . count($data) . " keys)</span>";
                                echo "</div>";
                                echo "<div class='indent'>";

                                foreach ($data as $k => $v) {
                                    displayStructure($v, $k, $level + 1, $key);
                                }

                                echo "</div>";
                                echo "<div><span class='json-bracket'>}</span></div>";
                            } else {
                                // Special handling for specific array types
                                $shouldExpandAll = false;
                                $itemLimit = 3; // default

                                // Check if this is ProductNodes, AttributeCategories, bookAttributes, or attributenodes (case-insensitive)
                                $keyLower = strtolower($key);
                                if (in_array($keyLower, ['productnodes', 'attributecategories', 'bookattributes', 'attributenodes'])) {
                                    $shouldExpandAll = true;
                                    $itemLimit = count($data); // show all items
                                }

                                // Also expand if parent is ProductList (for products)
                                if ($parentKey === 'ProductList') {
                                    $shouldExpandAll = true;
                                    $itemLimit = count($data); // show all items
                                }

                                echo "<div class='collapsible' onclick='toggleCollapse(this)'>";
                                echo "<span class='json-key'>\"{$key}\"</span>: ";
                                echo "<span class='json-bracket'>[</span> ";
                                echo "<span style='color: #6a737d;'>(" . count($data) . " items)</span>";
                                if ($shouldExpandAll) {
                                    echo " <span style='color: #28a745; font-size: 11px;'>[expanded]</span>";
                                }
                                echo "</div>";
                                echo "<div class='indent'>";

                                // Show items based on limit
                                $count = 0;
                                foreach ($data as $k => $v) {
                                    if ($count < $itemLimit) {
                                        displayStructure($v, "[{$k}]", $level + 1, $key);
                                        $count++;
                                    } else {
                                        echo "<div style='color: #6a737d;'>... " . (count($data) - $itemLimit) . " more items</div>";
                                        break;
                                    }
                                }

                                echo "</div>";
                                echo "<div><span class='json-bracket'>]</span></div>";
                            }
                            }
                        } elseif (is_string($data)) {
                            $preview = strlen($data) > 100 ? substr($data, 0, 100) . '...' : $data;
                            echo "<div><span class='json-key'>\"{$key}\"</span>: ";
                            echo "<span class='json-string'>\"" . htmlspecialchars($preview) . "\"</span></div>";
                        } elseif (is_numeric($data)) {
                            echo "<div><span class='json-key'>\"{$key}\"</span>: ";
                            echo "<span class='json-number'>{$data}</span></div>";
                        } elseif (is_bool($data)) {
                            echo "<div><span class='json-key'>\"{$key}\"</span>: ";
                            echo "<span class='json-boolean'>" . ($data ? 'true' : 'false') . "</span></div>";
                        } elseif (is_null($data)) {
                            echo "<div><span class='json-key'>\"{$key}\"</span>: ";
                            echo "<span class='json-null'>null</span></div>";
                        } else {
                            echo "<div><span class='json-key'>\"{$key}\"</span>: ";
                            echo "<span style='color: #6a737d;'>[{$type}]</span></div>";
                        }
                    }

                    displayStructure($catalog);
                    ?>
                </div>
            </div>
        </div>

        <div id="formatted" class="tab-content">
            <h2>Formatted JSON</h2>
            <pre><?php echo htmlspecialchars(json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
        </div>

        <div id="raw" class="tab-content">
            <h2>Raw JSON</h2>
            <pre><?php echo htmlspecialchars($content); ?></pre>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));

            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function toggleCollapse(element) {
            element.classList.toggle('collapsed');
            const nextElement = element.nextElementSibling;
            if (nextElement && nextElement.classList.contains('indent')) {
                nextElement.classList.toggle('collapsed-content');
                // Also toggle the closing bracket
                const closingBracket = nextElement.nextElementSibling;
                if (closingBracket) {
                    closingBracket.classList.toggle('collapsed-content');
                }
            }
        }

        function searchKeys() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const tree = document.getElementById('jsonTree');
            const keys = tree.getElementsByClassName('json-key');

            for (let i = 0; i < keys.length; i++) {
                const keyText = keys[i].textContent || keys[i].innerText;
                if (keyText.toLowerCase().indexOf(filter) > -1) {
                    keys[i].parentElement.style.display = "";
                    // Highlight matching text
                    if (filter) {
                        const regex = new RegExp('(' + filter + ')', 'gi');
                        keys[i].innerHTML = keys[i].textContent.replace(regex, '<span class="highlight">$1</span>');
                    }
                } else {
                    if (filter) {
                        keys[i].parentElement.style.display = "none";
                    } else {
                        keys[i].parentElement.style.display = "";
                        keys[i].innerHTML = keys[i].textContent;
                    }
                }
            }
        }
    </script>
</body>
</html>
