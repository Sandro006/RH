# Vues — Offre d'emploi

Écrans du module **Offre d'emploi** (`conception/OffreEmploi.md`).
Workflow : Brouillon → Publiée ↔ Suspendue → Clôturée ; Annulée.
Création automatique depuis une demande validée (feu vert).

Endpoints utilisés :

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/offres` | Lister (filtres) |
| GET | `/api/offres/{id}` | Fiche détaillée |
| POST | `/api/demandes/{id}/offre` | Créer l'offre depuis la demande validée |
| PATCH | `/api/offres/{id}` | Modifier (Brouillon) |
| POST | `/api/offres/{id}/publier` | Publier (canal, date de clôture) |
| POST | `/api/offres/{id}/suspendre` | Suspendre |
| POST | `/api/offres/{id}/reprendre` | Reprendre |
| POST | `/api/offres/{id}/clore` | Clôturer |
| POST | `/api/offres/{id}/annuler` | Annuler |
| GET | `/api/offres/{id}/candidatures` | Candidatures reçues |
| GET | `/api/offres/{id}/historique` | Journal des statuts |
| GET | `/api/public/offres` · `/api/public/offres/{id}` | Vitrine publique (sans session) |

---

## Écran : Liste des offres (interne)

- **Fichier** : `pages/recrutement/offres.html`
- **Rôle(s)** : rh, recruteur, admin
- **Objectif** : suivre les offres et leur état de publication.

```
┌ Filtres : [Statut ▼] [Demande ▼] [Poste ▼] [Rechercher] ┐
│ N°  Titre              Poste        Canal      Publiée le   Statut         Actions   │
│ 7   Dev Java - #12     Dev Java     Site+LinkedIn 02/05    ● Publiée       Voir      │
│ 6   Contrôleur - #11   Contrôleur   —          —           ● Brouillon     Voir/Éditer│
└────────────────────────────────────────────────────────┘
```

| Colonne | Source |
|---|---|
| N° | `id_offre` |
| Titre | `titre` |
| Poste | `demande_recrutement.id_poste` → `poste.intitule` |
| Canal | `canal_diffusion` |
| Publiée le | `date_publication` |
| Statut | `statut` (badge) |
| Candidatures | compteur `GET /candidatures` |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Filtrer | change/submit | `GET /api/offres?…` | tableau rafraîchi |
| Voir | ligne | — | ouvre `offre.html` |
| Éditer | ligne Brouillon | — | ouvre `offre-form.html` |

- **États** : vide, chargement, erreur.
- **Navigation** : entrée = menu / demande validée ; sortie = fiche.

---

## Écran : Fiche offre

- **Fichier** : `pages/recrutement/offre.html`
- **Rôle(s)** : rh, recruteur, admin
- **Objectif** : consulter l'offre, piloter la publication et suivre les candidatures.

```
┌ Offre n°7 — Dev Java - #12                       [badge ● Publiée] ┐
│ Onglets : [Détail] [Candidatures (8)] [Historique]                │
│ Lien demande : n°12  |  Canal : Site + LinkedIn                   │
│ Publiée le 02/05 — Clôture le 31/05                               │
│ Description : ……                                                  │
│ Zone d'actions (selon statut + rôle)                              │
└────────────────────────────────────────────────────────────────────┘
```

**Onglets**

| Onglet | Contenu | Source |
|---|---|---|
| Détail | Titre, description, canal, dates, lien vers la demande | `GET /api/offres/{id}` |
| Candidatures | Tableau des postulants (statut, origine) → module Candidature | `/candidatures` |
| Historique | Timeline des changements de statut | `/historique` |

**Zone d'actions**

| Statut | Action | Appel API | UI |
|---|---|---|---|
| Brouillon | Modifier | — | `offre-form.html` |
| Brouillon | Publier | `POST …/publier` | modal : canal + date de clôture |
| Brouillon | Annuler | `POST …/annuler` | modal confirmation |
| Publiée | Suspendre | `POST …/suspendre` | modal (motif) |
| Publiée | Clôturer | `POST …/clore` | modal confirmation |
| Publiée | Annuler | `POST …/annuler` | modal confirmation |
| Suspendue | Reprendre | `POST …/reprendre` | direct |
| Suspendue | Clôturer / Annuler | `POST …/clore` · `/annuler` | modal |

- **États** : statut final (Clôturée/Annulée) → actions masquées ; succès → toast + rafraîchissement.
- **Navigation** : entrée = liste ; sortie = liste, formulaire, module Candidature.

---

## Écran : Formulaire d'offre (édition Brouillon)

- **Fichier** : `pages/recrutement/offre-form.html`
- **Rôle(s)** : rh, recruteur
- **Objectif** : compléter le titre, la description et les canaux d'une offre en brouillon.

| Champ | Type | Contrôles | Notes |
|---|---|---|---|
| Titre | text | obligatoire, **unique** | suggéré : `<intitulé poste> - #<idDemande>` |
| Description | textarea | — | pré-remplie avec les missions de la demande |
| Canal de diffusion | select (multi) | — | Site carrières, LinkedIn, Interne… |
| Date de clôture | date | ≥ date de publication | |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Enregistrer | submit | `PATCH /api/offres/{id}` | toast + retour fiche |
| Publier | bouton | `POST /api/offres/{id}/publier` | passe en **Publiée** |

- **États** : erreur (titre déjà pris → HTTP 409), succès.
- **Lecture seule** : informations héritées de la demande (poste, contrat, fourchette).

---

## Écran : Vitrine publique des offres

- **Fichier** : `pages/publiques/offres.html` (+ `offre-detail.html`)
- **Rôle(s)** : visiteur (candidat non connecté)
- **Objectif** : consulter les offres **publiées** et postuler (module Candidature).

- **Liste** : cartes des offres ouvertes (`offresOuvertes()` : Publiées, non clôturées) —
  titre, poste, lieu, type de contrat, date de clôture.
- **Détail** : description, missions, profil, bouton **Postuler**.
- **Recherche** : par mot-clé / poste.
- **Actions** : *Postuler* → `pages/publiques/candidature-form.html?idOffre=…`.
- **États** : vide (« Aucune offre disponible »), chargement, erreur.
- **Sécurité** : n'affiche **que** les offres Publiées ; aucune donnée interne (demande, rémunération
  si non souhaitée).
