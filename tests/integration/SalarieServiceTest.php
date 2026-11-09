<?php

declare(strict_types=1);

namespace Tests\Integration\Personnel;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\Personnel\SalarieService;
use App\Libraries\Common\SessionUtilisateur;
use App\Models\Personnel\SalarieModel;
use App\Models\Personnel\StatusSalarieModel;

/** @internal */
final class SalarieServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = false;
    protected $refresh   = true;
    protected $namespace = null;

    private SalarieService $service;
    private SessionUtilisateur $user;
    private SalarieModel $salarieModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->query(file_get_contents(ROOTPATH . 'script/db/03_seeds_demo.sql'));

        $this->service      = service('salarieService');
        $this->salarieModel = model(SalarieModel::class);
        $this->user         = new SessionUtilisateur(1001, 'rh_test', 'rh', date('c'), '127.0.0.1');
    }

    private function creerSalarieBrut(string $matricule = 'MAT-2026-99999'): int
    {
        $statusModel = model(StatusSalarieModel::class);
        $statutAttente = $statusModel->idParDesignation("En attente d'embauche");

        return (int) $this->salarieModel->insert([
            'id_candidat'   => null,
            'matricule'     => $matricule,
            'nom'           => 'Brut',
            'prenom'        => 'Salarié',
            'email'         => 'brut@test.com',
            'telephone'     => '0600000000',
            'date_embauche' => date('Y-m-d'),
            'statut'        => $statutAttente,
        ], true);
    }

    public function testConsulterUnSalarieExistant(): void
    {
        $id = $this->creerSalarieBrut('MAT-2026-10001');
        $s  = $this->service->consulter($id);

        $this->assertNotNull($s);
        $this->assertSame('Brut', $s->nom);
        $this->assertSame('MAT-2026-10001', $s->matricule);
    }

    public function testConsulterSalarieInexistantRetourneNull(): void
    {
        $this->assertNull($this->service->consulter(999999));
    }

    public function testModifierSalarie(): void
    {
        $id = $this->creerSalarieBrut('MAT-2026-10002');
        $this->service->modifier($id, [
            'nom'       => 'NouveauNom',
            'telephone' => '0700000000',
        ], $this->user);

        $s = $this->service->consulter($id);
        $this->assertSame('NouveauNom', $s->nom);
        $this->assertSame('0700000000', $s->telephone);
    }

    public function testModifierIgnorerChampsNonAutorises(): void
    {
        $id = $this->creerSalarieBrut('MAT-2026-10003');
        $this->service->modifier($id, [
            'matricule' => 'HACKED',
            'statut'    => 99,
        ], $this->user);

        $s = $this->service->consulter($id);
        $this->assertSame('MAT-2026-10003', $s->matricule);
        $this->assertNotSame(99, $s->statut);
    }

    public function testModifierSalarieInexistantLeveException(): void
    {
        $this->expectException(\App\Exceptions\ObjetIntrouvableException::class);
        $this->service->modifier(999999, ['nom' => 'X'], $this->user);
    }

    public function testAffecter(): void
    {
        $id = $this->creerSalarieBrut('MAT-2026-10004');

        $idAff = $this->service->affecter(
            $id, 5, date('Y-m-d'), 'Nomination', $this->user
        );

        $this->assertGreaterThan(0, $idAff);

        $affectations = $this->service->listerAffectations($id);
        $this->assertCount(1, $affectations);
        $this->assertSame(5, $affectations[0]->id_poste);
        $this->assertNull($affectations[0]->date_fin);
    }

    public function testAffecterDeuxFoisClotLaPremiere(): void
    {
        $id = $this->creerSalarieBrut('MAT-2026-10005');

        $this->service->affecter($id, 5, '2026-01-01', 'Première', $this->user);
        $this->service->affecter($id, 6, '2026-06-01', 'Deuxième', $this->user);

        $affectations = $this->service->listerAffectations($id);
        $this->assertCount(2, $affectations);

        $premiere = null;
        $deuxieme = null;
        foreach ($affectations as $a) {
            if ((int) $a->id_poste === 5) $premiere = $a;
            if ((int) $a->id_poste === 6) $deuxieme = $a;
        }
        $this->assertNotNull($premiere);
        $this->assertNotNull($deuxieme);
        $this->assertSame('2026-06-01', $premiere->date_fin);
        $this->assertNull($deuxieme->date_fin);
    }

    public function testAffecterSalarieInexistantLeveException(): void
    {
        $this->expectException(\App\Exceptions\ObjetIntrouvableException::class);
        $this->service->affecter(999999, 5, date('Y-m-d'), null, $this->user);
    }

    public function testChangerStatut(): void
    {
        $id = $this->creerSalarieBrut('MAT-2026-10006');
        $this->service->changerStatut($id, 'Actif', $this->user, 'Fin de période d\'essai');

        $s = $this->service->consulter($id);
        $this->assertSame('Actif', $this->designationStatut((int) $s->statut));
    }

    public function testChangerStatutIdempotent(): void
    {
        $id = $this->creerSalarieBrut('MAT-2026-10007');
        $this->service->changerStatut($id, 'Actif', $this->user, 'Premier');

        $histo1 = $this->service->historique($id);
        $nb1 = count($histo1);

        $this->service->changerStatut($id, 'Actif', $this->user, 'Deuxième (même statut)');
        $histo2 = $this->service->historique($id);

        $this->assertSame($nb1, count($histo2));
    }

    public function testChangerStatutInexistantLeveException(): void
    {
        $this->expectException(\App\Exceptions\ObjetIntrouvableException::class);
        $this->service->changerStatut(999999, 'Actif', $this->user, null);
    }

    public function testHistorique(): void
    {
        $id = $this->creerSalarieBrut('MAT-2026-10008');
        $this->service->changerStatut($id, 'En période d\'essai', $this->user, 'Début essai');
        $this->service->changerStatut($id, 'Actif', $this->user, 'Fin essai');

        $histo = $this->service->historique($id);
        $this->assertCount(2, $histo);

        $this->assertSame('Début essai', $histo[0]->commentaire);
        $this->assertSame('Fin essai',   $histo[1]->commentaire);
    }

    public function testListerParStatut(): void
    {
        $this->creerSalarieBrut('MAT-2026-10009');
        $id2 = $this->creerSalarieBrut('MAT-2026-10010');
        $this->service->changerStatut($id2, 'Actif', $this->user, 'Activation');

        $actifs = $this->service->lister(['statut' => 2]); // 2 = Actif
        $this->assertGreaterThanOrEqual(1, count($actifs));
    }

    private function designationStatut(int $idStatut): string
    {
        $row = $this->db->table('status_salarie')->where('id', $idStatut)->get()->getRow();
        return $row ? $row->designation : '';
    }
}