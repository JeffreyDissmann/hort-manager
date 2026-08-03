<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;

class HomeworkDefault extends Model
{
    use LogsChanges;

    protected $fillable = [
        'weekday',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    /** @return list<string> */
    protected function activityAttributes(): array
    {
        return ['start_time', 'end_time'];
    }

    protected function activityLabel(): string
    {
        return [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag'][$this->weekday] ?? '?';
    }
}
