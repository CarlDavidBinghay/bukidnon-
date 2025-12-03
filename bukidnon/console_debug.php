<?php
// Source - https://stackoverflow.com/a
// Posted by Senador, modified by community. See post 'Timeline' for change history
// Retrieved 2025-12-03, License - CC BY-SA 4.0

if (!function_exists('debug_to_console')) {
    /**
     * Send PHP variable to browser console (or print to CLI).
     *
     * @param mixed  $data  Data to log
     * @param string $label Optional label shown before the data
     */
    function debug_to_console($data, $label = '')
    {
        // CLI: print readable output
        if (php_sapi_name() === 'cli' || defined('STDIN')) {
            if ($label !== '') {
                echo $label . ': ';
            }
            if (is_scalar($data)) {
                echo $data . PHP_EOL;
            } else {
                print_r($data);
            }
            return;
        }

        // Try to JSON encode safely for embedding into <script>
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            // Fallback to printable string if encoding fails
            $json = json_encode(print_r($data, true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                $json = '"[unserializable data]"';
            }
        }

        // Prepare label if provided
        $labelPart = '';
        if ($label !== '') {
            $labelPart = json_encode($label) . ', ';
        }

        // Output a safe script tag
        echo "<script>console.log(" . $labelPart . $json . ");</script>";
    }
}
