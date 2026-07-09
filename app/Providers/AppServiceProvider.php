<?php

namespace App\Providers;

use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\ProviderDetails;
use App\Models\Task;
use App\Policies\CredentialingCasePolicy;
use App\Policies\DocumentPolicy;
use App\Policies\ProviderPolicy;
use App\Policies\TaskPolicy;
use App\Services\MailSettingsService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Listeners\FollowUpEngineListener;
use App\Listeners\RecordTaskActivity;
use App\Listeners\RecordTimelineActivity;
use App\Listeners\TaskNotificationListener;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::policy(CredentialingCase::class, CredentialingCasePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(ProviderDetails::class, ProviderPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);

        Event::subscribe(RecordTimelineActivity::class);
        Event::subscribe(RecordTaskActivity::class);
        Event::subscribe(FollowUpEngineListener::class);
        Event::subscribe(TaskNotificationListener::class);

        if (Schema::hasTable('settings')) {
            try {
                app(MailSettingsService::class)->applyToConfig();
            } catch (\Throwable) {
                // Settings may be unavailable during install or migration.
            }
        }
    }
}
