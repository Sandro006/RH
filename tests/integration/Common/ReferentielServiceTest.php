<?php

declare(strict_types=1);

namespace Tests\Integration\Common;

use App\Models\Common\ReferentielModel;
use App\Services\Common\ReferentielService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\AuthTestTrait;

/**
 * @internal
 */
final class ReferentielServiceTest extends CIUnitTestCase
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
        $this->db->table('grille_salariale')
            ->where('date_debut', '2000-01-01')
            ->delete();
    }

    public function testListerDepartements(): void
    {
        $departements = $this->service()->lister('departements');

        $this->assertNotEmpty($departements);
        $this->assertContains('AUTH - Direction', array_column($departements, 'libelle'));
    }

    public function testListerServicesFiltresParDepartement(): void
    {
        $departement = $this->db->table('departement')
            ->where('nom', 'AUTH - Direction')
            ->get()
            ->getRow();

        $services = $this->service()->lister('services', [
            'idDepartement' => (int) $departement->id_departement,
        ]);

        $this->assertCount(1, $services);
        $this->assertSame('AUTH - Service', $services[0]['libelle']);
        $this->assertSame('AUTH - Direction', $services[0]['departement']);
    }

    public function testListerPostesAvecHierarchie(): void
    {
        $postes = $this->service()->lister('postes');

        $poste = null;

        foreach ($postes as $ligne) {
            if ($ligne['libelle'] === 'AUTH - Administrateur') {
                $poste = $ligne;
                break;
            }
        }

        $this->assertNotNull($poste);
        $this->assertSame('AUTH - Service', $poste['service']);
        $this->assertSame('AUTH - Direction', $poste['departement']);
        $this->assertSame('AUTH - Manager', $poste['rang']);
    }

    public function testStatutsSalarie(): void
    {
        $statuts = $this->service()->statuts('salarie');

        $this->assertContains('Actif', array_column($statuts, 'libelle'));
    }

    public function testStatutDeDomaineInconnuRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->statuts('inconnu');
    }

    public function testGrilleActive(): void
    {
        $poste = $this->db->table('poste')
            ->where('intitule', 'AUTH - Administrateur')
            ->get()
            ->getRow();

        $rang = $this->db->table('rang')
            ->where('designation', 'AUTH - Manager')
            ->get()
            ->getRow();

        $this->db->table('grille_salariale')->insert([
            'id_poste'    => $poste->id_poste,
            'rang'        => $rang->id,
            'salaire_min' => 1000,
            'salaire_max' => 2000,
            'date_debut'  => '2000-01-01',
        ]);

        $grille = $this->service()->grilleActive((int) $poste->id_poste);

        $this->assertNotNull($grille);
        $this->assertSame((int) $poste->id_poste, $grille['idPoste']);
        $this->assertSame((int) $rang->id, $grille['idRang']);
        $this->assertSame(1000.0, $grille['salaireMin']);
        $this->assertSame(2000.0, $grille['salaireMax']);
    }

    private function service(): ReferentielService
    {
        return new ReferentielService(new ReferentielModel());
    }
}
