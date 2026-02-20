<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Entity;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $entities = Entity::has('projects.documents')
                ->with(['projects' => function ($q) {
                    $q->has('documents')->orderBy('project_number')->withCount('documents');
                }])
                ->orderBy('name')
                ->get();
            $projectIds = $entities->pluck('projects')->flatten()->pluck('id')->unique()->filter();
            $foldersByProject = Document::whereIn('project_id', $projectIds)
                ->select('project_id', 'document_type')
                ->distinct()
                ->whereNotNull('document_type')
                ->where('document_type', '!=', '')
                ->get()
                ->groupBy('project_id');
            $view->with(compact('entities', 'foldersByProject'));
        });
    }
}
