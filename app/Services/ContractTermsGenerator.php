<?php

namespace App\Services;

use App\Exceptions\ContractConfirmationException;
use App\Models\Contract;
use DateTimeImmutable;
use DateTimeInterface;

class ContractTermsGenerator
{
    public const PREVIEW_PAYMENT_METHOD_TOKEN = '[[CONTRACT_PAYMENT_METHOD]]';

    public const PREVIEW_START_DATE_TOKEN = '[[CONTRACT_START_DATE]]';

    public const PREVIEW_END_DATE_TOKEN = '[[CONTRACT_END_DATE]]';

    public const PREVIEW_PAYMENT_METHOD_PLACEHOLDER = 'Chưa chọn phương thức thanh toán';

    public const PREVIEW_START_DATE_PLACEHOLDER = 'Chưa chọn ngày bắt đầu';

    public const PREVIEW_END_DATE_PLACEHOLDER = 'Chưa chọn ngày kết thúc';

    public function generate(Contract $contract): string
    {
        $paymentMethodText = match (strtoupper((string) $contract->payment_method)) {
            Contract::PAYMENT_METHOD_BANK_TRANSFER => 'Chuyển khoản',
            Contract::PAYMENT_METHOD_CASH => 'Tiền mặt',
            default => throw new ContractConfirmationException(
                'Phương thức thanh toán của hợp đồng không hợp lệ.'
            ),
        };

        if ($contract->start_date === null || $contract->end_date === null) {
            throw new ContractConfirmationException(
                'Hợp đồng chưa có đủ thời hạn để tạo điều khoản.'
            );
        }

        return $this->render(
            $contract,
            $paymentMethodText,
            $contract->start_date->format('d/m/Y'),
            $contract->end_date->format('d/m/Y')
        );
    }

    public function preview(
        Contract $contract,
        mixed $paymentMethod = null,
        mixed $startDate = null,
        mixed $endDate = null
    ): string {
        return $this->render(
            $contract,
            $this->previewPaymentMethod($paymentMethod),
            $this->previewDate($startDate, self::PREVIEW_START_DATE_PLACEHOLDER),
            $this->previewDate($endDate, self::PREVIEW_END_DATE_PLACEHOLDER)
        );
    }

    public function previewTemplate(Contract $contract): string
    {
        return $this->render(
            $contract,
            self::PREVIEW_PAYMENT_METHOD_TOKEN,
            self::PREVIEW_START_DATE_TOKEN,
            self::PREVIEW_END_DATE_TOKEN
        );
    }

    private function render(
        Contract $contract,
        string $paymentMethodText,
        string $startDate,
        string $endDate
    ): string {
        $contract->loadMissing([
            'tutoringRequest.subjectLevel.subject',
            'tutoringRequest.ward.province',
            'tutoringRequest.schedules.timeSlot',
        ]);

        $tutoringRequest = $contract->tutoringRequest;
        $subjectName = trim((string) $tutoringRequest?->subjectLevel?->subject?->subject_name);
        $levelName = trim((string) $tutoringRequest?->subjectLevel?->level_name);

        if ($subjectName === '' || $levelName === '') {
            throw new ContractConfirmationException(
                'Hợp đồng chưa có đủ thông tin môn học và trình độ để tạo điều khoản.'
            );
        }

        $learningModeText = match (strtoupper((string) $contract->learning_mode)) {
            'ONLINE' => 'trực tuyến',
            'OFFLINE' => 'tại nhà',
            default => throw new ContractConfirmationException(
                'Hình thức học của hợp đồng không hợp lệ.'
            ),
        };

        $learningContent = "Gia sư thực hiện giảng dạy môn {$subjectName}, trình độ {$levelName}, theo hình thức {$learningModeText}.";

        if (strtoupper((string) $contract->learning_mode) === 'OFFLINE') {
            $location = collect([
                trim((string) $tutoringRequest?->ward?->ward_name),
                trim((string) $tutoringRequest?->ward?->province?->province_name),
            ])->filter()->unique()->implode(', ');

            if ($location !== '') {
                $learningContent .= " Khu vực học: {$location}.";
            }
        }

        $scheduleLines = $tutoringRequest?->schedules
            ?->filter(fn ($schedule) => $schedule->timeSlot !== null)
            ->sortBy(fn ($schedule): string => sprintf(
                '%d-%s-%s',
                (int) $schedule->day_of_week,
                (string) $schedule->timeSlot->start_time,
                (string) $schedule->timeSlot->end_time
            ))
            ->map(function ($schedule): string {
                $startTime = substr((string) $schedule->timeSlot->start_time, 0, 5);
                $endTime = substr((string) $schedule->timeSlot->end_time, 0, 5);

                return "- {$schedule->dayLabel()} · {$startTime}–{$endTime}";
            })
            ->values();

        if (! $scheduleLines || $scheduleLines->isEmpty()) {
            throw new ContractConfirmationException(
                'Hợp đồng chưa có lịch học hợp lệ để tạo điều khoản.'
            );
        }

        $feeTypeText = match (strtoupper((string) $contract->agreed_fee_type)) {
            'HOURLY' => '/ giờ',
            default => throw new ContractConfirmationException(
                'Loại học phí của hợp đồng không hợp lệ.'
            ),
        };
        $fee = number_format((float) $contract->agreed_fee, 0, ',', '.').' VNĐ '.$feeTypeText;

        return implode("\n\n", [
            "Điều 1. Nội dung học tập\n{$learningContent}",
            "Điều 2. Lịch học theo thỏa thuận\nHai bên thống nhất thực hiện lịch học theo các khung thời gian sau:\n{$scheduleLines->implode("\n")}",
            "Điều 3. Học phí và phương thức thanh toán\nNgười học đồng ý thanh toán học phí với mức {$fee} cho gia sư.\nPhương thức thanh toán được hai bên thống nhất là {$paymentMethodText}.",
            "Điều 4. Thời hạn hợp đồng\nHợp đồng có hiệu lực từ ngày {$startDate} đến hết ngày {$endDate}.\nTrong thời hạn này, hai bên thực hiện việc học theo nội dung và lịch học đã được ghi trong hợp đồng.",
            "Điều 5. Xác nhận hợp đồng\nHợp đồng chỉ hoàn tất xác nhận khi cả người học và gia sư đều xác nhận trên hệ thống GiaSu.\nMỗi bên có trách nhiệm kiểm tra nội dung hợp đồng trước khi thực hiện xác nhận.",
        ]);
    }

    private function previewPaymentMethod(mixed $paymentMethod): string
    {
        return match (strtoupper(trim(is_string($paymentMethod) ? $paymentMethod : ''))) {
            Contract::PAYMENT_METHOD_BANK_TRANSFER => 'Chuyển khoản',
            Contract::PAYMENT_METHOD_CASH => 'Tiền mặt',
            default => self::PREVIEW_PAYMENT_METHOD_PLACEHOLDER,
        };
    }

    private function previewDate(mixed $date, string $placeholder): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('d/m/Y');
        }

        $normalizedDate = is_string($date) ? trim($date) : '';

        if ($normalizedDate === '') {
            return $placeholder;
        }

        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $normalizedDate);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            ! $parsedDate
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $parsedDate->format('Y-m-d') !== $normalizedDate
        ) {
            return $placeholder;
        }

        return $parsedDate->format('d/m/Y');
    }
}
