<?php

namespace App\Traits;

use Error;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

trait DataCompression
{
    /**
     * Compress a directory using maximum compression.
     *
     * @param string $source The source directory to compress.
     * @param string $destination The path to the output zip file.
     * @return bool Returns true on success, false on failure.
     * @source https://chatgpt.com/c/fa1339de-bec9-4f30-9aa2-698a22f4c51f
     */
    function compressDirectory(string $source, string $destination)
    {
        // verifying dependencies
        if (!extension_loaded('zip')) {
            throw new Error('php\'s zip extension is not installed.');
        }
        if (!file_exists($source)) {
            throw new Error('Unable to locate directory: ' . $source);
        }

        // compressing data
        $zip = new ZipArchive();
        if (!$zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            return false;
        }

        $source = realpath($source);
        if (is_dir($source)) {
            $iterator = new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file) {
                $file = realpath($file);

                if (is_dir($file)) {
                    $zip->addEmptyDir(str_replace($source . DIRECTORY_SEPARATOR, '', $file . DIRECTORY_SEPARATOR));
                } elseif (is_file($file)) {
                    $localName = str_replace($source . DIRECTORY_SEPARATOR, '', $file);
                    $zip->addFile($file, $localName);
                    $zip->setCompressionName($localName, ZipArchive::CM_DEFLATE, 9); // Set maximum compression
                }
            }
        } elseif (is_file($source)) {
            $localName = basename($source);
            $zip->addFile($source, $localName);
            $zip->setCompressionName($localName, ZipArchive::CM_DEFLATE, 9); // Set maximum compression
        }

        return $zip->close();
    }
    /*
        // Example usage:
        $sourceDir = 'path/to/source/directory';
        $destinationZip = 'path/to/destination/compressed.zip';

        if (compressDirectory($sourceDir, $destinationZip)) {
            echo 'Directory successfully compressed.';
        } else {
            echo 'Failed to compress directory.';
        }
    */
}
