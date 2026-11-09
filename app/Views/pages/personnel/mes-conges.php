<div id="heading" class="page-heading">
    <h1>Mes congés</h1>
    <p class="subtitle">Soldes disponibles et historique de vos demandes de congés.</p>
</div>

<section id="soldes" class="card">
    <div class="card-header">
        <h2>Soldes <span id="soldes-annee"></span></h2>
        <label>
            Année
            <input id="filtre-annee" type="number" min="2000" max="2100" step="1">
        </label>
    </div>
    <p id="solde-ttc" class="muted"></p>
    <div id="soldes-list" class="state">Chargement…</div>
</section>

<section id="demandes" class="card">
    <div class="card-header">
        <h2>Mes demandes</h2>
        <form id="filtres-demandes" class="filtres">
            <label>
                Statut
                <select id="filtre-statut">
                    <option value="">Tous</option>
                    <option value="EN_ATTENTE_MANAGER">En attente manager</option>
                    <option value="EN_ATTENTE_RH">En attente RH</option>
                    <option value="VALIDEE">Validée</option>
                    <option value="REFUSEE">Refusée</option>
                    <option value="ANNULEE">Annulée</option>
                </select>
            </label>
            <button type="submit" class="button-primary">Filtrer</button>
        </form>
    </div>
    <div id="demandes-list" class="state">Chargement…</div>
</section>
