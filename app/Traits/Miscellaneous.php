<?php

namespace App\Traits;

use App\Jobs\WriteDatabaseBackupChunks;
use Error;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use stdClass;

trait Miscellaneous
{
    /**
     * Flatten an array
     * @param array $array
     * @return array
     * @source: https://stackoverflow.com/a/1320156/12199939
     */
    private function flattenArray(array $array)
    {
        $return = array();
        array_walk_recursive($array, function ($a) use (&$return) {
            $return[] = $a;
        });
        return $return;
    }

    /**
     * Remove duplicates from an associative array based on a key
     * @param array $array
     * @param string $key - only depth=1 supported for now
     * @return void
     */
    private function removeDuplicatesByKey(array &$array, string $key)
    {
        $arrayIndicesToRemove = [];
        $uniqueVals = [];
        foreach ($array as $index => $value) {
            if (isset($value[$key])) {
                if (!in_array($value[$key], $uniqueVals)) {
                    array_push($uniqueVals, $value[$key]);
                } else {
                    array_push($arrayIndicesToRemove, $index);
                }
            }
        }
        unset($uniqueVals);
        if (!empty($arrayIndicesToRemove)) {
            $array = Arr::except($array, $arrayIndicesToRemove);
        }
    }

    /**
     * Read input from cli
     * @param integer $maxAllowedLines - Max input lines allowed
     * @param array $lineMsgs - Msg to display at each input line
     * @param callable $validateLine - function to validate each line, passes each line as an argument (must return boolean)
     * @param callable $terminate - function to stop reading lines when a condition is met, passes each line as an argument (must return boolean)
     * @param boolean $throwErrorIfLineValidationFails - stop execution and throw an error if validation fails for any line
     * @return array - returns input lines
     */
    private function readInputFromCli(
        int $maxAllowedLines = 1,
        array $lineMsgs = [],
        callable $validateLine = null,
        callable $terminate = null,
        bool $throwErrorIfLineValidationFails = false
    ) {
        $input = [];
        for ($i = 0; $i < $maxAllowedLines; $i++) {
            $line = readline($lineMsgs[$i] ?? null);
            if (is_callable($terminate) && $terminate($line)) {
                break;
            }
            if (is_callable($validateLine)) {
                if (!$validateLine($line)) {
                    if ($throwErrorIfLineValidationFails) {
                        throw new Exception('Invalid input error. Validation failed for line: ' . $i);
                    }
                } else {
                    array_push($input, $line);
                }
            } else {
                array_push($input, $line);
            }
        }
        return $input;
    }

    /**
     * Securely read & compare password input from cli (for linux only)
     * @param string $hashedPasswordString (hashed or encrypted password string to compare with)
     * @param string|callable $comparisonMethod (hash or callable function, must have two params in order of password & hashed password and should return a value)
     * @param string $prompt (Message to display for entering password)
     * @return boolean (true on success, false on failure)
     */
    private function readAndComparePasswordInputFromCli(
        string &$hashedPasswordString,
        string|callable $comparisonMethod = 'hash',
        string $prompt = "Enter Password: ",
    ) {
        $checkPassed = false;
        $command = "/usr/bin/env bash -c 'read -s -p \"" . addslashes($prompt) . "\" mypassword && echo \$mypassword'";
        $password = rtrim(shell_exec($command));
        print(PHP_EOL);
        unset($command);
        if ($comparisonMethod === 'hash') {
            $checkPassed = Hash::check($password, $hashedPasswordString);
        } else {
            $checkPassed = $comparisonMethod($password, $hashedPasswordString);
        }
        // flushing password out of memory
        $password = null;
        unset($password);
        return $checkPassed;
    }

    /**
     * Remove last line from cli, does not move cursor up to previous line, instead clears the last line for entry
     * @return void
     */
    private function removeLastLine()
    {
        print("\033[1A\033[K");
    }

