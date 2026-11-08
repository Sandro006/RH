<div id="heading" class="page-heading dossier-heading">
    <div>
        <h1>Fiche salarié</h1>
        <p class="muted">Chargement du dossier…</p>
    </div>
</div>

<?= view('partials/tabs', [
    'active' => 'identity',
    'tabs'   => [
        'identity'     => 'Identité',
        'contrats'     => 'Contrats',
        'affectations' => 'Affectations',
        'documents'    => 'Documents',
        'carriere'     => 'Carrière',
        'historique'   => 'Historique',
    ],
]) ?>

<section id="content" class="card">Chargement…</section>

<div id="replace-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="replace-title">
    <form id="replace-form" class="card form-grid insertion-form" enctype="multipart/form-data">
        <h2 id="replace-title" class="field-wide">Remplacer un document</h2>
        <p id="replace-context" class="field-wide muted"></p>

        <label class="field-wide">Nouveau fichier (PDF, JPEG, PNG, WEBP — 10 Mo max)
            <input name="fichier" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
        </label>
        <label>Nom affiché
            <input name="nomFichier" maxlength="255" placeholder="Par défaut, le nom du fichier">
        </label>
        <label>Date d'émission
            <input name="dateEmission" type="date">
        </label>
        <label>Date d'expiration
            <input name="dateExpiration" type="date">
        </label>
        <label>Confidentialité
            <select name="niveauConfidentialite">
                <option value="">Conserver le niveau actuel</option>
                <option value="public">Public</option>
                <option value="interne">Interne</option>
                <option value="rh">RH</option>
                <option value="restreint">Restreint</option>
            </select>
        </label>

        <div class="actions field-wide">
            <button type="button" class="button button-secondary" data-close-modal>Annuler</button>
            <button type="submit" class="button-primary">Remplacer</button>
        </div>
    </form>
</div>
