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
    public function rolesSalaries()
    {
        return $this->executer(function () {
            $this->autoriser();
            return db_connect()->table('utilisateur u')
                ->select('u.role, s.id_salarie, s.nom, s.prenom, s.matricule')
                ->join('employe e', 'e.id = u.id_employe')
                ->join('salarie s', 's.id_salarie = e.id_salarie')
                ->where('u.actif', true)->distinct()->orderBy('u.role')->orderBy('s.nom')->get()->getResultArray();
        });
    }
    public function calendrier()
    {
        return $this->chargerCalendrier(false);
    }
    public function monCalendrier()
    {
        return $this->chargerCalendrier(true);
    }
    private function chargerCalendrier(bool $personnel)
    {
        return $this->executer(function () use ($personnel) {
            if ($personnel) {
                $session = SessionUtilisateur::courante();
                $id = $session ? (new \App\Libraries\Temps\PointageAutomatique())->salarie($session) : null;
                if (!$id) throw new DonneesIncoherentesException('Votre compte n’est pas encore lié à un salarié. Contactez les RH.');
            } else {
                $this->autoriser(); $id = (int) $this->request->getGet('salarie');
            }
            $this->salarie($id);
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
            unset($data);
            $saisies = $db->table('temps_heures_saisies')->where('id_salarie', $id)->where('jour >=', $debut)->where('jour <', $fin)->get()->getResultArray();
            foreach ($saisies as $saisie) {
                $j = $saisie['jour'];
                if (!isset($jours[$j])) $jours[$j] = ['planning' => null, 'pointages' => [], 'minutes_travaillees' => 0, 'minutes_prevues' => 0, 'retard_minutes' => 0, 'heures_supplementaires_minutes' => 0, 'anomalies' => []];
                $jours[$j]['heures_saisies_minutes'] = (int) $saisie['minutes'];
            }
            ksort($jours);
            return ['jours' => $jours];
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
    public function heuresSaisies()
    {
        return $this->executer(function () {
            $this->autoriser(); $v = (array) $this->request->getJSON(true);
            $id = (int) ($v['id_salarie'] ?? 0); $this->salarie($id);
            $jour = (string) ($v['jour'] ?? ''); $minutes = filter_var($v['minutes'] ?? null, FILTER_VALIDATE_INT); $commentaire = trim((string) ($v['commentaire'] ?? ''));
            if (!$this->valide($jour, 'Y-m-d') || $minutes === false || $minutes < 1 || $minutes > 1440 || mb_strlen($commentaire) > 500) throw new DonneesIncoherentesException('Vérifiez la date, la durée (1 à 1440 minutes) et le commentaire.');
            $db = db_connect();
            $exists = $db->table('temps_heures_saisies')->where('id_salarie', $id)->where('jour', $jour)->countAllResults();
            if ($exists) throw new DonneesIncoherentesException('Une saisie existe déjà pour ce salarié et ce jour.');
            $db->table('temps_heures_saisies')->insert(['id_salarie' => $id, 'jour' => $jour, 'minutes' => $minutes, 'commentaire' => $commentaire]);
            return ['message' => 'Heures enregistrées.'];
        }, 201);
    }
    public function planningRole()
    {
        return $this->executer(function () {
            $this->autoriser(); $v = (array) $this->request->getJSON(true);
            $role = (string) ($v['role'] ?? ''); $jour = (string) ($v['jour'] ?? '');
            $debut = (string) ($v['debut'] ?? ''); $fin = (string) ($v['fin'] ?? '');
            $pause = filter_var($v['pause_minutes'] ?? 0, FILTER_VALIDATE_INT); $regime = trim((string) ($v['regime'] ?? ''));
            if (!Roles::estValide($role) || !$this->valide($jour, 'Y-m-d') || !$this->valide($debut, 'H:i') || !$this->valide($fin, 'H:i') || $fin <= $debut || $pause === false || $pause < 0 || $pause >= (strtotime($fin) - strtotime($debut)) / 60 || $regime === '' || mb_strlen($regime) > 100) throw new DonneesIncoherentesException('Vérifiez le rôle, la date, les horaires, la pause et le régime.');
            $db = db_connect();
            $employes = $db->table('utilisateur u')->select('s.id_salarie')
                ->join('employe e', 'e.id = u.id_employe')->join('salarie s', 's.id_salarie = e.id_salarie')
                ->where('u.role', $role)->where('u.actif', true)->distinct()->get()->getResultArray();
            if (!$employes) throw new DonneesIncoherentesException('Aucun salarié lié à un compte actif ne possède ce rôle.');
            $mode = (string) ($v['mode'] ?? 'mois');
            if (!in_array($mode, ['mois', 'modifier'], true)) throw new DonneesIncoherentesException('Mode invalide.');
            $dates = [];
            if ($mode === 'mois') {
                $date = new \DateTimeImmutable(substr($jour, 0, 7) . '-01');
                $limite = $date->modify('+1 month');
                while ($date < $limite) { $dates[] = $date->format('Y-m-d'); $date = $date->modify('+1 day'); }
            } else { $dates[] = $jour; }
            $db->transStart(); $crees = 0;
            foreach ($employes as $employe) foreach ($dates as $jourCourant) {
                $conflit = $mode === 'modifier' ? 'DO UPDATE SET debut = EXCLUDED.debut, fin = EXCLUDED.fin, pause_minutes = EXCLUDED.pause_minutes, regime = EXCLUDED.regime' : 'DO NOTHING';
                $db->query('INSERT INTO temps_planning (id_salarie, jour, debut, fin, pause_minutes, regime) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT (id_salarie, jour) ' . $conflit, [(int) $employe['id_salarie'], $jourCourant, $debut, $fin, $pause, $regime]);
                $crees += $db->affectedRows();
            }
            $db->transComplete();
            if (!$db->transStatus()) throw new \RuntimeException('Impossible de créer les plannings.');
            return ['crees' => $crees, 'existants' => count($employes) * count($dates) - $crees, 'total' => count($employes) * count($dates)];
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
