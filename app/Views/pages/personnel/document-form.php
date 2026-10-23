<?= view('partials/page-heading', ['heading' => 'Ajouter un document']) ?>

<section class="card">
    <form id="document-form" class="form-grid">
        <label>Type de document
            <select name="typeDocument" required>
                <option value="">Choisir…</option>
            </select>
        </label>
        <label>Fichier <input name="fichier" type="file" required></label>
        <label>Nom du fichier <input name="nomFichier" required></label>
        <?= view('partials/form-actions', ['cancelUrl' => '/personnel/salaries', 'submitLabel' => 'Déposer']) ?>
    </form>
</section>
