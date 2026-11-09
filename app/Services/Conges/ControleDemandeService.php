<?php

declare(strict_types=1);

namespace App\Services\Conges;

use App\Exceptions\CongeException;
use App\Models\Conges\AbsenceModel;
use App\Models\Conges\DemandeCongeModel;
use App\Models\Conges\DroitCongeModel;
use App\Models\Conges\TypeCongeModel;
use App\Models\Personnel\ContratModel;
use App\Models\Personnel\SalarieModel;

/**
 * Contrôles RG01→RG08 (hors solde, qui reste à SoldeService/CongeService).
 * verifier() retourne une liste d'erreurs [code, message] ; vide = OK.
 * RG08 (justificatif) n'est pas contrôlé ici : la signature TODO n'a pas de
 * pièce jointe ; JustificatifService + CongeService l'appliquent (dépôt à la
 * demande, ou sous 48 h pour MALADIE).
 */
class ControleDemandeService
{
    public function __construct(
        private readonly ContratModel $contratModel,
        private readonly SalarieModel $salarieModel,
        private readonly TypeCongeModel $typeCongeModel,
        private readonly DroitCongeModel $droitCongeModel,
        private readonly DemandeCongeModel $demandeCongeModel,
        private readonly AbsenceModel $absenceModel,
        private readonly CalendrierService $calendrier,
    ) {
    }

    /**
     * @return list<array{code: string, message: string}>
     */
    public function verifier(int $idSalarie, int $idType, string $debut, string $fin, bool $demiDebut, bool $demiFin): array
    {
        $erreurs = [];

        $type = $this->typeCongeModel->find($idType);
        if ($type === null) {
            return [['code' => CongeException::OBJET_INTROUVABLE, 'message' => "Type de congé #{$idType} introuvable"]];
        }
        if (! (bool) $type->actif) {
            $erreurs[] = ['code' => CongeException::TYPE_INACTIF, 'message' => "Le type {$type->code} est inactif"];
        }

        // RG02 : cohérence des dates.
        if ($fin < $debut) {
            $erreurs[] = ['code' => CongeException::DATES_INVALIDES, 'message' => 'La date de fin ne peut pas précéder la date de début'];
        }

        // RG02 : demi-journée autorisée par le type (et jamais les deux ensemble).
        if (($demiDebut || $demiFin) && ! (bool) $type->demi_journee_autorisee) {
            $erreurs[] = ['code' => CongeException::DEMI_JOURNEE_NON_AUTORISEE, 'message' => "La demi-journée n'est pas autorisée pour {$type->code}"];
        }
        if ($demiDebut && $demiFin) {
            $erreurs[] = ['code' => CongeException::DEMI_JOURNEE_NON_AUTORISEE, 'message' => 'Une demande ne peut pas avoir deux demi-journées'];
        }

        // RG01 : contrat actif à la date de début.
        if (! $this->contratActif($idSalarie, $debut)) {
            $erreurs[] = ['code' => CongeException::CONTRAT_INACTIF, 'message' => 'Aucun contrat actif à la date de début'];
        }

        // RG06 : durée maximale (nécessite nb_jours).
        $nbJours = $this->calendrier->calculerNbJours($debut, $fin, $demiDebut, $demiFin);
        if (! $this->dureeMaxRespectee($idType, $nbJours)) {
            $erreurs[] = ['code' => CongeException::DUREE_MAX, 'message' => "La durée maximale de {$type->code} est dépassée"];
        }

        // RG05 : chevauchement demandes actives + absences.
        if ($this->aChevauchement($idSalarie, $debut, $fin)) {
            $erreurs[] = ['code' => CongeException::CHEVAUCHEMENT, 'message' => 'Chevauchement avec une demande ou une absence existante'];
        }

        // RG04 : délai de prévenance.
        if (! $this->delaiPrevenanceRespecte($idType, $debut)) {
            $erreurs[] = ['code' => CongeException::DELAI_NON_RESPECTE, 'message' => "Le délai de prévenance de {$type->code} n'est pas respecté"];
        }

        // RG07 : ancienneté.
        if (! $this->ancienneteSuffisante($idSalarie, $idType)) {
            $erreurs[] = ['code' => CongeException::ANCIENNETE, 'message' => 'Ancienneté minimale du droit non atteinte'];
        }

        return $erreurs;
    }

