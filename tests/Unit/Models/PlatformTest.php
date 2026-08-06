<?php

namespace Tests\Unit\Models;

use App\Models\Manufacturer;
use App\Models\Platform;
use PHPUnit\Framework\TestCase;

class PlatformTest extends TestCase
{
    public function test_chip_label_falls_back_to_name(): void
    {
        $platform = new Platform(['name' => 'PlayStation 5', 'label' => null]);

        $this->assertSame('PlayStation 5', $platform->chipLabel());
    }

    public function test_chip_label_prefers_explicit_label(): void
    {
        $platform = new Platform(['name' => 'PlayStation 5', 'label' => 'PS5']);

        $this->assertSame('PS5', $platform->chipLabel());
    }

    public function test_effective_colors_fall_back_to_manufacturer(): void
    {
        $manufacturer = new Manufacturer([
            'bg_color' => '#111111',
            'text_color' => '#222222',
            'border_color' => '#333333',
        ]);

        $platform = new Platform(['name' => 'PlayStation 5']);
        $platform->setRelation('manufacturer', $manufacturer);

        $this->assertSame('#111111', $platform->effectiveBgColor());
        $this->assertSame('#222222', $platform->effectiveTextColor());
        $this->assertSame('#333333', $platform->effectiveBorderColor());
    }

    public function test_effective_colors_prefer_own_values_over_manufacturer(): void
    {
        $manufacturer = new Manufacturer(['bg_color' => '#111111']);

        $platform = new Platform(['name' => 'PlayStation 5', 'bg_color' => '#ABCABC']);
        $platform->setRelation('manufacturer', $manufacturer);

        $this->assertSame('#ABCABC', $platform->effectiveBgColor());
    }

    public function test_effective_colors_fall_back_to_defaults_without_manufacturer(): void
    {
        $platform = new Platform(['name' => 'PC']);
        $platform->setRelation('manufacturer', null);

        $this->assertSame('#EEF2FF', $platform->effectiveBgColor());
        $this->assertSame('#4338CA', $platform->effectiveTextColor());
        $this->assertSame('#C7D2FE', $platform->effectiveBorderColor());
    }
}
