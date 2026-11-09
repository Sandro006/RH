<?php

declare(strict_types=1);

namespace App\Services\Conges;

use App\Exceptions\CongeException;
use App\Models\Conges\DelegationValidationModel;
use App\Models\Conges\DemandeCongeModel;
use App\Models\Conges\StatutDemandeCongeModel;
use App\Models\Conges\TypeCongeModel;
use App\Models\Personnel\ContratModel;
use App\Models\Personnel\SalarieModel as PersonnelSalarieModel;

/**
 * Workflow CongeService (RG15→RG23) — voir TODO §3 :
 *   EN_ATTENTE_MANAGER → EN_ATTENTE_RH → VALIDEE
 *   (REFUSEE / ANNULEE possibles), tout en transaction (4.5).
 *
 * Adapter trouvėValideur/peutValider : salarie n'a pas de lien manager
 * (limitation documentée dans l'en-tête SQL Bloc 7). On utilise dans l'ordre :
 * delegation_validation, supérieur hiérarchique (rang poste supérieur dans
 * le même service via poste->rang), puis un utilisateur RH (rôle).
 */
class CongeService
{
    public function __construct(
        private readonly DemandeCongeModel $demandeModel,
        private readonly TypeCongeModel $typeCongeModel,
        private readonly StatutDemandeCongeModel $statutModel,
        private readonly PersonnelSalarieModel $personnelSalarieModel,
        private readonly ContratModel $contratModel,
        private readonly DelegationValidationModel $delegationModel,
        private readonly CalendrierService $calendrier,
        private readonly ControleDemandeService $controle,
        private readonly SoldeService $soldeService,
        private readonly AbsenceService $absenceService,
        private readonly AuditService $audit,
        private readonly NotificationService $notification,
    ) {
    }

