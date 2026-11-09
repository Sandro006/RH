<link rel="stylesheet" href="/assets/css/grille.css">

<main class="grille-page">

    <header class="grille-header">
        <div>
            <?php
            $mode = filter_input(INPUT_GET, 'mode') ?? 'create';
            $id = filter_input(INPUT_GET, 'id') ?? null;
            $titre = $mode === 'create' ? 'Créer une grille salariale' : 'Modifier la grille salariale';
            $btn = $mode === 'create' ? 'Créer la grille' : 'Mettre à jour la grille';
            ?>
            <h1><?= $titre ?></h1>
            <p>Quatrechettes de rémunération par poste et par rang.</p>
        </div>
        <span class="grille-badge">Gestion Finance</span>
    </header>

    <div class="grille-accent"></div>

    <form id="grille-form">

        <!-- 01 : Poste et rang -->
        <section class="grille-section">
            <div class="section-heading">
                <div class="section-number">01</div>
                <div>
                    <h2>Poste et rang</h2>
                    <p>Sélectionnez le poste et la catégorie de rang.</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="idPoste">Poste *</label>
                    <select id="idPoste" name="idPoste" required>
                        <option value="">Sélectionner un poste</option>
                        <?php
                        $postes = $data['postes'] ?? [];
                        foreach ($postes as $p):
                            $posteId = $p['id_poste'] ?? $p['id'] ?? null;
                            $posteLabel = $p['intitule'] ?? $p['libelle'] ?? ''; ?>
                            <option value="<?= $posteId ?>" <?= ($posteId !== null && (string) $posteId === (string) ($data['idPoste'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $posteLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="rang">Rang *</label>
                    <select id="rang" name="rang" required>
                        <option value="">Sélectionner un rang</option>
                        <?php
                        $rangs = $data['rangs'] ?? [];
                        foreach ($rangs as $r):
                            $rangId = $r['id'] ?? null;
                            $rangLabel = $r['designation'] ?? $r['libelle'] ?? ''; ?>
                            <option value="<?= $rangId ?>" <?= ($rangId !== null && (string) $rangId === (string) ($data['rang'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $rangLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <!-- 02 : Fourchette de salaire -->
        <section class="grille-section">
            <div class="section-heading">
                <div class="section-number">02</div>
                <div>
                    <h2>Fourchette de salaire</h2>
                    <p>Définissez les salaires minimum et maximum.</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="salaireMin">Salaire minimum *</label>
                    <input type="number" id="salaireMin" name="salaireMin" min="0" step="any" placeholder="Ex. : 30000" required>
                    <span class="form-help">Saisissez le salaire minimum prévu pour ce poste et ce rang.</span>
                </div>

                <div class="form-group">
                    <label for="salaireMax">Salaire maximum *</label>
                    <input type="number" id="salaireMax" name="salaireMax" min="0" step="any" placeholder="Ex. : 58000" required>
                    <span class="form-help">Saisissez le salaire maximum prévu pour ce poste et ce rang.</span>
                </div>
            </div>
        </section>

        <!-- 03 : Période de validité -->
        <section class="grille-section">
            <div class="section-heading">
                <div class="section-number">03</div>
                <div>
                    <h2>Période de validité</h2>
                    <p>Dates de début et de fin de la grille.</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="dateDebut">Date de début *</label>
                    <input type="date" id="dateDebut" name="dateDebut" required>
                    <span class="form-help">Date de prise d'effet de la grille.</span>
                </div>

                <div class="form-group">
                    <label for="dateFin">Date de fin</label>
                    <input type="date" id="dateFin" name="dateFin">
                    <span class="form-help">Laisser vide si la grille est en cours.</span>
                </div>
            </div>
        </section>

        <!-- 04 : Mode et identification -->
        <section class="grille-section">
            <div class="section-heading">
                <div class="section-number">04</div>
                <div>
                    <h2>Identification</h2>
                    <p>Grille salariale en cours ou historique.</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Mode <span class="required">*</span></label>
                    <input type="hidden" name="mode" value="<?= $mode ?>">
                    <input type="hidden" name="idGrille" value="<?= $id ?? '' ?>">
                    <select name="actif" required>
                        <option value="1" <?= ($mode === 'create' || ($actif ?? '1') == '1') ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= ($actif ?? '0') == '0' ? 'selected' : '' ?>>Historisée</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Actions -->
        <div class="grille-actions">
            <a href="/personnel/grille" class="button-secondary">Annuler</a>
            <button type="submit" class="button-primary">
                <?= $mode === 'create' ? 'Enregistrer la grille' : 'Mettre à jour la grille' ?>
            </button>
        </div>

    </form>

</main>