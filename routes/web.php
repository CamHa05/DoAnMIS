<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\AdminIdentityVerificationController;
use App\Http\Controllers\Web\AdminTutorController;
use App\Http\Controllers\Web\AdminTutoringClassController;
use App\Http\Controllers\Web\AdminTutoringRequestController;
use App\Http\Controllers\Web\AdminUserController;
use App\Http\Controllers\Web\ContractController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\IdentityVerificationController;
use App\Http\Controllers\Web\MyRequestController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\RequestController;
use App\Http\Controllers\Web\TutorAreaController;
use App\Http\Controllers\Web\TutorController;
use App\Http\Controllers\Web\TutoringClassController;
use App\Http\Controllers\Web\TutorProfileManagementController;
use App\Http\Controllers\Web\TutorRegistrationController;
use App\Http\Middleware\EnsureContactInformationComplete;
use App\Http\Middleware\EnsureIdentityVerified;
use App\Http\Middleware\EnsureTutorProfileExists;
use App\Http\Middleware\EnsureTutorRegistrationEditable;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsNotAdmin;
use App\Services\IdentityVerificationRequirement;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/tutors', [TutorController::class, 'index'])->name('tutors.index');
Route::get('/tutors/{tutor}', [TutorController::class, 'show'])->name('tutors.show');
Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
Route::get('/requests/{tutoringRequest}', [RequestController::class, 'show'])
    ->whereNumber('tutoringRequest')
    ->name('requests.show');
Route::get('/requests/{tutoringRequest}/applications/login', [RequestController::class, 'redirectGuestToLogin'])
    ->whereNumber('tutoringRequest')
    ->name('requests.applications.login');
Route::get('/tutors/{tutor}/request', [RequestController::class, 'directCreate'])
    ->whereNumber('tutor')
    ->middleware('auth')
    ->middleware(EnsureIdentityVerified::class)
    ->middleware(EnsureContactInformationComplete::class)
    ->name('requests.direct.create');
Route::post('/tutors/{tutor}/request', [RequestController::class, 'storeDirect'])
    ->whereNumber('tutor')
    ->middleware('auth')
    ->middleware(EnsureIdentityVerified::class)
    ->middleware(EnsureContactInformationComplete::class)
    ->name('requests.direct.store');

