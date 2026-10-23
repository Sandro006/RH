<?php

declare(strict_types=1);

namespace App\Controllers\Selection;

use App\Controllers\ApiController;
use App\Libraries\Common\SessionUtilisateur;
use App\Services\Selection\EvaluationService;

class EvaluationController extends ApiController
{
    private function service(): EvaluationService { return new EvaluationService(); }
    private function utilisateur(): SessionUtilisateur { $u = SessionUtilisateur::courante(); if ($u === null) throw new \RuntimeException('Session utilisateur absente'); return $u; }
    private function donnees(): array { return $this->request->getJSON(true) ?? $this->request->getPost(); }

    public function show(int $id): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->synthese($id)); }
    public function store(int $id): \CodeIgniter\HTTP\ResponseInterface { $d = $this->donnees(); $d['idCandidature'] = $id; return $this->executer(fn() => ['idEvaluation' => $this->service()->evaluer($d, $this->utilisateur())], 201); }
    public function retenir(int $id): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->retenir($id, $this->utilisateur())); }
    public function refuser(int $id): \CodeIgniter\HTTP\ResponseInterface { $d = $this->donnees(); return $this->executer(fn() => $this->service()->refuser($id, $d['motif'] ?? null, $this->utilisateur())); }
    public function classement(int $idOffre): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->classerPourOffre($idOffre)); }
    public function liste(int $idOffre): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->listerParOffre($idOffre)); }
}
