<?php

use App\Http\Controllers\Api\AgencyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientActivityController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientDocumentController;
use App\Http\Controllers\Api\ClientStatusController;
use App\Http\Controllers\Api\CompanyVisitController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectVisitController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalespeopleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('api.auth')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/projects/default', [ProjectController::class, 'default']);
    Route::get('/salespeople', [SalespeopleController::class, 'index']);
    Route::post('/salespeople', [SalespeopleController::class, 'store'])->middleware('role:is_admin');
    Route::patch('/salespeople/{salesperson}', [SalespeopleController::class, 'update'])->middleware('role:is_admin');
    Route::delete('/salespeople/{salesperson}', [SalespeopleController::class, 'destroy'])->middleware('role:is_admin');
    Route::put('/salespeople/{salesperson}/default', [SalespeopleController::class, 'setDefault'])->middleware('role:is_admin');

    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store'])->middleware('role:can_write');
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::patch('/clients/{client}', [ClientController::class, 'update'])->middleware('role:can_write');
    Route::put('/clients/{client}/follow-up', [ClientController::class, 'saveFollowUp'])->middleware('role:can_write');

    Route::get('/clients/{client}/activities', [ClientActivityController::class, 'index']);
    Route::post('/clients/{client}/activities', [ClientActivityController::class, 'store'])->middleware('role:can_write');

    Route::get('/clients/{client}/documents', [ClientDocumentController::class, 'index']);
    Route::post('/clients/{client}/documents', [ClientDocumentController::class, 'store'])->middleware('role:can_write');
    Route::get('/clients/{client}/documents/{document}/download', [ClientDocumentController::class, 'download']);
    Route::delete('/clients/{client}/documents/{document}', [ClientDocumentController::class, 'destroy'])->middleware('role:is_manager');

    Route::post('/clients/{client}/status', [ClientStatusController::class, 'store'])->middleware('role:can_write');

    Route::get('/agencies', [AgencyController::class, 'index']);
    Route::post('/agencies', [AgencyController::class, 'store'])->middleware('role:can_write');
    Route::get('/agencies/{agency}', [AgencyController::class, 'show']);
    Route::patch('/agencies/{agency}', [AgencyController::class, 'update'])->middleware('role:can_write');
    Route::delete('/agencies/{agency}', [AgencyController::class, 'destroy'])->middleware('role:is_manager');
    Route::get('/agencies/{agency}/summary', [AgencyController::class, 'summary']);
    Route::get('/agencies/{agency}/clients', [AgencyController::class, 'clients']);
    Route::get('/agencies/{agency}/project-visits', [AgencyController::class, 'projectVisits']);
    Route::get('/agencies/{agency}/company-visits', [AgencyController::class, 'companyVisits']);

    Route::get('/project-visits', [ProjectVisitController::class, 'index']);
    Route::post('/project-visits', [ProjectVisitController::class, 'store'])->middleware('role:can_write');
    Route::patch('/project-visits/{projectVisit}', [ProjectVisitController::class, 'update'])->middleware('role:can_write');
    Route::delete('/project-visits/{projectVisit}', [ProjectVisitController::class, 'destroy'])->middleware('role:is_manager');

    Route::get('/company-visits', [CompanyVisitController::class, 'index']);
    Route::post('/company-visits', [CompanyVisitController::class, 'store'])->middleware('role:can_write');
    Route::patch('/company-visits/{companyVisit}', [CompanyVisitController::class, 'update'])->middleware('role:can_write');
    Route::delete('/company-visits/{companyVisit}', [CompanyVisitController::class, 'destroy'])->middleware('role:is_manager');

    Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('/reports/daily', [ReportController::class, 'daily']);
    Route::put('/reports/daily/summary', [ReportController::class, 'upsertDailySummary'])->middleware('role:can_write');
    Route::get('/reports/performance', [ReportController::class, 'performance']);

    Route::get('/users', [UserController::class, 'index'])->middleware('role:is_admin');
    Route::post('/users', [UserController::class, 'store'])->middleware('role:is_admin');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('role:is_admin');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('role:is_admin');
    Route::post('/users/{user}/roles', [UserController::class, 'storeRole'])->middleware('role:is_admin');
    Route::delete('/users/{user}/roles/{role}', [UserController::class, 'destroyRole'])->middleware('role:is_admin');
});

Route::get('/client-documents/{document}/file', [ClientDocumentController::class, 'stream'])
    ->name('client-documents.stream')
    ->middleware('signed');
