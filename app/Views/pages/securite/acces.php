<div id="heading" class="page-heading">
    <h1>Gestion des accès employés</h1>
    <p class="subtitle">Liste des comptes utilisateurs, réinitialisation de mots de passe et activation/désactivation.</p>
</div>

<section id="toolbar" class="card">
    <div class="toolbar-row">
        <input type="search" id="searchInput" placeholder="Rechercher un identifiant ou un employé…" class="input-search">
        <select id="roleFilter" class="input-select">
            <option value="">Tous les rôles</option>
            <option value="admin">Administrateur</option>
            <option value="rh">RH</option>
            <option value="manager">Manager</option>
            <option value="recruteur">Recruteur</option>
            <option value="direction">Direction</option>
            <option value="controle_gestion">Contrôle de gestion</option>
            <option value="salarie">Salarié</option>
        </select>
        <select id="actifFilter" class="input-select">
            <option value="">Tous les statuts</option>
            <option value="1">Actifs</option>
            <option value="0">Désactivés</option>
        </select>
        <button id="btnRefresh" class="button-secondary" type="button">Rafraîchir</button>
    </div>
</section>

<section id="liste" class="card">
    <div id="listeUtilisateurs" class="state">Chargement…</div>
</section>

<!-- Modal : mot de passe temporaire -->
<div id="modalMdp" class="modal hidden">
    <div class="modal-overlay" onclick="fermerModalMdp()"></div>
    <div class="modal-panel">
        <h2>Nouveau mot de passe temporaire</h2>
        <p id="mdpEmploye"></p>
        <div class="mdp-box">
            <code id="mdpValeur">—</code>
            <button id="btnCopierMdp" class="button-secondary" type="button">Copier</button>
        </div>
        <p class="muted">Communiquez ce mot de passe à l'employé. Il devra le changer à sa prochaine connexion.</p>
        <div class="modal-actions">
            <button class="button-primary" type="button" onclick="fermerModalMdp()">Fermer</button>
        </div>
    </div>
</div>
