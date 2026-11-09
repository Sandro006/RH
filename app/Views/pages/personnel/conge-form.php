<div id="heading" class="page-heading">
    <h1>Nouvelle demande de congé</h1>
    <p class="subtitle">Remplissez le formulaire, puis envoyez votre demande — elle part en validation manager puis RH.</p>
</div>

<section id="nouvelle-demande" class="card">
    <form id="conge-form">
        <div class="conge-layout">
            <div class="conge-main">
                <div class="form-grid">
                    <label>
                        Type de congé
                        <select id="id_type_conge" required>
                            <option value="">Chargement des types…</option>
                        </select>
                    </label>

                    <label id="champ-id-salarie" class="hidden">
                        Salarié concerné (id) — pour la RH seulement
                        <input id="id_salarie" type="number" min="1" step="1">
                    </label>

                    <label>
                        Date de début
                        <input id="date_debut" type="date" required>
                    </label>

                    <label>
                        Date de fin
                        <input id="date_fin" type="date" required>
                    </label>

                    <label class="field-wide">
                        Motif
                        <textarea id="motif" rows="3" placeholder="Préciser le motif (optionnel)"></textarea>
                    </label>
                </div>

                <div class="conge-checks">
                    <label>
                        <input id="demi_journee_debut" type="checkbox"> Demi-journée le premier jour
                    </label>
                    <label>
                        <input id="demi_journee_fin" type="checkbox"> Demi-journée le dernier jour
                    </label>
                    <label>
                        <input id="justificatif_depose" type="checkbox"> Justificatif joint / déposé
                    </label>
                </div>

                <p id="conge-estimation" class="muted">Estimation : —</p>
                <p id="conge-justificatif-hint" class="muted hidden">Un justificatif est requis pour ce type de congé (sauf maladie : sous 48 h).</p>

                <div id="conge-feedback" class="state"></div>
            </div>

            <aside class="conge-solde-box">
                <div class="card">
                    <h3>Solde du salarié <span id="solde-annee"></span></h3>
                    <label>
                        Année
                        <input id="annee_solde" type="number" min="2000" max="2100" step="1">
                    </label>
                    <p id="solde-ttc" class="muted"></p>
                    <div id="soldes-list" class="state">Chargement…</div>
                </div>
            </aside>
        </div>

        <div class="form-actions">
            <a href="/personnel/mes-conges" class="button-secondary">Annuler</a>
            <button type="submit" class="button-primary">Envoyer la demande</button>
        </div>
    </form>
</section>