    public function creerDemande(array $donnees, int $idSalarie): int
    {
        $idType = (int) ($donnees['id_type_conge'] ?? 0);
        $debut = (string) ($donnees['date_debut'] ?? '');
        $fin = (string) ($donnees['date_fin'] ?? '');
        $demiDebut = (bool) ($donnees['demi_journee_debut'] ?? false);
        $demiFin = (bool) ($donnees['demi_journee_fin'] ?? false);
        $motif = trim((string) ($donnees['motif'] ?? ''));

        if ($idType <= 0 || $debut === '' || $fin === '') {
            throw new CongeException('Type et dates sont requis', CongeException::DATES_INVALIDES);
        }

        $erreurs = $this->controle->verifier($idSalarie, $idType, $debut, $fin, $demiDebut, $demiFin);
        if ($erreurs !== []) {
            throw new CongeException($erreurs[0]['message'], $erreurs[0]['code']);
        }

        $type = $this->typeCongeModel->find($idType);
        if ($type !== null && (bool) $type->justificatif_requis
            && $type->code !== 'MALADIE'
            && ! (bool) ($donnees['justificatif_depose'] ?? false)) {
            // RG08 : dépôt à la demande (maladie : tolérance 48 h, cf. §2).
            throw new CongeException("Un justificatif est requis pour {$type->code}", CongeException::JUSTIFICATIF_REQUIS);
        }

        $nbJours = $this->calendrier->calculerNbJours($debut, $fin, $demiDebut, $demiFin);
        if ($nbJours <= 0) {
            throw new CongeException('La période ne contient aucun jour ouvré', CongeException::DATES_INVALIDES);
        }

        $idAttenteManager = $this->idStatut('EN_ATTENTE_MANAGER');
        $db = $this->demandeModel->db;
        $db->transStart();

        try {
            $id = (int) $this->demandeModel->insert([
                'id_salarie'            => $idSalarie,
                'id_type_conge'         => $idType,
                'id_statut'             => $idAttenteManager,
                'date_debut'            => $debut,
                'date_fin'              => $fin,
                'demi_journee_debut'    => $demiDebut,
                'demi_journee_fin'      => $demiFin,
                'nb_jours'              => $nbJours,
                'motif'                 => $motif,
                'date_demande'          => date('Y-m-d H:i:s'),
            ], true);

            // Réservation dans la MÊME transaction (RG09/RG10 + verrou).
            $this->soldeService->reserver($idSalarie, $idType, $debut, $fin, $id);

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        $this->audit->journaliser(
            $donnees['id_utilisateur'] ?? null,
            'CREATE',
            'demande_conge',
            $id,
            ['type' => $idType, 'debut' => $debut, 'fin' => $fin, 'nb_jours' => $nbJours]
        );
        $this->notification->notifierManager($id, ['id_salarie' => $idSalarie, 'nb_jours' => $nbJours]);

        return $id;
    }

    public function validerManager(int $idDemande, int $idManager, ?string $commentaire = null): void
    {
        $db = $this->demandeModel->db;
        $db->transStart();

        try {
            $demande = $this->demandeVerrouillee($idDemande, 'EN_ATTENTE_MANAGER');
            $this->verifierPeutValider($idManager, $demande);

            $this->demandeModel->update($idDemande, [
                'id_statut'               => $this->idStatut('EN_ATTENTE_RH'),
                'id_manager_valideur'     => $idManager,
                'date_validation_manager' => date('Y-m-d H:i:s'),
                'commentaire_manager'     => $commentaire,
            ]);

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        $this->audit->journaliser($this->utilisateurDeSalarie($idManager), 'VALIDATION', 'demande_conge', $idDemande, [
            'etape' => 'manager',
        ]);
        $this->notification->notifierSalarie($idDemande, ['etape' => 'manager_valide']);
        $this->notification->notifierRh($idDemande, ['etape' => 'arrivee_rh']);
    }

    public function validerRh(int $idDemande, int $idRh, ?string $commentaire = null): void
    {
        $db = $this->demandeModel->db;
        $db->transStart();

        try {
            $demande = $this->demandeVerrouillee($idDemande, 'EN_ATTENTE_RH');

            $this->demandeModel->update($idDemande, [
                'id_statut'           => $this->idStatut('VALIDEE'),
                'id_rh_valideur'      => $idRh,
                'date_validation_rh'  => date('Y-m-d H:i:s'),
                'commentaire_rh'      => $commentaire,
            ]);

            // Effets de validation : débit du solde + création des absences (RG24).
            $this->soldeService->debiter($idDemande);
            $this->absenceService->genererDepuisDemande($idDemande);

            // RG19 : alerte si le taux de présence du service passe sous le seuil.
            $taux = $this->alertePresence($demande);

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        $this->audit->journaliser($this->utilisateurDeSalarie($idRh), 'VALIDATION', 'demande_conge', $idDemande, [
            'etape' => 'rh',
            'taux_presence_alerte' => $taux,
        ]);
        $this->notification->notifierSalarie($idDemande, ['etape' => 'validee']);
    }

    public function refuser(int $idDemande, int $idValideur, string $motif): void
    {
        if (trim($motif) === '') {
            // RG17 : le motif est obligatoire.
            throw new \RuntimeException('Le motif du refus est obligatoire');
        }

        $db = $this->demandeModel->db;
        $db->transStart();

        try {
            $demande = $this->demandeVerrouillee($idDemande, null, ['EN_ATTENTE_MANAGER', 'EN_ATTENTE_RH']);

            $this->demandeModel->update($idDemande, [
                'id_statut'   => $this->idStatut('REFUSEE'),
                'motif_refus' => $motif,
            ]);

            $this->soldeService->recrediter($idDemande, false);

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        $this->audit->journaliser($this->utilisateurDeSalarie($idValideur), 'REFUS', 'demande_conge', $idDemande, [
            'motif' => $motif,
        ]);
        $this->notification->notifierSalarie($idDemande, ['etape' => 'refusee', 'motif' => $motif]);
    }

    public function annuler(int $idDemande, int $idActeur, ?string $motif = null): void
    {
        $db = $this->demandeModel->db;
        $db->transStart();

        try {
            $demande = $this->demandeVerrouillee(
                $idDemande,
                null,
                ['EN_ATTENTE_MANAGER', 'EN_ATTENTE_RH', 'VALIDEE'],
            );

            $dejaDebite = $this->codeStatut($demande) === 'VALIDEE';

            // RG21 : après validation, uniquement avant la date de début.
            if ($dejaDebite && (string) $demande->date_debut <= date('Y-m-d')) {
                throw new CongeException(
                    'Annulation après le début : retour anticipé via la RH',
                    CongeException::STATUT_INVALIDE
                );
            }

            $this->demandeModel->update($idDemande, [
                'id_statut'       => $this->idStatut('ANNULEE'),
                'date_annulation' => date('Y-m-d H:i:s'),
            ]);

            $this->soldeService->recrediter($idDemande, $dejaDebite);
            $this->absenceService->supprimerPourDemande($idDemande);

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        $this->audit->journaliser($this->utilisateurDeSalarie($idActeur), 'ANNULATION', 'demande_conge', $idDemande, [
            'motif' => $motif,
        ]);
        $this->notification->notifierSalarie($idDemande, ['etape' => 'annulee']);
    }

    public function peutValider(int $idValideur, int $idDemande): bool
    {
        $demande = $this->demandeModel->find($idDemande);
        if ($demande === null) {
            return false;
        }
        // RG16 : jamais sa propre demande.
        if ((int) $demande->id_salarie === $idValideur) {
            return false;
        }

        return $this->estManagerOuDelegue($idValideur, (int) $demande->id_salarie);
    }

    public function trouverValideur(int $idSalarie): int
    {
        // 1) Supérieur direct (rang poste supérieur dans le même service).
        $superieur = $this->trouverSuperieur($idSalarie);
        if ($superieur !== null) {
            return $superieur;
        }

        // 2) Premier utilisateur RH (via employe -> salarie).
        $rh = $this->premierRh();
        if ($rh !== null) {
            return $rh;
        }

        throw new \RuntimeException("Aucun valideur déterminable pour le salarié #{$idSalarie}");
    }

    // -- Lectures (vues SQL lecture seule) -----------------------------------

    /**
     * Historique d'un salarié via v_demande_conge_detail (GET /demandes),
     * filtrable par code de statut et/ou année (année de la date de début).
     */
    public function historique(int $idSalarie, ?string $codeStatut = null, ?int $annee = null): array
    {
        $builder = $this->demandeModel->db->table('v_demande_conge_detail')
            ->where('id_salarie', $idSalarie)
            ->orderBy('date_debut', 'DESC')
            ->orderBy('id_demande_conge', 'DESC');

        if ($codeStatut !== null && $codeStatut !== '') {
            $builder->where('code_statut', $codeStatut);
        }
        if ($annee !== null) {
            $builder->where("EXTRACT(YEAR FROM date_debut)::int", $annee);
        }

        return $builder->get()->getResult();
    }

    /**
     * Demandes à valider selon le rôle (GET /a-valider).
     * - rh/admin : v_demandes_a_valider_rh (EN_ATTENTE_RH)
     * - manager/direction : v_demandes_a_valider_manager filtrées sur le service
     *   du manager courant.
     */
    public function demandesAValider(string $role, int $idSalarieManager): array
    {
        if ($role === 'rh' || $role === 'admin') {
            return $this->demandeModel->db->table('v_demandes_a_valider_rh')
                ->orderBy('date_demande', 'ASC')
                ->get()
                ->getResult();
        }

        $idService = $this->serviceDuSalarie($idSalarieManager);
        $builder = $this->demandeModel->db->table('v_demandes_a_valider_manager')
            ->orderBy('date_demande', 'ASC');

        if ($idService !== null) {
            $builder->where('id_service', $idService);
        }

        return $builder->get()->getResult();
    }

    // -- internals ---------------------------------------------------------

    private function demandeVerrouillee(int $idDemande, ?string $codeStatutAttendu, array $codesAttendus = []): object
    {
        // Verrou pessimiste via SELECT ... FOR UPDATE (4.5) + relecture du statut.
        /** @var object|null $demande */
        $demande = $this->demandeModel->db->query(
            'SELECT * FROM demande_conge WHERE id_demande_conge = ? FOR UPDATE',
            [$idDemande]
        )->getRow();

        if ($demande === null) {
            throw new CongeException("Demande #{$idDemande} introuvable", CongeException::OBJET_INTROUVABLE);
        }

        $code = $this->codeStatut($demande);

        if ($codeStatutAttendu !== null && $code !== $codeStatutAttendu) {
            throw new CongeException(
                "Statut invalide pour l'action (actuel : {$code}, attendu : {$codeStatutAttendu})",
                CongeException::STATUT_INVALIDE
            );
        }

        if ($codeStatutAttendu === null && ! in_array($code, $codesAttendus, true)) {
            throw new CongeException("Statut invalide pour l'action (actuel : {$code})", CongeException::STATUT_INVALIDE);
        }

        return $demande;
    }

    private function verifierPeutValider(int $idValideur, object $demande): void
    {
        $idSalarie = (int) $demande->id_salarie;
        if ($idValideur === $idSalarie) {
            throw new CongeException('Un salarié ne valide jamais sa propre demande', CongeException::NON_AUTORISE);
        }
        if (! $this->estManagerOuDelegue($idValideur, $idSalarie)) {
            throw new CongeException('Validateur non autorisé pour ce salarié', CongeException::NON_AUTORISE);
        }
    }

    private function estManagerOuDelegue(int $idValideur, int $idSalarie): bool
    {
        $delegation = $this->delegationModel->trouverDelegue($idSalarie, date('Y-m-d'));
        if ($delegation !== null && (int) $delegation->id_delegue === $idValideur) {
            return true;
        }

        // Le valideur est le supérieur déterminé par trouverValideur().
        try {
            return $this->trouverValideur($idSalarie) === $idValideur;
        } catch (\Throwable) {
            return false;
        }
    }

    private function trouverSuperieur(int $idSalarie): ?int
    {
        /** @var object|null $monPoste */
        $monPoste = $this->contratModel->db->table('employe')
            ->select('employe.poste_id, poste.rang_id, poste.id_service')
            ->join('poste', 'poste.id_poste = employe.poste_id')
            ->where('employe.id_salarie', $idSalarie)
            ->get()
            ->getRow();

        if ($monPoste === null || $monPoste->id_service === null) {
            return null;
        }

        $superieur = $this->contratModel->db->table('employe')
            ->select('employe.id_salarie')
            ->join('poste', 'poste.id_poste = employe.poste_id')
            ->where('poste.id_service', $monPoste->id_service)
            ->where('poste.rang_id >', $monPoste->rang_id)
            ->where('employe.id_salarie !=', $idSalarie)
            ->orderBy('poste.rang_id', 'ASC')
            ->get()
            ->getRow();

        return $superieur !== null ? (int) $superieur->id_salarie : null;
    }

    private function premierRh(): ?int
    {
        $ligne = $this->contratModel->db->table('utilisateur')
            ->select('employe.id_salarie')
            ->join('employe', 'employe.id = utilisateur.id_employe')
            ->where('utilisateur.actif', true)
            ->whereIn('utilisateur.role', ['rh', 'admin'])
            ->where('employe.id_salarie IS NOT NULL')
            ->orderBy('utilisateur.id_utilisateur', 'ASC')
            ->get()
            ->getRow();

        return $ligne !== null ? (int) $ligne->id_salarie : null;
    }

    private function alertePresence(object $demande): ?float
    {
        // RG19 : alerte (non bloquante) si le service passe sous 50 % de présence.
        $serviceId = $this->serviceDuSalarie((int) $demande->id_salarie);
        if ($serviceId === null) {
            return null;
        }

        $taux = $this->controle->tauxPresenceService((int) $serviceId, (string) $demande->date_debut);

        return $taux < 50.0 ? $taux : null;
    }

    private function serviceDuSalarie(int $idSalarie): ?int
    {
        $ligne = $this->contratModel->db->table('employe')
            ->select('poste.id_service')
            ->join('poste', 'poste.id_poste = employe.poste_id')
            ->where('employe.id_salarie', $idSalarie)
            ->get()
            ->getRow();

        return $ligne !== null && $ligne->id_service !== null ? (int) $ligne->id_service : null;
    }

    private function utilisateurDeSalarie(int $idSalarie): ?int
    {
        $ligne = $this->contratModel->db->table('utilisateur')
            ->select('utilisateur.id_utilisateur')
            ->join('employe', 'employe.id = utilisateur.id_employe')
            ->where('employe.id_salarie', $idSalarie)
            ->get()
            ->getRow();

        return $ligne !== null ? (int) $ligne->id_utilisateur : null;
    }

    private function codeStatut(object $demande): string
    {
        $statut = $this->statutModel->find((int) $demande->id_statut);

        return $statut !== null ? (string) $statut->code : '';
    }

    private function idStatut(string $code): int
    {
        $id = $this->statutModel->idParCode($code);
        if ($id === null) {
            throw new \RuntimeException("Statut de demande inconnu : {$code}");
        }

        return $id;
    }
}
