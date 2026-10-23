<div id="heading" class="page-heading">
    <h1>Modifier la demande</h1>
    <p class="muted">Seules les demandes « En cours » ou « À corriger » peuvent être modifiées.</p>
</div>

<div id="message" hidden></div>

<section class="card" style="max-width:900px">
    <form id="demande-form" class="form-grid">
        <label>Poste
            <select name="id_poste" required>
                <option value="">Chargement…</option>
            </select>
        </label>
        <label>Type de contrat
            <select name="type_contrat" required>
                <option value="">Chargement…</option>
            </select>
        </label>
        <label>Motif <input name="motif" required placeholder="Remplacement, création de poste…"></label>
        <label>Durée du contrat <input name="duree_contrat" placeholder="12 mois, indéterminée…"></label>
        <label>Personne remplacée <input name="nom_personne_remplacee"></label>
        <label>Date souhaitée <input name="date_souhaitee" type="date"></label>
        <label>Rémunération min <input name="remuneration_min" type="number" min="0" step="0.01"></label>
        <label>Rémunération max <input name="remuneration_max" type="number" min="0" step="0.01"></label>
        <label class="field-wide">Missions <textarea name="missions" placeholder="Principales missions du poste…"></textarea></label>
        <?= view('partials/form-actions', [
            'cancelUrl'   => '/recrutement/demandes',
            'submitLabel' => 'Enregistrer les modifications',
        ]) ?>
    </form>
</section>
