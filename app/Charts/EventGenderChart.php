<?php

namespace App\Charts;

use ConsoleTVs\Charts\Classes\Chartjs\Chart;

class EventGenderChart extends Chart
{
    public function __construct()
    {
        parent::__construct();

        $this->labels(['Male', 'Female']);
        $this->dataset('Gender Distribution', 'pie', [0, 0])
             ->backgroundColor(['#6cb2eb', '#f66d9b']);
    }

    public function setData(int $male, int $female)
    {
        $this->datasets[0]->data = [$male, $female];
    }
}
