<?php

use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\DistributionRuleController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\RuleController;
use App\Http\Controllers\Admin\ScoringRuleController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Companies\CompanyController;
use App\Http\Controllers\Companies\GooglePlacesProfileController;
use App\Http\Controllers\Companies\ImportController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Kanban\FollowUpController;
use App\Http\Controllers\Kanban\LeadAssignmentController;
use App\Http\Controllers\Kanban\LeadController;
use App\Http\Controllers\Kanban\LeadInteractionController;
use App\Http\Controllers\Kanban\LeadStageController;
use App\Http\Controllers\Kanban\ProposalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/**
 * Raiz mostra a tela de login (convencional — sem landing/marketing page, já que o login é o
 * único ponto de entrada real do CRM). `RedirectIfAuthenticated` (middleware `guest`) já manda
 * quem já está logado direto para `route('dashboard')`, então não precisa de lógica própria
 * aqui. Mesmo Controller/view de `GET /login` (`routes/auth.php`) — sem duplicar a tela.
 */
Route::get('/', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/export', [DashboardController::class, 'export'])->name('dashboard.export');

    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::post('/companies/{company}/places-profile', [GooglePlacesProfileController::class, 'store'])->name('companies.places-profile.store');

    Route::get('/companies/import', [ImportController::class, 'create'])->name('companies.import');
    Route::post('/companies/import', [ImportController::class, 'store'])->name('companies.import.store');
    Route::get('/companies/import/{importBatch}/mapping', [ImportController::class, 'mapping'])->name('companies.import.mapping');
    Route::post('/companies/import/{importBatch}/mapping', [ImportController::class, 'confirmMapping'])->name('companies.import.confirmMapping');
    Route::get('/companies/import/{importBatch}/confirm', [ImportController::class, 'confirm'])->name('companies.import.confirm');
    Route::post('/companies/import/{importBatch}/process', [ImportController::class, 'process'])->name('companies.import.process');

    Route::get('/kanban', [LeadController::class, 'board'])->name('kanban.board');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::patch('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
    Route::patch('/leads/{lead}/stage', [LeadStageController::class, 'update'])->name('leads.stage.update');

    Route::post('/leads/{lead}/proposals', [ProposalController::class, 'store'])->name('proposals.store');
    Route::get('/proposal-attachments/{attachment}/download', [ProposalController::class, 'downloadAttachment'])->name('proposals.attachments.download');

    Route::post('/leads/{lead}/follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
    Route::patch('/follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');

    Route::post('/leads/{lead}/interactions', [LeadInteractionController::class, 'store'])->name('interactions.store');

    Route::post('/leads/{lead}/assignment', [LeadAssignmentController::class, 'store'])->name('leads.assignment.store');
    Route::post('/leads/distribute', [LeadAssignmentController::class, 'distributeNow'])->name('leads.distribute');
    Route::post('/leads/bulk-assignment', [LeadAssignmentController::class, 'bulkAssign'])->name('leads.bulk-assignment');
    Route::post('/leads/bulk-stage', [LeadStageController::class, 'bulkUpdate'])->name('leads.bulk-stage');

    Route::get('/admin/audit', [AuditController::class, 'index'])->name('admin.audit.index');
    Route::get('/admin/audit/{entityType}/{entityId}', [AuditController::class, 'show'])->name('admin.audit.show');

    Route::get('/admin/appearance', [AppearanceController::class, 'edit'])->name('admin.appearance.edit');
    Route::put('/admin/appearance', [AppearanceController::class, 'update'])->name('admin.appearance.update');

    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::patch('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');

    Route::get('/admin/teams', [TeamController::class, 'index'])->name('admin.teams.index');
    Route::post('/admin/teams', [TeamController::class, 'store'])->name('admin.teams.store');
    Route::patch('/admin/teams/{team}', [TeamController::class, 'update'])->name('admin.teams.update');
    Route::delete('/admin/teams/{team}', [TeamController::class, 'destroy'])->name('admin.teams.destroy');

    Route::get('/admin/products', [AdminProductController::class, 'index'])->name('admin.products.index');
    Route::post('/admin/products', [AdminProductController::class, 'store'])->name('admin.products.store');
    Route::patch('/admin/products/{product}', [AdminProductController::class, 'update'])->name('admin.products.update');
    Route::delete('/admin/products/{product}', [AdminProductController::class, 'destroy'])->name('admin.products.destroy');

    Route::get('/admin/rules', [RuleController::class, 'index'])->name('admin.rules.index');
    Route::post('/admin/distribution-rules', [DistributionRuleController::class, 'store'])->name('admin.distribution-rules.store');
    Route::patch('/admin/distribution-rules/{distributionRule}', [DistributionRuleController::class, 'update'])->name('admin.distribution-rules.update');
    Route::post('/admin/scoring-rules', [ScoringRuleController::class, 'store'])->name('admin.scoring-rules.store');
    Route::patch('/admin/scoring-rules/{scoringRule}', [ScoringRuleController::class, 'update'])->name('admin.scoring-rules.update');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
