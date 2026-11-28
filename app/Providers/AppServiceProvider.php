<?php

namespace App\Providers;

use Anthropic\Client;
use App\Extraction\AnthropicBatchClient;
use App\Extraction\Contracts\BatchClient;
use App\Files\Pdf\ParserExtractor;
use App\Files\Pdf\PdfTextExtractor;
use App\Files\Pdf\PopplerExtractor;
use App\Models\ExtractionRun;
use App\Models\File;
use App\Models\Message;
use App\Models\Person;
use App\Models\Project;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // PDF text: `pdftotext` when the server has it, the pure-PHP reader when it doesn't.
        $this->app->bind(PdfTextExtractor::class, fn (): PdfTextExtractor => PopplerExtractor::isInstalled()
            ? new PopplerExtractor
            : new ParserExtractor);

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

        // What a Fact can be about, what a Cost can be triggered by, and who owns a token.
        Relation::enforceMorphMap([
            'user' => User::class,
            'person' => Person::class,
            'project' => Project::class,
            'topic' => Topic::class,
            'message' => Message::class,
            'file' => File::class,
            'extraction_run' => ExtractionRun::class,
        ]);

        // The push package reads its own config; Donna's is the one place the keys are set.
        config(['webpush.vapid' => [...config('donna.push.vapid'), 'pem_file' => null]]);

        $this->configureRateLimits();

        // `composer dev` has to work the queue replies are written on, not just the default one.
        DevCommands::artisan('queue:listen --queue=replies,default --tries=1 --timeout=0', 'queue');
    }

    /**
     * Limits that keep the sign-in email from being used to flood Josh's
     * inbox, a code from being guessed, and a stolen session from running up
     * the model bill.
     */
    private function configureRateLimits(): void
    {
        RateLimiter::for('login-code', function (Request $request): array {
            $email = is_string($request->input('email')) ? Str::lower($request->input('email')) : '';

            return [
                Limit::perMinutes(10, 3)->by("login-code:email:{$email}"),
                Limit::perHour(10)->by("login-code:ip:{$request->ip()}"),
            ];
        });

        RateLimiter::for('sign-in', fn (Request $request): Limit => Limit::perMinute(10)->by("sign-in:{$request->ip()}"));

        RateLimiter::for('files', fn (Request $request): Limit => Limit::perMinute(config('donna.files.uploads_per_minute'))->by('files:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('messages', fn (Request $request): Limit => Limit::perMinute(60)->by('messages:'.($request->user()?->id ?? $request->ip())));
    }
}
