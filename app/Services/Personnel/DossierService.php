<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Entities\Personnel\Document;
use App\Entities\Personnel\Salarie;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\ObjetIntrouvableException;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Personnel\DocumentPolicy;
use App\Models\Personnel\AffectationModel;
use App\Models\Personnel\ContratModel;
use App\Models\Personnel\OnboardingModel;
use App\Models\Personnel\SalarieHistoriqueModel;
use App\Models\Personnel\SalarieModel;

/**
 * Lecture agrégée du dossier administratif d'un salarié (Bloc 5).
 *
 * Service strictement en lecture : toutes les écritures passent par
 * SalarieService, ContratService, OnboardingService ou DocumentService.
 * Aucune donnée sensible de stockage (`chemin_fichier`) n'est exposée.
 */
class DossierService
{
    public function __construct(
        private readonly SalarieModel $salarieModel,
        private readonly AffectationModel $affectationModel,
        private readonly ContratModel $contratModel,
        private readonly SalarieHistoriqueModel $historiqueModel,
        private readonly OnboardingModel $onboardingModel,
        private readonly DocumentService $documentService,
        private readonly DocumentPolicy $documentPolicy,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * Dossier complet, prêt à être sérialisé en JSON.
     *
     * @return array<string, mixed>
     */
    public function dossier(int $idSalarie, SessionUtilisateur $u): array
    {
        $salarie = $this->salarieAutorise($idSalarie, $u);
        $contrats = $this->contrats($idSalarie);
        $documents = $this->documentService->lister($idSalarie, $u);

        $this->auditerLecture($idSalarie, $u);

        return [
            'identite'                 => $this->identite($salarie),
            'situationProfessionnelle' => $this->situationProfessionnelle($idSalarie),
            'contrats'                 => $contrats,
            'affectations'             => $this->affectations($idSalarie),
            'remuneration'             => $this->remuneration($contrats),
            'documents'                => $this->documents($idSalarie, $documents),
            'carriere'                 => $this->timelineCarriere($idSalarie, $contrats),
            'alertes'                  => $this->alertesDocumentaires($idSalarie, $documents),
            'onboarding'               => $this->onboarding($idSalarie),
        ];
    }

    /**
     * Alertes documentaires seules (dashboard RH / bandeau du dossier).
     *
     * @return array<string, mixed>
     */
    public function alertes(int $idSalarie, SessionUtilisateur $u): array
    {
        $this->salarieAutorise($idSalarie, $u);

        $documents = $this->documentService->lister($idSalarie, $u);

        $this->auditerLecture($idSalarie, $u, true);

        return $this->alertesDocumentaires($idSalarie, $documents);
    }

    /**
     * Timeline de carrière seule.
     *
     * @return list<array<string, mixed>>
     */
    public function carriere(int $idSalarie, SessionUtilisateur $u): array
    {
        $this->salarieAutorise($idSalarie, $u);

        $this->auditerLecture($idSalarie, $u, true);

        return $this->timelineCarriere($idSalarie, $this->contrats($idSalarie));
    }

    // ------------------------------------------------------------------
    // Sections
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function identite(Salarie $salarie): array
    {
        return [
            'id_salarie'          => (int) $salarie->id_salarie,
            'matricule'           => $salarie->matricule,
            'nom'                 => $salarie->nom,
            'prenom'              => $salarie->prenom,
            'nom_complet'         => trim($salarie->prenom . ' ' . $salarie->nom),
            'email'               => $salarie->email,
            'telephone'           => $salarie->telephone,
            'date_naissance'      => $this->date($salarie->date_naissance),
            'lieu_naissance'      => $salarie->lieu_naissance,
            'nationalite'         => $salarie->nationalite,
            'situation_familiale' => $salarie->situation_familiale,
            'adresse'             => $salarie->adresse,
            'ville'               => $salarie->ville,
            'pays'                => $salarie->pays,
            'date_embauche'       => $this->date($salarie->date_embauche),
            'date_sortie'         => $this->date($salarie->date_sortie),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function situationProfessionnelle(int $idSalarie): array
    {
        $ligne = $this->salarieModel->db->table('salarie s')
            ->select(
                's.statut, ss.designation AS statut_designation, '
                . 'p.id_poste, p.intitule AS poste, '
                . 'sv.id_service, sv.nom AS service, '
                . 'd.id_departement, d.nom AS departement, '
                . 'a.date_debut AS date_debut_poste'
            )
            ->join('status_salarie ss', 'ss.id = s.statut', 'left')
            ->join('affectation a', 'a.id_salarie = s.id_salarie AND a.date_fin IS NULL', 'left')
            ->join('poste p', 'p.id_poste = a.id_poste', 'left')
            ->join('service sv', 'sv.id_service = p.id_service', 'left')
            ->join('departement d', 'd.id_departement = sv.id_departement', 'left')
            ->where('s.id_salarie', $idSalarie)
            ->get()
            ->getRowArray();

        if ($ligne === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        $salarie = $this->salarieModel->find($idSalarie);

        return [
            'statut'              => $ligne['statut'] !== null ? (int) $ligne['statut'] : null,
            'statut_designation'  => $ligne['statut_designation'],
            'poste'               => $ligne['poste'],
            'id_poste'            => $ligne['id_poste'] !== null ? (int) $ligne['id_poste'] : null,
            'service'             => $ligne['service'],
            'departement'         => $ligne['departement'],
            'id_departement'      => $ligne['id_departement'] !== null ? (int) $ligne['id_departement'] : null,
            'manager'             => null,
            'date_debut_poste'    => $ligne['date_debut_poste'],
            'anciennete'          => $this->anciennete(
                $salarie?->date_embauche,
                $salarie?->date_sortie
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contrats(int $idSalarie): array
    {
        $lignes = $this->contratModel->db->table('contrat c')
            ->select(
                'c.id_contrat, c.id_salarie, c.id_poste, c.type_contrat_id, '
                . 'c.date_debut, c.date_fin, c.salaire_base, '
                . 'c.periode_essai_debut, c.periode_essai_fin, '
                . 'c.date_signature, c.statut, '
                . 'sc.designation AS statut_designation, '
                . 'tc.designation AS type_contrat_designation, '
                . 'p.intitule AS poste'
            )
            ->join('status_contrat sc', 'sc.id = c.statut', 'left')
            ->join('type_contrat tc', 'tc.id = c.type_contrat_id', 'left')
            ->join('poste p', 'p.id_poste = c.id_poste', 'left')
            ->where('c.id_salarie', $idSalarie)
            ->orderBy('c.date_debut', 'DESC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $ligne): array => [
            'id_contrat'               => (int) $ligne['id_contrat'],
            'type_contrat_id'          => $ligne['type_contrat_id'] !== null ? (int) $ligne['type_contrat_id'] : null,
            'type_contrat'             => $ligne['type_contrat_designation'],
            'poste'                    => $ligne['poste'],
            'date_debut'               => $ligne['date_debut'],
            'date_fin'                 => $ligne['date_fin'],
            'salaire_base'             => $ligne['salaire_base'] !== null ? (float) $ligne['salaire_base'] : null,
            'periode_essai_debut'      => $ligne['periode_essai_debut'],
            'periode_essai_fin'        => $ligne['periode_essai_fin'],
            'date_signature'           => $ligne['date_signature'],
            'statut'                   => $ligne['statut'] !== null ? (int) $ligne['statut'] : null,
            'statut_designation'       => $ligne['statut_designation'],
            'est_en_cours'             => (int) $ligne['statut'] === 4,
        ], $lignes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function affectations(int $idSalarie): array
    {
        return array_map(static fn (array $ligne): array => [
            'id_affectation' => (int) $ligne['id_affectation'],
            'id_poste'       => (int) $ligne['id_poste'],
            'poste'          => $ligne['poste'] ?? null,
            'departement'    => $ligne['departement'] ?? null,
            'date_debut'     => $ligne['date_debut'],
            'date_fin'       => $ligne['date_fin'],
            'motif'          => $ligne['motif'],
            'en_cours'       => $ligne['date_fin'] === null,
        ], $this->affectationModel->listerParSalarie($idSalarie));
    }

    /**
     * @param list<array<string, mixed>> $contrats
     *
     * @return array<string, mixed>
     */
    private function remuneration(array $contrats): array
    {
        $courant = null;

        foreach ($contrats as $contrat) {
            if ($contrat['est_en_cours']) {
                $courant = $contrat;
                break;
            }
        }

        if ($courant === null) {
            foreach ($contrats as $contrat) {
                if ($contrat['salaire_base'] !== null) {
                    $courant = $contrat;
                    break;
                }
            }
        }

        $historique = array_values(array_filter(
            array_map(static fn (array $contrat): ?array => $contrat['salaire_base'] === null
                ? null
                : [
                    'id_contrat'           => $contrat['id_contrat'],
                    'date_effet'           => $contrat['date_debut'],
                    'salaire_base'         => $contrat['salaire_base'],
                    'type_contrat'         => $contrat['type_contrat'],
                    'statut_designation'   => $contrat['statut_designation'],
                ],
            $contrats)
        ));

        return [
            'salaire_courant' => $courant['salaire_base'] ?? null,
            'contrat_courant' => $courant['id_contrat'] ?? null,
            'date_effet'      => $courant['date_debut'] ?? null,
            'devise'          => null,
            'historique'      => $historique,
        ];
    }

    /**
     * @param list<Document> $documents
     *
     * @return array<string, mixed>
     */
    private function documents(int $idSalarie, array $documents): array
    {
        return [
            'pieces'       => array_map(
                static fn (Document $document): array => $document->toPublicArray(),
                $documents
            ),
            'completude'   => $this->documentService->tauxCompletude($idSalarie),
            'manquants'    => array_map(
                static fn (object $type): array => [
                    'id'          => (int) $type->id,
                    'designation' => $type->designation,
                ],
                $this->documentService->piecesManquantes($idSalarie)
            ),
        ];
    }

    /**
     * @param list<Document> $documents
     *
     * @return array<string, mixed>
     */
    private function alertesDocumentaires(int $idSalarie, array $documents): array
    {
        $expires = [];
        $expirant = [];

        foreach ($documents as $document) {
            $statut = $document->statutValidite();

            if ($statut === 'expire') {
                $expires[] = $document->toPublicArray();
            } elseif ($statut === 'expire_bientot') {
                $expirant[] = $document->toPublicArray();
            }
        }

        $manquants = array_map(
            static fn (object $type): array => [
                'id'          => (int) $type->id,
                'designation' => $type->designation,
            ],
            $this->documentService->piecesManquantes($idSalarie)
        );

        return [
            'documents_expires'         => $expires,
            'documents_expirant_bientot' => $expirant,
            'pieces_manquantes'         => $manquants,
            'nombre_expires'            => count($expires),
            'nombre_expirant_bientot'   => count($expirant),
            'nombre_manquants'          => count($manquants),
            'total'                     => count($expires) + count($expirant) + count($manquants),
        ];
    }

    /**
     * Fusion chronologique contrats + affectations + changements de statut.
     *
     * @param list<array<string, mixed>> $contrats
     *
     * @return list<array<string, mixed>>
     */
    private function timelineCarriere(int $idSalarie, array $contrats): array
    {
        $evenements = [];

        foreach ($contrats as $contrat) {
            $evenements[] = [
                'type'     => 'contrat',
                'date'     => $contrat['date_debut'],
                'libelle'  => trim(sprintf(
                    'Contrat %s%s',
                    $contrat['type_contrat'] ?? '',
                    $contrat['statut_designation'] !== null ? ' — ' . $contrat['statut_designation'] : ''
                )),
                'details'  => [
                    'id_contrat' => $contrat['id_contrat'],
                    'poste'      => $contrat['poste'],
                    'date_fin'   => $contrat['date_fin'],
                ],
            ];
        }

        foreach ($this->affectationModel->listerParSalarie($idSalarie) as $affectation) {
            $evenements[] = [
                'type'    => 'affectation',
                'date'    => $affectation['date_debut'],
                'libelle' => 'Affectation — ' . ($affectation['poste'] ?? 'Poste #' . $affectation['id_poste']),
                'details' => [
                    'id_affectation' => (int) $affectation['id_affectation'],
                    'departement'    => $affectation['departement'] ?? null,
                    'date_fin'       => $affectation['date_fin'],
                    'motif'          => $affectation['motif'],
                ],
            ];
        }

        foreach ($this->statuts($idSalarie) as $statut) {
            $evenements[] = [
                'type'    => 'statut',
                'date'    => $statut['date_changement'],
                'libelle' => trim(sprintf(
                    'Statut : %s → %s',
                    $statut['statut_ancien_designation'] ?? '—',
                    $statut['statut_nouveau_designation'] ?? '—'
                )),
                'details' => [
                    'id_historique' => (int) $statut['id_historique'],
                    'commentaire'   => $statut['commentaire'],
                ],
            ];
        }

        usort($evenements, static function (array $a, array $b): int {
            return strcmp((string) $b['date'], (string) $a['date']);
        });

        return $evenements;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function onboarding(int $idSalarie): ?array
    {
        $onboarding = $this->onboardingModel->trouverParSalarie($idSalarie);

        if ($onboarding === null) {
            return null;
        }

        $ligne = $this->onboardingModel->db->table('onboarding o')
            ->select('o.id_onboarding, o.statut, o.date_debut, o.date_fin, so.designation AS statut_designation')
            ->join('status_onboarding so', 'so.id = o.statut', 'left')
            ->where('o.id_onboarding', (int) $onboarding->id_onboarding)
            ->get()
            ->getRowArray();

        $total = $this->db()->table('onboarding_tache')
            ->where('id_onboarding', (int) $onboarding->id_onboarding)
            ->countAllResults();

        $terminees = 0;

        if ($total > 0) {
            $statuts = $this->db()->table('status_onboarding_tache')
                ->select('id')
                ->whereIn('designation', ['Terminée', 'Annulée'])
                ->get()
                ->getResultArray();

            if ($statuts !== []) {
                $terminees = $this->db()->table('onboarding_tache')
                    ->where('id_onboarding', (int) $onboarding->id_onboarding)
                    ->whereIn('statut', array_map(static fn (array $row): int => (int) $row['id'], $statuts))
                    ->countAllResults();
            }
        }

        return [
            'id_onboarding'     => (int) $onboarding->id_onboarding,
            'statut'            => $ligne['statut'] !== null ? (int) $ligne['statut'] : null,
            'statut_designation' => $ligne['statut_designation'] ?? null,
            'date_debut'        => $ligne['date_debut'] ?? null,
            'date_fin'          => $ligne['date_fin'] ?? null,
            'taches_total'      => $total,
            'taches_terminees'  => $terminees,
            'progression'       => $total === 0 ? 0.0 : round($terminees / $total * 100, 2),
        ];
    }

    // ------------------------------------------------------------------
    // Accès et audit
    // ------------------------------------------------------------------

    private function salarieAutorise(int $idSalarie, SessionUtilisateur $u): Salarie
    {
        /** @var Salarie|null $salarie */
        $salarie = $this->salarieModel->find($idSalarie);

        if ($salarie === null) {
            throw new ObjetIntrouvableException("Salarié #{$idSalarie} introuvable");
        }

        if ($u->role === 'salarie' && $this->documentPolicy->idSalarieDe($u) !== $idSalarie) {
            throw new AccesRefuseException('Un salarié ne peut consulter que son propre dossier');
        }

        return $salarie;
    }

    private function auditerLecture(int $idSalarie, SessionUtilisateur $u, bool $partiel = false): void
    {
        if ($this->documentPolicy->idSalarieDe($u) === $idSalarie) {
            return;
        }

        $this->auditService->journaliser(
            $u->idUtilisateur,
            'READ',
            'dossier',
            $idSalarie,
            $partiel ? ['section' => 'partielle'] : ['section' => 'complete'],
            $u->adresseIp
        );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function statuts(int $idSalarie): array
    {
        return $this->historiqueModel->db->table('salarie_status_historique h')
            ->select(
                'h.id_historique, h.statut_ancien, h.statut_nouveau, h.date_changement, h.commentaire, '
                . 'sa.designation AS statut_ancien_designation, '
                . 'sn.designation AS statut_nouveau_designation'
            )
            ->join('status_salarie sa', 'sa.id = h.statut_ancien', 'left')
            ->join('status_salarie sn', 'sn.id = h.statut_nouveau', 'left')
            ->where('h.id_salarie', $idSalarie)
            ->orderBy('h.date_changement', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{annees: int, mois: int, jours: int, libelle: string}|null
     */
    private function anciennete(mixed $dateEmbauche, mixed $dateSortie): ?array
    {
        $debut = $this->date($dateEmbauche);

        if ($debut === null) {
            return null;
        }

        $fin = $this->date($dateSortie) ?? date('Y-m-d');
        $depuis = \DateTimeImmutable::createFromFormat('!Y-m-d', $debut);
        $jusqua = \DateTimeImmutable::createFromFormat('!Y-m-d', $fin);

        if ($depuis === false || $jusqua === false || $jusqua < $depuis) {
            return null;
        }

        $intervalle = $depuis->diff($jusqua);

        return [
            'annees'  => $intervalle->y,
            'mois'    => $intervalle->m,
            'jours'   => $intervalle->d,
            'libelle' => sprintf('%d an(s) %d mois', $intervalle->y, $intervalle->m),
        ];
    }

    private function date(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        if ($valeur instanceof \DateTimeInterface) {
            return $valeur->format('Y-m-d');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $valeur, 0, 10));

        return $date !== false ? $date->format('Y-m-d') : null;
    }

    private function db(): \CodeIgniter\Database\BaseConnection
    {
        return $this->salarieModel->db;
    }
}
