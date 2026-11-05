<?php

namespace Tests\Unit\Jobs;

use App\Enums\UserRoleEnum;
use App\Jobs\GenerateAiRecommendationJob;
use App\Services\AIService;
use App\Services\TicketStatusService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;
use Tests\Concerns\CreatesTickets;

class GenerateAiRecommendationJobTest extends FeatureTestCase
{
    use CreatesTickets;

    protected function setUp(): void
    {
        parent::setUp();

        app(TicketStatusService::class)->flush();
    }

    #[Test]
    public function it_persists_the_ai_recommendation_on_the_ticket(): void
    {
        $technician = $this->createTechnician();
        $ticket = $this->createTicket(['description' => 'Máquina de corte parada']);

        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'technician_id' => $technician->id,
                            'justification' => 'Perfil mais adequado ao tipo de avaria.',
                        ]),
                    ],
                    'finish_reason' => 'stop',
                ]],
            ]),
        ]);

        (new GenerateAiRecommendationJob($ticket))->handle(app(AIService::class));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'recommended_technician_id' => $technician->id,
            'ai_recommendation_reason' => 'Perfil mais adequado ao tipo de avaria.',
        ]);

        $this->assertNotNull($ticket->fresh()->ai_processed_at);
    }

    #[Test]
    public function it_does_not_call_the_ai_when_there_are_no_technicians_and_marks_the_ticket(): void
    {
        $ticket = $this->createTicket(['description' => 'Sem equipa disponível']);

        OpenAI::fake();

        (new GenerateAiRecommendationJob($ticket))->handle(app(AIService::class));

        OpenAI::assertNothingSent();

        $this->assertNull($ticket->fresh()->recommended_technician_id);
        $this->assertStringContainsString(
            'No active operational technicians',
            (string) $ticket->fresh()->ai_recommendation_reason,
        );
        $this->assertNotNull($ticket->fresh()->ai_processed_at);
    }

    #[Test]
    public function it_persists_the_fallback_reason_when_the_ai_is_unavailable(): void
    {
        $this->createTechnician();
        $ticket = $this->createTicket(['description' => 'IA indisponível']);

        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'resposta não JSON'],
                    'finish_reason' => 'stop',
                ]],
            ]),
        ]);

        (new GenerateAiRecommendationJob($ticket))->handle(app(AIService::class));

        $this->assertNull($ticket->fresh()->recommended_technician_id);
        $this->assertStringContainsString(
            'AI assistant unavailable',
            (string) $ticket->fresh()->ai_recommendation_reason,
        );
        $this->assertNotNull($ticket->fresh()->ai_processed_at);
    }

    #[Test]
    public function it_discards_an_ai_recommendation_for_a_technician_it_never_offered(): void
    {
        $this->createTechnician();
        $outside = $this->createInactiveUser(UserRoleEnum::Technician->value);
        $ticket = $this->createTicket(['description' => 'Compressor com ruído anormal']);

        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'technician_id' => $outside->id,
                            'justification' => 'Perfil mais adequado ao tipo de avaria.',
                        ]),
                    ],
                    'finish_reason' => 'stop',
                ]],
            ]),
        ]);

        (new GenerateAiRecommendationJob($ticket))->handle(app(AIService::class));

        // The row exists, so only the set offered to the model proves it was a hallucination.
        $this->assertNull($ticket->fresh()->recommended_technician_id);
        $this->assertStringContainsString(
            'outside the active team',
            (string) $ticket->fresh()->ai_recommendation_reason,
        );
        $this->assertNotNull($ticket->fresh()->ai_processed_at);
    }

    #[Test]
    public function it_is_unique_per_ticket(): void
    {
        $ticket = $this->createTicket(['description' => 'Unicidade']);

        $job = new GenerateAiRecommendationJob($ticket);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertEquals((string) $ticket->id, $job->uniqueId());
        $this->assertGreaterThan(0, $job->uniqueFor());
    }

    #[Test]
    public function failed_records_the_error_on_the_ticket(): void
    {
        $ticket = $this->createTicket(['description' => 'Falha do job']);

        (new GenerateAiRecommendationJob($ticket))->failed(new \RuntimeException('API fora do ar'));

        $this->assertNotNull($ticket->fresh()->ai_processed_at);
        $this->assertStringContainsString(
            'Could not obtain an automatic recommendation',
            (string) $ticket->fresh()->ai_recommendation_reason,
        );
    }
}
