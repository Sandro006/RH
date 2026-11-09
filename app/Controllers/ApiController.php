<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\AccesRefuseException;
use App\Exceptions\ConflitException;
<<<<<<< HEAD
=======
use App\Exceptions\CongeException;
>>>>>>> 57f1b34f20901561d84f17b8b34950d5e25ea61e
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use CodeIgniter\HTTP\ResponseInterface;

abstract class ApiController extends BaseController
{
    protected function ok(mixed $donnees, int $statut = 200): ResponseInterface
    {
        return $this->response->setStatusCode($statut)->setJSON($donnees);
    }

    protected function cree(mixed $donnees): ResponseInterface
    {
        return $this->ok($donnees, 201);
    }

    protected function erreur(string $code, string $message, int $statut): ResponseInterface
    {
        return $this->response->setStatusCode($statut)->setJSON([
            'error'   => $code,
            'message' => $message,
        ]);
    }

    protected function executer(callable $action, int $statutSucces = 200): ResponseInterface
    {
        try {
            return $this->ok($action(), $statutSucces);
<<<<<<< HEAD
=======
        } catch (CongeException $e) {
            return $this->erreur($e->getCodeMetier(), $e->getMessage(), $this->statutHttpPourCodeConge($e->getCodeMetier()));
>>>>>>> 57f1b34f20901561d84f17b8b34950d5e25ea61e
        } catch (AccesRefuseException $e) {
            return $this->erreur('FORBIDDEN', $e->getMessage(), 403);
        } catch (ObjetIntrouvableException $e) {
            return $this->erreur('NOT_FOUND', $e->getMessage(), 404);
        } catch (ConflitException $e) {
            return $this->erreur('CONFLIT', $e->getMessage(), 409);
        } catch (DonneesIncoherentesException | StatutInvalideException | \InvalidArgumentException $e) {
            return $this->erreur('DONNEES_INCOHERENTES', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            log_message('error', $e->getMessage() . "\n" . $e->getTraceAsString());

            return $this->erreur('ERREUR_INTERNE', 'Une erreur interne est survenue', 500);
        }
    }
<<<<<<< HEAD
=======

    private function statutHttpPourCodeConge(string $code): int
    {
        return match ($code) {
            CongeException::NON_AUTORISE => 403,
            CongeException::OBJET_INTROUVABLE => 404,
            CongeException::CHEVAUCHEMENT,
            CongeException::STATUT_INVALIDE => 409,
            CongeException::SOLDE_INSUFFISANT,
            CongeException::DELAI_NON_RESPECTE,
            CongeException::DUREE_MAX,
            CongeException::ANCIENNETE,
            CongeException::CONTRAT_INACTIF,
            CongeException::JUSTIFICATIF_REQUIS,
            CongeException::DATES_INVALIDES,
            CongeException::DEMI_JOURNEE_NON_AUTORISEE,
            CongeException::TYPE_INACTIF => 422,
            default => 400,
        };
    }
>>>>>>> 57f1b34f20901561d84f17b8b34950d5e25ea61e
}
