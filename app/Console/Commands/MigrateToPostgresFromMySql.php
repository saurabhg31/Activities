<?php

namespace App\Console\Commands;

use App\Traits\Miscellaneous;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\DB;

class MigrateToPostgresFromMySql extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate_to_postgres';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to migrate relevant data to postgress.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tables = [
            'users', 'images', 'image_types', 'image_search_indexing'
        ];
        try {
            print('Please enter the mysql database details:' . PHP_EOL);
            list($db, $dbUsername) = $this->readInputFromCli(2, [
                'MySql database: ',
                'MySql database username: '
            ]);
            $dbPass = $this->readPasswordFromCli('MySql database password: ');
            $connectionFactory = new ConnectionFactory(app());
            $mysqlConn = $connectionFactory->make([
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'database' => $db,
                'username' => $dbUsername,
                'password' => $dbPass,
            ]);
            dd($mysqlConn->select('SELECT * FROM users'));
        } catch (Exception $error) {
            report($error);
            print('Error encountered: ' . $error->getMessage() . PHP_EOL);
            return Command::FAILURE;
        }
    }
}
