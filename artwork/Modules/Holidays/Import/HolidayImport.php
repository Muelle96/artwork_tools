<?php

namespace Artwork\Modules\Holidays\Import;

use Artwork\Modules\Holidays\Api\ApiDto;
use Artwork\Modules\Holidays\Api\HolidayApi;
use Artwork\Modules\Holidays\Models\Subdivision;
use Artwork\Modules\Holidays\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;

class HolidayImport
{
    use Queueable;

    public function __construct()
    {
    }

    public function handle(
        HolidayApi $api,
        HolidayService $holidayService
    ): void {
        foreach (Subdivision::all() as $subdivision) {
            foreach (
                $api->getHolidays(
                    Carbon::now()->startOf('year'),
                    Carbon::now()->endOf('year'),
                    $subdivision
                ) as $holiday
            ) {
                if ($holidayService->getBy('remote_identifier', $holiday->remoteIdentifier)) {
                    continue;
                }

                $holidayService->createFromApi(
                    $holiday
                );
            }
        }
    }

}