    /**
     * Remove multiple lines from the bottom one by one in terminal, by default ($lineCount = 2)) moves the cursor up to previous line
     * @param integer $lineCount - number of lines to remove, must be greater than 0
     * @return void
     */
    private function removeMultipleLastLines(int $lineCount = 2)
    {
        if ($lineCount < 0) {
            throw new Error('$lineCount cannot be less than zero.');
        }
        for ($i = 0; $i < $lineCount; $i++) {
            $this->removeLastLine();
        }
    }

    /**
     * Checks if a multidimensional array has duplicate values
     * @param array $inputArray - the mutidimensional array
     * @return boolean - returns true if array has duplicate values, false otherwise
     */
    private function arrayHasDuplicateValues(array &$inputArray)
    {
        return !count(array_unique(array_map('json_encode', $inputArray))) == count($inputArray);
    }

    /**
     * Generate exists raw query string for a table from data
     * @param array $values
     * @param string $column
     * @param string $tableName
     * @param string|null $softDeleteColumn - pass null if deleted_at condition should not be added
     * @return string
     */
    private function generateExistsQuery(array $values, string $column, string $tableName, string|null $softDeleteColumn = 'deleted_at')
    {
        $values = implode(', ', array_map(function ($val) {
            return '"' . $val . '"';
        }, $values));
        $statement = "select `{$column}` from `{$tableName}` where `{$column}` in ({$values})";
        if (!is_null($softDeleteColumn)) {
            $statement .= " and `{$tableName}`.`{$softDeleteColumn}` is null";
        }
        return $statement;
    }

    /**
     * Generate raw insert query string for a table from data
     * @param array $data
     * @param string $tableName
     * @return string
     */
    private function generateRawInsertQuery(array &$data, string $tableName)
    {
        $columns = implode(', ', array_keys(reset($data)));
        $values = implode(', ', array_values(array_map(function ($row) {
            $row = array_map(function ($value) {
                return '"' . $value . '"';
            }, $row);
            return '(' . implode(', ', array_values($row)) . ')';
        }, $data)));
        return "INSERT INTO {$tableName} ({$columns}) VALUES {$values}";
    }

    /**
     * Determine number of maximum insertable rows at a time in mysql for a given array
     * @param array $dataset
     * @param integer $bufferRows - number of buffer rows for safety
     * @param string $considerExistsCheckOf - pass column name to check exist query
     * @return integer
     */
    private function findMaxAllowedInsertRows(array &$dataset, string $tableName = 'products', string $considerExistsCheckOf = null)
    {
        $maxAllowedBytes = (int)DB::select("show variables like 'max_allowed_packet'")[0]->Value;
        if ($considerExistsCheckOf) {
            if (in_array('deleted_at', Schema::getColumnListing($tableName))) {
                $softDeleteColumn = 'deleted_at';
            } else {
                $softDeleteColumn = null;
            }
            $values = array_column($dataset, $considerExistsCheckOf);
            $requiredBytes = strlen($this->generateExistsQuery(
                $values,
                $considerExistsCheckOf,
                $tableName,
                $softDeleteColumn
            ));
        } else {
            $requiredBytes = strlen($this->generateRawInsertQuery($dataset, $tableName));
        }
        $datasetLength = count($dataset);
        if ($requiredBytes <= $maxAllowedBytes) {
            return $datasetLength;
        } else {
            $requiredBytes = 0;
            for ($sliceLength = 1; $sliceLength <= $datasetLength; $sliceLength++) {
                $subset = array_slice($dataset, 0, $sliceLength);
                if ($considerExistsCheckOf) {
                    $values = array_column($subset, $considerExistsCheckOf);
                    $requiredBytes = strlen($this->generateExistsQuery(
                        $values,
                        $considerExistsCheckOf,
                        $tableName,
                        $softDeleteColumn
                    ));
                } else {
                    $requiredBytes = strlen($this->generateRawInsertQuery($subset, $tableName));
                }
                if ($requiredBytes == $maxAllowedBytes) {
                    break;
                } elseif ($requiredBytes > $maxAllowedBytes) {
                    $sliceLength--;
                }
            }
            return $sliceLength;
        }
    }

