<?php

declare(strict_types=1);

namespace App\Services\Paie;

use App\Exceptions\CongeException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Models\Paie\ElementPaieModel;
use App\Models\Personnel\SalarieModel;

/**
 * ElementPaieService : saisie des éléments variables de paie par mois.
 * - type PRIME / HEURE_SUP / AVANTAGE : montant positif (gain)
 * - type RETTENUE : montant négatif ou positif (interpreté comme une retenue) ;
 *   par convention le service stocke le signe saisi ; pour RETTENUE on stocke
 *   la valeur absolue en négatif pour rester additive dans le calcul.
 */
class ElementPaieService
{
    private const TYPES_AUTORISES = ['PRIME', 'HEURE_SUP', 'RETTENUE', 'AVANTAGE', 'AUTRE'];

    public function __construct(
        private readonly ElementPaieModel $model,
        private readonly SalarieModel $salarieModel,
        private readonly AuditService $audit,
    ) {
    }

    public function lister(int $idSalarie, string $mois): array
    {
        return $this->model->listerParSalarieAndMois($idSalarie, $mois);
    }

    /**
     * @return int id de l'élément créé
     */
    public function saisir(array $donnees, SessionUtilisateur $u): int
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new CongeException('Rôle insuffisant pour saisir un élément de paie', CongeException::NON_AUTORISE);
        }

        $idSalarie = (int) ($donnees['id_salarie'] ?? 0);
        if ($idSalarie <= 0) {
            throw new \InvalidArgumentException('id_salarie requis');
        }
        if ($this->salarieModel->find($idSalarie) === null) {
            throw new CongeException("Salarié #{$idSalarie} introuvable", CongeException::OBJET_INTROUVABLE);
        }

        $mois = $this->normaliserMois((string) ($donnees['mois'] ?? ''));
        $type = strtoupper(trim((string) ($donnees['type_element'] ?? '')));
        if (!in_array($type, self::TYPES_AUTORISES, true)) {
            throw new \InvalidArgumentException('type_element invalide');
        }

        $libelle = trim((string) ($donnees['libelle'] ?? ''));
        if ($libelle === '') {
            throw new \InvalidArgumentException('libelle requis');
        }

        $montant = (float) ($donnees['montant'] ?? 0);
        if ($montant == 0.0) {
            throw new \InvalidArgumentException('montant différent de 0 requis');
        }
        if ($type === 'RETTENUE') {
            $montant = -abs($montant); // retenue toujours soustraite
        }

        $id = (int) $this->model->insert([
            'id_salarie'   => $idSalarie,
            'mois'         => $mois,
            'type_element' => $type,
            'libelle'      => $libelle,
            'montant'      => round($montant, 2),
            'commentaire'  => (string) ($donnees['commentaire'] ?? '') ?: null,
            'id_saisi_par' => $u->idUtilisateur,
            'date_saisie'  => date('Y-m-d H:i:s'),
        ], true);

        $this->audit->journaliser($u->idUtilisateur, 'CREATE', 'element_paie', $id, [
            'id_salarie' => $idSalarie,
            'mois' => $mois,
            'type' => $type,
            'montant' => $montant,
        ], $u->adresseIp);

        return $id;
    }

    private function normaliserMois(string $mois): string
    {
        // '2026-11' ou '2026-11-01' -> premier jour du mois
        if (preg_match('/^(\d{4})-(\d{2})(?:-(\d{2}))?$/', trim($mois), $m) !== 1) {
            throw new \InvalidArgumentException('mois invalide (attendu YYYY-MM ou YYYY-MM-DD)');
        }

        return sprintf('%04d-%02d-01', (int) $m[1], (int) $m[2]);
    }
}
