<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnvironmentController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InternalNotificationController;
use App\Http\Controllers\OccurrenceCategoryController;
use App\Http\Controllers\OccurrenceController;
use App\Http\Controllers\OccurrenceResolutionController;
use App\Http\Controllers\OccurrenceTriageController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PrivateAttachmentController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\ServiceOrderController;
use App\Http\Controllers\ServiceOrderEntryController;
use App\Http\Controllers\ServiceOrderWorkflowController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/up', HealthController::class)->name('health');

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/painel', DashboardController::class)->name('dashboard');
    Route::get('/notificacoes', [InternalNotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notificacoes/preferencias', [InternalNotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
    Route::get('/notificacoes/{notification}/abrir', [InternalNotificationController::class, 'open'])->name('notifications.open');
    Route::patch('/notificacoes/lidas', [InternalNotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notificacoes/{notification}/lida', [InternalNotificationController::class, 'read'])->name('notifications.read');
    Route::resource('organizacoes', OrganizationController::class)
        ->parameters(['organizacoes' => 'organization'])
        ->names('organizations')
        ->except(['show', 'destroy']);
    Route::resource('escolas', SchoolController::class)
        ->parameters(['escolas' => 'school'])
        ->names('schools')
        ->except(['show', 'destroy']);
    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])
        ->names('users')
        ->except(['show', 'destroy']);
    Route::patch('/ambientes/{environment}/desativar', [EnvironmentController::class, 'deactivate'])->name('environments.deactivate');
    Route::resource('ambientes', EnvironmentController::class)
        ->parameters(['ambientes' => 'environment'])
        ->names('environments')
        ->except(['show', 'destroy']);
    Route::get('/categorias/{category}/disponibilidade', [OccurrenceCategoryController::class, 'availability'])->name('categories.availability');
    Route::put('/categorias/{category}/disponibilidade', [OccurrenceCategoryController::class, 'updateAvailability'])->name('categories.availability.update');
    Route::patch('/categorias/{category}/desativar', [OccurrenceCategoryController::class, 'deactivate'])->name('categories.deactivate');
    Route::resource('categorias', OccurrenceCategoryController::class)
        ->parameters(['categorias' => 'category'])
        ->names('categories')
        ->except(['show', 'destroy']);
    Route::post('/ocorrencias/{occurrence}/iniciar-triagem', [OccurrenceTriageController::class, 'start'])->name('occurrences.triage.start');
    Route::post('/ocorrencias/{occurrence}/confirmar-prioridade', [OccurrenceTriageController::class, 'confirmPriority'])->name('occurrences.priority.confirm');
    Route::post('/ocorrencias/{occurrence}/solicitar-informacao', [OccurrenceTriageController::class, 'requestInformation'])->name('occurrences.information.request');
    Route::post('/ocorrencias/{occurrence}/fornecer-informacao', [OccurrenceTriageController::class, 'provideInformation'])->name('occurrences.information.provide');
    Route::post('/ocorrencias/{occurrence}/nao-procede', [OccurrenceTriageController::class, 'notApplicable'])->name('occurrences.not-applicable');
    Route::post('/ocorrencias/{occurrence}/duplicada', [OccurrenceTriageController::class, 'duplicate'])->name('occurrences.duplicate');
    Route::post('/ocorrencias/{occurrence}/encaminhar', [OccurrenceTriageController::class, 'forward'])->name('occurrences.forward');
    Route::post('/ocorrencias/{o}/encerrar', [OccurrenceResolutionController::class, 'close'])->name('occurrences.close');
    Route::post('/ocorrencias/{o}/solicitar-reabertura', [OccurrenceResolutionController::class, 'requestReopening'])->name('occurrences.reopening.request');
    Route::post('/ocorrencias/{o}/reabrir', [OccurrenceResolutionController::class, 'reopen'])->name('occurrences.reopen');
    Route::resource('ocorrencias', OccurrenceController::class)
        ->parameters(['ocorrencias' => 'occurrence'])
        ->names('occurrences')
        ->only(['index', 'create', 'store', 'show']);
    Route::post('/ocorrencias/{occurrence}/anexos', [PrivateAttachmentController::class, 'storeForOccurrence'])->name('occurrences.attachments');
    Route::post('/ordens-servico/{o}/aprovar', [ServiceOrderWorkflowController::class, 'approve'])->name('service-orders.approve');
    Route::post('/ordens-servico/{o}/rejeitar', [ServiceOrderWorkflowController::class, 'reject'])->name('service-orders.reject');
    Route::post('/ordens-servico/{o}/cancelar', [ServiceOrderWorkflowController::class, 'cancel'])->name('service-orders.cancel');
    Route::post('/ordens-servico/{o}/iniciar', [ServiceOrderWorkflowController::class, 'start'])->name('service-orders.start');
    Route::post('/ordens-servico/{o}/aguardar-material', [ServiceOrderWorkflowController::class, 'waitMaterial'])->name('service-orders.wait-material');
    Route::post('/ordens-servico/{o}/pausar', [ServiceOrderWorkflowController::class, 'pause'])->name('service-orders.pause');
    Route::post('/ordens-servico/{o}/retomar', [ServiceOrderWorkflowController::class, 'resume'])->name('service-orders.resume');
    Route::post('/ordens-servico/{o}/iniciar-emergencial', [ServiceOrderWorkflowController::class, 'emergency'])->name('service-orders.emergency');
    Route::post('/ordens-servico/{o}/ratificar-emergencia', [ServiceOrderWorkflowController::class, 'ratifyEmergency'])->name('service-orders.ratify-emergency');
    Route::post('/ordens-servico/{o}/concluir', [ServiceOrderWorkflowController::class, 'complete'])->name('service-orders.complete');
    Route::patch('/ordens-servico/{o}/equipe', [ServiceOrderController::class, 'updateTeam'])->name('service-orders.team.update');
    Route::post('/ordens-servico/{o}/diagnostico', [ServiceOrderEntryController::class, 'diagnosis'])->name('service-orders.diagnosis');
    Route::post('/ordens-servico/{o}/atualizacoes', [ServiceOrderEntryController::class, 'update'])->name('service-orders.updates');
    Route::post('/ordens-servico/{o}/materiais', [ServiceOrderEntryController::class, 'material'])->name('service-orders.materials');
    Route::post('/ordens-servico/{o}/tempos', [ServiceOrderEntryController::class, 'time'])->name('service-orders.work-logs');
    Route::post('/ordens-servico/{o}/custos', [ServiceOrderEntryController::class, 'cost'])->name('service-orders.costs');
    Route::post('/ordens-servico/{o}/anexos', [PrivateAttachmentController::class, 'store'])->name('service-orders.attachments');
    Route::get('/anexos/{attachment}', [PrivateAttachmentController::class, 'download'])->name('attachments.download');
    Route::resource('ordens-servico', ServiceOrderController::class)
        ->parameters(['ordens-servico' => 'serviceOrder'])
        ->names('service-orders')
        ->only(['index', 'create', 'store', 'show']);
    Route::post('/sair', [LoginController::class, 'destroy'])->name('logout');
});