    /**
     * Append key value pair to .env file
     * @param string $key - CAUTION: The $key must not exist as commented line in .env
     * @param string $value
     * @return boolean - true on successful write, false otherwise
     */
    private function appendToEnv(string $key, string $value)
    {
        $value = trim($value);
        $envData = file_get_contents(app()->environmentFilePath());
        if (!str_contains($envData, $key . '=')) {
            $envData .= PHP_EOL . $key . '="' . $value . '"' . PHP_EOL;
            return (bool)file_put_contents(app()->environmentFilePath(), $envData);
        } else {
            return (bool)file_put_contents(app()->environmentFilePath(), str_replace(
                [$key . '=' . env($key), $key . '="' . env($key) . '"'],
                $key . '="' . $value . '"',
                $envData
            ));
        }
    }

    /**
     * Delay excution
     * @param integer seconds
     * @return void
     */
    private function delayExecution(int $seconds)
    {
        if ($seconds < 1) {
            throw new Exception('Argument $seconds must be at least 1.');
        }
        $endTime = now()->addSeconds($seconds);
        while (now()->lte($endTime)) {
            continue;
        }
    }

    /**
     * Attempt http requests - compensates for http request failures
     * @param string $url
     * @param string $method
     * @param array $payload
     * @param array $headers
     * @param integer $maxAttempts - maximum number of tries
     * @param boolean $throwErrorOnFailure
     * @param integer $attempt - attempt counter, do not pass while calling
     * @return \Illuminate\Http\Client\Response|false
     */
    private function attemptRequest(
        string $url,
        string $method,
        array $payload = null,
        array $headers = [],
        int $maxAttempts = 3,
        bool $throwErrorOnFailure = true,
        int $attempt = 1
    ) {
        $request = Http::withHeaders($headers);
        try {
            switch (strtoupper($method)) {
                case 'GET':
                    $request = $request->get($url, $payload);
                    break;
                case 'POST':
                    $request = $request->post($url, $payload ? $payload : []);
                    break;
                default:
                    throw new Exception('Unsupported request method.');
            }
            return $request;
        } catch (Exception $err) {
            if ($attempt <= $maxAttempts) {
                $attempt++;
                return $this->attemptRequest(
                    $url,
                    $method,
                    $payload,
                    $headers,
                    $maxAttempts,
                    $throwErrorOnFailure,
                    $attempt
                );
            }
            if ($throwErrorOnFailure) {
                throw $err;
            }
            return false;
        }
    }

    /**
     * Get all tables from application database
     * @return array
     */
    private function getAllTables()
    {
        $key = 'Tables_in_' . getenv('DB_DATABASE');
        return array_map(function ($obj) use (&$key) {
            return $obj->$key;
        }, DB::select('SHOW TABLES;'));
    }

    /**
     * Function to execute shell commands
     * @param string $command
     * @param boolean $returnOutput (if true return output as string, nothing is echoed)
     * @param boolean $silent (does not echo running command if passed as true and logs it instead)
     * @return boolean|string True if command is successful, false otherwise
     */
    private function executeCommand(string $command, bool $returnOutput = false, bool $silent = false)
    {
        if ($returnOutput) {
            return shell_exec($command);
        }
        if (!$silent) {
            print('    . Running command: <~ ' . $command . ' ~>' . PHP_EOL);
            // TODO: Add command logging code
        }
        $output = [];
        $resultCode = null;
        exec($command, $output, $resultCode);
        return $resultCode === 0;
    }

