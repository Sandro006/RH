<?php

declare(strict_types=1);

namespace Tests\Integration\Selection;

use App\Exceptions\StatutInvalideException;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\SelectionTestTrait;

/** @internal */
final class EntretienServiceTest extends CIUnitTestCase
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

    public function testPlanifierUnEntretien(): void
    {
        $id = $this->entretienService()->planifier([
            'idCandidature' => 1002,
            'idRecruteur'   => 1,
            'dateEntretien' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'typeEntretien' => 1,
        ], $this->selectionUser);

        $this->assertGreaterThan(0, $id);
        $entretien = $this->selectionDb->table('entretien')->where('id_entretien', $id)->get()->getRow();
        $this->assertNotNull($entretien);
        $this->assertSame('Planifié', $this->designation('status_entretien', (int) $entretien->statut));

        $candidature = $this->selectionDb->table('candidature')->where('id_candidature', 1002)->get()->getRow();
        $this->assertSame('Entretien prévu', $this->designation('status_candidature', (int) $candidature->statut));

        $this->assertCount(1, $this->entretienService()->historique($id));
    }

    public function testPlanifierRefuseUneCandidatureNonPreselectionnee(): void
    {
        $this->expectException(StatutInvalideException::class);

        $this->entretienService()->planifier([
            'idCandidature' => 1004,
            'idRecruteur'   => 1,
            'dateEntretien' => date('Y-m-d H:i:s', strtotime('+3 days')),
        ], $this->selectionUser);
    }

    public function testConfirmerPuisRealiserUnEntretien(): void
    {
        $service = $this->entretienService();
        $id = $service->planifier([
            'idCandidature' => 1002,
            'idRecruteur'   => 1,
            'dateEntretien' => date('Y-m-d H:i:s', strtotime('+3 days')),
        ], $this->selectionUser);

        $service->confirmer($id, $this->selectionUser);
        $service->realiser($id, 'Compte rendu de test', $this->selectionUser);

        $entretien = $this->selectionDb->table('entretien')->where('id_entretien', $id)->get()->getRow();
        $this->assertSame('Réalisé', $this->designation('status_entretien', (int) $entretien->statut));

        $candidature = $this->selectionDb->table('candidature')->where('id_candidature', 1002)->get()->getRow();
        $this->assertSame('En évaluation', $this->designation('status_candidature', (int) $candidature->statut));
    }

    public function testReporterUnEntretien(): void
    {
        $service = $this->entretienService();
        $id = $service->planifier([
            'idCandidature' => 1002,
            'idRecruteur'   => 1,
            'dateEntretien' => date('Y-m-d H:i:s', strtotime('+3 days')),
        ], $this->selectionUser);

        $nouvelleDate = date('Y-m-d H:i:s', strtotime('+10 days'));
        $service->reporter($id, $nouvelleDate, 'Indisponibilité', $this->selectionUser);

        $entretien = $this->selectionDb->table('entretien')->where('id_entretien', $id)->get()->getRow();
        $this->assertSame('Planifié', $this->designation('status_entretien', (int) $entretien->statut));
        $this->assertStringStartsWith(date('Y-m-d', strtotime('+10 days')), (string) $entretien->date_entretien);
    }

    public function testAnnulerUnEntretien(): void
    {
        $service = $this->entretienService();
        $id = $service->planifier([
            'idCandidature' => 1002,
            'idRecruteur'   => 1,
            'dateEntretien' => date('Y-m-d H:i:s', strtotime('+3 days')),
        ], $this->selectionUser);

        $service->annuler($id, 'Annulation de test', $this->selectionUser);

        $entretien = $this->selectionDb->table('entretien')->where('id_entretien', $id)->get()->getRow();
        $this->assertSame('Annulé', $this->designation('status_entretien', (int) $entretien->statut));

        $candidature = $this->selectionDb->table('candidature')->where('id_candidature', 1002)->get()->getRow();
        $this->assertSame('Présélectionnée', $this->designation('status_candidature', (int) $candidature->statut));
    }

    private function designation(string $table, int $id): string
    {
        return (string) $this->selectionDb->table($table)->where('id', $id)->get()->getRow()->designation;
    }
}
