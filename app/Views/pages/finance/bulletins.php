<div id="heading" class="page-heading">
    <h1>Bulletins de paie</h1>
    <p class="subtitle">Générez et validez les bulletins mensuels (variables congés + éléments de paie + cotisations).</p>
</div>

<section id="calcul-bulletin" class="card">
    <h2>Calculer un bulletin</h2>
    <form id="form-bulletin" class="form-grid">
        <label>
            Salarié (rechercher)
            <input id="salarie-recherche" type="text" list="employes-datalist" placeholder="Tapez le nom du salarié…" autocomplete="off">
            <input id="id_salarie" type="hidden">
            <datalist id="employes-datalist"></datalist>
        </label>
        <label>
            Mois (YYYY-MM)
            <input id="mois" type="month" required>
        </label>
        <div class="form-actions field-wide">
            <button type="submit" class="button-primary">Calculer</button>
        </div>
    </form>
    <div id="bulletin-resultat" class="state"></div>
</section>

<section id="liste-bulletins" class="card">
    <form id="filtres-bulletins" class="filtres">
        <label>
            Mois
            <input id="mois-liste" type="month">
        </label>
        <button type="submit" class="button-primary">Charger</button>
    </form>
    <div id="bulletins-list" class="state">Chargement…</div>
</section>

<section id="elements-paie" class="card">
    <h2>Éléments variables</h2>
    <p class="muted">Primes, heures supplémentaires, retenues manuelles — intégrées au brut avant cotisations.</p>
    <form id="form-element" class="form-grid">
        <label>
            Salarié (rechercher)
            <input id="element-salarie-recherche" type="text" list="employes-datalist-2" placeholder="Tapez le nom du salarié…" autocomplete="off">
            <input id="element-id_salarie" type="hidden">
            <datalist id="employes-datalist-2"></datalist>
        </label>
        <label>
            Mois (YYYY-MM)
            <input id="element-mois" type="month" required>
        </label>
        <label>
            Type
            <select id="element-type" required>
                <option value="PRIME">Prime</option>
                <option value="HEURE_SUP">Heure supplémentaire</option>
                <option value="RETTENUE">Retenue</option>
                <option value="AVANTAGE">Avantage</option>
                <option value="AUTRE">Autre</option>
            </select>
        </label>
        <label>
            Libellé
            <input id="element-libelle" type="text" placeholder="Ex. prime rendement" required>
        </label>
        <label>
            Montant (positif = gain, négatif = retenue)
            <input id="element-montant" type="number" step="0.01" placeholder="1000.00" required>
        </label>
        <div class="form-actions field-wide">
            <button type="submit" class="button-primary">Saisir</button>
        </div>
    </form>
    <div id="element-feedback" class="state"></div>
</section>

<div id="bulletin-preview" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="bulletin-preview-title">
    <div class="modal-overlay" data-close-preview></div>
    <div class="modal-panel bulletin-preview-panel">
        <div class="bulletin-preview-head">
            <h2 id="bulletin-preview-title"><i class="fa-solid fa-file-invoice"></i> Aperçu du bulletin</h2>
            <div class="modal-actions">
                <button type="button" class="button-secondary" id="bulletin-preview-print"><i class="fa-solid fa-print"></i> Imprimer</button>
                <button type="button" class="button-secondary" data-close-preview>Fermer</button>
            </div>
        </div>
        <div id="bulletin-preview-body" class="bulletin-preview-body">
            <p class="muted">Chargement…</p>
        </div>
    </div>
</div>
