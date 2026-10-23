<?php

declare(strict_types=1);

namespace App\Services\Selection;

use App\Exceptions\AccesRefuseException;
use App\Exceptions\ConflitException;
use App\Exceptions\DonneesIncoherentesException;
use App\Exceptions\ObjetIntrouvableException;
use App\Exceptions\StatutInvalideException;
use App\Libraries\Common\SessionUtilisateur;
use App\Libraries\Common\WorkflowService;
use App\Models\Selection\PropositionEmbaucheModel;
use App\Models\Selection\RevendicationModel;
use CodeIgniter\Database\BaseConnection;

class PropositionEmbaucheService
{
    private const ROLES_RH = ['rh', 'recruteur', 'direction', 'admin'];

    public function __construct(
        private readonly ?BaseConnection $db = null,
        private readonly ?PropositionEmbaucheModel $propositionModel = null,
        private readonly ?RevendicationModel $revendicationModel = null,
        private readonly ?WorkflowService $workflow = null,
    ) {
    }

    private function database(): BaseConnection { return $this->db ?? db_connect(); }
    private function propositions(): PropositionEmbaucheModel { return $this->propositionModel ?? new PropositionEmbaucheModel($this->database()); }
    private function revendications(): RevendicationModel { return $this->revendicationModel ?? new RevendicationModel($this->database()); }
    private function workflowService(): WorkflowService { return $this->workflow ?? new WorkflowService($this->database()); }

    public function creerDepuisCandidature(int $idCandidature, float $remuneration, ?string $dateEmbauche, SessionUtilisateur $u): int
    {
        $this->exigerRole($u);
        $candidature = $this->candidature($idCandidature);
        if ($candidature['statut_designation'] !== 'Retenue') throw new StatutInvalideException('Seules les candidatures retenues peuvent recevoir une proposition');
        if ($this->propositions()->where('id_candidature', $idCandidature)->first() !== null) throw new ConflitException('Une proposition existe déjà pour cette candidature');

        $offre = $this->database()->table('offre_emploi o')->select('o.*, d.id_poste')
            ->join('demande_recrutement d', 'd.id_demande = o.id_demande')->where('o.id_offre', $candidature['id_offre'])->get()->getRowArray();
        if ($offre === null) throw new ObjetIntrouvableException('Offre liée à la candidature introuvable');
        if (!$this->remunerationValide((int) $offre['id_poste'], $remuneration)) throw new DonneesIncoherentesException('La rémunération est hors de la grille salariale');
        $this->validerDate($dateEmbauche);

        $statut = $this->idStatut('À envoyer');
        $id = $this->propositions()->insert([
            'statut' => $statut,
            'id_candidature' => $idCandidature,
            'date_proposition' => date('Y-m-d'),
            'remuneration_proposee' => $remuneration,
            'date_embauche_proposee' => $dateEmbauche,
        ], true);
        if ($id === false) throw new \RuntimeException('Échec de création de la proposition');
        $this->historiqueProposition((int) $id, $statut, $statut, $u, 'Création de la proposition');
        $this->auditer($u, 'CREATE', 'proposition_embauche', (int) $id);
        return (int) $id;
    }

    public function envoyer(int $id, SessionUtilisateur $u): void
    {
        $this->exigerRole($u); $p = $this->proposition($id); $this->transition($p, 'Envoyée', $u, 'Proposition envoyée');
    }

    public function enregistrerReponse(int $id, bool $acceptee, ?string $commentaire, ?SessionUtilisateur $u = null): void
    {
        $p = $this->proposition($id);
        if (!in_array($p['statut_designation'], ['Envoyée', 'En négociation'], true)) throw new StatutInvalideException('La proposition n’attend pas de réponse');
        if ($acceptee && $this->ouvertes($id) !== []) throw new ConflitException('Les revendications ouvertes doivent être traitées avant acceptation');
        $this->propositions()->update($id, ['reponse' => $commentaire, 'date_reponse' => date('Y-m-d')]);
        $this->transition($p, $acceptee ? 'Acceptée' : 'Refusée', $u, $commentaire);
    }

    public function contreProposer(int $id, float $remuneration, ?string $dateEmbauche, SessionUtilisateur $u): void
    {
        $this->exigerRole($u); $p = $this->proposition($id);
        if ($p['statut_designation'] !== 'En négociation') throw new StatutInvalideException('La proposition doit être en négociation');
        $c = $this->candidature((int) $p['id_candidature']);
        $o = $this->database()->table('offre_emploi o')->select('d.id_poste')->join('demande_recrutement d', 'd.id_demande=o.id_demande')->where('o.id_offre', $c['id_offre'])->get()->getRowArray();
        if ($o === null || !$this->remunerationValide((int) $o['id_poste'], $remuneration)) throw new DonneesIncoherentesException('La rémunération est hors de la grille salariale');
        $this->validerDate($dateEmbauche);
        $this->propositions()->update($id, ['remuneration_proposee' => $remuneration, 'date_embauche_proposee' => $dateEmbauche]);
        $this->transition($p, 'Envoyée', $u, 'Nouvelle contre-proposition');
    }

