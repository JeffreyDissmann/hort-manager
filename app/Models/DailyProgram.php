<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DailyProgramFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyProgram extends Model
{
    /** @use HasFactory<DailyProgramFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'lunch',
        'activity',
        'homework_start',
        'homework_end',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }
}
