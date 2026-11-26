<?php

namespace App\Providers;

use Anthropic\Client;
use App\Extraction\AnthropicBatchClient;
use App\Extraction\Contracts\BatchClient;
use App\Models\ExtractionRun;
use App\Models\Message;
use App\Models\Person;
use App\Models\Project;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BatchClient::class, fn (): BatchClient => new AnthropicBatchClient(
            new Client(apiKey: config('ai.providers.anthropic.key')),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // What a Fact can be about, and what a Cost can be triggered by.
        Relation::enforceMorphMap([
            'person' => Person::class,
            'project' => Project::class,
            'topic' => Topic::class,
            'message' => Message::class,
            'extraction_run' => ExtractionRun::class,
        ]);
    }
}
