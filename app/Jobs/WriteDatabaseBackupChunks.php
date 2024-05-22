<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WriteDatabaseBackupChunks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $file, $table, $ids, $idColumn;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $file, string $table, array $ids, string $idColumn)
    {
        $this->file = $file;
        $this->table = $table;
        $this->ids = $ids;
        $this->idColumn = $idColumn;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = '';
        foreach ($this->ids as $id) {
            $data .= json_encode(Arr::first(DB::select('SELECT * FROM ' . $this->table . ' WHERE ' . $this->idColumn . ' = ' . $id))) . PHP_EOL;
        }
        file_put_contents($this->file, $data);
        $data = null;
        unset($data);
    }
}