    public function marquerExpiree(int $id, ?SessionUtilisateur $u = null): void
    {
        $p = $this->proposition($id);
        if (!in_array($p['statut_designation'], ['Envoyée', 'En négociation'], true)) throw new StatutInvalideException('La proposition ne peut pas expirer dans cet état');
        $this->transition($p, 'Expirée', $u, 'Proposition expirée');
    }

    public function consulter(int $id): ?array
    {
        $p = $this->proposition($id); $p['revendications'] = $this->listerRevendications($id); return $p;
    }

    public function lister(array $filtres = []): array
    {
        $q = $this->database()->table('proposition_embauche p')->select('p.*, s.designation AS statut_designation, c.id_offre, ca.nom, ca.prenom, o.titre AS offre_titre')
            ->join('status_proposition_embauche s', 's.id=p.statut')->join('candidature c', 'c.id_candidature=p.id_candidature')
            ->join('candidat ca', 'ca.id_candidat=c.id_candidat', 'left')->join('offre_emploi o', 'o.id_offre=c.id_offre', 'left');
        if (!empty($filtres['statut'])) $q->where('s.designation', $filtres['statut']);
        if (!empty($filtres['idCandidature'])) $q->where('p.id_candidature', (int) $filtres['idCandidature']);
        return $q->orderBy('p.id_proposition', 'DESC')->get()->getResultArray();
    }

    public function historique(int $id): array { return $this->database()->table('proposition_embauche_status_historique')->where('id_proposition', $id)->orderBy('date_changement', 'ASC')->get()->getResultArray(); }

    public function soumettreRevendication(array $data, ?SessionUtilisateur $auteur = null): int
    {
        $id = (int) ($data['idProposition'] ?? $data['id_proposition'] ?? 0); $p = $this->proposition($id);
        if (!in_array($p['statut_designation'], ['Envoyée', 'En négociation'], true)) throw new StatutInvalideException('La proposition n’est pas négociable');
        $nature = $this->idReference('nature_revendication', (string) ($data['nature'] ?? $data['natureDesignation'] ?? ''));
        $statut = $this->idReference('status_revendication', 'Soumise');
        $sens = strtoupper((string) ($data['sens'] ?? 'CANDIDAT'));
        if (!in_array($sens, ['CANDIDAT', 'ENTREPRISE'], true)) throw new DonneesIncoherentesException('sens invalide');
        $idRev = $this->revendications()->insert([
            'id_proposition' => $id, 'sens' => $sens, 'id_candidat' => $data['idCandidat'] ?? null,
            'id_utilisateur' => $auteur?->idUtilisateur, 'nature_id' => $nature,
            'valeur_demandee' => $data['valeurDemandee'] ?? $data['valeur_demandee'] ?? null,
            'commentaire' => $data['commentaire'] ?? null, 'statut_id' => $statut,
            'date_creation' => date('Y-m-d H:i:s'),
        ], true);
        if ($idRev === false) throw new \RuntimeException('Échec de création de la revendication');
        $this->historiqueRevendication((int) $idRev, $statut, $statut, $auteur, 'Revendication soumise');
        if ($p['statut_designation'] !== 'En négociation') $this->transition($p, 'En négociation', $auteur, 'Négociation ouverte');
        return (int) $idRev;
    }

    public function repondreRevendication(int $id, string $decision, ?string $reponse, SessionUtilisateur $u): void
    {
        $this->exigerRole($u); $r = $this->revendication($id); $actuel = $r['statut_designation'];
        if (!in_array($actuel, ['Soumise', 'En discussion'], true) || !in_array($decision, ['Acceptée', 'Refusée', 'En discussion'], true)) throw new StatutInvalideException('Transition de revendication invalide');
        $statut = $this->idReference('status_revendication', $decision);
        $this->revendications()->update($id, ['statut_id' => $statut, 'id_repondant' => $u->idUtilisateur, 'reponse' => $reponse, 'date_reponse' => date('Y-m-d H:i:s')]);
        $this->historiqueRevendication($id, (int) $r['statut_id'], $statut, $u, $reponse);
    }

    public function retirerRevendication(int $id, ?SessionUtilisateur $u = null): void
    {
        $r = $this->revendication($id); if (!in_array($r['statut_designation'], ['Soumise', 'En discussion'], true)) throw new StatutInvalideException('Revendication déjà traitée');
        $statut = $this->idReference('status_revendication', 'Retirée'); $this->revendications()->update($id, ['statut_id' => $statut]); $this->historiqueRevendication($id, (int) $r['statut_id'], $statut, $u, 'Revendication retirée');
    }

