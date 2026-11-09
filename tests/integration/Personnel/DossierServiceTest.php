<?php

declare(strict_types=1);

namespace Tests\Integration\Personnel;

use App\Exceptions\AccesRefuseException;
use App\Exceptions\ObjetIntrouvableException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Personnel\DocumentPolicy;
use App\Libraries\Personnel\StockageService;
use App\Models\Admin\EmployeModel;
use App\Models\Common\ActionModel;
use App\Models\Common\JournalAuditModel;
use App\Models\Personnel\AffectationModel;
use App\Models\Personnel\ContratModel;
use App\Models\Personnel\DocumentModel;
use App\Models\Personnel\OnboardingModel;
use App\Models\Personnel\SalarieHistoriqueModel;
use App\Models\Personnel\SalarieModel;
use App\Models\Personnel\TypeDocumentModel;
use App\Services\Personnel\DocumentService;
use App\Services\Personnel\DossierService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Tests du dossier agrégé (Bloc 5 — PR 3).
 *
 * La base de test CI4 est SQLite en mémoire : le schéma minimal est créé ici.
 *
 * @internal
 */
final class DossierServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private DossierService $service;
    private DocumentService $documentService;
    private DocumentPolicy $policy;

    private string $repertoireStockage;

    private int $idSalarie;
    private int $idAutreSalarie;

    private SessionUtilisateur $userRh;
    private SessionUtilisateur $userSalarie;
    private SessionUtilisateur $userSalarieAutre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creerSchemaMinimal();

        $this->repertoireStockage = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'test-dossier-' . uniqid('', true);

        $auditService       = new AuditService(new JournalAuditModel(), new ActionModel());
        $this->policy       = new DocumentPolicy(new EmployeModel());
        $this->documentService = new DocumentService(
            new DocumentModel(),
            new TypeDocumentModel(),
            new SalarieModel(),
            $auditService,
            new StockageService($this->repertoireStockage),
            $this->policy,
        );

        $this->service = new DossierService(
            new SalarieModel(),
            new AffectationModel(),
            new ContratModel(),
            new SalarieHistoriqueModel(),
            new OnboardingModel(),
            $this->documentService,
            $this->policy,
            $auditService,
        );

        $this->userRh           = new SessionUtilisateur(10, 'rh', 'rh', null, '127.0.0.1', date('c'), time());
        $this->userSalarie      = new SessionUtilisateur(11, 'salarie', 'salarie', 1001, '127.0.0.1', date('c'), time());
        $this->userSalarieAutre = new SessionUtilisateur(12, 'salarie', 'salarie', 1002, '127.0.0.1', date('c'), time());
    }

    protected function tearDown(): void
    {
        $this->supprimerRecursivement($this->repertoireStockage);

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Schéma et jeu de données
    // ------------------------------------------------------------------

    private function creerSchemaMinimal(): void
    {
        // La base SQLite en mémoire est partagée entre classes de test : on
        // repart d'un schéma propre pour garantir les colonnes attendues.
        foreach ([
            'journal_audit', 'action', 'employe', 'document', 'type_document',
            'onboarding_tache', 'onboarding', 'status_onboarding_tache', 'status_onboarding',
            'salarie_status_historique', 'affectation', 'contrat', 'salarie',
            'type_contrat', 'status_contrat', 'status_salarie', 'poste', 'service', 'departement',
        ] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $this->db->prefixTable($table));
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('departement')} (
            id_departement INTEGER PRIMARY KEY AUTOINCREMENT,
            nom TEXT NOT NULL
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('service')} (
            id_service INTEGER PRIMARY KEY AUTOINCREMENT,
            id_departement INTEGER,
            nom TEXT NOT NULL
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('poste')} (
            id_poste INTEGER PRIMARY KEY AUTOINCREMENT,
            id_service INTEGER,
            intitule TEXT NOT NULL,
            rang_id INTEGER
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('status_salarie')} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            designation TEXT NOT NULL
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('status_contrat')} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            designation TEXT NOT NULL
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('type_contrat')} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            designation TEXT NOT NULL
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('salarie')} (
            id_salarie INTEGER PRIMARY KEY AUTOINCREMENT,
            id_candidat INTEGER,
            matricule TEXT NOT NULL,
            nom TEXT NOT NULL,
            prenom TEXT NOT NULL,
            email TEXT,
            telephone TEXT,
            date_naissance TEXT,
            lieu_naissance TEXT,
            nationalite TEXT,
            situation_familiale TEXT,
            adresse TEXT,
            ville TEXT,
            pays TEXT,
            date_embauche TEXT,
            date_sortie TEXT,
            statut INTEGER
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('contrat')} (
            id_contrat INTEGER PRIMARY KEY AUTOINCREMENT,
            id_salarie INTEGER NOT NULL,
            id_poste INTEGER NOT NULL,
            type_contrat_id INTEGER NOT NULL,
            date_debut TEXT NOT NULL,
            date_fin TEXT,
            salaire_base REAL,
            periode_essai_debut TEXT,
            periode_essai_fin TEXT,
            date_signature TEXT,
            statut INTEGER
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('affectation')} (
            id_affectation INTEGER PRIMARY KEY AUTOINCREMENT,
            id_salarie INTEGER NOT NULL,
            id_poste INTEGER NOT NULL,
            date_debut TEXT NOT NULL,
            date_fin TEXT,
            motif TEXT
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('salarie_status_historique')} (
            id_historique INTEGER PRIMARY KEY AUTOINCREMENT,
            id_salarie INTEGER NOT NULL,
            statut_ancien INTEGER NOT NULL,
            statut_nouveau INTEGER NOT NULL,
            date_changement TEXT NOT NULL,
            id_utilisateur INTEGER,
            commentaire TEXT
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('status_onboarding')} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            designation TEXT NOT NULL
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('status_onboarding_tache')} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            designation TEXT NOT NULL
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('onboarding')} (
            id_onboarding INTEGER PRIMARY KEY AUTOINCREMENT,
            statut INTEGER,
            id_salarie INTEGER NOT NULL,
            date_debut TEXT NOT NULL,
            date_fin TEXT
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('onboarding_tache')} (
            id_tache INTEGER PRIMARY KEY AUTOINCREMENT,
            statut INTEGER,
            id_onboarding INTEGER NOT NULL,
            intitule TEXT NOT NULL,
            description TEXT,
            responsable TEXT,
            date_echeance TEXT,
            date_realisation TEXT
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('type_document')} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            designation TEXT NOT NULL,
            obligatoire INTEGER NOT NULL DEFAULT 0,
            duree_validite_mois INTEGER,
            niveau_confidentialite TEXT NOT NULL DEFAULT 'interne',
            actif INTEGER NOT NULL DEFAULT 1
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('document')} (
            id_document INTEGER PRIMARY KEY AUTOINCREMENT,
            id_salarie INTEGER NOT NULL,
            type_document INTEGER NOT NULL,
            nom_fichier TEXT,
            chemin_fichier TEXT NOT NULL,
            date_depot TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_emission TEXT,
            date_expiration TEXT,
            niveau_confidentialite TEXT NOT NULL DEFAULT 'interne',
            id_utilisateur_depot INTEGER,
            mime_type TEXT,
            taille_octets INTEGER,
            checksum_sha256 TEXT,
            remplace_par INTEGER,
            archive INTEGER NOT NULL DEFAULT 0
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('employe')} (
            id INTEGER PRIMARY KEY,
            nom TEXT,
            date_de_naissance TEXT,
            poste_id INTEGER,
            id_salarie INTEGER
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('action')} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL UNIQUE
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$this->db->prefixTable('journal_audit')} (
            id_audit INTEGER PRIMARY KEY AUTOINCREMENT,
            id_utilisateur INTEGER,
            action INTEGER NOT NULL,
            entite TEXT NOT NULL,
            id_entite TEXT,
            ancienne_valeur TEXT,
            nouvelle_valeur TEXT,
            details TEXT,
            adresse_ip TEXT,
            date_action TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        foreach ([
            'departement', 'service', 'poste', 'status_salarie', 'status_contrat', 'type_contrat',
            'salarie', 'contrat', 'affectation', 'salarie_status_historique', 'status_onboarding',
            'status_onboarding_tache', 'onboarding', 'onboarding_tache', 'type_document', 'document',
            'employe', 'action', 'journal_audit',
        ] as $table) {
            $this->db->table($this->db->prefixTable($table))->emptyTable();
        }

        $this->db->table($this->db->prefixTable('departement'))->insert(['id_departement' => 1, 'nom' => 'Technique']);
        $this->db->table($this->db->prefixTable('service'))->insert(['id_service' => 1, 'id_departement' => 1, 'nom' => 'Informatique']);
        $this->db->table($this->db->prefixTable('poste'))->insert(['id_poste' => 1, 'id_service' => 1, 'intitule' => 'Développeur', 'rang_id' => 1]);

        $this->db->table($this->db->prefixTable('status_salarie'))->insertBatch([
            ['id' => 1, 'designation' => "En attente d'embauche"],
            ['id' => 2, 'designation' => 'Actif'],
        ]);

        $this->db->table($this->db->prefixTable('status_contrat'))->insertBatch([
            ['id' => 4, 'designation' => 'En cours'],
            ['id' => 5, 'designation' => 'Terminé'],
        ]);

        $this->db->table($this->db->prefixTable('type_contrat'))->insertBatch([
            ['id' => 1, 'designation' => 'CDI'],
            ['id' => 2, 'designation' => 'CDD'],
        ]);

        $this->db->table($this->db->prefixTable('salarie'))->insert([
            'id_candidat'         => 50,
            'matricule'           => 'MAT-TEST-DOSSIER-00001',
            'nom'                 => 'Dossier',
            'prenom'              => 'Complet',
            'email'               => 'complet@example.com',
            'telephone'           => '0000000000',
            'date_naissance'      => '1990-05-12',
            'lieu_naissance'      => 'Antananarivo',
            'nationalite'         => 'Malgache',
            'situation_familiale' => 'Marié',
            'adresse'             => 'Lot 1',
            'ville'               => 'Antananarivo',
            'pays'                => 'Madagascar',
            'date_embauche'       => '2020-01-15',
            'statut'              => 2,
        ]);
        $this->idSalarie = (int) $this->db->insertID();

        $this->db->table($this->db->prefixTable('salarie'))->insert([
            'matricule'     => 'MAT-TEST-DOSSIER-00002',
            'nom'           => 'Autre',
            'prenom'        => 'Salarié',
            'date_embauche' => '2021-02-01',
            'statut'        => 2,
        ]);
        $this->idAutreSalarie = (int) $this->db->insertID();

        $this->db->table($this->db->prefixTable('employe'))->insertBatch([
            ['id' => 1001, 'nom' => 'Dossier Complet', 'date_de_naissance' => '1990-05-12', 'poste_id' => 1, 'id_salarie' => $this->idSalarie],
            ['id' => 1002, 'nom' => 'Autre Salarié', 'date_de_naissance' => '1991-01-01', 'poste_id' => 1, 'id_salarie' => $this->idAutreSalarie],
        ]);

        $this->db->table($this->db->prefixTable('affectation'))->insert([
            'id_salarie' => $this->idSalarie,
            'id_poste'   => 1,
            'date_debut' => '2020-01-15',
            'date_fin'   => null,
            'motif'      => 'Recrutement',
        ]);

        $this->db->table($this->db->prefixTable('contrat'))->insertBatch([
            [
                'id_salarie' => $this->idSalarie, 'id_poste' => 1, 'type_contrat_id' => 2,
                'date_debut' => '2018-01-01', 'date_fin' => '2019-12-31', 'salaire_base' => 2000,
                'date_signature' => '2018-01-01', 'statut' => 5,
            ],
            [
                'id_salarie' => $this->idSalarie, 'id_poste' => 1, 'type_contrat_id' => 1,
                'date_debut' => '2020-01-15', 'date_fin' => null, 'salaire_base' => 3000,
                'date_signature' => '2020-01-15', 'statut' => 4,
            ],
        ]);

        $this->db->table($this->db->prefixTable('salarie_status_historique'))->insert([
            'id_salarie'      => $this->idSalarie,
            'statut_ancien'   => 1,
            'statut_nouveau'  => 2,
            'date_changement' => '2020-01-15 08:00:00',
            'commentaire'     => 'Embauche',
        ]);

        $this->db->table($this->db->prefixTable('status_onboarding'))->insert(['id' => 2, 'designation' => 'En cours']);
        $this->db->table($this->db->prefixTable('status_onboarding_tache'))->insertBatch([
            ['id' => 1, 'designation' => 'À faire'],
            ['id' => 4, 'designation' => 'Terminée'],
        ]);

        $this->db->table($this->db->prefixTable('onboarding'))->insert([
            'statut'      => 2,
            'id_salarie'  => $this->idSalarie,
            'date_debut'  => '2020-01-15',
        ]);
        $idOnboarding = (int) $this->db->insertID();

        $this->db->table($this->db->prefixTable('onboarding_tache'))->insertBatch([
            ['statut' => 4, 'id_onboarding' => $idOnboarding, 'intitule' => 'Signer le contrat'],
            ['statut' => 1, 'id_onboarding' => $idOnboarding, 'intitule' => 'Visite médicale'],
        ]);

        $this->db->table($this->db->prefixTable('type_document'))->insertBatch([
            ['id' => 3, 'designation' => "Pièce d'identité", 'obligatoire' => 1, 'duree_validite_mois' => 120, 'niveau_confidentialite' => 'rh', 'actif' => 1],
            ['id' => 6, 'designation' => 'Certificat médical', 'obligatoire' => 1, 'duree_validite_mois' => 12, 'niveau_confidentialite' => 'rh', 'actif' => 1],
            ['id' => 7, 'designation' => 'RIB', 'obligatoire' => 1, 'duree_validite_mois' => null, 'niveau_confidentialite' => 'restreint', 'actif' => 1],
        ]);

        $this->db->table($this->db->prefixTable('document'))->insertBatch([
            [
                'id_salarie' => $this->idSalarie, 'type_document' => 3, 'nom_fichier' => 'identite.pdf',
                'chemin_fichier' => 'documents/identite.pdf', 'date_depot' => '2020-01-15 08:00:00',
                'date_emission' => '2019-01-01', 'date_expiration' => date('Y-m-d', strtotime('-10 days')),
                'niveau_confidentialite' => 'rh', 'mime_type' => 'application/pdf', 'taille_octets' => 1000,
                'checksum_sha256' => str_repeat('a', 64), 'archive' => 0,
            ],
            [
                'id_salarie' => $this->idSalarie, 'type_document' => 7, 'nom_fichier' => 'rib.pdf',
                'chemin_fichier' => 'documents/rib.pdf', 'date_depot' => '2020-01-15 08:00:00',
                'date_emission' => '2020-01-01', 'date_expiration' => date('Y-m-d', strtotime('+10 days')),
                'niveau_confidentialite' => 'restreint', 'mime_type' => 'application/pdf', 'taille_octets' => 1000,
                'checksum_sha256' => str_repeat('b', 64), 'archive' => 0,
            ],
        ]);
    }

    private function compterAudit(string $action, string $entite, ?int $idEntite = null): int
    {
        $actionId = (new ActionModel())->idParDesignation($action);

        if ($actionId === null) {
            return 0;
        }

        $requete = $this->db->table($this->db->prefixTable('journal_audit'))
            ->where('action', $actionId)
            ->where('entite', $entite);

        if ($idEntite !== null) {
            $requete->where('id_entite', (string) $idEntite);
        }

        return $requete->countAllResults();
    }

    private function supprimerRecursivement(?string $repertoire): void
    {
        if ($repertoire === null || !is_dir($repertoire)) {
            return;
        }

        foreach (scandir($repertoire) ?: [] as $entree) {
            if ($entree === '.' || $entree === '..') {
                continue;
            }

            $chemin = $repertoire . DIRECTORY_SEPARATOR . $entree;

            if (is_dir($chemin)) {
                $this->supprimerRecursivement($chemin);
            } else {
                @unlink($chemin);
            }
        }

        @rmdir($repertoire);
    }

    // ------------------------------------------------------------------
    // Dossier complet
    // ------------------------------------------------------------------

    public function testDossierCompletExposeToutesLesSections(): void
    {
        $dossier = $this->service->dossier($this->idSalarie, $this->userRh);

        $this->assertSame([
            'identite',
            'situationProfessionnelle',
            'contrats',
            'affectations',
            'remuneration',
            'documents',
            'carriere',
            'alertes',
            'onboarding',
        ], array_keys($dossier));

        $this->assertSame('MAT-TEST-DOSSIER-00001', $dossier['identite']['matricule']);
        $this->assertSame('Complet Dossier', $dossier['identite']['nom_complet']);
        $this->assertSame('1990-05-12', $dossier['identite']['date_naissance']);
        $this->assertSame('2020-01-15', $dossier['identite']['date_embauche']);

        $this->assertSame('Actif', $dossier['situationProfessionnelle']['statut_designation']);
        $this->assertSame('Développeur', $dossier['situationProfessionnelle']['poste']);
        $this->assertSame('Informatique', $dossier['situationProfessionnelle']['service']);
        $this->assertSame('Technique', $dossier['situationProfessionnelle']['departement']);
        $this->assertNotNull($dossier['situationProfessionnelle']['anciennete']);
    }

    public function testContratsEtRemuneration(): void
    {
        $dossier = $this->service->dossier($this->idSalarie, $this->userRh);

        $this->assertCount(2, $dossier['contrats']);
        $this->assertSame('CDI', $dossier['contrats'][0]['type_contrat']);
        $this->assertTrue($dossier['contrats'][0]['est_en_cours']);
        $this->assertSame('Terminé', $dossier['contrats'][1]['statut_designation']);

        $this->assertSame(3000.0, $dossier['remuneration']['salaire_courant']);
        $this->assertSame($dossier['contrats'][0]['id_contrat'], $dossier['remuneration']['contrat_courant']);
        $this->assertCount(2, $dossier['remuneration']['historique']);
    }

    public function testAffectationsEtCarriere(): void
    {
        $dossier = $this->service->dossier($this->idSalarie, $this->userRh);

        $this->assertCount(1, $dossier['affectations']);
        $this->assertSame('Développeur', $dossier['affectations'][0]['poste']);
        $this->assertTrue($dossier['affectations'][0]['en_cours']);

        $types = array_column($dossier['carriere'], 'type');
        $this->assertContains('contrat', $types);
        $this->assertContains('affectation', $types);
        $this->assertContains('statut', $types);

        $dates = array_column($dossier['carriere'], 'date');
        $datesTriees = $dates;
        rsort($datesTriees);
        $this->assertSame($datesTriees, $dates);
    }

    public function testDocumentsEtCompletude(): void
    {
        $dossier = $this->service->dossier($this->idSalarie, $this->userRh);

        $this->assertCount(2, $dossier['documents']['pieces']);
        $this->assertSame(3, $dossier['documents']['completude']['attendus']);
        $this->assertSame(2, $dossier['documents']['completude']['presents']);
        $this->assertSame(1, $dossier['documents']['completude']['manquants']);
        $this->assertSame(67, $dossier['documents']['completude']['pourcentage']);
        $this->assertSame('Certificat médical', $dossier['documents']['manquants'][0]['designation']);
    }

    public function testAucunCheminFichierDansLeDossier(): void
    {
        $dossier = $this->service->dossier($this->idSalarie, $this->userRh);
        $json    = json_encode($dossier);

        $this->assertIsString($json);
        $this->assertStringNotContainsString('chemin_fichier', $json);
        $this->assertStringNotContainsString('documents/identite.pdf', $json);
        $this->assertStringNotContainsString('documents/rib.pdf', $json);
    }

    public function testAlertesDocumentaires(): void
    {
        $alertes = $this->service->alertes($this->idSalarie, $this->userRh);

        $this->assertSame(1, $alertes['nombre_expires']);
        $this->assertSame(1, $alertes['nombre_expirant_bientot']);
        $this->assertSame(1, $alertes['nombre_manquants']);
        $this->assertSame(3, $alertes['total']);
        $this->assertSame("Pièce d'identité", $alertes['documents_expires'][0]['type_designation']);
        $this->assertSame('RIB', $alertes['documents_expirant_bientot'][0]['type_designation']);
    }

    public function testCarriereSeule(): void
    {
        $carriere = $this->service->carriere($this->idSalarie, $this->userRh);

        $this->assertNotEmpty($carriere);
        $this->assertSame('statut', $carriere[0]['type']);
        $this->assertStringContainsString('Actif', $carriere[0]['libelle']);
    }

    public function testOnboarding(): void
    {
        $dossier = $this->service->dossier($this->idSalarie, $this->userRh);

        $this->assertNotNull($dossier['onboarding']);
        $this->assertSame('En cours', $dossier['onboarding']['statut_designation']);
        $this->assertSame(2, $dossier['onboarding']['taches_total']);
        $this->assertSame(1, $dossier['onboarding']['taches_terminees']);
        $this->assertSame(50.0, $dossier['onboarding']['progression']);
    }

    public function testOnboardingAbsentRetourneNull(): void
    {
        $dossier = $this->service->dossier($this->idAutreSalarie, $this->userRh);

        $this->assertNull($dossier['onboarding']);
        $this->assertSame([], $dossier['contrats']);
        $this->assertSame([], $dossier['affectations']);
        $this->assertNull($dossier['remuneration']['salaire_courant']);
    }

    // ------------------------------------------------------------------
    // Accès et audit
    // ------------------------------------------------------------------

    public function testSalariePeutConsulterSonPropreDossier(): void
    {
        $dossier = $this->service->dossier($this->idSalarie, $this->userSalarie);

        $this->assertSame('MAT-TEST-DOSSIER-00001', $dossier['identite']['matricule']);
    }

    public function testSalarieNePeutPasConsulterLeDossierDUnAutre(): void
    {
        $this->expectException(AccesRefuseException::class);

        $this->service->dossier($this->idSalarie, $this->userSalarieAutre);
    }

    public function testDossierInexistant(): void
    {
        $this->expectException(ObjetIntrouvableException::class);

        $this->service->dossier(999999, $this->userRh);
    }

    public function testConsultationEstAuditeePourUnAutreUtilisateur(): void
    {
        $this->service->dossier($this->idSalarie, $this->userRh);

        $this->assertSame(1, $this->compterAudit('READ', 'dossier', $this->idSalarie));
    }

    public function testConsultationPropreDossierNonAuditee(): void
    {
        $this->service->dossier($this->idSalarie, $this->userSalarie);

        $this->assertSame(0, $this->compterAudit('READ', 'dossier', $this->idSalarie));
    }
}
