<?= view('partials/page-heading', ['heading' => 'Rédiger un contrat']) ?>

<section class="card">
    <form id="contract-form" class="form-grid insertion-form">
        <label>Salarié <input name="idSalarie" type="number" required></label>
        <label>Poste <input name="idPoste" type="number" required></label>
        <label>Type de contrat
            <select name="typeContratId" required>
                <option value="">Choisir…</option>
                <option value="1">CDI</option>
                <option value="2">CDD</option>
                <option value="3">Stage</option>
                <option value="4">Intérim</option>
            </select>
        </label>
        <label>Date de début <input name="dateDebut" type="date" required></label>
        <label>Date de fin <input name="dateFin" type="date"></label>
        <label>Rémunération de base <input name="salaireBase" type="number" min="0" step="0.01" required></label>
        <label class="field-wide">
            <span><input id="with-trial" name="avecPeriodeEssai" type="checkbox"> Prévoir une période d'essai</span>
        </label>
        <div id="trial-fields" class="form-grid field-wide hidden">
            <label>Catégorie
                <select name="categorie">
                    <option value="OUVRIER_EMPLOYE">Ouvrier / Employé</option>
                    <option value="AGENT_MAITRISE">Agent de maîtrise</option>
                    <option value="CADRE">Cadre</option>
                </select>
            </label>
            <label>Durée de l'essai (jours) <input name="dureeEssaiJours" type="number" min="1"></label>
        </div>
        <p class="field-wide">Une période d'essai est optionnelle. La date de fin est obligatoire pour les CDD, stages et intérims.</p>
        <?= view('partials/form-actions', ['cancelUrl' => '/personnel/contrats']) ?>
    </form>
</section>
