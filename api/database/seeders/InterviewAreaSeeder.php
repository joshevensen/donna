<?php

namespace Database\Seeders;

use App\Models\InterviewArea;
use Illuminate\Database\Seeder;

class InterviewAreaSeeder extends Seeder
{
    /**
     * The ground Getting to Know You covers.
     *
     * @var list<string>
     */
    private const array AREAS = [
        'Life story',
        'Work',
        'People',
        'Interests',
        'How Josh works',
        'Goals',
        'Josh & Donna',
    ];

    /**
     * Seed the interview areas, leaving any progress on existing ones alone.
     */
    public function run(): void
    {
        foreach (self::AREAS as $area) {
            InterviewArea::firstOrCreate(['area' => $area]);
        }
    }
}
