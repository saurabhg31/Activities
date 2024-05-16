<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use App\Traits\Miscellaneous;

class BackupDatabase extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to backup all tables present in the database of the application. Works on linux system only.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // log start time & verify all requirements are met before proceeding
        $startTime = Date::now();
        print('Database backup process initiated on : ' . Date::now()->format(env('DB_BACKUP_DATE_FORMAT', 'd M, Y H:i:s p')) . PHP_EOL . '    A. Checking if backup process requirements are met ... ' . PHP_EOL);
        // self::checkRequiremmments(); // TODO: Uncomment this line during hosting

        // declaring backup filename
        $databaseBackupFileName = storage_path(env('DB_BACKUP_STORAGE_FOLDER') . '/DatabaseBackup_' . Date::now()->format('Y_m_d_H_i'));

        // tables attributes for backup
        $tableProperties = [
            'compressionRequired' => ['images']
        ];

        // getting list of tables
        $tables = $this->getAllTables();
        $tableProperties['tablesWithoutCompressionRequirement'] = array_diff($tables, $tableProperties['compressionRequired']);

        // generating command for backing up database tables that don't require compression
        $shellCommand = 'mysqldump -u ' . env('DB_USERNAME') . ' -p ' . env('DB_DATABASE') . ' ' . implode(' ', $tableProperties['tablesWithoutCompressionRequirement']) . ' > ' . '"' . $databaseBackupFileName . '.sql"';

        // executing command
        print('    C. Backing up tables ... ' . PHP_EOL . '        . MySql : ');
        if ($this->executeCommand($shellCommand, false, true)) {
            if (count($tableProperties['compressionRequired'])) {
                // compressing tables
                foreach ($tableProperties['compressionRequired'] as $tableName) {
                    print('        . Compressing table "' . $tableName . '" ... ' . PHP_EOL);
                    self::compressTable($tableName);
                }
            }
            print('Process completed on : ' . Date::now()->format(env('DB_BACKUP_DATE_FORMAT', 'd M, Y H:i:s p')) . '. Took ' . str_replace(' after', '', Date::now()->diffForHumans($startTime)) . PHP_EOL);
            return Command::SUCCESS;
        } else {
            die('Failed to create database backup. Process aborted! E-02.' . PHP_EOL);
        }
    }

    /**
     * Function to compress a sql table containing massive data
     * @param string $tableName
     * @return boolean true on success, false otherwise
     */
    private function compressTable(string $tableName)
    {
        // checking if compression config enabled in config
        if (!env('DB_BACKUP_ENABLE_COMPRESSION', false)) {
            die('ERROR: Compression not enabled in config, process aborted.' . PHP_EOL);
        }

        // analyzing table for compression
        $backupDirectory = storage_path(env('DB_BACKUP_STORAGE_FOLDER'));
        $maxIdCountCap = env('DB_BACKUP_PART_FILE_MAX_SIZE_IN_MB') * pow(2, 20);
        print('            . Analyzing table ... ' . PHP_EOL);
        print('                . Getting total number of rows in table ... ');
        $rowCount = ($this->getRawQueryOutput('select count(*) as count from ' . $tableName))->count;
        $this->printActionCompletedMsg(number_format($rowCount) . ' rows found.' . PHP_EOL);
        print('                . Fetching table description ... ');
        $tableDesc = $this->getRawQueryOutput('desc ' . $tableName . ';');
        $this->printActionCompletedMsg();
        print('                . Searching for auto increment column ... ');
        $autoIncrementColumnDesc = array_filter($tableDesc, function ($columnDesc) {
            return isset($columnDesc->Extra) && $columnDesc->Extra == 'auto_increment';
        });
        if (empty($autoIncrementColumnDesc)) {
            $this->printActionCompletedMsg('None present.' . PHP_EOL);
            $autoIncrementColumnDesc = null;
        } else {
            $autoIncrementColumnDesc = reset($autoIncrementColumnDesc);
            $this->printActionCompletedMsg('Found column "' . $autoIncrementColumnDesc->Field . '"' . PHP_EOL);
        }

        // calculating best compression method for given table based on its contents
        print('                . Selecting optimal method of compression ... ');
        if ($autoIncrementColumnDesc) {
            if ($rowCount > $maxIdCountCap) {
                $this->printActionCompletedMsg('Too many rows, normal method selected.' . PHP_EOL);
                // TODO: Add code to compress rows without id sorting
            } else {
                $this->printActionCompletedMsg('Id sorting method selected.' . PHP_EOL);
                print('                . Fetching all ids present in table "' . $tableName . '" ... ');

                // fetching all ids
                $ids = array_map(function ($obj) {
                    return $obj->autoIncrementColumn;
                }, $this->getRawQueryOutput('select ' . $autoIncrementColumnDesc->Field . ' as autoIncrementColumn from ' . $tableName));
                asort($ids); // sorting ids in ascending order
                $this->printActionCompletedMsg(number_format($rowCount) . ' ids sorted in ascending order.' . PHP_EOL);
                print('                . Creating id chunks with ' . number_format(env('DB_BACKUP_ROW_CHUNK')) . ' ids in each ... ');
                $idChunks = array_chunk($ids, env('DB_BACKUP_ROW_CHUNK'));
                $totalGeneratedChunks = count($idChunks);
                $this->printActionCompletedMsg('Generated ' . number_format($totalGeneratedChunks) . ' chunks.' . PHP_EOL);

                // fetching table data in chunks & compressing the same
                if (strtolower(env('DB_BACKUP_MODE')) == 'dynamic') {
                    // TODO: Add code to compress by dynamically deciding number of chunks & filesize based on data
                } else {
                    if (env('DB_BACKUP_USE_QUEUE')) {
                        // TODO: Add code to backup table data using queue daemon
                    } else {
                        $chunkStorageFolder = $backupDirectory . '/chunks/';
                        exec('rm -rf ' . $chunkStorageFolder); // TODO: remove this obsolete line of code
                        mkdir($chunkStorageFolder, 0770);
                        $chunkFile = null;
                        $totalBytesWrittenInChunkFiles = 0;
                        $bytesWrittenInChunkFile = 0;
                        foreach ($idChunks as $index => $chunk) {
                            print('                . Processing chunk: ');
                            print(number_format($index + 1) . '/' . $totalGeneratedChunks . ' ... ');
                            $chunkFile = 'chunk_' . ($index + 1) . '.json';
                            $bytesWrittenInChunkFile = file_put_contents(
                                $chunkStorageFolder . $chunkFile,
                                json_encode($this->getRawQueryOutput('select * from ' . $tableName . ' where ' . $autoIncrementColumnDesc->Field . ' in (' . implode(',', $chunk) . ')'))
                            );
                            $totalBytesWrittenInChunkFiles += $bytesWrittenInChunkFile;
                            $this->printActionCompletedMsg(round($bytesWrittenInChunkFile / pow(2, 20), 2) . ' MB written in chunk file "' . $chunkFile . '"' . PHP_EOL);
                        }
                        print(PHP_EOL . '            . Chunks processing completed successfully, ' . (number_format($totalBytesWrittenInChunkFiles / pow(2, 20), 2)) . ' MB data written in total.' . PHP_EOL . '            . Beginning compressed zipping procedure ... ' . PHP_EOL);
                    }
                }
            }
        } else {
            $this->printActionCompletedMsg('Sequential method selected.' . PHP_EOL);
            // TODO: Add code to compress table if no auto increment column is present
        }
        die(PHP_EOL . '-------------- END --------------' . PHP_EOL);
    }

    /**
     * Function to check if all requirements for initiating database backup are met
     * @return void
     */
    private function checkRequiremmments()
    {
        // checking config
        array_map(function ($configKey) {
            if (is_null($configKey)) {
                die('Critical environment value(s) not set. Database backup process aborted! E-01.' . PHP_EOL);
            }
        }, ['DB_BACKUP_STORAGE_FOLDER', 'DB_BACKUP_FILE_DATETIME_FORMAT', 'DB_BACKUP_DATE_FORMAT', 'DB_BACKUP_ROW_CHUNK', 'DB_BACKUP_PART_FILE_MAX_SIZE_IN_MB', 'DB_BACKUP_USE_QUEUE', 'DB_BACKUP_MODE']);
        print('        . All configurations available.' . PHP_EOL);

        if (env('DB_BACKUP_USE_QUEUE')) {
            // checking queue daemon status
            if (!$this->checkQueueStatus()) {
                die(PHP_EOL . '------------------- CRITICAL ERROR -------------------' . PHP_EOL . 'Application\'s queue daemon is not running! Backup process aborted.' . PHP_EOL);
            }
            print('        . Queue daemon is running.' . PHP_EOL);
        }

        // checking available free memory (including swap)
        $minimumMemoryRequired = [
            'normal' => 0.75 * pow(2, 20), // in KB
            'swap' => 0.25 * pow(2, 20) // in KB
        ];
        if (!$this->minimumFreeMemoryIsAvailable($minimumMemoryRequired)) {
            die('Insufficient free memory for completing backup process! Process aborted. E - 03.' . PHP_EOL);
        } else {
            print('        . Sufficient memory available.' . PHP_EOL);
        }

        // creating directory with read & write access only  if it is absent
        $storageDirectory = storage_path(env('DB_BACKUP_STORAGE_FOLDER') . '/');
        if (!file_exists($storageDirectory)) {
            mkdir($storageDirectory, 0700);
        } else {
            // if directory is present, checking if it is empty
            if (count(scandir($storageDirectory)) > 2) {
                $choices = function ($response) {
                    return in_array(strtoupper($response), ['Y', 'N', 'YES', 'NO']);
                };
                $choice = $this->readInputFromCli(1, ['        . Database backup directory not empty. Clear all files within the same to proceed? (Y/N): '], $choices, null, true);
                $choice = strtoupper(reset($choice));
                if (in_array($choice, ['Y', 'YES'])) {
                    // clearing backup storage directory
                    exec('rm -rf "' . $storageDirectory . '"');
                    mkdir($storageDirectory, 0700);
                    print('        . Database backup storage directory cleared.' . PHP_EOL);
                } else {
                    die('Process aborted by user.' . PHP_EOL);
                }
            }
        }
        print('        . Database backup storage directory is active.' . PHP_EOL);

        // code to authenticate admin access verification
        print('    B. AUTHENTICATING ADMIN USER ... ' . PHP_EOL);
        $userModel = new User;
        if (!$this->authenticateUserViaTerminal($userModel)) {
            die('User Authenticated FAILED! Process aborted.' . PHP_EOL);
        }
        return true; // user authenticated
    }
}
