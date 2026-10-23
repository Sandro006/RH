<?php
declare(strict_types=1);
namespace App\Services\Recrutement;
use App\Models\Recrutement\OffreEmploiModel;
use App\Libraries\Common\Tri;
class OffreEmploiService extends RecruitmentService
{
    private OffreEmploiModel $model;
    public function __construct(?\CodeIgniter\Database\BaseConnection $db=null) { parent::__construct($db); $this->model=new OffreEmploiModel($this->db); }
    public function creerDepuisDemande(int $idDemande, ?array $user=null): int
    {
        $d=$this->row('demande_recrutement','id_demande',$idDemande); $valid=$this->id('status','Validée');
        if ((int)$d['statut']!==$valid) throw new \DomainException('La demande doit être validée.');
        $old=$this->model->where('id_demande',$idDemande)->first(); if ($old) return (int)$old['id_offre'];
        $p=$this->row('poste','id_poste',(int)$d['id_poste']); $title=$p['intitule'].' - #'.$idDemande;
        while($this->model->where('titre',$title)->first()) $title.=' - '.bin2hex(random_bytes(2));
        return (int)$this->model->insert(['statut'=>$this->id('status_offre_emploi','Brouillon'),'id_demande'=>$idDemande,'titre'=>$title,'description'=>$d['missions'],'date_creation'=>date('Y-m-d H:i:s')],true);
    }
    public function modifier(int $id,array $data,?array $user=null): void { $o=$this->row('offre_emploi','id_offre',$id); if((int)$o['statut']!==$this->id('status_offre_emploi','Brouillon')) throw new \DomainException('Seule une offre brouillon est modifiable.'); $this->model->update($id,array_intersect_key($data,array_flip(['titre','description','canal_diffusion','date_cloture']))); }
    private function change(int $id,string $to,?array $user=null,?string $comment=null): void { $o=$this->row('offre_emploi','id_offre',$id); $new=$this->id('status_offre_emploi',$to); $old=$this->designation((int)$o['statut']); $allowed=['Brouillon'=>['Publiée','Annulée'],'Publiée'=>['Suspendue','Clôturée','Annulée'],'Suspendue'=>['Publiée','Clôturée','Annulée']]; if(!in_array($to,$allowed[$old]??[],true))throw new \DomainException("Transition {$old} -> {$to} interdite."); $this->model->update($id,['statut'=>$new]); $this->db->table('offre_emploi_status_historique')->insert(['id_offre'=>$id,'statut_ancien'=>$o['statut'],'statut_nouveau'=>$new,'date_changement'=>date('Y-m-d H:i:s'),'id_utilisateur'=>$this->actor($user),'commentaire'=>$comment]); }
    private function designation(int $id):string{return (string)$this->db->table('status_offre_emploi')->where('id',$id)->get()->getRowArray()['designation'];}
    public function publier(int $id,string $canal,?string $cloture=null,?array $user=null):void { $this->modifier($id,['canal_diffusion'=>$canal,'date_cloture'=>$cloture],$user); $this->model->update($id,['date_publication'=>date('Y-m-d'),'canal_diffusion'=>$canal,'date_cloture'=>$cloture]); $this->change($id,'Publiée',$user,'Publication'); }
    public function suspendre(int $id,?string $motif=null,?array $u=null):void{$this->change($id,'Suspendue',$u,$motif);}
    public function reprendre(int $id,?array $u=null):void{$this->change($id,'Publiée',$u,'Reprise');}
    public function clore(int $id,?array $u=null):void{$this->change($id,'Clôturée',$u,'Clôture');}
    public function annuler(int $id,?array $u=null):void{$this->change($id,'Annulée',$u,'Annulation');}
    public function consulter(int $id):?array{return $this->model->find($id);}
    public function lister(array $filter=[]):array{$q=$this->db->table('offre_emploi o')->select('o.*, st.designation AS statut_designation')->join('status_offre_emploi st','st.id=o.statut','left'); foreach(['statut','id_demande'] as $f)if(isset($filter[$f])&&$filter[$f]!=='')$q->where('o.'.$f,$filter[$f]); [$colonne,$ordre]=Tri::resoudre($filter,['id_offre'=>'o.id_offre','titre'=>'o.titre','canal'=>'o.canal_diffusion','publication'=>'o.date_publication','statut'=>'st.designation'],'o.id_offre','DESC'); return $q->orderBy($colonne,$ordre)->get()->getResultArray();}
    public function offresOuvertes():array{$id=$this->id('status_offre_emploi','Publiée'); return $this->model->where('statut',$id)->groupStart()->where('date_cloture',null)->orWhere('date_cloture >=',date('Y-m-d'))->groupEnd()->findAll();}
}
