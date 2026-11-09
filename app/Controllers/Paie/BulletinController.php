<?php

declare(strict_types=1);

namespace App\Controllers\Paie;

use App\Controllers\ApiController;
use App\Exceptions\CongeException;
use App\Libraries\Common\SessionUtilisateur;
use App\Services\Paie\BulletinService;
use App\Services\Paie\CotisationService;
use App\Services\Paie\ElementPaieService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * API Paie (Bloc Paie) : bulletins, éléments variables, taux.
 * Réservé aux rôles rh/admin (voir Routes) ; CongeException est mappée
 * par ApiController::executer() comme pour les Congés.
 */
class BulletinController extends ApiController
{
    private BulletinService $bulletinService;
    private ElementPaieService $elementService;
    private CotisationService $cotisationService;

    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger,
    ): void {
        parent::initController($request, $response, $logger);

        $this->bulletinService = service('bulletinService');
        $this->elementService = service('elementPaieService');
        $this->cotisationService = service('cotisationService');
    }

    // GET /api/paie/bulletins?mois=&id_salarie=
    public function liste()
    {
        return $this->executer(function () {
            $mois = $this->request->getGet('mois');
            $idSalarie = $this->request->getGet('id_salarie');

            if ($idSalarie !== null && $idSalarie !== '') {
                return $this->bulletinService->trouver((int) $idSalarie, (string) $mois) ? [$this->bulletinService->trouver((int) $idSalarie, (string) $mois)] : [];
            }

            if ($mois === null || trim((string) $mois) === '') {
                return $this->erreur('DONNEES_INCOHERENTES', 'mois requis (YYYY-MM)', 400);
            }

            return $this->bulletinService->lister((string) $mois);
        });
    }

    // GET /api/paie/bulletins/{id}
    public function show(int $id)
    {
        return $this->executer(function () use ($id) {
            $bulletin = $this->bulletinDepuisId($id);

            return $bulletin;
        });
    }

    // POST /api/paie/bulletins/calculer  { id_salarie, mois }
    public function calculer()
    {
        return $this->executer(function () {
            $body = $this->corps();
            $mois = trim((string) ($body['mois'] ?? ''));

            if ($mois === '' || !isset($body['id_salarie'])) {
                return $this->erreur('DONNEES_INCOHERENTES', 'id_salarie et mois requis', 400);
            }

            return $this->bulletinService->generer((int) $body['id_salarie'], $mois);
        }, 201);
    }

    // GET /api/paie/bulletins/{id}/detail — bulletin + éléments + synthèse gains/retenues (aperçu)
    public function detail(int $id)
    {
        return $this->executer(fn () => $this->bulletinService->detail($id));
    }

    // POST /api/paie/bulletins/{id}/valider
    public function valider(int $id)
    {
        return $this->executer(function () use ($id) {
            $u = SessionUtilisateur::courante();
            $this->bulletinService->valider($id, $u);

            return ['id_bulletin' => $id, 'message' => 'Bulletin validé'];
        });
    }

    // GET /api/paie/elements?id_salarie=&mois=
    public function elements()
    {
        return $this->executer(function () {
            $idSalarie = (int) ($this->request->getGet('id_salarie') ?? 0);
            $mois = trim((string) ($this->request->getGet('mois') ?? ''));

            if ($idSalarie <= 0) {
                return $this->erreur('DONNEES_INCOHERENTES', 'id_salarie requis', 400);
            }
            if ($mois === '') {
                return $this->erreur('DONNEES_INCOHERENTES', 'mois requis (YYYY-MM)', 400);
            }

            return $this->elementService->lister($idSalarie, $mois);
        });
    }

    // POST /api/paie/elements  { id_salarie, mois, type_element, libelle, montant, commentaire? }
    public function saisirElement()
    {
        return $this->executer(function () {
            $u = SessionUtilisateur::courante();
            $body = $this->corps();

            $id = $this->elementService->saisir($body, $u);

            return ['id_element_paie' => $id, 'message' => 'Élément de paie saisi'];
        }, 201);
    }

    // GET /api/paie/taux
    public function taux()
    {
        return $this->executer(fn () => $this->cotisationService->lister());
    }

    private function bulletinDepuisId(int $id): object
    {
        $bulletin = service('bulletinService')->trouverParId($id);

        if ($bulletin === null) {
            throw new CongeException("Bulletin #{$id} introuvable", CongeException::OBJET_INTROUVABLE);
        }

        return $bulletin;
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
}