    /**
     * Get output from raw query
     * @param string $rawQuery
     * @param boolean|callable $autoprocess (if set to true, analyzes the query output and returns optimized result(s), if passed a function, executes the function with query output at first argument and return the result)
     * @return mixed
     */
    private function getRawQueryOutput(string $rawQuery, bool|callable $autoprocess = true)
    {
        $output = DB::select($rawQuery);
        if ($autoprocess === false) {
            return $output;
        } elseif (gettype($autoprocess) == 'object') {
            return $autoprocess($output);
        }
        $outputCount = count($output);
        $processedOutput = null;
        if (gettype($output) == 'array') {
            if (!$outputCount) {
                $processedOutput = null;
            } elseif ($outputCount == 1) {
                $processedOutput = reset($output);
            } else {
                $processedOutput = $output;
            }
        }
        return $processedOutput;
    }

    /**
     * Function to check if queue daemon is running
     * @return boolean (true if running, false otherwise)
     */
    private function checkQueueStatus()
    {
        $queueDaemonRunning = false;
        $lineParts = [];
        $commandToLookFor = 'php artisan queue:work';
        $output = shell_exec('ps xw | grep "' . $commandToLookFor . '"');
        foreach (explode(PHP_EOL, $output) as $line) {
            $lineParts = array_filter(array_map(function ($part) {
                return trim($part);
            }, explode(' ', $line)));
            if (count($lineParts) == 7) {
                $queueDaemonRunning = true;
                break;
            }
        }
        return $queueDaemonRunning;
    }

    /**
     * Function to check available free memory in a linux system
     * @param array $minimumMemoryRequired (an array containing keys: "normal" & "swap" with integer values of required free memory size in KB)
     * @return boolean (true is enough free memory is present, false otherwise)
     */
    private function minimumFreeMemoryIsAvailable(array $minimumMemoryRequired)
    {
        $memoryAvailable = [
            'normal' => 0, // in KB
            'swap' => 0 // in KB
        ];
        $memoryShortBy = [
            'normal' => 0, // in KB
            'swap' => 0 // in KB
        ];
        $freeMemoryDetails = explode(PHP_EOL, $this->executeCommand('free', true));
        unset($freeMemoryDetails[0]);
        $freeMemoryDetails = array_map(function ($line) use (&$memoryAvailable) {
            $lineParts = array_filter(explode(' ', $line));
            if (reset($lineParts) == 'Mem:') {
                $memoryAvailable['normal'] = (int)Arr::last($lineParts);
            } elseif (reset($lineParts) == 'Swap:') {
                $memoryAvailable['swap'] = (int)Arr::last($lineParts);
            }
        }, $freeMemoryDetails);
        // comparing available memory with requirements
        foreach (['normal', 'swap'] as $memoryType) {
            if ($memoryAvailable[$memoryType] < $minimumMemoryRequired[$memoryType]) {
                $memoryShortBy[$memoryType] = ($minimumMemoryRequired[$memoryType] - $memoryAvailable[$memoryType]) / pow(2, 10); // in MB
            }
        }
        if ($memoryShortBy['normal'] + $memoryShortBy['swap'] > 0) {
            return false;
        }
        return true;
    }

    /**
     * Securely authenticate user via terminal
     * @param string|\Illuminate\Database\Eloquent\Model $table (must be the table name if string)
     * @param array $options (key value pairs that should match)
     * @return boolean
     */
    private function authenticateUserViaTerminal(string|Model &$table, array &$options = ['is_admin' => 1])
    {
        $authCheckStatus = false;
        $email = $this->readInputFromCli(1, ['        . Enter admin account email: ']);
        $email = reset($email);
        if ($table instanceof Model) {
            $hashedPasswordString = $table->where(array_merge(['email' => $email], $options))->first();
            if (is_null($hashedPasswordString)) {
                print('            . Admin account with email "' . $email . '" not found! Please enter again. (Ctrl + C to abort backup process)' . PHP_EOL);
                return $this->authenticateUserViaTerminal($table, $options);
            }
            $hashedPasswordString = $hashedPasswordString->password;
            $authCheckStatus = $this->readAndComparePasswordInputFromCli($hashedPasswordString, 'hash', '        . Enter password: ');
        }
        if (!$authCheckStatus) {
            print('            . Invalid credentials for "' . $email . '"! Please try again. (Ctrl + C to abort backup process)' . PHP_EOL);
            return $this->authenticateUserViaTerminal($table, $options);
        }
        print('        . Authenticated for user with email: "' . $email . '"' . PHP_EOL);
        // flushing credentials from memory
        $hashedPasswordString = $table = $options = $email = null;
        unset($hashedPasswordString, $table, $options, $email);
        return $authCheckStatus;
    }

