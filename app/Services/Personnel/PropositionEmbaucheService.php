<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\PropositionEmbaucheModel;
use App\Models\Personnel\RevendicationModel;
use App\Models\Personnel\ReadModels\StatusPropositionReadModel;
use App\Models\Personnel\ReadModels\CandidatureReadModel;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\ConflitException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\Tri;
use App\Libraries\Common\WorkflowService;

/**
 * Service métier du module Proposition d'embauche (RH.md §II.B.2).
 *
 * Une proposition ne peut être créée que pour une candidature Retenue.
 * La négociation (revendications) est gérée par RevendicationService.
 */
class PropositionEmbaucheService
{
    private const ROLES = ['rh', 'recruteur', 'direction', 'admin'];
    private const STATUTS_NEGOCIATION = ['Envoyée', 'En négociation'];
    private const STATUTS_ACTIFS = ['À envoyer', 'Envoyée', 'En négociation'];

    public function __construct(
        private readonly PropositionEmbaucheModel $propositionModel,
        private readonly RevendicationModel $revendicationModel,
        private readonly StatusPropositionReadModel $statusModel,
        private readonly CandidatureReadModel $candidatureModel,
        private readonly GrilleService $grilleService,
        private readonly AuditService $auditService,
        private readonly WorkflowService $workflowService,
    ) {}

