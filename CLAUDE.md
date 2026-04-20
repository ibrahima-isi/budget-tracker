# Contexte du projet
Application web de gestion de budget personnel développée avec :
- Laravel 12
- Breeze + Inertia.js + Vue 3
- Tailwind CSS
- Base de données : MySQL ou PostgreSQL (les deux doivent être supportés,
  donc éviter toute syntaxe SQL brute spécifique à un SGBD,
  utiliser uniquement l'ORM Eloquent et le Query Builder Laravel)

# Structure déjà en place
## Migrations (déjà exécutées)
- users (générée par Breeze)
- categories : id, nom, couleur(hex), icone, timestamps
- budgets : id, user_id(FK), type(enum:mensuel/annuel), mois(nullable tinyint),
  annee(smallint), montant_prevu(decimal 12,2), libelle(nullable), timestamps
- revenus : id, user_id(FK), source, montant(decimal 12,2), date_revenu(date),
  mois(tinyint), annee(smallint), note(nullable), timestamps
- depenses : id, user_id(FK), budget_id(FK), categorie_id(FK),
  libelle, montant(decimal 12,2), date_depense(date), note(nullable), timestamps

## Models (déjà créés)
- User (Breeze)
- Categorie : fillable[nom, couleur, icone], hasMany Depense
- Budget : fillable[user_id, type, mois, annee, montant_prevu, libelle],
  appends[montant_depense, solde], belongsTo User, hasMany Depense,
  accesseurs getMontantDepenseAttribute() et getSoldeAttribute()
- Revenu : fillable[user_id, source, montant, date_revenu, mois, annee, note],
  casts[date_revenu:date, montant:decimal:2], belongsTo User
- Depense : fillable[user_id, budget_id, categorie_id, libelle, montant, date_depense, note],
  casts[date_depense:date, montant:decimal:2],
  belongsTo User, Budget, Categorie

## Seeders (déjà créés)
- CategorieSeeder : 10 catégories par défaut (Alimentation, Transport, Logement, etc.)
- BudgetSeeder, RevenuSeeder, DepenseSeeder : données de test

## Form Requests (déjà créés)
- StoreCategorieRequest / UpdateCategorieRequest
- StoreBudgetRequest / UpdateBudgetRequest
- StoreRevenuRequest / UpdateRevenuRequest
- StoreDepenseRequest / UpdateDepenseRequest

## Routes déjà définies ou a definir dans web.php
Route::middleware(['auth', 'verified'])->group(function () {
Route::resource('budgets',    BudgetController::class);
Route::resource('depenses',   DepenseController::class);
Route::resource('revenus',    RevenuController::class);
Route::resource('categories', CategorieController::class);
});

# Ce qui reste à implémenter

## 1. Policies (à générer et remplir)
Créer une Policy par modèle pour sécuriser les actions :
- BudgetPolicy   : view, update, delete → vérifier que budget->user_id === auth()->id()
- DepensePolicy  : update, delete       → vérifier que depense->user_id === auth()->id()
- RevenuPolicy   : update, delete       → vérifier que revenu->user_id === auth()->id()
- CategoriePolicy : pas de policy (catégories globales, pas liées à un user)
  Enregistrer les policies dans AuthServiceProvider.

## 2. Controllers (fichiers vides à remplir)

### DashboardController
- méthode index() :
    - récupérer le mois et l'année courants
    - budget mensuel du mois courant de l'user connecté
    - total dépenses du mois courant
    - total revenus du mois courant
    - solde = revenus - dépenses
    - dépenses par catégorie du mois courant (pour graphique)
    - 5 dernières dépenses
    - retourner Inertia::render('Dashboard', [...])

### BudgetController
- index() : tous les budgets de l'user, avec withCount('depenses'), paginé 10
- show(Budget $budget) : budget avec depenses.categorie chargées, authorize view
- store(StoreBudgetRequest $request) : créer avec user_id Auth::id()
- update(UpdateBudgetRequest $request, Budget $budget) : authorize update
- destroy(Budget $budget) : authorize delete, redirect budgets.index

### CategorieController
- index() : toutes les catégories avec withCount('depenses')
- store / update / destroy : CRUD simple, pas de policy

### DepenseController
- index() : dépenses de l'user avec categorie+budget, filtre optionnel par
  budget_id et categorie_id (query params), paginé 20
- store(StoreDepenseRequest) : créer avec user_id, redirect back
- update(UpdateDepenseRequest, Depense) : authorize, redirect back
- destroy(Depense) : authorize, redirect back

### RevenuController
- index() : revenus de l'user, paginé 20
- store(StoreRevenuRequest) : créer avec user_id, déduire mois+annee depuis date_revenu avec Carbon
- update(UpdateRevenuRequest, Revenu) : authorize, recalculer mois+annee
- destroy(Revenu) : authorize, redirect back

## 3. Pages Vue avec Inertia (dans resources/js/Pages/)

### Layout
Utiliser le AppLayout.vue déjà généré par Breeze.
Ajouter dans la sidebar/navbar les liens : Dashboard, Budgets, Dépenses, Revenus, Catégories.

### Dashboard.vue
Afficher :
- Cards résumé : Budget du mois, Total dépensé, Total revenus, Solde
- Barre de progression : montant dépensé vs budget prévu (%)
- Graphique donut : dépenses par catégorie (utiliser Chart.js via vue-chartjs)
- Tableau des 5 dernières dépenses

### Pages/Budgets/
- Index.vue : tableau listant tous les budgets (type, période, montant prévu,
  montant dépensé, solde, actions), bouton créer,
  modal ou slide-over pour le formulaire de création
- Show.vue  : détail d'un budget avec liste de ses dépenses,
  possibilité d'ajouter une dépense directement

### Pages/Depenses/
- Index.vue : tableau paginé avec filtres (par budget, par catégorie),
  modal pour ajout/édition rapide

### Pages/Revenus/
- Index.vue : tableau paginé, modal pour ajout/édition

### Pages/Categories/
- Index.vue : liste des catégories avec couleur et icône,
  formulaire inline ou modal pour ajout/édition/suppression

## 4. Composants Vue réutilisables (resources/js/Components/)
Créer :
- AppModal.vue       : modal générique avec slot
- AppTable.vue       : tableau générique avec slot pour colonnes
- AppBadge.vue       : badge coloré (pour afficher la catégorie avec sa couleur)
- BudgetProgress.vue : barre de progression budget (props: prevu, depense)
- StatCard.vue       : carte statistique (props: label, value, icon, color)

## 5. Helpers / Composables
- resources/js/composables/useFormatMoney.js :
  formater les montants en FCFA (XOF) avec Intl.NumberFormat
- resources/js/composables/useFlash.js :
  lire les flash messages Laravel (success/error) depuis usePage().props

## Contraintes techniques importantes
- Toujours utiliser Inertia::render() dans les controllers, jamais return view()
- Toujours utiliser le composant <Link> d'Inertia dans les vues Vue, jamais <a href>
- Les formulaires Vue utilisent useForm() de @inertiajs/vue3
- Pas de fetch/axios manuel : tout passe par Inertia (form.post, form.put, form.delete)
- Tous les montants sont en francs CFA (XOF), pas de symbole €/$
- La pagination Laravel doit être convertie avec .through() ou passée telle quelle
  à un composant Pagination.vue (déjà généré par Breeze)
- Pas de SQL brut, tout passer par Eloquent pour compatibilité MySQL/PostgreSQL
- Protéger toutes les routes avec le middleware auth + verified
- Utiliser $this->authorize() dans chaque action sensible du controller
