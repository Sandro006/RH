<div id="heading" class="page-heading">
    <h1>Créer un utilisateur</h1>
    <p class="subtitle">Créer un nouveau compte de connexion pour un salarié du département.</p>
</div>

<section id="formulaire" class="card">
    <form id="formCreerUtilisateur" novalidate>
        <div class="form-grid">
            <div class="field-wide">
                <label for="identifiant">Identifiant <small>(connexion)</small></label>
                <input type="text" id="identifiant" name="identifiant" placeholder="ex: tdurand" required>
            </div>

            <div>
                <label for="motDePasse">Mot de passe <small>(temporaire, 8 caractères minimum)</small></label>
                <input type="text" id="motDePasse" name="motDePasse" placeholder="••••••••" required>
            </div>

            <div>
                <label for="role">Rôle</label>
                <select id="role" name="role" required>
                    <option value="">Sélectionner un rôle</option>
                    <option value="admin">Administrateur</option>
                    <option value="rh">RH</option>
                    <option value="manager">Manager</option>
                    <option value="recruteur">Recruteur</option>
                    <option value="direction">Direction</option>
                    <option value="controle_gestion">Contrôle de gestion</option>
                    <option value="salarie">Salarié</option>
                </select>
            </div>

            <div>
                <label for="idSalarie">Salarié lié</label>
                <select id="idSalarie" name="idSalarie" required>
                    <option value="">Sélectionner un salarié</option>
                </select>
                <p class="muted" id="salarieInfo"></p>
            </div>

            <div>
                <label for="idPoste">Poste</label>
                <select id="idPoste" name="idPoste" required>
                    <option value="">Sélectionner un poste</option>
                </select>
            </div>

            <div class="field-half">
                <label class="checkbox">
                    <input type="checkbox" name="actif" value="1" checked>
                    Compte actif
                </label>
            </div>

            <div class="field-wide">
                <div class="form-actions">
                    <button type="submit" class="button-primary">Créer l'utilisateur</button>
                    <button type="button" class="button-secondary" onclick="window.location.href='/departement/informatique'">Annuler</button>
                </div>
            </div>
        </div>

        <div id="message" class="mt-md"></div>
    </form>
</section>
