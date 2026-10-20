<?php

declare(strict_types=1);

namespace App\Models\Common;

use CodeIgniter\Model;

class ReferentielModel extends Model
{
    protected $table            = 'departement';
    protected $primaryKey       = 'id_departement';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = [];

    private const SIMPLES = [
        'departements' => [
            'table'   => 'departement',
            'cle'     => 'id_departement',
            'libelle' => 'nom',
        ],
        'rangs' => [
            'table'   => 'rang',
            'cle'     => 'id',
            'libelle' => 'designation',
        ],
        'types_contrat' => [
            'table'   => 'type_contrat',
            'cle'     => 'id',
            'libelle' => 'designation',
        ],
        'types_entretien' => [
            'table'   => 'type_entrentien',
            'cle'     => 'id',
            'libelle' => 'designation',
        ],
        'types_document' => [
            'table'   => 'type_document',
            'cle'     => 'id',
            'libelle' => 'designation',
        ],
        'actions' => [
            'table'   => 'action',
            'cle'     => 'id',
            'libelle' => 'action',
        ],
        'natures_revendication' => [
            'table'   => 'nature_revendication',
            'cle'     => 'id',
            'libelle' => 'designation',
        ],
    ];

    private const STATUTS = [
        'demande'          => ['table' => 'status', 'libelle' => 'designation'],
        'candidature'      => ['table' => 'status_candidature', 'libelle' => 'designation'],
        'offre'            => ['table' => 'status_offre_emploi', 'libelle' => 'designation'],
        'entretien'        => ['table' => 'status_entretien', 'libelle' => 'designation'],
        'proposition'      => ['table' => 'status_proposition_embauche', 'libelle' => 'designation'],
        'revendication'    => ['table' => 'status_revendication', 'libelle' => 'designation'],
        'salarie'          => ['table' => 'status_salarie', 'libelle' => 'designation'],
        'contrat'          => ['table' => 'status_contrat', 'libelle' => 'designation'],
        'onboarding'       => ['table' => 'status_onboarding', 'libelle' => 'designation'],
        'onboarding_tache' => ['table' => 'status_onboarding_tache', 'libelle' => 'designation'],
        'action'           => ['table' => 'action', 'libelle' => 'action'],
        'nature_revendication' => ['table' => 'nature_revendication', 'libelle' => 'designation'],
    ];

    private const ALIASES = [
        'departement'          => 'departements',
        'service'              => 'services',
        'poste'                => 'postes',
        'grille'               => 'grilles',
        'rang'                 => 'rangs',
        'type_contrat'         => 'types_contrat',
        'type_entretien'       => 'types_entretien',
        'type_document'        => 'types_document',
        'action'               => 'actions',
        'nature_revendication' => 'natures_revendication',
    ];

    public function lister(string $ressource, array $filtres = []): array
    {
        $ressource = self::ALIASES[$ressource] ?? $ressource;

        return match ($ressource) {
            'services' => $this->listerServices($filtres),
            'postes'   => $this->postesAvecHierarchie(
                isset($filtres['idService']) ? (int) $filtres['idService'] : null
            ),
            'grilles'  => $this->listerGrilles((int) ($filtres['idPoste'] ?? 0)),
            default    => $this->listerSimple($ressource),
        };
    }

