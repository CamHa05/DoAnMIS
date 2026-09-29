<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutoringClass extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    protected $table = 'tutoring_classes';

    protected $primaryKey = 'class_id';

    protected $fillable = [
        'contract_id',
        'class_name',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function contract()
    {
        return $this->belongsTo(
            Contract::class,
            'contract_id',
            'contract_id'
        );
    }

    public function schedules()
    {
        return $this->hasMany(
            ClassSchedule::class,
            'class_id',
            'class_id'
        );
    }

    public static function nameFromSubjectLevel(?SubjectLevel $subjectLevel): string
    {
        return collect([
            $subjectLevel?->subject?->subject_name,
            $subjectLevel?->level_name,
        ])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->implode(' - ');
    }

    public function displayName(?SubjectLevel $subjectLevel = null): string
    {
        $className = trim((string) $this->class_name);

        if ($className !== '') {
            return $className;
        }

        $subjectLevel ??= $this->contract?->tutoringRequest?->subjectLevel;

        return static::nameFromSubjectLevel($subjectLevel) ?: 'Lớp học';
    }
}