    /**
     * Print progress to terminal
     * @param float|int $progressPercentage
     * @param string|callable $statusMsg (if set to null, dotted progress bar is displayed, 1 dot per percent, if callable/closure is passed, executes it with $progressPercentage as argument)
     * @return void
     */
    private function printProgress(float|int &$progressPercentage, string|callable $statusMsg = null)
    {
        $progressPercentage = round($progressPercentage, 5);
        if ($progressPercentage > 100 || $progressPercentage < 0) {
            throw new Error('Invalid progress percentage!');
        }
        if (is_null($statusMsg)) {
            $statusMsg = PHP_EOL . ' ' . str_repeat('.', floor($progressPercentage)) . '    ';
        }
        print($statusMsg . $progressPercentage . ' % complete.' . PHP_EOL);
    }

    /**
     * print action completed message in terminal
     * @param string $msg
     * @return void
     */
    private function printActionCompletedMsg(string $msg = 'Done.' . PHP_EOL)
    {
        print($msg);
    }

    /**
     * Get desired chunk size (in MB) from terminal
     * @param integer $maxAllowedInMB - max allowed chunk size
     * @return boolean - true on valid input, false otherwise
     */
    private function getDesiredChunkSize(int $maxAllowedInMB = 512, bool $throwErrorIfLineValidationFails = true)
    {
        $maxAllowedInStr = number_format($maxAllowedInMB);
        $chunkFileSize = $this->readInputFromCli(
            1,
            ['                . Manual mode is enabled, enter desired chunk file size (in MB, max "' . $maxAllowedInStr . '") : '],
            function ($str) use ($maxAllowedInMB, $maxAllowedInStr, $throwErrorIfLineValidationFails) {
                $check = is_int((int)$str) && 0 < $str && $str < $maxAllowedInMB + 1;
                if ($throwErrorIfLineValidationFails && !$check) {
                    print('                    . ERROR: Input value must be an integer between 1 and ' . $maxAllowedInStr . ', includes 1 & ' . $maxAllowedInStr . ' as well.' . PHP_EOL);
                }
                return $check;
            },
            null,
            true
        );
        return (int)reset($chunkFileSize);
    }

    /**
     * print progress bar in terminal
     * @param float|int $completed
     * @param float|int $total
     * @param string $char
     * @param integer $maxChars
     * @return void
     */
    private function printProgressBar(float|int $completed, float|int $total, string $char = '.', int $maxChars = 100)
    {
        print(str_repeat($char, (int)(($completed / $total) * $maxChars)));
    }

