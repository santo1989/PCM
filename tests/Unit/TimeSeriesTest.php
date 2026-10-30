<?php

namespace Tests\Unit;

use App\Services\Analytics\TimeSeries;
use PHPUnit\Framework\TestCase;

class TimeSeriesTest extends TestCase
{
    public function test_robust_statistics()
    {
        $this->assertSame(3.0, TimeSeries::median([5, 1, 3]));
        $this->assertSame(2.5, TimeSeries::median([4, 1, 3, 2]));
        $this->assertEqualsWithDelta(2.0, TimeSeries::theilSen([0, 2, 4, 6, 100, 10]), 0.01);
        $this->assertEqualsWithDelta(0.975, TimeSeries::normalCdf(1.96), 0.001);
        $this->assertEquals([10, 11, 10, 11, 11, 11, 10], TimeSeries::hampel([10, 11, 10, 11, 500, 11, 10]));
        $this->assertEquals([0, 0, 900, 0, 0], TimeSeries::hampel([0, 0, 900, 0, 0]), 'intermittent spend is kept');
    }

    public function test_flat_series_forecasts_its_level()
    {
        $r = TimeSeries::ensemble(array_fill(0, 30, 1000));
        $this->assertEqualsWithDelta(1000, $r['forecast'], 1);
        $this->assertLessThanOrEqual($r['forecast'], $r['lo']);
        $this->assertGreaterThanOrEqual($r['forecast'], $r['hi']);
    }

    public function test_seasonal_series_picks_up_the_season()
    {
        // 3 years: 1000 a month, 3000 every December; next month (index 35) is December.
        $y = [];
        for ($i = 0; $i < 35; $i++) {
            $y[] = $i % 12 === 11 ? 3000 : 1000;
        }
        $r = TimeSeries::ensemble($y);
        $this->assertGreaterThan(2000, $r['forecast']);
    }

    public function test_one_off_spike_does_not_drive_the_forecast()
    {
        $y = array_fill(0, 20, 500);
        $y[17] = 20000;
        $this->assertLessThan(800, TimeSeries::ensemble($y)['forecast']);
    }

    public function test_empty_and_zero_series()
    {
        $this->assertSame(0.0, TimeSeries::ensemble([])['forecast']);
        $this->assertSame(0.0, TimeSeries::ensemble([0, 0, 0])['forecast']);
    }
}
