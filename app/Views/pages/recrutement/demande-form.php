<?= view('partials/page-heading', [
    'heading'  => 'Nouvelle demande de recrutement',
    'subtitle' => "Décrivez le besoin : la demande est créée au statut « En cours ».",
]) ?>

<section class="card" style="max-width:900px">
    <div id="message" hidden></div>
    <form id="demande-form" class="form-grid insertion-form">
        <input type="hidden" name="id_demandeur">
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
        <label>Motif
            <input name="motif" list="motifs" required placeholder="Remplacement, création de poste…">
            <datalist id="motifs">
                <option value="Remplacement"></option>
                <option value="Création de poste"></option>
                <option value="Accroissement temporaire d'activité"></option>
                <option value="Réorganisation"></option>
            </datalist>
        </label>
        <label>Durée du contrat <input name="duree_contrat" placeholder="12 mois, indéterminée…"></label>
        <label>Personne remplacée <input name="nom_personne_remplacee"></label>
        <label>Date souhaitée <input name="date_souhaitee" type="date"></label>
        <label>Rémunération min <input name="remuneration_min" type="number" min="0" step="0.01"></label>
        <label>Rémunération max <input name="remuneration_max" type="number" min="0" step="0.01"></label>
        <label class="field-wide">Missions <textarea name="missions" placeholder="Principales missions du poste…"></textarea></label>
        <label class="field-wide">
            <span><input id="soumettre" name="soumettre" type="checkbox" checked> Soumettre immédiatement pour validation</span>
        </label>
        <?= view('partials/form-actions', [
            'cancelUrl'   => '/recrutement/demandes',
            'submitLabel' => 'Créer la demande',
        ]) ?>
    </form>
</section>