    public function statuts(string $domaine): array
    {
        $configuration = self::STATUTS[$domaine] ?? null;

        if ($configuration === null) {
            throw new \InvalidArgumentException("Domaine de statut inconnu : {$domaine}");
        }

        $lignes = $this->db->table($configuration['table'])
            ->select('id, ' . $configuration['libelle'] . ' AS libelle')
            ->orderBy($configuration['libelle'], 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $ligne): array => [
            'id'      => (int) $ligne['id'],
            'libelle' => $ligne['libelle'],
        ], $lignes);
    }

    public function grilleActive(int $idPoste): ?array
    {
        $ligne = $this->db->table('grille_salariale g')
            ->select('g.id_grille, g.id_poste, g.rang, g.salaire_min, g.salaire_max, g.date_debut, g.date_fin')
            ->where('g.id_poste', $idPoste)
            ->where('g.date_debut <=', date('Y-m-d'))
            ->groupStart()
                ->where('g.date_fin', null)
                ->orWhere('g.date_fin >=', date('Y-m-d'))
            ->groupEnd()
            ->orderBy('g.date_debut', 'DESC')
            ->get()
            ->getRowArray();

        if ($ligne === null) {
            return null;
        }

        return [
            'id'         => (int) $ligne['id_grille'],
            'idPoste'    => (int) $ligne['id_poste'],
            'idRang'     => (int) $ligne['rang'],
            'salaireMin' => (float) $ligne['salaire_min'],
            'salaireMax' => (float) $ligne['salaire_max'],
            'dateDebut'  => $ligne['date_debut'],
            'dateFin'    => $ligne['date_fin'],
        ];
    }

    public function postesAvecHierarchie(?int $idService = null): array
    {
        $builder = $this->db->table('poste p')
            ->select('p.id_poste, p.intitule, p.description, p.id_service, s.nom AS service, s.id_departement, d.nom AS departement, p.rang_id, r.designation AS rang')
            ->join('service s', 's.id_service = p.id_service', 'left')
            ->join('departement d', 'd.id_departement = s.id_departement', 'left')
            ->join('rang r', 'r.id = p.rang_id', 'left')
            ->orderBy('p.intitule', 'ASC');

        if ($idService !== null && $idService > 0) {
            $builder->where('p.id_service', $idService);
        }

        $lignes = $builder->get()->getResultArray();

        return array_map(static fn (array $ligne): array => [
            'id'            => (int) $ligne['id_poste'],
            'libelle'       => $ligne['intitule'],
            'description'   => $ligne['description'],
            'idService'     => $ligne['id_service'] !== null ? (int) $ligne['id_service'] : null,
            'service'       => $ligne['service'],
            'idDepartement' => $ligne['id_departement'] !== null ? (int) $ligne['id_departement'] : null,
            'departement'   => $ligne['departement'],
            'idRang'        => $ligne['rang_id'] !== null ? (int) $ligne['rang_id'] : null,
            'rang'          => $ligne['rang'],
        ], $lignes);
    }

    private function listerSimple(string $ressource): array
    {
        $configuration = self::SIMPLES[$ressource] ?? null;

        if ($configuration === null) {
            throw new \InvalidArgumentException("Référentiel inconnu : {$ressource}");
        }

        $lignes = $this->db->table($configuration['table'])
            ->select($configuration['cle'] . ' AS id, ' . $configuration['libelle'] . ' AS libelle')
            ->orderBy($configuration['libelle'], 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $ligne): array => [
            'id'      => (int) $ligne['id'],
            'libelle' => $ligne['libelle'],
        ], $lignes);
    }

    private function listerServices(array $filtres): array
    {
        $builder = $this->db->table('service s')
            ->select('s.id_service, s.nom, s.description, s.id_departement, d.nom AS departement')
            ->join('departement d', 'd.id_departement = s.id_departement', 'left')
            ->orderBy('s.nom', 'ASC');

        $idDepartement = isset($filtres['idDepartement']) ? (int) $filtres['idDepartement'] : 0;

        if ($idDepartement > 0) {
            $builder->where('s.id_departement', $idDepartement);
        }

        $lignes = $builder->get()->getResultArray();

        return array_map(static fn (array $ligne): array => [
            'id'            => (int) $ligne['id_service'],
            'libelle'       => $ligne['nom'],
            'description'   => $ligne['description'],
            'idDepartement' => $ligne['id_departement'] !== null ? (int) $ligne['id_departement'] : null,
            'departement'   => $ligne['departement'],
        ], $lignes);
    }

    private function listerGrilles(int $idPoste): array
    {
        if ($idPoste <= 0) {
            throw new \InvalidArgumentException('idPoste obligatoire pour lister les grilles');
        }

        $lignes = $this->db->table('grille_salariale g')
            ->select('g.id_grille, g.id_poste, g.rang, g.salaire_min, g.salaire_max, g.date_debut, g.date_fin')
            ->where('g.id_poste', $idPoste)
            ->orderBy('g.date_debut', 'DESC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $ligne): array => [
            'id'         => (int) $ligne['id_grille'],
            'idPoste'    => (int) $ligne['id_poste'],
            'idRang'     => (int) $ligne['rang'],
            'salaireMin' => (float) $ligne['salaire_min'],
            'salaireMax' => (float) $ligne['salaire_max'],
            'dateDebut'  => $ligne['date_debut'],
            'dateFin'    => $ligne['date_fin'],
        ], $lignes);
    }
}
