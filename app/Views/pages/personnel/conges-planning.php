<div id="heading" class="page-heading">
    <h1>Planning des congés</h1>
    <p class="subtitle">Qui est absent, jour par jour, dans votre service.</p>
</div>

<section id="planning" class="card">
    <form id="planning-filtres" class="filtres">
        <label>
            Service (rechercher)
            <input id="planning-service-texte" type="text" list="services-datalist" placeholder="Tapez le nom du service…" autocomplete="off">
            <input id="planning-service" type="hidden">
            <datalist id="services-datalist"></datalist>
        </label>
        <label>
            Du
            <input id="planning-debut" type="date">
        </label>
        <label>
            Au
            <input id="planning-fin" type="date">
        </label>
        <button type="submit" class="button-primary">Charger</button>
    </form>

    <div class="conge-titre-cal">
        <button type="button" id="cal-moins" class="button-secondary">‹ Mois précédent</button>
        <strong id="cal-titre">…</strong>
        <button type="button" id="cal-plus" class="button-secondary">Mois suivant ›</button>
        <button type="button" id="cal-auj" class="button-secondary">Aujourd’hui</button>
    </div>

    <div class="conge-legend">
        <span class="cal-chip conge">Congé</span>
        <span class="cal-chip maladie">Maladie</span>
        <span class="cal-chip injustifiee">Injustifié</span>
        <span class="cal-chip autre">Mission / autre</span>
    </div>

    <div id="calendrier" class="conge-cal" role="grid" aria-label="Calendrier des congés"></div>

    <div id="planning-list" class="state">Chargement…</div>
</section>
