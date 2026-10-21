<?php
declare(strict_types=1);
namespace App\Services\Recrutement;
class EligibiliteService extends RecruitmentService
{
    public function verifierEligibiliteMobilite(int $idSalarie, int $idPoste): array
    {
        $s=$this->db->table('salarie')->where('id_salarie',$idSalarie)->get()->getRowArray();
        if (!$s) return ['eligible'=>false,'motifs'=>['Salarié introuvable']];
        $e=$this->db->table('employe e')->select('e.poste_id')->join('salarie s','s.id_salarie=e.id_salarie')->where('e.id_salarie',$idSalarie)->get()->getRowArray();
        if (!$e) return ['eligible'=>false,'motifs'=>['Affectation actuelle introuvable']];
        return ['eligible'=>(int)$e['poste_id'] !== $idPoste,'motifs'=>(int)$e['poste_id'] === $idPoste ? ['Le salarié occupe déjà ce poste'] : []];
    }
}