    /**
     * Calculate optimal data chunk size for fastest possivle processing
     * @param array $ids
     * @param string $tableName
     * @param \stdClass $autoIncrementColumnDesc
     * @param array $chunkFileSizeLimitsInMB - chunk file size samples to test
     * @param boolean $silent - removes printed lines if passed as true
     * @return array - the optimal chunk size in MB & rate
     */
    private function calculateOptimalChunkFileSize(
        array &$ids,
        string &$tableName,
        stdClass &$autoIncrementColumnDesc,
        array $chunkFileSizeLimitsInMB = [5, 10, 15, 20],
        bool $silent = true
    ) {
        $processedIds = [];
        $removeLastLine = false;
        $rowData = $startTime = $tmpChunkFileData = null;
        $optimalChunkSizeInMB = reset($chunkFileSizeLimitsInMB);
        $chunkFileSizeLimitInBytes = $timeTakenForEachChunkToForm = $rateOfProcessing = $maxRateOfProcessing = $bytesAdded = 0;
        foreach ($chunkFileSizeLimitsInMB as $chunkFileSizeLimitInMB) {
            if ($removeLastLine && $silent) {
                $this->removeLastLine();
            }
            print('                    . Testing for chunk size: ' . $chunkFileSizeLimitInMB . ' MB ... ');
            $chunkFileSizeLimitInBytes = $chunkFileSizeLimitInMB * pow(2, 20);
            $startTime = now();
            foreach ($ids as $id) {
                $rowData = json_encode($this->getRawQueryOutput('select * from ' . $tableName . ' where ' . $autoIncrementColumnDesc->Field . ' = ' . $id));
                $bytesAdded += strlen($rowData) + 1; // calculating total bytes added, an extra 1 byte is added for line break as jsonl format is being used (PHP_EOL)
                if ($bytesAdded > $chunkFileSizeLimitInBytes) {
                    break;
                }
                array_push($processedIds, $id);
            }
            $timeTakenForEachChunkToForm = now()->diffInMilliseconds($startTime) / 1000; // converting to seconds from milliseconds
            $rateOfProcessing = floor(count($processedIds) / $timeTakenForEachChunkToForm); // calculating rate of processing
            print('Max capacity: ' . number_format($rateOfProcessing) . ' rows / second.' . PHP_EOL);
            $removeLastLine = true;
            // resetting variables
            $bytesAdded = 0;
            $processedIds = [];
            $startTime = now();
            if ($rateOfProcessing > $maxRateOfProcessing) {
                // storing chunk size value which gives max performance
                $maxRateOfProcessing = $rateOfProcessing;
                $optimalChunkSizeInMB = $chunkFileSizeLimitInMB;
            }
        }
        return [
            'optimalChunkSizeInMB' => $optimalChunkSizeInMB,
            'maxRateOfProcessing' => $maxRateOfProcessing
        ];
    }

    /**
     * Generate chunk file size limits in MB based on min, max & increment values
     * @param int $min
     * @param int $max
     * @param int $increment
     * @return array
     */
    private function generateChunkFileSizeLimits(int $min, int $max, int $increment = 5)
    {
        if ($min <= 0) {
            throw new Error('Min must be an integer greater than 0');
        }
        if ($max < $min) {
            throw new Error('Max must be an integer greater than min');
        }
        $chunkFileSizeLimits = [];
        for ($chunkFileSize = $min; $chunkFileSize < $max + 1; $chunkFileSize += $increment) {
            array_push($chunkFileSizeLimits, $chunkFileSize);
        }
        return $chunkFileSizeLimits;
    }