Route::get('/login', [GoogleAuthController::class, 'showLogin'])
    ->name('login');

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])
    ->name('auth.google.redirect');

Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('auth.google.callback');

Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::post('/notifications/{notification}/open', [NotificationController::class, 'open'])
        ->whereNumber('notification')
        ->name('notifications.open');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->whereNumber('notification')
        ->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');

    Route::get('/admin', [AdminDashboardController::class, 'index'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.dashboard');

    Route::get('/admin/tutors', [AdminTutorController::class, 'index'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.tutors.index');

    Route::get('/admin/users', [AdminUserController::class, 'index'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.users.index');

    Route::get('/admin/requests', [AdminTutoringRequestController::class, 'index'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.requests.index');

    Route::get('/admin/requests/{tutoringRequest}', [AdminTutoringRequestController::class, 'show'])
        ->whereNumber('tutoringRequest')
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.requests.show');

    Route::get('/admin/classes', [AdminTutoringClassController::class, 'index'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.classes.index');

    Route::get('/admin/classes/{tutoringClass}', [AdminTutoringClassController::class, 'show'])
        ->whereNumber('tutoringClass')
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.classes.show');

    Route::get('/admin/users/{user}', [AdminUserController::class, 'show'])
        ->whereNumber('user')
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.users.show');

    Route::patch('/admin/users/{user}/disable', [AdminUserController::class, 'disable'])
        ->whereNumber('user')
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.users.disable');

    Route::patch('/admin/users/{user}/activate', [AdminUserController::class, 'activate'])
        ->whereNumber('user')
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.users.activate');

    Route::get('/admin/tutors/{tutorProfile}', [AdminTutorController::class, 'show'])
        ->whereNumber('tutorProfile')
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.tutors.show');

    Route::patch('/admin/tutors/{tutorProfile}/approve', [AdminTutorController::class, 'approve'])
        ->whereNumber('tutorProfile')
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.tutors.approve');

    Route::patch('/admin/tutors/{tutorProfile}/reject', [AdminTutorController::class, 'reject'])
        ->whereNumber('tutorProfile')
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.tutors.reject');

    Route::get('/admin/tutors/{tutorProfile}/documents/{document}', [AdminTutorController::class, 'downloadDocument'])
        ->whereNumber(['tutorProfile', 'document'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.tutors.documents.download');

    Route::get('/admin/tutors/{tutorProfile}/changes/{changeRequest}/document', [AdminTutorController::class, 'downloadChangeDocument'])
        ->whereNumber(['tutorProfile', 'changeRequest'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.tutors.changes.document');

    Route::patch('/admin/tutors/{tutorProfile}/changes/{changeRequest}/approve', [AdminTutorController::class, 'approveChange'])
        ->whereNumber(['tutorProfile', 'changeRequest'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.tutors.changes.approve');

    Route::patch('/admin/tutors/{tutorProfile}/changes/{changeRequest}/reject', [AdminTutorController::class, 'rejectChange'])
        ->whereNumber(['tutorProfile', 'changeRequest'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.tutors.changes.reject');
    Route::get('/admin/identity-verifications', [AdminIdentityVerificationController::class, 'index'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.identity-verifications.index');

    Route::get('/admin/identity-verifications/{identityVerification}', [AdminIdentityVerificationController::class, 'show'])
        ->whereNumber('identityVerification')
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.identity-verifications.show');

    Route::patch('/admin/identity-verifications/{identityVerification}/approve', [AdminIdentityVerificationController::class, 'approve'])
        ->whereNumber('identityVerification')
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.identity-verifications.approve');

    Route::patch('/admin/identity-verifications/{identityVerification}/reject', [AdminIdentityVerificationController::class, 'reject'])
        ->whereNumber('identityVerification')
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.identity-verifications.reject');

    Route::get('/admin/identity-verifications/{identityVerification}/documents/{side}', [AdminIdentityVerificationController::class, 'document'])
        ->whereNumber('identityVerification')
        ->whereIn('side', ['front', 'back'])
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.identity-verifications.document');

    Route::middleware(EnsureUserIsNotAdmin::class)->group(function () {
        Route::get('/identity-verification', [IdentityVerificationController::class, 'show'])
            ->name('identity-verification.show');
        Route::post('/identity-verification', [IdentityVerificationController::class, 'submit'])
            ->middleware('throttle:5,1')
            ->name('identity-verification.submit');
        Route::middleware(EnsureTutorRegistrationEditable::class)->group(function () {
            Route::get('/become-tutor', [TutorRegistrationController::class, 'edit'])
                ->name('tutor-registration.basic.edit');

            Route::put('/become-tutor', [TutorRegistrationController::class, 'update'])
                ->name('tutor-registration.basic.update');

            Route::get('/become-tutor/specialization', [TutorRegistrationController::class, 'editSpecialization'])
                ->name('tutor-registration.specialization.edit');

            Route::put('/become-tutor/specialization', [TutorRegistrationController::class, 'updateSpecialization'])
                ->name('tutor-registration.specialization.update');

            Route::get('/become-tutor/teaching-preferences', [TutorRegistrationController::class, 'editTeachingPreferences'])
                ->name('tutor-registration.teaching-preferences.edit');

            Route::put('/become-tutor/teaching-preferences', [TutorRegistrationController::class, 'updateTeachingPreferences'])
                ->name('tutor-registration.teaching-preferences.update');

            Route::get('/become-tutor/availability', [TutorRegistrationController::class, 'editAvailability'])
                ->name('tutor-registration.availability.edit');

            Route::put('/become-tutor/availability', [TutorRegistrationController::class, 'updateAvailability'])
                ->name('tutor-registration.availability.update');

            Route::get('/become-tutor/documents', [TutorRegistrationController::class, 'editDocuments'])
                ->name('tutor-registration.documents.edit');

            Route::post('/become-tutor/documents', [TutorRegistrationController::class, 'storeDocument'])
                ->name('tutor-registration.documents.store');

            Route::post('/become-tutor/documents/continue', [TutorRegistrationController::class, 'continueDocuments'])
                ->name('tutor-registration.documents.continue');

            Route::delete('/become-tutor/documents/{document}', [TutorRegistrationController::class, 'destroyDocument'])
                ->whereNumber('document')
                ->name('tutor-registration.documents.destroy');
        });

        Route::get('/become-tutor/confirmation', [TutorRegistrationController::class, 'editConfirmation'])
            ->name('tutor-registration.confirmation.edit');

        Route::post('/become-tutor/confirmation', [TutorRegistrationController::class, 'submitConfirmation'])
            ->name('tutor-registration.confirmation.submit');

        Route::get('/requests/create', [RequestController::class, 'create'])
            ->middleware(EnsureIdentityVerified::class)
            ->middleware(EnsureContactInformationComplete::class)
            ->name('requests.create');

        Route::post('/requests', [RequestController::class, 'store'])
            ->middleware(EnsureIdentityVerified::class)
            ->middleware(EnsureContactInformationComplete::class)
            ->middleware('throttle:5,1')
            ->name('requests.store');

        Route::post('/requests/{tutoringRequest}/applications', [RequestController::class, 'storeApplication'])
            ->whereNumber('tutoringRequest')
            ->middleware(EnsureIdentityVerified::class.':'.IdentityVerificationRequirement::ACTION_TUTOR_APPLICATION)
            ->middleware(EnsureContactInformationComplete::class)
            ->name('requests.applications.store');
    });

    Route::get('/become-tutor/documents/{document}/download', [TutorRegistrationController::class, 'downloadDocument'])
        ->whereNumber('document')
        ->name('tutor-registration.documents.download');

    Route::get('/my-requests', [MyRequestController::class, 'index'])
        ->name('my-requests.index');

    Route::get('/my-requests/{tutoringRequest}/applications', [MyRequestController::class, 'applicationsIndex'])
        ->whereNumber('tutoringRequest')
        ->middleware(EnsureUserIsNotAdmin::class)
        ->name('my-requests.applications.index');

    Route::get('/my-requests/{tutoringRequest}/applications/{tutorApplication}', [MyRequestController::class, 'applicationsShow'])
        ->whereNumber(['tutoringRequest', 'tutorApplication'])
        ->middleware(EnsureUserIsNotAdmin::class)
        ->name('my-requests.applications.show');

    Route::patch('/my-requests/{tutoringRequest}/applications/{tutorApplication}/select', [MyRequestController::class, 'selectTutor'])
        ->whereNumber(['tutoringRequest', 'tutorApplication'])
        ->middleware(EnsureUserIsNotAdmin::class)
        ->name('my-requests.applications.select');

    Route::get('/my-requests/{tutoringRequest}', [MyRequestController::class, 'show'])
        ->whereNumber('tutoringRequest')
        ->name('my-requests.show');

    Route::get('/classes', [TutoringClassController::class, 'index'])
        ->name('classes.index');
    Route::get('/classes/{tutoringClass}', [TutoringClassController::class, 'show'])
        ->whereNumber('tutoringClass')
        ->name('classes.show');

    Route::middleware(EnsureTutorProfileExists::class)->group(function () {
        Route::get('/tutor-area/profile', [TutorAreaController::class, 'profile'])
            ->name('tutor-area.profile');

        Route::middleware(EnsureUserIsNotAdmin::class)->group(function () {
            Route::get('/tutor-area/profile/edit', [TutorProfileManagementController::class, 'edit'])
                ->name('tutor-area.profile.edit');
            Route::put('/tutor-area/profile', [TutorProfileManagementController::class, 'update'])
                ->name('tutor-area.profile.update');
            Route::post('/tutor-area/profile/availability', [TutorProfileManagementController::class, 'storeAvailability'])
                ->name('tutor-area.profile.availability.store');
            Route::patch('/tutor-area/profile/availability/{availability}', [TutorProfileManagementController::class, 'updateAvailability'])
                ->whereNumber('availability')
                ->name('tutor-area.profile.availability.update');
            Route::delete('/tutor-area/profile/availability/{availability}', [TutorProfileManagementController::class, 'destroyAvailability'])
                ->whereNumber('availability')
                ->name('tutor-area.profile.availability.destroy');
            Route::post('/tutor-area/profile/documents', [TutorProfileManagementController::class, 'storeDocument'])
                ->name('tutor-area.profile.documents.store');
            Route::put('/tutor-area/profile/documents/{document}', [TutorProfileManagementController::class, 'updateDocument'])
                ->whereNumber('document')
                ->name('tutor-area.profile.documents.update');
            Route::delete('/tutor-area/profile/documents/{document}', [TutorProfileManagementController::class, 'destroyDocument'])
                ->whereNumber('document')
                ->name('tutor-area.profile.documents.destroy');
            Route::get('/tutor-area/profile/changes/{changeRequest}/document', [TutorProfileManagementController::class, 'downloadDocumentChange'])
                ->whereNumber('changeRequest')
                ->name('tutor-area.profile.changes.document');
        });

        Route::get('/tutor-area/direct-requests', [TutorAreaController::class, 'directRequests'])
            ->name('tutor-area.direct-requests');
        Route::get('/tutor-area/direct-requests/{tutoringRequest}', [TutorAreaController::class, 'directRequestShow'])
            ->whereNumber('tutoringRequest')
            ->name('tutor-area.direct-requests.show');
        Route::patch('/tutor-area/direct-requests/{tutoringRequest}/accept', [RequestController::class, 'directAccept'])
            ->whereNumber('tutoringRequest')
            ->name('tutor-area.direct-requests.accept');
        Route::patch('/tutor-area/direct-requests/{tutoringRequest}/reject', [RequestController::class, 'directReject'])
            ->whereNumber('tutoringRequest')
            ->name('tutor-area.direct-requests.reject');
        Route::get('/tutor-area/applications', [TutorAreaController::class, 'applications'])
            ->name('tutor-area.applications');
        Route::get('/tutor-area/applications/{application}', [TutorAreaController::class, 'applicationShow'])
            ->whereNumber('application')
            ->name('tutor-area.applications.show');
    });

    Route::get('/contracts/{contract}', [ContractController::class, 'show'])
        ->whereNumber('contract')
        ->middleware(EnsureUserIsNotAdmin::class)
        ->name('contracts.show');

    Route::patch('/contracts/{contract}/confirm', [ContractController::class, 'confirm'])
        ->whereNumber('contract')
        ->middleware(EnsureUserIsNotAdmin::class)
        ->name('contracts.confirm');

    Route::get('/profile', [ProfileController::class, 'show'])
        ->name('profile.show');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::post('/logout', [GoogleAuthController::class, 'logout'])
        ->name('logout');
});
