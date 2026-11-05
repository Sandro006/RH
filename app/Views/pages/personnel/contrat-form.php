<link rel="stylesheet" href="/css/contrat.css">

<main class="contract-page">

    <header class="contract-header">
        <div>
            <h1>Créer un contrat</h1>
            <p>Gérez les informations et les conditions du contrat du salarié.</p>
        </div>
        <span class="contract-badge">Gestion RH</span>
    </header>

    <div class="contract-accent"></div>

    <form id="contract-form">

        <!-- 01 : Salarié et poste -->
        <section class="contract-section">
            <div class="section-heading">
                <div class="section-number">01</div>
                <div>
                    <h2>Informations du salarié</h2>
                    <p>Identifiez le salarié concerné par le contrat.</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group full-width">
                    <label for="idSalarie">Salarié *</label>
                    <select id="idSalarie" name="idSalarie" required>
                        <option value="">Sélectionner un salarié</option>
                        <!-- Options générées depuis les données RH -->
                    </select>
                </div>

                <div class="form-group">
                    <label for="idPoste">Poste *</label>
                    <select id="idPoste" name="idPoste" required>
                        <option value="">Sélectionner un poste</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- 02 : Conditions du contrat -->
        <section class="contract-section">
            <div class="section-heading">
                <div class="section-number">02</div>
                <div>
                    <h2>Conditions du contrat</h2>
                    <p>Définissez le type et la durée du contrat.</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="typeContratId">Type de contrat *</label>
                    <select id="typeContratId" name="typeContratId" required>
                        <option value="">Choisir un type</option>
                        <option value="1">CDI</option>
                        <option value="2">CDD</option>
                        <option value="3">Stage</option>
                        <option value="4">Intérim</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="dateDebut">Date de début *</label>
                    <input type="date" id="dateDebut" name="dateDebut" required>
                </div>

                <div class="form-group">
                    <label for="dateFin">Date de fin</label>
                    <input type="date" id="dateFin" name="dateFin">
                    <span class="form-help">
                        À renseigner selon le type de contrat.
                    </span>
                </div>
            </div>
        </section>

        <!-- 03 : Rémunération -->
        <section class="contract-section">
            <div class="section-heading">
                <div class="section-number">03</div>
                <div>
                    <h2>Rémunération</h2>
                    <p>Définissez le salaire de base du salarié.</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="salaireBase">Salaire de base *</label>
                    <input type="number" id="salaireBase" name="salaireBase" min="0" step="any" placeholder="Ex. : 750000" required>
                    <span class="form-help">
                        Saisissez le montant prévu au contrat.
                    </span>
                </div>
            </div>
        </section>

        <!-- 04 : Période d'essai -->
        <section class="contract-section">
            <div class="section-heading">
                <div class="section-number">04</div>
                <div>
                    <h2>Période d'essai</h2>
                    <p>Configurez la période d'essai si nécessaire.</p>
                </div>
            </div>

            <div class="trial-card">
                <input type="checkbox" id="with-trial" name="avecPeriodeEssai">
                <label for="with-trial">
                    <span class="trial-title">
                        Prévoir une période d'essai
                    </span>
                    <span class="trial-description">
                        Activez cette option pour préciser ses conditions.
                    </span>
                </label>
            </div>

            <div id="trial-fields" class="hidden">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="categorie">Catégorie</label>
                        <select id="categorie" name="categorie">
                            <option value="">Choisir une catégorie</option>
                            <option value="OUVRIER_EMPLOYE">Ouvrier / Employé</option>
                            <option value="AGENT_MAITRISE">Agent de maîtrise</option>
                            <option value="CADRE">Cadre</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="dureeEssaiJours">
                            Durée de l'essai (jours)
                        </label>
                        <input type="number" id="dureeEssaiJours" name="dureeEssaiJours" min="1" step="1" placeholder="Ex. : 30">
                    </div>
                </div>
            </div>
        </section>

        <!-- Actions -->
        <div class="contract-actions">
            <a href="/personnel/contrats" class="btn btn-secondary">
                Annuler
            </a>

            <button type="submit" class="btn btn-primary">
                Enregistrer le contrat
            </button>
        </div>

    </form>
</main>
