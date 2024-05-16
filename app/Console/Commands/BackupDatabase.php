<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use App\Traits\Miscellaneous;
use Illuminate\Support\Facades\DB;

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
        // self::checkRequiremmments();

        // declaring backup filename
        $databaseBackupFileName = storage_path(getenv('DB_BACKUP_STORAGE_FOLDER') . '/DatabaseBackup_' . Date::now()->format('Y_m_d_H_i'));

        // tables attributes for backup
        $tableProperties = [
            'compressionRequired' => ['images']
        ];

        // getting list of tables
        $tables = $this->getAllTables();
        $tableProperties['tablesWithoutCompressionRequirement'] = array_diff($tables, $tableProperties['compressionRequired']);

        // generating command for backing up database tables that don't require compression
        $shellCommand = 'mysqldump -u ' . getenv('DB_USERNAME') . ' -p ' . getenv('DB_DATABASE') . ' ' . implode(' ', $tableProperties['tablesWithoutCompressionRequirement']) . ' > ' . '"' . $databaseBackupFileName . '.sql"';

        // executing command
        print('    C. Backing up tables ... ' . PHP_EOL);
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

        // starting compressing process
        print('            . Analyzing table ... ' . PHP_EOL);

        // analyzing table for compression
        $progressPercentage = 0.0;
        print('                . Getting total number of rows in table ... ');
        $rowCount = ($this->getRawQueryOutput('select count(*) as count from ' . $tableName))->count;
        $progressPercentage = 1.0;
        $this->printActionCompletedMsg();
        $this->printProgress($progressPercentage);
        print('                . Fetching table description ... ');
        $tableDesc = $this->getRawQueryOutput('desc ' . $tableName . ';');
        $this->printActionCompletedMsg();
        $progressPercentage = 1.3;
        $this->printProgress($progressPercentage);
        print('                . Searching for auto increment column ... ');
        $autoIncrementColumnDesc = array_filter($tableDesc, function ($columnDesc) {
            return isset($columnDesc->Extra) && $columnDesc->Extra == 'auto_increment';
        });
        dd($tableDesc, $autoIncrementColumnDesc);

        die(PHP_EOL . '-------------- END --------------' . PHP_EOL);

        // calculating total size of table
        $table = DB::table($tableName);
        dd(mb_strlen(json_encode($table->first()), '8bit') / pow(2, 20));
    }

    /**
     * Function to check if all requirements for initiating database backup are met
     * @return void
     */
    private function checkRequiremmments()
    {
        // checking config
        array_map(function ($configKey) {
            if (!getenv($configKey)) {
                die('Critical environment value(s) not set. Database backup process aborted! E-01.' . PHP_EOL);
            }
        }, ['DB_BACKUP_STORAGE_FOLDER', 'DB_BACKUP_FILE_DATETIME_FORMAT', 'DB_BACKUP_DATE_FORMAT', 'DB_BACKUP_ROW_CHUNK', 'DB_BACKUP_PART_FILE_MAX_SIZE_IN_MB']);
        print('        . All configurations available.' . PHP_EOL);

        // checking queue daemon status
        if (!$this->checkQueueStatus()) {
            die(PHP_EOL . '------------------- CRITICAL ERROR -------------------' . PHP_EOL . 'Application\'s queue daemon is not running! Backup process aborted.' . PHP_EOL);
        }
        print('        . Queue daemon is running.' . PHP_EOL);

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
        $storageDirectory = storage_path(getenv('DB_BACKUP_STORAGE_FOLDER') . '/');
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
