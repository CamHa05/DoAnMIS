<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TutoringClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TutoringClassController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tutorProfileId = $user->tutorProfile()->value('tutor_profile_id');

        $classes = TutoringClass::query()
            ->where(function (Builder $query) use ($user, $tutorProfileId): void {
                $query->whereHas(
                    'contract.tutoringRequest',
                    fn (Builder $requestQuery) => $requestQuery->where('user_id', $user->user_id)
                );

                if ($tutorProfileId !== null) {
                    $query->orWhereHas(
                        'contract',
                        fn (Builder $contractQuery) => $contractQuery->where(
                            'tutor_profile_id',
                            $tutorProfileId
                        )
                    );
                }
            })
            ->with([
                'contract.tutoringRequest.subjectLevel.subject',
                'contract.tutoringRequest.subjectLevel.educationLevel',
                'contract.tutoringRequest.ward.province',
                'contract.tutorProfile.user',
                'schedules' => fn ($query) => $query
                    ->orderBy('day_of_week')
                    ->orderBy('time_slot_id'),
                'schedules.timeSlot',
            ])
            ->orderByDesc('start_date')
            ->orderByDesc('class_id')
            ->paginate(8);

        return view('classes.index', compact('classes'));
    }

    public function show(Request $request, TutoringClass $tutoringClass): View
    {
        $tutoringClass->load([
            'contract.tutoringRequest.user:user_id,full_name,email,phone,avatar_url',
            'contract.tutoringRequest.subjectLevel.subject',
            'contract.tutoringRequest.ward.province',
            'contract.tutoringRequest.schedules.timeSlot',
            'contract.tutorProfile.user:user_id,full_name,email,phone,avatar_url',
            'schedules.timeSlot',
        ]);

        $contract = $tutoringClass->contract;
        $learner = $contract?->tutoringRequest?->user;
        $tutor = $contract?->tutorProfile?->user;
        $userId = (int) $request->user()->getAuthIdentifier();
        $isLearner = $userId === (int) $learner?->getKey();
        $isTutor = $userId === (int) $tutor?->getKey();

        abort_unless($isLearner || $isTutor, 403);

        $isConfirmed = strtoupper((string) $contract?->status) === 'CONFIRMED';
        abort_unless($isConfirmed, 404);

        return view('classes.show', [
            'tutoringClass' => $tutoringClass,
            'contract' => $contract,
            'learner' => $learner,
            'tutor' => $tutor,
            'isLearner' => $isLearner,
            'isOnline' => strtoupper((string) $contract?->learning_mode) === 'ONLINE',
        ]);
    }
}
