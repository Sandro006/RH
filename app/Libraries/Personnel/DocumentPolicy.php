<?php

declare(strict_types=1);

namespace App\Libraries\Personnel;

use App\Entities\Personnel\Document;
use App\Libraries\Common\SessionUtilisateur;
use App\Models\Admin\EmployeModel;

/**
 * Politique d'accès aux pièces du dossier administratif.
 *
 * Matrice retenue :
 *   - admin, rh        : tous les niveaux ;
 *   - direction        : public, interne, rh ;
 *   - manager, recruteur, controle : public, interne ;
 *   - salarie          : uniquement ses propres pièces, tous niveaux ;
 *   - rôle inconnu     : public, interne.
 */
class DocumentPolicy
{
    public const NIVEAUX = ['public', 'interne', 'rh', 'restreint'];

    /** @var array<int, int|null> */
    private array $salarieParUtilisateur = [];

    public function __construct(private readonly EmployeModel $employeModel)
    {
    }

    public function niveauEffectif(Document $document): string
    {
        $niveau = (string) ($document->niveau_confidentialite ?? 'interne');

        return in_array($niveau, self::NIVEAUX, true) ? $niveau : 'interne';
    }

    public function peutLire(SessionUtilisateur $u, Document $document): bool
    {
        if ($u->aLeRole(['admin', 'rh'])) {
            return true;
        }

        if ($this->estProprietaire($u, $document)) {
            return true;
        }

        return in_array($this->niveauEffectif($document), $this->niveauxAutorises($u), true);
    }

    public function peutAjouter(SessionUtilisateur $u): bool
    {
        return $u->aLeRole(['admin', 'rh', 'recruteur']);
    }

    public function peutRemplacer(SessionUtilisateur $u): bool
    {
        return $u->aLeRole(['admin', 'rh']);
    }

    public function peutArchiver(SessionUtilisateur $u): bool
    {
        return $u->aLeRole(['admin', 'rh']);
    }

    public function estProprietaire(SessionUtilisateur $u, Document $document): bool
    {
        $idSalarie = $this->idSalarieDe($u);

        return $idSalarie !== null && $idSalarie === (int) $document->id_salarie;
    }

    /**
     * @return list<string>
     */
    public function niveauxAutorises(SessionUtilisateur $u): array
    {
        return match ($u->role) {
            'direction' => ['public', 'interne', 'rh'],
            'salarie'   => [],
            default     => ['public', 'interne'],
        };
    }

    public function idSalarieDe(SessionUtilisateur $u): ?int
    {
        if (array_key_exists($u->idUtilisateur, $this->salarieParUtilisateur)) {
            return $this->salarieParUtilisateur[$u->idUtilisateur];
        }

        $idSalarie = null;

        if ($u->idEmploye !== null) {
            $employe = $this->employeModel->find($u->idEmploye);

            if ($employe !== null && $employe->id_salarie !== null) {
                $idSalarie = (int) $employe->id_salarie;
            }
        }

        return $this->salarieParUtilisateur[$u->idUtilisateur] = $idSalarie;
    }
}
