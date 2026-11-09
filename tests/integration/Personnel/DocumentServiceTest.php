<?php

declare(strict_types=1);

namespace Tests\Integration\Personnel;

use App\Entities\Personnel\Document;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\DonneesIncoherentesException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Personnel\DocumentPolicy;
use App\Libraries\Personnel\StockageService;
use App\Models\Admin\EmployeModel;
use App\Models\Common\ActionModel;
use App\Models\Common\JournalAuditModel;
use App\Models\Personnel\DocumentModel;
use App\Models\Personnel\SalarieModel;
use App\Models\Personnel\TypeDocumentModel;
use App\Services\Personnel\DocumentService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Tests de l'upload, de la confidentialité et de l'audit des documents (Bloc 5).
 *
 * La base de test CI4 est SQLite en mémoire : le schéma minimal est créé ici.
 * La conformité du schéma PostgreSQL est vérifiée par script/db/verif_bloc5.sql.
 *
 * @internal
 */
final class DocumentServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private DocumentService $service;
    private DocumentModel $documentModel;
    private DocumentPolicy $policy;
    private StockageService $stockage;

    private string $repertoireStockage;
    private string $repertoireSources;

    private int $idSalarie;
    private int $idAutreSalarie;

    private SessionUtilisateur $userRh;
    private SessionUtilisateur $userSalarie;
    private SessionUtilisateur $userManager;
    private SessionUtilisateur $userRecruteur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creerSchemaMinimal();

        $this->repertoireStockage = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'test-documents-' . uniqid('', true);
        $this->repertoireSources  = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'test-sources-' . uniqid('', true);

        $this->documentModel = new DocumentModel();
        $this->stockage      = new StockageService($this->repertoireStockage);
        $this->policy        = new DocumentPolicy(new EmployeModel());

        $this->service = new DocumentService(
            $this->documentModel,
            new TypeDocumentModel(),
            new SalarieModel(),
            new AuditService(new JournalAuditModel(), new ActionModel()),
            $this->stockage,
            $this->policy,
        );

        $this->userRh        = new SessionUtilisateur(10, 'rh', 'rh', null, '127.0.0.1', date('c'), time());
        $this->userSalarie   = new SessionUtilisateur(11, 'salarie', 'salarie', 1001, '127.0.0.1', date('c'), time());
        $this->userManager   = new SessionUtilisateur(12, 'manager', 'manager', null, '127.0.0.1', date('c'), time());
        $this->userRecruteur = new SessionUtilisateur(13, 'recruteur', 'recruteur', null, '127.0.0.1', date('c'), time());
    }

    protected function tearDown(): void
    {
        $this->supprimerRecursivement($this->repertoireStockage);
        $this->supprimerRecursivement($this->repertoireSources);

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Jeu de données
    // ------------------------------------------------------------------

    private function creerSchemaMinimal(): void
    {
        $typeDocument = $this->db->prefixTable('type_document');
        $salarie      = $this->db->prefixTable('salarie');
        $document     = $this->db->prefixTable('document');
        $employe      = $this->db->prefixTable('employe');
        $action       = $this->db->prefixTable('action');
        $journal      = $this->db->prefixTable('journal_audit');

        $this->db->query("CREATE TABLE IF NOT EXISTS {$typeDocument} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            designation TEXT NOT NULL,
            obligatoire INTEGER NOT NULL DEFAULT 0,
            duree_validite_mois INTEGER,
            niveau_confidentialite TEXT NOT NULL DEFAULT 'interne',
            actif INTEGER NOT NULL DEFAULT 1
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$salarie} (
            id_salarie INTEGER PRIMARY KEY AUTOINCREMENT,
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

        $this->db->query("CREATE TABLE IF NOT EXISTS {$document} (
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

        $this->db->query("CREATE TABLE IF NOT EXISTS {$employe} (
            id INTEGER PRIMARY KEY,
            nom TEXT,
            date_de_naissance TEXT,
            poste_id INTEGER,
            id_salarie INTEGER
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$action} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT NOT NULL UNIQUE
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$journal} (
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

        foreach (['document', 'salarie', 'employe', 'type_document', 'journal_audit', 'action'] as $table) {
            $this->db->table($this->db->prefixTable($table))->emptyTable();
        }

        $this->db->table($this->db->prefixTable('type_document'))->insertBatch([
            ['id' => 1, 'designation' => 'CV', 'obligatoire' => 0, 'duree_validite_mois' => null, 'niveau_confidentialite' => 'interne', 'actif' => 1],
            ['id' => 3, 'designation' => "Pièce d'identité", 'obligatoire' => 1, 'duree_validite_mois' => 120, 'niveau_confidentialite' => 'rh', 'actif' => 1],
            ['id' => 7, 'designation' => 'RIB', 'obligatoire' => 1, 'duree_validite_mois' => null, 'niveau_confidentialite' => 'restreint', 'actif' => 1],
        ]);

        $this->db->table($this->db->prefixTable('salarie'))->insert([
            'matricule'     => 'MAT-TEST-DOC-00001',
            'nom'           => 'Dossier',
            'prenom'        => 'Un',
            'email'         => 'un@example.com',
            'telephone'     => '0000000000',
            'date_embauche' => date('Y-m-d'),
        ]);
        $this->idSalarie = (int) $this->db->insertID();

        $this->db->table($this->db->prefixTable('salarie'))->insert([
            'matricule'     => 'MAT-TEST-DOC-00002',
            'nom'           => 'Dossier',
            'prenom'        => 'Deux',
            'email'         => 'deux@example.com',
            'telephone'     => '0000000001',
            'date_embauche' => date('Y-m-d'),
        ]);
        $this->idAutreSalarie = (int) $this->db->insertID();

        $this->db->table($this->db->prefixTable('employe'))->insert([
            'id'              => 1001,
            'nom'             => 'Dossier Un',
            'date_de_naissance' => '1990-01-01',
            'poste_id'        => 1,
            'id_salarie'      => $this->idSalarie,
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers fichiers
    // ------------------------------------------------------------------

    private function fichier(string $nom, string $contenu): UploadedFile
    {
        if (!is_dir($this->repertoireSources)) {
            mkdir($this->repertoireSources, 0777, true);
        }

        $chemin = $this->repertoireSources . DIRECTORY_SEPARATOR . uniqid('src_', true);
        file_put_contents($chemin, $contenu);

        return new UploadedFile($chemin, $nom, null, strlen($contenu), UPLOAD_ERR_OK);
    }

    private function png(string $nom = 'piece.png'): UploadedFile
    {
        $contenu = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        );

        return $this->fichier($nom, $contenu);
    }

    /**
     * @return list<string>
     */
    private function fichiersStockes(): array
    {
        if (!is_dir($this->repertoireStockage)) {
            return [];
        }

        return array_values(array_filter(
            glob($this->repertoireStockage . DIRECTORY_SEPARATOR . '*') ?: [],
            static fn (string $chemin): bool => is_file($chemin)
        ));
    }

    private function compterAudit(string $action, string $entite): int
    {
        $actionId = (new ActionModel())->idParDesignation($action);

        if ($actionId === null) {
            return 0;
        }

        return $this->db->table($this->db->prefixTable('journal_audit'))
            ->where('action', $actionId)
            ->where('entite', $entite)
            ->countAllResults();
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
    // Upload
    // ------------------------------------------------------------------

    public function testAjouterStockeLeFichierEtRenseigneLesMetadonnees(): void
    {
        $id = $this->service->ajouter(
            $this->idSalarie,
            3,
            $this->png('piece-identite.png'),
            ['date_emission' => '2025-01-15'],
            $this->userRh
        );

        $document = $this->documentModel->find($id);

        $this->assertNotNull($document);
        $this->assertSame($this->idSalarie, $document->id_salarie);
        $this->assertSame(3, $document->type_document);
        $this->assertSame('rh', $document->niveau_confidentialite);
        $this->assertSame($this->userRh->idUtilisateur, $document->id_utilisateur_depot);
        $this->assertSame('image/png', $document->mime_type);
        $this->assertGreaterThan(0, $document->taille_octets);
        $this->assertSame(64, strlen((string) $document->checksum_sha256));
        $this->assertSame('2025-01-15', $document->date_emission->format('Y-m-d'));
        $this->assertSame('piece-identite.png', $document->nom_fichier);
        $this->assertFalse($document->archive);

        $this->assertMatchesRegularExpression('#^documents/[0-9a-f-]{36}\.png$#', (string) $document->chemin_fichier);
        $this->assertFileExists(
            $this->repertoireStockage . DIRECTORY_SEPARATOR . basename((string) $document->chemin_fichier)
        );

        $this->assertSame(1, $this->compterAudit('CREATE', 'document'));
    }

    public function testNomAfficheSupprimeLesCaracteresDeControle(): void
    {
        $id = $this->service->ajouter(
            $this->idSalarie,
            1,
            $this->png(),
            ['nom_fichier' => "cv\r\nX-Injected: 1"],
            $this->userRh
        );

        $document = $this->documentModel->find($id);

        $this->assertSame('cvX-Injected: 1', $document->nom_fichier);
    }

    public function testUnFichierNonAutoriseEstRefuse(): void
    {
        try {
            $this->service->ajouter(
                $this->idSalarie,
                3,
                $this->fichier('script.php', '<?php echo "pirate";'),
                [],
                $this->userRh
            );
            $this->fail('Un fichier PHP aurait dû être refusé');
        } catch (DonneesIncoherentesException $e) {
            $this->assertStringContainsString('non autorisé', $e->getMessage());
        }

        $this->assertSame(0, $this->documentModel->where('id_salarie', $this->idSalarie)->countAllResults());
        $this->assertSame([], $this->fichiersStockes());
    }

    public function testUneDateExpirationAnterieureAEmissonEstRefusee(): void
    {
        try {
            $this->service->ajouter(
                $this->idSalarie,
                3,
                $this->png(),
                ['date_emission' => '2025-05-01', 'date_expiration' => '2025-04-01'],
                $this->userRh
            );
            $this->fail('Des dates incohérentes auraient dû être refusées');
        } catch (DonneesIncoherentesException $e) {
            $this->assertStringContainsString('expiration', $e->getMessage());
        }

        $this->assertSame([], $this->fichiersStockes());
    }

    public function testUnRoleNonAutoriseNePeutPasAjouterUnDocument(): void
    {
        $this->expectException(AccesRefuseException::class);

        $this->service->ajouter($this->idSalarie, 3, $this->png(), [], $this->userSalarie);
    }

    public function testUnTypeDeDocumentDesactiveEstRefuse(): void
    {
        $this->db->table($this->db->prefixTable('type_document'))
            ->where('id', 1)
            ->update(['actif' => 0]);

        $this->expectException(DonneesIncoherentesException::class);

        $this->service->ajouter($this->idSalarie, 1, $this->png(), [], $this->userRh);
    }

    // ------------------------------------------------------------------
    // Confidentialité
    // ------------------------------------------------------------------

    public function testSalarieNeVoitQueSesPropresPieces(): void
    {
        $idPiece = $this->service->ajouter($this->idSalarie, 3, $this->png(), [], $this->userRh);
        $idAutre = $this->service->ajouter(
            $this->idAutreSalarie,
            1,
            $this->png(),
            ['niveau_confidentialite' => 'public'],
            $this->userRh
        );

        $piece = $this->documentModel->find($idPiece);
        $autre = $this->documentModel->find($idAutre);

        $this->assertTrue($this->policy->peutLire($this->userSalarie, $piece));
        $this->assertFalse($this->policy->peutLire($this->userSalarie, $autre));

        $this->expectException(AccesRefuseException::class);
        $this->service->consulter($idAutre, $this->userSalarie);
    }

    public function testSalariePeutLireSaProprePieceRestreinte(): void
    {
        $id = $this->service->ajouter($this->idSalarie, 7, $this->png('rib.png'), [], $this->userRh);
        $rib = $this->documentModel->find($id);

        $this->assertSame('restreint', $rib->niveau_confidentialite);
        $this->assertTrue($this->policy->peutLire($this->userSalarie, $rib));
    }

    public function testRecruteurNeVoitPasLesPiecesRestreintesMaisVoitLesPiecesInternes(): void
    {
        $idRib = $this->service->ajouter($this->idSalarie, 7, $this->png('rib.png'), [], $this->userRh);
        $idCv  = $this->service->ajouter($this->idSalarie, 1, $this->png('cv.png'), [], $this->userRh);

        $rib = $this->documentModel->find($idRib);
        $cv  = $this->documentModel->find($idCv);

        $this->assertFalse($this->policy->peutLire($this->userRecruteur, $rib));
        $this->assertTrue($this->policy->peutLire($this->userRecruteur, $cv));
    }

    public function testManagerNeVoitPasLesPiecesRhNiRestreintes(): void
    {
        $idPiece = $this->service->ajouter($this->idSalarie, 3, $this->png(), [], $this->userRh);
        $piece   = $this->documentModel->find($idPiece);

        $this->assertFalse($this->policy->peutLire($this->userManager, $piece));
    }

    // ------------------------------------------------------------------
    // Audit
    // ------------------------------------------------------------------

    public function testConsulterJournaliseLesLecturesSensibles(): void
    {
        $idPiece = $this->service->ajouter($this->idSalarie, 3, $this->png(), [], $this->userRh);
        $idCv    = $this->service->ajouter($this->idSalarie, 1, $this->png('cv.png'), [], $this->userRh);

        $this->service->consulter($idPiece, $this->userRh);
        $this->service->consulter($idCv, $this->userRh);

        $this->assertSame(1, $this->compterAudit('READ', 'document'));
    }

    // ------------------------------------------------------------------
    // Remplacement et archivage
    // ------------------------------------------------------------------

    public function testRemplacerArchiveLAncienDocumentEtConserveLesFichiers(): void
    {
        $ancienId = $this->service->ajouter($this->idSalarie, 3, $this->png('ancien.png'), [], $this->userRh);
        $ancien   = $this->documentModel->find($ancienId);
        $ancienChemin = $this->repertoireStockage . DIRECTORY_SEPARATOR . basename((string) $ancien->chemin_fichier);

        $nouvelId = $this->service->remplacer($ancienId, $this->png('nouveau.png'), [], $this->userRh);

        $ancienRecharge = $this->documentModel->find($ancienId);
        $nouveau        = $this->documentModel->find($nouvelId);

        $this->assertTrue($ancienRecharge->archive);
        $this->assertSame($nouvelId, $ancienRecharge->remplace_par);
        $this->assertFalse($nouveau->archive);
        $this->assertSame('nouveau.png', $nouveau->nom_fichier);
        $this->assertSame($this->idSalarie, $nouveau->id_salarie);
        $this->assertSame(3, $nouveau->type_document);

        $this->assertFileExists($ancienChemin);
        $this->assertFileExists(
            $this->repertoireStockage . DIRECTORY_SEPARATOR . basename((string) $nouveau->chemin_fichier)
        );

        $this->assertSame(1, $this->compterAudit('UPDATE', 'document'));
        $this->assertSame(2, $this->compterAudit('CREATE', 'document'));
    }

    public function testArchiverConserveLeFichierEtMarqueLeDocument(): void
    {
        $id      = $this->service->ajouter($this->idSalarie, 3, $this->png(), [], $this->userRh);
        $document = $this->documentModel->find($id);
        $chemin   = $this->repertoireStockage . DIRECTORY_SEPARATOR . basename((string) $document->chemin_fichier);

        $this->service->archiver($id, $this->userRh);

        $document = $this->documentModel->find($id);

        $this->assertTrue($document->archive);
        $this->assertSame('archive', $document->statutValidite());
        $this->assertFileExists($chemin);
        $this->assertSame(1, $this->compterAudit('ARCHIVE', 'document'));
    }

    public function testSupprimerEstUneSuppressionLogique(): void
    {
        $id = $this->service->ajouter($this->idSalarie, 3, $this->png(), [], $this->userRh);

        $this->service->supprimer($id, $this->userRh);

        $this->assertTrue($this->documentModel->find($id)->archive);
        $this->assertCount(1, $this->fichiersStockes());
    }

    // ------------------------------------------------------------------
    // Téléchargement
    // ------------------------------------------------------------------

    public function testTelechargerRetourneLeFichierEtJournaliseLaLecture(): void
    {
        $id     = $this->service->ajouter($this->idSalarie, 3, $this->png('piece.png'), [], $this->userRh);
        $infos  = $this->service->telecharger($id, $this->userRh);

        $this->assertFileExists($infos['chemin_absolu']);
        $this->assertSame('image/png', $infos['mime_type']);
        $this->assertSame('piece.png', $infos['nom_fichier']);
        $this->assertSame(1, $this->compterAudit('READ', 'document'));
    }

    public function testTelechargerRefuseUnRoleSansDroit(): void
    {
        $id = $this->service->ajouter($this->idSalarie, 3, $this->png(), [], $this->userRh);

        $this->expectException(AccesRefuseException::class);

        $this->service->telecharger($id, $this->userManager);
    }

    // ------------------------------------------------------------------
    // Calcul de validité
    // ------------------------------------------------------------------

    public function testStatutValiditeCalcule(): void
    {
        $expire = new Document([
            'archive'         => 0,
            'date_expiration' => date('Y-m-d', strtotime('-1 day')),
        ]);
        $bientot = new Document([
            'archive'         => 0,
            'date_expiration' => date('Y-m-d', strtotime('+10 days')),
        ]);
        $valide = new Document([
            'archive'         => 0,
            'date_expiration' => date('Y-m-d', strtotime('+60 days')),
        ]);
        $sansExpiration = new Document(['archive' => 0, 'date_expiration' => null]);
        $archive = new Document([
            'archive'         => 1,
            'date_expiration' => date('Y-m-d', strtotime('+60 days')),
        ]);

        $this->assertSame('expire', $expire->statutValidite());
        $this->assertSame('expire_bientot', $bientot->statutValidite());
        $this->assertSame('valide', $valide->statutValidite());
        $this->assertSame('sans_expiration', $sansExpiration->statutValidite());
        $this->assertSame('archive', $archive->statutValidite());
    }

    // ------------------------------------------------------------------
    // Complétude et échéances
    // ------------------------------------------------------------------

    public function testPiecesManquantesEtTauxDeCompletude(): void
    {
        $manquantes = $this->service->piecesManquantes($this->idSalarie);

        $this->assertSame(
            ["Pièce d'identité", 'RIB'],
            array_map(static fn (object $type): string => $type->designation, $manquantes)
        );

        $synthese = $this->service->tauxCompletude($this->idSalarie);
        $this->assertSame(2, $synthese['attendus']);
        $this->assertSame(0, $synthese['presents']);
        $this->assertSame(0, $synthese['pourcentage']);

        $this->service->ajouter($this->idSalarie, 3, $this->png(), [], $this->userRh);

        $synthese = $this->service->tauxCompletude($this->idSalarie);
        $this->assertSame(1, $synthese['presents']);
        $this->assertSame(50, $synthese['pourcentage']);

        $this->service->ajouter($this->idSalarie, 7, $this->png('rib.png'), [], $this->userRh);

        $this->assertSame(100, $this->service->tauxCompletude($this->idSalarie)['pourcentage']);
        $this->assertSame([], $this->service->piecesManquantes($this->idSalarie));
    }

    public function testExpirantRetourneLesPiecesProchesEtRefuseLesAutresRoles(): void
    {
        $this->service->ajouter(
            $this->idSalarie,
            3,
            $this->png(),
            ['date_expiration' => date('Y-m-d', strtotime('+10 days'))],
            $this->userRh
        );

        $expirant = $this->service->expirant(30, $this->userRh);
        $this->assertCount(1, $expirant);

        $this->expectException(AccesRefuseException::class);
        $this->service->expirant(30, $this->userManager);
    }

    // ------------------------------------------------------------------
    // Sécurité du stockage
    // ------------------------------------------------------------------

    public function testCheminAbsoluRefuseLesTentativesDeTraversee(): void
    {
        $this->assertNull($this->stockage->cheminAbsolu('../secret.pdf'));
        $this->assertNull($this->stockage->cheminAbsolu('documents/../secret.pdf'));
        $this->assertNull($this->stockage->cheminAbsolu('/etc/passwd'));
        $this->assertNull($this->stockage->cheminAbsolu('documents/sous/dossier.pdf'));
    }
}
