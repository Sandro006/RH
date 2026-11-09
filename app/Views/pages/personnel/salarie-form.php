<?= view('partials/page-heading', ['heading' => 'Créer un salarié depuis une candidature']) ?>

<section class="card">
    <form id="salary-form" class="form-grid">
        <label class="field-wide">Candidature
            <select name="idCandidature" required>
                <option value="">Chargement…</option>
            </select>
        </label>
        <p class="field-wide">Seules les candidatures dont la proposition d'embauche est acceptée et qui n'ont pas encore de salarié sont proposées.</p>
        <?= view('partials/form-actions', ['cancelUrl' => '/personnel/salaries', 'submitLabel' => 'Créer le salarié']) ?>
    </form>
</section>
