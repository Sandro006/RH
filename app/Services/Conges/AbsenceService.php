<?php

declare(strict_types=1);

namespace App\Services\Conges;

use App\Exceptions\CongeException;
use App\Models\Conges\AbsenceModel;
use App\Models\Conges\DemandeCongeModel;
use App\Models\Conges\TypeAbsenceModel;
use App\Models\Conges\TypeCongeModel;

/**
 * AbsenceService (RG24→RG26) : planning réel.
 * Mapping type de congé -> type d'absence (planning) :
 *   ANNUEL/SANS_SOLDE/FAMILIAL/FORMATION/RECUP/MATERNITE/PATERNITE => CONGE,
 *   MALADIE => MALADIE.
 */
class AbsenceService
{
    /** @var array<string, string> */
    private const MAP_TYPE_CONGE_VERS_ABSENCE = [
        'ANNUEL' => 'CONGE',
        'MALADIE' => 'MALADIE',
        'MATERNITE' => 'CONGE',
        'PATERNITE' => 'CONGE',
        'SANS_SOLDE' => 'CONGE',
        'FAMILIAL' => 'CONGE',
        'FORMATION' => 'CONGE',
        'RECUP' => 'CONGE',
    ];

    public function __construct(
        private readonly AbsenceModel $absenceModel,
        private readonly TypeAbsenceModel $typeAbsenceModel,
        private readonly TypeCongeModel $typeCongeModel,
        private readonly DemandeCongeModel $demandeModel,
        private readonly CalendrierService $calendrier,
    ) {
    }

    /**
     * Liste des absences (RH) avec libellés joints (type + salarié).
     */
    public function listerAbsences(?int $idSalarie = null, ?string $codeType = null, ?bool $justifiee = null, ?string $debut = null, ?string $fin = null): array
    {
        $builder = $this->absenceModel->db->table('absence')
            ->select('absence.*, type_absence.code AS code_type_absence, type_absence.libelle AS libelle_type_absence, salarie.matricule, salarie.nom, salarie.prenom')
            ->join('type_absence', 'type_absence.id_type_absence = absence.id_type_absence')
            ->join('salarie', 'salarie.id_salarie = absence.id_salarie')
            ->orderBy('absence.date_debut', 'DESC');

        if ($idSalarie !== null) {
            $builder->where('absence.id_salarie', $idSalarie);
        }
        if ($codeType !== null && $codeType !== '') {
            $builder->where('type_absence.code', $codeType);
        }
        if ($justifiee !== null) {
            $builder->where('absence.justifiee', $justifiee);
        }
        if ($debut !== null && $debut !== '') {
            $builder->where('absence.date_fin >=', $debut);
        }
        if ($fin !== null && $fin !== '') {
            $builder->where('absence.date_debut <=', $fin);
        }

        return $builder->get()->getResult();
    }

    /**
     * Génère les lignes d'absence depuis une demande validée (RG24).
     */
    public function genererDepuisDemande(int $idDemande): void
    {
        $demande = $this->demandeModel->find($idDemande);
        if ($demande === null) {
            throw new CongeException("Demande #{$idDemande} introuvable", CongeException::OBJET_INTROUVABLE);
        }

        $typeConge = $this->typeCongeModel->find((int) $demande->id_type_conge);
        $codeAbsence = self::MAP_TYPE_CONGE_VERS_ABSENCE[$typeConge->code ?? ''] ?? 'CONGE';
        $idTypeAbsence = $this->typeAbsenceModel->idParCode($codeAbsence);
        if ($idTypeAbsence === null) {
            throw new \RuntimeException("Type d'absence inconnu : {$codeAbsence}");
        }

        $this->absenceModel->insert([
            'id_salarie'       => (int) $demande->id_salarie,
            'id_type_absence'  => $idTypeAbsence,
            'id_demande_conge' => $idDemande,
            'date_debut'       => (string) $demande->date_debut,
            'date_fin'         => (string) $demande->date_fin,
            'nb_jours'         => (float) $demande->nb_jours,
            'justifiee'        => true,
            'commentaire'      => "Générée depuis la demande #{$idDemande}",
            'id_saisi_par'     => null,
            'date_saisie'      => date('Y-m-d H:i:s'),
        ], true);
    }

    public function supprimerPourDemande(int $idDemande): void
    {
        $this->absenceModel->supprimerPourDemande($idDemande);
    }

    /**
     * Saisie directe (maladie, injustifiée, mission...) par RH/acteur.
     * @return int id de l'absence créée
     */
    public function saisir(array $donnees, int $idSaisiPar): int
    {
        $idSalarie = (int) ($donnees['id_salarie'] ?? 0);
        $codeType = (string) ($donnees['code_type_absence'] ?? 'CONGE');
        $debut = (string) ($donnees['date_debut'] ?? '');
        $fin = (string) ($donnees['date_fin'] ?? '');
        $justifiee = (bool) ($donnees['justifiee'] ?? false);

        if ($idSalarie <= 0 || $debut === '' || $fin === '' || $fin < $debut) {
            throw new CongeException('Salarié et dates valides sont requis', CongeException::DATES_INVALIDES);
        }

        $idType = $this->typeAbsenceModel->idParCode($codeType);
        if ($idType === null) {
            throw new CongeException("Type d'absence inconnu : {$codeType}", CongeException::OBJET_INTROUVABLE);
        }

        $nbJours = $this->calendrier->calculerNbJours($debut, $fin);
        if ($nbJours <= 0) {
            throw new CongeException('La période ne contient aucun jour ouvré', CongeException::DATES_INVALIDES);
        }

        return (int) $this->absenceModel->insert([
            'id_salarie'       => $idSalarie,
            'id_type_absence'  => $idType,
            'id_demande_conge' => isset($donnees['id_demande_conge']) ? (int) $donnees['id_demande_conge'] : null,
            'date_debut'       => $debut,
            'date_fin'         => $fin,
            'nb_jours'         => $nbJours,
            'justifiee'        => $justifiee,
            'commentaire'      => isset($donnees['commentaire']) ? (string) $donnees['commentaire'] : null,
            'id_saisi_par'     => $idSaisiPar,
            'date_saisie'      => date('Y-m-d H:i:s'),
        ], true);
    }

    /**
     * RG26 : maladie non justifiée dans le délai → ABSENCE_INJUSTIFIEE.
     * @return int nombre requalifié
     */
    public function requalifierMaladiesNonJustifiees(): int
    {
        $limite = date('Y-m-d', strtotime('-2 days'));
        $idMaladie = $this->typeAbsenceModel->idParCode('MALADIE');
        $idInjustifiee = $this->typeAbsenceModel->idParCode('ABSENCE_INJUSTIFIEE');

        if ($idMaladie === null || $idInjustifiee === null) {
            return 0;
        }

        $absences = $this->absenceModel->db->table('absence')
            ->where('id_type_absence', $idMaladie)
            ->where('justifiee', false)
            ->where('date_fin <', $limite)
            ->get()
            ->getResult();

        foreach ($absences as $absence) {
            $this->absenceModel->update($absence->id_absence, [
                'id_type_absence' => $idInjustifiee,
                'commentaire'     => trim(((string) ($absence->commentaire ?? '')) . ' | Requalifiée : justificatif manquant (RG26)'),
            ]);
        }

        return count($absences);
    }
}
