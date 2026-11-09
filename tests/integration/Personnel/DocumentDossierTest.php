<?php

declare(strict_types=1);

namespace Tests\Integration\Personnel;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Models\Personnel\DocumentModel;
use App\Models\Personnel\SalarieModel;
use App\Models\Personnel\TypeDocumentModel;
use App\Entities\Personnel\Document;

/**
 * Tests des modèles du dossier documentaire (Bloc 5).
 *
 * La base de test CI4 est SQLite en mémoire : le schéma minimal est créé ici
 * pour rester autonome. La conformité du schéma PostgreSQL est vérifiée par
 * script/db/verif_bloc5.sql.
 *
 * @internal
 */
final class DocumentDossierTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private DocumentModel $documentModel;
    private TypeDocumentModel $typeDocumentModel;
    private SalarieModel $salarieModel;
    private int $idSalarie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creerSchemaMinimal();

        $this->documentModel     = model(DocumentModel::class);
        $this->typeDocumentModel = model(TypeDocumentModel::class);
        $this->salarieModel      = model(SalarieModel::class);

        $this->db->table('salarie')->insert([
            'matricule'     => 'MAT-TEST-DOC-00001',
            'nom'           => 'Dossier',
            'prenom'        => 'Test',
            'email'         => 'dossier.test@example.com',
            'telephone'     => '0000000000',
            'date_embauche' => date('Y-m-d'),
        ]);
        $this->idSalarie = (int) $this->db->insertID();
    }

    protected function tearDown(): void
    {
        if (isset($this->idSalarie)) {
            $this->db->table('document')->where('id_salarie', $this->idSalarie)->delete();
            $this->db->table('salarie')->where('id_salarie', $this->idSalarie)->delete();
        }

        parent::tearDown();
    }

    private function creerSchemaMinimal(): void
    {
        $salarie       = $this->db->prefixTable('salarie');
        $document      = $this->db->prefixTable('document');
        $typeDocument  = $this->db->prefixTable('type_document');

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

        $this->db->table('type_document')->emptyTable();
        $this->db->table('type_document')->insertBatch([
            ['id' => 1, 'designation' => 'CV', 'obligatoire' => 0, 'duree_validite_mois' => null, 'niveau_confidentialite' => 'interne', 'actif' => 1],
            ['id' => 2, 'designation' => 'Contrat de travail', 'obligatoire' => 1, 'duree_validite_mois' => null, 'niveau_confidentialite' => 'rh', 'actif' => 1],
            ['id' => 3, 'designation' => 'Pièce d\'identité', 'obligatoire' => 1, 'duree_validite_mois' => 120, 'niveau_confidentialite' => 'rh', 'actif' => 1],
            ['id' => 4, 'designation' => 'Diplôme', 'obligatoire' => 0, 'duree_validite_mois' => null, 'niveau_confidentialite' => 'interne', 'actif' => 1],
            ['id' => 7, 'designation' => 'RIB', 'obligatoire' => 1, 'duree_validite_mois' => null, 'niveau_confidentialite' => 'restreint', 'actif' => 1],
        ]);

        $this->db->table('document')->emptyTable();
        $this->db->table('salarie')->emptyTable();
    }

    private function insererDocument(array $surcharges = []): int
    {
        $this->db->table('document')->insert(array_merge([
            'id_salarie'             => $this->idSalarie,
            'type_document'          => 3,
            'nom_fichier'            => 'piece-identite.pdf',
            'chemin_fichier'         => 'tests/documents/piece-identite.pdf',
            'date_emission'          => date('Y-m-d', strtotime('-1 year')),
            'date_expiration'        => null,
            'niveau_confidentialite' => 'rh',
            'archive'                => false,
        ], $surcharges));

        return (int) $this->db->insertID();
    }

    public function testSalarieModelAccepteLesChampsIdentite(): void
    {
        $this->salarieModel->update($this->idSalarie, [
            'date_naissance'      => '1990-05-12',
            'lieu_naissance'      => 'Antananarivo',
            'nationalite'         => 'Malgache',
            'situation_familiale' => 'Célibataire',
            'adresse'             => 'Lot 1',
            'ville'               => 'Antananarivo',
            'pays'                => 'Madagascar',
        ]);

        $salarie = $this->salarieModel->find($this->idSalarie);

        $this->assertNotNull($salarie);
        $this->assertSame('Antananarivo', $salarie->lieu_naissance);
        $this->assertSame('Malgache', $salarie->nationalite);
        $this->assertSame('1990-05-12', $salarie->date_naissance->format('Y-m-d'));
    }

    public function testLesTypesDeDocumentPortentLeursMetadonnees(): void
    {
        $rib = $this->typeDocumentModel->trouverParDesignation('RIB');

        $this->assertNotNull($rib);
        $this->assertTrue((bool) $rib->obligatoire);
        $this->assertSame('restreint', $rib->niveau_confidentialite);

        $identite = $this->typeDocumentModel->trouverParDesignation('Pièce d\'identité');

        $this->assertNotNull($identite);
        $this->assertSame(120, (int) $identite->duree_validite_mois);
    }

    public function testLesTypesObligatoiresSontListes(): void
    {
        $designations = array_map(
            static fn (object $type): string => $type->designation,
            $this->typeDocumentModel->obligatoires()
        );

        $this->assertContains('Contrat de travail', $designations);
        $this->assertContains('Pièce d\'identité', $designations);
        $this->assertContains('RIB', $designations);
        $this->assertNotContains('CV', $designations);
    }

    public function testDocumentsActifsExcluentLesArchives(): void
    {
        $this->insererDocument();
        $this->insererDocument(['nom_fichier' => 'archive.pdf', 'archive' => true]);

        $documents = $this->documentModel->documentsActifs($this->idSalarie);

        $this->assertCount(1, $documents);
        $this->assertSame('piece-identite.pdf', $documents[0]->nom_fichier);
    }

    public function testDocumentsExpirantEtExpiresSontDistingues(): void
    {
        $this->insererDocument([
            'nom_fichier'     => 'bientot.pdf',
            'date_expiration' => date('Y-m-d', strtotime('+10 days')),
        ]);
        $this->insererDocument([
            'nom_fichier'     => 'plus-tard.pdf',
            'date_expiration' => date('Y-m-d', strtotime('+60 days')),
        ]);
        $this->insererDocument([
            'nom_fichier'     => 'expire.pdf',
            'date_expiration' => date('Y-m-d', strtotime('-1 day')),
        ]);
        $this->insererDocument([
            'nom_fichier'     => 'archive-expire.pdf',
            'date_expiration' => date('Y-m-d', strtotime('+5 days')),
            'archive'         => true,
        ]);

        $expirant = $this->documentModel->documentsExpirant(30, $this->idSalarie);
        $expires  = $this->documentModel->documentsExpires($this->idSalarie);

        $this->assertCount(1, $expirant);
        $this->assertSame('bientot.pdf', $expirant[0]->nom_fichier);

        $this->assertCount(1, $expires);
        $this->assertSame('expire.pdf', $expires[0]->nom_fichier);
    }

    public function testListerAvecTypeJointLeReferentiel(): void
    {
        $this->insererDocument();

        $documents = $this->documentModel->listerAvecType($this->idSalarie);

        $this->assertCount(1, $documents);
        $this->assertSame('Pièce d\'identité', $documents[0]->type_designation);
        $this->assertTrue((bool) $documents[0]->type_obligatoire);
    }

    public function testLEntiteDocumentMasqueLeCheminFichier(): void
    {
        $expiration = date('Y-m-d', strtotime('+60 days'));
        $document   = new Document([
            'id_document'     => 1,
            'chemin_fichier'  => 'writable/uploads/documents/secret.pdf',
            'date_expiration' => $expiration,
            'archive'         => 'f',
        ]);

        $tableau = $document->toPublicArray();

        $this->assertArrayNotHasKey('chemin_fichier', $tableau);
        $this->assertSame($expiration, $tableau['date_expiration']);
        $this->assertSame('valide', $tableau['statut_validite']);
        $this->assertFalse($document->archive);
    }

    public function testListerParTypeRetourneSeulementLesDocumentsActifsDuType(): void
    {
        $this->insererDocument();
        $this->insererDocument(['type_document' => 4, 'nom_fichier' => 'diplome.pdf']);

        $documents = $this->documentModel->documentsParType($this->idSalarie, 3);

        $this->assertCount(1, $documents);
        $this->assertSame('piece-identite.pdf', $documents[0]->nom_fichier);
    }
}
