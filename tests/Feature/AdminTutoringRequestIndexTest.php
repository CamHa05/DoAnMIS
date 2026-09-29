<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminTutoringRequestIndexTest extends TestCase
{
    private int $userSequence = 0;

    private int $subjectSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->createSchema();
    }

    public function test_only_admin_can_open_the_request_directory(): void
    {
        $admin = $this->createUser('Quản trị GiaSu', true);
        $member = $this->createUser('Người học');

        $this->get(route('admin.requests.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($member)
            ->get(route('admin.requests.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertViewIs('admin.requests.index')
            ->assertSeeText('Quản lý yêu cầu học')
            ->assertDontSeeText('Theo dõi và quản lý các yêu cầu tìm gia sư được tạo trên hệ thống.')
            ->assertSee('class="admin-navigation__item is-active"', false);
    }

    public function test_statistics_are_counted_from_request_status(): void
    {
        $admin = $this->createUser('Quản trị thống kê', true);
        $learner = $this->createUser('Người học thống kê');

        foreach (['PENDING', 'OPEN', 'OPEN', 'MATCHED', 'EXPIRED', 'REJECTED'] as $status) {
            $this->createRequest($learner, ['status' => $status]);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.requests.index'))
            ->assertOk();

        $this->assertSame([
            'total' => 6,
            'pending' => 1,
            'open' => 2,
            'matched' => 1,
        ], $response->viewData('statistics'));
    }

    public function test_search_accepts_formatted_request_code_and_learner_name(): void
    {
        $admin = $this->createUser('Quản trị tìm kiếm', true);
        $firstLearner = $this->createUser('Nguyễn Minh Anh');
        $secondLearner = $this->createUser('Trần Thu Hà');
        $firstRequestId = $this->createRequest($firstLearner);
        $this->createRequest($secondLearner);

        $this->actingAs($admin)
            ->get(route('admin.requests.index', ['q' => '#REQ-'.str_pad((string) $firstRequestId, 3, '0', STR_PAD_LEFT)]))
            ->assertOk()
            ->assertSeeText($firstLearner->full_name)
            ->assertDontSeeText($secondLearner->full_name);

        $this->actingAs($admin)
            ->get(route('admin.requests.index', ['q' => 'Thu Hà']))
            ->assertOk()
            ->assertSeeText($secondLearner->full_name)
            ->assertDontSeeText($firstLearner->full_name);
    }

    public function test_type_status_and_learning_mode_filters_use_database_values(): void
    {
        $admin = $this->createUser('Quản trị bộ lọc', true);
        $publicOnline = $this->createUser('Công khai online');
        $directOffline = $this->createUser('Trực tiếp offline');
        $matchedPublic = $this->createUser('Công khai đã ghép');

        $this->createRequest($publicOnline, [
            'request_type' => 'PUBLIC',
            'learning_mode' => 'ONLINE',
            'status' => 'OPEN',
        ]);
        $this->createRequest($directOffline, [
            'request_type' => 'DIRECT',
            'learning_mode' => 'OFFLINE',
            'status' => 'PENDING',
        ]);
        $this->createRequest($matchedPublic, [
            'request_type' => 'PUBLIC',
            'learning_mode' => 'OFFLINE',
            'status' => 'MATCHED',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.requests.index', ['type' => 'direct']))
            ->assertSeeText($directOffline->full_name)
            ->assertDontSeeText($publicOnline->full_name)
            ->assertDontSeeText($matchedPublic->full_name);

        $this->actingAs($admin)
            ->get(route('admin.requests.index', ['status' => 'matched']))
            ->assertSeeText($matchedPublic->full_name)
            ->assertDontSeeText($publicOnline->full_name)
            ->assertDontSeeText($directOffline->full_name);

        $this->actingAs($admin)
            ->get(route('admin.requests.index', ['mode' => 'online']))
            ->assertSeeText($publicOnline->full_name)
            ->assertDontSeeText($directOffline->full_name)
            ->assertDontSeeText($matchedPublic->full_name);
    }

    public function test_public_request_shows_application_count_and_direct_request_shows_dash(): void
    {
        $admin = $this->createUser('Quản trị ứng tuyển', true);
        $publicLearner = $this->createUser('Người học công khai');
        $directLearner = $this->createUser('Người học gửi trực tiếp');
        $publicRequestId = $this->createRequest($publicLearner);
        $this->createRequest($directLearner, [
            'request_type' => 'DIRECT',
            'status' => 'PENDING',
        ]);

        foreach (range(1, 4) as $index) {
            DB::table('tutor_applications')->insert([
                'request_id' => $publicRequestId,
                'tutor_profile_id' => $index,
                'status' => 'PENDING',
                'applied_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSeeText('4 gia sư')
            ->assertSeeText('—')
            ->assertSeeText('Gửi trực tiếp');
    }

    public function test_table_relationships_are_eager_loaded_without_lazy_queries(): void
    {
        $admin = $this->createUser('Quản trị eager', true);
        $learner = $this->createUser('Người học eager');
        $requestId = $this->createRequest($learner, ['learning_mode' => 'OFFLINE']);

        Model::preventLazyLoading(true);

        try {
            $response = $this->actingAs($admin)
                ->get(route('admin.requests.index'))
                ->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }

        $requestItem = $response->viewData('requests')->firstWhere('request_id', $requestId);

        $this->assertTrue($requestItem->relationLoaded('user'));
        $this->assertTrue($requestItem->relationLoaded('subjectLevel'));
        $this->assertTrue($requestItem->subjectLevel->relationLoaded('subject'));
        $this->assertTrue($requestItem->subjectLevel->relationLoaded('educationLevel'));
        $this->assertTrue($requestItem->relationLoaded('ward'));
        $this->assertTrue($requestItem->ward->relationLoaded('province'));
        $this->assertArrayHasKey('applications_count', $requestItem->getAttributes());
    }

    public function test_deadline_label_uses_status_before_expiration_time(): void
    {
        $admin = $this->createUser('Quản trị thời hạn', true);
        $learner = $this->createUser('Người học thời hạn');

        $this->createRequest($learner, [
            'status' => 'MATCHED',
            'expires_at' => now()->subDay(),
        ]);
        $this->createRequest($learner, [
            'status' => 'EXPIRED',
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSeeText('Đã kết thúc')
            ->assertSeeText('Đã hết hạn');
    }

    public function test_pagination_orders_newest_first_and_preserves_filters(): void
    {
        $admin = $this->createUser('Quản trị phân trang', true);

        foreach (range(1, 11) as $index) {
            $learner = $this->createUser('Người học phân trang '.$index);
            $this->createRequest($learner, [
                'created_at' => now()->addMinutes($index),
                'updated_at' => now()->addMinutes($index),
            ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.requests.index', [
                'q' => 'Người học phân trang',
                'type' => 'public',
                'status' => 'open',
                'mode' => 'online',
            ]))
            ->assertOk();

        $paginator = $response->viewData('requests');
        $nextPageQuery = [];
        parse_str((string) parse_url($paginator->nextPageUrl(), PHP_URL_QUERY), $nextPageQuery);

        $this->assertSame(11, $paginator->total());
        $this->assertSame(10, $paginator->perPage());
        $this->assertGreaterThan(
            $paginator->last()->created_at,
            $paginator->first()->created_at
        );
        $this->assertSame('Người học phân trang', $nextPageQuery['q']);
        $this->assertSame('public', $nextPageQuery['type']);
        $this->assertSame('open', $nextPageQuery['status']);
        $this->assertSame('online', $nextPageQuery['mode']);
        $this->assertSame('2', $nextPageQuery['page']);
    }

    public function test_invalid_array_filters_fall_back_safely(): void
    {
        $admin = $this->createUser('Quản trị dữ liệu lỗi', true);
        $learner = $this->createUser('Người học hợp lệ');
        $this->createRequest($learner);

        $response = $this->actingAs($admin)
            ->get('/admin/requests?q[]=REQ-1&type[]=public&status[]=open&mode[]=online')
            ->assertOk()
            ->assertSeeText($learner->full_name);

        $this->assertNull($response->viewData('search'));
        $this->assertNull($response->viewData('requestType'));
        $this->assertNull($response->viewData('status'));
        $this->assertNull($response->viewData('learningMode'));
    }

    private function createUser(string $name, bool $isAdmin = false): User
    {
        $this->userSequence++;

        $userId = DB::table('users')->insertGetId([
            'google_id' => null,
            'email' => 'request-user-'.$this->userSequence.'@example.test',
            'full_name' => $name,
            'avatar_url' => null,
            'phone' => null,
            'is_admin' => $isAdmin,
            'status' => 'ACTIVE',
            'last_login' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRequest(User $learner, array $overrides = []): int
    {
        $this->subjectSequence++;
        $subjectId = DB::table('subjects')->insertGetId([
            'subject_name' => 'Môn học '.$this->subjectSequence,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $educationLevelId = DB::table('education_levels')->insertGetId([
            'level_name' => 'Trung học',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subjectLevelId = DB::table('subject_levels')->insertGetId([
            'subject_id' => $subjectId,
            'education_level_id' => $educationLevelId,
            'level_name' => 'Lớp '.$this->subjectSequence,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $provinceId = DB::table('provinces')->insertGetId([
            'province_name' => 'TP.HCM',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $wardId = DB::table('wards')->insertGetId([
            'province_id' => $provinceId,
            'ward_name' => 'Phường '.$this->subjectSequence,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('tutoring_requests')->insertGetId(array_merge([
            'user_id' => $learner->user_id,
            'subject_level_id' => $subjectLevelId,
            'ward_id' => $wardId,
            'request_type' => 'PUBLIC',
            'learning_mode' => 'ONLINE',
            'expected_fee' => 180000,
            'fee_type' => 'HOURLY',
            'status' => 'OPEN',
            'expires_at' => now()->addDays(5),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('user_id');
            $table->string('google_id')->nullable()->unique();
            $table->string('email')->unique();
            $table->string('full_name', 100);
            $table->string('avatar_url', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('status', 20)->default('ACTIVE');
            $table->dateTime('last_login')->nullable();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->bigIncrements('subject_id');
            $table->string('subject_name', 100);
            $table->timestamps();
        });

        Schema::create('education_levels', function (Blueprint $table): void {
            $table->bigIncrements('education_level_id');
            $table->string('level_name', 100);
            $table->timestamps();
        });

        Schema::create('subject_levels', function (Blueprint $table): void {
            $table->bigIncrements('subject_level_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->string('level_name', 100);
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table): void {
            $table->bigIncrements('province_id');
            $table->string('province_name', 100);
            $table->timestamps();
        });

        Schema::create('wards', function (Blueprint $table): void {
            $table->bigIncrements('ward_id');
            $table->unsignedBigInteger('province_id');
            $table->string('ward_name', 100);
            $table->timestamps();
        });

        Schema::create('tutoring_requests', function (Blueprint $table): void {
            $table->bigIncrements('request_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('target_tutor_profile_id')->nullable();
            $table->unsignedBigInteger('subject_level_id');
            $table->unsignedBigInteger('ward_id')->nullable();
            $table->string('request_type', 20);
            $table->string('learning_mode', 20);
            $table->decimal('expected_fee', 12, 2)->nullable();
            $table->string('fee_type', 20)->default('HOURLY');
            $table->string('status', 30);
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_applications', function (Blueprint $table): void {
            $table->bigIncrements('application_id');
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('tutor_profile_id');
            $table->string('status', 30)->default('PENDING');
            $table->dateTime('applied_at');
            $table->dateTime('updated_at');
        });
    }
}
