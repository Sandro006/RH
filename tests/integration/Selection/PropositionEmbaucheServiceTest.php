<?php

declare(strict_types=1);

namespace Tests\Integration\Selection;

use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\StatutInvalideException;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\SelectionTestTrait;

/** @internal */
final class PropositionEmbaucheServiceTest extends CIUnitTestCase
{
    use SelectionTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->demarrerContexteSelection();
        $this->assurerGrillePoste();
    }

    protected function tearDown(): void
    {
        $this->arreterContexteSelection();
        parent::tearDown();
    }

    public function testCreerUnePropositionDepuisUneCandidatureRetenue(): void
    {
        $id = $this->propositionService()->creerDepuisCandidature(
            1004,
            45000.0,
            date('Y-m-d', strtotime('+20 days')),
            $this->selectionUser
        );

        $proposition = $this->propositionService()->consulter($id);

        $this->assertNotNull($proposition);
        $this->assertSame('À envoyer', $this->designation((int) $proposition->statut));
        $this->assertEquals(45000.0, (float) $proposition->remuneration_proposee);
        $this->assertCount(1, $this->propositionService()->historique($id));
    }

    public function testCreerRefuseUneCandidatureNonRetenue(): void
    {
        $this->expectException(StatutInvalideException::class);

        $this->propositionService()->creerDepuisCandidature(
            1002,
            45000.0,
            null,
            $this->selectionUser
        );
    }

    public function testCreerRefuseUneRemunerationHorsGrille(): void
    {
        $this->expectException(DonneesIncoherentesException::class);

        $this->propositionService()->creerDepuisCandidature(
            1004,
            10000.0,
            null,
            $this->selectionUser
        );
    }

    public function testEnvoyerPuisEnregistrerUneAcceptation(): void
    {
        $service = $this->propositionService();
        $id = $service->creerDepuisCandidature(1004, 45000.0, null, $this->selectionUser);

        $service->envoyer($id, $this->selectionUser);
        $service->enregistrerReponse($id, ['accepte' => true, 'commentaire' => 'Acceptée'], $this->selectionUser);

        $proposition = $service->consulter($id);

        $this->assertSame('Acceptée', $this->designation((int) $proposition->statut));
        $this->assertNotNull($proposition->date_reponse);
    }

    public function testMarquerUnePropositionExpiree(): void
    {
        $service = $this->propositionService();
        $id = $service->creerDepuisCandidature(1016, 45000.0, null, $this->selectionUser);

        $service->envoyer($id, $this->selectionUser);
        $this->selectionDb->table('proposition_embauche')
            ->where('id_proposition', $id)
            ->update(['date_proposition' => date('Y-m-d', strtotime('-30 days'))]);

        $service->marquerExpiree($id, $this->selectionUser);

        $this->assertSame('Expirée', $this->designation((int) $service->consulter($id)->statut));
    }

    public function testNegociationParRevendicationAcceptee(): void
    {
        $propositions = $this->propositionService();
        $revendications = $this->revendicationService();

        $id = $propositions->creerDepuisCandidature(1004, 45000.0, null, $this->selectionUser);
        $propositions->envoyer($id, $this->selectionUser);

        $idRevendication = $revendications->soumettre([
            'idProposition'  => $id,
            'sens'           => 'CANDIDAT',
            'nature'         => 'Rémunération',
            'valeurDemandee' => '50000',
        ], $this->selectionUser);

        $revendications->repondre($idRevendication, ['decision' => 'Acceptée', 'reponse' => 'Accord'], $this->selectionUser);

        $proposition = $propositions->consulter($id);
        $this->assertSame('Envoyée', $this->designation((int) $proposition->statut));
        $this->assertEquals(50000.0, (float) $proposition->remuneration_proposee);

        $propositions->enregistrerReponse($id, ['accepte' => true], $this->selectionUser);
        $this->assertSame('Acceptée', $this->designation((int) $propositions->consulter($id)->statut));
    }

    private function designation(int $id): string
    {
        return (string) $this->selectionDb->table('status_proposition_embauche')->where('id', $id)->get()->getRow()->designation;
    }
}