    // creerDepuisCandidature
    public function creerDepuisCandidature(
        int $idCandidature,
        float $remunerationProposee,
        ?string $dateEmbaucheProposee,
        SessionUtilisateur $u
    ): int {
        $this->exigerRole($u);

        $candidature = $this->candidatureModel->find($idCandidature);
        if ($candidature === null) {
            throw new ObjetIntrouvableException("Candidature #{$idCandidature} introuvable");
        }

        $statutCandidature = $this->designationCandidature($idCandidature);
        if ($statutCandidature !== 'Retenue') {
            throw new StatutInvalideException(
                "La candidature doit être Retenue (statut actuel : {$statutCandidature})"
            );
        }

        if ($this->propositionActive($idCandidature) !== null) {
            throw new ConflitException(
                "Une proposition active existe déjà pour la candidature #{$idCandidature}"
            );
        }

        if ($remunerationProposee <= 0) {
            throw new DonneesIncoherentesException('La rémunération proposée doit être supérieure à 0');
        }

        $idPoste = $this->idPosteDepuisCandidature($idCandidature);
        if (!$this->grilleService->remunerationValide($idPoste, $remunerationProposee)) {
            $fourchette = $this->grilleService->fourchettePoste($idPoste);
            throw new DonneesIncoherentesException(
                "Rémunération {$remunerationProposee} hors grille ({$fourchette['min']}-{$fourchette['max']})"
            );
        }

        $statut = $this->statusModel->idParDesignation('À envoyer');

        $db = $this->propositionModel->db;
        $db->transStart();

        try {
            $idProposition = $this->propositionModel->insert([
                'statut'                  => $statut,
                'id_candidature'          => $idCandidature,
                'date_proposition'        => date('Y-m-d'),
                'remuneration_proposee'   => $remunerationProposee,
                'date_embauche_proposee'  => $dateEmbaucheProposee,
                'date_reponse'            => null,
                'reponse'                 => null,
            ], true);

            if ($idProposition === false) {
                throw new \RuntimeException(
                    'Échec insertion proposition : ' . json_encode($this->propositionModel->errors())
                );
            }

            $this->enregistrerHistoriqueInitiale(
                (int) $idProposition,
                $statut,
                $u,
                'Création de la proposition'
            );

            $this->auditService->journaliser(
                $u->idUtilisateur,
                'CREATE',
                'proposition_embauche',
                (int) $idProposition,
                null,
                $u->adresseIp
            );

            $db->transComplete();

            return (int) $idProposition;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // envoyer
    public function envoyer(int $idProposition, SessionUtilisateur $u): void
    {
        $this->exigerRole($u);
        $this->changerStatut($idProposition, 'Envoyée', $u, 'Envoi de la proposition au candidat');
    }

    // enregistrerReponse
    public function enregistrerReponse(int $idProposition, array $reponse, ?SessionUtilisateur $u): void
    {
        $proposition = $this->trouver($idProposition);
        $statutActuel = $this->designationStatut((int) $proposition->statut);

        if (!in_array($statutActuel, self::STATUTS_NEGOCIATION, true)) {
            throw new StatutInvalideException(
                "La proposition doit être Envoyée ou En négociation (statut actuel : {$statutActuel})"
            );
        }

        if ($this->revendicationsOuvertes($idProposition) !== []) {
            throw new StatutInvalideException(
                'La réponse est bloquée tant que des revendications sont ouvertes'
            );
        }

        $accepte = (bool) ($reponse['accepte'] ?? false);
        $commentaire = isset($reponse['commentaire']) ? (string) $reponse['commentaire'] : null;
        $statutCible = $accepte ? 'Acceptée' : 'Refusée';

        $db = $this->propositionModel->db;
        $db->transStart();

        try {
            $this->propositionModel->update($idProposition, [
                'date_reponse' => date('Y-m-d'),
                'reponse'      => $commentaire ?? $statutCible,
            ]);

            $this->changerStatut($idProposition, $statutCible, $u, $commentaire);

            $this->auditService->journaliser(
                $u?->idUtilisateur,
                $accepte ? 'VALIDATION' : 'REFUS',
                'proposition_embauche',
                $idProposition,
                $commentaire,
                $u?->adresseIp
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // contreProposer
    public function contreProposer(
        int $idProposition,
        float $remunerationProposee,
        ?string $dateEmbaucheProposee,
        SessionUtilisateur $u
    ): void {
        $this->exigerRole($u);

        $proposition = $this->trouver($idProposition);
        $statutActuel = $this->designationStatut((int) $proposition->statut);

        if ($statutActuel !== 'En négociation') {
            throw new StatutInvalideException(
                "Seule une proposition En négociation peut être contre-proposée (statut : {$statutActuel})"
            );
        }

        if ($remunerationProposee <= 0) {
            throw new DonneesIncoherentesException('La rémunération proposée doit être supérieure à 0');
        }

        $idPoste = $this->idPosteDepuisCandidature((int) $proposition->id_candidature);
        if (!$this->grilleService->remunerationValide($idPoste, $remunerationProposee)) {
            throw new DonneesIncoherentesException('Rémunération hors grille salariale');
        }

        $db = $this->propositionModel->db;
        $db->transStart();

        try {
            $this->propositionModel->update($idProposition, [
                'remuneration_proposee'  => $remunerationProposee,
                'date_embauche_proposee' => $dateEmbaucheProposee,
            ]);

            $this->changerStatut($idProposition, 'Envoyée', $u, 'Contre-proposition');

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // marquerExpiree
    public function marquerExpiree(int $idProposition, ?SessionUtilisateur $u): void
    {
        $proposition = $this->trouver($idProposition);
        $statutActuel = $this->designationStatut((int) $proposition->statut);

        if (!in_array($statutActuel, self::STATUTS_NEGOCIATION, true)) {
            throw new StatutInvalideException(
                "Seule une proposition Envoyée ou En négociation peut expirer (statut : {$statutActuel})"
            );
        }

        if ($this->revendicationsOuvertes($idProposition) !== []) {
            throw new StatutInvalideException(
                'Impossible d\'expirer une proposition dont la négociation est ouverte'
            );
        }

        $delai = (int) env('PROPOSITION_DELAI_JOURS', 15);
        $dateExpiration = strtotime((string) $proposition->date_proposition . ' +' . $delai . ' days');

        if ($dateExpiration > time()) {
            throw new DonneesIncoherentesException(
                "Le délai de réponse de {$delai} jours n'est pas dépassé"
            );
        }

        $this->changerStatut($idProposition, 'Expirée', $u, 'Délai de réponse dépassé');
    }

    // consulter
    public function consulter(int $idProposition): ?object
    {
        $ligne = $this->propositionModel->db->table('proposition_embauche p')
            ->select('p.*, sp.designation AS statut_designation, c.id_offre, ca.nom, ca.prenom, ca.email, oe.titre AS offre')
            ->join('status_proposition_embauche sp', 'sp.id = p.statut', 'left')
            ->join('candidature c', 'c.id_candidature = p.id_candidature', 'left')
            ->join('candidat ca', 'ca.id_candidat = c.id_candidat', 'left')
            ->join('offre_emploi oe', 'oe.id_offre = c.id_offre', 'left')
            ->where('p.id_proposition', $idProposition)
            ->get()
            ->getRow();

        return $ligne ?: null;
    }

    public function trouverParCandidature(int $idCandidature): ?object
    {
        return $this->propositionModel->derniereParCandidature($idCandidature);
    }

    // lister
    public function lister(array $filtre = []): array
    {
        $query = $this->propositionModel->db->table('proposition_embauche p')
            ->select([
                'p.*',
                'sp.designation AS statut_designation',
                'c.id_offre',
                'ca.nom',
                'ca.prenom',
                'ca.email',
                'oe.titre AS offre',
            ])
            ->join('status_proposition_embauche sp', 'sp.id = p.statut', 'left')
            ->join('candidature c', 'c.id_candidature = p.id_candidature', 'left')
            ->join('candidat ca', 'ca.id_candidat = c.id_candidat', 'left')
            ->join('offre_emploi oe', 'oe.id_offre = c.id_offre', 'left');

        if (!empty($filtre['statut'])) {
            $query->where('sp.designation', $filtre['statut']);
        }
        if (!empty($filtre['idCandidature'])) {
            $query->where('p.id_candidature', (int) $filtre['idCandidature']);
        }
        if (!empty($filtre['idOffre'])) {
            $query->where('c.id_offre', (int) $filtre['idOffre']);
        }

        [$colonne, $ordre] = Tri::resoudre($filtre, [
            'id_proposition' => 'p.id_proposition',
            'candidat'       => 'ca.nom',
            'offre'          => 'oe.titre',
            'date_embauche' => 'p.date_embauche_proposee',
            'statut'         => 'sp.designation',
        ], 'p.id_proposition', 'DESC');

        return $query->orderBy($colonne, $ordre)->get()->getResultArray();
    }

    // historique
    public function historique(int $idProposition): array
    {
        return $this->propositionModel->db->table('proposition_embauche_status_historique h')
            ->select('h.*, sa.designation AS statut_ancien_designation, sn.designation AS statut_nouveau_designation')
            ->join('status_proposition_embauche sa', 'sa.id = h.statut_ancien', 'left')
            ->join('status_proposition_embauche sn', 'sn.id = h.statut_nouveau', 'left')
            ->where('h.id_proposition', $idProposition)
            ->orderBy('h.date_changement', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function estStatutFinal(string $statut): bool
    {
        return $this->statusModel->estFinal($statut);
    }

    private function changerStatut(
        int $idProposition,
        string $statutCible,
        ?SessionUtilisateur $u,
        ?string $commentaire = null
    ): void {
        $proposition = $this->trouver($idProposition);
        $statutActuel = $this->designationStatut((int) $proposition->statut);

        if ($statutActuel === $statutCible) {
            return;
        }

        if (!$this->statusModel->transitionAutorisee($statutActuel, $statutCible)) {
            throw new StatutInvalideException(
                "Transition {$statutActuel} -> {$statutCible} interdite"
            );
        }

        $this->workflowService->changerStatut(
            'proposition',
            $idProposition,
            $statutCible,
            $u,
            $commentaire
        );
    }

    private function enregistrerHistoriqueInitiale(
        int $idProposition,
        int $statut,
        SessionUtilisateur $u,
        string $commentaire
    ): void {
        $this->propositionModel->db->table('proposition_embauche_status_historique')->insert([
            'id_proposition'  => $idProposition,
            'statut_ancien'  => $statut,
            'statut_nouveau' => $statut,
            'date_changement'=> date('Y-m-d H:i:s'),
            'id_utilisateur' => $u->idUtilisateur,
            'commentaire'    => $commentaire,
        ]);
    }

    private function propositionActive(int $idCandidature): ?object
    {
        foreach ($this->propositionModel->listerParCandidature($idCandidature) as $proposition) {
            $designation = $this->designationStatut((int) $proposition->statut);
            if (in_array($designation, self::STATUTS_ACTIFS, true)) {
                return $proposition;
            }
        }

        return null;
    }

    private function revendicationsOuvertes(int $idProposition): array
    {
        $statutsOuverts = [
            $this->revendicationStatusId('Soumise'),
            $this->revendicationStatusId('En discussion'),
        ];

        return $this->revendicationModel
            ->where('id_proposition', $idProposition)
            ->whereIn('statut_id', $statutsOuverts)
            ->findAll();
    }

    private function revendicationStatusId(string $designation): int
    {
        $ligne = $this->propositionModel->db->table('status_revendication')
            ->select('id')
            ->where('designation', $designation)
            ->get()
            ->getRowArray();

        if ($ligne === null) {
            throw new \RuntimeException("Statut de revendication inconnu : {$designation}");
        }

        return (int) $ligne['id'];
    }

    private function designationStatut(int $idStatut): string
    {
        try {
            return $this->statusModel->designationParId($idStatut);
        } catch (\RuntimeException) {
            return '';
        }
    }

    private function designationCandidature(int $idCandidature): string
    {
        $ligne = $this->propositionModel->db->table('candidature c')
            ->select('sc.designation')
            ->join('status_candidature sc', 'sc.id = c.statut', 'left')
            ->where('c.id_candidature', $idCandidature)
            ->get()
            ->getRowArray();

        if ($ligne === null) {
            throw new ObjetIntrouvableException("Candidature #{$idCandidature} introuvable");
        }

        return (string) ($ligne['designation'] ?? '');
    }

    private function idPosteDepuisCandidature(int $idCandidature): int
    {
        $ligne = $this->propositionModel->db->table('candidature c')
            ->select('d.id_poste')
            ->join('offre_emploi o', 'o.id_offre = c.id_offre')
            ->join('demande_recrutement d', 'd.id_demande = o.id_demande')
            ->where('c.id_candidature', $idCandidature)
            ->get()
            ->getRowArray();

        if ($ligne === null || $ligne['id_poste'] === null) {
            throw new ObjetIntrouvableException(
                "Poste introuvable pour la candidature #{$idCandidature}"
            );
        }

        return (int) $ligne['id_poste'];
    }

    private function trouver(int $idProposition): object
    {
        $proposition = $this->propositionModel->find($idProposition);

        if ($proposition === null) {
            throw new ObjetIntrouvableException("Proposition #{$idProposition} introuvable");
        }

        return $proposition;
    }

    private function exigerRole(SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(self::ROLES)) {
            throw new AccesRefuseException('Rôle insuffisant pour gérer une proposition');
        }
    }
}
