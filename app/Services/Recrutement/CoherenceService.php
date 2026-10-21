<?php
declare(strict_types=1);
namespace App\Services\Recrutement;

class CoherenceService extends RecruitmentService
{
    public function verifierPosteExiste(int $idPoste): bool
    { return (bool)$this->db->table('poste p')->select('p.id_poste')->join('service s','s.id_service=p.id_service')->where('p.id_poste',$idPoste)->get()->getRowArray(); }
    public function verifierRemunerationDansGrille(int $poste, $min = null, $max = null): bool
    {
        if ($min === null || $max === null) return true;
        return (bool)$this->db->table('grille_salariale')->where('id_poste',$poste)->where('salaire_min <=',(float)$min)->where('salaire_max >=',(float)$max)->groupStart()->where('date_fin',null)->orWhere('date_fin >=',date('Y-m-d'))->groupEnd()->get()->getRowArray();
    }
    public function verifierContratConformeMotif(int $typeContrat, string $motif, ?string $duree = null): bool
    {
        $type = $this->db->table('type_contrat')->where('id',$typeContrat)->get()->getRowArray();
        if (!$type) return false;
        $name = strtolower($type['designation']); $motif = strtolower($motif);
        if (str_contains($name,'cdi')) return !str_contains($motif,'saisonnier');
        if (str_contains($name,'cdd')) return (bool)$duree && !in_array($motif,['création','creation'],true);
        return true;
    }
    public function resolutionInternePossible(int $idPoste): bool
    { return (bool)$this->db->table('employe')->where('poste_id',$idPoste)->get()->getRowArray(); }
    public function verifierCoherence(int $idDemande): array
    {
        $d=$this->row('demande_recrutement','id_demande',$idDemande); $checks=[];
        $checks[]=['regle'=>'poste','conforme'=>$this->verifierPosteExiste((int)$d['id_poste']),'message'=>'Le poste doit être rattaché à un service.'];
        $checks[]=['regle'=>'grille_salariale','conforme'=>$this->verifierRemunerationDansGrille((int)$d['id_poste'],$d['remuneration_min'],$d['remuneration_max']),'message'=>'La rémunération doit être dans la grille active.'];
        $checks[]=['regle'=>'contrat_motif','conforme'=>$this->verifierContratConformeMotif((int)$d['type_contrat'],(string)$d['motif'],$d['duree_contrat']),'message'=>'Le contrat doit être cohérent avec le motif.'];
        $checks[]=['regle'=>'mobilite_interne','conforme'=>true,'message'=>$this->resolutionInternePossible((int)$d['id_poste'])?'Une solution de mobilité interne existe.':'Aucune mobilité interne détectée.'];
        return ['conforme'=>!array_filter($checks,static fn($c)=>!$c['conforme']),'anomalies'=>$checks];
    }
}
