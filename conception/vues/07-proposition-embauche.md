# Vues — Proposition d'embauche

Écrans du module **Proposition d'embauche** (`conception/PropositionEmbauche.md`).
Workflow : À envoyer → Envoyée → Acceptée / Refusée / Expirée.
Règle clé : une proposition ne peut concerner qu'une candidature **Retenue**. À l'acceptation,
elle ouvre la création du **salarié** puis du **contrat**.

Endpoints utilisés :

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/propositions` | Lister (filtres) |
| POST | `/api/propositions` | Créer depuis une candidature retenue |
| GET | `/api/propositions/{id}` | Fiche proposition |
| POST | `/api/propositions/{id}/envoyer` | Envoyer au candidat |
| POST | `/api/propositions/{id}/reponse` | Enregistrer la réponse (RH/recruteur) |
| POST | `/api/propositions/{id}/expirer` | Marquer expirée |
| GET | `/api/propositions/{id}/historique` | Journal des statuts |
| GET | `/api/candidatures/{id}` | Candidature liée (statut Retenue) |
| GET | `/api/referentiels/grille/{posteId}` | Fourchette salariale du poste |
| GET | `/api/public/propositions/{ref}` · `POST …/reponse` | Réponse en ligne du candidat |

---

## Écran : Liste des propositions

- **Fichier** : `pages/recrutement/propositions.html`
- **Rôle(s)** : rh, recruteur, direction, admin
- **Objectif** : suivre les propositions et leur réponse.

```
┌ Filtres : [Statut ▼] [Offre ▼] [Candidature ▼] [Rechercher] ┐
│ N°  Candidat   Offre       Rému. proposée  Envoyée le  Réponse      Statut       Actions │
│ 5   A. Diallo  Dev Java    42 000          05/05      06/05 Accept. ● Acceptée   Voir    │
│ 4   B. Dro  Dev Java    40 000          05/05      —            ● Envoyée    Voir    │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

| Colonne | Source |
|---|---|
| N° | `id_proposition` |
| Candidat | `candidature` → `candidat` |
| Offre | `candidature` → `offre_emploi.titre` |
| Rémunération proposée | `remuneration_proposee` |
| Envoyée le | voir historique (statut **Envoyée**) |
| Réponse | `date_reponse` + `reponse` |
| Statut | `statut` (badge) |
| Actions | Voir / Envoyer |

- **États** : vide, chargement, erreur.
- **Navigation** : entrée = menu / candidature retenue ; sortie = fiche.

---

## Écran : Créer une proposition

- **Fichier** : `pages/recrutement/proposition-form.html` (ou modal depuis la candidature)
- **Rôle(s)** : rh, recruteur, direction
- **Objectif** : proposer une rémunération à un candidat retenu.

| Champ | Type | Contrôles | Notes |
|---|---|---|---|
| Candidat / Offre | lecture | — | issus de la candidature (statut **Retenue** uniquement) |
| Rémunération proposée | number | obligatoire, dans la **grille** du poste | affiche la fourchette min–max |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Créer | submit | `POST /api/propositions` | proposition **À envoyer** ; 400 si non Retenue ou hors grille |
| Envoyer immédiatement | case à cocher | `POST …/envoyer` après création | statut **Envoyée** + notification |

- **Garde-fou UI** : le bouton n'existe que pour une candidature **Retenue** (sinon masqué).
- **États** : erreur (statut invalide, montant hors grille), succès.

---

## Écran : Fiche proposition

- **Fichier** : `pages/recrutement/proposition.html`
- **Rôle(s)** : rh, recruteur, direction, admin
- **Objectif** : suivre la proposition, enregistrer la réponse, enchaîner vers l'embauche.

```
┌ Proposition n°5 — A. Diallo / Dev Java           [badge ● Envoyée] ┐
│ Rémunération proposée : 42 000 (grille 38 000–48 000)              │
│ Date de proposition : 05/05   Réponse : —                          │
│ Onglets : [Détail] [Historique]                                    │
│ Zone d'actions (selon statut + rôle)                              │
└────────────────────────────────────────────────────────────────────┘
```

**Zone d'actions**

| Statut | Action | Appel API | UI |
|---|---|---|---|
| À envoyer | Envoyer | `POST …/envoyer` | direct + toast |
| Envoyée | Enregistrer la réponse (Acc. / Ref.) | `POST …/reponse` | modal : décision + commentaire |
| Envoyée | Marquer expirée | `POST …/expirer` | modal confirmation |
| Acceptée | Créer le salarié | `POST /api/salaries` | redirige vers la fiche salarié / contrat |

> **Acceptée** déclenche `SalarieService.creerDepuisCandidature` puis
> `ContratService.creerContrat` (enchaînement automatique côté serveur).

- **États** : final (Acceptée/Refusée/Expirée) → actions masquées selon le cas.
- **Navigation** : entrée = liste / candidature ; sortie = salarié (si acceptée).

---

## Écran : Réponse du candidat (public)

- **Fichier** : `pages/publiques/reponse-proposition.html`
- **Rôle(s)** : candidat (via lien de référence / email)
- **Objectif** : accepter ou refuser la proposition en ligne.

- **Contenu** : rappel de l'offre et de la rémunération proposée, délai de réponse.
- **Actions** : **Accepter** / **Refuser** (+ commentaire) → `POST /api/public/propositions/{ref}/reponse`.
- **États** : déjà répondu / expiré (lecture seule), succès (confirmation).

---

## Rendu des statuts (badges)

| Libellé | Couleur |
|---|---|
| À envoyer | gris |
| Envoyée | bleu |
| Acceptée | vert |
| Refusée | rouge |
| Expirée | orange |