    /**
     * Generate file chunks for table backup based on parameters
     * @param array $ids
     * @param integer $desiredChunkFileSizeInMB
     * @param string $tableName
     * @param \stdClass $autoIncrementColumnDesc
     * @param string $chunkStorageFolder
     * @param integer $rowCount
     * @return void
     */
    private function generateChunkFiles(
        array $ids,
        int $desiredChunkFileSizeInMB,
        string $tableName,
        stdClass $autoIncrementColumnDesc,
        string $chunkStorageFolder,
        int $rowCount
    ) {
        $jsonRowData = null;
        $chunkFileData = '';
        $chunkFileCounter = 1;
        $processingIds = $processedIds = [];
        $totalBytesWrittenInChunkFiles = $processedIdsCounter = 0;
        $dataFileLengths = ['chunk' => 0, 'currentRow' => 0, 'total' => 0];
        $chunkFileSizeLimitInBytes = $desiredChunkFileSizeInMB * pow(2, 20);
        print('                . Generating chunk "chunk_' . $chunkFileCounter . '.jsonl" ... 0 % complete.');
        foreach ($ids as $id) {
            $dataFileLengths['chunk'] = strlen($chunkFileData);
            $jsonRowData = json_encode($this->getRawQueryOutput('select * from ' . $tableName . ' where ' . $autoIncrementColumnDesc->Field . ' = ' . $id));
            $dataFileLengths['currentRow'] = strlen($jsonRowData);
            $dataFileLengths['total'] = $dataFileLengths['chunk'] + $dataFileLengths['currentRow'];
            if ($dataFileLengths['total'] < $chunkFileSizeLimitInBytes) {
                array_push($processingIds, $id);
                $chunkFileData .= $jsonRowData . PHP_EOL;
                $processedIdsCounter++;
            } elseif ($dataFileLengths['total'] >= $chunkFileSizeLimitInBytes) {
                if (env('DB_BACKUP_USE_QUEUE')) {
                    WriteDatabaseBackupChunks::dispatch(
                        $chunkStorageFolder . 'chunk_' . $chunkFileCounter . '.jsonl',
                        $tableName,
                        $processingIds,
                        $autoIncrementColumnDesc->Field
                    );
                    $totalBytesWrittenInChunkFiles += strlen($chunkFileData);
                } else {
                    $totalBytesWrittenInChunkFiles += file_put_contents($chunkStorageFolder . 'chunk_' . $chunkFileCounter . '.jsonl', $chunkFileData);
                }
                $chunkFileData = $jsonRowData . PHP_EOL;
                $processingIds = [];
                $processedIds = array_merge($processingIds, $processedIds);
                $chunkFileCounter++;
                $this->printActionCompletedMsg();
                $this->removeLastLine();
                print('                . Generating chunk "chunk_' . $chunkFileCounter . '.jsonl" ... ' . round(($processedIdsCounter / $rowCount) * 100, 2) . ' % complete.');
            }
        }
        $unprocessedIds = array_diff($ids, $processedIds);
        asort($unprocessedIds);
        if (!empty($unprocessedIds)) {
            if (env('DB_BACKUP_USE_QUEUE')) {
                WriteDatabaseBackupChunks::dispatch(
                    $chunkStorageFolder . 'chunk_' . $chunkFileCounter . '.jsonl',
                    $tableName,
                    $unprocessedIds,
                    $autoIncrementColumnDesc->Field
                );
                $totalBytesWrittenInChunkFiles += strlen($chunkFileData);
            } else {
                $chunkFileData = '';
                $chunkFileCounter++;
                foreach ($unprocessedIds as $id) {
                    print(PHP_EOL);
                    $this->removeLastLine();
                    print('                . Generating chunk "chunk_' . $chunkFileCounter . '.jsonl" ... ' . round(($processedIdsCounter / $rowCount) * 100, 2) . ' % complete.');
                    $jsonRowData = json_encode($this->getRawQueryOutput('select * from ' . $tableName . ' where ' . $autoIncrementColumnDesc->Field . ' = ' . $id));
                    $chunkFileData .= $jsonRowData . PHP_EOL;
                    $processedIdsCounter++;
                }
                $totalBytesWrittenInChunkFiles += file_put_contents($chunkStorageFolder . 'chunk_' . $chunkFileCounter . '.jsonl', $chunkFileData);
            }
        }
        $this->printActionCompletedMsg();
        $this->removeLastLine();
        print('                . ' . number_format($totalBytesWrittenInChunkFiles / pow(2, 20), 2) . ' MB written in ' . number_format($chunkFileCounter) . ' chunk files.' . PHP_EOL);
    }

    /**
     * Get confirmation from terminal
     * @param string $promptMsg
     * @return boolean - true if input is yes, false otherwise
     */
    private function getConfirmation(string $promptMsg = 'Do you accept ?')
    {
        $confirm = $this->readInputFromCli(1, [$promptMsg . ' (Y/N): '], function ($char) {
            return is_string($char) && in_array(strtolower($char), ['y', 'n', 'yes', 'no']);
        }, null, true);
        return in_array(strtolower(reset($confirm)), ['y', 'yes']);
    }
}
