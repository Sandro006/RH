<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\RevendicationModel;
use App\Models\Personnel\StatusRevendicationModel;
use App\Models\Personnel\NatureRevendicationModel;
use App\Models\Personnel\ReadModels\StatusPropositionReadModel;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\WorkflowService;

/**
 * Fil de négociation d'une proposition d'embauche (revendications).
 */
class RevendicationService
{
    private const ROLES = ['rh', 'recruteur', 'direction', 'admin'];
    private const STATUTS_PRO_NEGOCIABLES = ['Envoyée', 'En négociation'];
    private const STATUTS_REV_OUVERTS = ['Soumise', 'En discussion'];

    public function __construct(
        private readonly RevendicationModel $revendicationModel,
        private readonly StatusRevendicationModel $statusModel,
        private readonly NatureRevendicationModel $natureModel,
        private readonly StatusPropositionReadModel $statusPropositionModel,
        private readonly PropositionEmbaucheService $propositionService,
        private readonly AuditService $auditService,
        private readonly WorkflowService $workflowService,
    ) {}

    // soumettre
    public function soumettre(array $nouvelle, ?SessionUtilisateur $auteur): int
    {
        $idProposition = (int) ($nouvelle['idProposition'] ?? 0);
        if ($idProposition <= 0) {
            throw new DonneesIncoherentesException('idProposition obligatoire');
        }

        $proposition = $this->propositionService->consulter($idProposition);
        if ($proposition === null) {
            throw new ObjetIntrouvableException("Proposition #{$idProposition} introuvable");
        }

        $statutProposition = $this->designationStatutProposition((int) $proposition->statut);
        if (!in_array($statutProposition, self::STATUTS_PRO_NEGOCIABLES, true)) {
            throw new StatutInvalideException(
                "Une revendication n'est possible que sur une proposition Envoyée ou En négociation (statut : {$statutProposition})"
            );
        }

        $sens = strtoupper((string) ($nouvelle['sens'] ?? 'CANDIDAT'));
        if (!in_array($sens, ['CANDIDAT', 'ENTREPRISE'], true)) {
            throw new DonneesIncoherentesException('sens doit être CANDIDAT ou ENTREPRISE');
        }

        $nature = (string) ($nouvelle['nature'] ?? '');
        if ($nature === '') {
            throw new DonneesIncoherentesException('nature obligatoire');
        }
        $natureId = $this->natureModel->idParDesignation($nature);

        $idCandidat = null;
        $idUtilisateur = null;

        if ($sens === 'CANDIDAT') {
            $idCandidat = isset($nouvelle['idCandidat'])
                ? (int) $nouvelle['idCandidat']
                : $this->idCandidatDepuisProposition((int) $proposition->id_candidature);
        } else {
            $idUtilisateur = $auteur?->idUtilisateur;
        }

        $statutSoumise = $this->statusModel->idParDesignation('Soumise');

        $db = $this->revendicationModel->db;
        $db->transStart();

        try {
            $idRevendication = $this->revendicationModel->insert([
                'id_proposition'  => $idProposition,
                'sens'            => $sens,
                'id_candidat'     => $idCandidat,
                'id_utilisateur'  => $idUtilisateur,
                'nature_id'       => $natureId,
                'valeur_demandee' => $nouvelle['valeurDemandee'] ?? null,
                'commentaire'     => $nouvelle['commentaire'] ?? null,
                'statut_id'       => $statutSoumise,
                'id_repondant'    => null,
                'reponse'         => null,
                'date_reponse'    => null,
            ], true);

            if ($idRevendication === false) {
                throw new \RuntimeException(
                    'Échec insertion revendication : ' . json_encode($this->revendicationModel->errors())
                );
            }

            $this->enregistrerHistoriqueInitiale(
                (int) $idRevendication,
                $statutSoumise,
                $auteur,
                'Revendication soumise'
            );

            if ($statutProposition !== 'En négociation') {
                $this->workflowService->changerStatut(
                    'proposition',
                    $idProposition,
                    'En négociation',
                    $auteur,
                    'Négociation ouverte'
                );
            }

            $this->auditService->journaliser(
                $auteur?->idUtilisateur,
                'CREATE',
                'revendication',
                (int) $idRevendication,
                null,
                $auteur?->adresseIp
            );

            $db->transComplete();

            return (int) $idRevendication;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // repondre
    public function repondre(int $idRevendication, array $reponse, SessionUtilisateur $u): void
    {
        $this->exigerRole($u);

        $revendication = $this->trouver($idRevendication);
        $statutActuel = $this->statusModel->designationParId((int) $revendication->statut_id);

        if (!in_array($statutActuel, self::STATUTS_REV_OUVERTS, true)) {
            throw new StatutInvalideException(
                "La revendication doit être Soumise ou En discussion (statut : {$statutActuel})"
            );
        }

        $decision = mb_strtolower(trim((string) ($reponse['decision'] ?? '')));
        $decision = match ($decision) {
            'acceptee', 'acceptée' => 'Acceptée',
            'refusee', 'refusée'   => 'Refusée',
            'en_discussion', 'en discussion' => 'En discussion',
            default => throw new DonneesIncoherentesException(
                'Décision invalide (Acceptée, Refusée ou En discussion)'
            ),
        };

        if (!$this->statusModel->transitionAutorisee($statutActuel, $decision)) {
            throw new StatutInvalideException("Transition {$statutActuel} -> {$decision} interdite");
        }

        $texte = isset($reponse['reponse']) ? (string) $reponse['reponse'] : null;

        $db = $this->revendicationModel->db;
        $db->transStart();

        try {
            $this->revendicationModel->update($idRevendication, [
                'id_repondant' => $u->idUtilisateur,
                'reponse'      => $texte,
                'date_reponse' => date('Y-m-d H:i:s'),
            ]);

            $this->workflowService->changerStatut(
                'revendication',
                $idRevendication,
                $decision,
                $u,
                $texte
            );

            $idProposition = (int) $revendication->id_proposition;

            if ($this->ouvertes($idProposition) === []) {
                $this->appliquerAcceptees($idProposition, $u);
            }

            $this->auditService->journaliser(
                $u->idUtilisateur,
                'UPDATE',
                'revendication',
                $idRevendication,
                $texte,
                $u->adresseIp
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // appliquerAcceptees
    public function appliquerAcceptees(int $idProposition, SessionUtilisateur $u): void
    {
        $proposition = $this->propositionService->consulter($idProposition);
        if ($proposition === null) {
            throw new ObjetIntrouvableException("Proposition #{$idProposition} introuvable");
        }

        $statutProposition = $this->designationStatutProposition((int) $proposition->statut);
        if ($statutProposition !== 'En négociation') {
            return;
        }

        $remuneration = (float) ($proposition->remuneration_proposee ?? 0);
        $dateEmbauche = $proposition->date_embauche_proposee;

        foreach ($this->revendicationModel->listerParProposition($idProposition) as $revendication) {
            $statut = $this->statusModel->designationParId((int) $revendication->statut_id);
            if ($statut !== 'Acceptée') {
                continue;
            }

            $nature = $this->natureModel->designationParId((int) $revendication->nature_id);

            if ($nature === 'Rémunération' && is_numeric($revendication->valeur_demandee)) {
                $remuneration = (float) $revendication->valeur_demandee;
            } elseif ($nature === "Date d'embauche" && !empty($revendication->valeur_demandee)) {
                $timestamp = strtotime((string) $revendication->valeur_demandee);
                if ($timestamp === false) {
                    throw new DonneesIncoherentesException(
                        "Date d'embauche invalide : {$revendication->valeur_demandee}"
                    );
                }
                $dateEmbauche = date('Y-m-d', $timestamp);
            }
        }

        if ($remuneration <= 0) {
            throw new DonneesIncoherentesException('La proposition ne contient aucune rémunération exploitable');
        }

        $this->propositionService->contreProposer($idProposition, $remuneration, $dateEmbauche, $u);
    }

    // retirer
    public function retirer(int $idRevendication, ?SessionUtilisateur $auteur): void
    {
        $revendication = $this->trouver($idRevendication);
        $statutActuel = $this->statusModel->designationParId((int) $revendication->statut_id);

        if (!in_array($statutActuel, self::STATUTS_REV_OUVERTS, true)) {
            throw new StatutInvalideException(
                "Seule une revendication ouverte peut être retirée (statut : {$statutActuel})"
            );
        }

        $this->workflowService->changerStatut(
            'revendication',
            $idRevendication,
            'Retirée',
            $auteur,
            'Retrait de la revendication'
        );

        $this->auditService->journaliser(
            $auteur?->idUtilisateur,
            'UPDATE',
            'revendication',
            $idRevendication,
            'Retrait',
            $auteur?->adresseIp
        );
    }

    // lister
    public function lister(int $idProposition): array
    {
        return $this->revendicationModel->db->table('revendication r')
            ->select('r.*, n.designation AS nature, sr.designation AS statut')
            ->join('nature_revendication n', 'n.id = r.nature_id', 'left')
            ->join('status_revendication sr', 'sr.id = r.statut_id', 'left')
            ->where('r.id_proposition', $idProposition)
            ->orderBy('r.date_creation', 'ASC')
            ->get()
            ->getResultArray();
    }

    // ouvertes
    public function ouvertes(int $idProposition): array
    {
        $statuts = [];
        foreach (self::STATUTS_REV_OUVERTS as $designation) {
            $statuts[] = $this->statusModel->idParDesignation($designation);
        }

        return $this->revendicationModel
            ->where('id_proposition', $idProposition)
            ->whereIn('statut_id', $statuts)
            ->findAll();
    }

    public function historique(int $idRevendication): array
    {
        return $this->revendicationModel->db->table('revendication_status_historique h')
            ->select('h.*, sa.designation AS statut_ancien_designation, sn.designation AS statut_nouveau_designation')
            ->join('status_revendication sa', 'sa.id = h.statut_ancien', 'left')
            ->join('status_revendication sn', 'sn.id = h.statut_nouveau', 'left')
            ->where('h.id_revendication', $idRevendication)
            ->orderBy('h.date_changement', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function consulter(int $idRevendication): ?object
    {
        return $this->revendicationModel->find($idRevendication);
    }

    private function enregistrerHistoriqueInitiale(
        int $idRevendication,
        int $statut,
        ?SessionUtilisateur $auteur,
        string $commentaire
    ): void {
        $this->revendicationModel->db->table('revendication_status_historique')->insert([
            'id_revendication' => $idRevendication,
            'statut_ancien'   => $statut,
            'statut_nouveau'  => $statut,
            'date_changement' => date('Y-m-d H:i:s'),
            'id_utilisateur'  => $auteur?->idUtilisateur,
            'commentaire'     => $commentaire,
        ]);
    }

    private function idCandidatDepuisProposition(int $idCandidature): int
    {
        $ligne = $this->revendicationModel->db->table('candidature')
            ->select('id_candidat')
            ->where('id_candidature', $idCandidature)
            ->get()
            ->getRowArray();

        if ($ligne === null) {
            throw new ObjetIntrouvableException("Candidature #{$idCandidature} introuvable");
        }

        return (int) $ligne['id_candidat'];
    }

    private function designationStatutProposition(int $idStatut): string
    {
        try {
            return $this->statusPropositionModel->designationParId($idStatut);
        } catch (\RuntimeException) {
            return '';
        }
    }

    private function trouver(int $idRevendication): object
    {
        $revendication = $this->revendicationModel->find($idRevendication);

        if ($revendication === null) {
            throw new ObjetIntrouvableException("Revendication #{$idRevendication} introuvable");
        }

        return $revendication;
    }

    private function exigerRole(SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(self::ROLES)) {
            throw new AccesRefuseException('Rôle insuffisant pour répondre à une revendication');
        }
    }
}
