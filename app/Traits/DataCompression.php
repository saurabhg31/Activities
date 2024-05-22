<?php

namespace App\Traits;

use Error;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

trait DataCompression
{
    /**
     * Remove n number of last lines (uses recursion)
     * @return true
     */
    private function removeLastLines(int $lineCount)
    {
        if ($lineCount) {
            print("\033[1A\033[K");
            return $this->removeLines($lineCount--);
        }
        return true;
    }

    /**
     * Compress a directory using maximum compression.
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
            $totalFileCount = iterator_count($files);
            $processedFileCount = $progress = 0;
            foreach ($files as $file) {
                print('                . Progress : ' . str_repeat('.', floor(($progress / 100) * 20)) . ' ' . $progress . ' %' . PHP_EOL);
                $file = realpath($file);
                if (is_dir($file)) {
                    $zip->addEmptyDir(str_replace($source . DIRECTORY_SEPARATOR, '', $file . DIRECTORY_SEPARATOR));
                } elseif (is_file($file)) {
                    $localName = str_replace($source . DIRECTORY_SEPARATOR, '', $file);
                    $zip->addFile($file, $localName);
                    $zip->setCompressionName($localName, ZipArchive::CM_DEFLATE, 9); // setting maximum compression (9)
                }
                $progress = ($processedFileCount / $totalFileCount) * 100;
                $this->removeLastLines(1);
            }
        } elseif (is_file($source)) {
            $localName = basename($source);
            $zip->addFile($source, $localName);
            $zip->setCompressionName($localName, ZipArchive::CM_DEFLATE, 9); // setting maximum compression (9)
        }

        return $zip->close();
    }
}
