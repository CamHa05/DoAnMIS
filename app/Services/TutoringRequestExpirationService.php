<?php

namespace App\Services;

use App\Models\RequestStatusHistory;
use App\Models\TutoringRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TutoringRequestExpirationService
{
    private const EXPIRATION_REASON = 'Yêu cầu đã hết thời hạn xử lý.';

    public function expirePublicRequests(): int
    {
        return $this->expireScoped(
            fn (Builder $query) => $query->where('request_type', 'PUBLIC')
        );
    }

    public function expireRequestsForUser(int $userId): int
    {
        return $this->expireScoped(
            fn (Builder $query) => $query->where('user_id', $userId)
        );
    }

    public function expireDirectRequestsForTutor(int $tutorProfileId): int
    {
        return $this->expireScoped(
            fn (Builder $query) => $query
                ->where('request_type', 'DIRECT')
                ->where('target_tutor_profile_id', $tutorProfileId)
        );
    }

    private function expireScoped(callable $scope): int
    {
        return DB::transaction(function () use ($scope): int {
            $changedAt = now();
            $query = TutoringRequest::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $changedAt)
                ->where(function (Builder $query): void {
                    $query
                        ->where(function (Builder $publicQuery): void {
                            $publicQuery
                                ->where('request_type', 'PUBLIC')
                                ->where('status', 'OPEN');
                        })
                        ->orWhere(function (Builder $directQuery): void {
                            $directQuery
                                ->where('request_type', 'DIRECT')
                                ->where('status', 'PENDING');
                        });
                });

            $scope($query);

            $expiredRequests = $query
                ->orderBy('request_id')
                ->lockForUpdate()
                ->get(['request_id', 'status']);

            if ($expiredRequests->isEmpty()) {
                return 0;
            }

            TutoringRequest::query()
                ->whereKey($expiredRequests->modelKeys())
                ->update([
                    'status' => 'EXPIRED',
                    'updated_at' => $changedAt,
                ]);

            RequestStatusHistory::query()->insert(
                $expiredRequests
                    ->map(fn (TutoringRequest $request): array => [
                        'request_id' => $request->getKey(),
                        'initiated_by_user_id' => null,
                        'old_status' => $request->status,
                        'new_status' => 'EXPIRED',
                        'reason' => self::EXPIRATION_REASON,
                        'changed_at' => $changedAt,
                    ])
                    ->all()
            );

            return $expiredRequests->count();
        });
    }
}
