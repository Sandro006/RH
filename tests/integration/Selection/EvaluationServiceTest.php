<?php

declare(strict_types=1);

namespace Tests\Integration\Selection;

use App\Exceptions\StatutInvalideException;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\SelectionTestTrait;

/** @internal */
final class EvaluationServiceTest extends CIUnitTestCase
{
    use SelectionTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->demarrerContexteSelection();
    }

    protected function tearDown(): void
    {
        $this->arreterContexteSelection();
        parent::tearDown();
    }

    public function testEvaluerCalculeLaNoteDepuisLesCriteres(): void
    {
        $id = $this->evaluationService()->evaluer([
            'idCandidature' => 1003,
            'criteres'      => ['technique' => 4, 'communication' => 2],
            'commentaire'   => 'Test',
        ], $this->selectionUser);

        $evaluation = $this->selectionDb->table('evaluation_candidat')->where('id_evaluation', $id)->get()->getRow();

        $this->assertNotNull($evaluation);
        $this->assertEquals(3.0, (float) $evaluation->note);
        $this->assertSame(1, (int) $evaluation->id_evaluateur);
        $this->assertSame(
            ['technique' => 4, 'communication' => 2],
            json_decode((string) $evaluation->criteres, true)
        );
    }

    public function testNoteMoyenneAvecPlusieursEvaluations(): void
    {
        $service = $this->evaluationService();

        $service->evaluer([
            'idCandidature' => 1003,
            'criteres'      => ['technique' => 5],
        ], $this->selectionUser);

        $service->evaluer([
            'idCandidature' => 1003,
            'criteres'      => ['technique' => 3],
        ], $this->selectionUser);

        $this->assertSame(4.0, $service->noteMoyenne(1003));
    }

    public function testEvaluerRefuseUneCandidatureNonEnEvaluation(): void
    {
        $this->expectException(StatutInvalideException::class);

        $this->evaluationService()->evaluer([
            'idCandidature' => 1002,
            'criteres'      => ['technique' => 4],
        ], $this->selectionUser);
    }

    public function testSyntheseRegroupeLesEvaluations(): void
    {
        $service = $this->evaluationService();

        $service->evaluer([
            'idCandidature' => 1003,
            'criteres'      => ['technique' => 4],
        ], $this->selectionUser);

        $synthese = $service->synthese(1003);

        $this->assertSame(1003, $synthese['idCandidature']);
        $this->assertSame(1, $synthese['nombreEvaluations']);
        $this->assertSame(4.0, $synthese['noteMoyenne']);
    }
}