    public function listerRevendications(int $id): array { return $this->database()->table('revendication r')->select('r.*, n.designation AS nature, s.designation AS statut_designation')->join('nature_revendication n', 'n.id=r.nature_id')->join('status_revendication s', 's.id=r.statut_id')->where('r.id_proposition', $id)->orderBy('r.date_creation', 'ASC')->get()->getResultArray(); }
    public function ouvertes(int $id): array { return array_values(array_filter($this->listerRevendications($id), fn(array $r) => in_array($r['statut_designation'], ['Soumise', 'En discussion'], true))); }
    public function historiqueRevendicationPublic(int $id): array { return $this->database()->table('revendication_status_historique')->where('id_revendication', $id)->orderBy('date_changement', 'ASC')->get()->getResultArray(); }

    private function proposition(int $id): array { $p = $this->database()->table('proposition_embauche p')->select('p.*, s.designation AS statut_designation')->join('status_proposition_embauche s', 's.id=p.statut')->where('p.id_proposition', $id)->get()->getRowArray(); if ($p === null) throw new ObjetIntrouvableException("Proposition #{$id} introuvable"); return $p; }
    private function revendication(int $id): array { $r = $this->database()->table('revendication r')->select('r.*, s.designation AS statut_designation')->join('status_revendication s', 's.id=r.statut_id')->where('r.id_revendication', $id)->get()->getRowArray(); if ($r === null) throw new ObjetIntrouvableException("Revendication #{$id} introuvable"); return $r; }
    private function candidature(int $id): array { $c = $this->database()->table('candidature c')->select('c.*, s.designation AS statut_designation')->join('status_candidature s', 's.id=c.statut')->where('c.id_candidature', $id)->get()->getRowArray(); if ($c === null) throw new ObjetIntrouvableException("Candidature #{$id} introuvable"); return $c; }
    private function idStatut(string $designation): int { return $this->idReference('status_proposition_embauche', $designation); }
    private function idReference(string $table, string $designation): int { if ($designation === '') throw new DonneesIncoherentesException('Référence obligatoire'); $r = $this->database()->table($table)->where('designation', $designation)->get()->getRowArray(); if ($r === null) throw new DonneesIncoherentesException("Référence inconnue : {$designation}"); return (int) $r['id']; }
    private function transition(array $p, string $cible, ?SessionUtilisateur $u, ?string $commentaire): void { $autorise = ['À envoyer' => ['Envoyée'], 'Envoyée' => ['En négociation', 'Acceptée', 'Refusée', 'Expirée'], 'En négociation' => ['Envoyée', 'Acceptée', 'Refusée', 'Expirée']]; if (!in_array($cible, $autorise[$p['statut_designation']] ?? [], true)) throw new StatutInvalideException("Transition {$p['statut_designation']} -> {$cible} interdite"); $this->workflowService()->changerStatut('proposition', (int) $p['id_proposition'], $cible, $u, $commentaire); }
    private function exigerRole(SessionUtilisateur $u): void { if (!$u->aLeRole(self::ROLES_RH)) throw new AccesRefuseException('Rôle insuffisant pour gérer une proposition'); }
    private function remunerationValide(int $idPoste, float $montant): bool { $r = $this->database()->table('grille_salariale')->where('id_poste', $idPoste)->where('date_debut <=', date('Y-m-d'))->groupStart()->where('date_fin >=', date('Y-m-d'))->orWhere('date_fin', null)->groupEnd()->orderBy('date_debut', 'DESC')->get()->getRowArray(); return $r !== null && $montant >= (float) $r['salaire_min'] && $montant <= (float) $r['salaire_max']; }
    private function validerDate(?string $date): void { if ($date !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false)) throw new DonneesIncoherentesException('dateEmbaucheProposee invalide'); }
    private function historiqueProposition(int $id, int $ancien, int $nouveau, ?SessionUtilisateur $u, ?string $commentaire): void { $this->database()->table('proposition_embauche_status_historique')->insert(['id_proposition' => $id, 'statut_ancien' => $ancien, 'statut_nouveau' => $nouveau, 'date_changement' => date('Y-m-d H:i:s'), 'id_utilisateur' => $u?->idUtilisateur, 'commentaire' => $commentaire]); }
    private function historiqueRevendication(int $id, int $ancien, int $nouveau, ?SessionUtilisateur $u, ?string $commentaire): void { $this->database()->table('revendication_status_historique')->insert(['id_revendication' => $id, 'statut_ancien' => $ancien, 'statut_nouveau' => $nouveau, 'date_changement' => date('Y-m-d H:i:s'), 'id_utilisateur' => $u?->idUtilisateur, 'commentaire' => $commentaire]); }
    private function auditer(SessionUtilisateur $u, string $action, string $entite, int $id): void { try { service('auditService')->journaliser($u->idUtilisateur, $action, $entite, $id, null, $u->adresseIp); } catch (\Throwable) { /* l’audit ne doit pas masquer l’opération métier */ } }
}
