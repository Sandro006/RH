<?php

declare(strict_types=1);

namespace App\Controllers\Selection;

use App\Controllers\ApiController;
use App\Libraries\Common\SessionUtilisateur;
use App\Services\Selection\PropositionEmbaucheService;

class PropositionEmbaucheController extends ApiController
{
    private function service(): PropositionEmbaucheService { return new PropositionEmbaucheService(); }
    private function utilisateur(): SessionUtilisateur { $u = SessionUtilisateur::courante(); if ($u === null) throw new \RuntimeException('Session utilisateur absente'); return $u; }
    private function donnees(): array { return $this->request->getJSON(true) ?? $this->request->getPost(); }

    public function index(): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->lister($this->request->getGet())); }
    public function show(int $id): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->consulter($id)); }
    public function create(int $idCandidature): \CodeIgniter\HTTP\ResponseInterface { $d = $this->donnees(); return $this->executer(fn() => ['idProposition' => $this->service()->creerDepuisCandidature($idCandidature, (float) ($d['remunerationProposee'] ?? $d['remuneration_proposee'] ?? 0), $d['dateEmbaucheProposee'] ?? $d['date_embauche_proposee'] ?? null, $this->utilisateur())], 201); }
    public function envoyer(int $id): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->envoyer($id, $this->utilisateur())); }
    public function repondre(int $id): \CodeIgniter\HTTP\ResponseInterface { $d = $this->donnees(); return $this->executer(fn() => $this->service()->enregistrerReponse($id, (bool) ($d['accepte'] ?? false), $d['commentaire'] ?? null, $this->utilisateur())); }
    public function contreProposer(int $id): \CodeIgniter\HTTP\ResponseInterface { $d = $this->donnees(); return $this->executer(fn() => $this->service()->contreProposer($id, (float) ($d['remunerationProposee'] ?? $d['remuneration_proposee'] ?? 0), $d['dateEmbaucheProposee'] ?? $d['date_embauche_proposee'] ?? null, $this->utilisateur())); }
    public function expirer(int $id): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->marquerExpiree($id, $this->utilisateur())); }
    public function historique(int $id): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->historique($id)); }
    public function revendications(int $id): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->listerRevendications($id)); }
    public function soumettreRevendication(int $id): \CodeIgniter\HTTP\ResponseInterface { $d = $this->donnees(); $d['idProposition'] = $id; return $this->executer(fn() => ['idRevendication' => $this->service()->soumettreRevendication($d, $this->utilisateur())], 201); }
    public function repondreRevendication(int $id): \CodeIgniter\HTTP\ResponseInterface { $d = $this->donnees(); return $this->executer(fn() => $this->service()->repondreRevendication($id, (string) ($d['decision'] ?? ''), $d['reponse'] ?? null, $this->utilisateur())); }
    public function retirerRevendication(int $id): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->retirerRevendication($id, $this->utilisateur())); }
    public function historiqueRevendication(int $id): \CodeIgniter\HTTP\ResponseInterface { return $this->executer(fn() => $this->service()->historiqueRevendicationPublic($id)); }
}
