<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImportWoltlabData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-woltlab-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connects to the Woltlab database and imports the data into the Laravel database.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->importUsers();
        $this->info('Data imported successfully.');
    }

    protected function importUsers(): void
    {
        $totalInserted = 0;

        DB::connection('mysql_woltlab')->table('wcf1_user')->chunkById(2500, function (Collection $users) use (&$totalInserted) {
            $insertData = [];
            foreach ($users as $wolt) {
                $registrationDate = Carbon::parse($wolt->registrationDate, 'UTC');
                if ($registrationDate->isFuture()) {
                    $registrationDate = now('UTC');
                }
                $registrationDate->setTimezone('UTC');

                $insertData[] = [
                    'name' => $wolt->username,
                    'email' => mb_convert_case($wolt->email, MB_CASE_LOWER, 'UTF-8'),
                    'password' => $wolt->password,
                    'created_at' => $registrationDate,
                    'updated_at' => now('UTC')->toDateTimeString(),
                ];
            }

            if (!empty($insertData)) {
                User::insert($insertData);
                $totalInserted += count($insertData);
                $this->info('Inserted ' . count($insertData) . ' users. Total inserted so far: ' . $totalInserted);
            }

            unset($insertData);
            unset($users);
        }, 'userID');

        $this->info('Total users inserted: ' . $totalInserted);
    }
}
