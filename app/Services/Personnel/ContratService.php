<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\ContratModel;
use App\Models\Personnel\StatusContratModel;
use App\Models\Personnel\ContratHistoriqueModel;
use App\Models\Personnel\SalarieModel;
use App\Models\Personnel\StatusSalarieModel;
use App\Models\Personnel\AffectationModel;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ConflitException;
use App\Exceptions\AccesRefuseException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\Tri;
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
    private const TYPES_NON_PERMANENTS = [2, 3, 4, 7];
    private const MAX_RENOUVELLEMENTS  = 2;

    public function __construct(
        private readonly ContratModel $contratModel,
        private readonly StatusContratModel $statusModel,
        private readonly ContratHistoriqueModel $historiqueModel,
        private readonly SalarieModel $salarieModel,
        private readonly StatusSalarieModel $statusSalarieModel,
        private readonly AffectationModel $affectationModel,
        private readonly PeriodeEssaiService $periodeEssaiService,
        private readonly GrilleService $grilleService,
        private readonly AuditService $auditService,
        private readonly WorkflowService $workflowService,
    ) {}

    // creerContrat
    public function creerContrat(array $data, SessionUtilisateur $u): int
    {
        if (!$u->aLeRole(['rh', 'recruteur', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour créer un contrat');
        }

        $idSalarie = (int) ($data['idSalarie'] ?? 0);
        if ($this->salarieModel->find($idSalarie) === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        $idPoste = (int) ($data['idPoste'] ?? 0);
        if (!$this->contratModel->posteExiste($idPoste)) {
            throw new ObjetIntrouvableException("Poste #{$idPoste} introuvable");
        }

        $dateDebut = $data['dateDebut'] ?? null;
        if ($dateDebut === null) {
            throw new DonneesIncoherentesException('dateDebut obligatoire');
        }

        $typeContratId = (int) ($data['typeContratId'] ?? 0);
        $dateFin       = $data['dateFin'] ?? null;

        if (in_array($typeContratId, self::TYPES_NON_PERMANENTS, true) && $dateFin === null) {
            throw new DonneesIncoherentesException('dateFin obligatoire pour ce type de contrat');
        }

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

        if ($this->contratModel->contratEnCours($idSalarie) !== null) {
            throw new ConflitException("Un contrat est déjà En cours pour ce salarié");
        }
		
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
				$dureeEffective = $this->periodeEssaiService->dureeEffective(
					$idSalarie, $typeContratId, $dateFin, $categorie, $duree, $dateDebut  // ← AJOUT $dateDebut
				);
				$periodeEssai = [
					'debut' => $dateDebut,
					'fin'   => date('Y-m-d', strtotime($dateDebut . ' +' . $dureeEffective . ' days')),
				];
			} else {
				$dureeEffective = $this->periodeEssaiService->dureeEffective(
					$idSalarie, $typeContratId, $dateFin, $categorie, null, $dateDebut  // ← AJOUT $dateDebut
				);
				$periodeEssai = [
					'debut' => $dateDebut,
					'fin'   => date('Y-m-d', strtotime($dateDebut . ' +' . $dureeEffective . ' days')),
				];
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

    // modifier
    public function modifier(int $idContrat, array $modifications, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour modifier un contrat');
        }

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

    // soumettrePourSignature
    public function soumettrePourSignature(int $idContrat, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour soumettre un contrat');
        }

        $this->changerStatut($idContrat, 'En attente de signature', $u, 'Soumis pour signature');
    }

    // signer
    public function signer(int $idContrat, string $dateSignature, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour signer un contrat');
        }

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

    // activer
    public function activer(int $idContrat, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour activer un contrat');
        }

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

            $statutSalarieCible = 'Actif';
            if ($contrat->periode_essai_debut !== null
                && $contrat->periode_essai_fin !== null
                && $contrat->periode_essai_fin >= date('Y-m-d')
            ) {
                $statutSalarieCible = "En période d'essai";
            }

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

    // mettreFin
    public function mettreFin(int $idContrat, string $motif, SessionUtilisateur $u): void
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour mettre fin à un contrat');
        }

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

            $affectationEnCours = $this->affectationModel->affectationEnCours((int) $contrat->id_salarie);
            if ($affectationEnCours !== null) {
                $this->affectationModel->update($affectationEnCours->id_affectation, [
                    'date_fin' => date('Y-m-d'),
                ]);
            }

            $this->auditService->journaliser(
                $u->idUtilisateur, 'UPDATE', 'salarie', (int) $contrat->id_salarie,
                'Statut → Parti', $u->adresseIp
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    // renouveler
    public function renouveler(int $idContrat, string $nouvelleDateFin, SessionUtilisateur $u): int
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour renouveler un contrat');
        }

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

    // creerAvenant
    public function creerAvenant(int $idContrat, array $modifications, SessionUtilisateur $u): int
    {
        if (!$u->aLeRole(['rh', 'admin'])) {
            throw new AccesRefuseException('Rôle insuffisant pour créer un avenant');
        }

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

    // consulter
    public function consulter(int $idContrat): ?array
    {
        $row = $this->requeteContrats()
            ->where('c.id_contrat', $idContrat)
            ->get()
            ->getRowArray();

        return $row;
    }

    // lister
    public function lister(array $filtre = []): array
    {
        $requete = $this->requeteContrats();

        if (!empty($filtre['statut'])) {
            $statut = is_numeric($filtre['statut'])
                ? (int) $filtre['statut']
                : $this->statusModel->idParDesignation((string) $filtre['statut']);
            $requete->where('c.statut', $statut);
        }
        if (!empty($filtre['idSalarie']) && is_numeric($filtre['idSalarie'])) {
            $requete->where('c.id_salarie', (int) $filtre['idSalarie']);
        }
        if (!empty($filtre['typeContratId'])) {
            $requete->where('c.type_contrat_id', $filtre['typeContratId']);
        }

        [$colonne, $ordre] = Tri::resoudre($filtre, [
            'id_contrat'    => 'c.id_contrat',
            'id_salarie'    => 'c.id_salarie',
            'date_debut'    => 'c.date_debut',
            'date_fin'      => 'c.date_fin',
            'salaire_base'  => 'c.salaire_base',
            'statut'        => 'sc.designation',
        ], 'c.date_debut', 'DESC');

        return $requete->orderBy($colonne, $ordre)->get()->getResultArray();
    }

    private function requeteContrats()
    {
        return $this->contratModel->db->table('contrat c')
            ->select('c.*, sc.designation AS statut_designation, tc.designation AS type_contrat_designation, s.nom AS salarie_nom, s.prenom AS salarie_prenom')
            ->join('status_contrat sc', 'sc.id = c.statut', 'left')
            ->join('type_contrat tc', 'tc.id = c.type_contrat_id', 'left')
            ->join('salarie s', 's.id_salarie = c.id_salarie', 'left');
    }

    // historique
    public function historique(int $idContrat): array
    {
        return $this->historiqueModel->listerParContrat($idContrat);
    }

    // contratsEnCours
    public function contratsEnCours(int $idSalarie): array
    {
        return $this->contratModel->where('id_salarie', $idSalarie)
                                   ->where('statut', 4)
                                   ->findAll();
    }

    // changerStatut
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