<div id="heading" class="page-heading">
    <h1>Absences (RH)</h1>
    <p class="subtitle">Saisies directes et régularisation des absences.</p>
</div>

<section id="nouvelle-absence" class="card">
    <h2>Saisie d’une absence</h2>
    <form id="absence-form" class="form-grid">
        <label>
            Salarié (rechercher)
            <input id="salarie-recherche" type="text" list="employes-datalist" placeholder="Tapez le nom…" autocomplete="off">
            <input id="id_salarie" type="hidden">
            <datalist id="employes-datalist"></datalist>
        </label>
        <label>
            Type
            <select id="code_type_absence" required>
                <option value="CONGE">Congé</option>
                <option value="MALADIE">Maladie</option>
                <option value="ABSENCE_INJUSTIFIEE">Absence injustifiée</option>
                <option value="MISSION">Mission</option>
                <option value="RETARD">Retard</option>
                <option value="AUTORISATION">Autorisation</option>
            </select>
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
            Commentaire
            <input id="commentaire" type="text" placeholder="Contexte de la saisie">
        </label>
        <label>
            <input id="justifiee" type="checkbox"> Justifiée
        </label>
        <div class="form-actions field-wide">
            <button type="submit" class="button-primary">Saisir</button>
        </div>
    </form>
    <div id="absence-feedback" class="state"></div>
</section>

<section id="liste-absences" class="card">
    <form id="absences-filtres" class="filtres">
        <label>
            Salarié (rechercher)
            <input id="filtre-salarie-texte" type="text" list="employes-datalist-filtre" placeholder="Tapez le nom…" autocomplete="off">
            <input id="filtre-salarie" type="hidden">
            <datalist id="employes-datalist-filtre"></datalist>
        </label>
        <label>
            Type
            <select id="filtre-type">
                <option value="">Tous</option>
                <option value="CONGE">Congé</option>
                <option value="MALADIE">Maladie</option>
                <option value="ABSENCE_INJUSTIFIEE">Absence injustifiée</option>
                <option value="MISSION">Mission</option>
                <option value="RETARD">Retard</option>
                <option value="AUTORISATION">Autorisation</option>
            </select>
        </label>
        <label>
            Statut
            <select id="filtre-justifiee">
                <option value="">Tous</option>
                <option value="1">Justifiées</option>
                <option value="0">Non justifiées</option>
            </select>
        </label>
        <button type="submit" class="button-primary">Filtrer</button>
    </form>

    <div id="absences-list" class="state">Chargement…</div>
</section>
