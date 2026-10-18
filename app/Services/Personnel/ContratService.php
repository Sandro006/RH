<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\ContratModel;
use App\Models\Personnel\StatusContratModel;
use App\Models\Personnel\ContratHistoriqueModel;
use App\Models\Personnel\SalarieModel;
use App\Models\Personnel\StatusSalarieModel;
use App\Entities\Personnel\Contrat;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ConflitException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\WorkflowService;

/**
 * Service métier du module Contrat (D3 + D4).
 *
 * Consignes respectées :
 *  - Architecture Service → Models (pas de HTTP, pas de SQL direct hors référentiels)
 *  - Pas d'appel aux services des autres pistes (lecture directe des référentiels)
 *  - WorkflowService centralisé (Iavo) pour tous les changements de statut
 *  - AuditService (Iavo) pour la traçabilité
 *  - Historique dans contrat_status_historique
 *  - Période d'essai optionnelle + plafonds légaux (RH.md §III)
 *  - Salaire dans la grille_salariale
 *  - Un seul contrat En cours par salarié
 *  - Limite 2 renouvellements
 *  - Avenant = nouveau contrat lié
 *  - PostgreSQL (NUMERIC, DATE, TIMESTAMPTZ)
 */
class ContratService
{
    private const TYPES_NON_PERMANENTS = [2, 3, 4, 7]; // CDD, Stage, Interim, Alternance
    private const MAX_RENOUVELLEMENTS  = 2;

    public function __construct(
        private readonly ContratModel $contratModel,
        private readonly StatusContratModel $statusModel,
        private readonly ContratHistoriqueModel $historiqueModel,
        private readonly SalarieModel $salarieModel,
        private readonly StatusSalarieModel $statusSalarieModel,
        private readonly PeriodeEssaiService $periodeEssaiService,
        private readonly GrilleService $grilleService,
        private readonly AuditService $auditService,
        private readonly WorkflowService $workflowService,
    ) {}

