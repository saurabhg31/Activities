<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
    protected $description = 'Command to backup all tables present in the database of the application. ';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $startTime = Date::now();
        print('Database backup process initiated on : ' . Date::now()->format(env('DB_BACKUP_DATE_FORMAT', 'd M, Y H:i:s p')) . PHP_EOL);

        // checking if all requirements for initiating database backup are met
        if (!getenv('DB_BACKUP_FILE_DATETIME_FORMAT') || !getenv('DB_BACKUP_ROW_CHUNK')) {
            die('Critical environment value(s) not set. Database backup process aborted! E-01.' . PHP_EOL);
        }

        // declaring backup storage directory & filename
        $storageDirectory = storage_path('Database Dumps/');
        $databaseBackupFileName = storage_path('Database Dumps/DatabaseBackup_' . Date::now()->format('Y_m_d_H_i'));

        // tables attributes for backup
        $tableProperties = [
            'compressionRequired' => ['images']
        ];

        // getting list of tables
        $tables = $this->getAllTables();
        $tableProperties['tablesWithoutCompressionRequirement'] = array_diff($tables, $tableProperties['compressionRequired']);

        // generating command for backing up database tables that don't require compression
        $shellCommand = 'mysqldump -u ' . getenv('DB_USERNAME') . ' -p ' . getenv('DB_DATABASE') . ' ' . implode(' ', $tableProperties['tablesWithoutCompressionRequirement']) . ' > ' . '"' . $databaseBackupFileName . '.sql"';

        // creating directory with read & write access only  if it is absent
        if (!file_exists($storageDirectory)) {
            mkdir($storageDirectory, 0700);
        }

        // executing command
        print('Backing up tables ... ' . PHP_EOL);
        if ($this->executeCommand($shellCommand)) {
            if (count($tableProperties['compressionRequired'])) {
                print('1. ' . number_format(count($tableProperties['tablesWithoutCompressionRequirement'])) . ' tables which do not require compression have been backed up.' . PHP_EOL);
                $totalCount = count($tableProperties['tablesWithoutCompressionRequirement']);
                print('2. Compressing ' . number_format($totalCount) . ' tables ... ' . PHP_EOL);

                // compressing tables
                foreach ($tableProperties['compressionRequired'] as $index => $tableName) {
                    print('    ' . ($index + 1) . '. ' . $tableName . ' ... ');
                    if (self::compressTable($tableName)) {
                        $this->removeLastLine();
                    } else {
                        print(PHP_EOL . 'Compression of table "' . $tableName . '" failed!' . PHP_EOL);
                    }
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
        // getting total rows
        $rowCount = ($this->getRawQueryOutput('select count(*) as count from ' . $tableName))->count;
        if (!$this->checkQueueStatus()) {
            die('Application\'s queue daemon is not running! Backup process aborted.' . PHP_EOL);
        }
    }
}
