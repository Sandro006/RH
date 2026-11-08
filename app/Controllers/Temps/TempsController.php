<?php
declare(strict_types=1);
namespace App\Controllers\Temps;
use App\Controllers\ApiController;
use App\Libraries\Common\Roles;
use App\Libraries\Common\SessionUtilisateur;
use App\Exceptions\AccesRefuseException;
use App\Exceptions\DonneesIncoherentesException;

final class TempsController extends ApiController
{
    private function autoriser(): void
    {
        $session = SessionUtilisateur::courante();
        if (!$session || !$session->aLeRole([Roles::ADMIN, Roles::RH])) throw new AccesRefuseException('Accès réservé aux ressources humaines.');
    }
    private function salarie(int $id): void
    {
        if ($id < 1 || !db_connect()->table('salarie')->where('id_salarie', $id)->countAllResults()) throw new DonneesIncoherentesException('Salarié invalide.');
    }
    private function valide(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        return $date !== false && $date->format($format) === $value;
    }
    public function calendrier()
    {
        return $this->executer(function () {
            $this->autoriser(); $id = (int) $this->request->getGet('salarie'); $this->salarie($id);
            $mois = (string) $this->request->getGet('mois');
            if (!$this->valide($mois, 'Y-m')) throw new DonneesIncoherentesException('Mois invalide.');
            $debut = $mois . '-01'; $fin = (new \DateTimeImmutable($debut))->modify('+1 month')->format('Y-m-d'); $db = db_connect();
            $plans = $db->table('temps_planning')->where('id_salarie', $id)->where('jour >=', $debut)->where('jour <', $fin)->get()->getResultArray();
            $points = $db->table('temps_pointage')->where('id_salarie', $id)->where('instant >=', $debut . ' 00:00:00')->where('instant <', $fin . ' 00:00:00')->orderBy('instant')->get()->getResultArray();
            $jours = [];
            foreach ($plans as $plan) $jours[$plan['jour']] = ['planning' => $plan, 'pointages' => []];
            foreach ($points as $point) {
                $jour = substr($point['instant'], 0, 10);
                if (!isset($jours[$jour])) $jours[$jour] = ['planning' => null, 'pointages' => []];
                $jours[$jour]['pointages'][] = $point;
            }
            foreach ($jours as $jour => &$data) {
                $ouvert = null; $minutes = 0; $anomalies = []; $premiere = null;
                foreach ($data['pointages'] as $point) {
                    $t = strtotime($point['instant']);
                    if ($point['type'] === 'entree') {
                        if ($ouvert !== null) $anomalies[] = 'Entrée sans sortie précédente';
                        else { $ouvert = $t; $premiere ??= $t; }
                    } elseif ($ouvert === null) $anomalies[] = 'Sortie sans entrée';
                    else { $minutes += (int) floor(($t - $ouvert) / 60); $ouvert = null; }
                }
                if ($ouvert !== null) $anomalies[] = 'Sortie manquante';
                $plan = $data['planning']; $prevu = 0; $retard = 0;
                if ($plan) {
                    $start = strtotime($jour . ' ' . $plan['debut']); $end = strtotime($jour . ' ' . $plan['fin']);
                    $prevu = (int) (($end - $start) / 60) - (int) $plan['pause_minutes'];
                    if ($premiere !== null) $retard = max(0, (int) floor(($premiere - $start) / 60));
                    elseif ($jour < date('Y-m-d')) $anomalies[] = 'Aucun pointage';
                } elseif ($data['pointages']) $anomalies[] = 'Pointage sans planning';
                $data += ['minutes_travaillees' => $minutes, 'minutes_prevues' => $prevu, 'retard_minutes' => $retard, 'heures_supplementaires_minutes' => $plan ? max(0, $minutes - $prevu) : 0, 'anomalies' => array_values(array_unique($anomalies))];
            }
            unset($data); ksort($jours);
            $taux = $db->table('temps_taux_supplementaire')->where('date_debut <', $fin)->orderBy('date_debut', 'DESC')->get()->getResultArray();
            return ['jours' => $jours, 'taux' => $taux];
        });
    }
    public function taux()
    {
        return $this->executer(function () {
            $this->autoriser();
            return db_connect()->table('temps_taux_supplementaire')->orderBy('regime')->orderBy('date_debut', 'DESC')->get()->getResultArray();
        });
    }
    public function ajouterTaux()
    {
        return $this->executer(function () {
            $this->autoriser(); $v = (array) $this->request->getJSON(true);
            $regime = trim((string) ($v['regime'] ?? '')); $date = (string) ($v['date_debut'] ?? '');
            $taux = $v['taux_horaire'] ?? null; $devise = trim((string) ($v['devise'] ?? ''));
            if ($regime === '' || mb_strlen($regime) > 100 || !$this->valide($date, 'Y-m-d') || !is_numeric($taux) || (float) $taux < 0 || (float) $taux > 999999999999 || !preg_match('/^\\d{1,12}(?:\\.\\d{1,2})?$/', (string) $taux) || $devise === '' || mb_strlen($devise) > 10) throw new DonneesIncoherentesException('Vérifiez le régime, la date, le taux et la devise.');
            $db = db_connect();
            $existe = $db->query('SELECT id_taux FROM temps_taux_supplementaire WHERE lower(trim(regime)) = lower(?) AND date_debut = ?', [$regime, $date])->getRowArray();
            if ($existe) throw new DonneesIncoherentesException('Un taux existe déjà pour ce régime à cette date.');
            $db->table('temps_taux_supplementaire')->insert(['regime' => $regime, 'date_debut' => $date, 'taux_horaire' => $taux, 'devise' => $devise]);
            return ['message' => 'Taux enregistré.'];
        }, 201);
    }
    public function planning()
    {
        return $this->executer(function () {
            $this->autoriser(); $v = (array) $this->request->getJSON(true); $id = (int) ($v['id_salarie'] ?? 0); $this->salarie($id);
            $jour = (string) ($v['jour'] ?? ''); $debut = (string) ($v['debut'] ?? ''); $fin = (string) ($v['fin'] ?? '');
            $pause = filter_var($v['pause_minutes'] ?? 0, FILTER_VALIDATE_INT); $regime = trim((string) ($v['regime'] ?? ''));
            if (!$this->valide($jour, 'Y-m-d') || !$this->valide($debut, 'H:i') || !$this->valide($fin, 'H:i') || $fin <= $debut || $pause === false || $pause < 0 || $pause >= (strtotime($fin) - strtotime($debut)) / 60 || $regime === '' || mb_strlen($regime) > 100) throw new DonneesIncoherentesException('Planning invalide : vérifiez la date, les horaires, la pause et le régime.');
            $table = db_connect()->table('temps_planning');
            if ($table->where('id_salarie', $id)->where('jour', $jour)->countAllResults()) throw new DonneesIncoherentesException('Un planning existe déjà pour ce jour.');
            $table->insert(['id_salarie' => $id, 'jour' => $jour, 'debut' => $debut, 'fin' => $fin, 'pause_minutes' => $pause, 'regime' => $regime]);
            return ['message' => 'Planning enregistré.'];
        }, 201);
    }
    public function pointage()
    {
        return $this->executer(function () {
            $this->autoriser(); $v = (array) $this->request->getJSON(true); $id = (int) ($v['id_salarie'] ?? 0); $this->salarie($id);
            $instant = str_replace('T', ' ', (string) ($v['instant'] ?? '')); $type = (string) ($v['type'] ?? '');
            if (!$this->valide($instant, 'Y-m-d H:i') || !in_array($type, ['entree', 'sortie'], true)) throw new DonneesIncoherentesException('Pointage invalide.');
            $table = db_connect()->table('temps_pointage');
            if ($table->where('id_salarie', $id)->where('instant', $instant . ':00')->countAllResults()) throw new DonneesIncoherentesException('Un pointage existe déjà à cette heure.');
            $table->insert(['id_salarie' => $id, 'instant' => $instant . ':00', 'type' => $type]);
            return ['message' => 'Pointage enregistré.'];
        }, 201);
    }
}
