<div id="heading" class="page-heading">
    <h1>Soldes des congés (RH)</h1>
    <p class="subtitle">Soldes de tous les salariés, alertes et ajustements.</p>
</div>

<section id="alertes" class="card">
    <h2>Alertes</h2>
    <div id="alertes-list" class="state">Chargement…</div>
</section>

<section id="soldes-rh" class="card">
    <form id="soldes-filtres" class="filtres">
        <label>
            Année
            <input id="annee" type="number" min="2000" max="2100" step="1">
        </label>
        <label>
            Type
            <select id="type_conge"><option value="">Tous</option></select>
        </label>
        <label>
            Recherche (matricule/nom)
            <input id="recherche" type="search" placeholder="Dupont…">
        </label>
        <button type="submit" class="button-primary">Filtrer</button>
    </form>

    <div id="soldes-list" class="state">Chargement…</div>
</section>

<section id="actions-rh" class="card">
    <h2>Ajustement de solde</h2>
    <form id="ajustement-form" class="form-grid">
        <label>
            Salarié (rechercher)
            <input id="ajustement-salarie-texte" type="text" list="employes-datalist" placeholder="Tapez le nom…" autocomplete="off">
            <input id="ajustement-salarie" type="hidden">
            <datalist id="employes-datalist"></datalist>
        </label>
        <label>
            Type
            <select id="ajustement-type"><option value="">Sélectionner…</option></select>
        </label>
        <label>
            Année
            <input id="ajustement-annee" type="number" min="2000" max="2100" step="1" required>
        </label>
        <label>
            Jours (positif/négatif)
            <input id="ajustement-jours" type="number" step="0.5" required>
        </label>
        <label class="field-wide">
            Commentaire
            <input id="ajustement-commentaire" type="text" placeholder="Motif de l’ajustement">
        </label>
        <div class="form-actions field-wide">
            <button type="submit" class="button-primary">Ajuster</button>
        </div>
    </form>

    <h2>Clôture d’année</h2>
    <form id="cloture-form" class="filtres">
        <label>
            Année
            <input id="cloture-annee" type="number" min="2000" max="2100" step="1" value="">
        </label>
        <button type="submit" class="button-secondary">Clôturer (report + expiration)</button>
    </form>
    <div id="conges-rh-feedback" class="state"></div>
</section>
