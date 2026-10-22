<?php
declare(strict_types=1);
namespace App\Controllers\Recrutement;
use App\Controllers\BaseController;
use App\Services\Recrutement\{DemandeRecrutementService, OffreEmploiService, CandidatureService, EligibiliteService};
class RecrutementController extends BaseController
{
    private function data(): array
    {
        return $this->request->getJSON(true) ?? $this->request->getPost();
    }
    private function ok($data, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON(['data' => $data]);
    }
    private function run(callable $fn)
    {
        try {
            return $this->ok($fn());
        } catch (\DomainException | \InvalidArgumentException $e) {
            return $this->response->setStatusCode(409)->setJSON(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => $e->getMessage()]);
        }
    }
    public function demandes()
    {
        return $this->ok((new DemandeRecrutementService())->lister($this->request->getGet()));
    }
    public function demande(int $id)
    {
        return $this->ok((new DemandeRecrutementService())->consulter($id));
    }
    public function creerDemande()
    {
        return $this->run(fn() => (new DemandeRecrutementService())->creer($this->data()));
    }
    public function modifierDemande(int $id)
    {
        return $this->run(fn() => (new DemandeRecrutementService())->modifier($id, $this->data()) ?? true);
    }
    public function demandeHistorique(int $id)
    {
        return $this->ok((new DemandeRecrutementService())->historique($id));
    }
    public function demandeCoherence(int $id)
    {
        return $this->ok((new DemandeRecrutementService())->verifierCoherence($id));
    }
    public function demandeValidations(int $id)
    {
        return $this->ok(db_connect()->table('validation')->where('id_demande', $id)->orderBy('date_validation', 'DESC')->get()->getResultArray());
    }
    public function actionDemande(int $id, string $a)
    {
        $s = new DemandeRecrutementService();
        $d = $this->data();
        return $this->run(fn() => match ($a) { 'soumettre' => $s->soumettre($id), 'controle-rh' => $s->controlerRh($id, $d['decision'] ?? 'VALIDER', $d['commentaire'] ?? ''), 'validation-budget' => $s->validerBudget($id, $d['montant_approuve'] ?? null, $d['decision'] ?? 'VALIDER', $d['commentaire'] ?? ''), 'validation-hierarchie' => $s->validerHierarchie($id, $d['decision'] ?? 'FEU_VERT', (bool) ($d['consultation'] ?? false), $d['commentaire'] ?? ''), 'consultation' => $s->demanderConsultation($id, $d['delai'] ?? null), 'cloture-consultation' => $s->cloturerConsultation($id, $d['decision'] ?? 'VALIDER', $d['commentaire'] ?? ''), 'feu-vert' => $s->donnerFeuVert($id, $d['canal_diffusion'] ?? null), 'annuler' => $s->annuler($id, $d['motif'] ?? null), default => throw new \InvalidArgumentException('Action inconnue')});
    }
    public function offres()
    {
        return $this->ok((new OffreEmploiService())->lister($this->request->getGet()));
    }
    public function offre(int $id)
    {
        return $this->ok((new OffreEmploiService())->consulter($id));
    }
    public function offrePublique(int $id)
    {
        $s = new OffreEmploiService();
        $o = $s->consulter($id);
        $ouvertes = $s->offresOuvertes();
        if (!$o || !array_filter($ouvertes, static fn($x) => (int) $x['id_offre'] === $id))
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Offre introuvable']);
        return $this->ok([
            'id_offre' => $o['id_offre'],
            'titre' => $o['titre'],
            'description' => $o['description'],
            'date_cloture' => $o['date_cloture'],
        ]);
    }
    public function offresOuvertes()
    {
        return $this->ok((new OffreEmploiService())->offresOuvertes());
    }
    public function offreDepuisDemande(int $id)
    {
        return $this->run(fn() => (new OffreEmploiService())->creerDepuisDemande($id));
    }
    public function modifierOffre(int $id)
    {
        return $this->run(fn() => (new OffreEmploiService())->modifier($id, $this->data()) ?? true);
    }
    public function offreHistorique(int $id)
    {
        return $this->ok(db_connect()->table('offre_emploi_status_historique')->where('id_offre', $id)->orderBy('date_changement')->get()->getResultArray());
    }
    public function offreCandidatures(int $id)
    {
        return $this->ok((new CandidatureService())->lister(['id_offre' => $id]));
    }
    public function actionOffre(int $id, string $a)
    {
        $s = new OffreEmploiService();
        $d = $this->data();
        return $this->run(fn() => match ($a) { 'publier' => $s->publier($id, $d['canal_diffusion'] ?? 'Site carrières', $d['date_cloture'] ?? null), 'suspendre' => $s->suspendre($id, $d['motif'] ?? null), 'reprendre' => $s->reprendre($id), 'clore' => $s->clore($id), 'annuler' => $s->annuler($id), default => throw new \InvalidArgumentException('Action inconnue')});
    }
    public function candidatures()
    {
        return $this->ok((new CandidatureService())->lister($this->request->getGet()));
    }
    public function candidature(int $id)
    {
        return $this->ok((new CandidatureService())->consulter($id));
    }
    public function candidatureHistorique(int $id)
    {
        return $this->ok((new CandidatureService())->historique($id));
    }
    public function postuler(int $id)
    {
        $d = $this->data();
        $d['id_offre'] = $id;
        return $this->run(fn() => (new CandidatureService())->postuler($d));
    }
    public function actionCandidature(int $id, string $a)
    {
        $s = new CandidatureService();
        $d = $this->data();
        return $this->run(fn() => match ($a) { 'retirer' => $s->retirer($id), 'preselection' => $s->mettreEnPreselection($id), 'selectionner' => $s->preselectionner($id, (bool) ($d['retenir'] ?? true)), 'planifier-entretien' => $s->planifierEntretien($id, $d), 'evaluer' => $s->marquerEvaluee($id), 'retenir' => $s->retenir($id), 'refuser' => $s->refuser($id, $d['motif'] ?? null), default => throw new \InvalidArgumentException('Action inconnue')});
    }
    public function eligibilite(int $id)
    {
        return $this->ok((new EligibiliteService())->verifierEligibiliteMobilite($id, (int) $this->request->getGet('poste')));
    }
}
