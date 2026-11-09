<?php

declare(strict_types=1);

namespace Tests\Integration\Selection;

use App\Exceptions\DonneesIncoherentesException;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\SelectionTestTrait;

/** @internal */
final class SelectionServiceTest extends CIUnitTestCase
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

    public function testRetenirUneCandidatureAuDessusDuSeuil(): void
    {
        $this->evaluationService()->evaluer([
            'idCandidature' => 1003,
            'criteres'      => ['technique' => 4],
        ], $this->selectionUser);

        $this->selectionService()->retenir(1003, $this->selectionUser);

        $candidature = $this->selectionDb->table('candidature')->where('id_candidature', 1003)->get()->getRow();
        $this->assertSame('Retenue', $this->designation((int) $candidature->statut));
    }

    public function testRetenirRefuseUneNoteSousLeSeuil(): void
    {
        $this->evaluationService()->evaluer([
            'idCandidature' => 1003,
            'criteres'      => ['technique' => 1],
        ], $this->selectionUser);

        $this->expectException(DonneesIncoherentesException::class);

        $this->selectionService()->retenir(1003, $this->selectionUser);
    }

    public function testRefuserUneCandidature(): void
    {
        $this->selectionService()->refuser(1003, 'Profil insuffisant', $this->selectionUser);

        $candidature = $this->selectionDb->table('candidature')->where('id_candidature', 1003)->get()->getRow();
        $this->assertSame('Refusée', $this->designation((int) $candidature->statut));
    }

    public function testClassementEtCandidatsEligibles(): void
    {
        $this->evaluationService()->evaluer([
            'idCandidature' => 1003,
            'criteres'      => ['technique' => 4],
        ], $this->selectionUser);

        $service = $this->selectionService();
        $classement = $service->classerPourOffre(1003);

        $this->assertNotEmpty($classement);
        $this->assertSame(1003, $classement[0]['idCandidature']);
        $this->assertSame(4.0, $classement[0]['noteMoyenne']);

        $eligibles = $service->candidatsEligibles(1003);
        $this->assertCount(1, $eligibles);
    }

    private function designation(int $id): string
    {
        return (string) $this->selectionDb->table('status_candidature')->where('id', $id)->get()->getRow()->designation;
    }
}
