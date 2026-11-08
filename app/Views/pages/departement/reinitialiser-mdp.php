<div id="heading" class="page-heading">
    <h1>Réinitialiser mot de passe</h1>
    <p class="subtitle">Générer un mot de passe temporaire pour un employé.</p>
</div>

<section id="formulaire" class="card">
    <div class="form-grid">
        <div class="field-wide">
            <label for="utilisateur">Utilisateur <small>(identifiant)</small></label>
            <select id="utilisateur" name="utilisateur" required>
                <option value="">Sélectionner un utilisateur</option>
            </select>
        </div>

        <div class="field-wide">
            <div class="form-actions">
                <button type="button" class="button-primary" id="btnReset">Réinitialiser le mot de passe</button>
                <button type="button" class="button-secondary" onclick="window.location.href='/departement/informatique'">Annuler</button>
            </div>
        </div>
    </div>

    <div id="modalResultat" class="modal hidden">
        <div class="modal-overlay" onclick="fermerModalResultat()"></div>
        <div class="modal-panel">
            <h2>Mot de passe temporaire</h2>
            <p id="resultatIdentifiant"></p>
            <div class="mdp-box">
                <code id="resultatMdp">—</code>
                <button id="btnCopierResultat" class="button-secondary" type="button">Copier</button>
            </div>
            <p class="muted">Communiquez ce mot de passe à l'employé. Il devra le changer à sa prochaine connexion.</p>
            <div class="modal-actions">
                <button class="button-primary" type="button" onclick="fermerModalResultat()">Fermer</button>
            </div>
        </div>
    </div>

    <div id="message" class="mt-md"></div>
</section>
