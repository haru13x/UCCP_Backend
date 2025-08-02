<?php

namespace App\Charts;

use ConsoleTVs\Charts\Classes\Chartjs\Chart;

class EventAttendanceChart extends Chart
{
    public function __construct()
    {
        parent::__construct();

        $this->labels(['Registered', 'Attended']);
        $this->dataset('Event Attendance', 'bar', [0, 0])
             ->backgroundColor(['#3490dc', '#38c172']);
    }

    public function setData(int $registered, int $attended)
    {
        $this->datasets[0]->data = [$registered, $attended];
    }
}
