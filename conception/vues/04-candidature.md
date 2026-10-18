# Vues — Candidature

Écrans du module **Candidature** (`conception/Candidature.md`).
Workflow : Reçue → En présélection → Présélectionnée → Entretien prévu → En évaluation →
Retenue / Refusée / Retirée.

Endpoints utilisés :

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/public/offres/{id}` | Offre pour le formulaire public |
| POST | `/api/offres/{id}/candidatures` | Postuler |
| GET | `/api/candidatures` | Lister (filtres : offre, statut, origine) |
| GET | `/api/candidatures/{id}` | Fiche candidature |
| GET | `/api/candidatures/{id}/historique` | Journal des statuts |
| POST | `/api/candidatures/{id}/preselection` | Reçue → En présélection |
| POST | `/api/candidatures/{id}/selectionner` | → Présélectionnée (ou Refusée) |
| POST | `/api/candidatures/{id}/planifier-entretien` | → Entretien prévu |
| POST | `/api/candidatures/{id}/evaluer` | → En évaluation |
| POST | `/api/candidatures/{id}/retenir` | → Retenue |
| POST | `/api/candidatures/{id}/refuser` | → Refusée |
| POST | `/api/candidatures/{id}/retirer` | → Retirée |
| GET | `/api/salaries/{id}/eligibilite?poste={id}` | Éligibilité mobilité interne |

---

## Écran : Formulaire de candidature (public)

- **Fichier** : `pages/publiques/candidature-form.html`
- **Rôle(s)** : visiteur / candidat (offre **publiée** requise)
- **Objectif** : déposer une candidature pour une offre.

```
┌ Postuler — Dev Java (offre n°7)                                  ┐
│ Civilité / Nom * [______]   Prénom * [______]                     │
│ Email * [________________]  Téléphone [__________]                │
│ Adresse [________________________________]                        │
│ Origine * (● Externe  ○ Interne/Mobilité  ○ Recommandation)       │
│ CV * [Choisir un fichier]   Lettre de motivation [Choisir]        │
│ Lettre (texte) [_______________________________]                  │
│ ☐ J'accepte les mentions légales *                                │
│ [ Annuler ]                                   [ Envoyer ]         │
└───────────────────────────────────────────────────────────────────┘
```

| Champ | Type | Contrôles | Notes |
|---|---|---|---|
| Nom / Prénom | text | obligatoires | |
| Email | email | obligatoire, format | réutilise la fiche `candidat` existante (email unique) |
| Téléphone | tel | optionnel | |
| Adresse | text | optionnel | |
| Origine | radio | obligatoire | Externe / Interne-Mobilité / Recommandation |
| CV | file | obligatoire (suggéré) | chemin stocké dans `candidature.cv` |
| Lettre de motivation | file / textarea | optionnel | `lettre_motivation` |
| Salarié (si mobilité) | select (recherche) | requis si origine = Interne/Mobilité | déclenche le contrôle d'éligibilité |
| Mentions légales | checkbox | obligatoire | |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Envoyer | submit | `POST /api/offres/{id}/candidatures` | page de confirmation ; 409 si doublon ; 400 si offre non publiée |
| Vérifier l'éligibilité | change origine/salarié | `GET /api/salaries/{id}/eligibilite` | bandeau résultat (`ResultatEligibilite`) |

- **États** : erreur (offre fermée, email invalide, doublon, non éligible), succès (accusé de réception).
- **Navigation** : entrée = vitrine / détail offre ; sortie = confirmation.

---

## Écran : Liste des candidatures d'une offre

- **Fichier** : `pages/recrutement/candidatures.html`
- **Rôle(s)** : rh, recruteur, admin
- **Objectif** : présélectionner et suivre les postulants d'une offre.

```
┌ Offre : Dev Java - #12         Filtres : [Statut ▼] [Origine ▼] [Rechercher] ┐
│ Candidat        Email            Origine    Reçue le    Statut             Actions │
│ A. Diallo       a@mail.com       Externe    03/05       ● Reçue            Voir    │
│ B. Ndiaye       b@mail.com       Mobilité   03/05       ● Présélectionnée  Voir    │
│ …                                                                     Pagination    │
└───────────────────────────────────────────────────────────────────────────────────┘
```

| Colonne | Source |
|---|---|
| Candidat | `candidat.nom` + `prenom` |
| Email | `candidat.email` |
| Origine | `origine_candidature` (badge) |
| Reçue le | `date_candidature` |
| Statut | `statut` (badge) |
| Actions | Voir / Présélectionner / Refuser (selon statut et rôle) |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Filtrer | change/submit | `GET /api/candidatures?…` | tableau rafraîchi |
| Voir | ligne | — | ouvre `candidature.html` |
| Présélectionner | ligne (Reçue) | `POST …/{id}/preselection` | statut mis à jour |
| Refuser | ligne (modal) | `POST …/{id}/refuser` | statut mis à jour |

- **États** : vide, chargement, erreur.
- **Sécurité** : la sélection se fait sur les **critères**, jamais sur un filtre automatique de
  statut du candidat (`RH.md` §II.B.1).

---

## Écran : Fiche candidature

- **Fichier** : `pages/recrutement/candidature.html`
- **Rôle(s)** : rh, recruteur, admin
- **Objectif** : consulter le profil, les pièces et faire avancer le cycle.

```
┌ Candidature n°45 — A. Diallo                    [badge ● En présélection] ┐
│ Barre d'étapes : Reçue ─ Présélection ─ Entretien ─ Évaluation ─ Décision │
│ Profil : nom, email, téléphone, adresse                                  │
│ Pièces : CV (lien) · Lettre (lien) · Origine : Externe                    │
│ Onglets : [Détail] [Historique]                                          │
│ Zone d'actions (selon statut + rôle)                                     │
└──────────────────────────────────────────────────────────────────────────┘
```

**Zone d'actions**

| Statut | Action | Appel API |
|---|---|---|
| Reçue | Mettre en présélection | `POST …/preselection` |
| En présélection | Présélectionner | `POST …/selectionner` |
| En présélection / Présélectionnée / Entretien / Évaluation | Refuser | `POST …/refuser` (motif) |
| Présélectionnée | Planifier l'entretien | `POST …/planifier-entretien` |
| Entretien prévu | Marquer évaluée | `POST …/evaluer` |
| En évaluation | Retenir | `POST …/retenir` |
| Tout état non final | Retirer | `POST …/retirer` |

- **Liens inter-modules** : *Planifier l'entretien* ouvre le module **Entretien** ;
  *Marquer évaluée* / *Retenir* préparent **Évaluation** et **Proposition d'embauche**.
- **États** : statut final (Retenue/Refusée/Retirée) → actions masquées, badge explicite.
- **Navigation** : entrée = liste / offre ; sortie = liste, entretien, évaluation, proposition.

---

## Écran : Suivi du candidat (public)

- **Fichier** : `pages/publiques/suivi-candidature.html`
- **Rôle(s)** : candidat (accès par email + référence, ou lien reçu par mail)
- **Objectif** : consulter l'état de sa candidature (lecture seule).

- **Contenu** : offre, date de dépôt, **statut courant** (libellé neutre), prochaine étape.
- **Actions** : aucune (ou *Retirer ma candidature* tant qu'elle n'est pas finale).
- **États** : introuvable (message neutre), vide, erreur.

---

## Rendu des statuts (badges)

| Libellé | Couleur |
|---|---|
| Reçue | gris |
| En présélection | bleu |
| Présélectionnée | bleu |
| Entretien prévu | orange |
| En évaluation | orange |
| Retenue | vert |
| Refusée | rouge |
| Retirée | gris |
