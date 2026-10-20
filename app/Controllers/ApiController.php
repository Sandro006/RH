<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\AccesRefuseException;
use App\Exceptions\ConflitException;
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
}
