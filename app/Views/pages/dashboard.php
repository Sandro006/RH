<?= view('partials/page-heading', [
    'heading'  => 'Tableau de bord',
    'subtitle' => "La vue d'ensemble de votre activité RH.",
]) ?>

<section id="metrics" class="metrics">
    <?php foreach ([
        'Demandes en attente',
        'Offres publiées',
        'Candidatures actives',
        'Entretiens à venir',
        'Propositions en cours',
        'Tâches onboarding',
    ] as $label): ?>
        <div class="metric">
            <small><?= esc($label) ?></small>
            <strong>—</strong>
        </div>
    <?php endforeach; ?>
</section>

<section class="card">
    <h2>Mes actions en attente</h2>
    <div id="actions" class="state">Chargement…</div>
</section>

<section class="card">
    <h2>Accès rapides</h2>
    <div class="shortcut-grid">
        <a class="shortcut" href="/recrutement/demande-form">Créer une demande<br><small class="muted">Démarrer un recrutement</small></a>
        <a class="shortcut" href="/personnel/salaries">Voir l'effectif<br><small class="muted">Consulter les salariés</small></a>
        <a class="shortcut" href="/personnel/onboardings">Suivre l'onboarding<br><small class="muted">Avancement des arrivées</small></a>
    </div>
</section>

<?php $sessionPersonnel = \App\Libraries\Common\SessionUtilisateur::courante(); ?>
<?php if ($sessionPersonnel && ($sessionPersonnel->idEmploye !== null || $sessionPersonnel->role === \App\Libraries\Common\Roles::SALARIE)): ?>
<section class="card personnel-pointages">
 <h2>Mon calendrier de pointage</h2>
 <p>Votre entrée est enregistrée à la connexion et votre sortie à la déconnexion, à l’heure de Madagascar.</p>
 <label>Mois <input type="month" id="mon-mois"></label>
 <p id="mon-message" role="status"></p>
 <div class="mon-calendrier-wrapper"><div id="mon-calendrier" class="mon-calendrier" aria-label="Mes pointages du mois"></div></div>
</section>
<style>
.mon-calendrier-wrapper{overflow-x:auto}.mon-calendrier{display:grid;grid-template-columns:repeat(7,minmax(95px,1fr));min-width:665px;gap:6px}.mon-calendrier .mon-entete{font-size:.8rem;color:#758397;font-weight:700;padding:.4rem}.mon-calendrier .mon-jour{min-height:105px;border:1px solid #dce3eb;border-radius:8px;padding:.6rem;background:#fff;overflow-wrap:anywhere;font-size:.8rem}.mon-calendrier .mon-jour.anomalie{background:#fff8f1;border-color:#efbd8c}.mon-calendrier .mon-jour strong,.mon-calendrier .mon-jour span{display:block;margin-bottom:.25rem}.mon-calendrier .mon-alerte{color:#a35419;font-weight:600}
</style>
<script src="/assets/js/pages/temps/mon-calendrier.js" defer></script>
<?php endif; ?>
