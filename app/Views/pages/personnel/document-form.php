<?= view('partials/page-heading', ['heading' => 'Ajouter un document']) ?>

<section class="card">
    <form id="document-form" class="form-grid insertion-form" enctype="multipart/form-data">
        <label>Type de document
            <select name="typeDocument" required>
                <option value="">Choisir…</option>
            </select>
        </label>
        <p id="document-type-hint" class="field-wide muted"></p>
        <label>Fichier (PDF, JPEG, PNG, WEBP — 10 Mo max)
            <input name="fichier" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
        </label>
        <label>Nom affiché
            <input name="nomFichier" placeholder="Par défaut, le nom du fichier">
        </label>
        <label>Date d'émission
            <input name="dateEmission" type="date">
        </label>
        <label>Date d'expiration
            <input name="dateExpiration" type="date">
        </label>
        <label>Confidentialité
            <select name="niveauConfidentialite">
                <option value="">Par défaut (selon le type)</option>
                <option value="public">Public</option>
                <option value="interne">Interne</option>
                <option value="rh">RH</option>
                <option value="restreint">Restreint</option>
            </select>
        </label>
        <?= view('partials/form-actions', ['cancelUrl' => '/personnel/salaries', 'submitLabel' => 'Déposer']) ?>
    </form>
</section>
