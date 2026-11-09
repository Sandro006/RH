<?php

declare(strict_types=1);

namespace App\Controllers\Personnel;

use App\Controllers\ApiController;
use App\Exceptions\CongeException;
use App\Libraries\Common\Roles;
use App\Libraries\Common\SessionUtilisateur;
use App\Models\Admin\EmployeModel;
use App\Models\Conges\JourFerieModel;
use App\Models\Conges\TypeCongeModel;
use App\Services\Conges\AbsenceService;
use App\Services\Conges\CongeService;
use App\Services\Conges\PaieCongeService;
use App\Services\Conges\SoldeService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * API Congés — Bloc 7 (voir TODO §7).
 * Tous les contrôleurs « congés » passent par ApiController::executer() :
 * CongeException est mappée en { error: CODE_METIER, message } + HTTP
 * (voir STATUT_HTTP dans ApiController, ex. SOLDE_INSUFFISANT → 422).
 */
class CongeController extends ApiController
{
    private CongeService $congeService;
    private SoldeService $soldeService;
    private PaieCongeService $paieService;
    private AbsenceService $absenceService;
    private TypeCongeModel $typeCongeModel;
    private JourFerieModel $jourFerieModel;
    private EmployeModel $employeModel;

    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger,
    ): void {
        parent::initController($request, $response, $logger);

        $this->congeService = service('congeWorkflowService');
        $this->soldeService = service('soldeCongeService');
        $this->paieService = service('paieCongeService');
        $this->absenceService = service('absenceCongeService');
        $this->typeCongeModel = model(TypeCongeModel::class);
        $this->jourFerieModel = model(JourFerieModel::class);
        $this->employeModel = model(EmployeModel::class);
    }

    // GET /api/conges/soldes/tous (RH) — vue + filtres
    public function soldesTous()
    {
        return $this->executer(function () {
            $anneeQ = $this->request->getGet('annee');
            $annee = $anneeQ !== null && $anneeQ !== '' ? (int) $anneeQ : null;
            $recherche = $this->request->getGet('recherche');
            $idType = $this->request->getGet('id_type_conge');
            $idType = $idType !== null && $idType !== '' ? (int) $idType : null;

            return $this->soldeService->listerTousSoldes(
                $annee,
                $recherche !== null ? (string) $recherche : null,
                $idType,
            );
        });
    }

    // GET /api/conges/alertes (RH) — v_alertes_solde
    public function alertes()
    {
        return $this->executer(fn () => $this->soldeService->listerAlertes());
    }

    // POST /api/conges/soldes/ajuster (RH)
    public function ajuster()
    {
        return $this->executer(function () {
            $u = SessionUtilisateur::courante();
            $body = $this->corps();

            $idSalarie = (int) ($body['id_salarie'] ?? 0);
            $idType = $this->resoudreIdType($body);
            $annee = (int) ($body['annee'] ?? date('Y'));
            $nbJours = isset($body['nb_jours']) ? (float) $body['nb_jours'] : 0.0;
            $commentaire = (string) ($body['commentaire'] ?? '');

            if ($idSalarie <= 0) {
                return $this->erreur('DONNEES_INCOHERENTES', 'id_salarie requis', 400);
            }
            if (abs($nbJours) < 0.0001) {
                return $this->erreur('DONNEES_INCOHERENTES', 'nb_jours différent de 0 requis', 400);
            }

            $this->soldeService->ajuster(
                $idSalarie,
                $idType,
                $annee,
                $nbJours,
                $commentaire,
                $u !== null ? $u->idUtilisateur : 0,
            );

            return ['id_salarie' => $idSalarie, 'message' => 'Solde ajusté'];
        });
    }

    // POST /api/conges/cloturer-annee (RH)
    public function cloturerAnnee()
    {
        return $this->executer(function () {
            $body = $this->corps();
            $annee = isset($body['annee']) ? (int) $body['annee'] : (int) date('Y');

            $this->soldeService->cloturerAnnee($annee);

            return ['annee' => $annee, 'message' => 'Clôture terminée'];
        });
    }

    // GET /api/conges/types
    public function types()
    {
        return $this->executer(fn () => $this->typeCongeModel->listerActifs());
    }

    // GET /api/conges/soldes
    public function soldes()
    {
        return $this->executer(function () {
            $salarie = $this->idSalarieCourant();
            $anneeQ = $this->request->getGet('annee');
            $annee = $anneeQ !== null && $anneeQ !== '' ? (int) $anneeQ : (int) date('Y');

            return [
                'id_salarie' => $salarie,
                'annee' => $annee,
                'soldes' => $this->soldeService->listerSoldes($salarie, $annee),
                'solde_tout_compte' => $this->soldeService->soldeToutCompte($salarie),
            ];
        });
    }

    // GET /api/conges/demandes (filtres statut, annee)
    public function historique()
    {
        return $this->executer(function () {
            $salarie = $this->idSalarieCourant();
            $codeStatut = $this->request->getGet('statut');
            $anneeQ = $this->request->getGet('annee');
            $annee = $anneeQ !== null && $anneeQ !== '' ? (int) $anneeQ : null;

            return $this->congeService->historique(
                $salarie,
                $codeStatut !== null && $codeStatut !== '' ? (string) $codeStatut : null,
                $annee,
            );
        });
    }

    // POST /api/conges/demandes
    public function creer()
    {
        return $this->executer(function () {
            $salarie = $this->idSalarieCourant();
            $u = SessionUtilisateur::courante();

            $body = $this->corps();
            $donnees = [
                'id_type_conge' => $this->resoudreIdType($body),
                'date_debut' => (string) ($body['date_debut'] ?? ''),
                'date_fin' => (string) ($body['date_fin'] ?? ''),
                'demi_journee_debut' => (bool) ($body['demi_journee_debut'] ?? false),
                'demi_journee_fin' => (bool) ($body['demi_journee_fin'] ?? false),
                'motif' => (string) ($body['motif'] ?? ''),
                'justificatif_depose' => (bool) ($body['justificatif_depose'] ?? false),
                'id_utilisateur' => $u !== null ? $u->idUtilisateur : null,
            ];

            // RG18 : la RH peut saisir un congé à la place d'un salarié.
            if ($u !== null && $u->aLeRole([Roles::RH, Roles::ADMIN]) && isset($body['id_salarie']) && (int) $body['id_salarie'] > 0) {
                $salarie = (int) $body['id_salarie'];
            }

            $id = $this->congeService->creerDemande($donnees, $salarie);

            return ['id_demande_conge' => $id, 'message' => 'Demande créée'];
        }, 201);
    }

    // POST /api/conges/demandes/{id}/annuler
    public function annuler(int $id)
    {
        return $this->executer(function () use ($id) {
            $salarie = $this->idSalarieCourant();
            $body = $this->corps();

            $this->congeService->annuler($id, $salarie, isset($body['motif']) ? (string) $body['motif'] : null);

            return ['id_demande_conge' => $id, 'message' => 'Demande annulée'];
        });
    }

    // POST /api/conges/demandes/{id}/valider (manager ou RH selon le statut courant)
    public function valider(int $id)
    {
        return $this->executer(function () use ($id) {
            $valideur = $this->idSalarieCourant();
            $body = $this->corps();
            $commentaire = isset($body['commentaire']) ? (string) $body['commentaire'] : null;
            $niveau = isset($body['niveau']) ? strtolower((string) $body['niveau']) : null;

            if ($niveau === 'rh') {
                $this->congeService->validerRh($id, $valideur, $commentaire);
            } elseif ($niveau === 'manager') {
                $this->congeService->validerManager($id, $valideur, $commentaire);
            } else {
                // Aiguillage par le statut courant (vu dans le Service via le verrou).
                $statut = $this->statutCourant($id);
                if ($statut === 'EN_ATTENTE_RH') {
                    $this->congeService->validerRh($id, $valideur, $commentaire);
                } elseif ($statut === 'EN_ATTENTE_MANAGER') {
                    $this->congeService->validerManager($id, $valideur, $commentaire);
                } else {
                    throw new CongeException(
                        "Statut invalide pour la validation (actuel : {$statut})",
                        CongeException::STATUT_INVALIDE,
                    );
                }
            }

            return ['id_demande_conge' => $id, 'message' => 'Validation enregistrée'];
        });
    }

    // POST /api/conges/demandes/{id}/refuser (motif obligatoire, RG17)
    public function refuser(int $id)
    {
        return $this->executer(function () use ($id) {
            $valideur = $this->idSalarieCourant();
            $body = $this->corps();
            $motif = trim((string) ($body['motif'] ?? ''));

            if ($motif === '') {
                return $this->erreur('DONNEES_INCOHERENTES', 'Le motif du refus est obligatoire', 400);
            }

            $this->congeService->refuser($id, $valideur, $motif);

            return ['id_demande_conge' => $id, 'message' => 'Demande refusée'];
        });
    }

    // GET /api/conges/a-valider (manager ou RH selon le rôle)
    public function aValider()
    {
        return $this->executer(function () {
            $u = SessionUtilisateur::courante();
            $manager = $this->idSalarieCourant();

            return $this->congeService->demandesAValider($u !== null ? $u->role : '', $manager);
        });
    }

    // GET /api/conges/planning?id_service=&debut=&fin=
    public function planning()
    {
        return $this->executer(function () {
            $idService = $this->request->getGet('id_service');
            if ($idService === null || $idService === '') {
                $idService = $this->serviceCourant();
            }
            if ($idService === null) {
                return $this->erreur('DONNEES_INCOHERENTES', 'id_service requis (ou employé sans poste rattaché)', 400);
            }

            $debut = (string) ($this->request->getGet('debut') ?: date('Y-m-01'));
            $fin = (string) ($this->request->getGet('fin') ?: date('Y-m-t'));

            return $this->paieService->planningEquipe((int) $idService, $debut, $fin);
        });
    }

    // GET /api/conges/jours-feries?debut=&fin= — fériés d'une plage (affichage planning)
    public function joursFeries()
    {
        return $this->executer(function () {
            $debut = (string) ($this->request->getGet('debut') ?: date('Y-m-01'));
            $fin = (string) ($this->request->getGet('fin') ?: date('Y-m-t'));

            if ($fin < $debut) {
                return $this->erreur('DONNEES_INCOHERENTES', 'fin doit être postérieure à debut', 400);
            }

            return $this->jourFerieModel->listerEntreAvecRecurrents($debut, $fin);
        });
    }

    // GET /api/conges/absences (RH) — liste filtrable
    public function absences()
    {
        return $this->executer(function () {
            $idSalarie = $this->request->getGet('id_salarie');
            $codeType = $this->request->getGet('code_type_absence');
            $justifieeQ = $this->request->getGet('justifiee');
            $debut = $this->request->getGet('debut');
            $fin = $this->request->getGet('fin');

            return $this->absenceService->listerAbsences(
                $idSalarie !== null && $idSalarie !== '' ? (int) $idSalarie : null,
                $codeType !== null ? (string) $codeType : null,
                $justifieeQ !== null && $justifieeQ !== '' ? ((int) $justifieeQ === 1) : null,
                $debut !== null ? (string) $debut : null,
                $fin !== null ? (string) $fin : null,
            );
        });
    }

    // POST /api/conges/absences (role rh,admin — voir Routes)
    public function saisirAbsence()
    {
        return $this->executer(function () {
            $u = SessionUtilisateur::courante();
            $body = $this->corps();

            $id = $this->absenceService->saisir([
                'id_salarie' => (int) ($body['id_salarie'] ?? 0),
                'code_type_absence' => (string) ($body['code_type_absence'] ?? 'CONGE'),
                'date_debut' => (string) ($body['date_debut'] ?? ''),
                'date_fin' => (string) ($body['date_fin'] ?? ''),
                'justifiee' => (bool) ($body['justifiee'] ?? false),
                'commentaire' => isset($body['commentaire']) ? (string) $body['commentaire'] : null,
                'id_demande_conge' => isset($body['id_demande_conge']) ? (int) $body['id_demande_conge'] : null,
            ], $u !== null ? $u->idUtilisateur : 0);

            return ['id_absence' => $id, 'message' => 'Absence saisie'];
        }, 201);
    }

    // -- résolution de l'identité métier -----------------------------------

    /**
     * Le compte authentifié → son salarié (utilisateur -> employe -> salarie).
     * Levée CONGE 404 si le compte n'est lié à aucun salarié.
     */
    private function idSalarieCourant(): int
    {
        $u = SessionUtilisateur::courante();
        if ($u === null || $u->idEmploye === null) {
            throw new CongeException('Compte non lié à un employé', CongeException::OBJET_INTROUVABLE);
        }

        $employe = $this->employeModel->find($u->idEmploye);
        if ($employe === null || $employe->id_salarie === null) {
            throw new CongeException('Aucun salarié lié à ce compte', CongeException::OBJET_INTROUVABLE);
        }

        return (int) $employe->id_salarie;
    }

    private function serviceCourant(): ?int
    {
        $u = SessionUtilisateur::courante();
        if ($u === null || $u->idEmploye === null) {
            return null;
        }

        $ligne = $this->employeModel->db->table('employe')
            ->select('poste.id_service')
            ->join('poste', 'poste.id_poste = employe.poste_id')
            ->where('employe.id', $u->idEmploye)
            ->get()
            ->getRow();

        return $ligne !== null && $ligne->id_service !== null ? (int) $ligne->id_service : null;
    }

    private function statutCourant(int $idDemande): string
    {
        $demande = $this->businessDb()->table('demande_conge')
            ->select('statut_demande_conge.code')
            ->join('statut_demande_conge', 'statut_demande_conge.id_statut = demande_conge.id_statut')
            ->where('demande_conge.id_demande_conge', $idDemande)
            ->get()
            ->getRow();

        if ($demande === null) {
            throw new CongeException("Demande #{$idDemande} introuvable", CongeException::OBJET_INTROUVABLE);
        }

        return (string) $demande->code;
    }

    private function resoudreIdType(array $body): int
    {
        if (isset($body['id_type_conge']) && (int) $body['id_type_conge'] > 0) {
            return (int) $body['id_type_conge'];
        }

        if (isset($body['type_code']) && trim((string) $body['type_code']) !== '') {
            $id = $this->typeCongeModel->idParCode(trim((string) $body['type_code']));
            if ($id !== null) {
                return $id;
            }
        }

        throw new CongeException('id_type_conge ou type_code est requis', CongeException::DATES_INVALIDES);
    }

    private function corps(): array
    {
        $post = $this->request->getPost();
        if (is_array($post) && $post !== []) {
            return $post;
        }

        $json = $this->request->getJSON(true);

        return is_array($json) ? $json : [];
    }

    private function businessDb(): \CodeIgniter\Database\BaseConnection
    {
        return $this->typeCongeModel->db;
    }
}
