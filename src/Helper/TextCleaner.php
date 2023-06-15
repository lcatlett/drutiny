<?php

namespace Drutiny\Helper;

class TextCleaner {
    public static function decodeDirtyJson(string $output)
    {
        while (true) {
            $result = json_decode($output, true, JSON_THROW_ON_ERROR);

            if ($result === null) {
                $cleaned = substr($output, strpos($output, '{'));

                // If the cleaned output looks like the beginning of a
                // JSON object, lets also ensure the output at the end
                // is clean too.
                if (substr($cleaned, 0, 1) == '{') {
                    $rev = strrev($cleaned);
                    $rev = substr($rev, strpos($rev, '}'));

                    // Ignore the term '{main}' which comes from stack traces in PHP errors.
                    if (strpos($rev, '}niam{') === 0) {
                        $rev = substr($rev, 1);
                        $rev = substr($rev, strpos($rev, '}'));
                    }
                    $cleaned = strrev(substr($rev, strpos($rev, '}')));
                }

                // Remove garbage from beginning of line and try again.
                if ($cleaned != $output) {
                    $output = $cleaned;
                    continue;
                }
            }
            return $result;
        }
    }

    public static function machineValue(string $text): string
    {
        return str_replace(' ', '_', preg_replace('/[^a-z0-9\. ]/', '', strtolower($text)));
    }
}