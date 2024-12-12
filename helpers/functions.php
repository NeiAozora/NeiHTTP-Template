<?php

use HttpSoft\Response\HtmlResponse;


function parseUrlParams($url) {
    $queryString = parse_url($url, PHP_URL_QUERY);
    parse_str($queryString, $params);
    return $params;
}

function createDirIfNotExist($dirname) {
    if (!file_exists($dirname) || !is_dir($dirname)) {
        mkdir($dirname, 0777, true);
    }
}



/**
 * Renders a view and returns an HtmlResponse.
 *
 * @param string $viewLocation The view name (e.g., 'pages.homepage').
 * @param array  $data         Data to pass to the view.
 *
 * @return \HttpSoft\Response\HtmlResponse
 * @throws \Exception If the view file doesn't exist.
 */
function view($viewLocation, $data = [])
{
    // Replace dots with slashes and append '.php' extension
    $viewLocation = str_replace('.', '/', $viewLocation) . '.php';

    $viewLocation = "view/" . $viewLocation;
    // Extract the data array to individual variables
    extract($data);

    // Check if the view file exists
    if (file_exists($viewLocation)) {
        // Start output buffering to capture the view's content
        ob_start();
        
        // Include the view file (this will use the extracted variables)
        include $viewLocation;

        // Get the captured content
        $content = ob_get_clean();

        // Return an HtmlResponse with the content
        return new HtmlResponse($content);
    } else {
        // Throw an exception if the view file does not exist
        throw new Exception("View not found: " . $viewLocation);
    }
}