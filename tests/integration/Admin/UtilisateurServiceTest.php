<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Exceptions\AccesRefuseException;
use App\Exceptions\ConflitException;
use App\Exceptions\DonneesIncoherentesException;
use App\Libraries\Auth\PasswordHasher;
use App\Models\Admin\EmployeModel;
use App\Models\Admin\UtilisateurModel;
use App\Services\Admin\UtilisateurService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\AuthTestTrait;

/**
 * @internal
 */
final class UtilisateurServiceTest extends CIUnitTestCase
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
    }

    public function testCreationUtilisateur(): void
    {
        $id = $this->service()->creer([
            'identifiant' => 'test_agent',
            'motDePasse'  => 'Agent1234.RH',
            'role'        => 'salarie',
            'actif'       => true,
        ], $this->sessionUtilisateur('admin'));

        $this->assertGreaterThan(0, $id);

        $utilisateur = $this->db->table('utilisateur')
            ->where('id_utilisateur', $id)
            ->get()
            ->getRow();

        $this->assertNotNull($utilisateur);
        $this->assertSame('test_agent', $utilisateur->identifiant);
        $this->assertSame('salarie', $utilisateur->role);
        $this->assertTrue((bool) $utilisateur->actif);
        $this->assertTrue((new PasswordHasher())->verifier('Agent1234.RH', (string) $utilisateur->mot_de_passe_hash));
        $this->assertSame(1, $this->compterAudit('CREATE', $id));
    }

    public function testCreationRefuseUnIdentifiantDejaUtilise(): void
    {
        $this->expectException(ConflitException::class);

        $this->service()->creer([
            'identifiant' => 'admin',
            'motDePasse'  => 'Agent1234.RH',
            'role'        => 'salarie',
        ], $this->sessionUtilisateur('admin'));
    }

    public function testCreationRefuseUnRoleInvalide(): void
    {
        $this->expectException(DonneesIncoherentesException::class);

        $this->service()->creer([
            'identifiant' => 'test_role',
            'motDePasse'  => 'Agent1234.RH',
            'role'        => 'superadmin',
        ], $this->sessionUtilisateur('admin'));
    }

    public function testCreationRefuseUnMotDePasseFaible(): void
    {
        $this->expectException(DonneesIncoherentesException::class);

        $this->service()->creer([
            'identifiant' => 'test_faible',
            'motDePasse'  => 'court',
            'role'        => 'salarie',
        ], $this->sessionUtilisateur('admin'));
    }

    public function testSeulUnAdminPeutCreerUnUtilisateur(): void
    {
        $this->expectException(AccesRefuseException::class);

        $this->service()->creer([
            'identifiant' => 'test_refuse',
            'motDePasse'  => 'Agent1234.RH',
            'role'        => 'salarie',
        ], $this->sessionUtilisateur('rh'));
    }

    public function testDesactivationDeSonPropreCompteRefusee(): void
    {
        $session = $this->sessionUtilisateur('admin');

        $this->expectException(ConflitException::class);

        $this->service()->desactiver($session->idUtilisateur, $session);
    }

    public function testRetrogradationDuDernierAdminRefusee(): void
    {
        $session = $this->sessionUtilisateur('admin');

        $this->expectException(ConflitException::class);

        $this->service()->modifier($session->idUtilisateur, ['role' => 'rh'], $session);
    }

    public function testReinitialisationMotDePasse(): void
    {
        $session = $this->sessionUtilisateur('admin');

        $this->db->table('utilisateur')
            ->where('id_utilisateur', $session->idUtilisateur)
            ->update([
                'tentatives_echec'  => 3,
                'verrouille_jusqu_a' => date('Y-m-d H:i:s', time() + 600),
            ]);

        $temporaire = $this->service()->reinitialiserMotDePasse($session->idUtilisateur, $session);

        $utilisateur = $this->db->table('utilisateur')
            ->where('id_utilisateur', $session->idUtilisateur)
            ->get()
            ->getRow();

        $this->assertTrue((new PasswordHasher())->verifier($temporaire, (string) $utilisateur->mot_de_passe_hash));
        $this->assertTrue((bool) $utilisateur->doit_changer_mot_de_passe);
        $this->assertSame(0, (int) $utilisateur->tentatives_echec);
        $this->assertNull($utilisateur->verrouille_jusqu_a);
        $this->assertSame(1, $this->compterAudit('UPDATE', $session->idUtilisateur));
    }

    public function testModificationIdentifiantEtRole(): void
    {
        $session = $this->sessionUtilisateur('admin');
        $idRh    = $this->idUtilisateur('rh');

        $this->service()->modifier($idRh, [
            'identifiant' => 'test_rh_renomme',
            'role'        => 'manager',
        ], $session);

        $utilisateur = $this->db->table('utilisateur')
            ->where('id_utilisateur', $idRh)
            ->get()
            ->getRow();

        $this->assertSame('test_rh_renomme', $utilisateur->identifiant);
        $this->assertSame('manager', $utilisateur->role);
    }

    public function testListerAvecFiltreRole(): void
    {
        $resultat = $this->service()->lister(
            ['role' => 'rh', 'page' => 1, 'parPage' => 10],
            $this->sessionUtilisateur('admin')
        );

        $this->assertSame(1, $resultat['total']);
        $this->assertCount(1, $resultat['donnees']);
        $this->assertSame('rh', $resultat['donnees'][0]['role']);
    }

    private function service(): UtilisateurService
    {
        return new UtilisateurService(
            new UtilisateurModel(),
            new EmployeModel(),
            new PasswordHasher(),
            $this->auditService(),
        );
    }

    private function compterAudit(string $action, ?int $idEntite = null): int
    {
        $builder = $this->db->table('journal_audit j')
            ->join('action a', 'a.id = j.action')
            ->where('a.action', $action);

        if ($idEntite !== null) {
            $builder->where('j.id_entite', (string) $idEntite);
        }

        return $builder->countAllResults();
    }
}
