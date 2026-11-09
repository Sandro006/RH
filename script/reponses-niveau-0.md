# TP — Réponses Niveau 0 : Prise en main

## 0.2 — Lire le schéma relationnel

### 1\. Schéma relationnel des quatre tables

*   `categorie` (**id**, libelle)
*   `plat` (**id**, nom, prix, #id\_categorie)
*   `commande` (**id**, date\_commande, numero\_table)
*   `commande_ligne` (**id**, **#id\_commande**, **#id\_plat**, quantite, prix\_unitaire)

Les attributs en gras représentent les clés primaires. Les attributs précédés de `#` représentent les clés étrangères.

### 2\. Pourquoi `commande_ligne` contient-elle `prix_unitaire` ?

La colonne `prix_unitaire` conserve le prix du plat au moment de la commande. Elle permet de garder l'historique des prix, même si le prix actuel du plat change dans la table `plat`.

**Exemple :** un client commande un plat à 6 000 Ar. Plus tard, le prix de ce plat passe à 7 000 Ar. La commande déjà enregistrée conserve son prix de 6 000 Ar grâce à la colonne `prix_unitaire`.

### 3\. Que se passe-t-il quand on supprime une commande ?

Lorsque l'on supprime une commande, ses lignes associées dans `commande_ligne` sont automatiquement supprimées grâce à la contrainte `ON DELETE CASCADE` définie sur la clé étrangère `id_commande`.

### 4\. Trois données absurdes acceptées par le schéma actuel

*   Un prix négatif, par exemple `-500 Ar`.
*   Une date de commande située dans le futur, par exemple `2099-01-01`.
*   Une quantité négative, par exemple `-3`.

Ces données devraient être interdites par des contraintes ou des règles de validation adaptées.

## 0.3 — Interroger l'API à la main

### a) Liste des soupes

**Code HTTP : 200 OK**

La requête renvoie la liste des plats appartenant à la catégorie 2, avec leurs informations et le libellé de leur catégorie.

Réponse :

```
[
  {
    "id": 7,
    "nom": "Mi sao",
    "prix": 6500,
    "id_categorie": 2,
    "categorie": "Soupe"
  },
  {
    "id": 6,
    "nom": "Soupe chinoise",
    "prix": 6000,
    "id_categorie": 2,
    "categorie": "Soupe"
  }
]
```

### b) Création d'un plat valide

**Code HTTP : 201 Created**

```
{
  "id": 14,
  "nom": "Mofo gasy",
  "prix": 300,
  "id_categorie": 1
}
```

Le code `201` indique que la ressource a été créée avec succès. Le code `200` indique généralement qu'une requête a réussi, mais ne précise pas qu'une nouvelle ressource a été créée.

_Remarque : l'identifiant et la réponse exacte dépendent de l'état de la base de données au moment de l'exécution._

### c) Création d'un plat invalide

**Code HTTP : 422 Unprocessable Entity**

```
{
  "message": "Données invalides.",
  "errors": {
    "nom": "Le nom du plat est obligatoire.",
    "prix": "Le prix doit être un nombre entier (en Ariary).",
    "id_categorie": "Choisissez une catégorie."
  }
}
```

La requête échoue parce que le nom est vide, le prix n'est pas un nombre entier et la catégorie n'est pas renseignée.

### d) Suppression d'un plat présent dans une commande

**Code HTTP : 409 Conflict**

```
{
  "message": "Ce plat figure déjà dans des commandes, il ne peut pas être supprimé.",
  "errors": {}
}
```

PostgreSQL bloque la suppression parce que le plat est encore référencé dans les lignes de commande par une clé étrangère qui interdit cette suppression.

Dans le contrôleur `Api/Plats.php`, le bloc `catch` transforme l'exception en réponse HTTP `409` :

```php
try {
    $this->plats->delete($id);
} catch (\Throwable $e) {
    // Le plat apparaît dans des commandes :
    // la clé étrangère bloque la suppression.
    return $this->erreur(
        'Ce plat figure déjà dans des commandes, il ne peut pas être supprimé.',
        409
    );
}

return $this->respondDeleted(['id' => (int) $id]);
```

### e) Une commande inexistante

**Code HTTP : 404 Not Found**

```
{
  "message": "Commande 999 introuvable.",
  "errors": {}
}
```

Ce code indique que la commande portant l'identifiant `999` n'existe pas dans la base de données.

### 5\. Pourquoi la requête b renvoie-t-elle 201 et non 200 ?

Le code `201 Created` indique qu'une nouvelle ressource a été créée avec succès. Le code `200 OK` indique simplement que la requête a réussi. Le code `201` est donc plus précis pour une création.

### 6\. Qui bloque la suppression du plat ?

C'est PostgreSQL qui bloque la suppression en raison de la contrainte de clé étrangère. PHP intercepte ensuite l'exception et renvoie le code HTTP `409 Conflict` au client.

La ligne qui transmet ce code est :

```php
return $this->erreur(
    'Ce plat figure déjà dans des commandes, il ne peut pas être supprimé.',
    409
);
```

### 7\. Créer une commande avec deux Romazava et un Café

Exemple de requête `curl` :

```
curl -X POST http://localhost:8080/api/commandes \
  -H "Content-Type: application/json" \
  -d '{
    "numero_table": 6,
    "lignes": [
      { "id_plat": 4, "quantite": 2 },
      { "id_plat": 10, "quantite": 1 }
    ]
  }'
```

Cette requête crée une commande pour la table 6 contenant deux Romazava et un Café, à condition que les identifiants `4` et `10` correspondent bien à ces plats dans la base de données.

## 0.4 — Suivre une requête de bout en bout

| # | Étape | Fichier | Fonction / ligne |
| --- | --- | --- | --- |
| 1 | Le formulaire est soumis | `frontend/src/components/PlatForm.vue` | `enregistrer()` |
| 2 | La requête HTTP est construite | `frontend/src/api.js` | `requete()` |
| 3 | Vite transmet `/api/...` au port 8080 | `frontend/vite.config.js` et `frontend/src/api.js` | Configuration du proxy et appel HTTP |
| 4 | CodeIgniter choisit le contrôleur | `backend/app/Config/Routes.php` | Déclaration de la route |
| 5 | Le contrôleur lit le JSON | `backend/app/Controllers/Api/Plats.php` | Lecture du corps de la requête |
| 6 | Les règles de validation sont vérifiées | `backend/app/Controllers/Api/Plats.php` | Validation des données |
| 7 | La ligne est insérée | `backend/app/Controllers/Api/Plats.php` | Appel du modèle pour enregistrer le plat |
| 8 | Le formulaire prévient l'écran parent | `frontend/src/components/PlatForm.vue` | `emit(...)` |
| 9 | L'écran recharge la liste | `frontend/src/views/CarteView.vue` | `charger()` |

_Les numéros de ligne exacts doivent être vérifiés dans les fichiers du projet, car ils peuvent varier selon la version du code._

### 8\. Où se trouve le code métier de création d'une commande ?

Le code métier se trouve dans `CommandesService.php`. La création d'une commande implique l'insertion dans deux tables liées : `commande` et `commande_ligne`.

Le service centralise ces opérations afin de séparer la logique métier du contrôleur `Commandes.php`. Il est également recommandé d'utiliser une transaction SQL pour garantir la cohérence des données si l'une des insertions échoue.

## 0.5 — Repérer les notions de Vue

Les exemples suivants indiquent les fichiers et les éléments à rechercher. Les lignes exactes sont à relever dans le code source du projet.

| Notion | Exemple à rechercher | Explication |
| --- | --- | --- |
| `ref()` | `PlatForm.vue` ou `CarteView.vue` | Permet de créer une variable réactive dont la modification met à jour l'interface. |
| `computed()` | `Panier.vue` ou `CommandeView.vue` | Permet de calculer une valeur à partir d'autres données réactives et de la recalculer lorsque celles-ci changent. |
| `v-for` | `PlatForm.vue` ou `CarteView.vue` | Répète un élément HTML pour chaque élément d'une liste, par exemple chaque catégorie ou chaque plat. |
| `v-if` / `v-else` | Un composant Vue contenant une condition d'affichage | Affiche un élément si une condition est vraie et peut afficher une alternative sinon. |
| `v-model` | `PlatForm.vue` | Lie un champ de formulaire à une variable réactive dans les deux sens. |
| `defineProps` | `PlatForm.vue` | Permet au composant de recevoir des données de son parent, par exemple la liste des catégories. |
| `defineEmits` / `emit` | `PlatForm.vue` ou `Panier.vue` | Permet au composant d'envoyer un événement au parent pour lui signaler une action. |
| `onMounted` | `CarteView.vue` ou un autre composant | Exécute du code lorsque le composant est monté, par exemple pour charger les données initiales. |

### 9\. Pourquoi `Panier.vue` émet-il `plus` et `moins` ?

`Panier.vue` émet les événements `plus` et `moins` afin de signaler au composant parent que l'utilisateur souhaite modifier une quantité. Il ne modifie pas lui-même cette quantité, ce qui permet de centraliser la gestion de l'état.

La quantité est réellement modifiée par le composant parent qui écoute ces événements, ici `CommandeView.vue`.

### 10\. À quoi sert le `watch` dans `PlatForm.vue` ?

Le `watch` surveille les changements d'une donnée réactive, notamment le plat sélectionné pour modification. Il permet de mettre à jour les champs du formulaire avec les nouvelles valeurs du plat.

Sans ce `watch`, lorsque l'utilisateur cliquerait sur « Modifier » pour un autre plat, le formulaire pourrait conserver les données du plat précédemment sélectionné au lieu d'afficher les nouvelles valeurs.

## 0.6 — Premières modifications

### a) Modifier le nom de la gargote

Le nom « Chez Bao, Analakely » doit être remplacé par le nom choisi dans le fichier du projet où ce texte est défini.

**Nouveau nom choisi :** Chez \[nom de votre choix\].

Cette modification permet de personnaliser le nom affiché dans l'interface sans changer le fonctionnement de l'application.

### b) Ajouter la catégorie « Goûter »

La catégorie est ajoutée directement dans la base de données avec la requête SQL suivante :

```
INSERT INTO categorie (libelle) VALUES ('Goûter');
```

Après l'exécution de cette requête, la catégorie existe dans la base de données. Il suffit ensuite de recharger les données de l'interface pour qu'elle apparaisse dans la liste déroulante.

### 11\. Pourquoi la catégorie apparaît-elle sans modifier le code Vue ?

La route `GET /api/categories`, déclarée dans `backend/app/Config/Routes.php`, appelle la méthode `Categories::index` et renvoie les catégories présentes dans la base de données.

Dans `frontend/src/views/CarteView.vue`, la fonction `charger()` appelle `api.categories.lister()` et stocke le résultat dans la variable réactive `categories`. Cette liste est ensuite transmise au composant `PlatForm` avec la prop `:categories="categories"`.

Enfin, dans `frontend/src/components/PlatForm.vue`, la directive `v-for="c in categories"` parcourt la liste reçue et génère une option du menu déroulant pour chaque catégorie.

La catégorie « Goûter » apparaît donc automatiquement après le rechargement des données, car l'interface récupère dynamiquement les catégories depuis l'API au lieu de les définir directement dans le code Vue.

---

## Conclusion

Ce niveau m'a permis de comprendre la structure de la base de données, le fonctionnement de l'API REST, le parcours d'une requête entre le frontend et le backend ainsi que les principales notions de Vue.js. Il m'a également permis de découvrir comment les clés étrangères protègent la cohérence des données et comment l'interface récupère dynamiquement les informations depuis la base de données.