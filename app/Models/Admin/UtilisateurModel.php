<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Entities\Admin\Utilisateur;
use App\Libraries\Common\Roles;
use CodeIgniter\Model;

class UtilisateurModel extends Model
{
    protected $table            = 'utilisateur';
    protected $primaryKey       = 'id_utilisateur';
    protected $useAutoIncrement = true;
    protected $returnType       = Utilisateur::class;
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id_employe',
        'identifiant',
        'mot_de_passe_hash',
        'role',
        'actif',
        'derniere_connexion',
        'tentatives_echec',
        'verrouille_jusqu_a',
        'doit_changer_mot_de_passe',
    ];

    public function trouverParIdentifiant(string $identifiant): ?Utilisateur
    {
        return $this->where('identifiant', $identifiant)->first();
    }

    public function trouverParId(int $id): ?Utilisateur
    {
        return $this->find($id);
    }

    public function existeIdentifiant(string $identifiant, ?int $saufId = null): bool
    {
        $builder = $this->db->table($this->table)->where('identifiant', $identifiant);

        if ($saufId !== null) {
            $builder->where('id_utilisateur !=', $saufId);
        }

        return $builder->countAllResults() > 0;
    }

    public function compterAdminsActifs(): int
    {
        return $this->db->table($this->table)
            ->where('role', Roles::ADMIN)
            ->where('actif', true)
            ->countAllResults();
    }

    public function estDernierAdminActif(int $id): bool
    {
        $utilisateur = $this->find($id);

        if ($utilisateur === null || !$utilisateur->actif || $utilisateur->role !== Roles::ADMIN) {
            return false;
        }

        return $this->compterAdminsActifs() <= 1;
    }

    public function enregistrerEchec(int $id, int $maxTentatives, int $dureeVerrouMinutes): void
    {
        $utilisateur = $this->find($id);

        if ($utilisateur === null) {
            return;
        }

        $tentatives = (int) ($utilisateur->tentatives_echec ?? 0);
        $verrou     = $utilisateur->verrouille_jusqu_a ?? null;

        if ($verrou !== null && strtotime((string) $verrou) <= time()) {
            $tentatives = 0;
        }

        $tentatives++;
        $donnees = ['tentatives_echec' => $tentatives];

        if ($tentatives >= $maxTentatives) {
            $donnees['verrouille_jusqu_a'] = date('Y-m-d H:i:sP', time() + ($dureeVerrouMinutes * 60));
        }

        $this->update($id, $donnees);
    }

    public function reinitialiserEchecs(int $id): void
    {
        $this->update($id, [
            'tentatives_echec'   => 0,
            'verrouille_jusqu_a' => null,
        ]);
    }

    public function mettreAJourDerniereConnexion(int $id): void
    {
        $this->update($id, ['derniere_connexion' => date('Y-m-d H:i:sP')]);
    }

    public function mettreAJourMotDePasse(int $id, string $hash, bool $doitChanger = false): void
    {
        $this->update($id, [
            'mot_de_passe_hash'          => $hash,
            'doit_changer_mot_de_passe'  => $doitChanger,
        ]);
    }

    public function activerDesactiver(int $id, bool $actif): void
    {
        $this->update($id, ['actif' => $actif]);
    }

    public function lister(array $filtres, int $page = 1, int $parPage = 20): array
    {
        $page    = max(1, $page);
        $parPage = min(100, max(1, $parPage));

        $compter = function () use ($filtres): int {
            $builder = $this->db->table($this->table . ' u')
                ->join('employe e', 'e.id = u.id_employe', 'left');

            $this->appliquerFiltres($builder, $filtres);

            return $builder->countAllResults();
        };

        $total = $compter();

        $builder = $this->db->table($this->table . ' u')
            ->select('u.id_utilisateur, u.id_employe, u.identifiant, u.role, u.actif, u.date_creation, u.derniere_connexion, e.nom AS employe_nom')
            ->join('employe e', 'e.id = u.id_employe', 'left')
            ->orderBy('u.identifiant', 'ASC')
            ->limit($parPage, ($page - 1) * $parPage);

        $this->appliquerFiltres($builder, $filtres);

        $lignes = $builder->get()->getResultArray();

        $donnees = array_map(static fn (array $ligne): array => [
            'idUtilisateur'     => (int) $ligne['id_utilisateur'],
            'idEmploye'         => $ligne['id_employe'] !== null ? (int) $ligne['id_employe'] : null,
            'identifiant'       => $ligne['identifiant'],
            'role'              => $ligne['role'],
            'actif'             => (bool) $ligne['actif'],
            'dateCreation'      => $ligne['date_creation'],
            'derniereConnexion' => $ligne['derniere_connexion'],
            'employeNom'        => $ligne['employe_nom'],
        ], $lignes);

        return [
            'donnees' => $donnees,
            'total'   => $total,
            'page'    => $page,
            'parPage' => $parPage,
        ];
    }

    private function appliquerFiltres($builder, array $filtres): void
    {
        $role = trim((string) ($filtres['role'] ?? ''));

        if ($role !== '') {
            $builder->where('u.role', $role);
        }

        $actif = $filtres['actif'] ?? null;

        if ($actif !== null && $actif !== '') {
            $builder->where('u.actif', filter_var($actif, FILTER_VALIDATE_BOOLEAN));
        }

        $recherche = trim((string) ($filtres['q'] ?? ''));

        if ($recherche !== '') {
            $builder->groupStart()
                ->like('u.identifiant', $recherche)
                ->orLike('e.nom', $recherche)
                ->groupEnd();
        }
    }
}
