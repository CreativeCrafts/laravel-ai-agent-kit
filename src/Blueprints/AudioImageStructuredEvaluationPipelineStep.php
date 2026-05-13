<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use InvalidArgumentException;

final readonly class AudioImageStructuredEvaluationPipelineStep implements PipelineStep
{
    public function __construct(
        private AudioImageStructuredEvaluation $evaluation,
    ) {
    }

    public function handle(RunContext $context): RunContext
    {
        $request = $context->inputValue('audio_image_structured_evaluation_request');

        if (!$request instanceof AudioImageStructuredEvaluationRequest) {
            throw new InvalidArgumentException(sprintf(
                'RunContext input [audio_image_structured_evaluation_request] must be an instance of [%s].',
                AudioImageStructuredEvaluationRequest::class,
            ));
        }

        return $context
            ->withStateValue('audio_image_structured_evaluation_result', $this->evaluation->evaluate($request))
            ->incrementStepCount();
    }
}
