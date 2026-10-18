# Vues — Proposition d'embauche

Écrans du module **Proposition d'embauche** (`conception/PropositionEmbauche.md`).
Workflow : À envoyer → Envoyée → En négociation ↔ Envoyée → Acceptée / Refusée / Expirée.
Règle clé : une proposition ne peut concerner qu'une candidature **Retenue**. Avant d'accepter,
le candidat peut formuler des **revendications** (contre-propositions). À l'acceptation, elle ouvre
la création du **salarié** puis du **contrat**.

Endpoints utilisés :

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/propositions` | Lister (filtres) |
| POST | `/api/propositions` | Créer depuis une candidature retenue |
| GET | `/api/propositions/{id}` | Fiche proposition |
| POST | `/api/propositions/{id}/envoyer` | Envoyer au candidat |
| POST | `/api/propositions/{id}/reponse` | Enregistrer la réponse (RH/recruteur) |
| POST | `/api/propositions/{id}/expirer` | Marquer expirée |
| GET | `/api/propositions/{id}/revendications` | Fil de négociation |
| POST | `/api/propositions/{id}/revendications` | Soumettre une revendication (candidat) |
| POST | `/api/revendications/{rid}/reponse` | Répondre à une revendication (RH) |
| POST | `/api/revendications/{rid}/retirer` | Retirer une revendication (candidat) |
| POST | `/api/propositions/{id}/contre-proposer` | Émettre une contre-proposition (RH) |
| GET | `/api/propositions/{id}/historique` | Journal des statuts |
| GET | `/api/candidatures/{id}` | Candidature liée (statut Retenue) |
| GET | `/api/referentiels/grille/{posteId}` | Fourchette salariale du poste |
| GET | `/api/referentiels/natures-revendication` | Natures de revendication |
| GET | `/api/public/propositions/{ref}` · `POST …/reponse` | Réponse en ligne du candidat |
| POST | `/api/public/propositions/{ref}/revendications` | Revendication en ligne du candidat |

---

## Écran : Liste des propositions

- **Fichier** : `pages/recrutement/propositions.html`
- **Rôle(s)** : rh, recruteur, direction, admin
- **Objectif** : suivre les propositions et leur réponse.

```
┌ Filtres : [Statut ▼] [Offre ▼] [Candidature ▼] [Rechercher] ┐
│ N°  Candidat   Offre       Rému. proposée  Envoyée le  Réponse      Statut       Actions │
│ 5   A. Diallo  Dev Java    42 000          05/05      06/05 Accept. ● Acceptée   Voir    │
│ 4   B. Ndiaye  Dev Java    40 000          05/05      —            ● Envoyée    Voir    │
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
| Date d'embauche proposée | date | optionnel | négociable via les revendications |

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
┌ Proposition n°5 — A. Diallo / Dev Java           [badge ● En négociation] ┐
│ Rémunération proposée : 42 000 (grille 38 000–48 000)                    │
│ Date d'embauche proposée : 02/06/2026                                     │
│ Date de proposition : 05/05   Réponse : —                                 │
│ Onglets : [Détail] [Négociation (2)] [Historique]                         │
│ Zone d'actions (selon statut + rôle)                                      │
└───────────────────────────────────────────────────────────────────────────┘
```

**Zone d'actions**

| Statut | Action | Appel API | UI |
|---|---|---|---|
| À envoyer | Envoyer | `POST …/envoyer` | direct + toast |
| Envoyée | Enregistrer la réponse (Acc. / Ref.) | `POST …/reponse` | modal : décision + commentaire |
| Envoyée / En négociation | Voir la négociation | — | onglet **Négociation** |
| En négociation | Contre-proposer | `POST …/contre-proposer` | modal : rémunération + date |
| Envoyée / En négociation | Marquer expirée | `POST …/expirer` | modal confirmation |
| Acceptée | Créer le salarié | `POST /api/salaries` | redirige vers la fiche salarié / contrat |

> **Accepter** est désactivé tant qu'une revendication est **Soumise** ou **En discussion**.
> **Acceptée** déclenche `SalarieService.creerDepuisCandidature` puis
> `ContratService.creerContrat` (enchaînement automatique côté serveur).

- **États** : final (Acceptée/Refusée/Expirée) → actions masquées selon le cas.
- **Navigation** : entrée = liste / candidature ; sortie = salarié (si acceptée).

---

## Onglet : Négociation (dans la fiche proposition)

- **Fichier** : `pages/recrutement/proposition.html` (onglet)
- **Rôle(s)** : rh, recruteur, direction, admin
- **Objectif** : suivre les revendications du candidat et y répondre.

```
┌ Négociation — Proposition n°5                                          ┐
│ #1 CANDIDAT  Rémunération   45 000      « Je demande 45 000 »          │
│    ● Soumise                                           [Accepter][Refuser][Discuter] │
│ #2 CANDIDAT  Date d'embauche 01/07/2026 « Disponible à partir de juillet » │
│    ● En discussion                                     [Accepter][Refuser]            │
└────────────────────────────────────────────────────────────────────────┘
```

| Colonne | Source |
|---|---|
| Sens | `sens` (badge Candidat / Entreprise) |
| Nature | `nature_revendication.designation` |
| Valeur demandée | `valeur_demandee` |
| Message | `commentaire` |
| Statut | `statut` (badge) |
| Réponse | `reponse`, `date_reponse` |

| Action | Déclencheur | Appel API | Résultat |
|---|---|---|---|
| Accepter / Refuser / Discuter | bouton ligne | `POST /api/revendications/{rid}/reponse` | statut de la revendication mis à jour |
| Contre-proposer | bouton global | `POST …/contre-proposer` | proposition repasse **Envoyée** |
| Retirer | ligne (candidat) | `POST …/{rid}/retirer` | statut **Retirée** |

- **Règles UI** : tant qu'une revendication reste **Soumise**/**En discussion**, le bouton
  *Accepter la proposition* est désactivé.
- **États** : vide (« Aucune revendication »), chargement, erreur, succès.

---

## Écran : Réponse du candidat (public)

- **Fichier** : `pages/publiques/reponse-proposition.html`
- **Rôle(s)** : candidat (via lien de référence / email)
- **Objectif** : accepter ou refuser la proposition en ligne.

- **Contenu** : rappel de l'offre et de la rémunération proposée, délai de réponse.
- **Actions** : **Accepter** / **Refuser** (+ commentaire) → `POST /api/public/propositions/{ref}/reponse`.
- **Négociation** : bouton **Faire une revendication** ouvrant un mini-formulaire :
  - Nature (select : Rémunération, Date d'embauche, Temps de travail, Avantages, Poste/Intitulé, Autre)
  - Valeur demandée (text : montant ou date)
  - Message (textarea)
  → `POST /api/public/propositions/{ref}/revendications` ; la proposition passe **En négociation**.
- **Affichage** : fil des revendications déjà soumises et de leurs réponses.
- **États** : déjà répondu / expiré (lecture seule), succès (confirmation). Le bouton *Accepter*
  reste désactivé tant qu'une revendication est **Soumise** ou **En discussion**.

---

## Rendu des statuts (badges)

| Libellé | Couleur |
|---|---|
| À envoyer | gris |
| Envoyée | bleu |
| En négociation | orange |
| Acceptée | vert |
| Refusée | rouge |
| Expirée | orange |

### Statuts de revendication

| Libellé | Couleur |
|---|---|
| Soumise | bleu |
| En discussion | orange |
| Acceptée | vert |
| Refusée | rouge |
| Retirée | gris |
