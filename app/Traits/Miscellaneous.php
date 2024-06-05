<?php

namespace App\Traits;

use App\Models\User;
use Exception;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

trait Miscellaneous
{
    /**
     * Flatten an array
     * @param array $array
     * @return array
     * @source: https://stackoverflow.com/a/1320156/12199939
     */
    public function flattenArray(array $array)
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
    public function removeDuplicatesByKey(array &$array, string $key)
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
    public function readInputFromCli(
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
     * print a line
     * @param string $text
     * @param integer $subsetCount
     * @param boolean $appendLineBreak
     * @return void
     */
    function printLine(string $text, int $subsetCount = 0, bool $appendLineBreak = false)
    {
        print(str_repeat(' ', $subsetCount * 4) . '. ' . $text . ($appendLineBreak ? PHP_EOL : ' '));
    }

    /**
     * Remove last line from cli
     * @return void
     */
    public function removeLastLine()
    {
        print("\033[1A\033[K");
    }

    /**
     * Checks if a multidimensional array has duplicate values
     * @param array $inputArray - the mutidimensional array
     * @return boolean - returns true if array has duplicate values, false otherwise
     */
    public function arrayHasDuplicateValues(array &$inputArray)
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
    public function generateExistsQuery(array $values, string $column, string $tableName, string|null $softDeleteColumn = 'deleted_at')
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
    public function generateRawInsertQuery(array &$data, string $tableName)
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
    public function findMaxAllowedInsertRows(array &$dataset, string $tableName = 'products', string $considerExistsCheckOf = null)
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
    public function appendToEnv(string $key, string $value)
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
    public function delayExecution(int $seconds)
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
    public function attemptRequest(
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
     * Securely authenticate user via terminal
     * @param string|\Illuminate\Database\Eloquent\Model $table (must be the table name if string)
     * @param array $options (key value pairs that should match)
     * @return boolean
     */
    private function authenticateUserViaTerminal(string|Model &$table = new User(), array &$options = ['is_admin' => 1])
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
     * Get confirmation
     * @param string $promptMsg
     * @return boolean - true if confirmed, false otherwise
     */
    private function getConfirmation(string $promptMsg)
    {
        $promptMsg .= ' (Y|N): ';
        $yesVals = ['y', 'yes', 'true', '1'];
        $choice = $this->readInputFromCli(1, [$promptMsg], function ($str) use ($yesVals) {
            return in_array(strtolower($str), array_merge($yesVals, ['no', 'n', 'false', '0']));
        }, null, true);
        $choice = reset($choice);
        return in_array($choice, $yesVals);
    }

    /**
     * parse & return raw query statement from query object
     * @param \Illuminate\Database\Eloquent\Builder $queryObj;
     * @return string
     */
    private function getRawStatementFromQueryObject(Builder|QueryBuilder|Model &$queryObj)
    {
        return sprintf(str_replace('?', '"%s"', $queryObj->toSql()), ...$queryObj->getBindings());
    }
}