    // creer contrat
    public function creerContrat(array $data, SessionUtilisateur $u): int
    {
        // 1. Salarié
        $idSalarie = (int) ($data['idSalarie'] ?? 0);
        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        // 2. Poste
        $idPoste = (int) ($data['idPoste'] ?? 0);
        if (!$this->contratModel->posteExiste($idPoste)) {
            throw new ObjetIntrouvableException("Poste #{$idPoste} introuvable");
        }

        // 3. Dates
        $dateDebut = $data['dateDebut'] ?? null;
        if ($dateDebut === null) {
            throw new DonneesIncoherentesException('dateDebut obligatoire');
        }

        $typeContratId = (int) ($data['typeContratId'] ?? 0);
        $dateFin       = $data['dateFin'] ?? null;

        // 4. CDD/Stage/Interim/Alternance → dateFin obligatoire
        if (in_array($typeContratId, self::TYPES_NON_PERMANENTS, true) && $dateFin === null) {
            throw new DonneesIncoherentesException('dateFin obligatoire pour ce type de contrat');
        }

        // 5. Salaire dans la grille
        $salaire = (float) ($data['salaireBase'] ?? 0);
        if ($salaire <= 0) {
            throw new DonneesIncoherentesException('salaireBase obligatoire et > 0');
        }
        if (!$this->grilleService->remunerationValide($idPoste, $salaire)) {
            $f = $this->grilleService->fourchettePoste($idPoste);
            throw new DonneesIncoherentesException(
                "Salaire {$salaire} hors grille ({$f['min']}–{$f['max']}) pour le poste #{$idPoste}"
            );
        }

        // 6. Un seul contrat En cours par salarié
        if ($this->contratModel->contratEnCours($idSalarie) !== null) {
            throw new ConflitException("Un contrat est déjà En cours pour ce salarié");
        }

        // 7. Période d'essai optionnelle
        $periodeEssai = null;
        if (!empty($data['avecPeriodeEssai'])) {
            $categorie = $data['categorie'] ?? $this->periodeEssaiService->categorieDepuisPoste($idPoste);

            if (!empty($data['dureeEssaiJours'])) {
                $duree = (int) $data['dureeEssaiJours'];
                if (!$this->periodeEssaiService->validerDuree($typeContratId, $duree, $categorie)) {
                    throw new DonneesIncoherentesException(
                        "Durée d'essai {$duree} jours > plafond légal pour la catégorie {$categorie}"
                    );
                }
                $periodeEssai = [
                    'debut' => $dateDebut,
                    'fin'   => date('Y-m-d', strtotime($dateDebut . ' +' . $duree . ' days')),
                ];
            } else {
                $calc = $this->periodeEssaiService->calculer($typeContratId, $dateDebut, $dateFin, $categorie);
                $periodeEssai = ['debut' => $calc['debut'], 'fin' => $calc['fin']];
            }
        }

        $statutPreparation = $this->statusModel->idParDesignation('En préparation');

        $db = $this->contratModel->db;
        $db->transStart();

        try {
            $idContrat = $this->contratModel->insert([
                'id_salarie'          => $idSalarie,
                'id_poste'            => $idPoste,
                'type_contrat_id'     => $typeContratId,
                'date_debut'          => $dateDebut,
                'date_fin'            => $dateFin,
                'salaire_base'        => $salaire,
                'periode_essai_debut' => $periodeEssai['debut'] ?? null,
                'periode_essai_fin'   => $periodeEssai['fin'] ?? null,
                'date_signature'      => null,
                'statut'              => $statutPreparation,
            ], true);

            if ($idContrat === false) {
                throw new \RuntimeException('Échec insertion contrat : ' . json_encode($this->contratModel->errors()));
            }

            // WorkflowService centralisé (Iavo)
            $this->workflowService->changerStatut(
                'contrat',
                (int) $idContrat,
                'En préparation',
                $u,
                'Création du contrat'
            );

            $this->auditService->journaliser(
                $u->idUtilisateur, 'CREATE', 'contrat', (int) $idContrat, null, $u->adresseIp
            );

            $db->transComplete();
            return (int) $idContrat;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // modifier (uniquement en preparation)
    public function modifier(int $idContrat, array $modifications, SessionUtilisateur $u): void
    {
        $contrat = $this->contratModel->find($idContrat);
        if ($contrat === null) {
            throw new ObjetIntrouvableException("Contrat #{$idContrat} introuvable");
        }

        $statutPreparation = $this->statusModel->idParDesignation('En préparation');
        if ((int) $contrat->statut !== $statutPreparation) {
            throw new StatutInvalideException('Seul un contrat En préparation peut être modifié');
        }

        $autorises = ['date_debut', 'date_fin', 'salaire_base', 'periode_essai_debut', 'periode_essai_fin'];
        $payload   = array_intersect_key($modifications, array_flip($autorises));

        if (empty($payload)) {
            return;
        }

        if (isset($payload['salaire_base'])) {
            if (!$this->grilleService->remunerationValide((int) $contrat->id_poste, (float) $payload['salaire_base'])) {
                throw new DonneesIncoherentesException('Salaire hors grille');
            }
        }

        $this->contratModel->update($idContrat, $payload);
        $this->auditService->journaliser(
            $u->idUtilisateur, 'UPDATE', 'contrat', $idContrat, json_encode($payload), $u->adresseIp
        );
    }

    // soummettre signature
    public function soumettrePourSignature(int $idContrat, SessionUtilisateur $u): void
    {
        $this->changerStatut($idContrat, 'En attente de signature', $u, 'Soumis pour signature');
    }

    // signer
    public function signer(int $idContrat, string $dateSignature, SessionUtilisateur $u): void
    {
        $contrat = $this->contratModel->find($idContrat);
        if ($contrat === null) {
            throw new ObjetIntrouvableException("Contrat #{$idContrat} introuvable");
        }

        $statutAttente = $this->statusModel->idParDesignation('En attente de signature');
        if ((int) $contrat->statut !== $statutAttente) {
            throw new StatutInvalideException('Le contrat doit être En attente de signature');
        }

        $this->contratModel->update($idContrat, ['date_signature' => $dateSignature]);
        $this->changerStatut($idContrat, 'Signé', $u, 'Signature du contrat');
    }

    // mettre a jour le status du salairer
    public function activer(int $idContrat, SessionUtilisateur $u): void
    {
        $contrat = $this->contratModel->find($idContrat);
        if ($contrat === null) {
            throw new ObjetIntrouvableException("Contrat #{$idContrat} introuvable");
        }

        $statutSigne = $this->statusModel->idParDesignation('Signé');
        if ((int) $contrat->statut !== $statutSigne) {
            throw new StatutInvalideException('Le contrat doit être Signé');
        }

        $db = $this->contratModel->db;
        $db->transStart();

        try {
            $this->changerStatut($idContrat, 'En cours', $u, 'Activation du contrat');

            $statutSalarieCible = $contrat->periode_essai_debut !== null
                ? "En période d'essai"
                : 'Actif';

            $statutSalarieId = $this->statusSalarieModel->idParDesignation($statutSalarieCible);
            $this->salarieModel->update((int) $contrat->id_salarie, ['statut' => $statutSalarieId]);

            $this->auditService->journaliser(
                $u->idUtilisateur, 'UPDATE', 'salarie', (int) $contrat->id_salarie,
                "Statut → {$statutSalarieCible}", $u->adresseIp
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // mettre fin
    public function mettreFin(int $idContrat, string $motif, SessionUtilisateur $u): void
    {
        $contrat = $this->contratModel->find($idContrat);
        if ($contrat === null) {
            throw new ObjetIntrouvableException("Contrat #{$idContrat} introuvable");
        }

        $statutEnCours = $this->statusModel->idParDesignation('En cours');
        if ((int) $contrat->statut !== $statutEnCours) {
            throw new StatutInvalideException('Le contrat doit être En cours');
        }

        $statutCible = str_contains(strtolower($motif), 'rupture') ? 'Résilié' : 'Terminé';

        $db = $this->contratModel->db;
        $db->transStart();

        try {
            $this->changerStatut($idContrat, $statutCible, $u, $motif);

            $statutPartiId = $this->statusSalarieModel->idParDesignation('Parti');
            $this->salarieModel->update((int) $contrat->id_salarie, ['statut' => $statutPartiId]);

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // renouvelle le contrat
    public function renouveler(int $idContrat, string $nouvelleDateFin, SessionUtilisateur $u): int
    {
        $contrat = $this->contratModel->find($idContrat);
        if ($contrat === null) {
            throw new ObjetIntrouvableException("Contrat #{$idContrat} introuvable");
        }

        if (!in_array((int) $contrat->type_contrat_id, self::TYPES_NON_PERMANENTS, true)) {
            throw new StatutInvalideException('Seuls les contrats non permanents peuvent être renouvelés');
        }

        $nbRenouvellements = $this->contratModel->compterRenouvellements(
            (int) $contrat->id_salarie,
            (int) $contrat->type_contrat_id
        );
        if ($nbRenouvellements >= self::MAX_RENOUVELLEMENTS) {
            throw new StatutInvalideException(
                'Limite de ' . self::MAX_RENOUVELLEMENTS . ' renouvellements atteinte'
            );
        }

        $this->mettreFin($idContrat, 'Fin de terme — renouvellement', $u);

        return $this->creerContrat([
            'idSalarie'        => (int) $contrat->id_salarie,
            'idPoste'          => (int) $contrat->id_poste,
            'typeContratId'    => (int) $contrat->type_contrat_id,
            'dateDebut'        => date('Y-m-d', strtotime($contrat->date_fin . ' +1 day')),
            'dateFin'          => $nouvelleDateFin,
            'salaireBase'      => (float) $contrat->salaire_base,
            'avecPeriodeEssai' => false,
        ], $u);
    }

	//nouveau contrat liee
    public function creerAvenant(int $idContrat, array $modifications, SessionUtilisateur $u): int
    {
        $contrat = $this->contratModel->find($idContrat);
        if ($contrat === null) {
            throw new ObjetIntrouvableException("Contrat #{$idContrat} introuvable");
        }

        return $this->creerContrat([
            'idSalarie'        => (int) $contrat->id_salarie,
            'idPoste'          => (int) ($modifications['idPoste'] ?? $contrat->id_poste),
            'typeContratId'    => (int) $contrat->type_contrat_id,
            'dateDebut'        => $modifications['dateDebut'] ?? date('Y-m-d'),
            'dateFin'          => $modifications['dateFin'] ?? $contrat->date_fin,
            'salaireBase'      => (float) ($modifications['salaireBase'] ?? $contrat->salaire_base),
            'avecPeriodeEssai' => false,
        ], $u);
    }

    // consulter contrat
    public function consulter(int $idContrat): ?Contrat
    {
        return $this->contratModel->find($idContrat);
    }

	// lister les contrat
    public function lister(array $filtre = []): array
    {
        if (!empty($filtre['statut'])) {
            $this->contratModel->where('statut', $filtre['statut']);
        }
        if (!empty($filtre['idSalarie'])) {
            $this->contratModel->where('id_salarie', $filtre['idSalarie']);
        }
        if (!empty($filtre['typeContratId'])) {
            $this->contratModel->where('type_contrat_id', $filtre['typeContratId']);
        }
        return $this->contratModel->findAll();
    }
	
	// Historique
    public function historique(int $idContrat): array
    {
        return $this->historiqueModel->listerParContrat($idContrat);
    }

    public function contratsEnCours(int $idSalarie): array
    {
        return $this->contratModel->where('id_salarie', $idSalarie)
                                   ->where('statut', 4)
                                   ->findAll();
    }

    // function utils (changement de status via le Workflow de Iavo)
    private function changerStatut(int $idContrat, string $statutCible, SessionUtilisateur $u, ?string $commentaire = null): void
    {
        $this->workflowService->changerStatut(
            'contrat',
            $idContrat,
            $statutCible,
            $u,
            $commentaire
        );
    }
}