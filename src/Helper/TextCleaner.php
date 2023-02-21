<?php

namespace Drutiny\Helper;

class TextCleaner {
    public static function decodeDirtyJson(string $output)
    {
        while (true) {
            $result = json_decode($output, true, JSON_THROW_ON_ERROR);

            if ($result === null) {
                $cleaned = substr($output, strpos($output, '{'));

                // Remove garbage from beginning of line and try again.
                if ($cleaned != $output) {
                    $output = $cleaned;
                    continue;
                }
            }
            return $result;
        }
    }
}