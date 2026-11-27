<?php

namespace App\Ai\Middleware;

use Closure;
use Laravel\Ai\PendingStep;
use Laravel\Ai\ToolChoice;

/**
 * On the last step a run is allowed, the model has to answer in words.
 * Without this a run that is still calling tools ends with nothing to say.
 */
class AnswerOnFinalStep
{
    /**
     * Handle the pending generation step.
     */
    public function handle(PendingStep $step, Closure $next): mixed
    {
        if ($step->isFinalStep && ! $step->isFirstStep()) {
            $step = $step->withToolChoice(ToolChoice::none);
        }

        return $next($step);
    }
}
