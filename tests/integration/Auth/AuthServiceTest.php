<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use App\Exceptions\DonneesIncoherentesException;
use App\Libraries\Auth\PasswordHasher;
use App\Libraries\Auth\ResultatAuthentification;
use App\Models\Admin\UtilisateurModel;
use App\Services\Auth\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\AuthTestTrait;

/**
 * @internal
 */
final class AuthServiceTest extends CIUnitTestCase
{
    use AuthTestTrait;
    use DatabaseTestTrait;

    protected $migrate          = false;
    protected $refresh          = false;
    protected $useTransactions  = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chargerDonneesAuth();
    }

    public function testChampsVidesSontRefusesSansRequete(): void
    {
        $resultat = $this->serviceAuth()->authentifier([
            'identifiant' => '',
            'motDePasse'  => '',
        ], '127.0.0.1');

        $this->assertFalse($resultat->succes);
        $this->assertSame(ResultatAuthentification::CHAMPS_VIDES, $resultat->codeErreur);
        $this->assertSame(0, $this->compterAudit('REFUS'));
    }

    public function testConnexionReussieMetAJourLaConnexionEtJournalise(): void
    {
        $resultat = $this->serviceAuth()->authentifier([
            'identifiant' => 'admin',
            'motDePasse'  => 'Admin1234.RH',
        ], '127.0.0.1');

        $this->assertTrue($resultat->succes);
        $this->assertNotNull($resultat->utilisateur);

        $admin = $this->db->table('utilisateur')
            ->where('identifiant', 'admin')
            ->get()
            ->getRow();

        $this->assertNotNull($admin);
        $this->assertNotNull($admin->derniere_connexion);
        $this->assertSame(0, (int) $admin->tentatives_echec);
        $this->assertSame(1, $this->compterAudit('LOGIN', (int) $admin->id_utilisateur));
    }

    public function testMotDePasseIncorrectIncrementeLesEchecsEtJournalise(): void
    {
        $resultat = $this->serviceAuth()->authentifier([
            'identifiant' => 'admin',
            'motDePasse'  => 'Mauvais1234.RH',
        ], '127.0.0.1');

        $this->assertFalse($resultat->succes);
        $this->assertSame(ResultatAuthentification::IDENTIFIANTS_INVALIDES, $resultat->codeErreur);

        $admin = $this->db->table('utilisateur')
            ->where('identifiant', 'admin')
            ->get()
            ->getRow();

        $this->assertSame(1, (int) $admin->tentatives_echec);
        $this->assertSame(1, $this->compterAudit('REFUS', (int) $admin->id_utilisateur));
    }

    public function testIdentifiantInconnuJournaliseUnRefusSansUtilisateur(): void
    {
        $resultat = $this->serviceAuth()->authentifier([
            'identifiant' => 'inconnu',
            'motDePasse'  => 'Mauvais1234.RH',
        ], '127.0.0.1');

        $this->assertFalse($resultat->succes);
        $this->assertSame(ResultatAuthentification::IDENTIFIANTS_INVALIDES, $resultat->codeErreur);
        $this->assertSame(1, $this->compterAuditSansUtilisateur('REFUS'));
    }

    public function testCompteDesactiveEstRefuse(): void
    {
        $this->db->table('utilisateur')
            ->where('identifiant', 'rh')
            ->update(['actif' => false]);

        $resultat = $this->serviceAuth()->authentifier([
            'identifiant' => 'rh',
            'motDePasse'  => 'Rh1234.Gestion',
        ], '127.0.0.1');

        $this->assertFalse($resultat->succes);
        $this->assertSame(ResultatAuthentification::COMPTE_DESACTIVE, $resultat->codeErreur);
    }

    public function testVerrouillageApresCinqEchecs(): void
    {
        for ($i = 0; $i < AuthService::MAX_TENTATIVES; $i++) {
            $resultat = $this->serviceAuth()->authentifier([
                'identifiant' => 'admin',
                'motDePasse'  => 'Mauvais1234.RH',
            ], '127.0.0.1');

            $this->assertSame(ResultatAuthentification::IDENTIFIANTS_INVALIDES, $resultat->codeErreur);
        }

        $resultat = $this->serviceAuth()->authentifier([
            'identifiant' => 'admin',
            'motDePasse'  => 'Admin1234.RH',
        ], '127.0.0.1');

        $this->assertSame(ResultatAuthentification::COMPTE_VERROUILLE, $resultat->codeErreur);

        $admin = $this->db->table('utilisateur')
            ->where('identifiant', 'admin')
            ->get()
            ->getRow();

        $this->assertSame(AuthService::MAX_TENTATIVES, (int) $admin->tentatives_echec);
        $this->assertNotNull($admin->verrouille_jusqu_a);
    }

    public function testChangementDeMotDePasse(): void
    {
        $service = $this->serviceAuth();
        $idAdmin = $this->idUtilisateur('admin');

        $service->changerMotDePasse($idAdmin, 'Admin1234.RH', 'Nouveau1234.RH');

        $admin = $this->db->table('utilisateur')
            ->where('id_utilisateur', $idAdmin)
            ->get()
            ->getRow();

        $this->assertFalse((bool) $admin->doit_changer_mot_de_passe);
        $this->assertTrue((new PasswordHasher())->verifier('Nouveau1234.RH', (string) $admin->mot_de_passe_hash));

        $ancien = $service->authentifier([
            'identifiant' => 'admin',
            'motDePasse'  => 'Admin1234.RH',
        ], '127.0.0.1');
        $this->assertSame(ResultatAuthentification::IDENTIFIANTS_INVALIDES, $ancien->codeErreur);

        $nouveau = $service->authentifier([
            'identifiant' => 'admin',
            'motDePasse'  => 'Nouveau1234.RH',
        ], '127.0.0.1');
        $this->assertTrue($nouveau->succes);
    }

    public function testChangementRefuseUnAncienMotDePasseIncorrect(): void
    {
        $this->expectException(DonneesIncoherentesException::class);

        $this->serviceAuth()->changerMotDePasse(
            $this->idUtilisateur('admin'),
            'Mauvais1234.RH',
            'Nouveau1234.RH'
        );
    }

    public function testChangementRefuseUnNouveauMotDePasseFaible(): void
    {
        $this->expectException(DonneesIncoherentesException::class);

        $this->serviceAuth()->changerMotDePasse(
            $this->idUtilisateur('admin'),
            'Admin1234.RH',
            'court'
        );
    }

    public function testDeconnexionJournaliseLOGOUT(): void
    {
        $session = $this->sessionUtilisateur('admin');

        $this->serviceAuth()->deconnecter($session, '127.0.0.1');

        $this->assertSame(1, $this->compterAudit('LOGOUT', $session->idUtilisateur));
    }

    private function serviceAuth(): AuthService
    {
        return new AuthService(
            new UtilisateurModel(),
            new PasswordHasher(),
            $this->sessionManagerFactice(),
            $this->auditService(),
        );
    }

    private function compterAudit(string $action, ?int $idUtilisateur = null): int
    {
        $builder = $this->db->table('journal_audit j')
            ->join('action a', 'a.id = j.action')
            ->where('a.action', $action);

        if ($idUtilisateur !== null) {
            $builder->where('j.id_utilisateur', $idUtilisateur);
        }

        return $builder->countAllResults();
    }

    private function compterAuditSansUtilisateur(string $action): int
    {
        return $this->db->table('journal_audit j')
            ->join('action a', 'a.id = j.action')
            ->where('a.action', $action)
            ->where('j.id_utilisateur', null)
            ->countAllResults();
    }
}