    public function contratActif(int $idSalarie, string $date): bool
    {
        /** @var object|null $contrat */
        $contrat = $this->contratModel
            ->where('id_salarie', $idSalarie)
            ->where('date_debut <=', $date)
            ->groupStart()
                ->where('date_fin', null)
                ->orWhere('date_fin >=', $date)
            ->groupEnd()
            ->first();

        return $contrat !== null;
    }

    public function delaiPrevenanceRespecte(int $idType, string $debut): bool
    {
        $type = $this->typeCongeModel->find($idType);
        if ($type === null) {
            return false;
        }

        $delai = (int) $type->delai_prevenance_jours;
        if ($delai <= 0) {
            return true;
        }

        $aujourdhui = new \DateTimeImmutable(date('Y-m-d'));
        $debutDate = new \DateTimeImmutable($debut);
        $ecart = (int) $aujourdhui->diff($debutDate)->format('%a');

        // Une date passée consomme le délai ; le futur doit laisser `delai` jours.
        if ($debutDate < $aujourdhui) {
            return false;
        }

        return $ecart >= $delai;
    }

    public function aChevauchement(int $idSalarie, string $debut, string $fin, ?int $exclureDemande = null): bool
    {
        $demandes = $this->demandeCongeModel->chercherChevauchantes($idSalarie, $debut, $fin, $exclureDemande);
        if ($demandes !== []) {
            return true;
        }

        $absences = $this->absenceModel->chercherChevauchantes($idSalarie, $debut, $fin);

        return $absences !== [];
    }

    public function ancienneteSuffisante(int $idSalarie, int $idType): bool
    {
        $salarie = $this->salarieModel->find($idSalarie);
        if ($salarie === null || $salarie->date_embauche === null) {
            // Sans embauche connue, on exige l'ancienneté minimale si le droit la définit.
            $droit = $this->droitCongeModel->droitActif($idType, null, date('Y-m-d'));
            return $droit === null || (int) $droit->anciennete_min_mois === 0;
        }

        $droit = $this->droitCongeModel->droitActif($idType, null, date('Y-m-d'));
        if ($droit === null) {
            return true;
        }

        $minimum = (int) $droit->anciennete_min_mois;
        if ($minimum <= 0) {
            return true;
        }

        $embauche = new \DateTimeImmutable((string) $salarie->date_embauche);
        $aujourdhui = new \DateTimeImmutable(date('Y-m-d'));
        $mois = ((int) $aujourdhui->format('Y') - (int) $embauche->format('Y')) * 12
            + ((int) $aujourdhui->format('m') - (int) $embauche->format('m'))
            - ((int) $aujourdhui->format('d') < (int) $embauche->format('d') ? 1 : 0);

        return $mois >= $minimum;
    }

    public function dureeMaxRespectee(int $idType, float $nbJours): bool
    {
        $type = $this->typeCongeModel->find($idType);
        if ($type === null || $type->duree_max_jours === null) {
            return true;
        }

        return $nbJours <= (float) $type->duree_max_jours;
    }

    /**
     * Taux de présence dans un service à une date (0..100).
     * Lit la vue v_taux_presence_service ; défaut 100 si aucune donnée.
     */
    public function tauxPresenceService(int $idService, string $date): float
    {
        $ligne = $this->contratModel->db->table('v_taux_presence_service')
            ->where('id_service', $idService)
            ->where('jour', $date)
            ->get()
            ->getRow();

        return $ligne !== null ? (float) $ligne->taux_presence_pct : 100.0;
    }
}
