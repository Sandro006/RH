<?php

declare(strict_types=1);

namespace Tests\Integration\Common;

use App\Libraries\Common\SessionUtilisateur;
use App\Services\Common\DashboardService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\AuthTestTrait;

/**
 * @internal
 */
final class DashboardServiceTest extends CIUnitTestCase
{
    use AuthTestTrait;
    use DatabaseTestTrait;

    protected $migrate         = false;
    protected $refresh         = false;
    protected $useTransactions = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chargerDonneesAuth();
        $this->db->table('demande_recrutement')
            ->where('motif', 'TEST-DASHBOARD')
            ->delete();
    }

    public function testIndicateursRetourneTousLesCompteurs(): void
    {
        $indicateurs = $this->service()->indicateurs($this->sessionUtilisateur('admin'));

        $this->assertSame([
            'demandesEnAttente',
            'offresPubliees',
            'candidatures',
            'entretiensAVenir',
            'propositionsEnCours',
            'tachesOnboarding',
<<<<<<< HEAD
=======
            'documentsExpirant',
            'documentsExpires',
>>>>>>> 57f1b34f20901561d84f17b8b34950d5e25ea61e
        ], array_keys($indicateurs));

        foreach ($indicateurs as $valeur) {
            $this->assertIsInt($valeur);
            $this->assertGreaterThanOrEqual(0, $valeur);
        }
    }

    public function testActionsManagerCompteLesDemandesACorriger(): void
    {
        $session = $this->sessionUtilisateur('manager');
        $this->creerDemande('En cours', $session);

        $actions = $this->service()->actionsEnAttente($session);

        $this->assertCount(1, $actions);
        $this->assertSame('demandesAModifier', $actions[0]['code']);
        $this->assertSame(1, $actions[0]['nombre']);
    }

    public function testActionsAdminCompteLesComptesInactifs(): void
    {
        $this->db->table('utilisateur')
            ->where('identifiant', 'rh')
            ->update(['actif' => false]);

        $actions = $this->service()->actionsEnAttente($this->sessionUtilisateur('admin'));

        $this->assertCount(1, $actions);
        $this->assertSame('comptesInactifs', $actions[0]['code']);
        $this->assertSame(1, $actions[0]['nombre']);
    }

    public function testRoleInconnuRetourneAucuneAction(): void
    {
        $session = new SessionUtilisateur(
            999999,
            'inconnu',
            'inconnu',
            null,
            '127.0.0.1',
            date('c'),
            time(),
        );

        $this->assertSame([], $this->service()->actionsEnAttente($session));
    }

    private function creerDemande(string $statut, SessionUtilisateur $manager): void
    {
        $this->db->query(
            "INSERT INTO type_contrat (designation) VALUES ('AUTH - CDI') ON CONFLICT (designation) DO NOTHING"
        );
        $this->db->query(
            "INSERT INTO status (designation) VALUES ('En cours') ON CONFLICT (designation) DO NOTHING"
        );

        $poste = $this->db->table('poste')
            ->select('id_poste')
            ->where('intitule', 'AUTH - Administrateur')
            ->get()
            ->getRow();

        $typeContrat = $this->db->table('type_contrat')
            ->select('id')
            ->where('designation', 'AUTH - CDI')
            ->get()
            ->getRow();

        $statutLigne = $this->db->table('status')
            ->select('id')
            ->where('designation', $statut)
            ->get()
            ->getRow();

        $this->db->table('demande_recrutement')->insert([
            'id_poste'     => $poste->id_poste,
            'id_demandeur' => $manager->idEmploye,
            'motif'        => 'TEST-DASHBOARD',
            'type_contrat' => $typeContrat->id,
            'statut'       => $statutLigne->id,
        ]);
    }

    private function service(): DashboardService
    {
        return new DashboardService($this->db);
    }
}
